<?php

declare(strict_types=1);

return [
    // Exercise modes switched on, IN ROTATION ORDER — the free-practice round-robin indexes into
    // this list, so reordering it re-deals every card (the mobile client mirrors both the set and
    // the order, pinned by tests/Fixtures/practice-mode-contract.json).
    //
    // Recognition + assembly + typing, plus listening (hear it, type it, graded like typing), cloze
    // (fill the blank in the term's own example) and scramble (assemble the example sentence from
    // word chips). Which of them a given term can actually be drilled in is a separate question,
    // answered in one place by TermPlayability. The ExerciseSelector rotates/degrades only within
    // this set, so it never hands out a mode that is not built.
    //
    // This is the ONE runtime source of the mode set; it becomes a column of the learning policy
    // later, and nothing else should grow a second list.
    // `intro` is deliberately absent: a new trainer ships switched OFF globally and is turned on
    // from the admin panel (CLAUDE.md release rule). The panel lists it as soon as the enum knows
    // it. Until it is switched on, a brand-new pair simply starts one rung higher, at
    // recognition — exactly what happened before the ladder existed.
    'enabled_modes' => ['multiple_choice', 'word_bank', 'typing', 'listening', 'cloze', 'scramble'],

    // HOW LONG A WRONG ANSWER MAY BE, relative to the target. The shape rule (DistractorFamily)
    // says a word stands beside a word; inside one shape, length still gives the answer away —
    // `key` offered `accommodation`, `neighbourhood`, `responsibility` is answered by picking the
    // short one without reading it. It got worse the day the option pool became the whole catalogue.
    //
    // Two measures because a phrase is not a long word: single lexical items are compared by
    // CHARACTERS, a spoken line by WORDS. Configuration and not constants — this is a product
    // judgement about how hard a card should be, and the first time one of them is wrong it should
    // move without a deploy. The rule itself lives in Shared\Domain\Service\DistractorLength and
    // is read by everyone who builds or predicts a choice card.
    'distractor_length' => [
        'char_tolerance' => 0.5,   // word / chunk / «no kind» — ±50 % of the target's characters
        'word_tolerance' => 0.4,   // line — ±40 % of the target's word count
    ],

    // The ADMISSION MATRIX — which rung of the acquisition ladder opens which trainer — is NOT
    // here. It is data, in `learning_mode_settings` beside the toggle above, with the same
    // global-plus-per-user-override mechanism: what a mode asks of the learner is a product
    // judgement that should move with one admin action, not with a deploy. The shipped values live
    // in `ModeAdmission::shipped()`, which seeds the table and is the fallback if it is emptied.
];
