<?php

declare(strict_types=1);

namespace App\Modules\Generation\Presentation\Http\Controller;

use App\Modules\Generation\Application\Command\AppendDialogTranscripts;
use App\Modules\Generation\Application\Command\AppendDialogTranscriptsHandler;
use App\Modules\Generation\Application\Command\FinishPracticeDialog;
use App\Modules\Generation\Application\Command\FinishPracticeDialogHandler;
use App\Modules\Generation\Application\Command\StartPracticeDialog;
use App\Modules\Generation\Application\Command\StartPracticeDialogHandler;
use App\Modules\Generation\Application\Query\GetLastCollectionDialog;
use App\Modules\Generation\Application\Query\GetLastCollectionDialogHandler;
use App\Modules\Generation\Application\Dto\DialogTranscriptEvent;
use App\Modules\Generation\Application\Dto\StartedDialogView;
use App\Modules\Generation\Application\Dto\TargetWordView;
use App\Modules\Generation\Presentation\Http\Request\AppendTranscriptsRequest;
use App\Modules\Generation\Presentation\Http\Request\StartPracticeDialogRequest;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\PracticeDialogId;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Realtime practice dialogs (premium). Audio never transits this server — we hand the client an
 * ephemeral OpenAI token, ingest transcripts to score target-word coverage, and recap on finish.
 * The response bodies match the fixed client contract verbatim (top-level, no `data` envelope).
 */
final class PracticeDialogController
{
    public function __construct(
        private readonly StartPracticeDialogHandler $start,
        private readonly AppendDialogTranscriptsHandler $append,
        private readonly FinishPracticeDialogHandler $finish,
        private readonly GetLastCollectionDialogHandler $lastForCollection,
    ) {}

    public function store(StartPracticeDialogRequest $request): JsonResponse
    {
        $data = $request->validated();

        $view = ($this->start)(new StartPracticeDialog(
            userId: $this->actorId($request),
            collectionId: CollectionId::fromString((string) $data['collection_id']),
            id: PracticeDialogId::fromString((string) $data['client_id']),
        ));

        if ($view === null) {
            throw new NotFoundHttpException(); // collection not found / not owned
        }

        return new JsonResponse($this->startedBody($view), Response::HTTP_CREATED);
    }

    public function transcripts(AppendTranscriptsRequest $request, string $id): JsonResponse
    {
        $coverage = ($this->append)(new AppendDialogTranscripts(
            userId: $this->actorId($request),
            dialogId: $this->dialogId($id),
            events: $this->events($request->validated()['events'] ?? []),
        ));

        return new JsonResponse(['target_words' => $this->targetWords($coverage)]);
    }

    public function finish(Request $request, string $id): JsonResponse
    {
        $view = ($this->finish)(new FinishPracticeDialog(
            userId: $this->actorId($request),
            dialogId: $this->dialogId($id),
        ));

        return new JsonResponse([
            'summary' => $view->summary,
            'words_used' => $view->wordsUsed,
            'words_total' => $view->wordsTotal,
        ]);
    }

    public function lastForCollection(Request $request, string $collectionId): JsonResponse
    {
        if (! Ulid::isValid($collectionId)) {
            throw new NotFoundHttpException();
        }

        $view = ($this->lastForCollection)(new GetLastCollectionDialog(
            userId: $this->actorId($request),
            collectionId: CollectionId::fromString($collectionId),
        ));

        if ($view === null) {
            throw new NotFoundHttpException(); // no concluded dialog for this collection yet
        }

        return new JsonResponse([
            'finished_at' => $view->finishedAt?->format(DateTimeInterface::ATOM),
            'words_used' => $view->wordsUsed,
            'words_total' => $view->wordsTotal,
            'summary' => $view->summary,
        ]);
    }

    /** @return array<string, mixed> */
    private function startedBody(StartedDialogView $view): array
    {
        return [
            'dialog_id' => $view->dialogId,
            'provider' => $view->provider,
            'endpoint' => $view->endpoint,
            'realtime_token' => $view->realtimeToken,
            'expires_at' => $view->expiresAt->format(DateTimeInterface::ATOM),
            'model' => $view->model,
            'target_words' => $this->targetWords($view->targetWords),
            'duration_seconds' => $view->durationSeconds,
            // Gemini bare-token path: the setup the client applies verbatim on connect. Null for
            // providers that bake the session into the token (OpenAI).
            'session_setup' => $view->sessionSetup,
        ];
    }

    /**
     * @param  list<TargetWordView>  $words
     * @return list<array{term_id: string, text: string, used: bool}>
     */
    private function targetWords(array $words): array
    {
        return array_map(static fn (TargetWordView $w): array => [
            'term_id' => $w->termId,
            'text' => $w->text,
            'used' => $w->used,
        ], $words);
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     * @return list<DialogTranscriptEvent>
     */
    private function events(array $events): array
    {
        return array_values(array_map(static fn (array $e): DialogTranscriptEvent => new DialogTranscriptEvent(
            role: (string) $e['role'],
            text: (string) $e['text'],
            ts: (int) $e['ts'],
        ), $events));
    }

    private function dialogId(string $id): PracticeDialogId
    {
        if (! Ulid::isValid($id)) {
            throw new NotFoundHttpException();
        }

        return PracticeDialogId::fromString($id);
    }

    private function actorId(Request $request): UserId
    {
        return UserId::fromString((string) $request->user()?->getAuthIdentifier());
    }
}
