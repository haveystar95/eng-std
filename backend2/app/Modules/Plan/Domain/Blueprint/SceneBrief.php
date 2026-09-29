<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Blueprint;

use App\Modules\Plan\Domain\ValueObject\SceneKind;

/**
 * One scene as the plan builder wrote it — the brief the day is later built from: the scene's SURVIVAL SET (what the learner
 * must say and understand, `plan-builder-v2.1`), its lines on screen, its roles and the three-part description.
 */
final readonly class SceneBrief
{
    /** @param list<string> $goalsNative */
    public function __construct(
        public int $order,
        public SceneKind $kind,
        public int $priority,
        public string $titleNative,
        public string $titleTarget,
        public string $teachesNative,
        public array $goalsNative,
        public string $learnerRoleTarget,
        public string $learnerRoleNative,
        public string $partnerRoleTarget,
        public string $partnerRoleNative,
        public string $topicDescription,
        public string $imagePrompt,
        public SurvivalSet $survival = new SurvivalSet,
    ) {}

    public function withPriority(int $priority): self
    {
        return $this->with(priority: $priority);
    }

    public function withOrder(int $order): self
    {
        return $this->with(order: $order);
    }

    /** @param list<string> $goals */
    public function withGoals(array $goals): self
    {
        return $this->with(goals: $goals);
    }

    /**
     * The same scene with ONE of its screen lines written anew — what a line repair puts back (наряд GEN-4):
     * `title_native`, `title_target`, `teaches_native`, or the goal at `$index` of `goals_native`.
     */
    public function withLine(string $field, string $line, int $index = 0): self
    {
        return match ($field) {
            'title_native' => $this->with(titleNative: $line),
            'title_target' => $this->with(titleTarget: $line),
            'teaches_native' => $this->with(teachesNative: $line),
            'goals_native' => $this->with(goals: array_map(
                static fn (string $goal, int $i): string => $i === $index ? $line : $goal,
                $this->goalsNative,
                array_keys($this->goalsNative),
            )),
            default => $this,
        };
    }

    /** @param list<string>|null $goals */
    private function with(
        ?int $order = null,
        ?int $priority = null,
        ?string $titleNative = null,
        ?string $titleTarget = null,
        ?string $teachesNative = null,
        ?array $goals = null,
    ): self {
        return new self(
            $order ?? $this->order, $this->kind, $priority ?? $this->priority, $titleNative ?? $this->titleNative,
            $titleTarget ?? $this->titleTarget, $teachesNative ?? $this->teachesNative, $goals ?? $this->goalsNative,
            $this->learnerRoleTarget, $this->learnerRoleNative, $this->partnerRoleTarget, $this->partnerRoleNative,
            $this->topicDescription, $this->imagePrompt, $this->survival,
        );
    }
}
