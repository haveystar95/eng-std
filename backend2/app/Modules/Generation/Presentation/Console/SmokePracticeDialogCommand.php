<?php

declare(strict_types=1);

namespace App\Modules\Generation\Presentation\Console;

use App\Modules\Generation\Application\Dto\PracticeDialogConfig;
use App\Modules\Generation\Application\Dto\RealtimeSessionSpec;
use App\Modules\Generation\Application\Port\RealtimeTokenPort;
use App\Modules\Generation\Domain\Service\ModelCost;
use Illuminate\Console\Command;
use Throwable;

/**
 * Smoke test on the live key: mint a REAL ephemeral realtime token (no audio is exchanged), print
 * that OpenAI accepted the session, and show the estimated cost of a full-TTL session. Uses the
 * configured realtime driver — run with PRACTICE_DRIVER=openai and a real OPENAI_API_KEY.
 */
final class SmokePracticeDialogCommand extends Command
{
    protected $signature = 'practice:smoke {--voice= : override the realtime voice} {--model= : override the realtime model}';

    protected $description = 'Mint a real realtime token (no audio) and print an estimated cost line';

    public function handle(RealtimeTokenPort $realtime, PracticeDialogConfig $config, ModelCost $cost): int
    {
        $model = $this->stringOption('model') ?? $config->realtimeModel;
        $voice = $this->stringOption('voice') ?? $config->voice;

        $lesson = [
            'topic' => 'At the bank',
            'level' => 'B1',
            'native' => 'ru',
            'target' => 'en',
            'model' => $model,
            'target_words' => [
                ['term_id' => '00000000000000000000000000', 'text' => 'withdraw cash', 'forms' => ['withdraw cash']],
                ['term_id' => '00000000000000000000000001', 'text' => 'account balance', 'forms' => ['account balance']],
            ],
            'rules' => [
                'speak_only_target_language' => true,
                'correct_after_reply' => true,
                'ask_one_question_at_a_time' => true,
                'require_all_words' => true,
                'roleplay' => 'At the bank',
            ],
        ];

        $this->info("Minting a realtime token (model={$model}, voice={$voice}, ttl={$config->ttlSeconds}s)…");

        try {
            $token = $realtime->mint(new RealtimeSessionSpec(
                model: $model,
                transcribeModel: $config->transcribeModel,
                voice: $voice,
                ttlSeconds: $config->ttlSeconds,
                vad: $config->vad,
                lesson: $lesson,
            ));
        } catch (Throwable $e) {
            $this->error('Mint failed: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info('✓ OpenAI accepted the session.');
        $this->line('  token:      ' . substr($token->value, 0, 12) . '… (' . strlen($token->value) . ' chars)');
        $this->line('  expires_at: ' . $token->expiresAt->format(DATE_ATOM));
        $this->line('  model:      ' . $token->model);

        // A representative full-TTL session with a small transcript on each side.
        $estimate = $cost->estimateRealtime($token->model, $config->ttlSeconds, 200, 200);
        $this->line('  est. cost:  ' . ($estimate ?? 'unknown (no rate for this model)') . ' USD (estimated, full-TTL session)');

        return self::SUCCESS;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
