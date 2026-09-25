<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Query;

/**
 * The languages of a plan's pair — the entry screen's list of targets (`GET /plans/languages`), both sides
 * named (`GET /languages`, наряд LANG-1 §7), and `POST /plans`'s rule.
 */
final readonly class GetPlanLanguages {}
