<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Console;

use App\Modules\Plan\Domain\Check\Language\LanguagePacks;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\Service\FrameSentences;
use App\Modules\Plan\Domain\Service\FrameText;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * THE TEXTS OF DEALT CARDS, REBUILT FROM THE FRAME AND ITS FILLER (наряд FIX-4 §6) — for the days dealt before one dot was
 * left after an abbreviation. A card keeps the payload it was dealt with, and the vet's day 1 was dealt with the model's
 * «I can come at 3 p.m..» on five cards and more. A string of a card that ends with a doubled full stop, and that is — with
 * one dot less — a sentence of its scene put together from a frame and one of its fillers ({@see FrameSentences}), is
 * written as that sentence; nothing else of the card is touched, nothing is voiced or bought (the sound of a line was
 * bought for its words, and «звук ≠ текст» reads them without the closing mark).
 *
 * Without `--apply` it only prints «план · день · карточка · поле: было → стало»; with it — after the database backup, as
 * for any write to the dev database — it writes. Idempotent: a second run finds nothing.
 */
final class PlanRebuildCardTextsCommand extends Command
{
    protected $signature = 'plan:rebuild-card-texts
        {--plan=* : only these plans (ids); every plan with dealt cards when none is given}
        {--apply : write the rebuilt texts into the dealt cards}';

    protected $description = 'Rebuild the texts of dealt cards that doubled an abbreviation\'s dot, from their frame and filler';

    public function handle(PlanRepository $plans, LanguagePacks $packs): int
    {
        $apply = $this->option('apply') === true;
        /** @var list<string> $only */
        $only = array_values(array_filter((array) $this->option('plan'), is_string(...)));

        $query = DB::table('day_cards as c')->join('plan_days as d', 'd.id', '=', 'c.day_id')
            ->orderBy('d.plan_id')->orderBy('d.number')->orderBy('c.position')->orderBy('c.id')
            ->select(['c.id', 'c.kind', 'c.payload', 'd.plan_id', 'd.number']);
        if ($only !== []) {
            $query->whereIn('d.plan_id', $only);
        }

        $sentences = [];
        $cards = 0;
        $strings = 0;
        foreach ($query->cursor() as $card) {
            $planId = (string) $card->plan_id;
            $sentences[$planId] ??= $this->sentencesOf($plans, $packs, $planId);
            $payload = json_decode((string) $card->payload, true);
            if (! is_array($payload) || $sentences[$planId] === []) {
                continue;
            }
            $changes = [];
            $rebuilt = self::rebuilt($payload, $sentences[$planId], '', '', $changes);
            if ($changes === []) {
                continue;
            }
            foreach ($changes as [$path, $was, $now]) {
                $this->line(sprintf('%sплан %s · день %d · %s %s · %s: «%s» → «%s»', $apply ? '' : '[dry-run] ', $planId, (int) $card->number, $card->kind, $card->id, $path, $was, $now));
            }
            $cards++;
            $strings += count($changes);
            if ($apply) {
                DB::table('day_cards')->where('id', $card->id)->update([
                    'payload' => json_encode($rebuilt, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR),
                ]);
            }
        }

        $this->info(($apply ? 'исправлено' : 'к исправлению').": карточек {$cards}, строк {$strings}");

        return self::SUCCESS;
    }

    /**
     * Every frame-and-filler sentence of every scene of the plan, by the scene — in the plan's languages.
     *
     * @return array<string, array<string, true>> scene id → sentences
     */
    private function sentencesOf(PlanRepository $plans, LanguagePacks $packs, string $planId): array
    {
        $plan = $plans->findById(PlanId::fromString($planId));
        if ($plan === null) {
            return [];
        }
        $target = $packs->for($plan->targetLang()->value)->sentenceEnds();
        $native = $packs->for($plan->nativeLang()->value)->sentenceEnds();
        $out = [];
        foreach ($plan->scenes() as $scene) {
            $lesson = $scene->lesson();
            if ($lesson !== null) {
                $out[$scene->id()->value] = array_fill_keys(FrameSentences::of($lesson, $target, $native), true);
            }
        }

        return $out;
    }

    /**
     * The payload with every string that doubled an abbreviation's dot rebuilt — read in the scene of the nearest node
     * that names one, as a card's sounds are (a page of «Вспомнить» is its own scene's).
     *
     * @param  array<array-key, mixed>  $value
     * @param  array<string, array<string, true>>  $sentences
     * @param  list<array{0: string, 1: string, 2: string}>  $changes
     * @return array<array-key, mixed>
     */
    private static function rebuilt(array $value, array $sentences, string $sceneId, string $path, array &$changes): array
    {
        if (is_string($value['scene_id'] ?? null) && $value['scene_id'] !== '') {
            $sceneId = $value['scene_id'];
        }
        foreach ($value as $key => $item) {
            $at = ltrim($path.'.'.$key, '.');
            if (is_array($item)) {
                $value[$key] = self::rebuilt($item, $sentences, $sceneId, $at, $changes);

                continue;
            }
            if (! is_string($item) || preg_match('/(?<!\.)\.\.\s*$/u', $item) !== 1) {
                continue;
            }
            $one = FrameText::withoutDoubledStop($item);
            if (isset($sentences[$sceneId][$one])) {
                $value[$key] = $one;
                $changes[] = [$at, $item, $one];
            }
        }

        return $value;
    }
}
