<?php


namespace webdophp\WebkassaIntegration\Console\Commands;


use Illuminate\Console\Command;
use Throwable;
use webdophp\WebkassaIntegration\Jobs\SyncCashboxes;

class WebkassaSyncCashboxes extends Command
{
    /**
     * Имя и сигнатура консольной команды.
     *
     * @var string
     */
    protected $signature = 'app:webkassa-sync-cashboxes';

    /**
     * Описание консольной команды.
     *
     * @var string
     */
    protected $description = 'Выполняет синхронизацию касс из Webkassa';


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
                    print_r($item);
                    SyncCashboxes::dispatch($item['base_url'], $item['login'], $item['password'])->delay(now()->addSeconds(1));
                }
                else{
                    print_r($item);
                    SyncCashboxes::dispatch($item['base_url'], $item['login'], $item['password'])->delay(now()->addMinutes(5));
                }
            }
            $this->info('Webkassa completed successfully at ' . now());
        } catch (Throwable $e) {
            $this->error('Error: ' . $e->getMessage());
        }
    }
}

