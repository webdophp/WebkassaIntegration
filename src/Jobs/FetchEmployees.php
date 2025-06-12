<?php

namespace webdophp\WebkassaIntegration\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Mail;
use webdophp\WebkassaIntegration\Mall\WebkassaJobFailed;
use webdophp\WebkassaIntegration\Services\WebkassaService;
use Throwable;
use Exception;

class FetchEmployees implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var string $token - Токен сессии Webkassa
     */
    protected string $token;


    /**
     * @param string $token
     */
    public function __construct(string $token) {
        $this->token = $token;
    }


    /**
     * Получаем список кассиров
     * @throws ConnectionException
     * @throws Exception
     */
    public function handle(WebkassaService $service): void
    {
        $cashiers = $service->setToken($this->token)->getEmployees();

        if (!$cashiers) {
            throw new Exception('Error in getting the cashier list.', 1002);
        }


        foreach ($cashiers as $cashier) {
            foreach ($cashier['Cashboxes'] as $cashbox) {
                $cashboxNumbers[] = $cashbox['CashboxUniqueNumber'];
            }
        }

        if (!isset($cashboxNumbers)) {
            throw new Exception('Error in getting the CashboxUniqueNumber list.', 1002);
        }

        $cashboxes = array_values(array_unique($cashboxNumbers));

        FetchLastShiftForCashboxes::dispatch($this->token, $cashboxes)->delay(now()->addSecond());
    }


    /**
     * @param Throwable $exception
     * @return void
     */
    public function failed(Throwable $exception): void
    {
        if(config('webkassa-integration.error_log', false)) {
            Log::error('FetchEmployees job failed', [
                'token' => $this->token,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
        }

        if(config('webkassa-integration.error_mail', false)) {
            Mail::to(config('webkassa-integration.mail_to'))->send(
                new WebkassaJobFailed(
                    $exception->getCode().': '.$exception->getMessage(),
                    $exception->getTraceAsString(),
                    $this->token
                )
            );
        }
    }
}