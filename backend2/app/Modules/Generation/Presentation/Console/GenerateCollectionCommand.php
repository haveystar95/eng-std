<?php

declare(strict_types=1);

namespace App\Modules\Generation\Presentation\Console;

use App\Modules\Generation\Application\Command\FailGeneration;
use App\Modules\Generation\Application\Command\FailGenerationHandler;
use App\Modules\Generation\Application\Command\ProcessGeneration;
use App\Modules\Generation\Application\Command\ProcessGenerationHandler;
use App\Modules\Generation\Application\Command\RequestCollectionGeneration;
use App\Modules\Generation\Application\Command\RequestCollectionGenerationHandler;
use App\Modules\Generation\Application\Query\GetGenerationRequest;
use App\Modules\Generation\Application\Query\GetGenerationRequestHandler;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Console\Command;
use Throwable;

/**
 * Runs a generation synchronously (no queue) and prints the result — handy for trying
 * prompts from the terminal without the mobile app. Same Application handlers as the API.
 */
final class GenerateCollectionCommand extends Command
{
    protected $signature = 'generation:make
        {user : owner user id (ULID)}
        {prompt : topic or situation, e.g. "иду в банк"}
        {--levels=A2,B1 : comma-separated CEFR levels}
        {--size=12 : number of items (8–25)}
        {--source=ru : source (native) language}
        {--target=en : target (learned) language}';

    protected $description = 'Generate a vocabulary collection from a prompt and print the result';

    public function handle(
        RequestCollectionGenerationHandler $request,
        ProcessGenerationHandler $process,
        GetGenerationRequestHandler $get,
        FailGenerationHandler $fail,
    ): int {
        $actor = UserId::fromString($this->asString($this->argument('user')));
        $levels = array_values(array_filter(array_map('trim', explode(',', $this->asString($this->option('levels'))))));

        $id = $request(new RequestCollectionGeneration(
            userId: $actor,
            prompt: $this->asString($this->argument('prompt')),
            sourceLang: new LanguageCode($this->asString($this->option('source'))),
            targetLang: new LanguageCode($this->asString($this->option('target'))),
            levels: $levels === [] ? ['A2', 'B1'] : $levels,
            size: (int) $this->asString($this->option('size')),
        ))->id;

        $this->info("Generating (request {$id->value})…");

        try {
            $process(new ProcessGeneration($id));
        } catch (Throwable $e) {
            $fail(new FailGeneration($id, $e->getMessage()));
            $this->error('Generation failed: ' . $e->getMessage());

            return self::FAILURE;
        }

        $view = $get(new GetGenerationRequest($id, $actor));
        if ($view === null) {
            $this->warn('Could not read the generation result.');

            return self::SUCCESS;
        }

        $this->info('Status: ' . $view->status);
        $this->line('Collection: ' . ($view->collectionId ?? '—'));
        $this->line('Model: ' . ($view->model ?? '—') . '  cost: $' . ($view->costUsd ?? '0'));

        return self::SUCCESS;
    }

    private function asString(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
