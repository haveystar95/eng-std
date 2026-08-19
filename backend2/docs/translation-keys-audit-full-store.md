<!-- snapshot: 2026-08-19T14:34:14+00:00 · head: 67f8ccd -->
# Переводы-ключи на вычитку

Снимок: **2026-08-19T14:34:14+00:00** · HEAD: `67f8ccd` · направление: `en` → `ru`.

Ключ должен ОДНОЗНАЧНО указывать на свой источник. Ломается это в обе стороны, и обе
здесь:

- **LOST** — источник к кому-то обращается, а перевод этого не несёт: по такому переводу
  нельзя однозначно восстановить источник, и честный ответ уходит в лог как промах.
- **EXTRA** — перевод несёт то, чего в источнике нет: «I get along with my team» →
  «Я **хорошо** лажу со своей командой». Тут наоборот — учащийся отвечает верно, а ключ
  требует слова, которого в источнике никогда не было.

Прогон по ВСЕЙ витрине: языки не задавались списком, а взяты из самого контента —
сколько языков перевода в базе, столько и просмотрено.

**Детектор грубый и ничего не правит.** Язык перевода выражает лицо способами, которых он
не видит: обращение может быть уже зашито в глагол, родительный падеж — подразумеваться,
притяжательное «свой»/«свій» — стоять за «ваш»/«ваш». Строка здесь — кандидат на прочтение,
а не приговор. Правки едут отдельным заходом через существующий apply-механизм.

Просмотрено пар: **876** — терминных **432**, примерных **444**. Кандидатов: **56** — LOST **56**, EXTRA **0**.

Пример — такой же ключ, как термин: его показывают, произносят и отвечают на него, поэтому
потерянный адресат в переводе примера ломает карточку ровно так же.

## Языки

Колонка «правило знает язык» — не формальность: для языка без списка соответствий детектор
молчит по построению, и ноль кандидатов там означает «не проверено», а не «чисто».

| язык | терминных пар | примерных пар | кандидатов | правило знает язык |
|---|---|---|---|---|
| `ru` | 432 | 444 | 56 | да |

## Кандидаты

Колонка «что не так» читается по направлению. LOST: слово САМОГО источника, которое
перевод не отразил, и формы, которые детектор счёл бы ответом на него. EXTRA: слово
ПЕРЕВОДА, которого источник не давал, и слова источника, которые его бы оправдали.
Это критерий детектора, а не предложенная правка: если перевод несёт лицо иначе
(глаголом, «свой»), строка — ложное срабатывание, и это видно прямо здесь.

### Термины (16)

| ← | язык | термин | текущий перевод | что не так | коллекция | группа |
|---|---|---|---|---|---|---|
| **LOST** | `ru` | Can you describe it? | Можете это описать? | `you` → вы/вас/вам/вами/ваш/ваша/… (35 форм) | Экстренные ситуации | `you/your` |
| **LOST** | `ru` | Can you give an example of a successful team project? | Можете привести пример успешного командного проекта? | `you` → вы/вас/вам/вами/ваш/ваша/… (35 форм) | Собеседование в IT: продвинутый уровень | `you/your` |
| **LOST** | `ru` | Can you tell me about the career growth opportunities? | Можете рассказать о возможностях карьерного роста? | `me` → нам/нас/нами/мне/меня/мной/… (8 форм); `you` → вы/вас/вам/вами/ваш/ваша/… (35 форм) | Собеседование в IT | `us/me`, `you/your` |
| **LOST** | `ru` | Can you walk me through your design process? | Можете рассказать о вашем процессе проектирования? | `me` → нам/нас/нами/мне/меня/мной/… (8 форм) | Собеседование в IT: продвинутый уровень | `us/me` |
| **LOST** | `ru` | Can you work under tight deadlines? | Можете работать в условиях сжатых сроков? | `you` → вы/вас/вам/вами/ваш/ваша/… (35 форм) | Job Interview Preparation | `you/your` |
| **LOST** | `ru` | check your messages | проверить свои сообщения | `your` → вы/вас/вам/вами/ваш/ваша/… (35 форм) | Телефон и мессенджеры | `you/your` |
| **LOST** | `ru` | Could you change my reservation? | Можете изменить моё бронирование? | `you` → вы/вас/вам/вами/ваш/ваша/… (35 форм) | Отель: бронь и заселение | `you/your` |
| **LOST** | `ru` | Could you elaborate on that? | Можете уточнить этот момент? | `you` → вы/вас/вам/вами/ваш/ваша/… (35 форм) | Собеседование в IT: продвинутый уровень | `you/your` |
| **LOST** | `ru` | Could you tell me the way to...? | Не могли бы вы подсказать путь до...? | `me` → нам/нас/нами/мне/меня/мной/… (8 форм) | Городской транспорт и такси | `us/me` |
| **LOST** | `ru` | Excuse me, is this seat taken? | Извините, это место занято? | `me` → нам/нас/нами/мне/меня/мной/… (8 форм) | Городской транспорт и такси | `us/me` |
| **LOST** | `ru` | fasten your seatbelt | пристегните ремень безопасности | `your` → вы/вас/вам/вами/ваш/ваша/… (35 форм) | В аэропорту и на рейсе | `you/your` |
| **LOST** | `ru` | How do you do? | Как поживаете? | `you` → вы/вас/вам/вами/ваш/ваша/… (35 форм) | Знакомство и small talk | `you/your` |
| **LOST** | `ru` | Nice to meet you | Приятно познакомиться | `you` → вы/вас/вам/вами/ваш/ваша/… (35 форм) | Знакомство и small talk | `you/your` |
| **LOST** | `ru` | reset your password | сбросить свой пароль | `your` → вы/вас/вам/вами/ваш/ваша/… (35 форм) | Компьютер и интернет | `you/your` |
| **LOST** | `ru` | secure your account | обезопасить свой аккаунт | `your` → вы/вас/вам/вами/ваш/ваша/… (35 форм) | Компьютер и интернет | `you/your` |
| **LOST** | `ru` | Would you like a receipt? | Хотите квитанцию? | `you` → вы/вас/вам/вами/ваш/ваша/… (35 форм) | Buying Dog Food at the Store | `you/your` |

### Примеры (40)

| ← | язык | термин | пример | перевод примера | что не так | коллекция | группа |
|---|---|---|---|---|---|---|---|
| **LOST** | `ru` | baggage claim | After landing, proceed to baggage claim to collect your luggage. | После приземления пройдите к выдаче багажа, чтобы забрать багаж. | `your` → вы/вас/вам/вами/ваш/ваша/… (35 форм) | В аэропорту и на рейсе | `you/your` |
| **LOST** | `ru` | call back | Could you call me back later? | Можешь перезвонить мне позже? | `you` → вы/вас/вам/вами/ваш/ваша/… (35 форм) | Телефон и мессенджеры | `you/your` |
| **LOST** | `ru` | Can you describe it? | Can you describe it to the police officer? | Можете это описать полицейскому? | `you` → вы/вас/вам/вами/ваш/ваша/… (35 форм) | Экстренные ситуации | `you/your` |
| **LOST** | `ru` | Can you tell me about the career growth opportunities? | Can you tell me about the career growth opportunities at your company? | Можете рассказать о возможностях карьерного роста в вашей компании? | `me` → нам/нас/нами/мне/меня/мной/… (8 форм) | Собеседование в IT | `us/me` |
| **LOST** | `ru` | Can you walk me through your design process? | Can you walk me through your design process for this task? | Можете рассказать о вашем процессе проектирования для этой задачи? | `me` → нам/нас/нами/мне/меня/мной/… (8 форм) | Собеседование в IT: продвинутый уровень | `us/me` |
| **LOST** | `ru` | Can you work under tight deadlines? | Can you work under tight deadlines and still maintain quality? | Можете работать в условиях сжатых сроков, сохраняю качество? | `you` → вы/вас/вам/вами/ваш/ваша/… (35 форм) | Job Interview Preparation | `you/your` |
| **LOST** | `ru` | carry out | Please carry out all items from your grocery list. | Пожалуйста, выполните все пункты из списка продуктов. | `your` → вы/вас/вам/вами/ваш/ваша/… (35 форм) | Покупки в супермаркете | `you/your` |
| **LOST** | `ru` | check-in desk | Please proceed to the check-in desk to collect your boarding pass. | Пожалуйста, пройдите к стойке регистрации, чтобы получить посадочный талон. | `your` → вы/вас/вам/вами/ваш/ваша/… (35 форм) | В аэропорту и на рейсе | `you/your` |
| **LOST** | `ru` | check your messages | Don't forget to check your messages when you get home. | Не забудь проверить свои сообщения, когда вернёшься домой. | `you` → вы/вас/вам/вами/ваш/ваша/… (35 форм); `your` → вы/вас/вам/вами/ваш/ваша/… (35 форм) | Телефон и мессенджеры | `you/your` |
| **LOST** | `ru` | Could you change my reservation? | Could you change my reservation to include an extra night? | Можете изменить моё бронирование, чтобы добавить еще одну ночь? | `you` → вы/вас/вам/вами/ваш/ваша/… (35 форм) | Отель: бронь и заселение | `you/your` |
| **LOST** | `ru` | Could you tell me the way to...? | Could you tell me the way to the nearest subway station? | Не могли бы вы подсказать путь до ближайшей станции метро? | `me` → нам/нас/нами/мне/меня/мной/… (8 форм) | Городской транспорт и такси | `us/me` |
| **LOST** | `ru` | download a file | Can you download the file from the website? | Можешь загрузить файл с сайта? | `you` → вы/вас/вам/вами/ваш/ваша/… (35 форм) | Компьютер и интернет | `you/your` |
| **LOST** | `ru` | exchange policy | The exchange policy allows you to return items within 30 days. | Политика обмена позволяет возвратить товары в течение 30 дней. | `you` → вы/вас/вам/вами/ваш/ваша/… (35 форм) | Магазин одежды и размеры | `you/your` |
| **LOST** | `ru` | fasten your seatbelt | Please fasten your seatbelt as we prepare for takeoff. | Пожалуйста, пристегните ремень безопасности, так как мы готовимся к взлету. | `your` → вы/вас/вам/вами/ваш/ваша/… (35 форм) | В аэропорту и на рейсе | `you/your` |
| **LOST** | `ru` | gate number | Check your gate number on the display board. | Проверьте номер выхода на посадку на табло. | `your` → вы/вас/вам/вами/ваш/ваша/… (35 форм) | В аэропорту и на рейсе | `you/your` |
| **LOST** | `ru` | Hang on | Hang on a minute, I'll be right with you. | Подождите минутку, я сейчас подойду. | `you` → вы/вас/вам/вами/ваш/ваша/… (35 форм) | Phrasal Verbs and Conditional Sentences | `you/your` |
| **LOST** | `ru` | How do you do? | How do you do? Nice to meet you. | Как поживаете? Приятно познакомиться. | `you` → вы/вас/вам/вами/ваш/ваша/… (35 форм) | Знакомство и small talk | `you/your` |
| **LOST** | `ru` | How much does this bag cost? | Can you tell me, how much does this bag cost at the new store? | Можешь сказать, сколько стоит эта сумка в новом магазине? | `me` → нам/нас/нами/мне/меня/мной/… (8 форм); `you` → вы/вас/вам/вами/ваш/ваша/… (35 форм) | Buying Dog Food at the Store | `us/me`, `you/your` |
| **LOST** | `ru` | Is this suitable for small breeds? | Can you recommend a toy that is suitable for small breeds? | Можете порекомендовать игрушку, которая подходит для мелких пород? | `you` → вы/вас/вам/вами/ваш/ваша/… (35 форм) | Buying Dog Food at the Store | `you/your` |
| **LOST** | `ru` | leave a message | Can you leave a message after the beep? | Можете оставить сообщение после сигнала? | `you` → вы/вас/вам/вами/ваш/ваша/… (35 форм) | Телефон и мессенджеры | `you/your` |
| **LOST** | `ru` | log in | Please log in to access your account. | Пожалуйста, войдите в систему, чтобы получить доступ к своему аккаунту. | `your` → вы/вас/вам/вами/ваш/ваша/… (35 форм) | Компьютер и интернет | `you/your` |
| **LOST** | `ru` | Look after | Can you look after the children while I am away? | Можешь присмотреть за детьми, пока меня не будет? | `you` → вы/вас/вам/вами/ваш/ваша/… (35 форм) | Phrasal Verbs and Conditional Sentences | `you/your` |
| **LOST** | `ru` | mute the phone | Please mute your phone during the meeting. | Пожалуйста, отключите звук на телефоне во время встречи. | `your` → вы/вас/вам/вами/ваш/ваша/… (35 форм) | Телефон и мессенджеры | `you/your` |
| **LOST** | `ru` | Nice to meet you | Nice to meet you, I'm Sarah. | Приятно познакомиться, я Сара. | `you` → вы/вас/вам/вами/ваш/ваша/… (35 форм) | Знакомство и small talk | `you/your` |
| **LOST** | `ru` | pain reliever | Can you recommend a good pain reliever? | Можете порекомендовать хорошее обезболивающее? | `you` → вы/вас/вам/вами/ваш/ваша/… (35 форм) | Going to the Pharmacy: Pain Relief | `you/your` |
| **LOST** | `ru` | secure your account | Enable two-factor authentication to secure your account. | Включите двухфакторную аутентификацию, чтобы обезопасить свой аккаунт. | `your` → вы/вас/вам/вами/ваш/ваша/… (35 форм) | Компьютер и интернет | `you/your` |
| **LOST** | `ru` | security check | Please remove your shoes at the security check. | Пожалуйста, снимите обувь на досмотре безопасности. | `your` → вы/вас/вам/вами/ваш/ваша/… (35 форм) | В аэропорту и на рейсе | `you/your` |
| **LOST** | `ru` | stay calm | Stay calm and tell me what’s wrong. | Оставайтесь спокойны и расскажите, что произошло. | `me` → нам/нас/нами/мне/меня/мной/… (8 форм) | Экстренные ситуации | `us/me` |
| **LOST** | `ru` | take medicine | Please remember to take your medicine twice a day. | Пожалуйста, не забывайте принимать лекарство два раза в день. | `your` → вы/вас/вам/вами/ваш/ваша/… (35 форм) | У врача и в аптеке | `you/your` |
| **LOST** | `ru` | Tell me about a time you faced a challenge at work. | Tell me about a time you faced a challenge at work and how you overcame it. | Расскажите о случае, когда вы столкнулись с трудностью на работе, и как вы ее преодолели. | `me` → нам/нас/нами/мне/меня/мной/… (8 форм) | Собеседование в IT: продвинутый уровень | `us/me` |
| **LOST** | `ru` | Tell me about yourself | Could you tell me about yourself and your career background? | Можете рассказать о себе и своей карьере? | `me` → нам/нас/нами/мне/меня/мной/… (8 форм); `you` → вы/вас/вам/вами/ваш/ваша/… (35 форм); `your` → вы/вас/вам/вами/ваш/ваша/… (35 форм) | Job Interview Preparation | `us/me`, `you/your` |
| **LOST** | `ru` | Tell us about a challenge you faced | Tell us about a challenge you faced and how you overcame it. | Расскажите о вызове, с которым вы столкнулись, и как вы его преодолели. | `us` → нам/нас/нами/мне/меня/мной/… (8 форм) | Job Interview Preparation | `us/me` |
| **LOST** | `ru` | There's an issue with my room. | Excuse me, there's an issue with my room; the air conditioning isn't working. | Извините, проблема с моим номером; кондиционер не работает. | `me` → нам/нас/нами/мне/меня/мной/… (8 форм) | Отель: бронь и заселение | `us/me` |
| **LOST** | `ru` | tray table | Please fold up your tray table before landing. | Пожалуйста, сложите свой откидной столик перед посадкой. | `your` → вы/вас/вам/вами/ваш/ваша/… (35 форм) | В аэропорту и на рейсе | `you/your` |
| **LOST** | `ru` | upload a document | Please upload your document to the portal. | Пожалуйста, загрузите свой документ на портал. | `your` → вы/вас/вам/вами/ваш/ваша/… (35 форм) | Компьютер и интернет | `you/your` |
| **LOST** | `ru` | What are your weaknesses? | Could you tell us about your weaknesses and how you work on them? | Можете рассказать о ваших слабых сторонах и как вы над ними работаете? | `us` → нам/нас/нами/мне/меня/мной/… (8 форм) | Job Interview Preparation | `us/me` |
| **LOST** | `ru` | What happened? | Can you tell me what happened? | Можете рассказать, что случилось? | `me` → нам/нас/нами/мне/меня/мной/… (8 форм); `you` → вы/вас/вам/вами/ваш/ваша/… (35 форм) | Экстренные ситуации | `us/me`, `you/your` |
| **LOST** | `ru` | What time is check-out? | Excuse me, what time is check-out tomorrow? | Извините, во сколько завтра выезд? | `me` → нам/нас/нами/мне/меня/мной/… (8 форм) | Отель: бронь и заселение | `us/me` |
| **LOST** | `ru` | Where is the elevator? | Can you tell me where the elevator is? | Можете сказать, где находится лифт? | `me` → нам/нас/нами/мне/меня/мной/… (8 форм); `you` → вы/вас/вам/вами/ваш/ваша/… (35 форм) | Отель: бронь и заселение | `us/me`, `you/your` |
| **LOST** | `ru` | Wi-Fi password | Can you give me the Wi-Fi password, please? | Можете дать мне пароль от Wi-Fi, пожалуйста? | `you` → вы/вас/вам/вами/ваш/ваша/… (35 форм) | Отель: бронь и заселение | `you/your` |

## Разбивка по группам

| группа | кандидатов |
|---|---|
| `us/me` | 17 |
| `you/your` | 44 |
| `we/our` | 0 |
| `well/хорошо` | 0 |

## Разбивка по коллекциям

Термин живёт в нескольких колодах сразу, поэтому сумма по строкам больше числа
кандидатов — это счётчик «сколько кандидатов спрашивает эта колода», а не дележ.

| коллекция | кандидатов |
|---|---|
| В аэропорту и на рейсе | 7 |
| Компьютер и интернет | 6 |
| Отель: бронь и заселение | 6 |
| Job Interview Preparation | 5 |
| Собеседование в IT: продвинутый уровень | 5 |
| Телефон и мессенджеры | 5 |
| Знакомство и small talk | 4 |
| Экстренные ситуации | 4 |
| Buying Dog Food at the Store | 3 |
| Городской транспорт и такси | 3 |
| Phrasal Verbs and Conditional Sentences | 2 |
| Собеседование в IT | 2 |
| Going to the Pharmacy: Pain Relief | 1 |
| Магазин одежды и размеры | 1 |
| Покупки в супермаркете | 1 |
| У врача и в аптеке | 1 |

