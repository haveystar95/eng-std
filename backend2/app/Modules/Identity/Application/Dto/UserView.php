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
    ) {}
}
