<?php

namespace webdophp\WebkassaIntegration\Mall;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WebkassaJobFailed extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @var string $cashboxes - Сообщение об ошибке
     */
    public string $errorMessage;

    /**
     * @var string $trace - трассировку стека
     */
    public string $trace;

    /**
     * @var array|null $cashboxes - Массив заводской/серийный номер кассы
     */
    public ?array $cashboxes;

    /**
     * @var string|null $token - Токен сессии Webkassa
     */
    public ?string $token;

    /**
     * @var string|null $cashbox - Заводской/серийный номер кассы
     */
    public ?string $cashbox;

    /**
     * @var int|null $shift - Номер смены
     */
    public ?int $shift;

    /**
     * @param string $errorMessage
     * @param string $trace
     * @param string|null $token
     * @param array|null $cashboxes
     * @param string|null $cashbox
     * @param int|null $shift
     */
    public function __construct(string $errorMessage, string $trace, ?string $token = null, ?array $cashboxes = [], ?string $cashbox = null, ?int $shift = null)
    {
        $this->errorMessage = $errorMessage;
        $this->trace = $trace;
        $this->token = $token;
        $this->cashboxes = $cashboxes ?? [];
        $this->cashbox = $cashbox;
        $this->shift = $shift;
    }

    /**
     * Отправка почты
     * @return self
     */
    public function build(): self
    {
        return $this->subject(config('webkassa-integration.mail_subject', 'Webkassa Job Failed'))
            ->view('webkassa-integration::failed')
            ->with([
                'errorMessage' => $this->errorMessage,
                'trace' => $this->trace,
                'token' => $this->token,
                'cashboxes' => $this->cashboxes,
                'cashbox' => $this->cashbox,
                'shift' => $this->shift,
            ]);
    }
}
