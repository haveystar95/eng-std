<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\ValueObject;

/**
 * ONE TURN OF THE SCENE'S CONVERSATION — whose it is, and which card of the day it is.
 *
 * The unit of the trainer is not a card but an EXCHANGE (`docs/plan-dialogue.md` §1): the other
 * person says something, you answer, they react. P2 v0.5 answers with the ORDER those turns happen
 * in ({@see \App\Modules\Generation\Application\Service\PlanSchemas::day()}), as refs into the
 * shelves it has already written — `hear[0]`, `say[2]`, `ask[1]`.
 *
 * ## A ref, and never a copy of the line
 *
 * «dialogue only orders the shelves» is the prompt's own sentence, and this class is what makes it
 * mechanical: a turn carries an ADDRESS and no text at all. A chain that carried its own sentences
 * would be a second place a line of the scene is written, the day would have two answers to «what
 * does the interlocutor say», and the ladder — which lives on the shelves — would be climbing one
 * of them while the screen played the other.
 *
 * ## Parsed here, judged there
 *
 * {@see fromArray()} never refuses: an unparseable ref becomes a turn with no shelf and no index,
 * and the VERDICT on it belongs to
 * {@see \App\Modules\Generation\Domain\Service\PlanDayValidator::DIALOGUE_REF_INVALID}. Parsing
 * that threw would make «the model wrote nonsense» indistinguishable from «the code crashed», on
 * the one path where the difference decides whether the learner's money bought anything.
 */
final readonly class PlanDialogueTurn
{
    /** The other person's turn — a ref into `hear`, and nothing else. */
    public const ROLE = 'role';

    /** The learner's own turn — a ref into `say` or `ask`. */
    public const YOU = 'you';

    /** `hear[0]`, `say[12]` — the one form a ref may take. */
    private const REF = '/^([a-z_]+)\[(\d+)\]$/';

    public function __construct(
        /** {@see ROLE} or {@see YOU}; anything else is the model inventing a third speaker. */
        public string $turn,
        /** The ref exactly as the model wrote it — what a violation quotes back to a person. */
        public string $ref,
        /** The shelf the ref names, or null when it names nothing this build knows. */
        public ?PlanShelf $shelf,
        /** The position on that shelf, or null when the ref did not parse. */
        public ?int $index,
    ) {}

    /**
     * One entry of the `dialogue` array, as it came off the wire.
     *
     * @param  array<string, mixed>  $raw
     */
    public static function fromArray(array $raw): self
    {
        $turn = is_string($raw['turn'] ?? null) ? mb_strtolower(trim($raw['turn'])) : '';
        $ref = is_string($raw['ref'] ?? null) ? mb_strtolower(trim($raw['ref'])) : '';

        $matches = [];
        if (preg_match(self::REF, $ref, $matches) !== 1) {
            return new self($turn, $ref, null, null);
        }

        return new self($turn, $ref, PlanShelf::tryFromName($matches[1]), (int) $matches[2]);
    }

    /** Whose side of the conversation this turn is — and `false` for a word the model made up. */
    public function isRole(): bool
    {
        return $this->turn === self::ROLE;
    }

    public function isLearner(): bool
    {
        return $this->turn === self::YOU;
    }

    /**
     * The shelves this turn is ALLOWED to point at — the alternation stated as a fact about a turn
     * rather than as a comparison between two of them.
     *
     * @return list<PlanShelf>
     */
    public function allowedShelves(): array
    {
        return $this->isRole()
            ? [PlanShelf::Hear]
            : [PlanShelf::Say, PlanShelf::Ask];
    }

    /** Does the ref name a shelf this turn's side may speak from? */
    public function shelfFits(): bool
    {
        return $this->shelf !== null && in_array($this->shelf, $this->allowedShelves(), true);
    }

    /** `hear#0` — the key a card is found by, and null when the ref did not parse. */
    public function address(): ?string
    {
        return $this->shelf === null || $this->index === null
            ? null
            : $this->shelf->value . '#' . $this->index;
    }
}
