<?php

namespace webdophp\WebkassaIntegration\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use webdophp\WebkassaIntegration\Mail\WebkassaJobFailed;
use Throwable;
use webdophp\WebkassaIntegration\Services\TelegramErrorService;

class ProcessTicketPositions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int $tries */
    public int $tries = 1;

    /** @var int $timeout */
    public int $timeout = 120;

    /** @var int $ticketId */
    protected int $ticketId;

    /** @var array $positions */
    protected array $positions;


    /**
     * @param int $ticketId
     * @param array $positions
     */
    public function __construct(int $ticketId, array $positions)
    {
        $this->ticketId = $ticketId;
        $this->positions = $positions;
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function handle(): void
    {
        $now = now();
        DB::transaction(function () use ($now) {
            $rows = [];
            foreach ($this->positions as $position) {
                $rows[] = [
                    'ticket_id'      => $this->ticketId,
                    'position_name'  => $position['PositionName'] ?? '',
                    'count'          => $position['Count'] ?? 0,
                    'price'          => $position['Price'] ?? 0,
                    'discount_tenge' => $position['DiscountTenge'] ?? 0,
                    'markup'         => $position['Markup'] ?? 0,
                    'sum'            => $position['Sum'] ?? 0,
                    'tax_percent'    => $position['TaxPercent'] ?? 0,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ];
            }

            if ($rows) {
                DB::table('ticket_positions')->insert($rows);
            }
        });
    }

    /**
     * @param Throwable $exception
     * @return void
     */
    public function failed(Throwable $exception): void
    {
        try{
            if (config('webkassa-integration.error_log', false)) {
                Log::error("ProcessTicketPositions job failed", [
                    'ticketId' => $this->ticketId,
                    'error' => $exception->getMessage(),
                    'trace' => $exception->getTraceAsString(),
                ]);
            }

            if (config('webkassa-integration.error_mail', false)) {
                Mail::to(config('webkassa-integration.mail_to'))->send(
                    new WebkassaJobFailed(
                        $exception->getCode() . ': ' . $exception->getMessage(),
                        $exception->getTraceAsString()
                    )
                );
            }

            if (config('webkassa-integration.telegram_error.error_telegram', false)) {
                //Отправляем ошибку в телеграм канал
                $telegram = app(TelegramErrorService::class);
                $telegram->MessageError(
                    "<b>Произошла ошибка при импорте из Webkassa</b>\n" .
                    "<b>Сервис:</b> " . config('webkassa-integration.service_name') . "\n" .
                    "<b>Код ошибки:</b> " . $exception->getCode() . "\n" .
                    "<b>Ошибка:</b> " . $exception->getMessage()
                );
            }
        }
        catch (Exception $e) {
            Log::error('Mail sending failed ProcessTicketPositions', ['error' => $e->getMessage()]);
        }
    }
}
