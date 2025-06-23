<?php


namespace webdophp\WebkassaIntegration\Console\Commands;


use Illuminate\Console\Command;
use Throwable;
use webdophp\WebkassaIntegration\Jobs\AuthorizeWebkassa;

class WebkassaData extends Command
{
    /**
     * Имя и сигнатура консольной команды.
     *
     * @var string
     */
    protected $signature = 'app:webkassa-data';

    /**
     * Описание консольной команды.
     *
     * @var string
     */
    protected $description = 'Выполняет синхронизацию пакетной загрузки данных из Webkassa';

    /**
     * Выполнение команды
     *
     * @throws Throwable
     */
    public function handle(): void
    {
        try {
            $this->info('Webkassa command started at ' . now());
            AuthorizeWebkassa::dispatch();
            $this->info('Webkassa completed successfully at ' . now());
        } catch (Throwable $e) {
            $this->error('Error: ' . $e->getMessage());
        }
    }
}

