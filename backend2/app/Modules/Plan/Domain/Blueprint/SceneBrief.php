<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Blueprint;

use App\Modules\Plan\Domain\ValueObject\SceneKind;

/** One scene as the plan builder wrote it — the brief the lesson generator is later handed. */
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
    ) {}

    public function withPriority(int $priority): self
    {
        return new self(
            $this->order, $this->kind, $priority, $this->titleNative, $this->titleTarget, $this->teachesNative,
            $this->goalsNative, $this->learnerRoleTarget, $this->learnerRoleNative, $this->partnerRoleTarget,
            $this->partnerRoleNative, $this->topicDescription, $this->imagePrompt,
        );
    }

    public function withOrder(int $order): self
    {
        return new self(
            $order, $this->kind, $this->priority, $this->titleNative, $this->titleTarget, $this->teachesNative,
            $this->goalsNative, $this->learnerRoleTarget, $this->learnerRoleNative, $this->partnerRoleTarget,
            $this->partnerRoleNative, $this->topicDescription, $this->imagePrompt,
        );
    }

    /** @param list<string> $goals */
    public function withGoals(array $goals): self
    {
        return new self(
            $this->order, $this->kind, $this->priority, $this->titleNative, $this->titleTarget, $this->teachesNative,
            $goals, $this->learnerRoleTarget, $this->learnerRoleNative, $this->partnerRoleTarget,
            $this->partnerRoleNative, $this->topicDescription, $this->imagePrompt,
        );
    }
}
