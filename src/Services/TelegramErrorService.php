<?php

namespace webdophp\WebkassaIntegration\Services;


use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramErrorService
{
    /**
     * Отправка сообщений в телеграм
     * @param string $message
     * @param string $token
     * @param string $chatId
     * @return void
     */
    private function send(string $message, string $token, string $chatId): void
    {
        try {

            if (!empty($token) && !empty($chatId)) {
                Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $message,
                    'parse_mode' => 'HTML'
                ]);
            }
            else{
                Log::warning('Telegram config missing', [
                    'token' => $token,
                    'chat_id' => $chatId,
                ]);
            }

        } catch (Throwable $e) {
            Log::error('Telegram send error', [
                'error' => $e->getMessage()
            ]);
        }
    }


    /**
     * Отправка сообщений об ошибках системы сбор продаж
     * @param string $message
     */
    public function MessageError(string $message): void
    {
        $this->send(
            $message,
            config('webkassa-integration.telegram_error.bot_token'),
            config('webkassa-integration.telegram_error.chat_id')
        );
    }

}
