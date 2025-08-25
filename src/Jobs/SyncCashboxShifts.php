<?php

namespace webdophp\WebkassaIntegration\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;
use webdophp\WebkassaIntegration\Mail\WebkassaJobFailed;
use webdophp\WebkassaIntegration\Models\Cashbox;
use webdophp\WebkassaIntegration\Models\Shift;
use webdophp\WebkassaIntegration\Services\WebkassaService;

class SyncCashboxShifts implements ShouldQueue
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
     * @var int
     */
    private int $cashboxId;

    /**
     * @param int $cashboxId
     */
    public function __construct(int $cashboxId)
    {
        $this->cashboxId = $cashboxId;
    }

    /**
     * Синхронизация смен по кассе
     * @throws ConnectionException
     */
    public function handle(WebkassaService $service): void
    {
        $cashbox = Cashbox::findOrFail($this->cashboxId);

        if (config('webkassa-integration.error_log', false)) {
            Log::info("Syncing shifts for cashbox {$cashbox->id} ({$cashbox->cashbox_unique_number})");
        }

        $response = $service->getShifts($cashbox->cashbox_unique_number);
        if (isset($response['error']) && $response['error']) {
            throw new RuntimeException("Webkassa error [{$response['status']}]: {$response['message']}");
        }

        $shiftsData = $response['Data']['Shifts'] ?? [];

        foreach ($shiftsData as $shiftItem) {
            $shift = Shift::updateOrCreate(
                [
                    'cashbox_id'   => $cashbox->id,
                    'shift_number' => $shiftItem['ShiftNumber'],
                ],
                [
                    'open_date'  => Carbon::parse($shiftItem['OpenDate']),
                ]
            );

            // если смена закрыта и тикеты уже есть — пропускаем
            if ($shift->close_date && $shift->tickets()->exists()) {
                continue;
            }

            // диспатчим отдельный джоб
            SyncShiftTickets::dispatch(
                $cashbox->cashbox_unique_number,
                $shift->id,
                $shift->shift_number
            )->delay(now()->addMilliseconds(2000));;

            if (!empty($shiftItem['CloseDate'])) {
                $shift->update([
                    'close_date' => Carbon::parse($shiftItem['CloseDate']),
                ]);
            }
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
                Log::error("SyncCashboxShifts job failed", [
                    'cashbox_id' => $this->cashboxId,
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
        } catch (Exception $e) {
            Log::error('Mail sending failed SyncCashboxShifts', ['error' => $e->getMessage()]);
        }
    }
}
