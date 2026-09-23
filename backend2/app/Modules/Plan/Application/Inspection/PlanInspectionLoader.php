<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Inspection;

use App\Modules\Plan\Application\Port\LearnerCalendar;
use App\Modules\Plan\Application\Port\LearnerGender;
use App\Modules\Plan\Application\Port\PlanInspectionReader;
use App\Modules\Plan\Domain\Repository\PlanEventRepository;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\Repository\PlanTermRepository;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * Finds a plan by its code or id and reads it for the admin's page (наряд ADM-1). No owner check: the caller is the
 * back-office, which sees every plan; nothing here writes.
 */
final readonly class PlanInspectionLoader
{
    public function __construct(
        private PlanInspectionReader $reader,
        private PlanRepository $plans,
        private PlanTermRepository $terms,
        private PlanEventRepository $events,
        private LearnerCalendar $calendar,
        private LearnerGender $genders,
        private Clock $clock,
    ) {}

    /**
     * The plan id a code or an id names: a 26-letter ULID as it is, a 6-letter code by `substr(id, 5, 6)`.
     *
     * @throws PlanCodeAmbiguous when two plans share the code
     */
    public function resolve(string $codeOrId): ?string
    {
        $value = strtoupper(trim($codeOrId));
        if (strlen($value) === 26) {
            return Ulid::isValid($value) && $this->reader->plan($value) !== null ? $value : null;
        }
        if (preg_match('/^[0-9A-HJKMNP-TV-Z]{6}$/', $value) !== 1) {
            return null;
        }
        $ids = $this->reader->idsByCode($value);
        if (count($ids) > 1) {
            throw PlanCodeAmbiguous::of($value, $ids);
        }

        return $ids[0] ?? null;
    }

    public function load(string $planId): ?PlanInspectionData
    {
        $row = $this->reader->plan($planId);
        $plan = $row === null ? null : $this->plans->findById(new PlanId($planId));
        if ($row === null || $plan === null) {
            return null;
        }
        $user = new UserId($row->userId);

        return new PlanInspectionData(
            plan: $plan,
            row: $row,
            scenes: $this->reader->scenes($planId),
            today: $this->calendar->todayFor($user, $this->clock->now()),
            profileGender: $this->genders->of($user),
            reader: $this->reader,
            termRepository: $this->terms,
            eventRepository: $this->events,
        );
    }

    /** @return list<string> */
    public function idsOf(string $userId): array
    {
        return $this->reader->idsOf($userId);
    }
}
