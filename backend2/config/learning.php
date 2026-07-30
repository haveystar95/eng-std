<?php

declare(strict_types=1);

return [
    // Exercise modes switched on. Phase 1 ships recognition + assembly + typing; listening and
    // cloze arrive with TTS and good examples. The ExerciseSelector rotates/degrades only within
    // this set, so it never hands out a mode that is not yet built.
    'enabled_modes' => ['multiple_choice', 'word_bank', 'typing'],
];
