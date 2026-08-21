<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Service;

use App\Modules\Generation\Application\Dto\PlaygroundAnswer;
use App\Modules\Generation\Application\Port\PlaygroundModelCatalog;
use App\Modules\Generation\Domain\ValueObject\ProviderId;
use App\Modules\Shared\Domain\Service\ModelCost;
use Throwable;

/**
 * One sandbox round-trip: send the prompt as written, price what it cost, and try to read the answer
 * as JSON without insisting that it is.
 *
 * Everything that can go wrong comes back as TEXT rather than as an exception, and that is the
 * design rather than laziness. A sandbox is where someone finds out that a key expired, that a model
 * name is wrong, that the org is out of credits — every one of those is an answer to the experiment
 * being run, and a 500 would turn it into "the panel is broken".
 */
final readonly class PlaygroundCall
{
    public function __construct(
        private PlaygroundModelCatalog $catalog,
        private ModelCost $cost = new ModelCost(),
    ) {}

    /** @param  string  $provider  the wire name; an unknown one is answered, not thrown. */
    public function run(string $provider, string $model, string $prompt, ?float $temperature = null): PlaygroundAnswer
    {
        $id = ProviderId::tryFrom($provider);
        if ($id === null) {
            return $this->failed($provider, $model, "неизвестный провайдер «{$provider}».");
        }

        $port = $this->catalog->get($id, $model);
        if ($port === null) {
            return $this->failed($provider, $model, $this->unavailableReason($id, $model));
        }

        try {
            $reply = $port->ask($prompt, $temperature);
        } catch (Throwable $e) {
            return $this->failed($provider, $model, $e->getMessage());
        }

        [$parsed, $parseError] = $this->readJson($reply->rawText);

        return new PlaygroundAnswer(
            provider: $provider,
            model: $reply->model,
            rawText: $reply->rawText,
            parsedJson: $parsed,
            parseError: $parseError,
            tokensIn: $reply->tokensIn,
            tokensOut: $reply->tokensOut,
            // The app's ONE pricing table, the same one the request log and the ledgers use — a
            // sandbox that priced calls its own way would disagree with the cost screens by design.
            costUsd: $this->cost->estimate($reply->model, $reply->tokensIn, $reply->tokensOut),
            latencyMs: $reply->latencyMs,
        );
    }

    /**
     * The answer as JSON, or why it is not.
     *
     * Two attempts, in this order: the text as it stands, then the contents of a fenced code block.
     * The second exists because models wrap JSON in ```json fences constantly, and a sandbox that
     * called that "malformed" would be reporting its own strictness as the model's failure. The raw
     * text is shown beside the tree either way, so nothing is hidden by unwrapping it.
     *
     * A JSON scalar (`"ok"`, `42`) is reported as unparsed on purpose: there is no tree to render and
     * no items to validate, and the raw text already says everything there is to say.
     *
     * @return array{0: array<mixed>|null, 1: string|null}
     */
    private function readJson(string $text): array
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return [null, 'пустой ответ.'];
        }

        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            return [$decoded, null];
        }
        $firstError = json_last_error() === JSON_ERROR_NONE
            ? 'ответ — не объект и не массив JSON.'
            : json_last_error_msg();

        $fenced = $this->fencedBlock($trimmed);
        if ($fenced !== null) {
            $decoded = json_decode($fenced, true);
            if (is_array($decoded)) {
                return [$decoded, null];
            }
        }

        return [null, $firstError];
    }

    /** The body of the first ```-fenced block, language tag ignored. */
    private function fencedBlock(string $text): ?string
    {
        if (preg_match('/```[a-zA-Z]*\s*\n(.*?)```/s', $text, $m) === 1) {
            return trim($m[1]);
        }

        return null;
    }

    private function failed(string $provider, string $model, string $error): PlaygroundAnswer
    {
        return new PlaygroundAnswer(
            provider: $provider,
            model: $model,
            rawText: '',
            parsedJson: null,
            parseError: null,
            tokensIn: null,
            tokensOut: null,
            costUsd: null,
            latencyMs: 0,
            error: $error,
        );
    }

    /** Why the catalogue refused: a missing key and an unlisted model are different fixes. */
    private function unavailableReason(ProviderId $provider, string $model): string
    {
        foreach ($this->catalog->providers() as $row) {
            if ($row->provider !== $provider->value) {
                continue;
            }

            return $row->available
                ? "модель «{$model}» не входит в список песочницы для «{$row->label}»."
                : "{$row->label}: {$row->reason}";
        }

        return "провайдер «{$provider->value}» не подключён к песочнице.";
    }
}
