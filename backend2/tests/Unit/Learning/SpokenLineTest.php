<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Service\SpeechNormalization;
use App\Modules\Learning\Domain\Service\SpokenLine;
use App\Modules\Learning\Domain\ValueObject\SpeechGradingRules;
use App\Modules\Learning\Domain\ValueObject\SpokenCredit;

/**
 * ЗАЧЁТ ВСЕЙ ФРАЗЫ — наряд SPEECH-2, Ч.3. Замки на все три формы зачёта и на обе границы каждой.
 */
beforeEach(function () {
    $this->line = new SpokenLine();
    $this->rules = new SpeechGradingRules();
});

// ПРАВИЛО: Ч.3.1 — фраза на экране засчитывается покрытием ≥ read_aloud (0.9).
// ЛОВИТ: чтение вслух, засчитанное по половине текста. Текст стоит перед глазами, и «прочитал две
// трети» — это не прочитал; порог 0.7, унаследованный от вспоминания, здесь просто неверен.
it('reads a printed phrase at the read-aloud bar, both sides of it', function () {
    $line = 'Could you take a photo of us please';

    // Прочитано целиком — 1.0.
    $said = $this->line->judge($line, $line, [], true, $this->rules);
    expect($said->credit)->toBe(SpokenCredit::Correct)
        ->and($said->threshold)->toBe('read_aloud');

    // Четыре слова из шести (артикль не считается вовсе) — ниже 0.9 и выше пола «почти».
    $half = $this->line->judge('could you take photo', $line, [], true, $this->rules);
    expect($half->credit)->toBe(SpokenCredit::Almost)
        ->and($half->missing)->toBe(['of', 'us', 'please']);
});

// ПРАВИЛО: Ч.3.1 — артикли, знаки и регистр не считаются; одно пропущенное слово-связка прощается.
// ЛОВИТ: «Не то» на съеденном артикле. Распознаватель ест безударные слова, и вердикт, который
// винит в этом человека, — это микрофонная ошибка, записанная в память ученика.
it('never says «не то» about an article or a single eaten connector', function () {
    $line = 'I would like to open a bank account';

    expect($this->line->judge('i would like to open bank account', $line, [], true, $this->rules)->credit)
        ->toBe(SpokenCredit::Correct)
        // …и один съеденный предлог — тоже: поблажка ровно на одно слово-связку.
        ->and($this->line->judge('i would like open a bank account', $line, [], true, $this->rules)->credit)
        ->toBe(SpokenCredit::Correct)
        // …но не на смысловое слово: «bank» прощению не подлежит.
        ->and($this->line->judge('i would like to open a account', $line, [], true, $this->rules)->credit)
        ->not->toBe(SpokenCredit::Correct);
});

// ПРАВИЛО: Ч.3.2 — без текста зачёт = ключ ОБЯЗАТЕЛЕН И остальные слова реплики ≥ 0.6.
// ЛОВИТ: возврат к «ключ есть — верно» (движок 08.09 закрывался на первом узнанном ключевом слове
// и ставил «верно» над недослушанной фразой) и обратную ошибку — зачёт без ключа.
it('asks a recalled reply for its key and for the rest of itself', function () {
    $line = "Yes, I'm looking for a place to rent for long-term living.";
    $keys = ['a place to rent'];

    // Ключ и почти ничего больше — не ответ на карточку, которая просит реплику.
    $keyOnly = $this->line->judge('I want a place to rent', $line, $keys, false, $this->rules);
    expect($keyOnly->credit)->not->toBe(SpokenCredit::Correct)
        ->and($keyOnly->threshold)->toBe('key_and_rest');

    // Реплика без ключа — «не то», и «почти» здесь было бы неправдой: сказано другое.
    expect($this->line->judge('yes i am looking for long term living', $line, $keys, false, $this->rules)->credit)
        ->toBe(SpokenCredit::Wrong);

    // Реплика целиком — зачёт.
    expect($this->line->judge("Yes I'm looking for a place to rent for long-term living", $line, $keys, false, $this->rules)->credit)
        ->toBe(SpokenCredit::Correct);
});

// ПРАВИЛО: Ч.3.2 — упрощённая форма это другой способ сказать ВСЮ реплику, а не её кусок.
// ЛОВИТ: правило «ключ + остальное», применённое к перефразу — оно требовало бы сказать реплику
// дважды и отменило бы канон GEN-1 Y4 боком.
it('takes a re-phrasing as the whole reply and a fragment as half of one', function () {
    $line = 'It hurts in my lower back.';

    // Перефраз: в реплике сплошным куском не стоит — значит, он и есть реплика.
    expect($this->line->judge('my back hurts', $line, ['my back hurts'], false, $this->rules)->credit)
        ->toBe(SpokenCredit::Correct)
        // Фрагмент: стоит сплошным куском — значит, спрашивается и остальное.
        ->and($this->line->judge('in my lower back', $line, ['in my lower back'], false, $this->rules)->credit)
        ->not->toBe(SpokenCredit::Correct);
});

// ПРАВИЛО: Ч.3.4 / фикс DAY-GATE-1 — реплика БЕЗ ключа судится целиком.
// ЛОВИТ: возврат к списку из одних упрощённых форм. Владелец 08.09 сказал реплику слово в слово и
// получил «Не то», потому что «что засчитывается» не содержало самой реплики.
it('asks a keyless reply for itself, entire', function () {
    $line = 'Sorry, could you repeat that?';

    expect($this->line->judge('sorry could you repeat that', $line, [], false, $this->rules)->threshold)
        ->toBe('whole_line')
        ->and($this->line->judge('sorry could you repeat that', $line, [], false, $this->rules)->credit)
        ->toBe(SpokenCredit::Correct)
        ->and($this->line->judge('sorry', $line, [], false, $this->rules)->credit)
        ->toBe(SpokenCredit::Wrong);
});

// ПРАВИЛО: Ч.4.2 — распознанная аббревиатура приводится к канону до всякого счёта.
// ЛОВИТ: промах на ровном месте. `SFSpeechRecognizer` пишет «sequel» за SQL и «a p i» за API —
// это не ошибка человека, а то, как звучит буква, и штраф за неё уходит в append-only журнал.
it('hears an abbreviation spelled out as the abbreviation', function () {
    expect($this->line->judge('I write sequel queries every day', 'I write SQL queries every day', [], true, $this->rules)->credit)
        ->toBe(SpokenCredit::Correct)
        ->and($this->line->judge('we call the a p i from the client', 'We call the API from the client', [], true, $this->rules)->credit)
        ->toBe(SpokenCredit::Correct)
        ->and($this->line->judge('h r will send the offer', 'HR will send the offer', [], true, $this->rules)->credit)
        ->toBe(SpokenCredit::Correct);
});

// ПРАВИЛО: Ч.4.2 — замена идёт от самой длинной формы.
// ЛОВИТ: «a p i», съеденное по кускам («a i» → ai, потом осиротевшее «p»). Порядок замены здесь и
// есть корректность, и ошибиться в нём можно ровно один раз — молча.
it('eats the longest spelled-out form first', function () {
    expect((new SpeechNormalization())->apply('the a p i is ready'))->toBe('the api is ready');
});

// ПРАВИЛО: Ч.3.3 — пороги в конфиге, а не в коде.
// ЛОВИТ: числа, зашитые в грейдер. Их читает ещё и телефон (контракт сессии), и порог, который
// сдвинулся только на одной стороне, — это «Не то» над зачтённым ответом.
it('judges by the rules it is given, not by numbers of its own', function () {
    $line = 'Could you take a photo of us please';
    $lenient = new SpeechGradingRules(readAloud: 0.5);

    expect($this->line->judge('could you take photo', $line, [], true, $this->rules)->credit)
        ->toBe(SpokenCredit::Almost)
        ->and($this->line->judge('could you take photo', $line, [], true, $lenient)->credit)
        ->toBe(SpokenCredit::Correct);
});
