<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\PlanRequest;
use App\Modules\Plan\Domain\Blueprint\Blueprint;
use App\Modules\Plan\Domain\Blueprint\BlueprintParser;
use App\Modules\Plan\Domain\Check\BlueprintChecker;
use App\Modules\Plan\Domain\Check\BlueprintContext;
use App\Modules\Plan\Domain\Check\CheckReport;
use App\Modules\Plan\Domain\ValueObject\CheckMode;
use App\Modules\Plan\Domain\ValueObject\CheckModes;
use App\Modules\Plan\Domain\ValueObject\Finding;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;
use App\Modules\Plan\Infrastructure\Prompt\PlanPromptFiles;

/**
 * THE SURVIVAL SET OF A SCENE (`plan-builder-v2.1`, STEP 4; наряд GEN-4, §2): what the plan gives each scene — `must_say`, the
 * learner's intentions with their slots, and `must_understand`, the partner's lines — read by the code, not by meaning. Its
 * shape is fatal (the plan is asked once more); the mechanics of the script of GEN-4a are warnings. Also what else v2.1
 * changed: the priorities after existing scenes, EXISTING_SCENES, the overdue line's full stop.
 */

/** @return array<string, mixed> the fake's plan of five scenes — every scene the doctor's visit's clean set */
function ssPayload(int $scenes = 5): array
{
    return FakePlanModel::planPayload(new PlanRequest('Иду к врачу с ребёнком', 'English', 'Russian', PlanLevel::Beginner, $scenes));
}

/** @return CheckReport<Blueprint> */
function ssRun(array $payload, string $check, CheckMode $mode = CheckMode::Observe, int $existing = 0): CheckReport
{
    return (new BlueprintChecker(CheckModes::fromArray([$check => $mode->value])))
        ->run((new BlueprintParser)->parse($payload), new BlueprintContext(count($payload['scenes']), $existing));
}

/** @return list<string> the details a check found */
function ssFound(array $payload, string $check, int $existing = 0): array
{
    return array_values(array_map(
        static fn (Finding $f): string => $f->detail,
        array_filter(ssRun($payload, $check, existing: $existing)->findings, static fn (Finding $f): bool => $f->check === $check),
    ));
}

/**
 * The fake's plan with the survival set of its second scene edited.
 *
 * @param  Closure(list<string>, list<string>): array{0: list<string>, 1: list<string>}  $edit
 * @return array<string, mixed>
 */
function ssSet(Closure $edit): array
{
    $p = ssPayload();
    [$p['scenes'][1]['must_say'], $p['scenes'][1]['must_understand']] = $edit($p['scenes'][1]['must_say'], $p['scenes'][1]['must_understand']);

    return $p;
}

it('finds nothing in the fake\'s survival set, and parses it into items and slots', function () {
    $scene = (new BlueprintParser)->parse(ssPayload())->scenes[1];

    foreach (['survival_set', 'survival_verbs', 'survival_asks', 'survival_slot_none', 'survival_unanswered', 'survival_slot_answer'] as $check) {
        expect(ssFound(ssPayload(), $check))->toBe([], $check);
    }
    expect($scene->survival->mustSay[0])->toBe(['text' => 'say where it hurts', 'slot' => 'the part of the body', 'marked' => true])
        ->and($scene->survival->mustSay[3]['slot'])->toBeNull()
        ->and($scene->survival->mustUnderstand[0])->toBe('asks where exactly it hurts')
        ->and($scene->survival->sayLines()[0])->toBe('say where it hurts — slot: the part of the body');
});

// Наряд GEN-4, §2: «must_say 6–8, must_understand 4–5 … Фатальное — перегон»; v2.1: «every must_say item contains "— slot:"». The
// only check of the set that ships as `gate`: the day is built from the set. Catches a set of the wrong size or an item
// without its slot let through to a skeleton that cannot be built from it, and a config that ships it otherwise.
it('survival_set: a set out of its sizes, or an item without its slot, refuses the plan', function (Closure $edit, string $found) {
    $p = ssSet($edit);

    expect(ssFound($p, 'survival_set'))->toHaveCount(1)
        ->and(ssFound($p, 'survival_set')[0])->toContain($found)
        ->and(ssRun($p, 'survival_set', CheckMode::Gate)->gated)->toBeTrue();
})->with([
    'five to say' => [static fn (array $say, array $understand): array => [array_slice($say, 0, 5), $understand], 'must_say has 5 items'],
    'nine to say' => [static fn (array $say, array $understand): array => [[...$say, 'say thanks — slot: none', 'say goodbye — slot: none'], $understand], 'must_say has 9 items'],
    'three to understand' => [static fn (array $say, array $understand): array => [$say, array_slice($understand, 0, 3)], 'must_understand has 3 items'],
    'six to understand' => [static fn (array $say, array $understand): array => [$say, [...$understand, 'asks for the card']], 'must_understand has 6 items'],
    'no slot' => [static fn (array $say, array $understand): array => [[...array_slice($say, 0, 6), 'ask whether another visit is needed'], $understand], 'has no «— slot:»'],
]);

it('ships the shape of the set as the one gate of the plan, the mechanics as warnings', function () {
    $checks = (require dirname(__DIR__, 3).'/config/plan.php')['checks']['plan'];

    expect($checks['survival_set'])->toBe('gate');
    foreach (['survival_verbs', 'survival_asks', 'survival_slot_none', 'survival_unanswered', 'survival_slot_answer'] as $check) {
        expect($checks[$check] ?? 'observe')->toBe('observe', $check);
    }
});

// v2.1, STEP 4: «each item starts with say, ask, answer, confirm, explain or give». A warning.
it('survival_verbs: an item that starts with another verb', function () {
    $p = ssSet(static fn (array $say, array $understand): array => [[...array_slice($say, 0, 6), 'tell the doctor about the visit — slot: the visit'], $understand]);

    expect(ssFound($p, 'survival_verbs'))->toBe(['scene 2: must_say 7 «tell the doctor about the visit» starts with no verb of the list']);
});

// v2.1, STEP 4: «at least two are questions to the partner and start with ask». A warning.
it('survival_asks: fewer than two questions of the learner\'s', function () {
    $p = ssSet(static fn (array $say, array $understand): array => [[...array_slice($say, 0, 6), 'confirm another visit — slot: the visit'], $understand]);

    expect(ssFound($p, 'survival_asks'))->toBe(['scene 2: 1 must_say items start with ask (at least 2)']);
});

// v2.1, STEP 4: «at most two of a scene end with "— slot: none"». A warning.
it('survival_slot_none: three items with nothing to swap', function () {
    $p = ssSet(static function (array $say, array $understand): array {
        $say[0] = 'say that it hurts — slot: none';
        $say[1] = 'say that it started today — slot: none';

        return [$say, $understand];
    });

    expect(ssFound($p, 'survival_slot_none'))->toBe(['scene 2: 3 must_say items end with «— slot: none» (at most 2)']);
});

// v2.1, STEP 4: «every question here has its answer in must_say» — by a shared content word of the item and of an answer
// with its slot (наряд GEN-4: «по эвристике скрипта»). A warning. Catches a partner's question the learner is never taught to
// answer, and a learner's own question counted as the answer.
it('survival_unanswered: a question of the partner\'s no item of the learner\'s answers', function () {
    $p = ssSet(static fn (array $say, array $understand): array => [$say, [...array_slice($understand, 0, 4), 'asks about his allergies']]);
    $asked = ssSet(static fn (array $say, array $understand): array => [$say, [...array_slice($understand, 0, 4), 'asks whether another visit is needed']]);

    expect(ssFound($p, 'survival_unanswered'))->toBe(['scene 2: must_understand 5 «asks about his allergies» has no answer in must_say'])
        // «ask whether another visit is needed» is the learner's question, no answer to the partner's.
        ->and(ssFound($asked, 'survival_unanswered'))->toBe(['scene 2: must_understand 5 «asks whether another visit is needed» has no answer in must_say']);
});

// v2.1, STEP 4: «a question is written as a pattern whose slot is the thing asked about … never the answer the partner will
// give» — «ask how much the rent is — slot: the price». A warning. Catches a slot that names what an answer is, and a slot
// the partner only speaks about read as its answer.
it('survival_slot_answer: a question whose slot is its answer', function (string $item, string $partner, int $found) {
    $p = ssSet(static fn (array $say, array $understand): array => [[...array_slice($say, 0, 6), $item], [...array_slice($understand, 0, 4), $partner]]);

    expect(ssFound($p, 'survival_slot_answer'))->toHaveCount($found);
})->with([
    'an answer\'s word' => ['ask how much the visit costs — slot: the price', 'says what is needed next', 1],
    'a schedule asked for' => ['ask what the usual schedule is — slot: the schedule', 'tells you the usual schedule', 1],
    'the thing asked about' => ['ask whether a holding deposit is needed — slot: the deposit', 'says whether a deposit is needed and how it works', 0],
]);

// Наряд GEN-4, §2: «PrioritiesCheck: при EXISTING_SCENES приоритеты продолжаются за существующими». Catches an extension held to
// «one priority 1» — the new scenes can never have it — and a drop that renumbers them from 1.
it('priorities: continue after the existing scenes, in any order, and are renumbered after them', function () {
    $p = ssPayload(2);
    $p['scenes'][0]['priority'] = 5;
    $p['scenes'][1]['priority'] = 4;
    $fromOne = ssPayload(2);

    expect(ssFound($p, 'priorities', existing: 3))->toBe([])
        ->and(ssFound($fromOne, 'priorities', existing: 3))->toBe(['priorities are 2,1 instead of 4,5 (in any order) after 3 existing scenes'])
        ->and(array_map(static fn ($s): int => $s->priority, ssRun($fromOne, 'priorities', CheckMode::Drop, existing: 3)->answer->scenes))->toBe([4, 5])
        // From the start: one priority 1, as before.
        ->and(ssFound(ssPayload(2), 'priorities'))->toBe([]);
});

// Наряд GEN-4, §2: «EXISTING_SCENES в новом формате — заголовок + must_say каждой существующей сцены». Catches an extension
// asked with the old form of the existing scenes, the items left out, or a set sent without its slots.
it('sends EXISTING_SCENES as each scene\'s title with its must_say items under it', function () {
    $user = (new PlanPromptFiles)->planUser(new PlanRequest('Иду к врачу', 'English', 'Russian', PlanLevel::Beginner, 2, [
        ['title' => 'Запись к врачу', 'must_say' => ['say who the visit is for — slot: the person', 'ask what time is free — slot: the day']],
        ['title' => 'Приём у врача', 'must_say' => ['say where it hurts — slot: the part of the body']],
    ]));

    expect($user)->toEndWith("SCENES_COUNT: 2\nEXISTING_SCENES:\n- Запись к врачу\n  - say who the visit is for — slot: the person\n  - ask what time is free — slot: the day\n- Приём у врача\n  - say where it hurts — slot: the part of the body")
        ->and((new PlanPromptFiles)->planUser(new PlanRequest('Иду к врачу', 'English', 'Russian', PlanLevel::Beginner, 5)))->toEndWith("SCENES_COUNT: 5\nEXISTING_SCENES:");
});

// Наряд GEN-4, §2: «overdue_native — точка в конце снимается кодом». Catches the phone's line «Приём был вчера.» with its stop.
it('takes the full stop off the overdue line, and nothing else', function () {
    $p = ssPayload();
    $p['plan']['overdue_native'] = 'Приём был вчера.';
    $ellipsis = ssPayload();
    $ellipsis['plan']['overdue_native'] = 'Приём был вчера…';

    expect((new BlueprintParser)->parse($p)->titles?->overdueNative)->toBe('Приём был вчера')
        ->and((new BlueprintParser)->parse($ellipsis)->titles?->overdueNative)->toBe('Приём был вчера…');
});
