<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Language;

/**
 * WHICH LANGUAGE A TEXT IS WRITTEN IN, AS FAR AS ITS LETTERS AND ITS FREQUENT WORDS TELL — one reading for every rule that
 * asks «is this line in the language it should be in?»: the guard of the role's translation
 * ({@see \App\Modules\Plan\Domain\Service\ReplyNative}, наряд LANG-1 §5) and the language of a word's definition
 * (`vocab.definition_language`, наряд LANG-1b §4). Each rule keeps its own threshold; the reading is this one.
 *
 * Two steps. The LETTERS first: a text fewer than half of whose letters are the pack's own (`script_letters`) is another
 * language's — a Russian definition under a German word. Then, for languages that share their letters, the WORDS: the
 * text's distinct runs of letters, lower-cased as {@see LanguagePack::normal()} gives them, counted against the pack's most
 * frequent words (`common_words`) and each neighbour's — a neighbour is every other pack of the deployment whose
 * `script_letters` is the very same pattern string ({@see LanguagePack::neighbours()}). Only the words that tell the two
 * apart count: the ones both languages use often («i», «to», «a» of Polish and English) say nothing either way.
 */
final class TextLanguage
{
    /**
     * Are fewer than half of the text's letters the pack's own? Null when that cannot be told: the pack writes no letters,
     * or the text has none.
     */
    public static function outOfScript(string $text, LanguagePack $pack): ?bool
    {
        if (! $pack->has('script_letters')) {
            return null;
        }
        preg_match_all('/\p{L}/u', $text, $found);
        $letters = $found[0];
        if ($letters === []) {
            return null;
        }
        $pattern = $pack->pattern('script_letters');
        $own = count(array_filter($letters, static fn (string $letter): bool => preg_match($pattern, $letter) === 1));

        return $own * 2 < count($letters);
    }

    /**
     * The words of the text that tell the pack's language from each neighbour of the same letters: how many of its distinct
     * words only this language has among its frequent ones (`mine`), and how many only the neighbour has (`theirs`) — one
     * row per neighbour, in the order the deployment names them. Empty when the pack writes no letters or no frequent
     * words: such a language is told from nobody.
     *
     * @return list<array{code: string, mine: int, theirs: int, their_words: list<string>}>
     */
    public static function tellingWords(string $text, LanguagePack $pack): array
    {
        $mine = $pack->commonWords();
        $letters = $pack->has('script_letters') ? $pack->pattern('script_letters') : null;
        if ($mine === [] || $letters === null) {
            return [];
        }
        $said = self::words($text);
        $out = [];
        foreach ($pack->neighbours() as $code => $neighbour) {
            if ($neighbour['script_letters'] !== $letters || $neighbour['common_words'] === []) {
                continue;
            }
            $theirs = array_values(array_intersect($said, array_diff($neighbour['common_words'], $mine)));
            $out[] = [
                'code' => (string) $code,
                'mine' => count(array_intersect($said, array_diff($mine, $neighbour['common_words']))),
                'theirs' => count($theirs),
                'their_words' => $theirs,
            ];
        }

        return $out;
    }

    /**
     * The distinct words of a text: runs of letters (a combining mark stays with its letter), lower-cased as the pack's
     * lists are — split on everything else, the apostrophe too, so an elision gives up its word («l'hôpital» → «l»,
     * «hôpital»). A word said three times counts once.
     *
     * @return list<string>
     */
    public static function words(string $text): array
    {
        $runs = preg_split('/[^\p{L}\p{M}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

        return $runs === false ? [] : array_values(array_unique(array_map(LanguagePack::normal(...), $runs)));
    }
}
