<?php

namespace webdophp\WebkassaIntegration\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Illuminate\Support\Facades\Log;

/**
 * Class WebkassaCheckHistoryService
 *
 * Дополнительный источник данных Webkassa (не затрагивает WebkassaService):
 * POST /Authorize, POST /Cashboxes, POST /Cashbox/ShiftHistory, POST /Check/History.
 * Запросы отправляются без дополнительных заголовков (без API-KEY) — только тело запроса,
 * как в документации Webkassa.
 */
class WebkassaCheckHistoryService
{
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
     * @param string $baseUrl Base URL of the Webkassa API
     * @param string $login Login for authentication
     * @param string $password Password for authentication
     */
    public function __construct(string $baseUrl, string $login, string $password)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->login = $login;
        $this->password = $password;
    }

    /**
     * Токен хранится под тем же ключом кэша, что и в WebkassaService, чтобы не плодить
     * повторные авторизации для одного и того же логина.
     *
     * @return string|null
     */
    public function getToken(): ?string
    {
        return Cache::get('webkassa_token_'.$this->login);
    }

    /**
     * @return bool
     * @throws ConnectionException
     */
    public function authorize(): bool
    {
        $response = Http::post("{$this->baseUrl}/Authorize", [
            'Login' => $this->login,
            'Password' => $this->password,
        ]);

        if ($response->successful() && isset($response['Data']['Token'])) {
            Cache::put('webkassa_token_'.$this->login, $response['Data']['Token'], now()->addHours(24));
            return true;
        }

        return false;
    }

    /**
     * @return bool
     * @throws ConnectionException
     */
    private function ensureToken(): bool
    {
        return $this->getToken() || $this->authorize();
    }

    /**
     * Список касс (POST /Cashboxes).
     *
     * @return array
     * @throws ConnectionException
     */
    public function getCashboxes(): array
    {
        return $this->post('/Cashboxes', []);
    }

    /**
     * История смен по кассе (POST /Cashbox/ShiftHistory).
     *
     * @param string $cashboxNumber
     * @param int $skip
     * @param int $take
     * @return array
     * @throws ConnectionException
     */
    public function getShiftHistory(string $cashboxNumber, int $skip = 0, int $take = 50): array
    {
        return $this->post('/Cashbox/ShiftHistory', [
            'CashboxUniqueNumber' => $cashboxNumber,
            'Skip' => $skip,
            'Take' => $take,
        ]);
    }

    /**
     * @param string $cashboxNumber
     * @param int $batchSize
     * @return array
     * @throws ConnectionException
     */
    public function getAllShiftHistory(string $cashboxNumber, int $batchSize = 50): array
    {
        $all = [];
        $skip = 0;
        $maxIterations = 1000; // защита от бесконечного цикла
        $iteration = 0;

        do {
            $response = $this->getShiftHistory($cashboxNumber, $skip, $batchSize);

            if (isset($response['error']) && $response['error']) {
                throw new RuntimeException("Webkassa error [{$response['status']}]: {$response['message']}");
            }

            $items = $response['Data']['Shifts'] ?? [];
            $total = $response['Data']['Total'] ?? 0;

            if (empty($items)) {
                break;
            }

            $all = array_merge($all, $items);
            $skip += $batchSize;

            if ($skip >= $total || count($items) < $batchSize) {
                break;
            }

            $iteration++;
            if ($iteration > $maxIterations) {
                Log::error("Превышено количество итераций при загрузке истории смен (cashbox=$cashboxNumber)");
                break;
            }
        } while (true);

        return $all;
    }

    /**
     * Контрольная лента чеков за смену (POST /Check/History).
     *
     * @param string $cashboxNumber
     * @param int $shiftNumber
     * @param int $skip
     * @param int $take
     * @return array
     * @throws ConnectionException
     */
    public function getCheckHistory(string $cashboxNumber, int $shiftNumber, int $skip = 0, int $take = 50): array
    {
        return $this->post('/Check/History', [
            'CashboxUniqueNumber' => $cashboxNumber,
            'ShiftNumber' => $shiftNumber,
            'Skip' => $skip,
            'Take' => $take,
        ]);
    }

    /**
     * @param string $cashboxNumber
     * @param int $shiftNumber
     * @param int $batchSize
     * @return array
     * @throws ConnectionException
     */
    public function getAllCheckHistory(string $cashboxNumber, int $shiftNumber, int $batchSize = 50): array
    {
        $all = [];
        $skip = 0;
        $maxIterations = 1000; // защита от бесконечного цикла
        $iteration = 0;

        do {
            $response = $this->getCheckHistory($cashboxNumber, $shiftNumber, $skip, $batchSize);

            if (isset($response['error']) && $response['error']) {
                throw new RuntimeException("Webkassa error [{$response['status']}]: {$response['message']}");
            }

            $items = $response['Data']['Items'] ?? [];
            $total = $response['Data']['Total'] ?? 0;

            if (empty($items)) {
                break;
            }

            $all = array_merge($all, $items);
            $skip += $batchSize;

            if ($skip >= $total || count($items) < $batchSize) {
                break;
            }

            $iteration++;
            if ($iteration > $maxIterations) {
                Log::error("Превышено количество итераций при загрузке чеков (cashbox=$cashboxNumber, shift=$shiftNumber)");
                break;
            }
        } while (true);

        return $all;
    }

    /**
     * Sends a POST request to the specified API endpoint with the provided data.
     * Автоматически получает/обновляет токен и повторяет запрос при его истечении.
     * Заголовки запроса не добавляются (нет API-KEY) — только JSON-тело.
     *
     * @param string $endpoint
     * @param array $data
     * @return array
     * @throws ConnectionException
     */
    private function post(string $endpoint, array $data): array
    {
        if (!$this->ensureToken()) {
            return ['error' => true, 'status' => 401, 'message' => 'Authorization failed'];
        }

        $data['Token'] = $this->getToken();
        $response = Http::timeout(15)->connectTimeout(5)->post("{$this->baseUrl}{$endpoint}", $data);

        if ($response->ok() && ($response['Errors'][0]['Code'] ?? null) === 2) {
            $this->authorize();
            $data['Token'] = $this->getToken();
            $response = Http::post("{$this->baseUrl}{$endpoint}", $data);
        }

        if ($response->successful()) {
            return $response->json();
        }

        return [
            'error' => true,
            'status' => $response->status(),
            'message' => $response['Errors'][0]['Text'] ?? 'Unknown error',
        ];
    }
}
