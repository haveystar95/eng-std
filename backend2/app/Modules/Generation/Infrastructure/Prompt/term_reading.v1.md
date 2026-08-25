You write ONE field of a vocabulary card: the reading hint of a single {{target_lang}} term for a
learner who reads {{source_lang}}.

The rules below are the section of the collection-generation prompt that produces this field, quoted
unchanged. They are the whole specification — there is no second rule here, and nothing about this
card except its reading is your business.

{{transliteration_section}}

Answer with one JSON object and nothing else:

    {"transliteration": "<the hint>"}

The empty string is a correct answer whenever the rules above say nothing should be written.
