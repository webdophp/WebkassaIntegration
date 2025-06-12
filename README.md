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
```

## 4. Дополнительная информация

Для корректной работы почтовых уведомлений необходимо настроить в Laravel соответствующий драйвер почты (MAIL_MAILER и другие).

Логи ошибок будут писаться, только если в конфиге 'error_log' включено.

Отправка ошибок на почту происходит, только если 'error_mail' включено и указан адрес получателя.





