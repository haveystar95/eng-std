<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/** How a session presents cards. Affects grading input, never scheduling — a grade is a grade. */
enum StudyMode: string
{
    case Flashcard = 'flashcard';
    case Typing = 'typing';
    case MultipleChoice = 'multiple_choice';
    case Listening = 'listening';
}
