<?php

declare(strict_types=1);

/**
 * GEN-1, Ч.4.1 — пары для пробника судьи. `expect` — вердикт ПО КАНОНУ Ч.2 (README.md), не по
 * промпту v0.1. Половина пар взята из живых планов «было» (`before/`), половина написана руками
 * как заведомый брак одного вида.
 *
 * Поля `A_translation` / `B_translation` едут в судью только когда стоят: v0.1 переводов не видит,
 * и этими парами проверяется, что он их и не судит.
 */
return [
    // ── заведомо хорошие (expect = true) ────────────────────────────────────────────────────
    ['id' => 'good-answer', 'kind' => 'answer', 'A' => 'How many years of experience do you have with PHP?', 'B' => 'About two years.', 'expect' => true, 'why' => 'прямой короткий ответ'],
    ['id' => 'good-ask', 'kind' => 'ask', 'A' => 'Is there anything you would like to ask?', 'B' => 'What tools does the team use?', 'expect' => true, 'why' => 'вопрос на приглашение'],
    ['id' => 'good-state', 'kind' => 'answer', 'A' => 'Please hold for a moment.', 'B' => 'Sure, I will hold.', 'expect' => true, 'why' => 'реакция на утверждение'],

    // ── ответ не на вопрос (expect = false; v0.1 обязан ловить) ─────────────────────────────
    ['id' => 'wrong-q', 'kind' => 'answer', 'A' => 'What kinds of projects did you work on?', 'B' => 'Later, I moved into an in-house team.', 'expect' => false, 'why' => 'живой брак 05.09 — ответ на другой вопрос'],
    ['id' => 'wrong-q-2', 'kind' => 'answer', 'A' => 'Where exactly does it hurt?', 'B' => 'It started two days ago.', 'expect' => false, 'why' => 'ответ на «когда», спросили «где»'],
    ['id' => 'nonsense', 'kind' => 'answer', 'A' => 'What seems to be the problem?', 'B' => 'The problem is my steak.', 'expect' => false, 'why' => 'живой (restaurant, день 2): фраза бессмысленна'],

    // ── переспрос в полке ответов (expect = false по Y2; v0.1 разрешает «clarify») ──────────
    ['id' => 'clarify-1', 'kind' => 'answer', 'A' => 'What tools do you use in development?', 'B' => 'Sorry, what does tools mean?', 'expect' => false, 'why' => 'живой (interview, день 2): переспрос вместо ответа'],
    ['id' => 'clarify-2', 'kind' => 'answer', 'A' => 'Do you have experience with our users?', 'B' => 'Sorry, do you mean the users?', 'expect' => false, 'why' => 'переспрос из наряда'],
    ['id' => 'repeat', 'kind' => 'answer', 'A' => 'Which PHP frameworks have you used?', 'B' => 'Could you say that again, please?', 'expect' => false, 'why' => 'починка, а не ответ — место у спасателей'],

    // ── канцелярит / не по уровню (expect = false по Y3; v0.1: «register does not matter») ──
    ['id' => 'formal-1', 'kind' => 'answer', 'A' => 'What was your role in your last job?', 'B' => 'I was responsible for planning and delivery.', 'expect' => false, 'why' => 'канцелярит из наряда'],
    ['id' => 'formal-2', 'kind' => 'answer', 'A' => 'What do you do when you do not know the answer?', 'B' => 'I undertake a comprehensive investigation of the relevant documentation.', 'expect' => false, 'why' => 'не basic: 10 слов, книжная лексика'],
    ['id' => 'too-long', 'kind' => 'answer', 'A' => 'Have you worked with databases?', 'B' => 'Yes, I have worked with several relational databases in production for many years now.', 'expect' => false, 'why' => '15 слов на уровне basic'],

    // ── ask-пара: role не приглашение / you не вопрос (expect = false по H2) ────────────────
    ['id' => 'ask-not-q', 'kind' => 'ask', 'A' => 'Can I help with anything else?', 'B' => 'Please connect me to card services.', 'expect' => false, 'why' => 'живой (bank, день 1): you — просьба, не вопрос'],
    ['id' => 'ask-yes', 'kind' => 'ask', 'A' => 'Can I check that for you?', 'B' => 'Yes, please. It is the wrong dish.', 'expect' => false, 'why' => 'живой (restaurant, день 2): role — вопрос, you — не вопрос'],

    // ── перевод с добавкой / неверный (expect = false по T1; v0.1 перевод не видит) ─────────
    ['id' => 'tr-added', 'kind' => 'answer', 'A' => 'What kind of projects have you worked on?', 'A_translation' => 'Над какими проектами вы работали?', 'B' => 'I worked on a reporting platform.', 'B_translation' => 'Я работал над платформой кредитной отчётности.', 'expect' => false, 'why' => 'добавка «кредитной» из наряда'],
    ['id' => 'tr-wrong', 'kind' => 'answer', 'A' => 'What seems to be the problem?', 'A_translation' => 'Что кажется проблемой?', 'B' => 'Also, the steak is cold.', 'B_translation' => 'И ещё, стейк простыл.', 'expect' => false, 'why' => 'перевод role буквальный, перевод you неверный'],
];
