<?php


namespace webdophp\WebkassaIntegration\Console\Commands;


use Illuminate\Console\Command;
use Throwable;
use webdophp\WebkassaIntegration\Jobs\SyncShiftsAndTickets;

class WebkassaSyncShiftsAndTickets extends Command
{
    /**
     * Имя и сигнатура консольной команды.
     *
     * @var string
     */
    protected $signature = 'app:webkassa-sync-shifts-tickets';

    /**
     * Описание консольной команды.
     *
     * @var string
     */
    protected $description = 'Выполняет синхронизацию кассовой ленты из Webkassa';

    /**
     * Handles the execution of the Webkassa command.
     *
     * @return void
     */
    public function handle(): void
    {
        try {
            $this->info('Webkassa command started at ' . now());
            SyncShiftsAndTickets::dispatch();
            $this->info('Webkassa completed successfully at ' . now());
        } catch (Throwable $e) {
            $this->error('Error: ' . $e->getMessage());
        }
    }
}

