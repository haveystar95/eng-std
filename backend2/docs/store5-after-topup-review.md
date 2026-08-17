# Вычитка enrich-v1-store5-after-topup (архитектор, 12.08.2026)

> **Поправки при применении (13.08.2026).** Документ применён как есть, кроме двух секций,
> отменённых по сверке с базой:
>
> - **E (эмфазис) — отменена.** Три строки чистые. Выгрузка снята в 16:10, свип `46f9076` отработал
>   в 17:32 (`terms.updated_at` у всех трёх терминов = 14:32:37 UTC). Свип не промахнулся — устарел
>   документ; отсюда же секция «шапка выгрузки» в следующей итерации.
> - **F.1 (ладити з) — отменена.** Уже применено в `00b2609`: перевод читается «ладить с», `lang=ru`.
>   Хвост №2 (`TermContentReader::byIds()`) не подтверждается: это был украинский текст в русском
>   поле, а не uk-строка, выбранная без фильтра языка.
>
> Ещё три расхождения, решённые при применении: **C.2** переписана шире (предложенная правка не
> замыкала круг), **D** применена ровно как названа (вторая пара дублей у того же термина осталась
> ретро-аудиту), **F.2** расширена свипом до 3 дистракторов и 2 вариантов. Число флагов в базе — 40,
> а не 41: термин `notice period` лежит в двух коллекциях, а выгрузка печатает термин по разу на
> коллекцию, так что одна находка сосчитана дважды.

Объём: 100 терминов, 289 дистракторов, 85 принимаемых вариантов, 41 флаг. Применять через CC
атомарным коммитом `content: apply store5 after-topup review` (образец — 00b2609). Каждое
удаление/правка — по точной строке.

## A. УДАЛИТЬ: дистрактор — корректное английское предложение (двойной верный ответ)

Класс, решающий вердикт по mini. 14 строк:

1. debit card — `I prefer to use my debit card for everyday purchases.` (prefer to use = prefer using, оба верны)
2. ATM — `I withdrew cash at the ATM last night.` (at the ATM — идиоматично)
3. direct debit — `I pay my utility bills through direct debit.` (through — легальный предлог)
4. lease agreement — `Please read the lease agreement carefully before signing it.` (добавленное it — норма)
5. move-in date — `What is your preferred move-in date?` (расстяжка контракции ≠ ошибка)
6. run a temperature — `He has been running a temperature since last night.` (= эталон без контракции; correction — no-op «has been → has been»)
7. Could you tell me more about the team? — `...about the team with which I would be working?` (формальный регистр, безупречно)
8. medicine — `Did the doctor give you any medicines?` (any + plural легально; correction «medicines any» — галиматья)
9. menu — `Could you bring me a menu, please?` (a menu — норма)
10. Can I have some water, please? — `Can I have water, please?` (без some — норма)
11. minimize fees — `I want to minimize the fees on my account.`
12. transfer money — `I need to transfer the money to my sister's account today.`
13. utilities included — `Are the utilities included in the rent?`
14. financial advisor — `I scheduled a meeting with my financial advisors to discuss investments.` (plural легален; заодно снимается mislabel word_order)

## B. УДАЛИТЬ: замена определителя на грамматически легальный, перевод выбор не якорит

Правило: артиклевый дистрактор оставляем, только если результат неграмматичен (голое исчисляемое:
«open account», «a application») ИЛИ русский перевод однозначно якорит выбор. Эти 15 — легальные
предложения, решаемые только сверкой с эталоном, которого учащийся не видит:

1. I'd like to open an account — `...open an account in your bank.` (in/with оба легальны)
2. credit card — `She got a new credit card for a higher limit.` (легальное чтение цели)
3. standing order — `I set up the standing order to pay my rent each month.` (вторую, `a the standing order`, оставить — она сломана)
4. savings account — `I opened the savings account for future expenses.`
5. savings account — `I opened a savings account for the future expenses.`
6. online banking — `Do you offer the online banking services?`
7. Could you explain the fees? — `Could you explain fees for maintaining this account?` (нулевой артикль легален)
8. monthly statement — `I review my monthly statement for the discrepancies.`
9. shared accommodation — `I'm looking for the shared accommodation in the city center.`
10. tenant — `The tenant is responsible for the minor repairs.`
11. sublet — `Do you have the permission to sublet the apartment?` (`a permission` оставить — сломана)
12. What are your strengths and weaknesses? — `...asked me about the strengths and weaknesses.`
13. long-term goals — `In the interview, I discussed the long-term goals in the IT industry.`
14. get along with — `I get along with the team well, making collaboration effective.`
15. tip — `It's customary to leave the tip in restaurants here.`
16. comfortable with — `I'm comfortable with the fast-paced environments and enjoy challenges.`

(п.16 — да, их 16, посчитан после нумерации; удалить все.)

## C. ИСПРАВИТЬ correction (галиматья уедет в option_feedback)

Дистрактор оставить, correction переписать:

1. online banking — `Do you offer online banking at services?`: сейчас «at → services»; должно быть: span `at services` → `services` (убрать at)
2. What are your symptoms? — `What today are your symptoms?`: сейчас «today → your symptoms today?»; должно: span `What today are` → `What are`
3. Can I have some water — `Can I have some water for, please?`: сейчас «for → please»; должно: span `water for` → `water`
4. What are your strengths... — `...weaknesses me.`: сейчас «me. → me about»; должно: span `asked about my strengths and weaknesses me` → `asked me about my strengths and weaknesses`
5. Please take a seat — `...will see on you shortly.`: сейчас «on you → you shortly»; должно: span `see on you` → `see you`
6. Could you help me with this form? — `Could you help me with form?`: correction «with the form» противоречит эталону; должно: `with this form`
7. fill out — `Please fill out a application to proceed.`: correction «the application» противоречит эталону; должно: `this application`
8. What are the main responsibilities... — `What is the main responsibilities...`: correction меняет существительное («the main responsibility»); должно чинить глагол: span `What is` → `What are`

## D. ДУБЛИКАТЫ (различие только контракция/юникод-апостроф) — удалить по одной

1. I'll have the chicken salad — `I will have the chicken salads, please.` удалить (дубль `I'll have the chicken salads, please.` после нормализации)
2. Can I get this to go? — `I’d like the pasta for go, please.` (типографский апостроф) удалить, ASCII-версию оставить

## E. ЭМФАЗИС ПЕРЕЖИЛ ЗАЧИСТКУ 46f9076 — 3 строки, зачистить + выяснить почему

1. security deposit — `You'll need to pay a security deposit **for** moving in.`
2. furnished — `Is **an** apartment furnished or unfurnished?`
3. When can you start? — `They asked me when I could start if **offer** the position.` — звёздочки в предложении, В SPAN И В CORRECTION (`**offer**` → `**offered**`)

Отдельно: понять, почему свип 46f9076 их не увидел (счёт «5 из 346» был неполным? свип не покрывал
span/correction? строки из другого поля?). Это регрессия зачистки.

## F. ЯЗЫК

1. get along with — перевод термина: `ладити з` — украинский БЕЗ уникальных букв (charset-слепой, живое подтверждение ограничения детектора). Править руками: `ладить с`. Обязательно проверить lang исходной строки: если это легальная uk-строка, выбранная ридером/экспортёром без фильтра языка — это снова TermContentReader::byIds() (хвост №2), и приоритет хвоста поднимается.
2. serve — дистрактор `The waiter will serve your meal в скором времени.` — русский внутри английского предложения. Удалить строку. Заодно: прогнать isWrongScript-свип по en-полям дистракторов/эталонов — существующие данные писались до барьера.
3. I'm confident in my problem-solving skills — заметка варианта содержит JSON-мусор: `Синонимы."},{` — вычистить заметку (вариант оставить).

## G. ПРИНИМАЕМЫЕ ВАРИАНТЫ — удалить (кормят грейдинг, примут неверное/чужое)

1. monthly statement — `monthly invoice` (invoice = счёт на оплату, не выписка)
2. pay the rent — `pay for the rent` (нестандарт; принимать как верный ответ нельзя)
3. take medicine — `take your drugs` (наркотическая коннотация, в учебный контент не годится)

Спорные, но оставить: `lease out` (sublet), `co-housing`, `coughing`, `first course`.

## H. ФЛАГИ — погасить скопом, один вывод

Из 41 флага полезных ноль: «переформулировать» (31 шт.) — тривиа обратного перевода; 🌐-флаги
ложные («Фраза написана на английском» на английском термине; совет заменить «среда» на «окружающая
среда» — активно вреден); ✍️-флаги — шум («Нет ошибочно написанных слов», галлюцинация «слоник
instead of столик», «куриный салат (should be куриный салат)»). Все погасить. Вывод для станка:
kind «переформулировать» демотировать до opt-in или убрать — s/n ниже порога полезности, создаёт
ack-усталость.

## I. В СЛЕДУЮЩУЮ ИТЕРАЦИЮ (валидатор v2 + промпт v7 + сборщик) — не данными, кодом

1. Валидатор: normalized-equality. Дистрактор, равный эталону после LexicalNormalizer (контракции, апострофы, регистр), — брак. Убивает класс «расстяжка контракции» (A.5, A.6) детерминированно и бесплатно.
2. Валидатор: normalized-dedup внутри термина. Убивает секцию D.
3. Валидатор: no-op correction (normalized span == normalized correction) — брак (A.6).
4. Валидатор: круговая проверка correction. replace(span → correction) в дистракторе, нормализовать, сравнить с эталоном. Не сошлось — correction кривой. Убивает всю секцию C навсегда.
5. Промпт v7: правило определителей. Дистрактор с заменой артикля/определителя легален, только если результат неграмматичен в любом контексте; замена the↔my/a с сохранением грамматичности — запрещена. (Секция B — 16/289 = 5.5% выпуска.)
6. Сборщик (сервер+клиент): разные span'ы на карточке. Не подавать два дистрактора с одинаковым span (credit card for/at, statement of/at, appointment in/on, wine saw/seen — ~8 карточек). Детерминированно, данные не трогаем, паттерн «одно место дважды» гаснет на выдаче.
7. Таксономия в выгрузке уже живёт (article 102 / tense 80 / preposition 61 / word_order 31 / modal_to 11 / false_friend 4), но ярлыки шумные: «tense» вешается на согласование и число, «word_order» — на множественное. При реализации error_type-идеи ярлыки перегенерировать, текущим не верить.

## Итог по числам

Удалить дистракторов: 14 (A) + 16 (B) + 1 (F.2) + 2 (D) = 33 из 289 (11.4%). Исправить correction: 8.
Удалить вариантов: 3. Править переводов: 1 (ладити з). Зачистить эмфазис: 3. JSON-мусор: 1.
Погасить флагов: 41.
