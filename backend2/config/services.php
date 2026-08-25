<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        // Accepted OAuth client IDs for verifying Google Sign-In ID tokens:
        // the iOS client id (from the mobile app) and, optionally, a web/server one.
        'client_ids' => array_values(array_filter([
            env('GOOGLE_IOS_CLIENT_ID'),
            env('GOOGLE_WEB_CLIENT_ID'),
        ])),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        // What PRODUCTION generation uses. Do not repoint this to run a comparison.
        'generate_model' => env('OPENAI_GENERATE_MODEL', 'gpt-4o'),
        // What the multi-provider comparison uses. Separate from `generate_model` on purpose: the
        // two must be able to differ, and sharing one variable means a bake-off silently moves live
        // generation onto a model nobody decided to ship. Unset falls back, so nothing changes.
        'compare_model' => env('OPENAI_COMPARE_MODEL', env('OPENAI_GENERATE_MODEL', 'gpt-4o')),
        // Enriching a single bare term is a small task — the cheaper model is plenty.
        'enrich_model' => env('OPENAI_ENRICH_MODEL', 'gpt-4o-mini'),
        // Looking up ONE word for the search screen: a dictionary entry, mechanically produced.
        // The cheap model on purpose and by decision, not by default — the strong model costs two
        // hundred times more for an answer the learner cannot tell apart, and this is the one paid
        // call a learner can trigger by typing.
        'search_model' => env('OPENAI_SEARCH_MODEL', 'gpt-4o-mini'),
        // Recapping a finished practice dialog is a tiny task — the cheap text model is plenty.
        'summary_model' => env('OPENAI_SUMMARY_MODEL', 'gpt-4o-mini'),
    ],

    'anthropic' => [
        // Claude, for the generation bake-off (and as a production candidate afterwards).
        // Absent by default: the org has no credits, so this provider reports itself unavailable
        // and the bake-off runs without it rather than failing.
        'api_key' => env('ANTHROPIC_API_KEY'),
        'generate_model' => env('ANTHROPIC_GENERATE_MODEL', 'claude-opus-5'),
    ],

    'xai' => [
        // Grok. The key env var is GROK_API_KEY (the vendor's own name for it in this .env);
        // the module and the reports call the provider `xai`, which is the API's name.
        'api_key' => env('GROK_API_KEY'),
        'generate_model' => env('XAI_GENERATE_MODEL', 'grok-4.6'),
        // OpenAI-compatible endpoint — same request shape, different host.
        'base_url' => env('XAI_BASE_URL', 'https://api.x.ai/v1'),
    ],

    'gemini' => [
        // Google Gemini API key — server-side only; the client never sees it (only ephemeral tokens).
        'api_key' => env('GEMINI_API_KEY'),
        // Text generation model for the bake-off. Flash rather than Pro: Pro is the top tier and
        // flash-lite is the cheap one, and this comparison is between mid-tier workhorses.
        'generate_model' => env('GEMINI_GENERATE_MODEL', 'gemini-3.7-flash'),
    ],

    'deepl' => [
        // Machine translation for the search field's instant hint — and for NOTHING else. It never
        // writes card content: a term's translation, its example and its description are written by
        // the lookup model against a prompt that knows about CEFR level, register and isomorphism,
        // none of which a general-purpose translator knows.
        //
        // Absent by default. No key = the feature reports itself disabled and search carries on
        // exactly as before; it is a garnish, not a dependency.
        'api_key' => env('DEEPL_API_KEY'),
        // The FREE plan's host. A key ending in `:fx` is a free key and belongs here; a paid key
        // uses api.deepl.com and this has to move with it.
        'base_url' => env('DEEPL_BASE_URL', 'https://api-free.deepl.com/v2'),
        // Characters per month the plan allows. The app stops at 95% of this — see
        // TranslationMonthlyBudget for why it does not run the meter to zero.
        'monthly_characters' => (int) env('DEEPL_MONTHLY_CHARACTERS', 500000),
        // 'deepl' (default) or 'fake' — the deterministic translator for tests and offline dev.
        'driver' => env('TRANSLATION_DRIVER', 'deepl'),
    ],

    'pexels' => [
        // Stock-image search for AI-generated collections (A3). Key from the Pexels dashboard.
        'key' => env('PEXELS_API_KEY'),
        // Politeness delay between image searches inside AttachImagesJob, ms. Pexels' free tier is
        // 200 req/hour; a ~15-term collection is well under, but spacing calls keeps us clear of bursts.
        'throttle_ms' => (int) env('PEXELS_THROTTLE_MS', 250),
        // Which outcome FakePexelsImageSearch returns when IMAGE_DRIVER=fake: found | not_found |
        // rate_limited | transient_error. Tests usually bind the fake directly instead.
        'fake_mode' => env('PEXELS_FAKE_MODE', 'found'),
    ],

    'generation' => [
        // 'openai' (default) or 'fake' (deterministic, no network — for local/dev/tests).
        'driver' => env('GENERATION_DRIVER', 'openai'),
        // WHICH STACK production generation runs on, and the rollback switch for the cut-over:
        //   'v1' — the frozen single-vendor generator (prompt v9, `generate_model`, OpenAI inline);
        //   'v2' — the multi-vendor stack: prompt catalogue + shared schema + ContentModelPort.
        // A flag rather than a deploy, because the cut-over moves the model, the prompt and the
        // vendor path at once, and content defects surface days later. v1 is the code that has been
        // serving production all along, and it stays callable.
        'stack' => env('GENERATION_STACK', 'v2'),
        // The CORE of a card — term, key, one example — under the v2 stack. Written once and read
        // forever, so it runs on the strong model (A/B decision К2, docs/bakeoff-v11-ab.md).
        'core_provider' => env('GENERATION_CORE_PROVIDER', 'openai'),
        'core_model' => env('GENERATION_CORE_MODEL', 'gpt-5.4'),
        'core_prompt_version' => env('GENERATION_CORE_PROMPT_VERSION', 'v15'),
        // The MACHINERY around a finished core — accepted forms and the example's wrong versions.
        // Mechanical work over content that already exists: the cheap model does it at 1/200th of
        // the price of re-generating the core inside a full enrichment.
        'mechanics_provider' => env('GENERATION_MECHANICS_PROVIDER', 'openai'),
        'mechanics_model' => env('GENERATION_MECHANICS_MODEL', 'gpt-4o-mini'),
        'mechanics_prompt_version' => env('GENERATION_MECHANICS_PROMPT_VERSION', 'v14.2'),

        /*
         * Do the two synonym writers actually WRITE? Off by default (DECISIONS п. 32: a new product
         * ships switched off globally — «включить себе → бете → всем»).
         *
         * Not caution for its own sake. A synonym is an ACCEPTED ANSWER, and three measured prompt
         * iterations put the model's accuracy at roughly half (docs/syn-1-findings.md §7): the wrong
         * ones are all «narrower» — `bank account` → `savings account`, `cont` → `factură` — and a
         * card carrying one teaches an equivalence that does not hold. The mechanics around them
         * (schema, grading, the option ban, the sync contract) are finished and correct, and with no
         * rows they are inert rather than wrong.
         *
         * The switch is what makes «прогон остановлен» mean something on BOTH doors. Stopping the
         * catalogue run stops the станок; it does nothing about `POST /search/add`, which enriches
         * every newly saved word and had already written that `cont` → `factură` row live before
         * anyone decided anything.
         *
         * Turned on by the owner's decision, once the accuracy question has an answer — a stronger
         * model for this field, a deterministic anti-hyponym rule, or curated-only.
         */
        'write_synonyms' => (bool) env('GENERATION_WRITE_SYNONYMS', false),

        /*
         * The reading hint («cómo estás» → «комо эстас»), on its own switch rather than sharing the
         * one above. Two products, two producers' worth of risk: a transliteration is judged by an
         * alphabet check a machine CAN make, a synonym by a meaning judgement it cannot, so they can
         * honestly earn their way on at different times. One flag would make the safer product wait
         * for the riskier one — which is exactly what happened, and why they are two.
         *
         * ON by default since 25.08. Its pilot passed and was not close: 49 hints over two generated
         * collections, 49 readable, none rejected by the alphabet gate, and the model got the hard
         * ones right on its own — silent letters («honest» → «онист», «calm» → «кам») and
         * non-rhotic endings («under pressure» → «андер преша»). The threshold was ≥85% clean;
         * the measured number is 100% (docs/syn-1-findings.md §8). Its twin above stayed off in the
         * same run at 67%.
         */
        'write_transliteration' => (bool) env('GENERATION_WRITE_TRANSLITERATION', true),
        // 'pexels' (default) or 'fake' — the image-search adapter for AttachImagesJob.
        'image_driver' => env('IMAGE_DRIVER', 'pexels'),
        // Chain the enrichment станок onto a finished generation (accepted variants + distractors).
        // A switch rather than a hardcoded chain because it multiplies the cost of every generation
        // by roughly one model call per term: if the scrap rate turns out bad, this is how the bleed
        // stops without a deploy. Off makes generation behave exactly as before.
        'auto_enrich' => (bool) env('GENERATION_AUTO_ENRICH', true),
        // Chain the echo-example repair onto a finished generation (QA-7): give a real example to
        // the terms whose example was refused for merely repeating the term. One model call per bad
        // example — rare under the v7 prompt, but the prompt is a request and this is the guarantee.
        // Same switch shape as auto_enrich, so the bleed stops without a deploy.
        'auto_repair_examples' => (bool) env('GENERATION_AUTO_REPAIR_EXAMPLES', true),
        // The commit a proofreading export was produced by, stamped into its first line. Set it at
        // deploy: the app image mounts backend2 and `.git` lives one directory above, so asking git
        // in-container finds nothing. Empty falls back to a git call (a local checkout) and then to
        // an explicit "не определён" — a header that admits the hole still dates the data.
        'git_sha' => env('APP_GIT_SHA'),
        // How many PAID search lookups one learner may trigger per day. A runaway guard rather than
        // a plan differentiator: a lookup costs a fraction of a cent, and gating the app's cheapest
        // useful surface by tier would turn the search field into a paywall pitch. Cache hits do not
        // count — nothing was bought. 0 switches paid lookups off entirely.
        'search_lookup_daily_cap' => (int) env('SEARCH_LOOKUP_DAILY_CAP', 30),
        // The longest thing the search field will treat as a query, in characters. This app catches
        // WORDS and PHRASES: a card, a description, an example and a place in the pool all mean
        // nothing for a paragraph, and a field that accepted one would be a translator. Enforced
        // before the vendor, which bills by the character. See SearchQueryLength.
        'search_query_max_chars' => (int) env('SEARCH_QUERY_MAX_CHARS', 120),
        // Per-call timeout for the multi-provider comparison stack, seconds. 180 is generous for a
        // model that answers directly and NOT enough for a reasoning model on a long prompt —
        // grok-4.6 exceeded it on a 12-item collection. It is a knob rather than a constant so a
        // slow model can be measured instead of being recorded as "did not answer" at OUR limit.
        'model_timeout' => (int) env('GENERATION_MODEL_TIMEOUT', 180),
    ],

    'practice' => [
        // Realtime conversation practice (premium). Realtime-token driver:
        //   'openai' (default) — OpenAI Realtime;  'gemini' — Gemini Live;  'fake' — offline (tests).
        // The recap/summariser is always the OpenAI text model, independent of this driver.
        'driver' => env('PRACTICE_DRIVER', 'openai'),
        // OpenAI realtime engine. Default is the cheaper "mini" model (gpt-realtime-mini is
        // deprecated → 2.1-mini). Confirm the exact name against the current OpenAI docs before prod.
        'realtime_model' => env('PRACTICE_REALTIME_MODEL', 'gpt-realtime-2.1-mini'),
        // Gemini Live model (used when driver=gemini). Confirm against current Gemini Live docs.
        'gemini_model' => env('PRACTICE_GEMINI_MODEL', 'gemini-3.1-flash-live-preview'),
        // Bake the lesson into the Gemini token via liveConnectConstraints. Off by default: the live
        // v1beta endpoint currently rejects that field (docs are ahead of deployment), so a bare
        // token is minted and the client sets the lesson in its setup message. Flip on when it lands.
        'gemini_constrained' => (bool) env('PRACTICE_GEMINI_CONSTRAINED', false),
        // Input-audio transcription model — REQUIRED for the learner's speech to come back as
        // transcript events (input_audio_transcription.completed); without it coverage never lights up.
        'transcribe_model' => env('PRACTICE_REALTIME_TRANSCRIBE_MODEL', 'gpt-4o-mini-transcribe'),
        'voice' => env('PRACTICE_REALTIME_VOICE', 'alloy'),
        // Which versioned lesson prompt to render (Infrastructure/Prompt/practice_dialog.{v}.md).
        'prompt_version' => env('PRACTICE_PROMPT_VERSION', 'v3'),
        // Output-audio playback speed for A1/A2 lessons (mechanical; the prompt also instructs pace).
        'slow_speed' => (float) env('PRACTICE_SLOW_SPEED', 0.9),
        // Server-VAD turn detection — tuned so the model doesn't cut the learner off mid-sentence.
        'vad_silence_ms' => (int) env('PRACTICE_VAD_SILENCE_MS', 900),
        'vad_threshold' => (float) env('PRACTICE_VAD_THRESHOLD', 0.5),
        'vad_prefix_padding_ms' => (int) env('PRACTICE_VAD_PREFIX_PADDING_MS', 300),
        // The session duration guard: the ephemeral token expires after this many seconds.
        'dialog_ttl_seconds' => (int) env('PRACTICE_DIALOG_TTL_SECONDS', 200),
        // Per-user daily cap (premium-only feature; a flat cost guard, not a plan differentiator).
        'dialogs_per_day' => (int) env('PRACTICE_DIALOGS_PER_DAY', 5),
        // Upper bound on how many collection terms a lesson briefs as target words.
        'max_target_words' => (int) env('PRACTICE_MAX_TARGET_WORDS', 8),
    ],

];
