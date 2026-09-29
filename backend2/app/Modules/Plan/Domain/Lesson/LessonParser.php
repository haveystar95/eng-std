<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Lesson;

use App\Modules\Plan\Domain\Exception\ModelAnswerOffSchema;
use App\Modules\Plan\Domain\Service\FrameText;
use App\Modules\Plan\Domain\Service\ReadingLetters;
use App\Modules\Plan\Domain\ValueObject\ExchangeKind;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/**
 * JSON → the day's values: the model's SKELETON ({@see skeleton()}, `lesson_skeleton.v1.1`) and DIALOGUE ({@see dialogue()},
 * `lesson_dialogue.v1.1`), one repaired card of either ({@see card()}, `lesson_card_repair.v1.5`), and a stored {@see Lesson}
 * ({@see parse()} — the lesson assembled from the two, in the shape every day of the plan has been stored in).
 * Strict about SHAPE only: a missing key, a wrong type, an unknown kind or speaker, an empty required
 * string is a reply that is not the requested schema, and that is the model's refusal, not a finding
 * ({@see ModelAnswerOffSchema}).
 * Everything about CONTENT is the stages' checks' (`SkeletonCheck`, `DialogueCheck`), run on the parsed values.
 *
 * THREE things are put right on the way, and only three. A frame — `frame_target` and `frame_native` — and a filler's
 * native text lose the space before the mark they end with (доработка GEN-3; both sides since наряд BACK-TAILS-1 §3.1,
 * {@see FrameText::withEndMarkClosed()}): «I work ___ .» is read as «I work ___.» by the validator, the seam judge, the
 * repair and every card, whatever the stored answer says. A line, a frame or a filler that ends in TWO full stops
 * keeps one ({@see FrameText::withoutDoubledStop()}, хвост ROADMAP, наряд CONV-1): «I can come at 3 p.m..» is the
 * abbreviation's stop plus the sentence's, and the phone, the voice and the judge all read it as written. And a
 * READING — every `pronunciation_native` the parser reads: a frame's, a filler's, a word's, a learner line's, in a
 * whole lesson and in a repaired card alike — has the Latin letters drawn inside a Cyrillic word put back into
 * Cyrillic, a Latin acute vowel («á») as the Cyrillic vowel with the combining stress mark (наряд LANG-1), and a letter of
 * another Cyrillic alphabet as the letter it stands for («аҗута́» → «ажута́», наряд LANG-1b §10) — the one rule of
 * {@see ReadingLetters}, which `plan:clean-text` reads the readings already stored with. A parser that knows the learner's
 * language ({@see forNative()}, the day's build) reads that language's own twins too: a Latin «ú» / «í» as «и́» for a Russian
 * learner, «і́» for a Ukrainian or a Belarusian one (наряд GEN-4b §3).
 */
final class LessonParser
{
    /** @param  string|null  $native  the learner's language code, when the caller knows it — the day's build does */
    public function __construct(private readonly ?string $native = null) {}

    /** The same parser reading the readings of a learner of `$native` ({@see ReadingLetters::mended()}). */
    public function forNative(?string $native): self
    {
        return new self($native);
    }

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
     * THE SKELETON of a day (`lesson_skeleton.v1.1`, OUTPUT SCHEMA): topic, learner role, the partner's gender, the frames with
     * the `must_say` numbers they serve, the partner lines, the vocabulary.
     *
     * @param  array<string, mixed>  $payload
     */
    public function skeleton(array $payload): Skeleton
    {
        $topic = $this->object($payload, 'topic');
        $role = $this->object($payload, 'learner_role');

        $frames = [];
        foreach ($this->list($payload, 'phrases') as $index => $frame) {
            $frames[] = $this->skeletonFrame($this->objectAt($frame, "phrases[{$index}]"), "phrases[{$index}]");
        }
        $lines = [];
        foreach ($this->list($payload, 'partner_lines') as $index => $line) {
            $lines[] = $this->partnerLine($this->objectAt($line, "partner_lines[{$index}]"), "partner_lines[{$index}]");
        }
        $vocabulary = [];
        foreach ($this->list($payload, 'vocabulary') as $index => $item) {
            $vocabulary[] = $this->vocabularyItem($this->objectAt($item, "vocabulary[{$index}]"), "vocabulary[{$index}]");
        }

        return new Skeleton(
            titleTarget: $this->stringOrEmpty($topic, 'title_target'),
            titleNative: $this->stringOrEmpty($topic, 'title_native'),
            descriptionTarget: $this->stringOrEmpty($topic, 'description_target'),
            descriptionNative: $this->stringOrEmpty($topic, 'description_native'),
            learnerRoleTarget: $this->stringOrEmpty($role, 'role_target'),
            learnerRoleNative: $this->stringOrEmpty($role, 'role_native'),
            roleGender: VoiceGender::tryFromAny($payload['role_gender'] ?? null),
            frames: $frames,
            partnerLines: $lines,
            vocabulary: $vocabulary,
        );
    }

    /**
     * THE DIALOGUE of a day (`lesson_dialogue.v1.1`, OUTPUT SCHEMA): the exchanges — each with the partner line it carries and
     * the item that line delivers — and the listening questions.
     *
     * @param  array<string, mixed>  $payload
     */
    public function dialogue(array $payload): Dialogue
    {
        $exchanges = [];
        foreach ($this->list($payload, 'dialogue') as $index => $exchange) {
            $exchanges[] = $this->dialogueExchange($this->objectAt($exchange, "dialogue[{$index}]"), "dialogue[{$index}]");
        }
        $listening = [];
        foreach ($this->list($this->object($payload, 'listening'), 'questions') as $index => $question) {
            $listening[] = $this->listeningQuestion($this->objectAt($question, "listening.questions[{$index}]"), "listening.questions[{$index}]");
        }

        return new Dialogue($exchanges, $listening);
    }

    /**
     * One card on its own — what a repair (`lesson_card_repair.v1.5`) answers with, held to the shape the card has in its
     * stage: of the skeleton a frame, a partner line or a word; of the dialogue a whole exchange, its check or a listening
     * question.
     *
     * @param  LessonCard::FRAME|LessonCard::TERM|LessonCard::PARTNER_LINE|LessonCard::EXCHANGE|LessonCard::CHECK|LessonCard::LISTENING  $kind
     * @param  array<string, mixed>  $row
     */
    public function card(string $kind, array $row): SkeletonFrame|VocabularyItem|PartnerLine|DialogueExchange|ExchangeCheck|ListeningQuestion
    {
        return match ($kind) {
            LessonCard::FRAME => $this->skeletonFrame($row, 'card'),
            LessonCard::TERM => $this->vocabularyItem($row, 'card'),
            LessonCard::PARTNER_LINE => $this->partnerLine($row, 'card'),
            LessonCard::EXCHANGE => $this->dialogueExchange($row, 'card'),
            LessonCard::CHECK => $this->check($row, 'card'),
            LessonCard::LISTENING => $this->listeningQuestion($row, 'card'),
        };
    }

    /** @param array<string, mixed> $row */
    private function skeletonFrame(array $row, string $path): SkeletonFrame
    {
        return new SkeletonFrame($this->phrase($row, $path), $this->intList($row, 'must_say', $path));
    }

    /** @param array<string, mixed> $row */
    private function partnerLine(array $row, string $path): PartnerLine
    {
        $kind = $this->string($row, 'kind', $path);
        if (! in_array($kind, [PartnerLine::QUESTION, PartnerLine::STATEMENT], true)) {
            throw ModelAnswerOffSchema::at("{$path}.kind", 'not question or statement');
        }

        return new PartnerLine(
            id: $this->string($row, 'id', $path),
            mustUnderstand: $this->int($row, 'must_understand', $path),
            kind: $kind,
            pairsWith: $this->intList($row, 'pairs_with', $path),
            textTarget: FrameText::withoutDoubledStop($this->string($row, 'text_target', $path)),
            textNative: FrameText::withoutDoubledStop($this->string($row, 'text_native', $path)),
        );
    }

    /** @param array<string, mixed> $row */
    private function dialogueExchange(array $row, string $path): DialogueExchange
    {
        $item = $row['must_understand'] ?? null;
        if ($item !== null && ! is_int($item)) {
            throw ModelAnswerOffSchema::at("{$path}.must_understand", 'not an integer or null');
        }

        return new DialogueExchange($this->exchange($row, $path), $item, $this->nullableString($row, 'partner_line'));
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
            pronunciationNative: $this->reading($this->stringOrEmpty($row, 'pronunciation_native')),
            definitionTarget: $this->stringOrEmpty($row, 'definition_target'),
            kind: $kind,
            imagePrompt: $this->nullableString($row, 'image_prompt'),
            usedIn: $this->stringList($row, 'used_in'),
        );
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
            pronunciationNative: $this->reading($this->nullableString($row, 'pronunciation_native')),
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
                    pronunciationNative: $this->reading($this->stringOrEmpty($f, 'pronunciation_native')),
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
            pronunciationNative: $this->reading($this->stringOrEmpty($row, 'pronunciation_native')),
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
     * A READING IN THE LETTERS ITS LEARNER READS ({@see ReadingLetters}): the Latin twins and the letters of other Cyrillic
     * alphabets inside a Cyrillic word put back, nothing else. Only the reading is read so: `text_target`, `text_native` and
     * every other field are the model's as written, and the validator judges the reading the learner will get.
     *
     * @return ($reading is null ? null : string)
     */
    private function reading(?string $reading): ?string
    {
        return $reading === null ? null : ReadingLetters::mended($reading, $this->native);
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

    /**
     * A list of whole numbers — `must_say`, `pairs_with`; empty when absent. Anything that is not a whole number is not the
     * schema.
     *
     * @param  array<string, mixed>  $row
     * @return list<int>
     */
    private function intList(array $row, string $key, string $path): array
    {
        $value = $row[$key] ?? [];
        if (! is_array($value)) {
            throw ModelAnswerOffSchema::at("{$path}.{$key}", 'not a list');
        }
        $out = [];
        foreach ($value as $item) {
            if (! is_int($item)) {
                throw ModelAnswerOffSchema::at("{$path}.{$key}", 'not a list of integers');
            }
            $out[] = $item;
        }

        return $out;
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
