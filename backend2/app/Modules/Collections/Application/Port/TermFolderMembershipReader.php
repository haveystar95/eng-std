<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Port;

use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * Which of THIS learner's own folders already hold each of these terms.
 *
 * The one fact a search result needs that Vocabulary cannot supply: «уже в такой-то твоей папке».
 * Without it the main button on a search card would offer to save a word that is already saved, and
 * a one-tap save that silently does nothing is the worst kind of button.
 *
 * Owned folders only — a store deck the learner is subscribed to is not somewhere they PUT a word,
 * so listing it here would offer «уже в „Аэропорт"» for a catalogue they cannot edit.
 */
interface TermFolderMembershipReader
{
    /**
     * @param  list<string>  $termIds
     * @return array<string, list<array{id: string, title: string, is_default: bool}>>
     *         term id => the owner's folders holding it, in the order the shelf shows them
     */
    public function foldersHolding(UserId $userId, array $termIds): array;
}
