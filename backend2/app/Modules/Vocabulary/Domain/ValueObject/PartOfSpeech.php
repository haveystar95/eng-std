<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Domain\ValueObject;

enum PartOfSpeech: string
{
    case Noun = 'noun';
    case Verb = 'verb';
    case Adjective = 'adjective';
    case Adverb = 'adverb';
    case Pronoun = 'pronoun';
    case Preposition = 'preposition';
    case Conjunction = 'conjunction';
    case Interjection = 'interjection';
    case Phrase = 'phrase';
    case Other = 'other';
}
