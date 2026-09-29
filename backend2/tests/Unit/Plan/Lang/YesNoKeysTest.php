<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Check\Language\LanguagePack;
use App\Modules\Plan\Domain\Check\Language\LanguagePacks;
use App\Modules\Plan\Domain\Check\Language\LanguageWords;
use App\Modules\Plan\Domain\Check\Skeleton\AskedFor;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonCheck;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonContext;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;
use App\Modules\Shared\Domain\Service\LanguageRoles;

/*
 * YES OR NO, READ OFF THE TARGET'S PACK (наряд GEN-4c §2): `yes_no` — the words a reply to a yes-or-no question opens with,
 * «yes» first, «no» second; `question_words` — the words and phrases of a question that asks for a fact. Both keys of the seven
 * targets' packs, both read by `partner.yes_no_missing` and `partner.yes_no_extra` the day they are written, and what a
 * question of the learner's asks for ({@see AskedFor}) as the recorded ask frames of GEN-4 and GEN-4b asked it.
 */

/** Every target's pack as the config writes it — read by path, as the deployment reads it. */
function ynDeployed(string $code): array
{
    return require dirname(__DIR__, 4)."/config/lesson/lang/{$code}.php";
}

// Наряд GEN-4c §2: «ключи обязательны для целевых пакетов; тест пакетов — ключи есть, списки без дублей». Catches a target
// written without one of them, a list with a word twice — as the rule reads it, folded and lower-cased —, a blank entry, and a
// `yes_no` without its yes and its no.
it('writes yes_no and question_words for every target, each word once as the rule reads it', function (string $code) {
    $pack = ynDeployed($code);

    foreach (['yes_no', 'question_words'] as $key) {
        expect($pack)->toHaveKey($key)
            ->and($pack[$key])->toBeArray()->not->toBeEmpty();
        $read = array_map(LanguagePack::normal(...), $pack[$key]);
        expect(array_filter($read, static fn (string $w): bool => trim($w) === ''))->toBe([], "{$code}.{$key}: a blank entry")
            ->and(array_values(array_unique($read)))->toBe($read, "{$code}.{$key}: a word twice");
    }
    expect(count($pack['yes_no']))->toBeGreaterThanOrEqual(2);
})->with(LanguageRoles::planTargets());

// Наряд GEN-4c §2: «оба ключа читаются кодом с первого дня (не мёртвые)». The canon's a6 without its «Da.» — a finding with
// the deployed pack; with the target's pack missing either key, the rules do not run at all. Catches a key nobody reads, and a
// rule that runs on a pack that lacks what it needs (and would read every question as a yes or no).
it('reads both keys: without either the yes-or-no rules do not run', function (?string $without) {
    $ro = ynDeployed('ro');
    if ($without !== null) {
        unset($ro[$without]);
    }
    $packs = new LanguagePacks(['ru' => ynDeployed('ru'), 'ro' => $ro]);
    $context = new SkeletonContext(dayCanonSurvival(), 8, 12, $packs->for('ru'), $packs->for('ro'), VoiceGender::Male);
    $skeleton = dayCanonSkeleton(static function (array $raw): array {
        $raw['partner_lines'][5]['text_target'] = 'Lucrați în ture, dimineața sau seara.';
        $raw['partner_lines'][6]['text_target'] = 'Da. '.$raw['partner_lines'][6]['text_target'];

        return $raw;
    });
    $found = array_map(static fn ($v): string => "{$v->code}@{$v->address}", (new SkeletonCheck)->run($skeleton, $context));

    $without === null
        ? expect($found)->toContain('partner.yes_no_missing@a6')->toContain('partner.yes_no_extra@a7')
        : expect(array_filter($found, static fn (string $f): bool => str_starts_with($f, 'partner.yes_no')))->toBe([]);
})->with([
    'the deployed pack' => [null],
    'without yes_no' => ['yes_no'],
    'without question_words' => ['question_words'],
]);

// Наряд GEN-4c §2: «каждый ask-каркас должен попасть в да/нет либо в вопросительный класс; спорные перечисли». The ask frames
// the gate runs of GEN-4 and GEN-4b wrote, as they wrote them (the report, §12), and the forms the lists were written for.
// Catches a question with its word in the middle or at the end read as a yes or no («Je commence à quelle heure ?»), «What's»
// not read as «what», «est-ce que» and «czy» read as asking for a fact, a tag «…, oder?» read as a choice — and «gross or
// net» read as a yes-or-no question, which six recorded replies («That amount is gross…») would have broken.
it('reads what a question of the learner\'s asks for', function (string $code, string $question, string $kind, ?string $word) {
    $asked = AskedFor::of($question, new LanguageWords(lessonPacks()->for($code)));

    expect([$asked->kind, $asked->word])->toBe([$kind, $word]);
})->with([
    'ro: a yes or no' => ['ro', 'Postul include ___?', AskedFor::YES_NO, null],
    'ro: «care»' => ['ro', 'Care este programul de lucru?', AskedFor::FACT, 'care'],
    'ro: «cât de»' => ['ro', 'Cât de lungă este perioada de probă?', AskedFor::FACT, 'cât de'],
    'en: «What\'s» holds «what»' => ['en', 'What\'s the salary range for ___?', AskedFor::FACT, 'what'],
    'en: «how often»' => ['en', 'How often is ___ paid?', AskedFor::FACT, 'how often'],
    'en: a tag question' => ['en', 'I go to ___ now, right?', AskedFor::YES_NO, null],
    'en: a choice' => ['en', 'Is ___ gross or net?', AskedFor::CHOICE, 'or'],
    'en: no mark, a yes or no (Luna, day 06)' => ['en', 'Does the package include ___', AskedFor::YES_NO, null],
    'fr: «est-ce que» asks yes or no' => ['fr', 'Est-ce que ___ compte pour ce poste ?', AskedFor::YES_NO, null],
    'fr: the question word last' => ['fr', 'Je commence à quelle heure ?', AskedFor::FACT, 'à quelle heure'],
    'fr: «qu\'est-ce que»' => ['fr', 'Qu’est-ce que je dois apporter ?', AskedFor::FACT, "qu'est-ce que"],
    'es: «¿Se puede…?»' => ['es', '¿Se puede reservar ___?', AskedFor::YES_NO, null],
    'es: «a qué hora»' => ['es', '¿A qué hora empieza ___?', AskedFor::FACT, 'a qué hora'],
    'it: «Dov\'è» holds «dov»' => ['it', 'Dov’è ___?', AskedFor::FACT, 'dov'],
    'it: a yes or no' => ['it', 'Può dipendere da ___?', AskedFor::YES_NO, null],
    'de: «wie viel»' => ['de', 'Wie viel kostet ___ im Monat?', AskedFor::FACT, 'wie viel'],
    'de: a tag «oder» at the end' => ['de', 'Das ist brutto, oder?', AskedFor::YES_NO, null],
    'de: a choice' => ['de', 'Zahle ich bar oder mit ___?', AskedFor::CHOICE, 'oder'],
    'pl: «czy» asks yes or no' => ['pl', 'Czy potrzebuję ___?', AskedFor::YES_NO, null],
    'pl: «o której»' => ['pl', 'O której zaczynam ___?', AskedFor::FACT, 'o której'],
    // Disputed (the report, §12): two verbs joined by «or» inside a yes-or-no question read as a choice — neither rule speaks.
    'pl: «jeść lub pić» — disputed' => ['pl', 'Czy mogę jeść lub pić ___?', AskedFor::CHOICE, 'lub'],
]);

// Наряд GEN-4c §2: «сравнение — по началу реплики после нормализации регистра и пунктуации». Catches a yes read past the
// reply's first word, marks and case in the way, and the Spanish «Si» of «if» read as the «Sí» of «yes».
it('reads the yes or the no a reply opens with, its first word only', function (string $code, string $reply, ?string $word) {
    expect((new LanguageWords(lessonPacks()->for($code)))->yesNoOpening($reply))->toBe($word);
})->with([
    'ro: «Da.»' => ['ro', 'Da. Programul este de luni până vineri.', 'da'],
    'ro: no yes, no no' => ['ro', 'Postul include lucru cu clienții.', null],
    'ro: «da» later' => ['ro', 'Programul este de luni, da.', null],
    'en: «No» of a sentence' => ['en', 'No test is needed now.', 'no'],
    'es: «¡Sí!»' => ['es', '¡Sí!, se admiten niños.', 'sí'],
    'es: «Si» is «if»' => ['es', 'Si le interesa, puede presentar la solicitud.', null],
    'de: «Doch»' => ['de', 'Doch, das geht.', 'doch'],
]);
