<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Lesson;

use App\Modules\Plan\Domain\Exception\ModelAnswerOffSchema;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/**
 * The model's JSON → a {@see Lesson}. Strict about SHAPE only: a missing key, a wrong type or an
 * empty required string is a reply that is not the requested schema, and that is the model's
 * refusal, not a check ({@see ModelAnswerOffSchema}). Everything about CONTENT — counts, keys,
 * scripts, ids — is a check, and the checks run on the parsed lesson.
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
            $exchanges[] = $this->exchange($this->objectAt($exchange, "dialogue[{$index}]"));
        }

        $phrases = [];
        foreach ($this->list($payload, 'phrases') as $index => $phrase) {
            $row = $this->objectAt($phrase, "phrases[{$index}]");
            $phrases[] = new Phrase(
                id: $this->string($row, 'id', 'phrases'),
                textTarget: $this->string($row, 'text_target', 'phrases'),
                textNative: $this->string($row, 'text_native', 'phrases'),
                pronunciationNative: $this->stringOrEmpty($row, 'pronunciation_native'),
            );
        }

        $vocabulary = [];
        foreach ($this->list($payload, 'vocabulary') as $index => $item) {
            $row = $this->objectAt($item, "vocabulary[{$index}]");
            $kind = $this->string($row, 'kind', 'vocabulary');
            if (! in_array($kind, [VocabularyItem::KIND_WORD, VocabularyItem::KIND_CHUNK], true)) {
                throw ModelAnswerOffSchema::at("vocabulary[{$index}].kind", "unknown kind «{$kind}»");
            }
            $vocabulary[] = new VocabularyItem(
                id: $this->string($row, 'id', 'vocabulary'),
                termTarget: $this->string($row, 'term_target', 'vocabulary'),
                translationNative: $this->string($row, 'translation_native', 'vocabulary'),
                pronunciationNative: $this->stringOrEmpty($row, 'pronunciation_native'),
                definitionTarget: $this->stringOrEmpty($row, 'definition_target'),
                kind: $kind,
                imagePrompt: $this->nullableString($row, 'image_prompt'),
            );
        }

        return new Lesson(
            titleTarget: $this->stringOrEmpty($topic, 'title_target'),
            titleNative: $this->stringOrEmpty($topic, 'title_native'),
            descriptionTarget: $this->stringOrEmpty($topic, 'description_target'),
            descriptionNative: $this->stringOrEmpty($topic, 'description_native'),
            learnerRoleTarget: $this->stringOrEmpty($role, 'role_target'),
            learnerRoleNative: $this->stringOrEmpty($role, 'role_native'),
            exchanges: $exchanges,
            phrases: $phrases,
            vocabulary: $vocabulary,
            // `lesson-v4` says whose voice the role has. A lesson written before (`lesson-v3`) has no
            // such key, and an odd word is not a reason to refuse a paid lesson: both are «not said»,
            // and the scene speaks with the default cast.
            roleGender: VoiceGender::tryFromAny($payload['role_gender'] ?? null),
        );
    }

    /** @param array<string, mixed> $row */
    private function exchange(array $row): Exchange
    {
        $step = $row['step'] ?? null;
        if (! is_int($step)) {
            throw ModelAnswerOffSchema::at('dialogue[].step', 'not an integer');
        }
        $initiator = $this->string($row, 'initiator', 'dialogue');
        if (! in_array($initiator, [Message::SPEAKER_PARTNER, Message::SPEAKER_LEARNER], true)) {
            throw ModelAnswerOffSchema::at("dialogue[{$step}].initiator", "unknown speaker «{$initiator}»");
        }

        $messages = [];
        foreach ($this->list($row, 'messages') as $index => $message) {
            $messages[] = $this->message($this->objectAt($message, "dialogue[{$step}].messages[{$index}]"));
        }

        return new Exchange(
            step: $step,
            initiator: $initiator,
            messages: $messages,
            question: $this->question($this->object($row, 'question')),
        );
    }

    /** @param array<string, mixed> $row */
    private function message(array $row): Message
    {
        $speaker = $this->string($row, 'speaker', 'messages');
        if (! in_array($speaker, [Message::SPEAKER_PARTNER, Message::SPEAKER_LEARNER], true)) {
            throw ModelAnswerOffSchema::at('messages[].speaker', "unknown speaker «{$speaker}»");
        }

        return new Message(
            speaker: $speaker,
            roleTarget: $this->stringOrEmpty($row, 'role_target'),
            roleNative: $this->stringOrEmpty($row, 'role_native'),
            textTarget: $this->string($row, 'text_target', 'messages'),
            textNative: $this->string($row, 'text_native', 'messages'),
            pronunciationNative: $this->nullableString($row, 'pronunciation_native'),
            speakingKey: $this->nullableString($row, 'speaking_key'),
            simplifiedVariants: $this->stringList($row, 'simplified_variants'),
            phraseIds: $this->stringList($row, 'phrase_ids'),
            vocabularyIds: $this->stringList($row, 'vocabulary_ids'),
        );
    }

    /** @param array<string, mixed> $row */
    private function question(array $row): Question
    {
        $options = [];
        foreach ($this->list($row, 'options') as $index => $option) {
            $o = $this->objectAt($option, "question.options[{$index}]");
            $options[] = new QuestionOption(
                $this->stringOrEmpty($o, 'text_target'),
                $this->stringOrEmpty($o, 'text_native'),
            );
        }
        $correct = $row['correct_option_index'] ?? null;
        if (! is_int($correct)) {
            throw ModelAnswerOffSchema::at('question.correct_option_index', 'not an integer');
        }

        return new Question(
            textTarget: $this->stringOrEmpty($row, 'text_target'),
            textNative: $this->stringOrEmpty($row, 'text_native'),
            options: $options,
            correctOptionIndex: $correct,
            explanationNative: $this->stringOrEmpty($row, 'explanation_native'),
        );
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
    private function string(array $row, string $key, string $where): string
    {
        $value = $row[$key] ?? null;
        if (! is_string($value) || trim($value) === '') {
            throw ModelAnswerOffSchema::at("{$where}.{$key}", 'missing or empty string');
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
