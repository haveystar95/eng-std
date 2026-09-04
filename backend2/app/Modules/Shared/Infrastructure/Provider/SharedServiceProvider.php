<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Provider;

use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\Service\DistractorLength;
use App\Modules\Shared\Domain\Service\TransactionManager;
use App\Modules\Shared\Domain\Service\VoiceCatalog;
use App\Modules\Shared\Infrastructure\IlluminateTransactionManager;
use App\Modules\Shared\Infrastructure\SystemClock;
use Illuminate\Support\ServiceProvider;

final class SharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Clock::class, SystemClock::class);
        $this->app->bind(TransactionManager::class, IlluminateTransactionManager::class);

        // The length band's two thresholds are a product judgement, so they are configuration and
        // the Domain never reads them itself — it is handed them here, once, for everyone who asks.
        $this->app->singleton(DistractorLength::class, static fn (): DistractorLength => new DistractorLength(
            charTolerance: (float) config('learning.distractor_length.char_tolerance', DistractorLength::DEFAULT_CHAR_TOLERANCE),
            wordTolerance: (float) config('learning.distractor_length.word_tolerance', DistractorLength::DEFAULT_WORD_TOLERANCE),
        ));

        // Голос языкового пакета (наряд TTS-1), по той же причине и в той же форме: какой голос
        // читает реплики — продуктовое решение, и Domain получает таблицу, а не читает конфиг.
        // ОДНА строка на всё приложение: станок покупает за этот ключ, сборка посадки за него же
        // ищет; два места, читающие конфиг порознь, — это два ключа, которые однажды разойдутся.
        $this->app->singleton(VoiceCatalog::class, static fn (): VoiceCatalog => new VoiceCatalog(
            (array) config('generation.speech.voices', []),
        ));
    }
}
