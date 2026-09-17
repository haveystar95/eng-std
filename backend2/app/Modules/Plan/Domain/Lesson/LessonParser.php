<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Lesson;

use App\Modules\Plan\Domain\Exception\ModelAnswerOffSchema;
use App\Modules\Plan\Domain\Service\FrameText;
use App\Modules\Plan\Domain\ValueObject\ExchangeKind;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/**
 * The model's JSON → a {@see Lesson} (`lesson_day.v4.7`). Strict about SHAPE only: a missing key, a
 * wrong type, an unknown kind or speaker, an empty required string is a reply that is not the
 * requested schema, and that is the model's refusal, not a finding ({@see ModelAnswerOffSchema}).
 * Everything about CONTENT — counts, frames, fillers, keys, checks, listening — is the validator's,
 * and the validator runs on the parsed lesson.
 *
 * One thing is put right on the way, and only one (доработка GEN-3; both sides since наряд BACK-TAILS-1 §3.1): a frame —
 * `frame_target` and `frame_native` — and a filler's native text lose the space before the mark they end with
 * ({@see FrameText::withEndMarkClosed()}) — «I work ___ .» is read as «I work ___.» by the validator, the seam judge,
 * the repair and every card, whatever the stored answer says. The target side was left out of the first pass and a card
 * showed the gap.
 */
final class LessonParser
{
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
            pronunciationNative: $this->stringOrEmpty($row, 'pronunciation_native'),
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
        $text = $this->string($row, 'text_target', $path);
        $native = $this->string($row, 'text_native', $path);
        if ($speaker === Message::SPEAKER_PARTNER) {
            return new Message($speaker, $this->stringOrEmpty($row, 'role_target'), $this->stringOrEmpty($row, 'role_native'), $text, $native);
        }

        return new Message(
            speaker: $speaker,
            roleTarget: $this->stringOrEmpty($row, 'role_target'),
            roleNative: $this->stringOrEmpty($row, 'role_native'),
            textTarget: $text,
            textNative: $native,
            pronunciationNative: $this->nullableString($row, 'pronunciation_native'),
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
                    native: FrameText::withEndMarkClosed($this->stringOrEmpty($f, 'native')),
                    pronunciationNative: $this->stringOrEmpty($f, 'pronunciation_native'),
                    inDialogue: $inDialogue,
                );
            }
            $slot = new Slot($this->stringOrEmpty($raw, 'hint_native'), $fillers);
        }

        return new Phrase(
            id: $this->string($row, 'id', $path),
            kind: $kind,
            frameTarget: FrameText::withEndMarkClosed($this->string($row, 'frame_target', $path)),
            frameNative: FrameText::withEndMarkClosed($this->stringOrEmpty($row, 'frame_native')),
            pronunciationNative: $this->stringOrEmpty($row, 'pronunciation_native'),
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
