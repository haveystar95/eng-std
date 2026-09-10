<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Blueprint;

use App\Modules\Plan\Domain\Exception\ModelAnswerOffSchema;
use App\Modules\Plan\Domain\ValueObject\SceneKind;

/** The plan builder's JSON → a {@see Blueprint}. Shape only; content is the checks' business. */
final class BlueprintParser
{
    /** @param array<string, mixed> $payload */
    public function parse(array $payload): Blueprint
    {
        $status = $payload['status'] ?? null;
        if (! is_string($status)) {
            throw ModelAnswerOffSchema::at('status', 'missing');
        }
        if ($status === Blueprint::STATUS_UNCLEAR) {
            $reason = $payload['unclear_reason'] ?? null;

            return new Blueprint($status, is_string($reason) ? trim($reason) : null, null, []);
        }
        if ($status !== Blueprint::STATUS_OK) {
            throw ModelAnswerOffSchema::at('status', "unknown status «{$status}»");
        }

        $plan = $payload['plan'] ?? null;
        if (! is_array($plan)) {
            throw ModelAnswerOffSchema::at('plan', 'missing object');
        }
        /** @var array<string, mixed> $plan */
        $titles = new PlanTitles(
            titleNative: $this->string($plan, 'title_native', 'plan'),
            titleTarget: $this->string($plan, 'title_target', 'plan'),
            eventNative: $this->string($plan, 'event_native', 'plan'),
            untilPhraseNative: $this->string($plan, 'until_phrase_native', 'plan'),
            overdueNative: $this->string($plan, 'overdue_native', 'plan'),
            coverImagePrompt: $this->stringOrEmpty($plan, 'cover_image_prompt'),
            learnerRoleTarget: $this->stringOrEmpty($plan, 'learner_role_target'),
            learnerRoleNative: $this->stringOrEmpty($plan, 'learner_role_native'),
        );

        $rawScenes = $payload['scenes'] ?? null;
        if (! is_array($rawScenes)) {
            throw ModelAnswerOffSchema::at('scenes', 'missing list');
        }
        $scenes = [];
        foreach (array_values($rawScenes) as $index => $raw) {
            if (! is_array($raw)) {
                throw ModelAnswerOffSchema::at("scenes[{$index}]", 'not an object');
            }
            /** @var array<string, mixed> $raw */
            $scenes[] = $this->scene($raw, $index);
        }

        return new Blueprint($status, null, $titles, $scenes);
    }

    /** @param array<string, mixed> $raw */
    private function scene(array $raw, int $index): SceneBrief
    {
        $where = "scenes[{$index}]";
        $order = $raw['order'] ?? null;
        $priority = $raw['priority'] ?? null;
        if (! is_int($order) || ! is_int($priority)) {
            throw ModelAnswerOffSchema::at($where, 'order and priority must be integers');
        }
        $kind = SceneKind::tryFrom($this->stringOrEmpty($raw, 'kind'));
        if ($kind === null) {
            throw ModelAnswerOffSchema::at("{$where}.kind", 'unknown kind');
        }
        $goals = $raw['goals_native'] ?? [];
        if (! is_array($goals)) {
            throw ModelAnswerOffSchema::at("{$where}.goals_native", 'not a list');
        }

        return new SceneBrief(
            order: $order,
            kind: $kind,
            priority: $priority,
            titleNative: $this->string($raw, 'title_native', $where),
            titleTarget: $this->stringOrEmpty($raw, 'title_target'),
            teachesNative: $this->stringOrEmpty($raw, 'teaches_native'),
            goalsNative: array_values(array_filter(array_map(
                static fn (mixed $g): string => is_string($g) ? trim($g) : '',
                $goals,
            ), static fn (string $g): bool => $g !== '')),
            learnerRoleTarget: $this->stringOrEmpty($raw, 'learner_role_target'),
            learnerRoleNative: $this->stringOrEmpty($raw, 'learner_role_native'),
            partnerRoleTarget: $this->stringOrEmpty($raw, 'partner_role_target'),
            partnerRoleNative: $this->stringOrEmpty($raw, 'partner_role_native'),
            topicDescription: $this->string($raw, 'topic_description', $where),
            imagePrompt: $this->stringOrEmpty($raw, 'image_prompt'),
        );
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
}
