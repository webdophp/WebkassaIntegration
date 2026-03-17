<?php

namespace webdophp\WebkassaIntegration\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use webdophp\WebkassaIntegration\Mail\WebkassaJobFailed;
use webdophp\WebkassaIntegration\Models\Cashbox;
use webdophp\WebkassaIntegration\Services\TelegramErrorService;
use webdophp\WebkassaIntegration\Services\WebkassaService;
use Throwable;


class SyncShiftsAndTickets implements ShouldQueue
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
     * @var WebkassaService $service
     */
    public WebkassaService $service;

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
     * @var string|null
     */
    protected ?string $day;

    /**
     * @param string $baseUrl
     * @param string $login
     * @param string $password
     * @param string|null $day
     */
    public function __construct(string $baseUrl, string $login, string $password, ?string $day = null)
    {
        $this->baseUrl = $baseUrl;
        $this->login = $login;
        $this->password = $password;
        $this->day = $day;
    }

    /**
     * Контрольная летна за смену
     * @return void
     */
    public function handle(): void
    {

        Cashbox::chunkById(50, function ($cashboxes) {
            foreach ($cashboxes as $cashbox) {
                // диспатчим отдельную задачу на каждую кассу
                SyncCashboxShifts::dispatch($this->baseUrl, $this->login, $this->password, $cashbox->id, $this->day);
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
                Log::error("SyncShiftTickets job failed", [
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
            Log::error('Mail sending failed SyncShiftTickets', ['error' => $e->getMessage()]);
        }
    }
}
