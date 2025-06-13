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
    'login' => env('WEBKASSA_LOGIN', ''),

    'password' => env('WEBKASSA_PASSWORD', ''),

    'api_key' => env('WEBKASSA_API_KEY', ''),

    'base_url' => env('WEBKASSA_BASE_URL', 'https://devkkm.webkassa.kz'),

    /**
     * API-ключ для получения данных в веб сервисе
     */
    'api_key_data' => env('WEBKASSA_API_KEY_DATA', ''),
];