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
use webdophp\WebkassaIntegration\Mall\WebkassaJobFailed;
use Throwable;

class ProcessTicketPositions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 120;

    protected int $ticketId;
    protected array $positions;

    public function __construct(int $ticketId, array $positions)
    {
        $this->ticketId = $ticketId;
        $this->positions = $positions;
    }

    public function handle(): void
    {
        $now = now();

        DB::transaction(function () use ($now) {
            DB::table('ticket_positions')->where('ticket_id', $this->ticketId)->delete();

            $rows = [];
            foreach ($this->positions as $position) {
                $rows[] = [
                    'ticket_id'      => $this->ticketId,
                    'position_name'  => $position['PositionName'] ?? '',
                    'count'          => $position['Count'] ?? 0,
                    'price'          => $position['Price'] ?? 0,
                    'discount_tenge' => $position['DiscountTenge'] ?? 0,
                    'markup'         => $position['Markup'] ?? 0,
                    'sum'            => $position['Sum'] ?? 0,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ];
            }

            if ($rows) {
                DB::table('ticket_positions')->insert($rows);
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
