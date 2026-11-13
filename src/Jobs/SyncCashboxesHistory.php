<?php

namespace webdophp\WebkassaIntegration\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;
use webdophp\WebkassaIntegration\Mail\WebkassaJobFailed;
use webdophp\WebkassaIntegration\Models\Cashbox;
use webdophp\WebkassaIntegration\Services\TelegramErrorService;
use webdophp\WebkassaIntegration\Services\WebkassaCheckHistoryService;

/**
 * Class SyncCashboxesHistory
 *
 * Синхронизация списка касс через дополнительный метод Webkassa (POST /Cashboxes).
 * Это отдельный источник данных, дополняющий (но не заменяющий) существующий SyncCashboxes.
 * Метод /Cashboxes не возвращает xin/organization_name, поэтому эти поля
 * заполняются только при создании новой записи и не перезаписываются при обновлении.
 */
class SyncCashboxesHistory implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var int
     */
    public int $tries = 3;

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
     * @throws ConnectionException
     */
    public function handle(): void
    {
        $service = new WebkassaCheckHistoryService($this->baseUrl, $this->login, $this->password);

        $response = $service->getCashboxes();
        if (isset($response['error']) && $response['error']) {
            throw new RuntimeException("Webkassa error [{$response['status']}]: {$response['message']}");
        }

        $cashboxes = $response['Data']['List'] ?? [];

        foreach ($cashboxes as $cb) {
            if (empty($cb['UniqueNumber'])) {
                continue;
            }

            Cashbox::firstOrCreate(
                ['cashbox_unique_number' => $cb['UniqueNumber']],
                [
                    'xin' => '',
                    'organization_name' => $cb['Name'] ?? '',
                ]
            );
        }
    }

    /**
     * @param Throwable $exception
     * @return void
     */
    public function failed(Throwable $exception): void
    {
        try {
            if (config('webkassa-integration.error_log', false)) {
                Log::error('SyncCashboxesHistory job failed', [
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
                    "<b>Произошла ошибка при импорте касс из Webkassa (Cashboxes)</b>\n" .
                    "<b>Сервис:</b> " . config('webkassa-integration.service_name') . "\n" .
                    "<b>Код ошибки:</b> " . $exception->getCode() . "\n" .
                    "<b>Ошибка:</b> " . htmlspecialchars($exception->getMessage())
                );
            }
        } catch (Throwable $e) {
            Log::error('Mail sending failed SyncCashboxesHistory', ['error' => $e->getMessage()]);
        }
    }
}
