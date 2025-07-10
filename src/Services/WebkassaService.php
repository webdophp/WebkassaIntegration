<?php

namespace webdophp\WebkassaIntegration\Services;


use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Class WebkassaService
 *
 * Handles integration with the Webkassa API, providing functionalities such as
 * authorization, managing tokens, retrieving cashboxes, shifts, and tickets.
 */
class WebkassaService
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
     * Initializes the class with configuration data for Webkassa integration.
     *
     * @return void
     */
    public function __construct()
    {
        $this->login = config('webkassa-integration.login');
        $this->password = config('webkassa-integration.password');
        $this->baseUrl = config('webkassa-integration.base_url');
    }

    /**
     * Retrieves the Webkassa token from the cache.
     *
     * @return string|null Returns the token as a string if found, or null if it does not exist.
     */
    public function getToken(): ?string
    {
        return Cache::get('webkassa_token');
    }

    /**
     * Handles the authorization process by sending login and password credentials to the Webkassa API.
     * Stores the received token in the cache upon successful authentication.
     *
     * @return bool Returns true if authorization is successful and the token is cached; false otherwise.
     * @throws ConnectionException
     */
    public function authorize(): bool
    {
        $response = Http::post("{$this->baseUrl}/Authorize", [
            'Login' => $this->login,
            'Password' => $this->password,
        ]);

        if ($response->successful() && isset($response['Data']['Token'])) {
            Cache::put('webkassa_token', $response['Data']['Token'], now()->addHours(24));
            return true;
        }

        return false;
    }

    /**
     * Ensures a valid token is present by either retrieving an existing token
     * or authorizing a new one if necessary.
     *
     * @return bool Returns true if a valid token is obtained, false otherwise.
     * @throws ConnectionException
     */
    private function ensureToken(): bool
    {
        return $this->getToken() || $this->authorize();
    }

    /**
     * Retrieves a list of available cashboxes that can be accessed for reading history.
     *
     * @return array Returns an array containing information about the available cashboxes.
     * @throws ConnectionException
     */
    public function getAvailableCashboxes(): array
    {
        return $this->post('/cashboxes/availableForReadHistory', []);
    }

    /**
     * Retrieves a list of shifts based on the specified criteria.
     *
     * @param string $cashboxNumber The unique identifier of the cashbox.
     * @param string|null $fromDate The start date for the shift history in YYYY-MM-DD format. Defaults to null.
     * @param string|null $toDate The end date for the shift history in YYYY-MM-DD format. Defaults to null.
     * @param int $skip The number of records to skip. Defaults to 0.
     * @param int $take The number of records to retrieve. Defaults to 50.
     * @return array An array containing the shift history.
     * @throws ConnectionException
     */
    public function getShifts(string $cashboxNumber, ?string $fromDate = null, ?string $toDate = null, int $skip = 0, int $take = 500): array
    {
        $fromDate = Carbon::parse($fromDate ?? Carbon::now()->subDay()->startOfDay())->format('d.m.Y H:i:s');
        $toDate = Carbon::parse($toDate ?? Carbon::now()->endOfDay())->format('d.m.Y H:i:s');

        return $this->post('/Shift/ExternalHistory', [
            'CashboxUniqueNumber' => $cashboxNumber,
            'FromDate' => $fromDate,
            'ToDate' => $toDate,
            'Skip' => $skip,
            'Take' => $take,
        ]);
    }

    /**
     * Retrieves a list of tickets based on the specified parameters.
     *
     * @param string $cashboxNumber The unique identifier of the cashbox.
     * @param int $shiftNumber The number of the shift for which tickets are to be retrieved.
     * @param int $skip The number of tickets to skip in the result set. Defaults to 0.
     * @param int $take The number of tickets to retrieve. Defaults to 50.
     *
     * @return array An array of tickets retrieved based on the provided parameters.
     * @throws ConnectionException
     */
    public function getTickets(string $cashboxNumber, int $shiftNumber, int $skip = 0, int $take = 50): array
    {
        return $this->post('/Ticket/ExternalHistory', [
            'CashboxUniqueNumber' => $cashboxNumber,
            'ShiftNumber' => $shiftNumber,
            'Skip' => $skip,
            'Take' => $take,
        ]);
    }


    /**
     * @param string $cashboxNumber The unique identifier of the cashbox.
     * @param int $shiftNumber The number of the shift for which tickets are to be retrieved.
     * @param int $batchSize The number of tickets to retrieve. Defaults to 50.
     * @return array
     * @throws ConnectionException
     */
    public function getAllTickets(string $cashboxNumber, int $shiftNumber, int $batchSize = 50): array
    {
        $allTickets = [];
        $skip = 0;

        do {
            $response = $this->getTickets($cashboxNumber, $shiftNumber, $skip, $batchSize);

            if (isset($response['error']) && $response['error']) {
                throw new RuntimeException("Webkassa error [{$response['status']}]: {$response['message']}");
            }

            $items = $response['Data']['Items'] ?? [];
            $total = $response['Data']['Total'] ?? 0;

            $allTickets = array_merge($allTickets, $items);
            $skip += $batchSize;

        } while (count($allTickets) < $total);

        return $allTickets;
    }

    /**
     * Sends a POST request to the specified API endpoint with the provided data.
     * Automatically handles token authorization and retries the request
     * if the token is invalid.
     *
     * @param string $endpoint The API endpoint to which the request is sent.
     * @param array $data The data payload to include in the POST request.
     *
     * @return array The response from the API, including the error details if the request fails.
     * @throws ConnectionException
     */
    private function post(string $endpoint, array $data): array
    {
        if (!$this->ensureToken()) {
            return ['error' => true, 'message' => 'Authorization failed'];
        }

        $data['Token'] = $this->getToken();
        $response = Http::post("{$this->baseUrl}{$endpoint}", $data);

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
