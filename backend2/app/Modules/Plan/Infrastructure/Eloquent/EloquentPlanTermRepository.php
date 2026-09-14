<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Eloquent;

use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\Lesson\Filler;
use App\Modules\Plan\Domain\Lesson\Phrase;
use App\Modules\Plan\Domain\Lesson\Slot;
use App\Modules\Plan\Domain\Repository\PlanTermRepository;
use App\Modules\Plan\Domain\ValueObject\ExchangeKind;
use App\Modules\Plan\Domain\ValueObject\Image;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\PlanStatus;
use App\Modules\Plan\Domain\ValueObject\PlanTermId;
use App\Modules\Plan\Domain\ValueObject\TermKind;
use Illuminate\Support\Facades\DB;

final class EloquentPlanTermRepository implements PlanTermRepository
{
    public function forScene(PlanSceneId $sceneId): array
    {
        return $this->forScenes([$sceneId])[$sceneId->value] ?? [];
    }

    public function forScenes(array $sceneIds): array
    {
        if ($sceneIds === []) {
            return [];
        }
        $rows = PlanTermModel::query()
            ->whereIn('scene_id', array_map(static fn (PlanSceneId $id): string => $id->value, $sceneIds))
            ->orderBy('scene_id')
            ->orderBy('position')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[$row->scene_id][] = $this->toDomain($row);
        }

        return $out;
    }

    public function replaceForScene(PlanSceneId $sceneId, array $terms): void
    {
        DB::transaction(function () use ($sceneId, $terms): void {
            $userId = DB::table('plan_scenes')->where('id', $sceneId->value)->value('user_id');
            PlanTermModel::query()->where('scene_id', $sceneId->value)->delete();
            $now = now();
            $rows = [];
            foreach ($terms as $term) {
                $rows[] = [...$this->columns($term), 'id' => $term->id()->value, 'user_id' => (string) $userId, 'created_at' => $now, 'updated_at' => $now];
            }
            if ($rows !== []) {
                PlanTermModel::query()->insert($rows);
            }
        });
    }

    public function rewriteTexts(PlanSceneId $sceneId, array $terms): void
    {
        DB::transaction(function () use ($sceneId, $terms): void {
            foreach ($terms as $term) {
                $columns = $this->columns($term);
                unset($columns['scene_id'], $columns['kind'], $columns['ref'], $columns['position'], $columns['image_url'], $columns['image_author'], $columns['image_author_url'], $columns['image_tone']);
                PlanTermModel::query()->where('scene_id', $sceneId->value)->where('ref', $term->ref())->update([...$columns, 'updated_at' => now()]);
            }
        });
    }

    public function attachImage(PlanTermId $id, Image $image): void
    {
        PlanTermModel::query()->whereKey($id->value)->whereNull('image_url')->update([
            'image_url' => $image->url,
            'image_author' => $image->author,
            'image_author_url' => $image->authorUrl,
            'image_tone' => $image->tone,
            'updated_at' => now(),
        ]);
    }

    public function markImageMissing(PlanTermId $id, string $tone): void
    {
        $normal = Image::normalTone($tone);
        if ($normal === null) {
            return;
        }
        PlanTermModel::query()->whereKey($id->value)->whereNull('image_url')->update([
            'image_tone' => $normal,
            'updated_at' => now(),
        ]);
    }

    public function replaceImage(PlanTermId $id, Image $image): void
    {
        PlanTermModel::query()->whereKey($id->value)->update([
            'image_url' => $image->url,
            'image_author' => $image->author,
            'image_author_url' => $image->authorUrl,
            'image_tone' => $image->tone,
            'updated_at' => now(),
        ]);
    }

    public function photographedWithoutPrompt(?PlanId $planId): array
    {
        $rows = PlanTermModel::query()
            ->select('plan_terms.*')
            ->join('plan_scenes', 'plan_scenes.id', '=', 'plan_terms.scene_id')
            ->join('plans', 'plans.id', '=', 'plan_scenes.plan_id')
            ->where('plans.status', '<>', PlanStatus::Deleted->value)
            ->when($planId !== null, static fn ($q) => $q->where('plans.id', $planId?->value))
            ->whereIn('plan_terms.kind', [TermKind::Word->value, TermKind::Chunk->value])
            ->whereNotNull('plan_terms.image_url')
            ->where(static fn ($q) => $q->whereNull('plan_terms.image_prompt')->orWhere('plan_terms.image_prompt', ''))
            ->orderBy('plan_terms.scene_id')
            ->orderBy('plan_terms.position')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[$row->scene_id][] = $this->toDomain($row);
        }

        return $out;
    }

    public function repeatingDayPhotos(?PlanId $planId): array
    {
        $photographed = [TermKind::Word->value, TermKind::Chunk->value];
        $rows = PlanTermModel::query()
            ->select('plan_terms.*')
            ->join('plan_scenes', 'plan_scenes.id', '=', 'plan_terms.scene_id')
            ->join('plans', 'plans.id', '=', 'plan_scenes.plan_id')
            ->where('plans.status', '<>', PlanStatus::Deleted->value)
            ->when($planId !== null, static fn ($q) => $q->where('plans.id', $planId?->value))
            ->whereIn('plan_terms.kind', $photographed)
            ->whereNotNull('plan_terms.image_url')
            ->where(static fn ($q) => $q
                ->whereColumn('plan_terms.image_url', 'plan_scenes.image_url')
                ->orWhereExists(static fn ($earlier) => $earlier
                    ->selectRaw('1')
                    ->from('plan_terms as earlier')
                    ->whereColumn('earlier.scene_id', 'plan_terms.scene_id')
                    ->whereIn('earlier.kind', $photographed)
                    ->whereColumn('earlier.image_url', 'plan_terms.image_url')
                    ->whereColumn('earlier.position', '<', 'plan_terms.position')))
            ->orderBy('plan_terms.scene_id')
            ->orderBy('plan_terms.position')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[$row->scene_id][] = $this->toDomain($row);
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function columns(PlanTerm $term): array
    {
        return [
            'scene_id' => $term->sceneId()->value,
            'kind' => $term->kind()->value,
            'ref' => $term->ref(),
            'position' => $term->position(),
            'text_target' => $term->textTarget(),
            'text_native' => $term->textNative(),
            'pronunciation_native' => $term->pronunciationNative(),
            'definition_target' => $term->definitionTarget(),
            'example_target' => $term->exampleTarget(),
            'example_native' => $term->exampleNative(),
            'speaking_key' => $term->speakingKey(),
            'simplified_variants' => json_encode($term->simplifiedVariants(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'image_prompt' => $term->imagePrompt(),
            'image_url' => $term->image()?->url,
            'image_author' => $term->image()?->author,
            'image_author_url' => $term->image()?->authorUrl,
            'image_tone' => $term->imageTone(),
            'frame_target' => $term->frame()?->frameTarget,
            'frame_native' => $term->frame()?->frameNative,
            'frame_pronunciation_native' => $term->frame()?->pronunciationNative,
            'frame_kind' => $term->frame()?->kind->value,
            'slot' => ($slot = $term->frame()?->slot) === null ? null : json_encode($slot->toArray(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'used_in' => $term->kind() === TermKind::Phrase ? null : json_encode($term->usedIn(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ];
    }

    private function toDomain(PlanTermModel $row): PlanTerm
    {
        return PlanTerm::reconstitute(
            id: PlanTermId::fromString($row->id),
            sceneId: PlanSceneId::fromString($row->scene_id),
            kind: TermKind::from($row->kind),
            ref: $row->ref,
            position: $row->position,
            textTarget: $row->text_target,
            textNative: $row->text_native,
            pronunciationNative: $row->pronunciation_native,
            definitionTarget: $row->definition_target,
            exampleTarget: $row->example_target,
            exampleNative: $row->example_native,
            speakingKey: $row->speaking_key,
            simplifiedVariants: array_map('strval', $row->simplified_variants ?? []),
            imagePrompt: $row->image_prompt,
            image: $row->image_url === null ? null : new Image($row->image_url, $row->image_author, $row->image_author_url, $row->image_tone),
            missingImageTone: $row->image_url === null ? $row->image_tone : null,
            frame: self::frame($row),
            usedIn: array_map('strval', $row->used_in ?? []),
        );
    }

    /** The frame a phrase row keeps — null for a word or a chunk, or a row with no frame kind. */
    private static function frame(PlanTermModel $row): ?Phrase
    {
        $kind = $row->frame_kind === null ? null : ExchangeKind::tryFrom($row->frame_kind);
        if ($kind === null || $row->frame_target === null) {
            return null;
        }
        $slot = null;
        if (is_array($row->slot)) {
            $fillers = [];
            foreach (is_array($row->slot['fillers'] ?? null) ? $row->slot['fillers'] : [] as $filler) {
                if (is_array($filler)) {
                    $fillers[] = new Filler(
                        (string) ($filler['target'] ?? ''),
                        (string) ($filler['native'] ?? ''),
                        (string) ($filler['pronunciation_native'] ?? ''),
                        (bool) ($filler['in_dialogue'] ?? false),
                    );
                }
            }
            $slot = new Slot((string) ($row->slot['hint_native'] ?? ''), $fillers);
        }

        return new Phrase($row->ref, $kind, $row->frame_target, (string) $row->frame_native, (string) $row->frame_pronunciation_native, $slot);
    }
}
