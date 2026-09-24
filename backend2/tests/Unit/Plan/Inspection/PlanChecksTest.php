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

/** A line as the client is given it: the card's text beside the text of the file its sound id names. */
function servedLine(string $cardText, ?string $soundText, ?string $fileRef = 'S1:x1b', string $kind = 'dialogue_answer', int $day = 1): CardSoundFact
{
    return new CardSoundFact($day, 'cards', 'C1', $kind, 'payload.own_line', '01AUDIO', $fileRef, $cardText, $soundText);
}

describe('звук ≠ текст', function () {
    it('passes a line whose sound says it', function () {
        expect((new SoundTextMismatch)->find(new PlanFacts(cardSounds: [servedLine('I have a fever.', 'I have a fever.')])))->toBe([]);
    });

    it('flags «Вспомнить» playing another scene\'s line (the phone, 2DX8QC, «С тренером»)', function () {
        $issue = (new SoundTextMismatch)->find(new PlanFacts(cardSounds: [
            servedLine('I have some shoulder pain.', 'Yes, this is my first visit.', 'RECEPTION:x3b', 'recall_scenes', 3),
        ]));

        expect($issue)->toHaveCount(1)
            ->and($issue[0]->severity)->toBe(PlanIssue::ERROR)
            ->and($issue[0]->day)->toBe(3)
            ->and($issue[0]->place)->toBe('C1')
            ->and($issue[0]->detail['file_ref'])->toBe('RECEPTION:x3b');
    });

    it('compares without case and without the closing mark', function () {
        $facts = new PlanFacts(cardSounds: [
            servedLine('I can come at 3 p.m..', 'I can come at 3 p.m.'),
            servedLine('Do you have a day pass', 'do you have a day pass?'),
            servedLine('  Where are  the rooms?! ', 'Where are the rooms'),
        ]);

        expect((new SoundTextMismatch)->find($facts))->toBe([]);
    });

    it('reads a gap on the card as whatever the sound says there, and nothing else', function () {
        $ok = servedLine('We have a basic plan and an ___.', 'We have a basic plan and an unlimited plan.', kind: 'word_in_line');
        $wrong = servedLine('We have a gold plan and an ___.', 'We have a basic plan and an unlimited plan.', kind: 'word_in_line');

        expect(checkCodes((new SoundTextMismatch)->find(new PlanFacts(cardSounds: [$ok]))))->toBe([])
            ->and(checkCodes((new SoundTextMismatch)->find(new PlanFacts(cardSounds: [$wrong]))))->toBe([SoundTextMismatch::CODE]);
    });

    it('holds an option to its phrase: its sound must say it within the phrase', function () {
        $option = static fn (string $text, string $sound): CardSoundFact => new CardSoundFact(1, 'cards', 'C1', 'phrase_slot', 'payload.options.2', '01AUDIO', 'S1:p7', $text, $sound, fragment: true);

        expect((new SoundTextMismatch)->find(new PlanFacts(cardSounds: [$option('by card', 'Can I pay by card?')])))->toBe([])
            ->and(checkCodes((new SoundTextMismatch)->find(new PlanFacts(cardSounds: [$option('in cash', 'Can I pay by card?')]))))->toBe([SoundTextMismatch::CODE])
            ->and(checkCodes((new SoundTextMismatch)->find(new PlanFacts(cardSounds: [$option('card', 'Can I pay by cards?')]))))->toBe([SoundTextMismatch::CODE]);
    });

    it('flags a sound id that is no file of the plan, and says nothing of a file whose text is unknown', function () {
        expect((new SoundTextMismatch)->find(new PlanFacts(cardSounds: [servedLine('Hello.', null, null)])))->toHaveCount(1)
            ->and((new SoundTextMismatch)->find(new PlanFacts(cardSounds: [servedLine('Hello.', null)])))->toBe([]);
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
    it('holds a day to $0.16 with room: a warning from 125 % ($0.20), an error from 150 % ($0.24)', function () {
        $check = new DayCostOverCanon(0.16, 0.10, 1.25, 1.5);
        $severity = static fn (float $gen, float $voice): array => array_map(
            static fn (PlanIssue $i): string => $i->severity,
            $check->find(new PlanFacts(days: [inspectDay(['generationUsd' => $gen, 'voiceUsd' => $voice])])),
        );

        expect($severity(0.1088, 0.056))->toBe([])            // $0.1648 — the gym's day 1: no finding
            ->and($severity(0.12, 0.07))->toBe([])             // $0.19
            ->and($severity(0.13, 0.07))->toBe([PlanIssue::WARNING]) // $0.20
            ->and($severity(0.15, 0.08))->toBe([PlanIssue::WARNING]) // $0.23
            ->and($severity(0.16, 0.08))->toBe([PlanIssue::ERROR])   // $0.24
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

    it('does not judge a talk begun before the openings were recorded', function () {
        $old = new TalkFact('T1', 1, 'day', true, 'natural', 4, 0, [], openersRecorded: false);

        expect((new TalkWithoutOpeners)->find(new PlanFacts(talks: [$old])))->toBe([]);
    });

    it('flags a talk that ended by its limit, not with a goodbye', function () {
        $check = new TalkEndedByLimit;

        expect(checkCodes($check->find(new PlanFacts(talks: [new TalkFact('T1', 1, 'day', true, 'limit', 6, 2, [])]))))->toBe([TalkEndedByLimit::CODE])
            ->and($check->find(new PlanFacts(talks: [new TalkFact('T1', 1, 'day', true, 'natural', 6, 2, [])])))->toBe([]);
    });

    // Canon (наряд FIX-4 §4): the moves ran out before the scenes did — the role said goodbye (`natural`), and it is a limit.
    it('flags a rehearsal whose moves ran out before its scenes, though the role said goodbye', function () {
        $check = new TalkEndedByLimit;
        $issues = $check->find(new PlanFacts(talks: [new TalkFact('T1', 5, 'rehearsal', true, 'natural', 11, 7, [], endedByLimit: true)]));

        expect(checkCodes($issues))->toBe([TalkEndedByLimit::CODE])
            ->and($issues[0]->message)->toBe('Разговор дня 5: ходы кончились раньше сцен — роль попрощалась по лимиту')
            ->and($check->find(new PlanFacts(talks: [new TalkFact('T1', 5, 'rehearsal', true, 'natural', 11, 7, [], endedByLimit: false)])))->toBe([]);
    });
});
