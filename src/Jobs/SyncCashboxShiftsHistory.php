<?php

namespace webdophp\WebkassaIntegration\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;
use webdophp\WebkassaIntegration\Mail\WebkassaJobFailed;
use webdophp\WebkassaIntegration\Models\Cashbox;
use webdophp\WebkassaIntegration\Models\Shift;
use webdophp\WebkassaIntegration\Services\TelegramErrorService;
use webdophp\WebkassaIntegration\Services\WebkassaCheckHistoryService;

/**
 * Class SyncCashboxShiftsHistory
 *
 * Синхронизация смен по кассе через дополнительный метод Webkassa (POST /Cashbox/ShiftHistory).
 * Пишет в ту же таблицу shifts, что и существующий SyncCashboxShifts, по тому же
 * уникальному ключу (cashbox_id, shift_number) — без конфликтов с уже загруженными данными.
 */
class SyncCashboxShiftsHistory implements ShouldQueue
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
     * @var int
     */
    protected int $cashboxId;

    /**
     * @var string|null
     */
    protected ?string $fromDate;

    /**
     * @var string|null
     */
    protected ?string $toDate;

    /**
     * @param string $baseUrl
     * @param string $login
     * @param string $password
     * @param int $cashboxId
     * @param string|null $fromDate
     * @param string|null $toDate
     */
    public function __construct(string $baseUrl, string $login, string $password, int $cashboxId, ?string $fromDate = null, ?string $toDate = null)
    {
        $this->baseUrl = $baseUrl;
        $this->login = $login;
        $this->password = $password;
        $this->cashboxId = $cashboxId;
        $this->fromDate = $fromDate;
        $this->toDate = $toDate;
    }

    /**
     * @return void
     * @throws ConnectionException
     */
    public function handle(): void
    {
        $service = new WebkassaCheckHistoryService($this->baseUrl, $this->login, $this->password);

        $cashbox = Cashbox::findOrFail($this->cashboxId);

        if (config('webkassa-integration.error_log', false)) {
            Log::info("Syncing shift history for cashbox {$cashbox->id} ({$cashbox->cashbox_unique_number})");
        }

        $shiftsData = $service->getAllShiftHistory($cashbox->cashbox_unique_number);

        // /Cashbox/ShiftHistory не принимает FromDate/ToDate, поэтому фильтруем
        // уже полученные смены по тому же диапазону, что использует "day"-синхронизация,
        // чтобы не обрабатывать всю историю кассы целиком.
        $from = $this->fromDate ? Carbon::parse($this->fromDate)->startOfDay() : null;
        $to = $this->toDate ? Carbon::parse($this->toDate)->endOfDay() : null;

        foreach ($shiftsData as $shiftItem) {
            if (empty($shiftItem['ShiftNumber']) || empty($shiftItem['OpenDate'])) {
                continue;
            }

            $openDate = Carbon::createFromFormat('d.m.Y H:i:s', $shiftItem['OpenDate']);

            if (($from && $openDate->lt($from)) || ($to && $openDate->gt($to))) {
                continue;
            }

            $shift = Shift::updateOrCreate(
                [
                    'cashbox_id' => $cashbox->id,
                    'shift_number' => $shiftItem['ShiftNumber'],
                ],
                [
                    'open_date' => $openDate,
                    'close_date' => !empty($shiftItem['CloseDate'])
                        ? Carbon::createFromFormat('d.m.Y H:i:s', $shiftItem['CloseDate'])
                        : null,
                ]
            );

            // если смена закрыта и чеки по ней уже загружены — пропускаем повторную загрузку
            if ($shift->close_date && $shift->tickets()->exists()) {
                continue;
            }

            SyncCheckHistoryTickets::dispatch(
                $this->baseUrl, $this->login, $this->password,
                $cashbox->cashbox_unique_number,
                $shift->id,
                $shift->shift_number
            )->delay(now()->addSeconds(2));
        }
    }

    /**
     * @param Throwable $exception
     * @return void
     */
    public function failed(Throwable $exception): void
    {
        try {
            if (config('webkassa-integration.error_log', false)) {
                Log::error('SyncCashboxShiftsHistory job failed', [
                    'cashbox_id' => $this->cashboxId,
                    'error' => $exception->getMessage(),
                    'trace' => $exception->getTraceAsString(),
                ]);
            }

            if (config('webkassa-integration.error_mail', false)) {
                Mail::to(config('webkassa-integration.mail_to'))->send(
                    new WebkassaJobFailed(
                        $exception->getCode().': '.$exception->getMessage(),
                        $exception->getTraceAsString()
                    )
                );
            }

            if (config('webkassa-integration.telegram_error.error_telegram', false)) {
                $telegram = app(TelegramErrorService::class);
                $telegram->MessageError(
                    "<b>Произошла ошибка при импорте смен из Webkassa (ShiftHistory)</b>\n" .
                    "<b>Сервис:</b> " . config('webkassa-integration.service_name') . "\n" .
                    "<b>Код ошибки:</b> " . $exception->getCode() . "\n" .
                    "<b>Ошибка:</b> " . htmlspecialchars($exception->getMessage())
                );
            }
        } catch (Throwable $e) {
            Log::error('Mail sending failed SyncCashboxShiftsHistory', ['error' => $e->getMessage()]);
        }
    }
}
