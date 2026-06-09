# Notification Service

Микросервис массовых уведомлений на PHP 8.3 + Laravel 13.

## Возможности

- Массовая рассылка SMS/Email через API
- Приоритизация: транзакционные уведомления обрабатываются первыми (отдельные RabbitMQ очереди)
- Статусы доставки: `queued` → `sent` → `delivered` / `discarded`
- История уведомлений получателя с фильтрацией и пагинацией
- Дедупликация на двух уровнях: HTTP `Idempotency-Key` + бизнес-fingerprint батча
- Exactly-once обработка через Redis locks + `SELECT FOR UPDATE SKIP LOCKED`
- Retry с экспоненциальным backoff (1m, 5m, 15m, 30m, 60m)
- Mock-шлюзы SMS и Email (симулируют реальных провайдеров)
- Swagger UI документация

## Технологический стек

| Компонент        | Технология              |
|------------------|-------------------------|
| Язык/Фреймворк   | PHP 8.3 / Laravel 13    |
| База данных      | PostgreSQL 16           |
| Брокер очередей  | RabbitMQ 3.13           |
| Кэш              | Redis 7.2               |
| Веб-сервер       | Nginx 1.25              |
| Контейнеризация  | Docker + Docker Compose |

## Быстрый старт

### 1. Запуск через Docker Compose

```bash
git clone https://github.com/vamischenko/notification_service.git
cd notification_service

# Создать .env файл
cp .env.example .env

# Генерировать APP_KEY
docker run --rm -v $(pwd):/app -w /app php:8.3-cli php artisan key:generate --show
# Вписать полученный ключ в .env: APP_KEY=base64:...

# Запустить всё одной командой
docker-compose up -d --build

# Применить миграции и сидеры
docker-compose exec app php artisan migrate --seed
```

### 2. Проверка работоспособности

```bash
curl http://localhost:8080/up
```

### Доступные сервисы

| Сервис       | URL                                          |
|--------------|----------------------------------------------|
| API          | <http://localhost:8080/api/v1>               |
| Swagger UI   | <http://localhost:8080/api/documentation>    |
| RabbitMQ UI  | <http://localhost:15672> (app/secret)        |
| PostgreSQL   | localhost:5432                               |
| Redis        | localhost:6379                               |

## API Документация

Swagger UI доступен по адресу: **<http://localhost:8080/api/documentation>**

### Эндпоинты

#### POST /api/v1/notifications/batch

Запустить массовую рассылку.

```bash
curl -X POST http://localhost:8080/api/v1/notifications/batch \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: $(uuidgen)" \
  -d '{
    "channel": "sms",
    "priority": "transactional",
    "message_text": "Ваш код доступа: 1234",
    "recipient_ids": [
      "018e1b2a-0000-7000-8000-000000000001",
      "018e1b2a-0000-7000-8000-000000000002"
    ]
  }'
```

**Параметры тела запроса:**

| Поле            | Тип      | Обязательное | Описание                                |
|-----------------| ---------|:------------:|-----------------------------------------|
| `channel`       | string   | Да           | `sms` или `email`                       |
| `priority`      | string   | Да           | `transactional` или `marketing`         |
| `message_text`  | string   | Да           | Текст сообщения (max 1000 символов)     |
| `recipient_ids` | string[] | Да           | Массив UUID получателей (max 1000)      |

Заголовок `Idempotency-Key` (опционально) — UUID для защиты от дублирования запросов.

**Ответ 202 (новый батч):**

```json
{
  "data": {
    "batch_id": "uuid",
    "channel": "sms",
    "priority": "transactional",
    "message_text": "...",
    "total_count": 2,
    "queued_count": 2,
    "sent_count": 0,
    "delivered_count": 0,
    "discarded_count": 0,
    "progress_percent": 0,
    "created_at": "2024-01-15T10:00:00+00:00"
  },
  "meta": { "idempotent": false }
}
```

#### GET /api/v1/notifications/batches/{batchId}

Получить статус батча.

```bash
curl http://localhost:8080/api/v1/notifications/batches/{batch_id}
```

#### GET /api/v1/recipients/{recipientId}/notifications

История уведомлений получателя.

```bash
curl "http://localhost:8080/api/v1/recipients/{recipient_id}/notifications?status=delivered&channel=sms&per_page=15&page=1"
```

**Query параметры:**

| Параметр   | Тип     | Описание                                           |
|------------|---------|----------------------------------------------------|
| `status`   | string  | Фильтр: `queued`, `sent`, `delivered`, `discarded` |
| `channel`  | string  | Фильтр: `sms`, `email`                             |
| `per_page` | integer | Размер страницы (default: 15, max: 100)            |
| `page`     | integer | Номер страницы                                     |

## Архитектура

### Очереди RabbitMQ

```text
notifications.exchange (topic)
  ├── notifications.transactional  ← транзакционные (OTP, срочные)
  ├── notifications.marketing      ← маркетинговые рассылки
  └── notifications.dead           ← failed jobs (DLX)
```

Транзакционная очередь обслуживается **2 воркерами**, маркетинговая — **1 воркером**.
Это гарантирует что срочные уведомления не застревают за маркетинговыми рассылками.

### Дедупликация (exactly-once)

1. **HTTP уровень** — `Idempotency-Key` заголовок → Redis lock + PostgreSQL кэш ответа
2. **Бизнес-уровень** — SHA-256 fingerprint батча → `ON CONFLICT DO NOTHING`
3. **Job уровень** — Redis lock + `SELECT FOR UPDATE SKIP LOCKED` + проверка статуса

### Retry стратегия

| Попытка | Задержка  |
|---------|-----------|
| 1       | 1 минута  |
| 2       | 5 минут   |
| 3       | 15 минут  |
| 4       | 30 минут  |
| 5       | 60 минут  |
| failed  | → discard |

- `GatewayUnavailableException` — retriable (временная недоступность)
- `InvalidRecipientException` — немедленный discard (постоянная ошибка)

## Запуск тестов

Тесты требуют запущенного PostgreSQL и Redis (входят в docker-compose):

```bash
# Создать тестовую БД
docker-compose exec postgres psql -U app -c "CREATE DATABASE notification_service_test;"

# Запустить все тесты
docker-compose exec app php artisan test

# Только интеграционные тесты
docker-compose exec app php artisan test --testsuite=Integration

# Только unit-тесты
docker-compose exec app php artisan test --testsuite=Unit
```

## Makefile команды

```bash
make up                 # docker-compose up -d --build
make down               # docker-compose down
make migrate            # запустить миграции
make seed               # запустить сидеры
make test               # все тесты
make test-unit          # unit тесты
make test-integration   # интеграционные тесты
make swagger            # сгенерировать Swagger документацию
make logs               # логи воркеров
make shell              # bash в app-контейнере
```
