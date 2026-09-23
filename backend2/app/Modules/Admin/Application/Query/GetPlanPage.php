<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Query;

/**
 * One part of the admin's plan page (наряд ADM-1): the header (`section` null) or a section — `issues`, `days`, `pipeline`,
 * `lesson`, `passage`, `conversations`, `money` — of the plan a code (or a full id) names, for the whole plan or one day.
 */
final readonly class GetPlanPage
{
    public const SECTIONS = ['issues', 'days', 'pipeline', 'lesson', 'passage', 'conversations', 'money'];

    public function __construct(
        public string $code,
        public ?string $section = null,
        public ?int $day = null,
    ) {}
}
