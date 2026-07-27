# Eng Std — бэкенд (Laravel 13 / PHP 8.4, Docker)

API для персонального тренажёра английского: коллекции слов, интервальное повторение (FSRS),
Google-авторизация и интеграция Claude (генерация коллекций + проверка ответов).

## Запуск (всё в Docker)

```bash
docker compose up -d
```

Поднимает три сервиса:
| Сервис | Что делает | Порт |
|--------|-----------|------|
| `app` | Laravel API (`php artisan serve`), миграции на старте | http://localhost:8000 |
| `queue` | воркер очереди для async AI-генерации | — |
| `ngrok` | публичный туннель к API | инспектор: http://localhost:4040 |

Публичный URL API:
```bash
curl -s http://localhost:4040/api/tunnels | grep -o '"public_url":"https://[^"]*"'
```

Остановить: `docker compose down`. Логи: `docker compose logs -f app`.

## Конфигурация (`.env`)

| Ключ | Назначение |
|------|-----------|
| `ANTHROPIC_API_KEY` | ключ Claude для генерации/проверки (console.anthropic.com) |
| `CLAUDE_GENERATE_MODEL` | модель генерации (по умолчанию `claude-sonnet-5`) |
| `CLAUDE_CHECK_MODEL` | модель проверки (по умолчанию `claude-haiku-4-5-20251001`) |
| `GOOGLE_IOS_CLIENT_ID` | OAuth client ID (iOS) для проверки Google ID-токена |
| `GOOGLE_WEB_CLIENT_ID` | (опц.) web/server OAuth client ID |
| `NGROK_AUTHTOKEN` | токен ngrok (уже проставлен) |

После правки `.env`: `docker compose restart`.

## Эндпоинты

Публичные: `POST /api/auth/google`, `GET /api/health`.
Остальные — под `auth:sanctum` (заголовок `Authorization: Bearer <token>`).
Полный контракт: [../docs/API_CONTRACT.md](../docs/API_CONTRACT.md).

## Artisan внутри контейнера

```bash
docker compose exec app php artisan <command>
docker compose exec app php artisan tinker
```
