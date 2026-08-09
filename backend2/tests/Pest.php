<?php

declare(strict_types=1);

use App\Modules\Admin\Infrastructure\Eloquent\Admin;
use App\Modules\Collections\Application\Command\AddWordToCollection;
use App\Modules\Collections\Application\Command\AddWordToCollectionHandler;
use App\Modules\Collections\Application\Command\CreateCustomCollection;
use App\Modules\Collections\Application\Command\CreateCustomCollectionHandler;
use App\Modules\Identity\Infrastructure\Eloquent\User;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');

/**
 * Create a back-office admin and return it with a fresh bearer token. Password is fixed so
 * credential tests can log in with it.
 *
 * @return array{0: Admin, 1: string}
 */
function adminActor(string $email = 'root@wt.test'): array
{
    $admin = Admin::create(['email' => $email, 'name' => 'Root', 'password' => 'secret123']);

    return [$admin, $admin->createToken('panel')->plainTextToken];
}

/**
 * A study term added to a (new) custom collection for the user, without HTTP.
 *
 * @return array{0: string, 1: string}  [collectionId, termId]
 */
function adminSeedTerm(User $user, string $title, string $text, string $translation = 'x'): array
{
    $actor = UserId::fromString($user->id);
    $collectionId = app(CreateCustomCollectionHandler::class)(new CreateCustomCollection(
        $actor, $title, new LanguageCode('ru'), new LanguageCode('en'),
    ));
    $termId = app(AddWordToCollectionHandler::class)(new AddWordToCollection($collectionId, $actor, $text, $translation))->value;

    return [$collectionId->value, $termId];
}
