<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Eloquent;

use App\Modules\Plan\Application\Dto\MissingImageCounts;
use App\Modules\Plan\Application\Dto\SceneImageRef;
use App\Modules\Plan\Application\Port\PlanListReader;
use App\Modules\Plan\Application\Port\SceneLocator;
use App\Modules\Plan\Domain\Entity\Plan;
use App\Modules\Plan\Domain\Entity\PlanDay;
use App\Modules\Plan\Domain\Entity\PlanScene;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\ValueObject\DayMetrics;
use App\Modules\Plan\Domain\ValueObject\Image;
use App\Modules\Plan\Domain\ValueObject\LessonStatus;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\PlanStatus;
use App\Modules\Shared\Domain\ValueObject\UserId;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/** The aggregate in three queries (plan, scenes, days); written back as upserts and deletions of what left. */
final class EloquentPlanRepository implements PlanListReader, PlanRepository, SceneLocator
{
    public function __construct(private readonly PlanMapper $mapper) {}

    public function findById(PlanId $id): ?Plan
    {
        $row = $this->query()->find($id->value);

        return $row === null ? null : $this->mapper->toDomain($row);
    }

    public function findOwned(PlanId $id, UserId $owner): ?Plan
    {
        $row = $this->query()->whereKey($id->value)->where('user_id', $owner->value)->first();

        return $row === null ? null : $this->mapper->toDomain($row);
    }

    public function findOwnedForUpdate(PlanId $id, UserId $owner): ?Plan
    {
        $row = $this->query()->whereKey($id->value)->where('user_id', $owner->value)->lockForUpdate()->first();

        return $row === null ? null : $this->mapper->toDomain($row);
    }

    public function findByIdForUpdate(PlanId $id): ?Plan
    {
        $row = $this->query()->whereKey($id->value)->lockForUpdate()->first();

        return $row === null ? null : $this->mapper->toDomain($row);
    }

    public function findLiveFor(UserId $owner): ?Plan
    {
        $row = $this->query()
            ->where('user_id', $owner->value)
            ->whereIn('status', [PlanStatus::Active->value, PlanStatus::Overdue->value])
            ->orderByDesc('created_at')
            ->first();

        return $row === null ? null : $this->mapper->toDomain($row);
    }

    /**
     * Live first, then the newest built-and-unstarted one — one index scan on
     * `plans_user_status_idx`, ordered by a case so the two states come back in one query.
     */
    public function findCurrentFor(UserId $owner): ?Plan
    {
        $row = $this->query()
            ->where('user_id', $owner->value)
            ->whereIn('status', [PlanStatus::Active->value, PlanStatus::Overdue->value, PlanStatus::Ready->value])
            ->orderByRaw("CASE WHEN status = 'ready' THEN 1 ELSE 0 END")
            ->orderByDesc('created_at')
            ->first();

        return $row === null ? null : $this->mapper->toDomain($row);
    }

    public function allFor(UserId $owner): array
    {
        $rows = $this->query()
            ->where('user_id', $owner->value)
            ->where('status', '!=', PlanStatus::Deleted->value)
            ->orderByRaw("CASE WHEN status IN ('active','overdue') THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->get();

        return array_values($rows->map(fn (PlanModel $row): Plan => $this->mapper->toDomain($row))->all());
    }

    public function planIdOf(PlanSceneId $sceneId): ?PlanId
    {
        $planId = PlanSceneModel::query()->whereKey($sceneId->value)->value('plan_id');

        return is_string($planId) ? PlanId::fromString($planId) : null;
    }

    public function findSceneForUpdate(PlanSceneId $id): ?PlanScene
    {
        $row = PlanSceneModel::query()->whereKey($id->value)->lockForUpdate()->first();

        return $row === null ? null : $this->mapper->sceneOf($row);
    }

    public function saveScene(PlanScene $scene): void
    {
        // The photo belongs to the photo jobs' conditional writes: the lesson job read this scene before its
        // model call, and its copy would put a photo found meanwhile back as none (DAY-UI-3: both jobs start
        // together, and the scene photo often lands while the lesson is being written).
        $columns = array_diff_key($this->mapper->sceneColumns($scene), array_flip(PlanMapper::SCENE_PHOTO_COLUMNS));
        PlanSceneModel::query()->whereKey($scene->id()->value)->update([...$columns, 'updated_at' => now()]);
    }

    public function saveDayMetrics(PlanDayId $dayId, DayMetrics $metrics): void
    {
        PlanDayModel::query()->whereKey($dayId->value)
            ->update([...PlanMapper::metricColumns($metrics), 'updated_at' => now()]);
    }

    public function savePace(PlanId $id, array $pace): void
    {
        PlanModel::query()->whereKey($id->value)->update(['pace' => json_encode($pace, JSON_THROW_ON_ERROR), 'updated_at' => now()]);
    }

    public function attachCoverImage(PlanId $id, Image $image): void
    {
        PlanModel::query()->whereKey($id->value)->whereNull('cover_image_url')->update([
            'cover_image_url' => $image->url,
            'cover_image_author' => $image->author,
            'cover_image_author_url' => $image->authorUrl,
            'cover_image_tone' => $image->tone,
            'updated_at' => now(),
        ]);
    }

    public function attachSceneImage(PlanSceneId $id, Image $image): bool
    {
        return PlanSceneModel::query()->whereKey($id->value)->whereNull('image_url')->update([
            'image_url' => $image->url,
            'image_author' => $image->author,
            'image_author_url' => $image->authorUrl,
            'image_tone' => $image->tone,
            'updated_at' => now(),
        ]) > 0;
    }

    public function attachSceneImageTone(PlanSceneId $id, string $imageUrl, string $tone): bool
    {
        $tone = Image::normalTone($tone);
        if ($tone === null) {
            return false;
        }

        return PlanSceneModel::query()->whereKey($id->value)
            ->where('image_url', $imageUrl)
            ->whereNull('image_tone')
            ->update(['image_tone' => $tone, 'updated_at' => now()]) > 0;
    }

    public function finishIllustration(PlanSceneId $id, DateTimeImmutable $at): bool
    {
        return PlanSceneModel::query()->whereKey($id->value)
            ->where('lesson_status', LessonStatus::Illustrating->value)
            ->update(['lesson_status' => LessonStatus::Ready->value, 'built_at' => $at->format(DATE_ATOM), 'updated_at' => now()]) > 0;
    }

    public function voicesOf(array $sceneIds): array
    {
        if ($sceneIds === []) {
            return [];
        }
        $out = [];
        foreach (PlanSceneModel::query()->whereKey($sceneIds)->get(['id', 'user_id', 'partner_voice_gender']) as $row) {
            $out[(string) $row->id] = ['partner' => VoiceGender::tryFromAny($row->partner_voice_gender), 'learner' => UserId::fromString((string) $row->user_id)];
        }

        return $out;
    }

    /** One row by primary key, the owner in the same predicate — a stranger's scene is simply not found. */
    public function ownedImage(PlanSceneId $sceneId, UserId $owner): ?Image
    {
        $row = PlanSceneModel::query()
            ->whereKey($sceneId->value)
            ->where('user_id', $owner->value)
            ->whereNotNull('image_url')
            ->first(['image_url', 'image_author', 'image_author_url', 'image_tone']);

        return $row === null || $row->image_url === null ? null
            : new Image($row->image_url, $row->image_author, $row->image_author_url, $row->image_tone);
    }

    public function scenesWithImages(?PlanId $planId): array
    {
        $query = PlanSceneModel::query()->whereNotNull('image_url')->orderBy('plan_id')->orderBy('order');
        if ($planId !== null) {
            $query->where('plan_id', $planId->value);
        }

        $out = [];
        foreach ($query->cursor() as $row) {
            if ($row->image_url === null) {
                continue;
            }
            $out[] = new SceneImageRef(
                PlanSceneId::fromString($row->id),
                new Image($row->image_url, $row->image_author, $row->image_author_url, $row->image_tone),
            );
        }

        return $out;
    }

    public function plansMissingImages(?PlanId $planId): array
    {
        $ids = DB::table('plans as p')
            ->where('p.status', '<>', PlanStatus::Deleted->value)
            ->when($planId !== null, static fn ($q) => $q->where('p.id', $planId?->value))
            ->where(static function ($q): void {
                $q->whereExists(static fn ($s) => $s->from('plan_scenes as s')->whereColumn('s.plan_id', 'p.id')->whereNull('s.image_url'))
                    ->orWhereExists(static fn ($t) => $t->from('plan_terms as t')
                        ->join('plan_scenes as ts', 'ts.id', '=', 't.scene_id')
                        ->whereColumn('ts.plan_id', 'p.id')
                        ->whereIn('t.kind', ['word', 'chunk'])
                        ->whereNull('t.image_url'));
            })
            ->orderBy('p.created_at')
            ->pluck('p.id');

        return array_values(array_map(static fn (mixed $id): PlanId => PlanId::fromString((string) $id), $ids->all()));
    }

    public function missingImageCounts(?PlanId $planId): MissingImageCounts
    {
        $scenes = DB::table('plan_scenes as s')
            ->join('plans as p', 'p.id', '=', 's.plan_id')
            ->where('p.status', '<>', PlanStatus::Deleted->value)
            ->when($planId !== null, static fn ($q) => $q->where('p.id', $planId?->value))
            ->whereNull('s.image_url')
            ->count();
        $terms = DB::table('plan_terms as t')
            ->join('plan_scenes as s', 's.id', '=', 't.scene_id')
            ->join('plans as p', 'p.id', '=', 's.plan_id')
            ->where('p.status', '<>', PlanStatus::Deleted->value)
            ->when($planId !== null, static fn ($q) => $q->where('p.id', $planId?->value))
            ->whereIn('t.kind', ['word', 'chunk'])
            ->whereNull('t.image_url')
            ->count();

        return new MissingImageCounts($scenes, $terms);
    }

    public function save(Plan $plan): void
    {
        DB::transaction(function () use ($plan): void {
            PlanModel::query()->updateOrCreate(['id' => $plan->id()->value], $this->mapper->planColumns($plan));

            $sceneIds = array_map(static fn (PlanScene $s): string => $s->id()->value, $plan->scenes());
            $removed = PlanSceneModel::query()->where('plan_id', $plan->id()->value);
            if ($sceneIds !== []) {
                $removed->whereNotIn('id', $sceneIds);
            }
            $removed->delete();
            foreach ($plan->scenes() as $scene) {
                PlanSceneModel::query()->updateOrCreate(
                    ['id' => $scene->id()->value],
                    [...$this->mapper->sceneColumns($scene), 'plan_id' => $scene->planId()->value, 'user_id' => $plan->userId()->value],
                );
            }

            $dayIds = array_map(static fn (PlanDay $d): string => $d->id()->value, $plan->days());
            $gone = PlanDayModel::query()->where('plan_id', $plan->id()->value);
            if ($dayIds !== []) {
                $gone->whereNotIn('id', $dayIds);
            }
            $gone->delete();
            foreach ($plan->days() as $day) {
                PlanDayModel::query()->updateOrCreate(
                    ['id' => $day->id()->value],
                    [...$this->mapper->dayColumns($day), 'plan_id' => $day->planId()->value, 'user_id' => $plan->userId()->value],
                );
            }
        });
    }

    /** @return Builder<PlanModel> */
    private function query(): Builder
    {
        return PlanModel::query()->with(['scenes', 'days']);
    }
}
