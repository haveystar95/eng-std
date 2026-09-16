<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Adapter;

use App\Modules\Plan\Application\Port\SlotJudgeQuota;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\Redis;

/**
 * The slot judge's daily quota in Redis — one counter per learner per LOCAL day (`plan:slot_judge:{user}:{Y-m-d}` in
 * the learner's zone), expiring at the learner's next midnight, so the key of a day nobody judges on is gone by
 * itself and a new day starts at zero whatever the server's clock says.
 *
 * `INCR` first and ask after: two attempts at once can never both take the last call. A refused take gives its
 * increment back, so the counter reads «calls made», never «calls asked for».
 */
final class RedisSlotJudgeQuota implements SlotJudgeQuota
{
    private const CONNECTION = 'cache';

    public function take(UserId $user, DateTimeImmutable $now, DateTimeZone $zone, int $cap): bool
    {
        $local = $now->setTimezone($zone);
        $key = 'plan:slot_judge:'.$user->value.':'.$local->format('Y-m-d');
        $redis = Redis::connection(self::CONNECTION);

        $count = (int) $redis->command('incr', [$key]);
        if ($count === 1) {
            $redis->command('expireat', [$key, $local->setTime(0, 0)->modify('+1 day')->getTimestamp()]);
        }
        if ($count > $cap) {
            $redis->command('decr', [$key]);

            return false;
        }

        return true;
    }
}
