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
    public array $cashboxes;

    /**
     * @var string $token - Токен сессии Webkassa
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
        $this->cashboxes = $cashboxes;
        $this->cashbox = $cashbox;
        $this->shift = $shift;
    }

    /**
     * Отправка почты
     * @return self
     */
    public function build(): self
    {
        return $this->subject(config('webkassa-integration.mail_subject'))
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
