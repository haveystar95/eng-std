<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Lesson;

use App\Modules\Plan\Domain\Exception\ModelAnswerOffSchema;
use App\Modules\Plan\Domain\Service\FrameText;
use App\Modules\Plan\Domain\ValueObject\ExchangeKind;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/**
 * The model's JSON → a {@see Lesson} (`lesson_day.v4.10`; its rollback `v4.7` answers the same schema).
 * Strict about SHAPE only: a missing key, a wrong type, an unknown kind or speaker, an empty required
 * string is a reply that is not the requested schema, and that is the model's refusal, not a finding
 * ({@see ModelAnswerOffSchema}).
 * Everything about CONTENT — counts, frames, fillers, keys, checks, listening — is the validator's,
 * and the validator runs on the parsed lesson.
 *
 * THREE things are put right on the way, and only three. A frame — `frame_target` and `frame_native` — and a filler's
 * native text lose the space before the mark they end with (доработка GEN-3; both sides since наряд BACK-TAILS-1 §3.1,
 * {@see FrameText::withEndMarkClosed()}): «I work ___ .» is read as «I work ___.» by the validator, the seam judge, the
 * repair and every card, whatever the stored answer says. A line, a frame or a filler that ends in TWO full stops
 * keeps one ({@see FrameText::withoutDoubledStop()}, хвост ROADMAP, наряд CONV-1): «I can come at 3 p.m..» is the
 * abbreviation's stop plus the sentence's, and the phone, the voice and the judge all read it as written. And a
 * READING — every `pronunciation_native` the parser reads: a frame's, a filler's, a word's, a learner line's, in a
 * whole lesson and in a repaired card alike — has the Latin letters drawn inside a Cyrillic word put back into
 * Cyrillic, a Latin acute vowel («á») as the Cyrillic vowel with the combining stress mark ({@see self::reading()},
 * наряд LANG-1), and a letter of another Cyrillic alphabet as the letter it stands for («аҗута́» → «ажута́», наряд
 * LANG-1b §10).
 */
final class LessonParser
{
    /**
     * The Latin letters a Cyrillic word may be written with by mistake, each with the Cyrillic letter it is drawn
     * like — one shape in two tables of the alphabet (наряд LANG-1): lower-case a e o c p x y k, capital A E O C P X
     * Y B H K M T.
     */
    private const CYRILLIC_TWINS = [
        'a' => 'а', 'e' => 'е', 'o' => 'о', 'c' => 'с', 'p' => 'р', 'x' => 'х', 'y' => 'у', 'k' => 'к',
        'A' => 'А', 'E' => 'Е', 'O' => 'О', 'C' => 'С', 'P' => 'Р', 'X' => 'Х', 'Y' => 'У',
        'B' => 'В', 'H' => 'Н', 'K' => 'К', 'M' => 'М', 'T' => 'Т',
    ];

    /**
     * The letters of OTHER Cyrillic alphabets a Cyrillic reading may be written with by mistake — Kazakh, Tatar, Bashkir,
     * Mongolian letters no learner's language of the plan has — each with the letter of the readings it stands for (наряд
     * LANG-1b §10: the owner's ru→ro day read «a ajuta» as «а аҗута́»): җ→ж, ғ→г, қ→к, ә→э, ү→у, ұ→у, ң→н, һ→х, ө→о, and
     * their capitals.
     */
    private const CYRILLIC_ALIENS = [
        'җ' => 'ж', 'ғ' => 'г', 'қ' => 'к', 'ә' => 'э', 'ү' => 'у', 'ұ' => 'у', 'ң' => 'н', 'һ' => 'х', 'ө' => 'о',
        'Җ' => 'Ж', 'Ғ' => 'Г', 'Қ' => 'К', 'Ә' => 'Э', 'Ү' => 'У', 'Ұ' => 'У', 'Ң' => 'Н', 'Һ' => 'Х', 'Ө' => 'О',
    ];

    /**
     * A Latin vowel written with its acute in one character — the stress of a Cyrillic reading drawn from the Latin
     * table («лекáжа») — as its canonical decomposition read through {@see self::CYRILLIC_TWINS}: the Cyrillic vowel,
     * then the combining acute U+0301 the readings mark stress with («лека́жа»).
     */
    private const CYRILLIC_STRESSED = [
        'á' => "а\u{0301}", 'é' => "е\u{0301}", 'ó' => "о\u{0301}", 'ý' => "у\u{0301}",
        'Á' => "А\u{0301}", 'É' => "Е\u{0301}", 'Ó' => "О\u{0301}", 'Ý' => "У\u{0301}",
    ];

    /** @param array<string, mixed> $payload */
    public function parse(array $payload): Lesson
    {
        $topic = $this->object($payload, 'topic');
        $role = $this->object($payload, 'learner_role');

        $exchanges = [];
        foreach ($this->list($payload, 'dialogue') as $index => $exchange) {
            $exchanges[] = $this->exchange($this->objectAt($exchange, "dialogue[{$index}]"), "dialogue[{$index}]");
        }

        $phrases = [];
        foreach ($this->list($payload, 'phrases') as $index => $phrase) {
            $phrases[] = $this->phrase($this->objectAt($phrase, "phrases[{$index}]"), "phrases[{$index}]");
        }

        $listening = [];
        foreach ($this->list($this->object($payload, 'listening'), 'questions') as $index => $question) {
            $listening[] = $this->listeningQuestion($this->objectAt($question, "listening.questions[{$index}]"), "listening.questions[{$index}]");
        }

        $vocabulary = [];
        foreach ($this->list($payload, 'vocabulary') as $index => $item) {
            $vocabulary[] = $this->vocabularyItem($this->objectAt($item, "vocabulary[{$index}]"), "vocabulary[{$index}]");
        }

        return new Lesson(
            titleTarget: $this->stringOrEmpty($topic, 'title_target'),
            titleNative: $this->stringOrEmpty($topic, 'title_native'),
            descriptionTarget: $this->stringOrEmpty($topic, 'description_target'),
            descriptionNative: $this->stringOrEmpty($topic, 'description_native'),
            learnerRoleTarget: $this->stringOrEmpty($role, 'role_target'),
            learnerRoleNative: $this->stringOrEmpty($role, 'role_native'),
            // The schema holds it to female|male; an odd word is still no reason to refuse a paid lesson —
            // the scene speaks with the default cast.
            roleGender: VoiceGender::tryFromAny($payload['role_gender'] ?? null),
            exchanges: $exchanges,
            phrases: $phrases,
            listening: $listening,
            vocabulary: $vocabulary,
        );
    }

    /**
     * One card of a lesson on its own — what a repair answers with: a frame, a whole exchange, a learner line,
     * an exchange's check, a listening question or a word, held to the same shape as inside a whole lesson.
     *
     * @param  'frame'|'exchange'|'line'|'check'|'listening'|'term'  $kind
     * @param  array<string, mixed>  $row
     */
    public function card(string $kind, array $row): Phrase|Exchange|Message|ExchangeCheck|ListeningQuestion|VocabularyItem
    {
        return match ($kind) {
            LessonCard::FRAME => $this->phrase($row, 'card'),
            LessonCard::EXCHANGE => $this->exchange($row, 'card'),
            LessonCard::LINE => $this->learnerLine($row),
            LessonCard::CHECK => $this->check($row, 'card'),
            LessonCard::LISTENING => $this->listeningQuestion($row, 'card'),
            LessonCard::TERM => $this->vocabularyItem($row, 'card'),
        };
    }

    /**
     * The frame a repaired exchange comes with (P2R v1.1, `frame_update`): absent or null — none; anything else is
     * held to a frame's shape.
     */
    public function frameUpdate(mixed $raw): ?Phrase
    {
        if ($raw === null) {
            return null;
        }
        if (! is_array($raw)) {
            throw ModelAnswerOffSchema::at('frame_update', 'not an object');
        }

        /** @var array<string, mixed> $raw */
        return $this->phrase($raw, 'frame_update');
    }

    /** @param array<string, mixed> $row */
    private function vocabularyItem(array $row, string $path): VocabularyItem
    {
        $kind = $this->string($row, 'kind', $path);
        if (! in_array($kind, [VocabularyItem::KIND_WORD, VocabularyItem::KIND_CHUNK], true)) {
            throw ModelAnswerOffSchema::at("{$path}.kind", "unknown kind «{$kind}»");
        }

        return new VocabularyItem(
            id: $this->string($row, 'id', $path),
            termTarget: $this->string($row, 'term_target', $path),
            translationNative: $this->string($row, 'translation_native', $path),
            pronunciationNative: self::reading($this->stringOrEmpty($row, 'pronunciation_native')),
            definitionTarget: $this->stringOrEmpty($row, 'definition_target'),
            kind: $kind,
            imagePrompt: $this->nullableString($row, 'image_prompt'),
            usedIn: $this->stringList($row, 'used_in'),
        );
    }

    /** @param array<string, mixed> $row */
    private function learnerLine(array $row): Message
    {
        $message = $this->message($row, 'card');
        if (! $message->isLearner()) {
            throw ModelAnswerOffSchema::at('card.speaker', 'a learner line is spoken by B');
        }

        return $message;
    }

    /** @param array<string, mixed> $row */
    private function exchange(array $row, string $path): Exchange
    {
        $step = $row['step'] ?? null;
        if (! is_int($step)) {
            throw ModelAnswerOffSchema::at("{$path}.step", 'not an integer');
        }
        $kind = ExchangeKind::tryFrom($this->string($row, 'kind', $path));
        if ($kind === null) {
            throw ModelAnswerOffSchema::at("{$path}.kind", 'not answer, ask or rescue');
        }
        $initiator = $this->speaker($row, 'initiator', $path);

        $messages = [];
        foreach ($this->list($row, 'messages') as $index => $message) {
            $messages[] = $this->message($this->objectAt($message, "{$path}.messages[{$index}]"), "{$path}.messages[{$index}]");
        }

        return new Exchange(
            step: $step,
            kind: $kind,
            initiator: $initiator,
            messages: $messages,
            check: $this->check($this->object($row, 'check'), "{$path}.check"),
        );
    }

    /** @param array<string, mixed> $row */
    private function check(array $row, string $path): ExchangeCheck
    {
        $options = [];
        foreach ($this->list($row, 'options') as $index => $option) {
            $o = $this->objectAt($option, "{$path}.options[{$index}]");
            $options[] = new CheckOption($this->stringOrEmpty($o, 'text_target'), $this->stringOrEmpty($o, 'text_native'));
        }

        return new ExchangeCheck(
            textTarget: $this->stringOrEmpty($row, 'text_target'),
            textNative: $this->stringOrEmpty($row, 'text_native'),
            options: $options,
            correctOptionIndex: $this->int($row, 'correct_option_index', $path),
            explanationNative: $this->stringOrEmpty($row, 'explanation_native'),
        );
    }

    /** @param array<string, mixed> $row */
    private function message(array $row, string $path): Message
    {
        $speaker = $this->speaker($row, 'speaker', $path);
        $text = FrameText::withoutDoubledStop($this->string($row, 'text_target', $path));
        $native = FrameText::withoutDoubledStop($this->string($row, 'text_native', $path));
        if ($speaker === Message::SPEAKER_PARTNER) {
            return new Message($speaker, $this->stringOrEmpty($row, 'role_target'), $this->stringOrEmpty($row, 'role_native'), $text, $native);
        }

        return new Message(
            speaker: $speaker,
            roleTarget: $this->stringOrEmpty($row, 'role_target'),
            roleNative: $this->stringOrEmpty($row, 'role_native'),
            textTarget: $text,
            textNative: $native,
            pronunciationNative: self::reading($this->nullableString($row, 'pronunciation_native')),
            speakingKey: $this->nullableString($row, 'speaking_key'),
            simplifiedVariants: $this->stringList($row, 'simplified_variants'),
            phraseId: $this->nullableString($row, 'phrase_id'),
            filler: $this->nullableString($row, 'filler'),
        );
    }

    /** @param array<string, mixed> $row */
    private function phrase(array $row, string $path): Phrase
    {
        $kind = ExchangeKind::tryFrom($this->string($row, 'kind', $path));
        if ($kind === null || ! $kind->takesFrame()) {
            throw ModelAnswerOffSchema::at("{$path}.kind", 'not answer or ask');
        }

        $slot = null;
        if (($row['slot'] ?? null) !== null) {
            $raw = $this->object($row, 'slot');
            $fillers = [];
            foreach ($this->list($raw, 'fillers') as $index => $filler) {
                $f = $this->objectAt($filler, "{$path}.slot.fillers[{$index}]");
                $inDialogue = $f['in_dialogue'] ?? null;
                if (! is_bool($inDialogue)) {
                    throw ModelAnswerOffSchema::at("{$path}.slot.fillers[{$index}].in_dialogue", 'not a boolean');
                }
                $fillers[] = new Filler(
                    target: $this->string($f, 'target', "{$path}.slot.fillers[{$index}]"),
                    native: FrameText::withoutDoubledStop(FrameText::withEndMarkClosed($this->stringOrEmpty($f, 'native'))),
                    pronunciationNative: self::reading($this->stringOrEmpty($f, 'pronunciation_native')),
                    inDialogue: $inDialogue,
                );
            }
            $slot = new Slot($this->stringOrEmpty($raw, 'hint_native'), $fillers);
        }

        return new Phrase(
            id: $this->string($row, 'id', $path),
            kind: $kind,
            frameTarget: FrameText::withoutDoubledStop(FrameText::withEndMarkClosed($this->string($row, 'frame_target', $path))),
            frameNative: FrameText::withoutDoubledStop(FrameText::withEndMarkClosed($this->stringOrEmpty($row, 'frame_native'))),
            pronunciationNative: self::reading($this->stringOrEmpty($row, 'pronunciation_native')),
            slot: $slot,
        );
    }

    /** @param array<string, mixed> $row */
    private function listeningQuestion(array $row, string $path): ListeningQuestion
    {
        return new ListeningQuestion(
            textNative: $this->string($row, 'text_native', $path),
            optionsNative: $this->stringList($row, 'options_native'),
            correctOptionIndex: $this->int($row, 'correct_option_index', $path),
            explanationNative: $this->stringOrEmpty($row, 'explanation_native'),
        );
    }

    /**
     * A READING WITH ITS CYRILLIC WORDS WRITTEN IN CYRILLIC (наряд LANG-1, валидатор): in every run of letters (and
     * their combining marks) that holds at least one Cyrillic letter, a Latin letter drawn like a Cyrillic one becomes
     * that Cyrillic letter ({@see self::CYRILLIC_TWINS}), and a Latin vowel with its acute becomes the Cyrillic vowel with
     * the combining acute U+0301 ({@see self::CYRILLIC_STRESSED}): «до лекáжа» → «до лека́жа», «___ ми пасуe» → «___ ми
     * пасуе», «нюмэро дё телефoн» → «нюмэро дё телефон». A letter of ANOTHER Cyrillic alphabet becomes the letter of the
     * readings it stands for ({@see self::CYRILLIC_ALIENS}, наряд LANG-1b §10): «а аҗута́» → «а ажута́» — the owner's ru→ro
     * day, three readings of «a ajuta» with the Tatar «җ»; the letter is Cyrillic, so the fatal `foreign_script` let it
     * through and only the warning `pronunciation.script` counted it, and the learner got a letter they cannot read.
     * Nothing else changes.
     *
     * Why the parser mends it rather than the repair: `pronunciation.foreign_script` is FATAL (наряд BACK-TAILS-1 §3.2),
     * and these are not letters of another writing — they are the same letter taken from the other table, drawn as the
     * learner already reads it; only the code point is wrong. They were every one of the
     * seven `foreign_script` findings of the LANG-1 scouting days (ru→pl: the Polish «pasuje» leaving its «e», the stress
     * written with a Latin «á»; ru→fr: «телефoн») and four of the eleven of the ru→en days replayed
     * (`docs/research/lang-1/baseline.md`) — a paid repair, or a failed day, for a letter a machine puts back.
     *
     * What it does NOT touch. A run with no Cyrillic letter in it stays as written: the Latin reading of a learner who
     * reads Latin letters, and a Latin word among Cyrillic ones («SMS-ку» keeps its «SMS»; «X-рэй» its «X» — the hyphen
     * ends a run) — those stay what the rule finds. A letter with no Cyrillic twin stays too («пасуje» keeps its «j»).
     * Letters of OTHER writings — Georgian «პლ», Armenian «ֆ» and «պր», the Greek «θ» of the ru→en days — are not
     * mended by any table and stay real findings of `foreign_script` for the repair to rewrite. Only the reading is
     * read so: `text_target`, `text_native` and every other field are the model's as written. And a reading the pattern
     * cannot read at all — bytes that are not UTF-8, which a hand-built payload can carry though a decoded JSON cannot —
     * is kept as written, never emptied: the validator judges the model's text, and a card never loses its reading to
     * the mending of it.
     *
     * @return ($reading is null ? null : string)
     */
    private static function reading(?string $reading): ?string
    {
        if ($reading === null) {
            return null;
        }

        return preg_replace_callback(
            '/[\p{L}\p{M}]+/u',
            static fn (array $run): string => preg_match('/\p{Cyrillic}/u', $run[0]) === 1
                ? strtr($run[0], self::CYRILLIC_STRESSED + self::CYRILLIC_TWINS + self::CYRILLIC_ALIENS)
                : $run[0],
            $reading,
        ) ?? $reading;
    }

    /** @param array<string, mixed> $row */
    private function speaker(array $row, string $key, string $path): string
    {
        $speaker = $this->string($row, $key, $path);
        if (! in_array($speaker, [Message::SPEAKER_PARTNER, Message::SPEAKER_LEARNER], true)) {
            throw ModelAnswerOffSchema::at("{$path}.{$key}", "unknown speaker «{$speaker}»");
        }

        return $speaker;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function object(array $row, string $key): array
    {
        $value = $row[$key] ?? null;
        if (! is_array($value)) {
            throw ModelAnswerOffSchema::at($key, 'missing object');
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /** @return array<string, mixed> */
    private function objectAt(mixed $value, string $path): array
    {
        if (! is_array($value)) {
            throw ModelAnswerOffSchema::at($path, 'not an object');
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<mixed>
     */
    private function list(array $row, string $key): array
    {
        $value = $row[$key] ?? null;
        if (! is_array($value)) {
            throw ModelAnswerOffSchema::at($key, 'missing list');
        }

        return array_values($value);
    }

    /** @param array<string, mixed> $row */
    private function int(array $row, string $key, string $path): int
    {
        $value = $row[$key] ?? null;
        if (! is_int($value)) {
            throw ModelAnswerOffSchema::at("{$path}.{$key}", 'not an integer');
        }

        return $value;
    }

    /** @param array<string, mixed> $row */
    private function string(array $row, string $key, string $path): string
    {
        $value = $row[$key] ?? null;
        if (! is_string($value) || trim($value) === '') {
            throw ModelAnswerOffSchema::at("{$path}.{$key}", 'missing or empty string');
        }

        return trim($value);
    }

    /** @param array<string, mixed> $row */
    private function stringOrEmpty(array $row, string $key): string
    {
        $value = $row[$key] ?? null;

        return is_string($value) ? trim($value) : '';
    }

    /** @param array<string, mixed> $row */
    private function nullableString(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;
        if (! is_string($value)) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    private function stringList(array $row, string $key): array
    {
        $value = $row[$key] ?? [];
        if (! is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $item) {
            if (is_string($item) && trim($item) !== '') {
                $out[] = trim($item);
            }
        }

        return $out;
    }
}
