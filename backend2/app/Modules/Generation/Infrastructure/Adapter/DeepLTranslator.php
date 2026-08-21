<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Application\Dto\InstantTranslation;
use App\Modules\Generation\Application\Port\TranslationProvider;
use App\Modules\Observability\Application\Support\OutboundCallContext;
use Illuminate\Support\Facades\Http;

/**
 * DeepL, asked for one short string.
 *
 * ## The timeout is the product
 *
 * Two seconds, and it is the most important number in this class. The hint's whole value is that it
 * lands while the learner is still looking at the field; a translation that arrives after they have
 * moved on is worse than none, because it appears under a word they are no longer typing. So a slow
 * DeepL is treated exactly like an absent one — the request is abandoned and the line stays empty.
 * That is also why there is no retry: a second attempt could only land later than the first.
 *
 * ## Why failures are quiet
 *
 * Every failure path here returns null rather than throwing, with one deliberate exception — a
 * transport error, which the caller catches. Nothing about this feature is load-bearing: the search
 * works, the full lookup works, the learner simply does not get a grey line. A hint that shows an
 * error is a worse hint than no hint.
 *
 * The call is labelled `instant_translation` so it lands in the same outbound log as every model
 * call. It costs no dollars on the free plan, and that is precisely why it needs to be visible:
 * spend that shows up nowhere is spend nobody notices reaching its ceiling.
 */
final class DeepLTranslator implements TranslationProvider
{
    public const NAME = 'deepl';

    /** Long enough for a healthy call (~0.2–0.4 s), short enough to be over before it is noticed. */
    private const TIMEOUT_SECONDS = 2;

    public function __construct(
        private readonly OutboundCallContext $context,
        private readonly string $apiKey,
        private readonly string $baseUrl = 'https://api-free.deepl.com/v2',
    ) {}

    public function isAvailable(): bool
    {
        return trim($this->apiKey) !== '';
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function translate(string $text, string $source, string $target): ?InstantTranslation
    {
        $clean = trim($text);
        if (! $this->isAvailable() || $clean === '') {
            return null;
        }

        $response = $this->context->run('instant_translation', null, fn () => Http::asJson()
            ->withHeaders(['Authorization' => 'DeepL-Auth-Key ' . $this->apiKey])
            ->timeout(self::TIMEOUT_SECONDS)
            ->post(rtrim($this->baseUrl, '/') . '/translate', [
                'text' => [$clean],
                'source_lang' => strtoupper($source),
                'target_lang' => strtoupper($target),
            ]));

        // 456 is DeepL's «quota exceeded». It should be unreachable — the monthly budget stops us
        // at 95% precisely so the vendor never has to — but if it is ever reached, it is still just
        // a hint that did not arrive. Same for 429, 5xx and anything else: no throw, no retry.
        if ($response->failed()) {
            return null;
        }

        $translated = $response->json('translations.0.text');
        if (! is_string($translated) || trim($translated) === '') {
            return null;
        }

        return new InstantTranslation(
            text: trim($translated),
            provider: self::NAME,
            // What was SENT, in characters — the unit the plan is billed in. Counted with mb_strlen
            // because DeepL counts characters and not bytes, and a Cyrillic query would otherwise
            // meter at double its real cost.
            characters: mb_strlen($clean),
        );
    }
}
