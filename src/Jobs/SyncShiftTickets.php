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
use Throwable;
use RuntimeException;
use webdophp\WebkassaIntegration\Mall\WebkassaJobFailed;
use webdophp\WebkassaIntegration\Services\WebkassaService;

class SyncShiftTickets implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $cashboxNumber;
    protected int $shiftId;
    protected int $shiftNumber;

    public function __construct(string $cashboxNumber, int $shiftId, int $shiftNumber)
    {
        $this->cashboxNumber = $cashboxNumber;
        $this->shiftId       = $shiftId;
        $this->shiftNumber   = $shiftNumber;
    }

    /**
     * @throws ConnectionException
     */
    public function handle(WebkassaService $service): void
    {
        Log::info("Syncing tickets for cashbox={$this->cashboxNumber}, shift={$this->shiftNumber}");

        $tickets = $service->getAllTickets($this->cashboxNumber, $this->shiftNumber);

        if (isset($tickets['error']) && $tickets['error']) {
            throw new RuntimeException("Webkassa error: {$tickets['message']}");
        }

        foreach ($tickets as $ticket) {
            ProcessTicket::dispatch($this->shiftId, $ticket);
            usleep(1500);
        }

        Log::info("Tickets dispatched for cashbox={$this->cashboxNumber}, shift={$this->shiftNumber}, count=" . count($tickets));
    }

    public function failed(Throwable $exception): void
    {
        if (config('webkassa-integration.error_log', false)) {
            Log::error("SyncShiftTickets job failed", [
                'cashboxNumber' => $this->cashboxNumber,
                'shiftId' => $this->shiftId,
                'shiftNumber' => $this->shiftNumber,
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
    }
}
