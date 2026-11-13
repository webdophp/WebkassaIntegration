<?php


namespace webdophp\WebkassaIntegration\Console\Commands;


use Illuminate\Console\Command;
use Throwable;
use webdophp\WebkassaIntegration\Jobs\SyncShiftsAndCheckHistory;
use webdophp\WebkassaIntegration\Models\RepeatedTicket;

/**
 * Дополнительная команда синхронизации смен и чеков через методы Webkassa
 * POST /Cashbox/ShiftHistory и POST /Check/History.
 * Не заменяет app:webkassa-sync-shifts-tickets, а дополняет его данными из другого метода API.
 *
 * Период, за который берутся смены/чеки, определяется записью в repeated_tickets
 * (так же, как у app:webkassa-sync-day-shifts-tickets): без --skip-update она
 * выставляется на "сегодня - 2 дня", а с --skip-update используется то, что уже
 * лежит в таблице (например, проставленное вручную под конкретный период).
 */
class WebkassaSyncShiftsAndCheckHistory extends Command
{
    /**
     * Имя и сигнатура консольной команды.
     *
     * @var string
     */
    protected $signature = 'app:webkassa-sync-shifts-check-history {--skip-update}';

    /**
     * Описание консольной команды.
     *
     * @var string
     */
    protected $description = 'Выполняет синхронизацию смен и чеков из Webkassa через ShiftHistory/Check History (доп. источник данных)';

    /**
     * @return void
     */
    public function handle(): void
    {
        try {
            $skipUpdate = $this->option('skip-update');
            $this->info('Webkassa shifts/check history command started at ' . now());
            $data = config('webkassa-integration.data');

            if (empty($data)) {
                $this->info('No cashboxes found in config.');
                return;
            }

            if (!$skipUpdate) {
                foreach ($data as $item) {
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

            foreach ($data as $index => $item) {
                if ($index == 0) {
                    SyncShiftsAndCheckHistory::dispatch($item['base_url'], $item['login'], $item['password'])->delay(now()->addSeconds(1));
                } else {
                    SyncShiftsAndCheckHistory::dispatch($item['base_url'], $item['login'], $item['password'])->delay(now()->addMinutes(5));
                }
            }
            $this->info('Webkassa shifts/check history completed successfully at ' . now());
        } catch (Throwable $e) {
            $this->error('Error: ' . $e->getMessage());
        }
    }
}
