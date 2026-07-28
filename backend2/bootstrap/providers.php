<?php

return [
    App\Modules\Collections\Infrastructure\Provider\CollectionsServiceProvider::class,
    App\Modules\Generation\Infrastructure\Provider\GenerationServiceProvider::class,
    App\Modules\Identity\Infrastructure\Provider\IdentityServiceProvider::class,
    App\Modules\Learning\Infrastructure\Provider\LearningServiceProvider::class,
    App\Modules\Observability\Infrastructure\Provider\ObservabilityServiceProvider::class,
    App\Modules\Shared\Infrastructure\Provider\SharedServiceProvider::class,
    App\Modules\Vocabulary\Infrastructure\Provider\VocabularyServiceProvider::class,
    App\Providers\AppServiceProvider::class,
    App\Providers\HorizonServiceProvider::class,
];
