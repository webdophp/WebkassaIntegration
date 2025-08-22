<?php

namespace webdophp\WebkassaIntegration\Jobs;

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
use webdophp\WebkassaIntegration\Mall\WebkassaJobFailed;
use webdophp\WebkassaIntegration\Models\Cashbox;
use webdophp\WebkassaIntegration\Models\Shift;
use webdophp\WebkassaIntegration\Models\Ticket;
use webdophp\WebkassaIntegration\Services\WebkassaService;

class SyncCashboxShiftsAndTickets implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    private int $cashboxId;

    public function __construct(int $cashboxId)
    {
        $this->cashboxId = $cashboxId;
    }

    /**
     * Синхронизируем одну кассу
     * @throws ConnectionException
     */
    public function handle(WebkassaService $service): void
    {
        $cashbox = Cashbox::findOrFail($this->cashboxId);

        Log::info("Syncing cashbox {$cashbox->id} ({$cashbox->cashbox_unique_number})");

        $response = $service->getShifts($cashbox->cashbox_unique_number);
        if (isset($response['error']) && $response['error']) {
            throw new RuntimeException("Webkassa error [{$response['status']}]: {$response['message']}");
        }

        $shiftsData = $response['Data']['Shifts'] ?? [];

        foreach ($shiftsData as $shiftItem) {
            $shift = Shift::updateOrCreate(
                [
                    'cashbox_id' => $cashbox->id,
                    'shift_number' => $shiftItem['ShiftNumber'],
                ],
                [
                    'open_date' => Carbon::parse($shiftItem['OpenDate']),
                    'close_date' => isset($shiftItem['CloseDate']) ? Carbon::parse($shiftItem['CloseDate']) : null,
                ]
            );

            if ($shift->close_date && $shift->tickets()->exists()) {
                continue;
            }

            $tickets = $service->getAllTickets($cashbox->cashbox_unique_number, $shift->shift_number);

            foreach ($tickets as $ticket) {
                $ticketModel = Ticket::updateOrCreate(
                    [
                        'shift_id' => $shift->id,
                        'number' => $ticket['Number'],
                        'order_number' => $ticket['OrderNumber'],
                        'date' => Carbon::createFromFormat('d.m.Y H:i:s', $ticket['RegistratedOn']),
                    ],
                    [
                        'operation_type' => $ticket['OperationType'],
                        'operation_type_text' => $ticket['OperationTypeText'],
                        'total' => $ticket['Total'],
                        'discount' => $ticket['Discount'],
                        'markup' => $ticket['Markup'],
                    ]
                );

                // Payments
                $ticketModel->payments()->delete();
                foreach ($ticket['Payments'] ?? [] as $payment) {
                    $ticketModel->payments()->create([
                        'sum' => $payment['Sum'],
                        'payment_type' => Ticket::PAYMENT_TYPES[trim($payment['PaymentTypeName'])] ?? null,
                        'payment_type_name' => $payment['PaymentTypeName'] ?? null,
                    ]);
                }

                // Positions
                $ticketModel->positions()->delete();
                foreach ($ticket['Positions'] ?? [] as $position) {
                    $ticketModel->positions()->create([
                        'position_name' => $position['PositionName'],
                        'count' => $position['Count'],
                        'price' => $position['Price'],
                        'discount_tenge' => $position['DiscountTenge'],
                        'markup' => $position['Markup'],
                        'sum' => $position['Sum'],
                    ]);
                }
            }
        }
    }

    public function failed(Throwable $exception): void
    {
        if (config('webkassa-integration.error_log', false)) {
            Log::error("SyncCashboxShiftsAndTickets job failed", [
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
    }
}
