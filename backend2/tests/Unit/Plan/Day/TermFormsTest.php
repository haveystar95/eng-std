<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Check\Language\LanguageWords;
use App\Modules\Plan\Domain\Check\Skeleton\TermForms;

// Canon (наряд GEN-4, «по форме, лемма против спрягаемой формы»): a word of the day is in a text when the text says it in a
// form of it. The gate run failed a day of ro→fr on «vouloir» said as «Je veux» and one on «ambiance» said as «l’ambiance»:
// an irregular form the target's pack lists (`lemma_forms`) and a word an apostrophe joins are the word. Catches the check
// that reads only a common beginning of letters — and one that finds a word in a text that says another.
it('finds a word of the day in the form a text says it', function (string $target, string $term, string $text, bool $found) {
    expect(TermForms::in($term, $text, new LanguageWords(lessonPacks()->for($target))))->toBe($found);
})->with([
    'fr: an irregular present' => ['fr', 'vouloir', 'Je veux ce poste pour ___', true],
    'fr: an impersonal verb' => ['fr', 'falloir', 'Est-ce qu’il faut parler anglais pour ce poste ?', true],
    'fr: after an elided article' => ['fr', 'ambiance', 'Comment est l’ambiance dans votre équipe ?', true],
    'it: after an elided article' => ['it', 'ascensore', "C'è l'ascensore nel palazzo?", true],
    'ro: a verb without its «a»' => ['ro', 'a putea', 'Pot începe de luni.', true],
    'ro: an irregular «a durea»' => ['ro', 'a durea', 'Mă doare spatele.', true],
    'en: a short verb and its -ing' => ['en', 'go to', 'I am going to the bank.', true],
    'en: a past the letters do not reach' => ['en', 'pay', 'I paid by card.', true],
    'es: a stem that changes' => ['es', 'doler', 'Me duele la espalda.', true],
    'de: a modal verb' => ['de', 'können', 'Kann ich mit Karte zahlen?', true],
    'pl: a verb of another stem' => ['pl', 'móc', 'Czy mogę zapłacić kartą?', true],
    'de: a vowel that changes (GEN-4b)' => ['de', 'gelten', 'Ich verstehe, dass die Hausordnung gilt.', true],
    'de: a separable participle (GEN-4b)' => ['de', 'anmelden', 'Haustiere sind erlaubt, wenn sie angemeldet sind.', true],
    'it: an impersonal verb (GEN-4b)' => ['it', 'bisognare', 'Bisogna portare un documento?', true],
    'fr: another verb' => ['fr', 'vouloir', 'Je peux venir demain.', false],
    'ro: another verb' => ['ro', 'a putea', 'Vreau să încep luni.', false],
    'en: another word of the same meaning' => ['en', 'return', 'Please come back in three days.', false],
]);
