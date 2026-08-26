<?php

declare(strict_types=1);

namespace App\Modules\Learning\Presentation\Http\Controller;

use App\Modules\Learning\Application\Query\GetHomePlan;
use App\Modules\Learning\Application\Query\GetHomePlanHandler;
use App\Modules\Learning\Presentation\Http\Resource\HomePlanResource;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;
use Illuminate\Http\Request;

/**
 * The home screen's day, in one read.
 *
 * Separate from {@see StudyController} on purpose. `/stats` is the dashboard's aggregate — totals,
 * mastered, the activity calendar — and the Progress screen reads it too; this is the PLANNER's
 * answer to «что мне делать сейчас», and it hydrates term and collection content to say it. Folding
 * the two together would make every Progress poll run the session planner, and would put words and
 * folder titles inside a resource whose whole point is that it holds only numbers.
 */
final class HomeController
{
    public function __construct(private readonly GetHomePlanHandler $homePlan) {}

    public function plan(Request $request): HomePlanResource
    {
        return new HomePlanResource(($this->homePlan)(new GetHomePlan(
            UserId::fromString((string) $request->user()?->getAuthIdentifier()),
            new DateTimeImmutable(),
        )));
    }
}
