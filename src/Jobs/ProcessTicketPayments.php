<?php

namespace webdophp\WebkassaIntegration\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;
use webdophp\WebkassaIntegration\Mail\WebkassaJobFailed;
use webdophp\WebkassaIntegration\Models\Ticket;
use webdophp\WebkassaIntegration\Services\TelegramErrorService;

class ProcessTicketPayments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int $tries */
    public int $tries = 1;

    /** @var int $timeout */
    public int $timeout = 120;

    /** @var int $ticketId */
    protected int $ticketId;

    /** @var array $payments */
    protected array $payments;

    /**
     * @param int $ticketId
     * @param array $payments
     */
    public function __construct(int $ticketId, array $payments)
    {
        $this->ticketId = $ticketId;
        $this->payments = $payments;
    }

    /**
     * @throws Throwable
     */
    public function handle(): void
    {
        $now = now();
        DB::transaction(function () use ($now) {
            $rows = [];
            foreach ($this->payments as $payment) {
                $rows[] = [
                    'ticket_id'         => $this->ticketId,
                    'sum'               => $payment['Sum'] ?? 0,
                    'payment_type'      => Ticket::PAYMENT_TYPES[trim($payment['PaymentTypeName'] ?? '')] ?? null,
                    'payment_type_name' => $payment['PaymentTypeName'] ?? null,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ];
            }

            if ($rows) {
                DB::table('ticket_payments')->insert($rows);
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
                Log::error("ProcessTicketPayments job failed", [
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
                    "<b>Ошибка:</b> " . htmlspecialchars($exception->getMessage())
                );
            }
        }
        catch (Throwable $e) {
            Log::error('Mail sending failed ProcessTicketPayments', ['error' => $e->getMessage()]);
        }
    }
}
