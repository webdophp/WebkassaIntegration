<?php

namespace webdophp\WebkassaIntegration\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use webdophp\WebkassaIntegration\Mall\WebkassaJobFailed;
use webdophp\WebkassaIntegration\Models\CashboxShift;
use webdophp\WebkassaIntegration\Models\ControlTapeRecord;
use webdophp\WebkassaIntegration\Services\WebkassaService;
use Illuminate\Support\Facades\Log;
use Throwable;
use Exception;

class FetchControlTape implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var  string $token - Токен сессии Webkassa
     */
    protected string $token;

    /**
     * @var string $cashbox - Заводской/серийный номер кассы
     */
    protected string $cashbox;

    /**
     * @var int $shift - Номер смены
     */
    protected int $shift;

    /**
     * @param string $token
     * @param string $cashbox
     * @param int $shift
     */
    public function __construct(string $token, string $cashbox, int $shift)
    {
        $this->token = $token;
        $this->cashbox = $cashbox;
        $this->shift = $shift;
    }

    /**
     * Контрольная летна за смену
     * @throws ConnectionException
     * @throws Exception
     */
    public function handle(WebkassaService $service): void
    {
        $tapes = $service->setToken($this->token)->getControlTape($this->cashbox, $this->shift);

        if (empty($tapes)) {
            throw new Exception('Failed to fetch control tape.', 1003);
        }


        foreach ($tapes as $item) {
            ControlTapeRecord::firstOrCreate([
                'cashbox_unique_number' => $this->cashbox,
                'shift_number' => $this->shift,
                'operation_type' => $item['OperationTypeText'],
                'date' => $item['Date'],
            ], [
                'sum' => $item['Sum'],
                'employee_code' => $item['EmployeeCode'] ?? null,
                'number' => $item['number'] ?? null,
                'is_offline' => $item['IsOffline'],
            ]);
        }

        CashboxShift::firstOrCreate(
            [
                'cashbox_unique_number' => $this->cashbox,
                'shift_number' => $this->shift,
            ]
        );
    }


    /**
     * @param Throwable $exception
     * @return void
     */
    public function failed(Throwable $exception): void
    {
        if(config('webkassa-integration.error_log', false)) {
            Log::error('FetchControlTape job failed', [
                'token' => $this->token,
                'cashbox' => $this->cashbox,
                'shift' => $this->shift,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
        }

        if(config('webkassa-integration.error_mail', false)) {
            Mail::to(config('webkassa-integration.mail_to'))->send(
                new WebkassaJobFailed(
                    $exception->getCode().': '.$exception->getMessage(),
                    $exception->getTraceAsString(),
                    $this->token,
                    null,
                    $this->cashbox,
                    $this->shift
                )
            );
        }
    }
}