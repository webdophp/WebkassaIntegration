<?php

namespace webdophp\WebkassaIntegration\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use webdophp\WebkassaIntegration\Models\Cashbox;
use webdophp\WebkassaIntegration\Models\RepeatedTicket;
use webdophp\WebkassaIntegration\Services\WebkassaService;


class SyncShiftsAndTickets implements ShouldQueue
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
     * @var WebkassaService $service
     */
    public WebkassaService $service;

    /**
     * @var string
     */
    protected string $baseUrl;

    /**
     * @var string
     */
    protected string $login;

    /**
     * @var string
     */
    protected string $password;
    /**
     * @var string
     */
    protected string $day;

    /**
     * @param string $baseUrl
     * @param string $login
     * @param string $password
     * @param string|null $day
     */
    public function __construct(string $baseUrl, string $login, string $password, ?string $day)
    {
        $this->baseUrl = $baseUrl;
        $this->login = $login;
        $this->password = $password;
        $this->day = $day;
    }

    /**
     * Контрольная летна за смену
     * @return void
     */
    public function handle(): void
    {

        Cashbox::chunkById(50, function ($cashboxes) {
            foreach ($cashboxes as $cashbox) {
                // диспатчим отдельную задачу на каждую кассу
                SyncCashboxShifts::dispatch($this->baseUrl, $this->login, $this->password, $cashbox->id, $this->day);
            }
        });

    }
}
