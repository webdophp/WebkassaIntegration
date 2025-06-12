<?php

namespace webdophp\WebkassaIntegration\Jobs;
use Illuminate\Bus\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Mail;
use webdophp\WebkassaIntegration\Mall\WebkassaJobFailed;
use webdophp\WebkassaIntegration\Models\CashboxShift;
use webdophp\WebkassaIntegration\Services\WebkassaService;
use Illuminate\Support\Facades\Log;
use Throwable;


class FetchLastShiftForCashboxes implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var string $token - Токен сессии Webkassa
     */
    protected string $token;

    /**
     * @var array $cashboxes - Список кассиров
     */
    protected array $cashboxes;

    /**
     * @param string $token
     * @param array $cashboxes
     */
    public function __construct(string $token,  array $cashboxes) {
        $this->token = $token;
        $this->cashboxes = $cashboxes;
    }

    /**
     * Получение списка смен кассы
     * @throws ConnectionException
     */
    public function handle(WebkassaService $service): void
    {
        foreach ($this->cashboxes as $cashbox) {
            sleep(2); // Пауза 2 секунда
            // Получаем последнюю смену из таблицы по unique_number
            $lastShiftNumber = CashboxShift::where('cashbox_unique_number', $cashbox)
                ->orderByDesc('shift_number')
                ->value('shift_number');

            // Если ничего не найдено, начинаем с 0
            $skip = 0;
            if(isset($lastShiftNumber)) {
                // Если lastShiftNumber больше нуля, то нужно всегда брать последнею смену вдруг новый чек появился
                $skip = $lastShiftNumber > 0 ? $lastShiftNumber - 1 : 0;
            }

            // Получаем список смен с пропуском предыдущих
            $shifts = $service->setToken($this->token)->getShiftHistory($cashbox, $skip);

            if (empty($shifts)) {
                continue;
            }

            foreach ($shifts as $shift) {
                sleep(5);// Пауза 5 секунда
                if (!isset($shift['ShiftNumber']) || !$shift['ShiftNumber']) {
                    continue;
                }
                FetchControlTape::dispatch($this->token, $cashbox, $shift['ShiftNumber'])->delay(now()->addSecond());
            }
        }
    }

    /**
     * @param Throwable $exception
     * @return void
     */
    public function failed(Throwable $exception): void
    {
        if(config('webkassa-integration.error_log', false)) {
            Log::error('FetchLastShiftForCashboxes job failed', [
                'token' => $this->token,
                'cashboxes' => $this->cashboxes,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
        }

        if(config('webkassa-integration.error_mail', false)) {
            Mail::to(config('webkassa-integration.mail_to'))->send(
                new WebkassaJobFailed(
                    $exception->getCode().': '.$exception->getMessage(),
                    $exception->getTraceAsString(),
                    $this->token,
                    $this->cashboxes
                )
            );
        }
    }
}
