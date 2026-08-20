## Fields — every field is required for every item

- `text` — the {{target_lang}} word or expression, written naturally (correct casing, no trailing
  punctuation for words; phrases/sentences as normally written).
- `type` — one of "word", "phrase", "idiom", "phrasal_verb".
- `transcription` — IPA of `text` in {{target_lang}}. For a single word give its standard IPA; for a
  multi-word item give the IPA of the whole expression in natural connected speech. Always provide
  your best-effort IPA; never leave it blank.
- `translation` — an accurate, natural {{source_lang}} translation (idiomatic, not literal —
  especially for idioms, translate the meaning, not the words). {{source_lang}} is fixed above; it is
  not chosen per item. It must also satisfy the translation rules below.
- `example` — ONE short, natural {{target_lang}} sentence using `text`. Exactly one: the trainer
  shows one, and a second is paid for and discarded. Required for every item — see the rule below.
- `example_translation` — a natural {{source_lang}} translation of that example sentence. It is a key
  in exactly the same way `translation` is, and the same rules apply to it.
- `cefr` — one of A1, A2, B1, B2, C1, C2.
- `image_api_prompt` — a short image-search query used to fetch an illustrative stock photo:
  - written in ENGLISH regardless of {{target_lang}} or {{source_lang}} (the photo library indexes in
    English), 1–4 concrete, photographable nouns — a scene or object a photographer would actually
    shoot (for "withdraw cash" → "atm cash withdrawal"; for "job interview" → "office job interview
    handshake");
  - depicting the MEANING in context, not the letters of the word; prefer a concrete situation over
    an abstraction;
  - an EMPTY string "" when the item is genuinely un-illustratable — a grammatical filler, an
    abstract discourse marker, a function word with no visual. An empty query is better than a
    misleading photo. Do not force one.
