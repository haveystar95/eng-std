# Вычитка enrich-v2-backfill (архитектор, 13.08.2026)

> **Поправки при применении (13.08.2026, `326524f`).** Применена review-файлом
> `database/review/enrich-v2-backfill-review.json`, 31/32 дистракторов + 7/9 вариантов + 1
> span/correction + 1 перевод. Не сошлось 3 строки, все три предсказаны самой вычиткой (не
> угаданы задним числом):
>
> - **B.3 (sign the lease) — не найдена.** У термина `sign the lease` в базе только один
>   эталон-пример — `We signed the lease yesterday.` — и он не содержит цитируемого вычиткой
>   предложения `You need to sign the lease before moving in.`. Три живых дистрактора на этом
>   примере не тронуты (не совпадают ни по одному полю с этой строкой вычитки).
> - **E.1 (bank account / savings account, checking account) — разрешено SQL-проверкой перед
>   применением C.1/C.2, как и просила вычитка.** `SELECT` по `term_accepted_variants` для
>   `bank account` (единственный `term_id` с этим текстом по всей базе) и отдельно по `text ILIKE
>   '%savings account%' OR '%checking account%'` без привязки к термину — оба варианта
>   отсутствуют, 0 строк. **Отчёт `521f475` не солгал: 6/6 — верное число, удаление сработало.**
>   Причина видимости в снимке «12:27»: коммит `521f475` (сам применивший это удаление) сделан в
>   **14:36:56** — на два часа позже 12:27. Снимок, на который смотрела вычитка, физически не мог
>   быть «после добивки» (топап `d158440`, 14:00:49) *и* после `521f475` одновременно — 12:27
>   раньше обоих. Значит выгрузка снята ДО применения `521f475`, а не после, и «расхождение» — это
>   вычитка, честно увидевшая предыдущее (ещё не почищенное) состояние базы, ошибочно датированная
>   «после». Ничего не пришлось удалять: `remove_variants` для C.1/C.2 корректно вернула
>   «не найден» — база уже была чистой. `EloquentTermEnrichmentWriter::append()` только
>   `insertOrIgnore`, никогда не удаляет, так что более поздний бэкфилл (`9e428fb`) не мог вернуть
>   эти варианты обратно, даже если бы модель предложила их снова (экспорт `9e428fb` показывает 0
>   вариантов, предложенных для `bank account` в этом прогоне).
>
> Числа по остальным секциям сошлись день-в-день с ожиданием: A+B = 31/32 (B.3 — исключение выше),
> C = 7/9 (C.1/C.2 — исключение выше), D = 2/2.

Метод: две ⚠️-коллекции целиком, Phrasal Verbs целиком, остальное выборочно. Применять
review-файлом по образцу 01a4380/521f475. Удаления пишут suppression. Термины, общие для
нескольких коллекций (дедуп), — строка одна, удалять по row id.

## A. УДАЛИТЬ: дистрактор — корректное английское предложение (17)

1. uppercut — `He knocked out his opponent with a quick uppercut.` (оба порядка частицы легальны)
2. self-checkout — `I prefer to use the self-checkout for small purchases.` (prefer to use = prefer using)
3. What do you like to do in your free time? — `What do you like doing in your free time?` (like doing легально)
4. How's it going? — `Hey, Tom! How is it going?` (расстяжка контракции)
5. a little bit about me — `Here is a little bit about me.` (расстяжка контракции)
6. try on — `You should try the jacket on before buying it.` (перенос частицы легален)
7. It's a pleasure to meet you — `It's a pleasure meeting you, Karen.` (pleasure meeting — норма)
8. introduce yourself — `On your first day, you introduce yourself to your new coworkers.` (утверждение вместо императива — легально)
9. colleague — `You'll find your colleagues to be very helpful.` (find X to be — норма)
10. log in — `Please log on to access your account.` (log on — синоним log in)
11. reset your password — `If you've forgotten your password, reset it by email.` (by/via оба верны)
12. join a gym — `I want to join the gym to improve my fitness.` (the gym — идиоматично)
13. feel energized — `After the workout, I always feel energized.`
14. cash register — `The cash register was down, so we had to wait for a while.` (добавленное for a while легально; термин общий для «Покупки в супермаркете» и «Магазин одежды»)
15. try out — `I'm going to try the new software out today.` (перенос частицы)
16. in good shape — `He is in good shape because he is exercising regularly.` (continuous легален)
17. roll back — `If the update fails, we'll roll back the previous version.` (переходное употребление легально)

## B. УДАЛИТЬ: легальная замена определителя/модала, перевод не якорит (15)

1. Can I help you with that? — `Can I help you with the heavy bag?`
2. salary — `What are the salary expectations?`
3. sign the lease (Discussing Long-term House Rental) — `You need to sign the lease before moving in.`
4. extension — `We would like to discuss the extension of the lease.`
5. pay a fee — `You have to pay the fee for early termination of the lease.`
6. maintenance — `Who is responsible for the maintenance?`
7. run a program — `Click this icon to run a program.`
8. back up data — `Don't forget to back up the data before the update.`
9. back up — `You should back up the files regularly.`
10. rental application — `Fill out a rental application before the viewing.`
11. scooting — `The vet noticed a dog scooting and recommended a check-up.`
12. unscramble — `He tried to unscramble mixed-up letters.` (голое множественное легально)
13. fitness level — `Your fitness level affects how you must train.` (замена модала легальна)
14. baggage claim — `After landing, proceed to the baggage claim to collect your luggage.`
15. window seat — `Would you like a window seat or aisle seat?` (эллипсис артикля допустим)

## C. УДАЛИТЬ: принимаемые варианты, кормящие грейдинг неверным (9)

1. bank account — `savings account` ⚠️ должен был уйти в 521f475 — сначала SQL-проверка, см. секцию E
2. bank account — `checking account` ⚠️ то же
3. boarding pass — `flight ticket` (билет ≠ посадочный талон)
4. locksmith — `locksmith for locks` (не английский)
5. jab — `straight hit` (не боксёрский термин)
6. sparring session — `practice sparring` (не употребляется)
7. sparring session — `training sparring` (не английский)
8. healthy lifestyle — `fit lifestyle` (не употребляется)
9. pencil — `colored pencil` (вид, а не синоним; graphite pencil оставить)

## D. ПРАВКИ ДАННЫХ (2)

1. colleague («First Day at a New Company») — перевод термина: `колледка` → `коллега`. Тот самый
   нонворд из первой находки станка — жив в этой коллекции. Проверить ВСЕ вхождения термина
   colleague по базе.
2. corner («Boxing Practice Essentials») — correction `coach.` → `coach` (лишняя точка).

## E. ПРОВЕРКИ (расхождения с отчётами сессий)

1. Отчёт 521f475 заявил 6/6 удалённых вариантов, но `savings account` и `checking account` у
   bank account ПРИСУТСТВУЮТ в экспорте (снимок 12:27, после добивки). SQL-ом подтвердить их
   статус; если живы — понять, почему отчёт сказал 6/6, удалить, в отчёт причину.
2. Дыра валидатора: расстяжки `'s = is` не ловятся equality-проверкой (пропущены A.4, A.5 —
   How is / Here is). Чинить безопасно так: equality пробует ОБА раскрытия ('s→is и 's→has) и
   бракует при совпадении любого с эталоном. Только для equality, грейдинг не трогать. Тест на
   живых A.4/A.5.
3. В промпт станка (следующая версия пака, НЕ в этом наряде): запрет дистракторов переносом
   частицы фразового глагола (try on/try out/knock out) и расстяжкой контракций — класс A этой
   вычитки почти весь из них.

## F. УКРАИНСКИЙ — НЕ В ЭТОТ НАРЯД, отдельная сессия «язык переводов»

Фразовые глаголы (give up, look after, look forward to, take off, put off, turn down, turn up,
come across, break down, on the same page) показывают украинские переводы терминов
(«відкладати», «відхилити»), примеров и заметок вариантов («Синонім до…») — это lang=uk-строки,
выбранные ридерами без фильтра языка (хвост №2), плюс 🇺🇦-находки по ним. Данные скорее всего
легальны для uk-пользователя — чинить надо ВЫБОР строки, не данные. Блокер качества, но не
блокер включения pick_correct (режим показывает те же переводы, что и остальные тренажёры, —
хуже не становится).

## Итог по числам

Удалить дистракторов: 32 (A:17 + B:15). Удалить вариантов: 9 (с проверкой E.1 по двум). Правок
данных: 2. Проверок: 2 + 1 заметка в будущий пак. Класс «верное предложение» в новом выпуске:
17+15 из 581 ≈ 5.5% — промпт v2 держит планку store5-ре-топапа хуже на разнообразном контенте, но
истребимая часть (контракции, частицы) уходит в валидатор/пак детерминированно.
