<!-- snapshot: 2026-08-13T15:48:26+00:00 · head: f39147976d81 -->
# Выгрузка станка на вычитку

Снимок: **2026-08-13T15:48:26+00:00** · HEAD: `f39147976d81` · версия генератора: `enrich-v1`.

Снимок старше правок в базе — выгрузку надо снять заново: то, что здесь написано, было
верно на момент снимка и с тех пор могло быть починено.

Термины без вариантов, дистракторов и флагов в выгрузку не попадают — это рабочий список,
а не дамп базы. Колонка «флаги» — то, что требует решения человека.

## Ordering Drinks in a Coffee Shop

### I would like a cappuccino, please.

- **перевод (промпт):** Я бы хотел капучино, пожалуйста.
- **эталон-пример:** I would like a cappuccino, please.
- **дистракторы:**
    - `I would like cappuccino, please.` — article: **cappuccino** → `a cappuccino`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «I would like a cappuccino» — ни эталон, ни варианты этого не покрывают; переформулировать.

### to go

- **перевод (промпт):** с собой
- **эталон-пример:** I'd like an espresso to go.
- **дистракторы:**
    - `I’d like an espresso to gone.` — tense: **gone** → `go`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «to take» — ни эталон, ни варианты этого не покрывают; переформулировать.

### Could I have a menu, please?

- **перевод (промпт):** Можно мне меню, пожалуйста?
- **эталон-пример:** Could I have a menu, please?
- **дистракторы:**
    - `Could I have the menu, please?` — article: **the** → `a`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «Can I have a menu, please?» — ни эталон, ни варианты этого не покрывают; переформулировать.

### How much is a latte?

- **перевод (промпт):** Сколько стоит латте?
- **эталон-пример:** How much is a latte?
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «How much does a latte cost?» — ни эталон, ни варианты этого не покрывают; переформулировать.

### table for two

- **перевод (промпт):** столик на двоих
- **эталон-пример:** Do you have a table for two?
- **принимаемые варианты:**
    - `table for 2` — _Это часто используется._
- **дистракторы:**
    - `Do you have table for two?` — article: **table for two** → `a table for two`

### I'd like it with soy milk.

- **перевод (промпт):** Я бы хотел это с соевым молоком.
- **эталон-пример:** I'd like my coffee with soy milk.
- **дистракторы:**
    - `I want my coffee with soy milk.` — tense: **want** → `would like`
    - `I'd like my coffee for soy milk.` — preposition: **for soy milk** → `with soy milk`
    - `I'd like the coffee with soy milk.` — article: **the coffee** → `my coffee`

### regular or large

- **перевод (промпт):** обычный или большой
- **эталон-пример:** Would you like a regular or large coffee?
- **дистракторы:**
    - `Would you like the regular or large coffee?` — article: **the** → `a`

### What do you recommend?

- **перевод (промпт):** Что вы посоветуете?
- **эталон-пример:** What do you recommend?
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «What will you advise?» — ни эталон, ни варианты этого не покрывают; переформулировать.

### Can I pay by card?

- **перевод (промпт):** Могу я оплатить картой?
- **эталон-пример:** Can I pay by card, or is it cash only?
- **дистракторы:**
    - `Can I pay by card, at is it cash only?` — preposition: **at** → `or`
    - `Can I pay by card, or are it cash only?` — tense: **are** → `is`

### self-service

- **перевод (промпт):** самообслуживание
- **эталон-пример:** This coffee shop is self-service.
- **дистракторы:**
    - `This coffee shop are self-service.` — tense: **are** → `is`

### Take a seat and I'll bring it over.

- **перевод (промпт):** Садитесь, я принесу.
- **эталон-пример:** Take a seat and I'll bring it over in a moment.
- **дистракторы:**
    - `Take a seat and I bring it over in a moment.` — tense: **I bring** → `I'll bring`
    - `Take a seat and I'll bring over it in a moment.` — word_order: **bring over it** → `bring it over`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «Sit down, I will bring it.» — ни эталон, ни варианты этого не покрывают; переформулировать.

