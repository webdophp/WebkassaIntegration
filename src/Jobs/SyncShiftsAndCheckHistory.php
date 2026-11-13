<?php

namespace webdophp\WebkassaIntegration\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;
use webdophp\WebkassaIntegration\Mail\WebkassaJobFailed;
use webdophp\WebkassaIntegration\Models\Cashbox;
use webdophp\WebkassaIntegration\Models\RepeatedTicket;
use webdophp\WebkassaIntegration\Services\TelegramErrorService;

/**
 * Class SyncShiftsAndCheckHistory
 *
 * Точка входа для дополнительной синхронизации смен и чеков через
 * Cashbox/ShiftHistory + Check/History. Проходит по уже известным кассам
 * (таблица cashboxes) и запускает для каждой SyncCashboxShiftsHistory.
 *
 * Метод /Cashbox/ShiftHistory не поддерживает FromDate/ToDate, поэтому диапазон
 * дат из RepeatedTicket (тот же, что использует WebkassaSyncDayShiftsAndTickets)
 * применяется на уровне фильтрации уже полученных смен в SyncCashboxShiftsHistory,
 * чтобы не обрабатывать всю историю целиком.
 */
class SyncShiftsAndCheckHistory implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var int
     */
    public int $tries = 1;

    /**
     * @var int
     */
    public int $timeout = 120;

    /**
     * @var string
     */
    protected string $baseUrl;

    /**
     * @var string
     */
    protected string $login;

    /**
     * @var string
     */
    protected string $password;

    /**
     * @param string $baseUrl
     * @param string $login
     * @param string $password
     */
    public function __construct(string $baseUrl, string $login, string $password)
    {
        $this->baseUrl = $baseUrl;
        $this->login = $login;
        $this->password = $password;
    }

    /**
     * @return void
     */
    public function handle(): void
    {
        $repeatedTicket = RepeatedTicket::query()->where('login', $this->login)->first();
        if (!$repeatedTicket) {
            throw new RuntimeException("Webkassa error: repeated_ticket is null");
        }

        $from = $repeatedTicket->from?->toDateString();
        $to = $repeatedTicket->to?->toDateString();

        Cashbox::chunkById(50, function ($cashboxes) use ($from, $to) {
            foreach ($cashboxes as $cashbox) {
                SyncCashboxShiftsHistory::dispatch($this->baseUrl, $this->login, $this->password, $cashbox->id, $from, $to);
            }
        });
    }

    /**
     * @param Throwable $exception
     * @return void
     */
    public function failed(Throwable $exception): void
    {
        try {
            if (config('webkassa-integration.error_log', false)) {
                Log::error('SyncShiftsAndCheckHistory job failed', [
                    'error' => $exception->getMessage(),
                    'trace' => $exception->getTraceAsString(),
                ]);
            }

            if (config('webkassa-integration.error_mail', false)) {
                Mail::to(config('webkassa-integration.mail_to'))->send(
                    new WebkassaJobFailed(
                        $exception->getCode().': '.$exception->getMessage(),
                        $exception->getTraceAsString()
                    )
                );
            }

            if (config('webkassa-integration.telegram_error.error_telegram', false)) {
                $telegram = app(TelegramErrorService::class);
                $telegram->MessageError(
                    "<b>Произошла ошибка при импорте из Webkassa (Shift/Check History)</b>\n" .
                    "<b>Сервис:</b> " . config('webkassa-integration.service_name') . "\n" .
                    "<b>Код ошибки:</b> " . $exception->getCode() . "\n" .
                    "<b>Ошибка:</b> " . htmlspecialchars($exception->getMessage())
                );
            }
        } catch (Throwable $e) {
            Log::error('Mail sending failed SyncShiftsAndCheckHistory', ['error' => $e->getMessage()]);
        }
    }
}
