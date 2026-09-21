<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| LANGUAGE PACK · ro — the skeleton, no rules yet
|--------------------------------------------------------------------------
|
| docs/plan-v2.md §4 (наряд GEN-2b). Romanian can be a learner's own language, and nobody has written what the
| validator should read of it: every key is null. A lesson for a Romanian learner is checked by the rules that
| need no language (counts, shapes, frames and their fillers, keys, the English target's own rules) and every
| rule that needs this pack counts `lang.pack_missing` instead of running — never a finding, never another
| language's words in place of Romanian ones.
|
| What to write here and how to know it is right — ROADMAP «Пакеты языков ученика uk и ro» (TODO-карточка GEN-2b).
*/
return [
    'script' => null,
    'script_letters' => null,
    'sentence_ends' => null,
    'abbreviations' => null,
    'question_word_order' => null,
    'function_words' => null,
    'unstressed_words' => null,
    'number_words' => null,
    'word_forms' => null,
    'number_pattern' => null,
    'time_pattern' => null,
    'amount_pattern' => null,
    'amount_prefix' => null,
    'everyday_words' => null,
    'ordinary_heads' => null,
    'closers' => null,
    'saying_verbs' => null,
    'alternative_words' => null,
    'second_question_pattern' => null,
    'articles' => null,
    'seam_repeatable_words' => null,
    'article_sound' => null,
    'clause' => null,
    'unresolved_pronouns' => null,
    'gendered_past_pattern' => null,
    'agreement' => null,

    // «НЕ ПОНЯЛ» НА ЯЗЫКЕ ЦЕЛИ (наряд CONV-2, п. 4а) — what a rescue move of the talk says in the learner's own bubble
    // (кадр 37-7, en «Sorry?»).
    'rescue_line' => 'Poftim?',
];
