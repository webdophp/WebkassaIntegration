<?php

namespace webdophp\WebkassaIntegration\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use webdophp\WebkassaIntegration\Mall\WebkassaJobFailed;
use webdophp\WebkassaIntegration\Services\WebkassaService;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Bus\Dispatchable;
use Throwable;
use Exception;

class AuthorizeWebkassa implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    /**
     * Авторизация
     * @throws ConnectionException
     * @throws Exception
     */
    public function handle(WebkassaService $service): void
    {
        $token = $service->authenticate();
        if (!$token) {
            throw new Exception('Webkassa authorization failed.', 1001);
        }
        FetchEmployees::dispatch($token)->delay(now()->addSecond());
    }


    /**
     * @param Throwable $exception
     * @return void
     */
    public function failed(Throwable $exception): void
    {
        if(config('webkassa-integration.error_log', false)) {
            Log::error('AuthorizeWebkassa job failed', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
        }

        if(config('webkassa-integration.error_mail', false)) {
            Mail::to(config('webkassa-integration.mail_to'))->send(
                new WebkassaJobFailed(
                    $exception->getCode().': '.$exception->getMessage(),
                    $exception->getTraceAsString()
                )
            );
        }
    }
}