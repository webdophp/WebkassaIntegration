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
            $data = config('webkassa-integration.data');

            if (empty($data)) {
                $this->info('No cashboxes found in config.');
                return;
            }
            foreach ($data as $index => $item) {
                if($index==0){
                    SyncShiftsAndTickets::dispatch($item['base_url'], $item['login'], $item['password'])->delay(now()->addSeconds(1));
                }
                else{
                    SyncShiftsAndTickets::dispatch($item['base_url'], $item['login'], $item['password'])->delay(now()->addMinutes(5));
                }
            }
            $this->info('Webkassa completed successfully at ' . now());
        } catch (Throwable $e) {
            $this->error('Error: ' . $e->getMessage());
        }
    }
}

