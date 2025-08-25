<?php

namespace webdophp\WebkassaIntegration\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;
use RuntimeException;
use webdophp\WebkassaIntegration\Mall\WebkassaJobFailed;
use webdophp\WebkassaIntegration\Models\Ticket;

class ProcessTicket implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 120;
    protected int $shiftId;

    protected array $ticket;

    public function __construct(int $shiftId, array $ticket)
    {
        $this->shiftId = $shiftId;
        $this->ticket  = $ticket;
    }

    /**
     * @throws Throwable
     */
    public function handle(): void
    {
        $date = $this->ticket['RegistratedOn'] ?? null;

        if ($date && Carbon::hasFormat($date, 'd.m.Y H:i:s')) {
            $registeredDate = Carbon::createFromFormat('d.m.Y H:i:s', $date);
        } else {
            throw new RuntimeException(
                sprintf('Unexpected date format for ticket. Got: %s', $date ?? 'NULL')
            );
        }

        // сохраняем сам тикет
        $ticketModel = null;
        DB::transaction(function () use ($registeredDate, &$ticketModel) {
            $ticketModel = Ticket::updateOrCreate(
                [
                    'shift_id'     => $this->shiftId,
                    'number'       => $this->ticket['Number'],
                    'order_number' => $this->ticket['OrderNumber'],
                    'date'         => $registeredDate,
                ],
                [
                    'operation_type'      => $this->ticket['OperationType'],
                    'operation_type_text' => $this->ticket['OperationTypeText'],
                    'total'               => $this->ticket['Total'],
                    'discount'            => $this->ticket['Discount'],
                    'markup'              => $this->ticket['Markup'],
                ]
            );
        });

        // диспатчим под-джобы
        if (!empty($this->ticket['Payments'])) {
            ProcessTicketPayments::dispatch($ticketModel->id, $this->ticket['Payments'])->delay(now()->addMilliseconds(50));;
        }

        if (!empty($this->ticket['Positions'])) {
            ProcessTicketPositions::dispatch($ticketModel->id, $this->ticket['Positions'])->delay(now()->addMilliseconds(100));;
        }

    }

    public function failed(Throwable $exception): void
    {
        try {
            if (config('webkassa-integration.error_log', false)) {
                Log::error("ProcessTicket job failed", [
                    'shiftId' => $this->shiftId,
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
        catch (\Exception $e) {
            Log::error('Mail sending failed ProcessTicket', ['error' => $e->getMessage()]);
        }
    }
}
