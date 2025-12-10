<?php

return [
    /**
     *  Пишем логи в лог файл или отправляем на почту
     */
    'error_log' => env('WEBKASSA_ERROR_LOG', false),

    'error_mail' => env('WEBKASSA_ERROR_MAIL', false),


    /**
     * Если лог отправка на почту, то нужно указать кому отправить и заголовок письма для сортировки в почте.
     */
    'mail_to' => env('WEBKASSA_MAIL_TO', 'mail@localhost.lan'),

    'mail_subject' => env('WEBKASSA_MAIL_SUBJECT', 'WebkassaFetchData Job Failed'),

    /**
     * Данные для авторизации в Webkassa
     */
    'data' => (function () {
        $data = [];
        $i = 1;

        while ($url = env("WEBKASSA_BASE_URL_{$i}")) {
            $data[] = [
                'base_url' => $url,
                'login'    => env("WEBKASSA_LOGIN_{$i}", ''),
                'password' => env("WEBKASSA_PASSWORD_{$i}", ''),
            ];
            $i++;
        }

        return $data;
    })(),


    'api_key' => env('WEBKASSA_API_KEY', ''),

    /**
     * API-ключ для получения данных в веб сервисе
     */
    'api_key_data' => env('WEBKASSA_API_KEY_DATA', ''),

    /**
     * Загружать маршруты или нет. Если нужно свою обработку middleware написать
     */
    'load_routes' => true,

    /**
     * Количество записей при выборе
     */
    'operation_limit' => env('WEBKASSA_OPERATION_LIMIT', 100),
];