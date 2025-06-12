<?php

namespace webdophp\WebkassaIntegration\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebkassaService
{
    /**
     * @var  string $token - Токен сессии Webkassa
     */
    protected string $token;

    /**
     * Установить токен
     * @param string $token
     * @return WebkassaService
     */
    public function setToken(string $token): WebkassaService
    {
        $this->token  = $token;
        return $this;
    }

    /**
     * Получить токен
     * @return string|null
     * @throws ConnectionException
     */
    public function getToken(): ?string
    {
        return $this->token ?? $this->authenticate();
    }


    /**
     * Авторизация
     * @return ?string
     * @throws ConnectionException
     */
    public function authenticate(): ?string
    {
        $response = Http::withHeaders(['X-API-KEY' => config('webkassa-integration.api_key')])
            ->post(config('webkassa-integration.base_url') . '/api/v4/Authorize', [
                'Login' => config('webkassa-integration.login'),
                'Password' => config('webkassa-integration.password'),
            ]);

        if ($response->successful() && $token = $response->json('Data.Token')) {
            $this->token = $token;
            return $token;
        }

        Log::error('Webkassa auth error', ['response' => $response->body()]);
        return null;
    }

    /**
     * Получение списка кассиров
     * @return array
     * @throws ConnectionException
     */
    public function getEmployees(): array
    {
        return Http::withHeaders(['X-API-KEY' => config('webkassa-integration.api_key')])
            ->post(config('webkassa-integration.base_url') . '/api-portal/employee/employees-and-cashboxes', [
                'Token' => $this->token
            ])
            ->json('Data');
    }

    /**
     * Получение списка смен кассы
     * @param string $cashbox - Уникальный номер кассы
     * @param int $skip - Количество записей, которые необходимо пропустить. Может быть равным нулю
     * @param int $take - Количество записей чеков, которые будут получены. Значение может быть в диапазоне от 1 до 50
     * @return array
     * @throws ConnectionException
     */
    public function getShiftHistory(string $cashbox, int $skip = 0, int $take = 40): array
    {
        return Http::withHeaders(['X-API-KEY' => config('webkassa-integration.api_key')])
            ->post(config('webkassa-integration.base_url') . '/api/v4/Cashbox/ShiftHistory', [
                'Token' => $this->token,
                'CashboxUniqueNumber' => $cashbox,
                'Skip' => $skip,
                'Take' => $take
            ])
            ->json('Data.Shifts');
    }

    /**
     * Контрольная лента за смену
     * @param string $cashbox
     * @param int $shift
     * @return array
     * @throws ConnectionException
     */
    public function getControlTape(string $cashbox, int $shift): array
    {
        return Http::withHeaders(['X-API-KEY' => config('webkassa-integration.api_key')])
            ->post(config('webkassa-integration.base_url') . '/api/v4/Reports/ControlTape', [
                'Token' => $this->token,
                'CashboxUniqueNumber' => $cashbox,
                'ShiftNumber' => $shift
            ])
            ->json('Data');
    }
}