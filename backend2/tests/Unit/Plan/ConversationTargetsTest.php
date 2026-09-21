<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Service\ConversationTargets;
use App\Modules\Plan\Domain\Service\IntentClause;
use App\Modules\Plan\Domain\Service\NativeStrings;
use App\Modules\Plan\Domain\ValueObject\ConversationCheckpoint;
use App\Modules\Plan\Domain\ValueObject\ConversationPhrase;
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
        $phrases[] = new ConversationPhrase($id, "p{$i}", "key {$i}", "Phrase {$id} {$i}.", "Фраза {$id} {$i}.");
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
 * (`TalkTexts.clause`) moves to the server: the chip read «Скажи, что У моего сына температура.» (CLIENT-CONV-1a §5 п. 10).
 * Catches a capital glued inside a sentence, a full stop inside it, an abbreviation lower-cased, and a question mark or
 * an ellipsis eaten.
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
        expect($ru->talkTitle($role))->toBe($title, $role);
    }

    expect((new NativeStrings('uk'))->talkTitle('Лікар'))->toBe('Поговори з лікарем')
        ->and((new NativeStrings('uk'))->talkTitle('Співробітник банку'))->toBe('Поговори зі співробітником банку')
        ->and((new NativeStrings('en'))->talkTitle('Doctor'))->toBe('Talk to the doctor')
        ->and((new NativeStrings('en'))->talkTitle('HR manager'))->toBe('Talk to the HR manager');
});
