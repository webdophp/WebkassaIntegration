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
use webdophp\WebkassaIntegration\Mall\WebkassaJobFailed;
use webdophp\WebkassaIntegration\Models\Cashbox;
use webdophp\WebkassaIntegration\Services\WebkassaService;


/**
 * Class SyncCashboxes
 *
 * This class is responsible for synchronizing the list of cashboxes available
 * through the WebkassaService. It implements the ShouldQueue interface to
 * allow asynchronous queue-based execution.
 *
 * The handle method retrieves available cashboxes from the Webkassa service
 * and updates or creates corresponding records in the database.
 *
 * If the job encounters any issues, the failed method handles the exception
 * by logging the error details and optionally sending an email notification
 * if configured.
 */
class SyncCashboxes implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var int
     */
    public int $tries = 3;


    /**
     * Получение списка касс
     * @param WebkassaService $service
     * @return void
     * @throws ConnectionException
     */
    public function handle(WebkassaService $service): void
    {
        $response = $service->getAvailableCashboxes();
        if (isset($response['error']) && $response['error']) {
            throw new RuntimeException("Webkassa error [{$response['status']}]: {$response['message']}");
        }
        $cashboxes = $response['Data'] ?? [];
        foreach ($cashboxes as $cb) {
            Cashbox::updateOrCreate(
                [
                    'cashbox_unique_number' => $cb['CashboxUniqueNumber']
                ],
                [
                    'xin' => $cb['Xin'],
                    'organization_name' => $cb['OrganizationName']
                ]
            );
        }
    }


    /**
     * Handles the failure of the job by logging the error details and sending a notification email if configured.
     *
     * @param Throwable $exception The exception thrown during the job execution.
     * @return void
     */
    public function failed(Throwable $exception): void
    {
        if(config('webkassa-integration.error_log', false)) {
            Log::error('GetCashboxes job failed', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString()
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
