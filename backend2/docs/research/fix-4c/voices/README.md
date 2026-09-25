# FIX-4c §1 — второй голос собеседника: кандидаты для выбора Дена

Фраза у всех одна: «Good morning. What brings you in today? Take a seat, please.» (60 символов), модель и настройка —
как у языкового пакета (`eleven_v3_conversational`, stability 0.5), то есть так, как голос прозвучит в приложении.
Нужен **один id на женский и один на мужской** — это будут `SPEECH_VOICE_EN_PARTNER_FEMALE_2` / `_MALE_2`.

| пол | № | файл | id | имя в библиотеке — характер | откуда |
|---|---|---|---|---|---|
| Ж | 1 | `female-1-talia.mp3` | `OZ0L6eISlOejga3XjDFt` | Talia — Warm Soft Guide | замена Default «Sarah» |
| Ж | 2 | `female-2-maisie.mp3` | `QtY3JBOUKEB5xzrRfOKc` | Maisie — Friendly Casual Neighbor | замена Default «Matilda» |
| Ж | 3 | `female-3-jade.mp3` | `g7LVvkPWALzPxOQbF6OE` | Jade — Upbeat and Natural | замена Default «Jessica» |
| Ж | 4 | `female-4-juniper.mp3` | `aMSt68OGf4xUZAnLpTU8` | Juniper — Grounded and Professional | библиотека, «conversational» (ConvoAI) |
| М | 1 | `male-1-eddie.mp3` | `l7kNoIfnJKPg7779LI2t` | Eddie — Helpful and Comforting | замена Default «Eric» |
| М | 2 | `male-2-kellan.mp3` | `cymHWdiF8WjUCg6vvFxx` | Kellan — Casual Friendly Speaker | замена Default «Callum» |
| М | 3 | `male-3-caleb.mp3` | `AaOhDHYJ1XLZk74lXhdE` | Caleb — Trusted Guide | замена Default «Chris» |
| М | 4 | `male-4-wyatt.mp3` | `FrS6cKLB1wg4WYgPa9GW` | Wyatt — Seasoned Mentor | замена Default «Bill» |

Исключены уже занятые голоса пакета: `4NejU5DwQjevnR6mh3mb`, `EnjklPXGBMNldCJ7jqkE`, `TWutjvRaJqAX89preB4e`,
`Nhs7eitvQWFTQBsf0yiT`.

**Откуда кандидаты и почему возраст не из библиотеки.** У ключа ElevenLabs нет права `voices_read`: и общая библиотека
(`/v1/shared-voices`), и карточка голоса отвечают `401 missing_permissions`. Искать «тёплые, разговорные, разных
возрастов» фильтрами API нечем, а менять права ключа — настройка аккаунта Дена, не наряда. Поэтому семь кандидатов —
официальные голоса, которыми ElevenLabs заменяет свои Default-голоса (Default истекают **31.12.2026**, брать их для голоса,
который закрепляется за сценой навсегда, нельзя), и один — ConvoAI-голос публичной страницы библиотеки. Id прочитаны с
собственных ссылок вендора (таблица замен в справке ElevenLabs → `voice-library?search=<id>`). Характер — название голоса
в библиотеке; возраст библиотека этим ключом не отдаёт — ориентир только по голосу, который заменён (Sarah, Jessica —
молодые; Matilda, Eric, Chris, Callum — средних лет; Bill — пожилой), а решает слух.

**Покупка:** 8 строк · 480 символов · 120 кредитов · $0,024 (кап наряда на образцы ≈ $0,05). Счётчик аккаунта до —
14 215 из 39 224; ответы вендора построчно — `samples.json`. Инструмент — `../tools/voice-samples.php`.

**Выбор Дена (25.09):** Ж — `female-2-maisie` (Maisie, `QtY3JBOUKEB5xzrRfOKc`), М — `male-3-caleb` (Caleb,
`AaOhDHYJ1XLZk74lXhdE`); они — `SPEECH_VOICE_EN_PARTNER_FEMALE_2` / `_MALE_2` (отчёт FIX-4c §1).
