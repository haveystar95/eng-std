<?php

declare(strict_types=1);

use App\Modules\Vocabulary\Domain\Service\AddresseeIsomorphism;
use App\Modules\Vocabulary\Domain\ValueObject\AddresseeDirection;

/**
 * QA-17: the detector that finds translations which have stopped pointing at their own term.
 *
 * What it protects: a translation is the KEY, not prose. Drop «нам» from «Tell us about a challenge
 * you faced» and `Tell me…`, `Tell us…` and `Describe…` all answer the question equally — so an
 * honest answer is logged as a lapse.
 *
 * It is coarse and it never fixes anything, and the tests below say so in both directions: the cases
 * it must catch, and the cases where it stays quiet because Russian carries a person some other way.
 */
beforeEach(function () {
    $this->rule = new AddresseeIsomorphism();
});

it("catches the owner's own case: «нам» dropped from a `Tell us…`", function () {
    expect($this->rule->violations(
        'Tell us about a challenge you faced',
        'Расскажите о вызове, с которым вы столкнулись',
    ))->toBe(['us/me']);
});

it('clears the same term once the addressee is back', function () {
    expect($this->rule->violations(
        'Tell us about a challenge you faced',
        'Расскажите нам о вызове, с которым вы столкнулись',
    ))->toBe([]);
});

it("catches «your» smoothed into «своём»", function () {
    // «Расскажите о своём опыте» is a legitimate Russian sentence and a bad key: `Tell us about
    // your experience`, `Tell me about your experience` and `Describe your experience` all fit it.
    expect($this->rule->violations('Tell us about your experience', 'Расскажите о своём опыте'))
        ->toBe(['us/me', 'you/your']);
});

it('matches groups, never one flat list of pronouns', function () {
    // «вы» is present and «нам» is not. A flat list would clear this row; the whole point of the
    // grouping is that a term saying `us` is not rescued by a translation saying «вы».
    expect($this->rule->violations('Tell us what you think', 'Скажите, что вы думаете'))
        ->toBe(['us/me']);
});

it('stays quiet when the term addresses nobody', function () {
    expect($this->rule->violations('withdraw cash', 'снять наличные'))->toBe([]);
});

it('only reads STANDALONE words — a pronoun buried in another word is not one', function () {
    // `us` lives inside «campus» and «discuss»; «я» lives inside almost every Russian verb ending.
    // A substring search would flag or clear nearly everything, which is a detector that says
    // nothing at all.
    expect($this->rule->violations('discuss the campus bus route', 'обсудить маршрут автобуса по кампусу'))
        ->toBe([]);
});

it('does not read «нас» inside «насос»', function () {
    // The Cyrillic side is where a word-boundary mistake is easiest to make and hardest to see.
    expect($this->rule->violations('Tell us about the pump', 'Расскажите про насос'))
        ->toBe(['us/me'], 'насос must not count as «нас»');
});

it('is case-insensitive on both sides', function () {
    expect($this->rule->violations('TELL US ABOUT IT', 'РАССКАЖИТЕ НАМ ОБ ЭТОМ'))->toBe([]);
});

it('names every group it knows, so a report can show the empty ones too', function () {
    expect(AddresseeIsomorphism::groupNames())->toBe(['us/me', 'you/your', 'we/our', 'well/хорошо']);
});

it('reports each tripped group once, in a stable order', function () {
    expect($this->rule->violations('Can you tell me about our plans?', 'Расскажите про планы'))
        ->toBe(['us/me', 'you/your', 'we/our']);
});

// Ukrainian (QA, 2026-08-18): extends the same detector with a second counterpart list per group
// rather than a second rule — «us/me» happens to share its Russian and Ukrainian forms («нам»,
// «нас»), the other two groups are Ukrainian-specific words that would never appear as Russian
// counterparts.

it('catches «нам» dropped from a `Tell us…` in Ukrainian too — us/me forms overlap Russian', function () {
    expect($this->rule->violations(
        'Tell us about a challenge you faced',
        'Розкажіть про виклик, з яким ви зіткнулися',
        'uk',
    ))->toBe(['us/me']);
});

it('clears the Ukrainian us/me case once «нам» is back', function () {
    expect($this->rule->violations(
        'Tell us about a challenge you faced',
        'Розкажіть нам про виклик, з яким ви зіткнулися',
        'uk',
    ))->toBe([]);
});

it('catches `me` dropped from a Ukrainian translation via мені/мене', function () {
    expect($this->rule->violations('Tell me about yourself', 'Розкажіть про себе', 'uk'))
        ->toBe(['us/me']);
    expect($this->rule->violations('Tell me about yourself', 'Розкажіть мені про себе', 'uk'))
        ->toBe([]);
});

it('catches `your` dropped from a Ukrainian translation, and clears on ваш or твій', function () {
    expect($this->rule->violations('Describe your experience', 'Опишіть досвід', 'uk'))
        ->toBe(['you/your']);
    expect($this->rule->violations('Describe your experience', 'Опишіть ваш досвід', 'uk'))
        ->toBe([]);
    expect($this->rule->violations('What is your name?', 'Як тебе звати?', 'uk'))
        ->toBe([], 'тебе is the second person too — it clears the group since the ты-forms landed');
    expect($this->rule->violations('What is your name?', 'Як твій друг?', 'uk'))
        ->toBe([]);
});

it('catches `our` dropped from a Ukrainian translation via ми/наш*', function () {
    expect($this->rule->violations('Tell us about our plans', 'Розкажіть про плани', 'uk'))
        ->toBe(['us/me', 'we/our']);
    expect($this->rule->violations('Tell us about our plans', 'Розкажіть нам про наші плани', 'uk'))
        ->toBe([]);
});

it('does not read «нас» inside a Ukrainian word either', function () {
    expect($this->rule->violations('Tell us about the pump', 'Розкажіть про насос', 'uk'))
        ->toBe(['us/me'], 'насос must not count as «нас» in Ukrainian any more than in Russian');
});

it('defaults to Russian when no language is given, unchanged from before Ukrainian support', function () {
    // Judged with no $lang: falls back to Russian counterparts, so a Ukrainian translation (which
    // shares none of the Russian pronoun spellings tested here) trips every group the term uses.
    expect($this->rule->violations('Tell us about a challenge you faced', 'Розкажіть про виклик'))
        ->toBe(['us/me', 'you/your']);
});

it('skips every group for a language it has no counterpart list for, rather than false-hitting', function () {
    expect($this->rule->violations('Tell us about a challenge you faced', 'Скажи-nous quelque chose', 'fr'))
        ->toBe([]);
});

// The evidence half (QA-17, whole-store sweep): a group name counts a row, the WORD makes it
// readable. The export hands a human «чего не хватает», and it must be the term's own word, not a
// category the reader has to re-derive by eye.

it('names the term word that went unanswered, not just the group', function () {
    $misses = $this->rule->misses(
        'Can you tell me about the career growth opportunities?',
        'Можете рассказать о возможностях карьерного роста?',
    );

    expect($misses)->toHaveCount(2)
        ->and($misses[0]->group)->toBe('us/me')
        ->and($misses[0]->words)->toBe(['me'], 'the term says `me`, never `us` — the report must not claim otherwise')
        ->and($misses[1]->group)->toBe('you/your')
        ->and($misses[1]->words)->toBe(['you']);
});

it('lists every trigger of a group the term actually uses, in term order', function () {
    $misses = $this->rule->misses('Tell us what you think of your team and our plan', 'Скажите, что думаете о команде и плане');

    expect($misses[0]->words)->toBe(['us'])
        ->and($misses[1]->words)->toBe(['you', 'your'], 'both triggers are in the term, both are unanswered')
        ->and($misses[2]->words)->toBe(['our']);
});

it('carries the forms that would have cleared the group, so a false positive is visible as one', function () {
    // «Расскажите о своём опыте» is good Russian and a flagged row. The reader needs to see WHY:
    // «свой» is not among the forms the rule accepts for `your`.
    $misses = $this->rule->misses('Describe your experience', 'Опишите свой опыт');

    expect($misses)->toHaveCount(1)
        ->and($misses[0]->expected)->toContain('вы', 'ваш', 'ваши')
        ->and($misses[0]->expected)->not->toContain('свой');
});

it('offers the counterparts of the language actually being judged', function () {
    $ru = $this->rule->misses('Tell me about it', 'Расскажите об этом', 'ru');
    $uk = $this->rule->misses('Tell me about it', 'Розкажіть про це', 'uk');

    expect($ru[0]->expected)->toContain('мне')
        ->and($uk[0]->expected)->toContain('мені')
        ->and($uk[0]->expected)->not->toContain('мне');
});

it('says which languages it has been taught, so a sweep can tell «silent» from «clean»', function () {
    // A store that grows a third learner language gets zero candidates in it either way; only this
    // list separates «the rule read it and found nothing» from «the rule cannot read it at all».
    expect(AddresseeIsomorphism::languages())->toBe(['ru', 'uk'])
        ->and(AddresseeIsomorphism::knowsLanguage('ru'))->toBeTrue()
        ->and(AddresseeIsomorphism::knowsLanguage('es'))->toBeFalse();
});

it('reports no miss at all for a language it does not know, rather than an empty-formed one', function () {
    expect($this->rule->misses('Tell us about it', 'Cuéntanos sobre esto', 'es'))->toBe([]);
});

// Вторая волна, часть 1: списки соответствий по следам первого прогона по всей витрине. Каждый
// случай здесь — конкретная строка выгрузки от 2026-08-19, помеченная зря.

it('clears the ты-forms: informal is still the second person', function () {
    // Три строки прошлого прогона. Адресат в переводе есть, правило его не знало.
    expect($this->rule->violations('If I were you, I would take the job', 'На твоём месте я бы согласился на работу'))
        ->toBe([]);
    expect($this->rule->violations('If you study hard, you will pass the exam', 'Если ты будешь усердно учиться, ты сдашь экзамен'))
        ->toBe([]);
    expect($this->rule->violations("What's your number?", 'Какой у тебя номер?'))
        ->toBe([]);
});

it('keeps flagging a translation with no second person at all', function () {
    // The ты-forms must not have widened the group into "any word will do".
    expect($this->rule->violations('Can you describe it?', 'Можете это описать?'))
        ->toBe(['you/your']);
    expect($this->rule->violations('fasten your seatbelt', 'пристегните ремень безопасности'))
        ->toBe(['you/your']);
});

it('does not read «ты» inside another word', function () {
    // «ты» is two letters and lives inside «цветы», «мечты», «работы» — a substring match here would
    // clear half the store silently.
    expect($this->rule->violations('Can you water your flowers?', 'Полить цветы?'))
        ->toBe(['you/your'], 'цветы must not count as «ты»');
    expect($this->rule->violations('What are your dreams?', 'Какие мечты?'))
        ->toBe(['you/your'], 'мечты must not count as «ты»');
});

it('counts «нам» as an answer to `we`, not only to `us`', function () {
    // «Could we have the bill, please?» → «Можно нам счёт, пожалуйста?». The dative first person
    // plural renders `we` here; the group is a person, and «нам» is that person.
    expect($this->rule->violations('Could we have the bill, please?', 'Можно нам счёт, пожалуйста?'))
        ->toBe([]);
    expect($this->rule->violations('Could we have the bill, please?', 'Можно нам счёт, будь ласка?', 'uk'))
        ->toBe([]);
});

it('still catches a `we` that the translation carries in no form', function () {
    expect($this->rule->violations('as per our conversation', 'как обсуждали ранее'))
        ->toBe(['we/our']);
});

it('keeps ru and uk symmetric on the informal second person', function () {
    // The same defect must not be a hit in one language and clean in the other — the whole reason
    // the Ukrainian list grew in the same commit as the Russian one.
    expect($this->rule->violations('Describe your experience', 'Опиши свій досвід', 'uk'))
        ->toBe(['you/your'], 'свій is still not a second-person word in either language');
    expect($this->rule->violations('Describe your experience', 'Опиши твій досвід', 'uk'))->toBe([]);
    expect($this->rule->violations('Describe your experience', 'Опиши твой опыт'))->toBe([]);
});

// Вторая волна, часть 2: пример — такой же ключ, как термин. Правило принимает ИСТОЧНИК, и ему
// всё равно, термин это или предложение; здесь закреплено, что живой кейс с телефона ловится.

it("catches the owner's second live case: «нам» dropped from an EXAMPLE sentence", function () {
    expect($this->rule->violations(
        'Tell us about a challenge you faced and how you overcame it',
        'Расскажите о вызове, с которым вы столкнулись, и как вы его преодолели',
    ))->toBe(['us/me']);
});

it('names `us` as the word the example translation left unanswered', function () {
    $gaps = $this->rule->misses(
        'Tell us about a challenge you faced and how you overcame it',
        'Расскажите о вызове, с которым вы столкнулись, и как вы его преодолели',
    );

    expect($gaps)->toHaveCount(1)
        ->and($gaps[0]->words)->toBe(['us'])
        ->and($gaps[0]->expected)->toContain('нам');
});

it('clears the same example once «нам» is back', function () {
    expect($this->rule->violations(
        'Tell us about a challenge you faced and how you overcame it',
        'Расскажите нам о вызове, с которым вы столкнулись, и как вы его преодолели',
    ))->toBe([]);
});

// Вторая волна, часть 3 (QA-22): обратное правило. Ключ ломается в обе стороны, и вторая —
// зеркальная: перевод несёт то, чего в источнике нет. Учащийся отвечает верно, а ключ требует
// слова, которого никогда не было.

it("catches the owner's live EXTRA case: «хорошо» that no `well` licenses", function () {
    $extras = $this->rule->extras('I get along with my team', 'Я хорошо лажу со своей командой');

    expect($extras)->toHaveCount(1)
        ->and($extras[0]->direction)->toBe(AddresseeDirection::Extra)
        ->and($extras[0]->group)->toBe('well/хорошо')
        ->and($extras[0]->words)->toBe(['хорошо'])
        ->and($extras[0]->expected)->toContain('well', 'good');
});

it('clears the same pair once the source says `well`', function () {
    expect($this->rule->extras('I get along well with my team', 'Я хорошо лажу со своей командой'))
        ->toBe([]);
});

it('does not call «я» extra when the source is first person at all', function () {
    // The point of a separate licence list. `I`/`my` never DEMAND «я» — Russian drops the subject
    // pronoun constantly — but they plainly permit it, and a mirror rule that read only the triggers
    // would flag every first-person sentence in the store.
    expect($this->rule->extras('I get along with my team', 'Я лажу со своей командой'))->toBe([]);
    expect($this->rule->extras('Tell me about yourself', 'Расскажите мне о себе'))->toBe([]);
});

it('catches an addressee the translation invented', function () {
    $extras = $this->rule->extras('Open the window, please', 'Откройте, пожалуйста, ваше окно');

    expect($extras)->toHaveCount(1)
        ->and($extras[0]->group)->toBe('you/your')
        ->and($extras[0]->words)->toBe(['ваше']);
});

it('catches a first person the translation changed the number of', function () {
    // `I` licenses «нам» for the `us/me` group — it is the same person — but nothing in the source
    // licenses the PLURAL, so the group that owns «мы» still speaks up.
    $extras = $this->rule->extras('Can I have the bill?', 'Можно нам счёт?');

    expect($extras)->toHaveCount(1)
        ->and($extras[0]->group)->toBe('we/our')
        ->and($extras[0]->words)->toBe(['нам']);
});

it('reports one unlicensed word once, even when two groups share it', function () {
    // «нам» is first person plural: it lives in both `us/me` and `we/our`. One invented «нам» is one
    // thing to read, not two rows saying it twice.
    $extras = $this->rule->extras('Bring the bill, please', 'Принесите нам счёт, пожалуйста');

    expect($extras)->toHaveCount(1)
        ->and($extras[0]->group)->toBe('us/me', 'the first group to claim the word reports it')
        ->and($extras[0]->words)->toBe(['нам']);
});

it('stays quiet on a translation that invents nothing', function () {
    expect($this->rule->extras('withdraw cash', 'снять наличные'))->toBe([]);
    expect($this->rule->extras('Tell us about a challenge you faced', 'Расскажите нам о вызове, с которым вы столкнулись'))
        ->toBe([]);
});

it('does not read a group word inside another word in the EXTRA direction either', function () {
    expect($this->rule->extras('Turn on the pump', 'Включите насос'))->toBe([]);
    expect($this->rule->extras('Water the flowers', 'Полить цветы'))->toBe([]);
});

it('judges EXTRA in the language being read, and stays silent in a language it does not know', function () {
    expect($this->rule->extras('I get along with my team', 'Я добре ладнаю зі своєю командою', 'uk'))
        ->toHaveCount(1);
    expect($this->rule->extras('I get along with my team', 'Me llevo bien con mi equipo', 'es'))
        ->toBe([]);
});

it('gaps() returns both directions, LOST first', function () {
    // A pair can be broken both ways at once: the addressee is gone AND a «хорошо» arrived.
    $gaps = $this->rule->gaps('Tell us how it went', 'Расскажите, как всё прошло хорошо');

    expect($gaps)->toHaveCount(2)
        ->and($gaps[0]->direction)->toBe(AddresseeDirection::Lost)
        ->and($gaps[0]->words)->toBe(['us'])
        ->and($gaps[1]->direction)->toBe(AddresseeDirection::Extra)
        ->and($gaps[1]->words)->toBe(['хорошо']);
});

it('names the well group among the groups it knows', function () {
    expect(AddresseeIsomorphism::groupNames())->toBe(['us/me', 'you/your', 'we/our', 'well/хорошо']);
});
