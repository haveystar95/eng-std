<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/** The plan as the tab and the preview show it — one shape, every string ready to print. */
final readonly class PlanView
{
    /**
     * @param  list<DayRouteView>  $days
     * @param  list<SceneView>  $scenes
     * @param  array{url: string, author: string|null, author_url: string|null, tone: string|null}|null  $coverImage
     * @param  list<array{text_target: string, text_native: string, pronunciation_native: string}>  $rescueKit
     */
    public function __construct(
        public string $id,
        public string $status,
        public string $goalText,
        public string $targetLang,
        public string $nativeLang,
        public string $level,
        public int $daysTotal,
        public int $daysRequested,
        public ?int $daysShortenedFrom,
        public ?string $eventDate,
        public ?int $daysLeft,
        public ?string $titleNative,
        public ?string $titleTarget,
        public ?string $eventNative,
        public ?string $untilPhrase,
        public ?string $overdueNative,
        public string $routeSummary,
        public ?string $learnerRoleTarget,
        public ?string $learnerRoleNative,
        public ?array $coverImage,
        public ?string $collectionId,
        public ?string $unclearReason,
        public ?string $failReason,
        public ?DayRouteView $currentDay,
        public array $days,
        public array $scenes,
        public array $rescueKit,
        public string $costUsd,
        public VersionsView $versions,
        public ?string $startedAt,
        public ?string $finishedAt,
        public string $createdAt,
        /** «Регистрация на рейс, заселение в отель, ресторан. К 17 сентября скажешь всё это сам» — computed on read, never stored */
        public ?string $summary = null,
        /** Local hour of the daily reminder and «сегодня разговор» (8…23; 19 without visits). */
        public int $reminderHour = 19,
    ) {}
}
