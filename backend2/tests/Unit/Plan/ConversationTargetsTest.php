<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Application\Dto\PlanRequest;
use App\Modules\Plan\Application\Service\ConversationMaterial;
use App\Modules\Plan\Domain\Blueprint\BlueprintParser;
use App\Modules\Plan\Domain\Check\Language\LanguagePack;
use App\Modules\Plan\Domain\Check\Language\LanguagePacks;
use App\Modules\Plan\Domain\Entity\Plan;
use App\Modules\Plan\Domain\Lesson\EarlierDays;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\Repository\PlanTermRepository;
use App\Modules\Plan\Domain\Service\ConversationTargets;
use App\Modules\Plan\Domain\Service\IntentClause;
use App\Modules\Plan\Domain\Service\NativeStrings;
use App\Modules\Plan\Domain\Service\PlanCalendar;
use App\Modules\Plan\Domain\ValueObject\ConversationCheckpoint;
use App\Modules\Plan\Domain\ValueObject\ConversationPhrase;
use App\Modules\Plan\Domain\ValueObject\Image;
use App\Modules\Plan\Domain\ValueObject\ModelCall;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\PlanTermId;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\UserId;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/**
 * WHAT THE TALK IS FOR AND HOW IT IS NAMED (наряд CONV-2, пп. 10–12): the targets «Скажи в разговоре», the intention as a
 * clause for «Скажи, что …», and the title «Поговори с врачом».
 */

/** A scene of `$n` phrases whose learner lines say them in the order given. @param list<string> $said the refs the visit says, in order */
function ctScene(string $id, int $n, array $said): array
{
    $phrases = [];
    for ($i = 1; $i <= $n; $i++) {
        $phrases[] = new ConversationPhrase($id, "p{$i}", "Phrase {$id} {$i} ___.", "Фраза {$id} {$i} ___.", "value {$i}", "значение {$i}");
    }
    $lines = array_map(static fn (string $ref): array => ['target' => "Line {$ref}.", 'native' => "Реплика {$ref}.", 'phrase_ref' => $ref], $said);

    return [new ConversationCheckpoint($id, 'Сцена', 'Scene', 'о чём', 'Doctor', 'Врач', VoiceGender::Female, $lines), $phrases];
}

/**
 * Canon (п. 10): «targets[] — 4–7 целевых фраз по чекпойнтам по порядку»; the summary counts the same list. Inside a scene
 * the phrases come as the visit SAYS them (a frame no line stands on after them); over several scenes the seven places are
 * shared out scene after scene. Catches a list in the frames' own order, a rehearsal whose first scene takes every place,
 * and more than seven.
 */
it('asks a talk for its phrases in the order of its scenes and their visits, seven at most, shared between scenes', function () {
    [$doctor, $doctorPhrases] = ctScene('s1', 7, ['p3', 'p1', 'p2', 'p3', 'p5', 'p4', 'p6']);
    $one = ConversationTargets::of([$doctor], $doctorPhrases);
    // p7 is on no line of the visit: it comes last; p3 said twice counts once, where it is said first.
    expect(array_map(static fn (ConversationPhrase $p): string => $p->ref, $one))->toBe(['p3', 'p1', 'p2', 'p5', 'p4', 'p6', 'p7']);

    [$flatCall, $callPhrases] = ctScene('a', 7, ['p1', 'p2', 'p3', 'p4', 'p5', 'p6', 'p7']);
    [$flatVisit, $visitPhrases] = ctScene('b', 8, ['p1', 'p2', 'p3', 'p4', 'p5', 'p6', 'p7', 'p8']);
    $rehearsal = ConversationTargets::of([$flatCall, $flatVisit], [...$callPhrases, ...$visitPhrases]);
    expect(array_map(static fn (ConversationPhrase $p): string => $p->id(), $rehearsal))
        ->toBe(['a:p1', 'a:p2', 'a:p3', 'a:p4', 'b:p1', 'b:p2', 'b:p3']);

    // A scene of eight asks for seven; one of three asks for its three — nothing is invented to reach four.
    [$eight, $eightPhrases] = ctScene('c', 8, ['p1', 'p2', 'p3', 'p4', 'p5', 'p6', 'p7', 'p8']);
    [$three, $threePhrases] = ctScene('d', 3, ['p1', 'p2', 'p3']);
    expect(ConversationTargets::of([$eight], $eightPhrases))->toHaveCount(7)
        ->and(ConversationTargets::of([$three], $threePhrases))->toHaveCount(3)
        // …and a short scene gives its places to the long one beside it.
        ->and(array_map(static fn (ConversationPhrase $p): string => $p->id(), ConversationTargets::of([$three, $eight], [...$threePhrases, ...$eightPhrases])))
        ->toBe(['d:p1', 'd:p2', 'd:p3', 'c:p1', 'c:p2', 'c:p3', 'c:p4']);
});

/**
 * Canon (п. 11): «hints.native — придаточным без заглавной и точки». The rule the client wrote for itself
 * (`TalkTexts.clause`) moved to the server: the chip read «Скажи, что У моего сына температура.» (CLIENT-CONV-1a §5 п. 10).
 * The talk's `hints.native` is gone (наряд ACC-1 §5 — the build (21) shows `hints.sentence`); the rule stays the task of
 * «Говорю сам» (`task_clause_native`). Catches a capital glued inside a sentence, a full stop inside it, an abbreviation
 * lower-cased, and a question mark or an ellipsis eaten.
 */
it('sends the intention as a clause: no capital, no closing full stop, the rest as written', function () {
    expect(IntentClause::of('У моего сына температура.'))->toBe('у моего сына температура')
        ->and(IntentClause::of('США — это далеко.'))->toBe('США — это далеко')
        ->and(IntentClause::of('Это двухкомнатная квартира?'))->toBe('это двухкомнатная квартира?')
        ->and(IntentClause::of('Ну...'))->toBe('ну...')
        ->and(IntentClause::of('у моего сына температура'))->toBe('у моего сына температура')
        ->and(IntentClause::of('Я могу оплатить картой!'))->toBe('я могу оплатить картой!');
});

/**
 * Canon (п. 12): «talk_title_native („Поговори с врачом")». The role comes in the nominative only; the title puts it in the
 * instrumental by rule, and where the ending hangs on stress the rule does not guess — «Поговори с собеседником» is plain
 * and never wrong. Every role the three databases hold on 21.09 is here. Catches «Поговори с Врач», an abbreviation
 * lower-cased, a genitive complement inflected, and a guessed ending.
 */
it('names the talk «Поговори с врачом», by rule, and says «с собеседником» where the ending would be a guess', function () {
    $ru = new NativeStrings('ru');
    $roles = [
        'Врач' => 'Поговори с врачом', 'Агент' => 'Поговори с агентом', 'Администратор' => 'Поговори с администратором',
        'Ветеринар' => 'Поговори с ветеринаром', 'Интервьюер' => 'Поговори с интервьюером', 'Тренер' => 'Поговори с тренером',
        'Фармацевт' => 'Поговори с фармацевтом', 'Хозяин' => 'Поговори с хозяином', 'HR-менеджер' => 'Поговори с HR-менеджером',
        'Арендодатель' => 'Поговори с арендодателем', 'Нанимающий менеджер' => 'Поговори с нанимающим менеджером',
        'Официант' => 'Поговори с официантом', 'Регистратор' => 'Поговори с регистратором',
        'Сотрудник банка' => 'Поговори с сотрудником банка', 'Сотрудник регистрации' => 'Поговори с сотрудником регистрации',
        'Сотрудник стойки' => 'Поговори с сотрудником стойки', 'Тимлид' => 'Поговори с тимлидом',
        'Медсестра' => 'Поговори с медсестрой', 'Продавщица' => 'Поговори с продавщицей', 'Главный врач' => 'Поговори с главным врачом',
        'Стоматолог' => 'Поговори со стоматологом', 'Портье' => 'Поговори с портье', 'Горничная' => 'Поговори с горничной',
        'Секретарь' => 'Поговори с секретарём', 'Сторож' => 'Поговори со сторожем',
        // A stress the rule cannot see: «-ец» and «-ь» not in the list.
        'Иностранец' => 'Поговори с собеседником', 'Дикарь' => 'Поговори с собеседником', '' => 'Поговори с собеседником',
    ];
    foreach ($roles as $role => $title) {
        expect($ru->talkTitle([(string) $role]))->toBe($title, (string) $role);
    }

    expect((new NativeStrings('uk'))->talkTitle(['Лікар']))->toBe('Поговори з лікарем')
        ->and((new NativeStrings('uk'))->talkTitle(['Співробітник банку']))->toBe('Поговори зі співробітником банку')
        ->and((new NativeStrings('en'))->talkTitle(['Doctor']))->toBe('Talk to the doctor')
        ->and((new NativeStrings('en'))->talkTitle(['HR manager']))->toBe('Talk to the HR manager')
        ->and($ru->talkTitle([]))->toBe('Поговори с собеседником');
});

/**
 * Canon (наряд FIX-4c §4): «talk_title_native: одна сцена — как сейчас; две — „Поговори с регистратором и врачом"; три и
 * больше — „Поговори с регистратором, врачом и медсестрой". Формы ролей — из того же источника, что даёт нынешнее „с врачом"».
 * The preposition is the first role's and said once; a role two scenes share is said once; a role the rule cannot inflect
 * makes the whole title plain — «с собеседниками» for several. CATCHES the rehearsal's doctor lost from its title
 * («Поговори с регистратором»), «с регистратором и с врачом», «со стоматологом» turned «с стоматологом» behind a first
 * role, and a guessed ending among good ones.
 */
it('names a talk over several scenes by every role: «Поговори с регистратором и врачом», «…, врачом и медсестрой»', function () {
    $ru = new NativeStrings('ru');

    expect($ru->talkTitle(['Врач']))->toBe('Поговори с врачом')
        ->and($ru->talkTitle(['Регистратор', 'Врач']))->toBe('Поговори с регистратором и врачом')
        ->and($ru->talkTitle(['Регистратор', 'Врач', 'Медсестра']))->toBe('Поговори с регистратором, врачом и медсестрой')
        ->and($ru->talkTitle(['Регистратор', 'Врач', 'Фармацевт', 'Медсестра']))->toBe('Поговори с регистратором, врачом, фармацевтом и медсестрой')
        ->and($ru->talkTitle(['Стоматолог', 'Врач']))->toBe('Поговори со стоматологом и врачом')
        ->and($ru->talkTitle(['Врач', 'Регистратор', 'Врач']))->toBe('Поговори с врачом и регистратором')
        ->and($ru->talkTitle(['Врач', 'Врач']))->toBe('Поговори с врачом')
        ->and($ru->talkTitle(['Регистратор', 'Иностранец']))->toBe('Поговори с собеседниками')
        ->and((new NativeStrings('uk'))->talkTitle(['Лікар', 'Секретар']))->toBe('Поговори з лікарем і секретарем')
        ->and((new NativeStrings('en'))->talkTitle(['Receptionist', 'Doctor', 'Nurse']))->toBe('Talk to the receptionist, the doctor and the nurse');
});

/**
 * A pack's `talk_title_template` (наряд LANG-1 §6) — the shape every language executor writes, here with synthetic packs so
 * the rule is tested apart from what the packs will say.
 *
 * @param  array<string, mixed>  $extra
 */
function ctTalkPack(string $code, string $title, string $and, string $anyone, bool $lowerFirst = true, array $extra = []): LanguagePack
{
    return new LanguagePack($code, ['talk_title_template' => [
        'title' => $title, 'and' => $and, 'anyone' => $anyone, 'lower_first' => $lowerFirst, ...$extra,
    ]]);
}

/**
 * Canon (наряд LANG-1 §6; дополняет пп. 375, 417): «talk_title_template — for natives WITHOUT declension rules in code …
 * Roles joined „a, b and c" with the pack's `and`; `anyone` when the list of roles is empty; lower_first lower-cases the
 * first letter of each role unless the role is an acronym; de has lower_first false». The title of a talk for a Polish,
 * Spanish, German or Belarusian learner names the roles and inflects none. CATCHES the English fallback with the learner's
 * nouns in it («Talk to the recepcjonistka and the lekarz»), a role in capitals mid-title («Rozmowa: Lekarz»), a German noun
 * lower-cased, an acronym lower-cased («hR-menedżer»), a role two scenes share said twice, «, » before the last role, an
 * empty title for a talk with no role, and a template read for ru/uk/en — whose titles must not move by a byte.
 */
it('names the talk of any other native by its pack: «Rozmowa: recepcjonistka i lekarz», nothing inflected', function () {
    $pl = ctTalkPack('pl', 'Rozmowa: {roles}', 'i', 'Rozmowa');
    $es = ctTalkPack('es', 'Conversación: {roles}', 'y', 'Conversación');
    $de = ctTalkPack('de', 'Gespräch: {roles}', 'und', 'Gespräch', lowerFirst: false);
    $be = ctTalkPack('be', 'Размова: {roles}', 'і', 'Размова');
    $plStrings = new NativeStrings('pl');

    expect($plStrings->talkTitle(['Recepcjonistka', 'Lekarz'], $pl))->toBe('Rozmowa: recepcjonistka i lekarz')
        ->and($plStrings->talkTitle(['Lekarz'], $pl))->toBe('Rozmowa: lekarz')
        ->and((new NativeStrings('es'))->talkTitle(['Recepcionista', 'Médico'], $es))->toBe('Conversación: recepcionista y médico')
        ->and((new NativeStrings('de'))->talkTitle(['Rezeptionistin', 'Arzt'], $de))->toBe('Gespräch: Rezeptionistin und Arzt')
        ->and((new NativeStrings('be'))->talkTitle(['Рэгістратар'], $be))->toBe('Размова: рэгістратар')
        // Three roles and more: commas, the pack's «and» once, before the last.
        ->and($plStrings->talkTitle(['Recepcjonistka', 'Lekarz', 'Pielęgniarka'], $pl))->toBe('Rozmowa: recepcjonistka, lekarz i pielęgniarka')
        ->and($plStrings->talkTitle(['Recepcjonistka', 'Lekarz', 'Farmaceuta', 'Pielęgniarka'], $pl))
        ->toBe('Rozmowa: recepcjonistka, lekarz, farmaceuta i pielęgniarka')
        // A role two scenes share is said once, where it comes first — whatever its capitals and spaces.
        ->and($plStrings->talkTitle(['Lekarz', 'Recepcjonistka', 'lekarz '], $pl))->toBe('Rozmowa: lekarz i recepcjonistka')
        ->and($plStrings->talkTitle(['Lekarz', 'Lekarz'], $pl))->toBe('Rozmowa: lekarz')
        // No role to name: the whole title is the pack's own word.
        ->and($plStrings->talkTitle([], $pl))->toBe('Rozmowa')
        ->and($plStrings->talkTitle(['', '  '], $pl))->toBe('Rozmowa')
        // An acronym keeps its capitals; a capital later in the role is not the first word's.
        ->and($plStrings->talkTitle(['HR-menedżer', 'Lekarz'], $pl))->toBe('Rozmowa: HR-menedżer i lekarz')
        ->and($plStrings->talkTitle(['Specjalista IT'], $pl))->toBe('Rozmowa: specjalista IT');

    // A native whose pack has no template — or no pack — keeps the English fallback, as before.
    expect($plStrings->talkTitle(['Recepcjonistka', 'Lekarz']))->toBe('Talk to the recepcjonistka and the lekarz')
        ->and($plStrings->talkTitle(['Recepcjonistka', 'Lekarz'], LanguagePack::none('pl')))->toBe('Talk to the recepcjonistka and the lekarz')
        ->and($plStrings->talkTitle(['Lekarz'], new LanguagePack('pl', ['talk_title_template' => null])))->toBe('Talk to the lekarz')
        ->and($plStrings->talkTitle(['Lekarz'], new LanguagePack('pl', ['talk_title_template' => []])))->toBe('Talk to the lekarz')
        // A template without its three strings is no template — never a title with a hole in it.
        ->and($plStrings->talkTitle(['Lekarz'], new LanguagePack('pl', ['talk_title_template' => ['title' => 'Rozmowa: {roles}', 'and' => 'i']])))
        ->toBe('Talk to the lekarz');

    // ru, uk and en keep their own titles whatever the pack they are handed says.
    $foreign = ctTalkPack('xx', 'Rozmowa: {roles}', 'i', 'Rozmowa');
    expect((new NativeStrings('ru'))->talkTitle(['Регистратор', 'Врач'], $foreign))->toBe('Поговори с регистратором и врачом')
        ->and((new NativeStrings('ru'))->talkTitle([], $foreign))->toBe('Поговори с собеседником')
        ->and((new NativeStrings('uk'))->talkTitle(['Лікар'], $foreign))->toBe('Поговори з лікарем')
        ->and((new NativeStrings('en'))->talkTitle(['Receptionist', 'Doctor'], $foreign))->toBe('Talk to the receptionist and the doctor');
});

/**
 * Canon (наряд LANG-1 §6): «Spanish „y" before a word starting with i-/hi- becomes „e" („médico e internista") — via a
 * pack field». `and_before` maps a pattern of the last role, as printed, to the word said instead of `and`; the pattern
 * is the pack's, the code knows no Spanish. CATCHES «médico y internista», «y» turned «e» before «hie-» (a diphthong —
 * «agua y hielo»), and the swap applied to a join that is not before the matching role.
 */
it('says the pack\'s other «and» before a role its pattern matches: «médico e internista», «y hierbero»', function () {
    $es = ctTalkPack('es', 'Conversación: {roles}', 'y', 'Conversación', extra: ['and_before' => ['/^h?[ií](?![aeouáéóú])/iu' => 'e']]);
    $strings = new NativeStrings('es');

    expect($strings->talkTitle(['Médico', 'Internista'], $es))->toBe('Conversación: médico e internista')
        ->and($strings->talkTitle(['Médico', 'Higienista'], $es))->toBe('Conversación: médico e higienista')
        ->and($strings->talkTitle(['Recepcionista', 'Médico', 'Intérprete'], $es))->toBe('Conversación: recepcionista, médico e intérprete')
        ->and($strings->talkTitle(['Médico', 'Hierbero'], $es))->toBe('Conversación: médico y hierbero')
        ->and($strings->talkTitle(['Internista', 'Médico'], $es))->toBe('Conversación: internista y médico')
        ->and($strings->talkTitle(['Internista'], $es))->toBe('Conversación: internista')
        // An empty map is the pack's explicit «no such rule».
        ->and($strings->talkTitle(['Médico', 'Internista'], ctTalkPack('es', 'Conversación: {roles}', 'y', 'Conversación', extra: ['and_before' => []])))
        ->toBe('Conversación: médico y internista')
        // A title with no place for its roles would name nobody: no template — the same line the pack's own reader draws.
        ->and($strings->talkTitle(['Médico'], ctTalkPack('es', 'Conversación', 'y', 'Conversación')))->toBe('Talk to the médico');
});

/**
 * The talk of day 1 of a fresh plan — its one scene's lesson written by the fake and illustrated — as
 * {@see ConversationMaterial} names it with the packs given, and the scene's partner role as the plan wrote it.
 *
 * @return array{0: string|null, 1: string}
 */
function ctMaterialTitle(string $native, LanguagePacks $packs): array
{
    $now = new DateTimeImmutable('2026-09-17T10:00:00Z');
    $plan = Plan::create(
        id: PlanId::generate(),
        userId: UserId::generate(),
        goalText: 'Иду к врачу с ребёнком',
        targetLang: new LanguageCode('en'),
        nativeLang: new LanguageCode($native),
        level: PlanLevel::Beginner,
        daysRequested: 3,
        eventDate: null,
        today: new DateTimeImmutable('2026-09-17'),
        now: $now,
        dayIds: static fn (): PlanDayId => PlanDayId::generate(),
    );
    $plan->beginBuild($now);
    $request = new PlanRequest('врач', 'English', 'Russian', PlanLevel::Beginner, PlanCalendar::scenesCount(3));
    $plan->acceptBlueprint((new BlueprintParser)->parse(FakePlanModel::planPayload($request)), new ModelCall('plan-builder-v2', 'test', 'fake', '0.000000', 1, 1), [], static fn (): PlanSceneId => PlanSceneId::generate());
    $scene = $plan->sceneOf($plan->day(1)) ?? throw new RuntimeException('day 1 holds no scene');
    $payload = FakePlanModel::lessonPayload(new LessonRequest('x', 'x', 'English', 'Russian', PlanLevel::Beginner, null, 8, 8, FakePlanModel::roles(), new EarlierDays));
    $scene->acceptLesson((new LessonParser)->parse($payload), lessonPacks()->for('en'), new ModelCall('lesson_day.v4.9', 'test', 'fake', '0.000000', 1, 1), [], $now);
    $scene->finishIllustration($now);

    // The talk's title needs no term: a repository that holds none, and fails loudly if asked to write.
    $terms = new class implements PlanTermRepository
    {
        public function forScene(PlanSceneId $sceneId): array
        {
            return [];
        }

        public function forScenes(array $sceneIds): array
        {
            return [];
        }

        public function replaceForScene(PlanSceneId $sceneId, array $terms): void
        {
            throw new LogicException('read only');
        }

        public function rewriteTexts(PlanSceneId $sceneId, array $terms): void
        {
            throw new LogicException('read only');
        }

        public function attachImage(PlanTermId $id, Image $image): void
        {
            throw new LogicException('read only');
        }

        public function markImageMissing(PlanTermId $id, string $tone): void
        {
            throw new LogicException('read only');
        }

        public function replaceImage(PlanTermId $id, Image $image): void
        {
            throw new LogicException('read only');
        }

        public function photographedWithoutPrompt(?PlanId $planId): array
        {
            return [];
        }

        public function repeatingDayPhotos(?PlanId $planId): array
        {
            return [];
        }
    };

    return [(new ConversationMaterial($terms, $packs))->for($plan, $plan->day(1))->titleNative, $scene->partnerRoleNative()];
}

/**
 * Canon (наряд LANG-1 §6): the title is asked of the LEARNER'S pack at its one producer — {@see ConversationMaterial},
 * whose `titleNative` the window's talk row and the talk itself both print. CATCHES the pack left out at the call (a Polish
 * learner's talk named «Talk to the …» although the pack writes its template — every NativeStrings test above would still
 * pass), the TARGET'S pack asked instead (the decoy «WRONG: …» on en), and a Russian learner's title moved by a template.
 */
it('names the talk by the learner\'s own pack where the talk is assembled, never by the target\'s', function () {
    $packs = new LanguagePacks([
        'en' => ['talk_title_template' => ['title' => 'WRONG: {roles}', 'and' => 'and', 'anyone' => 'WRONG', 'lower_first' => true]],
        'pl' => ['talk_title_template' => ['title' => 'Rozmowa: {roles}', 'and' => 'i', 'anyone' => 'Rozmowa', 'lower_first' => true]],
        'ru' => ['talk_title_template' => ['title' => 'WRONG: {roles}', 'and' => 'и', 'anyone' => 'WRONG', 'lower_first' => true]],
    ]);

    [$pl, $role] = ctMaterialTitle('pl', $packs);
    expect($role)->not->toBe('')
        ->and($pl)->toBe('Rozmowa: '.mb_strtolower(mb_substr($role, 0, 1)).mb_substr($role, 1));

    [$ru] = ctMaterialTitle('ru', $packs);
    expect($ru)->toStartWith('Поговори с')
        ->and(str_contains((string) $ru, 'WRONG'))->toBeFalse();

    // A native whose pack writes no template keeps the English fallback, as before наряд LANG-1.
    [$bare, $bareRole] = ctMaterialTitle('pl', new LanguagePacks(['en' => [], 'pl' => []]));
    expect($bare)->toBe('Talk to the '.mb_strtolower(mb_substr($bareRole, 0, 1)).mb_substr($bareRole, 1));
});

/**
 * Canon (наряд LANG-1 §6, `talk_title_template.and_before`): the patterns are the pack's, and the title runs them on every
 * talk it names — a pattern that does not compile is a PHP warning, which the framework turns into a 500 on the day's
 * window and on the talk. {@see LanguagePack::talkTitleTemplate()} reads the other four fields and not this one, so the
 * pack-shape check of the four does not see it. CATCHES a deployed pack whose `and_before` holds a broken pattern, a
 * pattern keyed by a number, or a word that is no word.
 */
it('compiles every «and_before» pattern a deployed pack writes for its talk title', function () {
    $packs = lessonPacks();
    expect(count($packs->codes()))->toBeGreaterThan(0);
    foreach ($packs->codes() as $code) {
        $pack = $packs->for($code);
        $template = $pack->has('talk_title_template') ? $pack->map('talk_title_template') : [];
        $before = $template['and_before'] ?? [];
        expect($before)->toBeArray("{$code}: talk_title_template.and_before");
        foreach (is_array($before) ? $before : [] as $pattern => $word) {
            expect(is_string($pattern) && @preg_match($pattern, '') !== false)->toBeTrue("{$code}: and_before «{$pattern}» does not compile")
                ->and(is_string($word) && trim($word) !== '')->toBeTrue("{$code}: and_before «{$pattern}» says no word");
        }
    }
});
