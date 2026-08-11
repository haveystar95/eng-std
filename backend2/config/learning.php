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
    'enabled_modes' => ['multiple_choice', 'word_bank', 'typing', 'listening', 'cloze', 'scramble'],
];
