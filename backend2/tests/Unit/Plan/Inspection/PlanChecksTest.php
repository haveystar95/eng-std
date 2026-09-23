<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Inspection\CallFact;
use App\Modules\Plan\Domain\Inspection\CardSoundFact;
use App\Modules\Plan\Domain\Inspection\Check\BuildingOutOfLine;
use App\Modules\Plan\Domain\Inspection\Check\DayCostOverCanon;
use App\Modules\Plan\Domain\Inspection\Check\DayFailed;
use App\Modules\Plan\Domain\Inspection\Check\LineWithoutSound;
use App\Modules\Plan\Domain\Inspection\Check\LostModelCall;
use App\Modules\Plan\Domain\Inspection\Check\PassedWithoutSummary;
use App\Modules\Plan\Domain\Inspection\Check\ScheduledDayNotReady;
use App\Modules\Plan\Domain\Inspection\Check\SoundTextMismatch;
use App\Modules\Plan\Domain\Inspection\Check\TalkEndedByLimit;
use App\Modules\Plan\Domain\Inspection\Check\TalkWithoutOpeners;
use App\Modules\Plan\Domain\Inspection\Check\VoiceGenderMismatch;
use App\Modules\Plan\Domain\Inspection\DayFact;
use App\Modules\Plan\Domain\Inspection\LineFact;
use App\Modules\Plan\Domain\Inspection\PlanFacts;
use App\Modules\Plan\Domain\Inspection\PlanIssue;
use App\Modules\Plan\Domain\Inspection\TalkFact;

/*
 * «ЧТО НЕ ТАК» ON CANON (наряд ADM-1): each check against what MUST hold — a learner hears what a card shows; the learner
 * speaks in the profile's gender and the partner in the role's (DECISIONS п. 389); the server voices every line (п. 309);
 * a failed lesson, a day at the door without its lesson, a build out of line (GEN-3 §11); a lost call (п. 334); a day
 * ≈ $0.16 and repairs ≤ 10 % of the day's generation (п. 323); a closed day has its `day_passed`; a talk's role opens the
 * constructions (FIX-3 §7) and ends with a goodbye.
 */

function inspectDay(array $overrides = []): DayFact
{
    $d = $overrides + [
        'number' => 1, 'type' => 'scene', 'status' => 'open', 'scheduledOpen' => false, 'nextInLine' => false,
        'lessonStatus' => 'ready', 'failReason' => null, 'closed' => false, 'hasPassedEvent' => false,
        'generationUsd' => 0.08, 'voiceUsd' => 0.05, 'repairUsd' => null,
    ];

    return new DayFact(...$d);
}

const LEARNER_MALE = 'el:v3:TWut:s50';
const LEARNER_FEMALE = 'el:v3:Nhs7:s50';
const PARTNER_FEMALE = 'el:v3:4Nej:s50';

function inspectLine(array $overrides = []): LineFact
{
    $l = $overrides + [
        'sceneId' => 'S1', 'days' => [1], 'ref' => 'x1b', 'speaker' => 'learner', 'text' => 'I have a fever.',
        'expectedGender' => 'male', 'expectedVoice' => LEARNER_MALE, 'storedVoices' => [LEARNER_MALE => 'learner:male'], 'voicedText' => null,
    ];

    return new LineFact(...$l);
}

/** @return list<string> */
function checkCodes(array $issues): array
{
    return array_map(static fn (PlanIssue $i): string => $i->check, $issues);
}

describe('звук ≠ текст', function () {
    it('passes a card whose line reads as its sound says it', function () {
        $facts = new PlanFacts(cardSounds: [new CardSoundFact(1, 'C1', 'dialogue_answer', 'own_line', 'x1b', 'I have a fever.', 'I have a fever.')]);

        expect((new SoundTextMismatch)->find($facts))->toBe([]);
    });

    it('flags a card whose sound says another line than the card shows', function () {
        $facts = new PlanFacts(cardSounds: [new CardSoundFact(2, 'C1', 'recall_scenes', 'scenes.0.lines.2', 'x3b', 'Is parking included?', 'Are dogs allowed?')]);

        $issues = (new SoundTextMismatch)->find($facts);

        expect($issues)->toHaveCount(1)
            ->and($issues[0]->severity)->toBe(PlanIssue::ERROR)
            ->and($issues[0]->day)->toBe(2)
            ->and($issues[0]->place)->toBe('C1');
    });

    it('flags a card showing one more mark than the line it plays', function () {
        $facts = new PlanFacts(cardSounds: [new CardSoundFact(1, 'C1', 'dialogue_ask', 'own_line', 'x6b', 'I can come at 3 p.m..', 'I can come at 3 p.m.')]);

        expect((new SoundTextMismatch)->find($facts))->toHaveCount(1);
    });

    it('reads a gap on the card as whatever the sound says there, and nothing else', function () {
        $gapOk = new CardSoundFact(1, 'C1', 'word_in_line', 'line', 'x2', 'We have a basic plan and an ___.', 'We have a basic plan and an unlimited plan.');
        $gapWrong = new CardSoundFact(1, 'C2', 'word_in_line', 'line', 'x2', 'We have a gold plan and an ___.', 'We have a basic plan and an unlimited plan.');

        expect(checkCodes((new SoundTextMismatch)->find(new PlanFacts(cardSounds: [$gapOk]))))->toBe([])
            ->and(checkCodes((new SoundTextMismatch)->find(new PlanFacts(cardSounds: [$gapWrong]))))->toBe([SoundTextMismatch::CODE]);
    });

    it('flags a sound whose ref names no line of the lesson', function () {
        $facts = new PlanFacts(cardSounds: [new CardSoundFact(1, 'C1', 'listen_predict', 'own_line', 'x9b', 'Hello.', null)]);

        expect((new SoundTextMismatch)->find($facts))->toHaveCount(1);
    });

    it('flags a file bought for another text than the line has now, and stays silent when that text is unknown', function () {
        $drift = inspectLine(['voicedText' => 'I had a fever.']);
        $unknown = inspectLine(['voicedText' => null]);
        $same = inspectLine(['voicedText' => ' I have a  fever. ']);

        expect((new SoundTextMismatch)->find(new PlanFacts(lines: [$drift])))->toHaveCount(1)
            ->and((new SoundTextMismatch)->find(new PlanFacts(lines: [$unknown, $same])))->toBe([]);
    });
});

describe('голос не по полу', function () {
    it('flags a learner line bought only in the other gender than the profile says', function () {
        $line = inspectLine(['storedVoices' => [LEARNER_FEMALE => 'learner:female']]);

        $issues = (new VoiceGenderMismatch)->find(new PlanFacts(lines: [$line]));

        expect($issues)->toHaveCount(1)->and($issues[0]->severity)->toBe(PlanIssue::ERROR);
    });

    it('passes a line stored in the cast voice, even when an old voice is kept beside it', function () {
        $line = inspectLine(['storedVoices' => [LEARNER_MALE => 'learner:male', LEARNER_FEMALE => 'learner:female']]);

        expect((new VoiceGenderMismatch)->find(new PlanFacts(lines: [$line])))->toBe([]);
    });

    it('flags a role line of a talk said in a voice other than its scene gives the role', function () {
        $talk = new TalkFact('T1', 1, 'day', true, 'natural', 2, 1, [
            ['turn' => 0, 'voice' => PARTNER_FEMALE, 'expected' => PARTNER_FEMALE],
            ['turn' => 2, 'voice' => 'el:v3:Enjk:s50', 'expected' => PARTNER_FEMALE],
        ]);

        $issues = (new VoiceGenderMismatch)->find(new PlanFacts(talks: [$talk]));

        expect($issues)->toHaveCount(1)->and($issues[0]->place)->toBe('T1#2');
    });
});

describe('строка без звука', function () {
    it('flags a line with no file at all — the phone reads it', function () {
        $issues = (new LineWithoutSound)->find(new PlanFacts(lines: [inspectLine(['storedVoices' => []])]));

        expect($issues)->toHaveCount(1)->and($issues[0]->severity)->toBe(PlanIssue::WARNING);
    });

    it('flags a line of a language whose pack has no voice', function () {
        $issues = (new LineWithoutSound)->find(new PlanFacts(lines: [inspectLine(['expectedVoice' => null, 'storedVoices' => []])]));

        expect($issues)->toHaveCount(1)->and($issues[0]->message)->toContain('нет голоса');
    });

    it('passes a voiced line, and leaves a line bought in the wrong gender to the gender check', function () {
        $facts = new PlanFacts(lines: [inspectLine(), inspectLine(['ref' => 'x2b', 'storedVoices' => [LEARNER_FEMALE => 'learner:female']])]);

        expect((new LineWithoutSound)->find($facts))->toBe([]);
    });
});

describe('дни', function () {
    it('flags a failed day with its reason', function () {
        $issues = (new DayFailed)->find(new PlanFacts(days: [inspectDay(['lessonStatus' => 'failed', 'failReason' => 'fatal: line.ne_frame'])]));

        expect($issues)->toHaveCount(1)->and($issues[0]->message)->toContain('fatal: line.ne_frame');
    });

    it('flags a day the schedule opened whose lesson is not ready — and only that', function () {
        $check = new ScheduledDayNotReady;

        expect(checkCodes($check->find(new PlanFacts(days: [inspectDay(['scheduledOpen' => true, 'lessonStatus' => 'building'])]))))->toBe([ScheduledDayNotReady::CODE])
            ->and($check->find(new PlanFacts(days: [inspectDay(['scheduledOpen' => false, 'lessonStatus' => 'building'])])))->toBe([])
            ->and($check->find(new PlanFacts(days: [inspectDay(['scheduledOpen' => true, 'lessonStatus' => 'ready'])])))->toBe([])
            ->and($check->find(new PlanFacts(days: [inspectDay(['scheduledOpen' => true, 'lessonStatus' => 'failed'])])))->toBe([])
            ->and($check->find(new PlanFacts(days: [inspectDay(['scheduledOpen' => true, 'type' => 'review', 'lessonStatus' => null])])))->toBe([]);
    });

    it('flags a lesson being written for a day that is not next in line', function () {
        $check = new BuildingOutOfLine;

        expect(checkCodes($check->find(new PlanFacts(days: [inspectDay(['number' => 3, 'lessonStatus' => 'building'])]))))->toBe([BuildingOutOfLine::CODE])
            ->and(checkCodes($check->find(new PlanFacts(days: [inspectDay(['number' => 3, 'lessonStatus' => 'illustrating'])]))))->toBe([BuildingOutOfLine::CODE])
            ->and($check->find(new PlanFacts(days: [inspectDay(['nextInLine' => true, 'lessonStatus' => 'building'])])))->toBe([]);
    });

    it('flags a closed day with no day_passed line', function () {
        $check = new PassedWithoutSummary;

        expect(checkCodes($check->find(new PlanFacts(days: [inspectDay(['closed' => true])]))))->toBe([PassedWithoutSummary::CODE])
            ->and($check->find(new PlanFacts(days: [inspectDay(['closed' => true, 'hasPassedEvent' => true])])))->toBe([])
            ->and($check->find(new PlanFacts(days: [inspectDay(['closed' => false])])))->toBe([]);
    });
});

describe('деньги', function () {
    it('holds a day to $0.16 of generation and voice', function () {
        $check = new DayCostOverCanon(0.16, 0.10);

        expect(checkCodes($check->find(new PlanFacts(days: [inspectDay(['generationUsd' => 0.10, 'voiceUsd' => 0.07])]))))->toBe([DayCostOverCanon::CODE])
            ->and($check->find(new PlanFacts(days: [inspectDay(['generationUsd' => 0.08, 'voiceUsd' => 0.08])])))->toBe([])
            ->and($check->find(new PlanFacts(days: [inspectDay(['generationUsd' => null, 'voiceUsd' => 0.0])])))->toBe([]);
    });

    it('holds the repairs to 10 % of the lesson and the judge, and does not guess them', function () {
        $check = new DayCostOverCanon(0.16, 0.10);

        // generation $0.10 = lesson + judge $0.08 + repairs $0.02 → 25 %.
        $over = $check->find(new PlanFacts(days: [inspectDay(['generationUsd' => 0.10, 'voiceUsd' => 0.0, 'repairUsd' => 0.02])]));
        // $0.087 = $0.08 + $0.007 → 8.75 %: within the canon.
        $within = $check->find(new PlanFacts(days: [inspectDay(['generationUsd' => 0.087, 'voiceUsd' => 0.0, 'repairUsd' => 0.007])]));

        expect($over)->toHaveCount(1)->and($over[0]->message)->toContain('P2R')
            ->and($within)->toBe([])
            ->and($check->find(new PlanFacts(days: [inspectDay(['generationUsd' => 0.10, 'voiceUsd' => 0.0, 'repairUsd' => null])])))->toBe([]);
    });
});

describe('вызовы и разговоры', function () {
    it('flags a lost model call', function () {
        $facts = new PlanFacts(calls: [new CallFact('M1', 1, 'lesson', 'lost', 'cURL error 28'), new CallFact('M2', 1, 'lesson', 'completed', null)]);

        $issues = (new LostModelCall)->find($facts);

        expect($issues)->toHaveCount(1)->and($issues[0]->place)->toBe('M1')->and($issues[0]->severity)->toBe(PlanIssue::ERROR);
    });

    it('flags a talk whose role spoke and opened no construction', function () {
        $check = new TalkWithoutOpeners;

        expect(checkCodes($check->find(new PlanFacts(talks: [new TalkFact('T1', 1, 'day', true, 'natural', 4, 0, [])]))))->toBe([TalkWithoutOpeners::CODE])
            ->and($check->find(new PlanFacts(talks: [new TalkFact('T1', 1, 'day', true, 'natural', 4, 1, [])])))->toBe([])
            ->and($check->find(new PlanFacts(talks: [new TalkFact('T1', 1, 'day', false, null, 0, 0, [])])))->toBe([]);
    });

    it('flags a talk that ended by its limit, not with a goodbye', function () {
        $check = new TalkEndedByLimit;

        expect(checkCodes($check->find(new PlanFacts(talks: [new TalkFact('T1', 1, 'day', true, 'limit', 6, 2, [])]))))->toBe([TalkEndedByLimit::CODE])
            ->and($check->find(new PlanFacts(talks: [new TalkFact('T1', 1, 'day', true, 'natural', 6, 2, [])])))->toBe([]);
    });
});
