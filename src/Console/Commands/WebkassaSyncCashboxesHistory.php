<?php


namespace webdophp\WebkassaIntegration\Console\Commands;


use Illuminate\Console\Command;
use Throwable;
use webdophp\WebkassaIntegration\Jobs\SyncCashboxesHistory;

/**
 * Дополнительная команда синхронизации касс через метод Webkassa POST /Cashboxes.
 * Не заменяет app:webkassa-sync-cashboxes, а дополняет его данными из другого метода API.
 */
class WebkassaSyncCashboxesHistory extends Command
{
    /**
     * Имя и сигнатура консольной команды.
     *
     * @var string
     */
    protected $signature = 'app:webkassa-sync-cashboxes-history';

    /**
     * Описание консольной команды.
     *
     * @var string
     */
    protected $description = 'Выполняет синхронизацию касс из Webkassa через метод Cashboxes (доп. источник данных)';

    /**
     * @return void
     */
    public function handle(): void
    {
        try {
            $this->info('Webkassa cashboxes history command started at ' . now());
            $data = config('webkassa-integration.data');

            if (empty($data)) {
                $this->info('No cashboxes found in config.');
                return;
            }
            foreach ($data as $index => $item) {
                if ($index == 0) {
                    SyncCashboxesHistory::dispatch($item['base_url'], $item['login'], $item['password'])->delay(now()->addSeconds(1));
                } else {
                    SyncCashboxesHistory::dispatch($item['base_url'], $item['login'], $item['password'])->delay(now()->addMinutes(5));
                }
            }
            $this->info('Webkassa cashboxes history completed successfully at ' . now());
        } catch (Throwable $e) {
            $this->error('Error: ' . $e->getMessage());
        }
    }
}
