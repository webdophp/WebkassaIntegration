<?php

namespace webdophp\WebkassaIntegration\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;
use webdophp\WebkassaIntegration\Mail\WebkassaJobFailed;
use webdophp\WebkassaIntegration\Models\Ticket;

class ProcessTicketPayments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 120;
    protected int $ticketId;
    protected array $payments;


    public function __construct(int $ticketId, array $payments)
    {
        $this->ticketId = $ticketId;
        $this->payments = $payments;
    }

    public function handle(): void
    {
        $now = now();

        DB::transaction(function () use ($now) {
            DB::table('ticket_payments')->where('ticket_id', $this->ticketId)->delete();

            $rows = [];
            foreach ($this->payments as $payment) {
                $rows[] = [
                    'ticket_id'         => $this->ticketId,
                    'sum'               => $payment['Sum'] ?? 0,
                    'payment_type'      => Ticket::PAYMENT_TYPES[trim($payment['PaymentTypeName'] ?? '')] ?? null,
                    'payment_type_name' => $payment['PaymentTypeName'] ?? null,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ];
            }

            if ($rows) {
                DB::table('ticket_payments')->insert($rows);
            }
        });
    }


    public function failed(Throwable $exception): void
    {
        try{
            if (config('webkassa-integration.error_log', false)) {
                Log::error("ProcessTicketPayments job failed", [
                    'ticketId' => $this->ticketId,
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
            Log::error('Mail sending failed ProcessTicketPayments', ['error' => $e->getMessage()]);
        }
    }
}
