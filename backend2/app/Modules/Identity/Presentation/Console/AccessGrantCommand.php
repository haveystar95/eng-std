<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation\Console;

use App\Modules\Identity\Application\Command\GrantAccess;
use App\Modules\Identity\Application\Command\GrantAccessHandler;
use App\Modules\Identity\Application\Query\GetAccess;
use App\Modules\Identity\Application\Query\GetAccessHandler;
use App\Modules\Identity\Domain\ValueObject\EntitlementProduct;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * THE OWNER GIVES THE PAID PLAN BY HAND (наряд ACC-1 §2) — the only door into `entitlements` until the store purchases of
 * PAY-1: `php artisan access:grant {user} {product} {--until=}`. The learner is named by id or by email; the product is
 * `month`, `year` or `lifetime`; `--until` ends a month or a year on a date of its own — a bare date is in force through
 * that day (UTC), a moment is taken as written. The right's source is `admin`; granting again rewrites it.
 */
final class AccessGrantCommand extends Command
{
    use NamesALearner;

    protected $signature = 'access:grant {user : the learner — id or email} {product : month | year | lifetime} {--until= : the end of a month or a year: a date (in force through that day, UTC) or an ISO-8601 moment}';

    protected $description = 'Give a learner the paid plan by hand (source admin): a month, a year or for good';

    public function handle(GrantAccessHandler $grant, GetAccessHandler $access): int
    {
        $userId = $this->learner();
        $named = $this->argument('product');
        $product = EntitlementProduct::tryFrom(is_string($named) ? $named : '');
        if ($userId === null || $product === null) {
            if ($product === null) {
                $this->error('The product is month, year or lifetime.');
            }

            return self::FAILURE;
        }

        try {
            $until = $this->until();
            $right = $grant(new GrantAccess($userId, $product, $until));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $now = $access(new GetAccess($userId));
        $this->info(sprintf(
            'Granted %s (%s) to %s until %s — access: %s%s.',
            $right->product->value,
            $right->source->value,
            $userId->value,
            $right->expiresAt?->format(DATE_ATOM) ?? 'no end',
            $now->plan,
            $now->expiresAt === null ? '' : " until {$now->expiresAt}",
        ));

        return self::SUCCESS;
    }

    private function until(): ?DateTimeImmutable
    {
        $raw = $this->option('until');
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }
        $raw = trim($raw);
        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
                return (new DateTimeImmutable($raw, new DateTimeZone('UTC')))->modify('+1 day');
            }

            return new DateTimeImmutable($raw, new DateTimeZone('UTC'));
        } catch (Exception) {
            throw new InvalidArgumentException("--until is not a date: {$raw}");
        }
    }
}
