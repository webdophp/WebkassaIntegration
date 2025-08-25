<?php

namespace webdophp\WebkassaIntegration\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;
use RuntimeException;
use webdophp\WebkassaIntegration\Mail\WebkassaJobFailed;
use webdophp\WebkassaIntegration\Services\WebkassaService;

class SyncShiftTickets implements ShouldQueue
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
    protected string $cashboxNumber;

    /**
     * @var int
     */
    protected int $shiftId;

    /**
     * @var int
     */
    protected int $shiftNumber;

    /**
     * @param string $cashboxNumber
     * @param int $shiftId
     * @param int $shiftNumber
     */
    public function __construct(string $cashboxNumber, int $shiftId, int $shiftNumber)
    {
        $this->cashboxNumber = $cashboxNumber;
        $this->shiftId       = $shiftId;
        $this->shiftNumber   = $shiftNumber;
    }

    /**
     * @param WebkassaService $service
     * @return void
     * @throws Throwable
     */
    public function handle(WebkassaService $service): void
    {
        if (config('webkassa-integration.error_log', false)) {
            Log::info("Syncing tickets for cashbox={$this->cashboxNumber}, shift={$this->shiftNumber}");
        }

        $tickets = $service->getAllTickets($this->cashboxNumber, $this->shiftNumber);

        if (isset($tickets['error']) && $tickets['error']) {
            throw new RuntimeException("Webkassa error: {$tickets['message']}");
        }

        foreach ($tickets as $index => $ticket) {
            if (config('webkassa-integration.error_log', false)) {
                Log::debug("SyncShiftTickets Dispatching ticket", [
                    'cashbox' => $this->cashboxNumber ?? null,
                    'shift' => $this->shiftNumber ?? null,
                    'index' => $index ?? null,
                    'number' => $ticket['Number'] ?? null,
                    'order' => $ticket['OrderNumber'] ?? null,
                ]);
            }
            ProcessTicket::dispatch($this->shiftId, $ticket)->delay(now()->addMilliseconds($index * 100));
        }
        if (config('webkassa-integration.error_log', false)) {
            Log::info("Tickets dispatched for cashbox={$this->cashboxNumber}, shift={$this->shiftNumber}, count=" . count($tickets));
        }
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
        catch (Exception $e) {
            Log::error('Mail sending failed SyncShiftTickets', ['error' => $e->getMessage()]);
        }
    }
}
