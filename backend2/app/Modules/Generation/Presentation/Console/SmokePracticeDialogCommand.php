<?php

declare(strict_types=1);

namespace App\Modules\Generation\Presentation\Console;

use App\Modules\Generation\Application\Dto\RealtimeSessionSpec;
use App\Modules\Generation\Application\Dto\RealtimeVad;
use App\Modules\Generation\Application\Port\RealtimeTokenPort;
use App\Modules\Generation\Domain\Service\ModelCost;
use Illuminate\Console\Command;
use Throwable;

/**
 * Smoke test on a live key: mint a REAL ephemeral realtime token (no audio is exchanged), print that
 * the provider accepted the session, and show the estimated cost of a full-TTL session. Pick the
 * provider with --driver=openai|gemini (defaults to the configured PRACTICE_DRIVER); the matching
 * key must be set (OPENAI_API_KEY / GEMINI_API_KEY). Uses an A2 lesson to exercise the level rules.
 */
final class SmokePracticeDialogCommand extends Command
{
    protected $signature = 'practice:smoke
        {--driver= : openai | gemini (defaults to config)}
        {--voice= : override the realtime voice}
        {--model= : override the realtime model}';

    protected $description = 'Mint a real realtime token (no audio) via a provider and print a cost line';

    public function handle(ModelCost $cost): int
    {
        $driver = $this->stringOption('driver') ?? (string) config('services.practice.driver', 'openai');
        config(['services.practice.driver' => $driver]); // re-bind RealtimeTokenPort to this driver

        $model = $this->stringOption('model') ?? ($driver === 'gemini'
            ? (string) config('services.practice.gemini_model', 'gemini-3.1-flash-live-preview')
            : (string) config('services.practice.realtime_model', 'gpt-realtime-2.1-mini'));
        $voice = $this->stringOption('voice') ?? (string) config('services.practice.voice', 'alloy');
        $ttl = (int) config('services.practice.dialog_ttl_seconds', 200);

        $lesson = [
            'topic' => 'At the bank',
            'level' => 'A2',
            'native' => 'ru',
            'target' => 'en',
            'model' => $model,
            'target_words' => [
                ['term_id' => '00000000000000000000000000', 'text' => 'withdraw cash', 'forms' => ['withdraw cash']],
                ['term_id' => '00000000000000000000000001', 'text' => 'account', 'forms' => ['account']],
            ],
            'rules' => ['roleplay' => 'At the bank'],
        ];

        $spec = new RealtimeSessionSpec(
            model: $model,
            transcribeModel: (string) config('services.practice.transcribe_model', 'gpt-4o-mini-transcribe'),
            voice: $voice,
            ttlSeconds: $ttl,
            vad: new RealtimeVad(
                silenceMs: (int) config('services.practice.vad_silence_ms', 900),
                threshold: (float) config('services.practice.vad_threshold', 0.5),
                prefixPaddingMs: (int) config('services.practice.vad_prefix_padding_ms', 300),
            ),
            lesson: $lesson,
            speed: (float) config('services.practice.slow_speed', 0.9), // A2 lesson → slow
        );

        $this->info("Minting a realtime token (driver={$driver}, model={$model}, voice={$voice}, ttl={$ttl}s)…");

        try {
            $token = $this->laravel->make(RealtimeTokenPort::class)->mint($spec);
        } catch (Throwable $e) {
            $this->error('Mint failed: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info("✓ {$token->provider} accepted the session.");
        $this->line('  provider:   ' . $token->provider);
        $this->line('  endpoint:   ' . $token->endpoint);
        $this->line('  token:      ' . substr($token->value, 0, 12) . '… (' . strlen($token->value) . ' chars)');
        $this->line('  expires_at: ' . $token->expiresAt->format(DATE_ATOM));
        $this->line('  model:      ' . $token->model);

        // A representative full-TTL session: input audio the whole time, agent speaking ~half.
        $estimate = $cost->estimateRealtime($token->model, $ttl, intdiv($ttl, 2), 200, 200);
        $this->line('  est. cost:  ' . ($estimate ?? 'unknown (no rate for this model)') . ' USD (estimated, full-TTL, agent ~50% talk)');

        return self::SUCCESS;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
