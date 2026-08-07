<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

use App\Modules\Collections\Application\Dto\CollectionView;
use App\Modules\Collections\Application\Query\GetCollection;
use App\Modules\Collections\Application\Query\GetCollectionHandler;
use App\Modules\Generation\Application\Dto\PracticeDialogConfig;
use App\Modules\Generation\Application\Dto\RealtimeSessionSpec;
use App\Modules\Generation\Application\Dto\RealtimeToken;
use App\Modules\Generation\Application\Dto\StartedDialogView;
use App\Modules\Generation\Application\Dto\TargetWordView;
use App\Modules\Generation\Application\Port\PracticeQuota;
use App\Modules\Generation\Application\Port\RealtimeTokenPort;
use App\Modules\Generation\Domain\Entity\PracticeDialog;
use App\Modules\Generation\Domain\Exception\PracticeDialogIdConflict;
use App\Modules\Generation\Domain\Exception\PracticeDialogsQuotaExceeded;
use App\Modules\Generation\Domain\Exception\PracticeSubscriptionRequired;
use App\Modules\Generation\Domain\Repository\PracticeDialogRepository;
use App\Modules\Generation\Domain\Service\PracticeDailyLimit;
use App\Modules\Generation\Domain\Service\TargetWordPrioritizer;
use App\Modules\Generation\Domain\ValueObject\PracticeDialogStatus;
use App\Modules\Identity\Application\Port\UserReader;
use App\Modules\Identity\Application\Port\UserTierReader;
use App\Modules\Learning\Application\Port\DueTermsReader;
use App\Modules\Learning\Application\Port\ProgressExistenceReader;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Query\TermAnswerKeyReader;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Starts a realtime practice dialog in one call: premium gate → daily limit → assemble the lesson
 * from the collection (topic, level, langs, prioritised target words) → mint an ephemeral realtime
 * token → record the dialog. This is PRACTICE — it never writes a review or (user, term) progress;
 * it only READS the learner's due/started state to decide which words to rehearse.
 *
 * A client-id retry of a still-live dialog re-mints a fresh token for the same lesson without
 * charging quota again; a spent or cross-user id is a 409.
 */
final readonly class StartPracticeDialogHandler
{
    private const DEFAULT_CEFR = 'B1';

    public function __construct(
        private PracticeDialogRepository $dialogs,
        private UserTierReader $tiers,
        private UserReader $users,
        private GetCollectionHandler $collections,
        private DueTermsReader $dueTerms,
        private ProgressExistenceReader $progress,
        private TermAnswerKeyReader $answerKeys,
        private TargetWordPrioritizer $prioritizer,
        private RealtimeTokenPort $realtime,
        private PracticeQuota $quota,
        private PracticeDailyLimit $limit,
        private PracticeDialogConfig $config,
        private Clock $clock,
    ) {}

    public function __invoke(StartPracticeDialog $command): ?StartedDialogView
    {
        $now = $this->clock->now();

        // Idempotent retry / conflict — before the gate, like generation: a repeat must not spend.
        $existing = $this->dialogs->findById($command->id);
        if ($existing !== null) {
            if (! $existing->userId()->equals($command->userId)) {
                throw PracticeDialogIdConflict::make();
            }
            if ($existing->status() === PracticeDialogStatus::Active && ! $existing->isExpiredAt($now)) {
                $token = $this->mint($existing->lesson());
                $existing->renew($token->expiresAt);
                $this->dialogs->save($existing);

                return $this->startedView($command->id->value, $existing->lesson(), $token);
            }

            throw PracticeDialogIdConflict::make();
        }

        // 1. Premium gate, then the daily limit.
        if (! $this->tiers->tierOf($command->userId)->isPremium()) {
            throw PracticeSubscriptionRequired::forPractice();
        }

        $perDay = $this->limit->perDay();
        if ($this->quota->usedOn($command->userId, $now) >= $perDay) {
            throw PracticeDialogsQuotaExceeded::perDay($perDay, $this->nextUtcMidnight($now));
        }

        // 2. Assemble the lesson from the collection (owner-scoped: null → 404 at the edge).
        $collection = ($this->collections)(new GetCollection($command->collectionId, $command->userId));
        if ($collection === null) {
            return null;
        }

        $lesson = $this->buildLesson($command, $collection, $now);

        // 3 & 4. Mint the ephemeral token, briefed with the lesson, then record the dialog.
        $token = $this->mint($lesson);

        $dialog = PracticeDialog::open(
            id: $command->id,
            userId: $command->userId,
            collectionId: $command->collectionId,
            lesson: $lesson,
            expiresAt: $token->expiresAt,
            createdAt: $now,
        );
        $this->dialogs->save($dialog);

        return $this->startedView($command->id->value, $lesson, $token);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLesson(StartPracticeDialog $command, CollectionView $collection, DateTimeImmutable $now): array
    {
        // Terms that carry text, in collection order, with a term_id → text lookup.
        $termIds = [];
        $textOf = [];
        foreach ($collection->items as $item) {
            if ($item->text === null || $item->text === '') {
                continue;
            }
            $termIds[] = $item->termId;
            $textOf[$item->termId] = $item->text;
        }

        // Priority: learning/due → new → known, capped at the configured max.
        $due = array_map(
            static fn ($v): string => $v->termId->value,
            $this->dueTerms->dueAmong($command->userId, $now, $termIds, $this->config->maxTargetWords),
        );
        $started = $this->progress->existingTermIds($command->userId, $termIds);
        $selected = $this->prioritizer->select($termIds, $due, $started, $this->config->maxTargetWords);

        // Accepted forms per selected term — the coverage check matches these, not just the text.
        $keys = $this->answerKeys->byIds(array_map(static fn (string $id): TermId => TermId::fromString($id), $selected));

        $targetWords = [];
        foreach ($selected as $id) {
            $forms = isset($keys[$id]) && $keys[$id]->accepted !== [] ? $keys[$id]->accepted : [$textOf[$id]];
            $targetWords[] = [
                'term_id' => $id,
                'text' => $textOf[$id],
                'forms' => $forms,
            ];
        }

        $user = $this->users->byId($command->userId);
        $cefr = $user !== null && $user->profile !== null ? $user->profile->cefrLevel : self::DEFAULT_CEFR;

        return [
            'topic' => $collection->title,
            'level' => $cefr,
            'native' => $collection->sourceLang,
            'target' => $collection->targetLang,
            'model' => $this->config->realtimeModel,
            'target_words' => $targetWords,
            'rules' => [
                'speak_only_target_language' => true,
                'correct_after_reply' => true,
                'ask_one_question_at_a_time' => true,
                'require_all_words' => true,
                'roleplay' => $collection->title,
            ],
        ];
    }

    /** @param array<string, mixed> $lesson */
    private function mint(array $lesson): RealtimeToken
    {
        return $this->realtime->mint(new RealtimeSessionSpec(
            model: $this->config->realtimeModel,
            transcribeModel: $this->config->transcribeModel,
            voice: $this->config->voice,
            ttlSeconds: $this->config->ttlSeconds,
            lesson: $lesson,
        ));
    }

    /**
     * @param  array<string, mixed>  $lesson
     */
    private function startedView(string $dialogId, array $lesson, RealtimeToken $token): StartedDialogView
    {
        $targetWords = [];
        /** @var list<array{term_id: string, text: string, forms: list<string>}> $words */
        $words = $lesson['target_words'] ?? [];
        foreach ($words as $word) {
            $targetWords[] = new TargetWordView($word['term_id'], $word['text'], used: false);
        }

        return new StartedDialogView(
            dialogId: $dialogId,
            realtimeToken: $token->value,
            expiresAt: $token->expiresAt,
            model: $token->model,
            targetWords: $targetWords,
            durationSeconds: $this->config->ttlSeconds,
        );
    }

    private function nextUtcMidnight(DateTimeImmutable $now): DateTimeImmutable
    {
        return $now->setTimezone(new DateTimeZone('UTC'))->setTime(0, 0)->modify('+1 day');
    }
}
