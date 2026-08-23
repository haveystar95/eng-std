# eng-std — старый бэкенд (`backend/`)

**Что это.** Первый, плоский Laravel-API тренажёра (Http/Models/Services/Actions/Policies/
Resources, SQLite, FSRS-повторения, вход через Google, генерация коллекций и проверка ответов
моделью). Он работает и остаётся живым до явного решения о выводе; приложение при этом уже
говорит с `../backend2` — переписанным модульным API. Два стека одновременно не поднимать:
ngrok-домен у них один.

Развёрнутый контекст проекта — в корневом `../CLAUDE.md`; контракт этого API —
`../docs/API_CONTRACT.md`. Новый бэкенд документирован отдельно (`../backend2/`).

## Запуск

```bash
docker compose up -d
```

Три сервиса: `app` (Laravel на `:8000`, миграции на старте), `queue` (воркер для асинхронной
генерации), `ngrok` (публичный туннель, инспектор на `:4040`). Artisan —
`docker compose exec app php artisan …`. Логи — `docker compose logs -f app`.

**Готча:** воркер очереди держит код джобов в памяти. После правки `app/Jobs/*`, промптов или
провайдера — `docker compose restart queue`. После правки `.env` — тоже.

## Модель

`AI_PROVIDER` выбирает провайдера за одним портом (`App\Services\Ai\AiProvider`): сейчас в `.env`
стоит `openai` (`OPENAI_GENERATE_MODEL` / `OPENAI_CHECK_MODEL`), рядом живут `ollama` (локальный,
через `host.docker.internal:11434`) и `claude` (ключ есть, кредитов у организации нет — дефолт в
`config/services.php` остался историческим). Вход — `GOOGLE_IOS_CLIENT_ID` + Sanctum-токен.

## Поверхность

`POST /auth/google`, `GET /auth/me`, `POST /auth/logout`, профиль, CRUD коллекций + `generate`,
CRUD слов, `GET /reviews/due` + `POST /reviews/{word}/answer`, `GET /stats`, `POST /ai/check`,
`GET /ai/jobs/{id}`, `GET /health`.
