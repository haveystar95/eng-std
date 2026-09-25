<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Service\PartnerVoiceRota;
use App\Modules\Shared\Domain\Service\VoiceCatalog;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;
use App\Modules\Shared\Domain\ValueObject\VoiceRole;

/**
 * THE SECOND PARTNER VOICE (наряд FIX-4c §1): the pack has two partner voices of each gender, a scene is cast once — when
 * its lesson is accepted — and the scenes of one gender alternate in the plan's order, so two neighbouring scenes of one
 * gender are never one person.
 */

/**
 * A plan's roles cast one by one, as their lessons are accepted, in the plan's order.
 *
 * @param  array<int, VoiceGender>  $roles  scene order → the role's gender
 * @param  array{female: list<string>, male: list<string>}  $voices
 * @return list<string|null>
 */
function pvrCast(array $roles, array $voices = ['female' => ['F1', 'F2'], 'male' => ['M1', 'M2']]): array
{
    $cast = [];
    foreach ($roles as $order => $gender) {
        $cast[] = ['order' => $order, 'gender' => $gender, 'voice' => PartnerVoiceRota::pick($order, $gender, $cast, $voices[$gender->value])];
    }

    return array_column($cast, 'voice');
}

// Canon (§1): «Тест на канон: план с ролями Ж, Ж, М, Ж → голоса F1, F2, M1, F1». CATCHES one voice for two women in a row
// (the registrar and the doctor of the rehearsal), a man in the middle that resets or steals the women's turn, and a rota
// kept across genders.
it('casts a plan of roles F, F, M, F as F1, F2, M1, F1', function () {
    expect(pvrCast([1 => VoiceGender::Female, 2 => VoiceGender::Female, 3 => VoiceGender::Male, 4 => VoiceGender::Female]))
        ->toBe(['F1', 'F2', 'M1', 'F1'])
        ->and(pvrCast([1 => VoiceGender::Male, 2 => VoiceGender::Male, 3 => VoiceGender::Male, 4 => VoiceGender::Female]))
        ->toBe(['M1', 'M2', 'M1', 'F1']);
});

// Canon (§1): «сцены одного пола в порядке плана чередуются между голосом 1 и 2». CATCHES a scene cast off a LATER neighbour,
// a stale id taken for voice 1, and a pack with one voice that casts nothing.
it('takes the other voice of the nearest earlier scene of its gender — voice 1 when there is none, or when that one is gone', function () {
    $earlier = [
        ['order' => 1, 'gender' => VoiceGender::Female, 'voice' => 'F1'],
        ['order' => 2, 'gender' => VoiceGender::Female, 'voice' => 'F2'],
        ['order' => 5, 'gender' => VoiceGender::Female, 'voice' => 'F1'],
    ];

    expect(PartnerVoiceRota::pick(3, VoiceGender::Female, $earlier, ['F1', 'F2']))->toBe('F1')
        ->and(PartnerVoiceRota::pick(3, VoiceGender::Male, $earlier, ['M1', 'M2']))->toBe('M1')
        ->and(PartnerVoiceRota::pick(3, VoiceGender::Female, [['order' => 2, 'gender' => VoiceGender::Female, 'voice' => 'OLD']], ['F1', 'F2']))->toBe('F1')
        ->and(PartnerVoiceRota::pick(3, VoiceGender::Female, $earlier, ['F1']))->toBe('F1')
        ->and(PartnerVoiceRota::pick(3, VoiceGender::Female, $earlier, []))->toBeNull();
});

/** @return array<string, mixed> a pack row */
function pvrRow(string $voice): array
{
    return ['provider' => 'elevenlabs', 'model' => 'eleven_v3_conversational', 'voice' => $voice, 'stability' => 0.5];
}

// Canon (§1): «голос сцены хранится в plan_scenes.partner_voice_id; все реплики сцены звучат голосом этой сцены». CATCHES a
// second voice the catalog cannot find, a scene whose voice left the pack re-voiced as voice 1, and the learner's voice
// swapped by the partner's id.
it('finds a scene\'s partner voice by its id — the second row, or the first row said with an id the pack no longer names', function () {
    $catalog = new VoiceCatalog(['en' => [
        'partner' => ['female' => pvrRow('F1'), 'female_2' => pvrRow('F2'), 'male' => pvrRow('M1')],
        'learner' => ['male' => pvrRow('L1')],
    ]]);

    expect($catalog->partnerVoices('en', VoiceGender::Female))->toBe(['F1', 'F2'])
        ->and($catalog->partnerVoices('en', VoiceGender::Male))->toBe(['M1'])
        ->and($catalog->partnerVoices('pl', VoiceGender::Female))->toBe([])
        ->and($catalog->forLanguage('en', VoiceRole::Partner, VoiceGender::Female)?->voice)->toBe('F1')
        ->and($catalog->forLanguage('en', VoiceRole::Partner, VoiceGender::Female, 'F2')?->voice)->toBe('F2')
        ->and($catalog->forLanguage('en', VoiceRole::Partner, VoiceGender::Female, 'GONE')?->key())->toBe('elevenlabs:eleven_v3_conversational:GONE')
        ->and($catalog->forLanguage('en', VoiceRole::Learner, VoiceGender::Male, 'F2')?->voice)->toBe('L1')
        ->and($catalog->forLanguage('pl', VoiceRole::Partner, VoiceGender::Female, 'F2'))->toBeNull();
});
