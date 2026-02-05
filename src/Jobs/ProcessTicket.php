<?php

namespace webdophp\WebkassaIntegration\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;
use Exception;
use RuntimeException;
use webdophp\WebkassaIntegration\Mail\WebkassaJobFailed;
use webdophp\WebkassaIntegration\Models\Ticket;
use webdophp\WebkassaIntegration\Models\TicketBlacklist;

class ProcessTicket implements ShouldQueue
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
     * @var int|null
     */
    protected ?int $shiftId;

    /**
     * @var array|null
     */
    protected ?array $ticket;

    /**
     * @param int|null $shiftId
     * @param array|null $ticket
     */
    public function __construct(?int $shiftId, ?array $ticket)
    {
        $this->shiftId = $shiftId;
        $this->ticket  = $ticket;
    }

    /**
     * @throws Throwable
     */
    public function handle(): void
    {

        if (isset($this->ticket['Number']) && $this->ticket['Number'] != '' && TicketBlacklist::isBlacklisted($this->ticket['Number'])) {
            Log::warning("Ticket skipped (blacklisted): {$this->ticket['Number']}");
            return; // просто пропускаем
        }

        // Проверка обязательных переменных.
        if (empty($this->ticket) || empty($this->shiftId)) {
            throw new RuntimeException("Invalid job data: ticket or shiftId is null");
        }

        // Логируем дату регистрации тикета
        if (config('webkassa-integration.error_log', false)) {
            Log::debug('ProcessTicket RegistratedOn', [
                'RegistratedOn' => $this->ticket['RegistratedOn'] ?? null,
            ]);
        }

        $date = $this->ticket['RegistratedOn'] ?? null;
        // Проверка латы
        if (empty($date)) {
            throw new RuntimeException("Ticket date (RegistratedOn) is missing");
        }
        // Бросить исключение, если дата не правильного формата.

        try {
            $registeredDate = Carbon::createFromFormat('d.m.Y H:i:s', $date);
        } catch (Exception) {
            throw new RuntimeException("Unexpected date format for ticket. Got: {$date}");
        }

        // Формируем массивы для сохранения
        $attributes = [
            'shift_id'     => $this->shiftId,
            'number'       => $this->ticket['Number'],
            'order_number' => $this->ticket['OrderNumber'],
            'date'         => $registeredDate,
        ];

        $values = [
            'operation_type'      => $this->ticket['OperationType'],
            'operation_type_text' => $this->ticket['OperationTypeText'],
            'total'               => $this->ticket['Total'],
            'discount'            => $this->ticket['Discount'] ?? 0,
            'markup'              => $this->ticket['Markup'] ?? 0,
            'tax_percent'         => $this->ticket['TaxPercent'] ?? 0,
        ];

        // Логируем все данные
        if (config('webkassa-integration.error_log', false)) {
            Log::debug('ProcessTicket incoming data', [
                'attributes' => $attributes,
                'values' => $values,
                'payments' => $this->ticket['Payments'] ?? [],
                'positions' => $this->ticket['Positions'] ?? [],
            ]);
        }

        $ticketModel = Ticket::where($attributes)->first();
        if (!$ticketModel) {
            $ticketModel = Ticket::create(array_merge($attributes, $values));
            // диспатчим под-джобы
            if (!empty($this->ticket['Payments'])) {
                ProcessTicketPayments::dispatch($ticketModel->id, $this->ticket['Payments'])->delay(now()->addMilliseconds(200));
            }

            if (!empty($this->ticket['Positions'])) {
                ProcessTicketPositions::dispatch($ticketModel->id, $this->ticket['Positions'])->delay(now()->addMilliseconds(500));
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
        catch (Exception $e) {
            Log::error('Mail sending failed ProcessTicket', ['error' => $e->getMessage()]);
        }
    }
}
