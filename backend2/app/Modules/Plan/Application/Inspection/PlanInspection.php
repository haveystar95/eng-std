<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Inspection;

use App\Modules\Plan\Application\Port\ConversationAudioStore;
use App\Modules\Plan\Application\Port\LineAudioStore;

/**
 * THE PLAN'S PAGE IN THE ADMIN PANEL, READ BY THE MODULE THAT OWNS THE PLAN (наряд ADM-1). Admin calls this and nothing
 * else of Plan for the page: every section is one document, put together here from the plan's own tables and the call
 * journal (through Plan's port). Read-only — nothing here writes, buys or queues.
 *
 * A section takes the plan's id (resolved once from its code) and an optional day — the page's «весь план / день N»
 * filter narrows every section the same way.
 */
final readonly class PlanInspection
{
    public const SECTIONS = ['issues', 'days', 'pipeline', 'lesson', 'passage', 'conversations', 'money'];

    public function __construct(
        private PlanInspectionLoader $loader,
        private CallAttribution $attribution,
        private OverviewReport $overview,
        private PipelineReport $pipeline,
        private LessonReport $lesson,
        private PassageReport $passage,
        private TalkReport $talks,
        private MoneyReport $money,
        private CallJournalReport $journal,
        private IssueReport $issues,
        private LineAudioStore $lineAudios,
        private ConversationAudioStore $talkAudios,
    ) {}

    /**
     * The plan a code (or a full id) names; null — none.
     *
     * @throws PlanCodeAmbiguous
     */
    public function resolve(string $codeOrId): ?string
    {
        return $this->loader->resolve($codeOrId);
    }

    /**
     * The learner's plans, newest first — the «Планы» tab of the learner's card.
     *
     * @return list<array<string, mixed>>
     */
    public function plansOf(string $userId): array
    {
        $out = [];
        foreach ($this->loader->idsOf($userId) as $id) {
            $data = $this->loader->load($id);
            if ($data !== null) {
                $out[] = $this->overview->listRow($data);
            }
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    public function header(string $planId): ?array
    {
        $data = $this->loader->load($planId);

        return $data === null ? null : $this->overview->header($data, []);
    }

    /**
     * One section of the page: `issues`, `days`, `pipeline`, `lesson`, `passage`, `conversations`, `money`.
     *
     * @return array<string, mixed>|null
     */
    public function section(string $planId, string $section, ?int $day): ?array
    {
        $data = $this->loader->load($planId);
        if ($data === null) {
            return null;
        }

        return match ($section) {
            'issues' => $this->issues->of($data, $day, $this->attribution->of($data)),
            'days' => ['data' => $this->overview->days($data, $day, $this->attribution->of($data))],
            'pipeline' => $this->pipeline->of($data, $day, $this->attribution->of($data)),
            'lesson' => ['days' => $this->lesson->of($data, $day)],
            'passage' => $this->passage->of($data, $day),
            'conversations' => ['data' => $this->talks->of($data, $day)],
            'money' => $this->money->of($data, $day, $this->attribution->of($data)),
            default => throw new \InvalidArgumentException("Unknown plan section {$section}"),
        };
    }

    /**
     * The journal of calls, newest first, a page at a time.
     *
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>}|null
     */
    public function calls(string $planId, ?int $day, ?string $source, ?string $cursor, int $limit): ?array
    {
        $data = $this->loader->load($planId);

        return $data === null ? null : $this->journal->of($data, $day, $source, $cursor, $limit, $source === null || $source === 'model' ? $this->attribution->of($data) : []);
    }

    /**
     * The bytes of a sound of this plan — a scene's line or a talk's line — so the page can play it; null when the id is
     * not one of this plan's sounds.
     *
     * @return array{bytes: string, format: string}|null
     */
    public function audio(string $planId, string $audioId): ?array
    {
        $data = $this->loader->load($planId);
        if ($data === null) {
            return null;
        }
        foreach ($data->audios() as $audio) {
            if ($audio->id === $audioId) {
                $row = $this->lineAudios->find($audioId);
                $bytes = $row === null ? null : $this->lineAudios->read($row);

                return $bytes === null ? null : ['bytes' => $bytes, 'format' => $audio->format];
            }
        }
        foreach ($data->talks() as $talk) {
            foreach ($talk->turns as $turn) {
                if ($turn->id === $audioId && $turn->hasAudio) {
                    $bytes = $this->talkAudios->read($audioId);

                    return $bytes === null ? null : ['bytes' => $bytes, 'format' => 'mp3'];
                }
            }
        }

        return null;
    }
}
