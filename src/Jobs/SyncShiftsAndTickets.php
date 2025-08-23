<?php

namespace webdophp\WebkassaIntegration\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use webdophp\WebkassaIntegration\Models\Cashbox;


class SyncShiftsAndTickets implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var int
     */
    public int $tries = 1;
 

    /**
     * Контрольная летна за смену
     */
    public function handle(): void
    {
        Cashbox::chunkById(50, function ($cashboxes) {
            foreach ($cashboxes as $cashbox) {
                // диспатчим отдельную задачу на каждую кассу
                SyncCashboxShifts::dispatch($cashbox->id);
            }
        });
    }
}
