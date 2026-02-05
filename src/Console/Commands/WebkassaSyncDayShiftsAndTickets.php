<?php


namespace webdophp\WebkassaIntegration\Console\Commands;


use Illuminate\Console\Command;
use Throwable;
use webdophp\WebkassaIntegration\Jobs\SyncShiftsAndTickets;
use webdophp\WebkassaIntegration\Models\RepeatedTicket;

class WebkassaSyncDayShiftsAndTickets extends Command
{
    /**
     * Имя и сигнатура консольной команды.
     *
     * @var string
     */
    protected $signature = 'app:webkassa-sync-day-shifts-tickets {--skip-update}';

    /**
     * Описание консольной команды.
     *
     * @var string
     */
    protected $description = 'Выполняет синхронизацию кассовой ленты из Webkassa в указанный день';

    /**
     * Handles the execution of the Webkassa command.
     *
     * @return void
     */
    public function handle(): void
    {
        try {
            $skipUpdate = $this->option('skip-update');
            $this->info('Webkassa command started at ' . now());
            $data = config('webkassa-integration.data');

            if (!is_array($data)  || empty($data)) {
                $this->info('No cashboxes found in config.');
                return;
            }

            if (!$skipUpdate) {
                foreach ($data as  $item) {
                     RepeatedTicket::updateOrCreate(
                        [
                            'login' => $item['login']
                        ],
                        [
                            'from' => now()->subDays(2)->toDateString(),
                            'to' => now()->subDays(2)->toDateString()
                        ]
                    );
                }
            }

            $delayMinutes = $skipUpdate ? 5 : 20;
            foreach ($data as $index => $item) {
                if($index==0){
                    SyncShiftsAndTickets::dispatch($item['base_url'], $item['login'], $item['password'], 'day')->delay(now()->addSeconds(1));
                }
                else{
                    SyncShiftsAndTickets::dispatch($item['base_url'], $item['login'], $item['password'], 'day')->delay(now()->addMinutes($index * $delayMinutes));
                }
            }
            $this->info('Webkassa DAY completed successfully at ' . now());
        } catch (Throwable $e) {
            $this->error('Error: ' . $e->getMessage());
        }
    }
}

