<?php

declare(strict_types=1);

return [
    // Exercise modes switched on. Recognition + assembly + typing, plus listening (hear it, type it,
    // graded like typing) and cloze (fill the blank in the term's own example — only offered when the
    // example contains the answer). The ExerciseSelector rotates/degrades only within this set, so it
    // never hands out a mode that is not built.
    'enabled_modes' => ['multiple_choice', 'word_bank', 'typing', 'listening', 'cloze'],
];
