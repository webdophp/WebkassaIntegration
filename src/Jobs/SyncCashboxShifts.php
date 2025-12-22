<?php

namespace webdophp\WebkassaIntegration\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;
use webdophp\WebkassaIntegration\Mail\WebkassaJobFailed;
use webdophp\WebkassaIntegration\Models\Cashbox;
use webdophp\WebkassaIntegration\Models\RepeatedTicket;
use webdophp\WebkassaIntegration\Models\Shift;
use webdophp\WebkassaIntegration\Services\WebkassaService;

class SyncCashboxShifts implements ShouldQueue
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
     * @var int
     */
    private int $cashboxId;

    /**
     * @var WebkassaService $service
     */
    protected WebkassaService $service;

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
     * @param int $cashboxId
     * @param string|null $day
     */
    public function __construct(string $baseUrl, string $login, string $password, int $cashboxId, ?string $day)
    {
        $this->baseUrl = $baseUrl;
        $this->login = $login;
        $this->password = $password;
        $this->cashboxId = $cashboxId;
        $this->day = $day;
    }

    /**
     * Синхронизация смен по кассе
     * @throws ConnectionException
     */
    public function handle(): void
    {
        $this->service = new WebkassaService($this->baseUrl, $this->login, $this->password);

        $cashbox = Cashbox::findOrFail($this->cashboxId);

        if (config('webkassa-integration.error_log', false)) {
            Log::info("Syncing shifts for cashbox {$cashbox->id} ({$cashbox->cashbox_unique_number})");
        }

        $repeated_ticket = null;
        if($this->day == 'day'){
            $repeated_ticket = RepeatedTicket::query()->where('login', $this->login)->first();
            if (!$repeated_ticket) {
                throw new RuntimeException("Webkassa error: repeated_ticket is null");
            }
            $response = $this->service->getShifts($cashbox->cashbox_unique_number, $repeated_ticket->from?->toDateString(), $repeated_ticket->to?->toDateString());
        }
        else{
            $response = $this->service->getShifts($cashbox->cashbox_unique_number);
        }

        if (isset($response['error']) && $response['error']) {
            throw new RuntimeException("Webkassa error [{$response['status']}]: {$response['message']}");
        }

        $shiftsData = $response['Data']['Shifts'] ?? [];

        foreach ($shiftsData as $shiftItem) {
            $shift = Shift::updateOrCreate(
                [
                    'cashbox_id'   => $cashbox->id,
                    'shift_number' => $shiftItem['ShiftNumber'],
                ],
                [
                    'open_date'  => Carbon::parse($shiftItem['OpenDate']),
                    'close_date' => !empty($shiftItem['CloseDate'])
                        ? Carbon::parse($shiftItem['CloseDate'])
                        : null,
                ]
            );

            // если смена закрыта и тикеты уже есть — пропускаем
            if ($shift->close_date && $shift->tickets()->exists() &&  is_null($repeated_ticket)) {
                //Делаем проверку, если нет repeated_ticket, то нужно пропустить цикл, так как уже было загружено ранее эти значения
                continue;
            }

            // диспатчим отдельный джоб
            SyncShiftTickets::dispatch(
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
                Log::error("SyncCashboxShifts job failed", [
                    'cashbox_id' => $this->cashboxId,
                    'error' => $exception->getMessage(),
                    'trace' => $exception->getTraceAsString(),
                ]);
            }

            if (config('webkassa-integration.error_mail', false)) {
                Mail::to(config('webkassa-integration.mail_to'))->send(
                    new WebkassaJobFailed(
                        $exception->getCode() . ': ' . $exception->getMessage(),
                        $exception->getTraceAsString()
                    )
                );
            }
        } catch (Exception $e) {
            Log::error('Mail sending failed SyncCashboxShifts', ['error' => $e->getMessage()]);
        }
    }
}
