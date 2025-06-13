# Webkassa — Инструкция по установке

---

## 1. Запуск миграций базы данных

Для применения миграций, которые идут с пакетом, выполните команду:

```bash
php artisan migrate
```


## 2. Публикация конфигурации и представлений

Для публикации файла конфигурации выполните команду:

```bash
php artisan vendor:publish --tag=webkassa-integration
```
Для публикации представлений (views) выполните команду:

```bash
php artisan vendor:publish --tag=webkassa-integration-views
```

## 3. Переменные окружения (env)

В файле .env необходимо добавить или настроить следующие переменные:

```ini
# Включение логирования ошибок (true/false)
WEBKASSA_ERROR_LOG=true

# Включение отправки ошибок на почту (true/false)
WEBKASSA_ERROR_MAIL=false

# Кому отправлять письмо при ошибке (если включена отправка почты)
WEBKASSA_MAIL_TO=mail@localhost.lan

# Тема письма для ошибок
WEBKASSA_MAIL_SUBJECT="WebkassaFetchData Job Failed"

# Специальный ключ в Webkassa API-KEY
WEBKASSA_API_KEY=""

# Данные для авторизации в Webkassa API (логин)
WEBKASSA_LOGIN=""

# Данные для авторизации в Webkassa API (пароль)
WEBKASSA_PASSWORD=""

# Базовый URL Webkassa (пример тестового адреса)
WEBKASSA_URL=""

# API-ключ для получения данных из веб сервиса
WEBKASSA_API_KEY_DATA
```

## 4. Дополнительная информация

Для корректной работы почтовых уведомлений необходимо настроить в Laravel соответствующий драйвер почты (MAIL_MAILER и другие).

Логи ошибок будут писаться, только если в конфиге 'error_log' включено.

Отправка ошибок на почту происходит, только если 'error_mail' включено и указан адрес получателя.

## 5. Получение данных из Webkassa (потоковая выгрузка)
### 5.1. Получение данных из Webkassa вручную

Для получения накопленных данных из системы используется следующая очередь:
```php
use webdophp\WebkassaIntegration\Jobs\AuthorizeWebkassa;

AuthorizeWebkassa::dispatch();
```

### 5.2. Автоматический запуск через планировщик (scheduler)

Для автоматического получения данных из системы 
рекомендуется настроить вызов AuthorizeWebkassa::dispatch();
через Laravel Scheduler.

Например, в методе schedule() файла app/Console/Kernel.php добавьте:

```php
use webdophp\WebkassaIntegration\Jobs\AuthorizeWebkassa;

protected function schedule(Schedule $schedule)
{
    $schedule->call(function () {
        AuthorizeWebkassa::dispatch();
    })->everyFiveMinutes(); // или любое другое расписание
}
```

Требования
==========================
> Для корректной работы очереди необходимо:
>
> Убедиться, что очереди настроены в Laravel. Например, в .env указано:
> ```ini
> QUEUE_CONNECTION=database
> ```
> Создать таблицу для хранения очередей (если используется database драйвер):
> ```bash
> php artisan queue:table
> php artisan migrate
> ```
> Запустить обработчик очередей:
> ```bash
> php artisan queue:work
> ```

## 6. Вызовы API
#### 1. Проверка доступности сервиса
```bash
GET http://localhost/api/webkassa/ping
```
#### 2. Получить данные
```bash
GET http://localhost/api/webkassa/data
```
#### 3. Подтвердить получение данных
```bash
GET http://localhost/api/webkassa/confirm
```
Обязательные заголовки
>
> Каждый запрос к API должен содержать обязательный заголовок API-KEY.
>
>Пример заголовков:
>
> API-KEY: WEBKASSA_API_KEY_DATA (ваш_ключ_доступа)

Пример с использованием curl:
```bash
curl -X GET http://localhost:8000/api/webkassa/ping \
  -H "API-KEY: ваш_ключ_доступа"
```




