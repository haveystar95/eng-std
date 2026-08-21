#!/usr/bin/env bash
# Builds `assets/wordlist/en_frequency.txt` — the offline autocomplete dictionary.
#
# ## Source
#
#   hermitdave/FrequencyWords, content/2018/en/en_50k.txt
#   https://github.com/hermitdave/FrequencyWords  ·  licence: CC BY-SA 4.0
#
# Word frequencies counted over the OpenSubtitles 2018 corpus — i.e. over film and television
# dialogue. That corpus is the reason this list was chosen over the obvious alternatives:
#
#   * Norvig's `count_1w` is a web crawl. It is larger and freer, and it is full of the web —
#     misspellings, usernames, markup fragments, "pdf", "http". An autocomplete that offers those
#     to somebody learning English is worse than one that offers nothing.
#   * Google's `google-10000-english` is clean but stops at ten thousand words, which runs out
#     exactly where a learner stops needing help.
#
# Spoken dialogue is also simply the right register here: the app teaches words for real
# situations, and a list built from what people SAY ranks those first for free.
#
# ## Output format, and why
#
# One lowercase word per line, in FREQUENCY ORDER (most frequent first), no counts. The order is
# the payload: a line's index IS its frequency rank, so the client needs no second column to sort
# suggestions by, and the file stays about a third smaller than `word count` would be.
#
# The client sorts an ALPHABETICAL index over this once at load, so it can binary-search a prefix
# and still rank the hits by frequency. See `lib/data/search/word_list.dart`.
#
# ## Filtering
#
# Only plain `a-z` words survive. Dropped, deliberately:
#   * anything with digits or punctuation — "24", "l'll", "gonna'" are noise in a lookup field;
#   * contractions ("i'm", "don't") — the base word is what a learner looks up, and the apostrophe
#     forms would double the prefix hits for `d`, `i`, `w` without adding a single new meaning;
#   * single letters except `a` and `i`, the only two that are English words. The rest are corpus
#     debris (subtitle artefacts, initials) and they would crowd the very first keystroke, which is
#     the one place suggestions matter most.
#
# Usage:  mobile/scripts/build_wordlist.sh
set -euo pipefail

SRC="https://raw.githubusercontent.com/hermitdave/FrequencyWords/master/content/2018/en/en_50k.txt"
OUT="$(cd "$(dirname "$0")/.." && pwd)/assets/wordlist/en_frequency.txt"
MAX_WORDS=50000

mkdir -p "$(dirname "$OUT")"
tmp="$(mktemp)"
trap 'rm -f "$tmp"' EXIT

echo "→ fetching $SRC"
curl -fsSL --max-time 60 "$SRC" -o "$tmp"

echo "→ filtering"
awk '
  { w = tolower($1) }
  w !~ /^[a-z]+$/      { next }                 # letters only: no digits, no apostrophes
  length(w) == 1 && w != "a" && w != "i" { next }  # the only two one-letter English words
  !seen[w]++           { print w }              # first occurrence wins: it is the most frequent
' "$tmp" | head -n "$MAX_WORDS" > "$OUT"

words=$(wc -l < "$OUT" | tr -d ' ')
bytes=$(wc -c < "$OUT" | tr -d ' ')
echo "→ wrote $OUT"
echo "   $words words, $bytes bytes ($((bytes / 1024)) KB)"

# The asset ships inside the app bundle, so its size is the user's download. Half a megabyte is the
# line at which this stops being a rounding error next to the app itself.
if [ "$bytes" -gt 512000 ]; then
  echo "!! over the 500 KB budget — lower MAX_WORDS" >&2
  exit 1
fi
