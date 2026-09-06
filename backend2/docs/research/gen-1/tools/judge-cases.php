<?php

declare(strict_types=1);

/**
 * GEN-1, Ч.4.1 / Ч.6 — пары для пробника судьи. `expect` — вердикт ПО КАНОНУ Ч.2 (README.md), не
 * по промпту. Половина пар взята из живых планов «было» (`before/`), половина написана руками как
 * заведомый брак одного вида. Переводы стоят у всех: v0.2 судит и их; v0.1 их не читал.
 */
return [
    // ── заведомо хорошие (expect = true) ────────────────────────────────────────────────────
    ['id' => 'good-answer', 'kind' => 'answer', 'A' => 'How many years of experience do you have with PHP?', 'A_translation' => 'Сколько лет опыта у вас с PHP?', 'B' => 'About two years.', 'B_translation' => 'Около двух лет.', 'expect' => true, 'why' => 'прямой короткий ответ'],
    ['id' => 'good-ask', 'kind' => 'ask', 'A' => 'Is there anything you would like to ask?', 'A_translation' => 'Вы хотите что-нибудь спросить?', 'B' => 'What tools does the team use?', 'B_translation' => 'Какие инструменты использует команда?', 'expect' => true, 'why' => 'вопрос на приглашение'],
    ['id' => 'good-state', 'kind' => 'answer', 'A' => 'Please hold for a moment.', 'A_translation' => 'Пожалуйста, подождите минутку.', 'B' => 'Sure, I will hold.', 'B_translation' => 'Конечно, я подожду.', 'expect' => true, 'why' => 'реакция на утверждение'],

    // ── ответ не на вопрос (expect = false; v0.1 обязан ловить) ─────────────────────────────
    ['id' => 'wrong-q', 'kind' => 'answer', 'A' => 'What kinds of projects did you work on?', 'A_translation' => 'Над какими проектами вы работали?', 'B' => 'Later, I moved into an in-house team.', 'B_translation' => 'Позже я перешёл в штатную команду.', 'expect' => false, 'why' => 'живой брак 05.09 — ответ на другой вопрос'],
    ['id' => 'wrong-q-2', 'kind' => 'answer', 'A' => 'Where exactly does it hurt?', 'A_translation' => 'Где именно болит?', 'B' => 'It started two days ago.', 'B_translation' => 'Началось два дня назад.', 'expect' => false, 'why' => 'ответ на «когда», спросили «где»'],
    ['id' => 'nonsense', 'kind' => 'answer', 'A' => 'What seems to be the problem?', 'A_translation' => 'Что случилось?', 'B' => 'The problem is my steak.', 'B_translation' => 'Проблема в том, что мой стейк.', 'expect' => false, 'why' => 'живой (restaurant, день 2): фраза бессмысленна'],

    // ── переспрос в полке ответов (expect = false по Y2; v0.1 разрешал «clarify») ───────────
    ['id' => 'clarify-1', 'kind' => 'answer', 'A' => 'What tools do you use in development?', 'A_translation' => 'Какие инструменты вы используете в разработке?', 'B' => 'Sorry, what does tools mean?', 'B_translation' => 'Извините, что значит tools?', 'expect' => false, 'why' => 'живой (interview, день 2): переспрос вместо ответа'],
    ['id' => 'clarify-2', 'kind' => 'answer', 'A' => 'Do you have experience with our users?', 'A_translation' => 'У вас есть опыт работы с нашими пользователями?', 'B' => 'Sorry, do you mean the users?', 'B_translation' => 'Извините, вы имеете в виду пользователей?', 'expect' => false, 'why' => 'переспрос из наряда'],
    ['id' => 'repeat', 'kind' => 'answer', 'A' => 'Which PHP frameworks have you used?', 'A_translation' => 'Какими PHP-фреймворками вы пользовались?', 'B' => 'Could you say that again, please?', 'B_translation' => 'Не могли бы вы повторить?', 'expect' => false, 'why' => 'починка, а не ответ — место у спасателей'],

    // ── канцелярит / не по уровню (expect = false по Y3; v0.1: «register does not matter») ──
    ['id' => 'formal-1', 'kind' => 'answer', 'A' => 'What was your role in your last job?', 'A_translation' => 'Какова была ваша роль на последней работе?', 'B' => 'I was responsible for planning and delivery.', 'B_translation' => 'Я отвечал за планирование и поставку.', 'expect' => false, 'why' => 'канцелярит из наряда'],
    ['id' => 'formal-2', 'kind' => 'answer', 'A' => 'What do you do when you do not know the answer?', 'A_translation' => 'Что вы делаете, когда не знаете ответа?', 'B' => 'I undertake a comprehensive investigation of the relevant documentation.', 'B_translation' => 'Я предпринимаю всестороннее исследование соответствующей документации.', 'expect' => false, 'why' => 'не basic: 10 слов, книжная лексика'],
    ['id' => 'too-long', 'kind' => 'answer', 'A' => 'Have you worked with databases?', 'A_translation' => 'Вы работали с базами данных?', 'B' => 'Yes, I have worked with several relational databases in production for many years now.', 'B_translation' => 'Да, я много лет работал с несколькими реляционными базами данных в проде.', 'expect' => false, 'why' => '15 слов на уровне basic'],

    // ── ask-пара: role не приглашение / you не вопрос (expect = false по H2) ────────────────
    ['id' => 'ask-not-q', 'kind' => 'ask', 'A' => 'Can I help with anything else?', 'A_translation' => 'Могу ещё чем-то помочь?', 'B' => 'Please connect me to card services.', 'B_translation' => 'Соедините меня, пожалуйста, с отделом карт.', 'expect' => false, 'why' => 'живой (bank, день 1): you — просьба, не вопрос'],
    ['id' => 'ask-yes', 'kind' => 'ask', 'A' => 'Can I check that for you?', 'A_translation' => 'Мне проверить это для вас?', 'B' => 'Yes, please. It is the wrong dish.', 'B_translation' => 'Да, пожалуйста. Это не то блюдо.', 'expect' => false, 'why' => 'живой (restaurant, день 2): role — вопрос, you — не вопрос'],

    // ── перевод с добавкой / неверный (expect = false по T1; v0.1 перевод не видел) ─────────
    ['id' => 'tr-added', 'kind' => 'answer', 'A' => 'What kind of projects have you worked on?', 'A_translation' => 'Над какими проектами вы работали?', 'B' => 'I worked on a reporting platform.', 'B_translation' => 'Я работал над платформой кредитной отчётности.', 'expect' => false, 'why' => 'добавка «кредитной» из наряда'],
    ['id' => 'tr-wrong', 'kind' => 'answer', 'A' => 'What seems to be the problem?', 'A_translation' => 'Что кажется проблемой?', 'B' => 'Also, the steak is cold.', 'B_translation' => 'И ещё, стейк простыл.', 'expect' => false, 'why' => 'перевод role буквальный, перевод you неверный'],
];
