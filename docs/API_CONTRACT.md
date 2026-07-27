# API-контракт (Laravel ↔ Flutter)

Базовый URL: `{API_BASE_URL}/api` · Авторизация: `Authorization: Bearer <token>` (Sanctum).
Все ответы — JSON. Даты в ISO-8601 UTC.

## Аутентификация

Личное приложение на одного пользователя, поэтому упрощённо:

```
POST /auth/token
  body:  { "email": string, "password": string }
  resp:  { "token": string }
```

## Коллекции

```
GET  /collections
  resp: [ Collection ]

POST /collections
  body: { "title": string, "topic": string|null }
  resp: Collection

GET  /collections/{id}
  resp: Collection & { "words": [ Word ] }

DELETE /collections/{id}
```

## Слова

```
POST /collections/{id}/words
  body: { "term": string, "translation": string, "transcription": string|null, "example": string|null }
  resp: Word

DELETE /words/{id}
```

## Тренажёр (FSRS на бэкенде)

```
GET  /reviews/due?limit=20
  resp: [ ReviewCard ]           // слова, которые пора повторить сегодня

POST /reviews/{wordId}/answer
  body: { "rating": 1|2|3|4 }    // 1=again, 2=hard, 3=good, 4=easy
  resp: { "next_due_at": string, "state": ReviewState }
```

## Генерация коллекции ИИ (Claude)

```
POST /collections/generate
  body: {
    "topic": string,            // напр. "Airport & travel"
    "level": "A1|A2|B1|B2|C1",
    "size": number              // сколько слов (напр. 15)
  }
  resp: { "job_id": string, "status": "queued" }

GET  /ai/jobs/{jobId}
  resp: { "status": "queued|processing|done|failed",
          "collection_id": number|null,
          "error": string|null }
```

## Проверка открытого ответа ИИ (Claude)

```
POST /ai/check
  body: { "word_id": number, "user_answer": string, "mode": "translation|usage" }
  resp: {
    "correct": boolean,
    "score": number,            // 0..100
    "feedback": string,         // краткое объяснение
    "corrected": string|null
  }
```

## Схемы

```
Collection {
  id: number
  title: string
  topic: string|null
  source: "manual" | "ai"
  words_count: number
  created_at: string
}

Word {
  id: number
  collection_id: number
  term: string
  translation: string
  transcription: string|null
  example: string|null
  cefr_level: string|null
}

ReviewCard {
  word: Word
  state: ReviewState
}

ReviewState {
  stability: number
  difficulty: number
  reps: number
  due_at: string
  last_rating: number|null
}
```
