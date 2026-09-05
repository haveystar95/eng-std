<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Dto;

/** The user as the mobile client sees it. Built from Eloquent in Infrastructure, never leaked as a model. */
final readonly class UserView
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $email,
        public ?string $avatar,
        public ?ProfileView $profile,
        /**
         * ЭТОЙ СЕССИИ РАЗРЕШЕНЫ QA-ИНСТРУМЕНТЫ — та же дверь, что у входа без пароля.
         *
         * Не «этот аккаунт помечен `is_qa`», а ОБА замка сразу: аккаунт помечен И дверь открыта
         * ({@see \App\Modules\Identity\Domain\Service\DevLoginGate} — среда не production и
         * включён явный флаг). Одно поле вместо двух потому, что клиенту нужен один ответ: можно
         * ли показать инструмент, которого у человека быть не должно. В production здесь всегда
         * `false`, что бы ни стояло в базе.
         */
        public bool $qaTools = false,
    ) {}
}
