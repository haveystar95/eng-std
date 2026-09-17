# Plan module

**Owns:** the learning plan — a preparation for one event over 1–10 days: the plan and its scenes
(the model's briefs and lessons), the calendar of days, the dealt cards of a day, the terms of a
scene, the spoken audio of a day (both speakers' lines, phrases, words), the check counters. Canon: `docs/plan-v2.md`; contract:
`docs/plan-api.md` + `openapi/openapi.yaml` (tag `Plans`).

Tables: `plans`, `plan_scenes`, `plan_days`, `day_cards`, `plan_terms`, `plan_line_audios`,
`plan_check_counters`. A `day_cards` row carries one kind of the registry of day trainers (наряд SESSION-1a:
29 values in the enum, 28 dealt, `listen_pairs` reserved), its unit (`word`/`phrase`/`exchange`/`day`) and
`response` — what came with the attempt: what was heard, the slot's value, whether the frame was shown, the
client's mode, and the judge's ruling with its call. Photo tones (PLAN-UI-3): `plan_scenes.image_tone`, `plans.cover_image_tone`
(`#RRGGBB`, written in the same conditional UPDATE as the photo); `plan_terms.image_tone` (DAY-UI-2)
— the tone of a word's photo, or of its slot when the search ladder found none (which is also the
mark that the ladder was asked). `plan_line_audios` rows are named by the unit reference
(`line_ref`: `x3` — the partner's line of exchange 3, `x3b` — the learner's, `p2` — a phrase, `p2.f3` — the
phrase's frame said with its third filler, `v5` — a word or chunk), each with its bill (`characters`,
`credits` — the vendor's `character-cost`, `cost_usd`, `request_id`). Since DAY-UI-3 the server voices
everything a day says (with TTS-2, on ElevenLabs), in the voices of two people of different gender per
scene: the partner's gender is `plan_scenes.partner_voice_gender` (from the lesson's `role_gender`), the
learner's lines, phrases, fillers and words take the other; the voice is picked by role and gender. A lesson written is
`illustrating` (the wire says `building`) until its photos are found, then `ready`. Files on disks, not tables:
spoken lines (`plan.audio_disk`, `plan-audio/…`) and the square copies of scene photos
(`plan.image_disk`, `plan-images/<scene>/<112|448>.jpg`).

Notifications (PLAN-UI-3): `plan_events` — the plan's append-only journal (`plan_ready`,
`day_ready`, `day_passed`, `days_skipped_rebuilt`, `event_today`, `event_passed`; once-only kinds
held by partial unique indexes) and `plan_notifications` — the append-only delivery log
(`sent|not_sent|failed|no_token`, one `daily_reminder` per user per local date by a partial unique
index). Rules and texts: `docs/plan-api.md` «События и уведомления».

## Model style

**rich.** The plan has rules that can be broken from outside — which day may open today, which
scene is dropped when the plan shrinks, what a first and a second failure of a card do, what a
check may erase — and every one of them lives in `Domain` behind an aggregate or a pure service.
Nothing in Domain imports Laravel.

## Aggregates

| Aggregate | Invariants it protects |
|---|---|
| `Plan` (+ `PlanScene`, `PlanDay`) | one status machine (building → ready/unclear/failed → active → finished; overdue derived); the calendar layout by `PlanCalendar`; days open one per calendar day in the learner's zone — day N+1 on the calendar day after day N was OPENED — and never ahead of an unclosed day, except that a plan catching up with its event (`isCatchingUp`: calendar days left to the event, its own day included, ≤ days not closed) waits for no date (GEN-3 §11); the day next in line whose lesson is not written is `building` and does not open (`PlanDayBuilding`); the core scene (priority 1) is never dropped or removed; a reschedule never cuts a walked day; a scene is claimed (`building`) before the model is asked |

**No job writes the aggregate.** `PlanRepository::save()` writes the whole plan — row, scenes and
days — from the snapshot in hand, so only a handler that read the plan under a lock and writes it
in the same transaction may call it. A job holds its snapshot across a model call or an image
search, and the learner is free to press «Начать» meanwhile: the lesson job and the photo job write
their own columns instead (`saveScene` — never the photo columns, `attachSceneImage`, `attachCoverImage`,
`PlanTermRepository::attachImage`), and the plan builder — whose write IS the aggregate — re-reads under
`lockForUpdate` inside the writing transaction and re-checks the status first. Paid for once: on
11.09 a lesson job and a photo job put their pre-start snapshot back over a started plan, and the
plan fell to `ready` with no start date and day 1 locked again (`docs/research/plan-api-fix-1/`).
| `DayCard` | answered once; what may be written is the KIND's (`CardKind::allows`, наряд SESSION-1a: a judged card only ever takes `skipped` from the client, a walkthrough is walked or skipped, the voice never fails, a choice takes all four); a first failure of a choice requeues, a second returns the unit tomorrow — and a `day` unit (the listening) returns never; a skip has no consequence; the judge writes its own pass (`judge()`: attempts up, the ruling and what was heard into `response`, the result only when accepted) |
| `PlanTerm` | written once from the served lesson (`fromLesson`), refs `v*`/`p*` are how cards point at terms; a phrase keeps its frame (`frame_*`, `slot`) and reads as the frame said with its dialogue filler, a word keeps `used_in`; a P2R `--apply` rewrites the texts by ref, never the row or its photo |
| `PlanEvent` | a journal line, written once and never changed (no mutator; `PlanEventRepository` has `append`/`has`/`forPlan` only); a day event names its day; a rebuild carries `{from, to}` with `to < from`. Written inside the transaction of the handler whose change it records (`BuildPlanHandler`, `BuildLessonHandler`, `CloseDayHandler`, `ReschedulePlanHandler`) or by the tick; the letter is queued after the commit |

The lesson (`Domain/Lesson`, `lesson_day.v4.7`): `Lesson` — exchanges (`answer`/`ask`/`rescue`, each with its
`check`), phrases as frames (`Phrase` + `Slot` + `Filler`), the listening (`ListeningQuestion`, the lesson's own,
not an exchange's), vocabulary with `used_in`; `LessonParser` (shape only — and one thing put right, доработка GEN-3: a native frame and a filler's native text lose the space before the mark they end with); `LessonAssembly` — the SERVED lesson
every reader deals from: the filler of a learner line is the one the server finds in its text among its frame's
fillers, the closing mark aside (`FrameText`), the `in_dialogue` marks are what the lines say, the speaking key comes
from the frame (`SpeakingKey`, the target's pack says which words are content) — the model's `filler`, marks and key
are read by nobody but `filler.one_in_dialogue` (the marks); the right answers of checks and listening stand at seeded
shuffled places; `LessonCard` — one repairable card by its address (P2R: a frame, a whole exchange — with the frame
its line stands on, together or not at all — a learner line, a check, a listening question); `LessonCardContext` — the
part of the lesson a repair of that card is shown, as the server reads it; `NativeSeams` — every native sentence a
frame makes with its fillers (what the seam judge reads). A scene keeps the model's answer (stored, validated,
repaired) and serves the assembled lesson in the plan's target language. The story so far (GEN-3): `EarlierDay` / `EarlierDays` —
the scene days before this one whose lesson is written, read from their served lessons (`Plan::earlierDaysOf`), the words and
frames they taught (what `StoryRules` holds a new day to); `LessonRoles` — the plan's learner role and the scene's partner role,
written over the model's (`Lesson::withRoles`) after the answer and after every repair.

Pure services: `PlanCalendar` (layout 1…10, days until the event), `DayAssembler` + the five stages of the
REGISTRY OF DAY TRAINERS (наряд SESSION-1a: `WordsStage`, `PhrasesStage`, `DialogueStage`, `ListenStage`,
`SpeakStage` — 28 dealt kinds over one served lesson; three cards per word spaced by `Spacing::interleave`
(`A[i]`, `B[i−1]`, `C[i−2]`), the words' checks walking ONE seeded circle over all the words — `WordChecks`
(SESSION-1e): a kind a word cannot have swapped with the nearest word that can, `word_choose` asked both ways by turns,
`word_listen` answered in the learner's language; per frame an intro, two or three
recognitions and a production — `PhraseSeries` (SESSION-1d) decides which filler and which kind each is, for the day,
a failed card's copy and a frame that comes back — spaced by `Spacing::apart` (`SpacingSearch`: at least two other
frames' cards between two of one frame); the units that failed twice returned at the end of their own stage, a frame
as the kind it failed as last with another filler; the review's ten and the rehearsal's twelve `speak_answer`),
their card builders (`WordCards`, `PhraseCards`, `DialogueCards`, `ListenCards`, `SpeakCards` — and `CardObjects`,
the one place a frame, a line, an exchange and the phrase as it is said are drawn, so no two stages show them
differently, and the one list of a frame's fillers every card shows — without a filler the seam judge said does not
read, unless the dialogue says it, SESSION-1e) and the helpers every kind shares: `SceneMaterial` (a scene's served
lesson, its terms, the packs of the plan's two languages and its `filler.native_seam` findings — `hides()`; whether a
line asks — `asks()`, which `phrase_combine` is dealt on), `Options` (the wrong ones never equal to the right one or to each other, ids `o1…`
in the SHOWN order, a card left with fewer than two options is not dealt), `Audio` (the sound stub a payload
carries until it is read), `PartnerLines` (the longest partner line — one helper, so `listen_pace` and
`speak_echo` cannot share a line), `NumberValues`, `Retry` (the reshuffled options and tiles of a copy),
`RouteStages` (which stages a day on the route has and where each
stands — from card tallies, the dealer's outline or the day type), `BlueprintChecker` (the plan
checks in observe/drop/gate), `LessonValidator` + `Check/Lesson/*Rules` (the lesson's codes, each with its
card's address, `LessonCodes`), `Check/Language` — the rules' languages: `LanguagePack` (one language's words, marks
and patterns from `config/lesson/lang/<code>.php`; a key it lacks is a check skipped, `PackSkips`, never a finding),
`LanguageWords` (the same questions of any language, answered off its pack), `LessonGate` (the nine fatal codes, the
card order a repair takes — a word last —, at most two cards, the `fatal: …` reason), `Words` / `FrameText` / `FrameParts` (the text
rules the validator and the assembly share — and, since SESSION-1a, the frame without its window: its words, where
the slot stands), `SpeechCoverage` (how much of the expected text must be heard — 1.0 up to two words, else 0.7,
the target pack's articles forgiven; also what of the heard text falls OUTSIDE the frame, which is what the judge
is shown as the slot), `DayMetricsCalculator`, `NativeStrings`, `Shuffle`.
The day window (DAY-UI-2, `window` of the day read, put together by `Application/Service/DayWindowViews`):
`DayWindowStages` (the five rows — a number only on the current one — and the day's progress),
`DayPace` (seconds per card BY KIND — the table is `plan.pace`, наряд SESSION-1a: a stage of the registry mixes a
ten-second tap with a line said aloud, and `listen_dialogue` is the whole visit played once, so a rate per stage
would promise a stage of taps the minutes of a stage of speech), `UnitStates` (a unit's state from its cards, the
cards of the `day` unit skipped — the listening is about the visit and nothing of it returns tomorrow),
`ImageTones` (the slot tone when there is no photo), `ImageQueries` (the photo search ladder's
queries); value objects `WindowStatus`, `WindowStage`, `WindowAction`, `UnitState`, `ProgramSummary`.
Application: `PlanImageLadder` (every missing photo's ladder climbed together, one finder batch per rung —
image_prompt → «term, scene theme» → the theme on a page of its own; never the bare word, DAY-UI-3).
The voice (DAY-UI-3, TTS-2): `SpokenLines` (what a day says out loud, the file names, whose voice each is, the fillers
a phrase does not already say), `VoiceCast`; `SceneVoiceQueue` (what a scene still owes, every line its own call),
`VoiceFuse` (nothing is bought below a tenth of the vendor account left), `VoiceCap` (one run of purchases — a voice
job, a backfill — never buys past `generation.speech.job_credits_cap`: checked before each scene against its estimate),
`SceneVoices` + `SceneAudioIndex` (a reader's lookup in the speaker's voice); `DropUnreadVoiceHandler` (a changed voice's
lines deleted before they are bought anew). A database in `generation.speech.named_plans_only_databases` (the e2e stand)
queues no voice for a new day (`QueuedPlanDispatcher`).
`WordUsage` (the line of the day a word is said in — by the lesson's `used_in` — and its place in it, sheet 23-0e).
Application: `LessonBuildService` (the lesson call, one retry only for an answer off the schema, the validator's
findings counted by code, the checks the packs could not run counted as `lang.pack_missing`, the seam judge once after
the gate), `LessonContexts` (a lesson's validation context — the pair of languages as their packs, by code),
`LessonGateKeeper` (a fatal finding holds the lesson: P2R for its card, at most two cards, the repaired answer stored
or the lesson failed with its codes; warnings pass), `LessonSeamJudge` (every native sentence of the day in one call,
a «no» is `filler.native_seam`) and `LessonCardRepairer` + `ReviseLesson` (P2R: one card repaired by the model for what
the validator finds at it, shown only the part of the lesson it needs — asked by the gate before a lesson is stored, or
by the command for a stored lesson, written only on `--apply` and before the day is dealt, the judged seams of frames
it did not rewrite kept).
Application, the day itself (наряд SESSION-1a): `DayDealer` (what a day is dealt from — the scene's served lesson
and terms, the packs, the scenes the returned units belong to, the Beginner catalogue top-up; the one place that
decides which scenes a review or a rehearsal covers), `CardViews` (what a card gets when it is READ, not when it is
dealt: every sound stub its file's id and length in its speaker's voice of that scene, every word photo its url and
tone, the whole-visit cards their `total_ms` — found wherever they stand in the payload, three queries for any
number of cards), `SlotJudge` (the ruling on one spoken attempt of a card judged by meaning: the code first — the
frame's own words must be heard, and a value the lesson knows, heard as one run, is a value — then the model on the
slot alone, and never a failure the vendor caused: a model not asked, silent or off the shape leaves the attempt
accepted and counts `judge.unavailable`), called by `JudgeCardHandler` (`POST …/cards/{card}/judge`: the card found
and checked WITHOUT a lock, the judge ruling outside any transaction, then the card locked, re-checked and written —
the model's eight seconds hold no row).
`SceneReadiness` (`illustrating` → `ready` + the `day_ready` line).
Notifications: `PlanEventRules` (which reschedule is a rebuild; what the calendar owes on and after
the event date, `event_today` not before 08:00), `NotificationRules` (which fact is a letter —
`day_ready` only for day ≥ 2; which plan status still gets it; the 15-minute reminder window),
`NotificationTexts` (the letters' words, ru + en, plurals via `NativeStrings`).

## Public surface (what other modules may call)

- Queries: `GetCheckCounters` → `list<CheckCounterRow>` (Admin reads it through its own handler).
- Port fulfilled for Identity: `PlanAccountEraser` (the account eraser).

Nothing else. The HTTP surface is the client's. Admin reads `plans.cost_usd_plan` and
`plan_scenes.cost_usd_lesson` as a reporting projection (its own README's rule); no other module
reads plan tables.

## Depends on

| Module | How | Why |
|---|---|---|
| `Generation` | `ContentModelCatalog` → `ContentModelPort` (purpose `plan`, own timeout — 180 s for the plan, the lesson, P2R and the seam judge since GEN-3, the vendor's `VendorCall` connects in 10 s, retries only an ANSWERED 408/409/429/5xx and journals every call in `model_calls` before it is made — and, since SESSION-1a, its own retry count: the slot judge asks for exactly ONE attempt, every other caller keeps the default); `ImageSearchPort`; `SpeechSynthesizerPort` | the plan's model calls — the plan, the lesson, the P2R repair, the seam judge, the slot judge — the photos (`searchMany`), the day's voice (`speakLines`, `balance`) |
| `Identity` | `UserReader`; `GetPushTokens` + `RemovePushToken`; `GetUsualVisitTime` | the learner's timezone, native language and gender (the lesson's LEARNER_GENDER); the device addresses a letter goes to (and forgetting a dead one); when the daily reminder is due |
| `Vocabulary` | `ImportTerm`; `NativeDistractorReader` | a closed day's words and phrases become terms (dedup, provenance); catalogue translations as wrong options for a thin Beginner choice |
| `Collections` | `CreateGeneratedCollection` (origin `plan`), `AddTermToCollection`; `DeleteCollection` | the plan's collection; its tombstone when the plan is dropped by the GEN-2a purge migration |
| `Observability` | `OutboundCallContext` | the image job, the photo-copy fetch and the backfill label their calls |

## Ports (outbound interfaces)

| Port | Implementations |
|---|---|
| `PlanModelPort` | `ContentModelPlanBuilder` (over the catalogue, prompt files + strict schemas; the plan, the lesson, the P2R card repair, the seam judge and — SESSION-1a — `judgeSlot`: the judge model, `plan.slot_judge.timeout`, ONE attempt through `ContentModelCatalog::get(retries: 1)`, the `plan.slot_judge` log line with its version, tokens, price and latency), `FakePlanModel` (tests / `PLAN_MODEL_DRIVER=fake`; its slot judge accepts by default and its closure may throw, to play the model's silence) |
| `PlanDispatcher` | `QueuedPlanDispatcher` (`BuildPlanJob`, `BuildLessonJob`, `AttachPlanImagesJob` — the route's photos, `IllustrateSceneJob` — a day's photos after its lesson, `VoiceSceneJob` — a scene's voice: waits out the concurrency limit, fails with the vendor's code on a refusal of the account, stops at the fuse) |
| `LearnerCalendar` | `IdentityLearnerCalendar` |
| `NextDayAccess` | `EveryNextDayAllowed` — may the learner have the next day; asked by `CloseDayHandler` before the next day's lesson is queued (GEN-3 §11). Always yes until PAY-1, whose paywall is this one method |
| `LearnerGender` | `IdentityLearnerGender` (the profile's gender, read when a lesson is written) |
| `BuildVersion` | `StampedBuildVersion` (`APP_COMMIT` / `storage/app/commit`) |
| `PlanImageFinder` | `PexelsPlanImageFinder` (search → photo + tone; `findMany` — a batch, six on the wire, over Generation's `searchMany`; `tone(url)` → Pexels `GET /photos/{id}` for the backfill) |
| `SceneImageStore` | `CdnSceneImageStore` (disk `plan.image_disk`; fetches the 112/448 square crops from the photo's CDN, labelled `images`; fetches nothing under the fake image driver) |
| `LineSpeaker` | `GenerationLineSpeaker` — every line on its own vendor call in the voice the pack gives its role and gender (`SpeechSynthesizerPort::speakLines`), the account's balance for the fuse; off when `SPEECH_ENABLED=false` |
| `LineAudioStore` | `EloquentLineAudioStore` (private disk `plan.audio_disk`; `withoutDuration` / `read` / `setDuration` are what `plan:audio-durations` backfills `duration_ms` through) |
| `SlotJudgeQuota` | `RedisSlotJudgeQuota` (cache connection, key `plan:slot_judge:{user}:{Y-m-d in the learner's zone}`, `INCR` and an `EXPIREAT` on the first call to the next local midnight) and `ArraySlotJudgeQuota` (in-process, `PLAN_SLOT_JUDGE_QUOTA_STORE=array` under test) — the judge is a paid call inside an HTTP request, so it is capped per learner per LOCAL day; past the cap the verdict is the code's and nothing is bought |
| `PlanCollectionWriter` | `VocabularyPlanCollectionWriter` |
| `NativeDistractorSource` | `VocabularyNativeDistractorSource` (over Vocabulary's `NativeDistractorReader` — catalogue translations for a thin Beginner choice) |
| `CheckCounters` | `EloquentCheckCounters` |
| `PlanListReader`, `SceneLocator` (plan of a scene, the owner's scene photo, scenes with photos), repositories | `EloquentPlanRepository` (+ `PlanMapper`), `EloquentDayCardRepository` (+ `stageTallies` — the route's one grouped query), `EloquentPlanTermRepository` |
| `PlanEventRepository` (Domain) | `EloquentPlanEventRepository` (INSERT … ON CONFLICT DO NOTHING + SELECT; no UPDATE/DELETE) |
| `NotificationLog` | `EloquentNotificationLog` (same shape) |
| `NotifiablePlans` | `EloquentNotifiablePlans` (stored `active` plans, over `plans_one_active_uidx`) |
| `NotificationDispatcher` | `QueuedNotificationDispatcher` (`SendPlanNotificationJob`, one try) |
| `PushSender` | `ApnsPushSender` (+ `ApnsProviderToken`: ES256 JWT, cached 50 min; HTTP/2; 410/`BadDeviceToken` → the address is forgotten) when `APNS_KEY_P8` is set, else `DryRunPushSender` (the letter to the log, `not_sent`) |
| `LearnerDevices` | `IdentityLearnerDevices` (Identity's `GetPushTokens` / `RemovePushToken`) |
| `LearnerHabits` | `IdentityLearnerHabits` (Identity's `GetUsualVisitTime`) |

## Notes

- The prompt files under `Infrastructure/Prompt/` are FROZEN; the version is the file name
  (`plan-builder-v2`, `lesson_day.v4.7`, `lesson_card_repair.v1.3`, `lesson_seam_judge.v1.1`, `slot_judge.v2`; `lesson_day.v4.6`
  and `lesson_card_repair.v1.2` stay beside them — a rollback is one constant of `PlanPromptFiles`). The
  loader cuts the lesson's `TEST INPUT` section and sends the real inputs as the user message — the prompt is the system
  message, byte for byte the same on every call, so the vendor's cache holds it (GEN-3); the inputs are built by one
  `LessonRequests` (roles, `EARLIER_DAYS`) for the build and for a repair alike; the repair wrapper
  quotes the lesson prompt's own sections for the card's kind (a heading the lesson prompt lacks is a broken pair and fails
  the call), is shown only the part of the lesson the card needs, an exchange's `NEIGHBOURS`, and the earlier days' frames and
  words, and is told every finding at its card but, for a frame, that its native pattern is another frame's
  (`LessonCard::cites` — P2R v1.3: a native frame is a translation); its schema
  is one per kind, with no address in it; the slot judge's user message is one line per INPUT of its prompt, values as they are — `HEARD` is not
  collapsed, because the recognition noise the prompt forgives can only be forgiven if it is seen.
- The lesson is stored as the model wrote it (`plan_scenes.lesson_json`), re-parsed on read and served assembled.
- Every plan check ships in `observe`; modes are flipped in `config/plan.php`, never in code. The lesson validator
  has no modes: it counts (`checks_json` of the scene, `plan_check_counters` by code, `lang.pack_missing` for a check
  its languages' packs cannot run); nine codes are fatal by the architect's decisions after GEN-2a, in GEN-2b and in GEN-3
  (`LessonGate`; an abbreviation as a word of the day is a warning — whether the learner's language has an everyday word for
  it is the model's to judge) — a lesson with them is never stored before P2R repairs their card (at most two a day; a
  repaired WORD is checked again by the server and refused when it is still a known word, a second id of a word or not
  where `used_in` says), else it fails `fatal: <codes>`. A failed lesson is asked for again only by the learner's
  retry — no open, close, reschedule or extension rebuilds it. A lesson that passed is read once by the seam judge (`Application/Service/LessonSeamJudge`,
  `filler.native_seam`, a warning; `judge.unavailable` when it does not answer). The SLOT judge counts in the same
  table under its own prompt version (`slot_judge.v2`) and has that one code only: it judges a learner's attempt,
  not a lesson, so it writes no finding anywhere and its price goes to the outbound log, never to the scene.
- QA: `plan:shift-day` (the simulator's calendar), `plan:seed-load` (a load for EXPLAIN), `plan:repair-card`
  (P2R by hand — Presentation/Console).
- Ops: `plan:images-backfill {--plan=} {--requery}` — first the photos plans still lack, asked the search
  ladder (prints «было пусто / стало»; `--requery` re-asks the words the bare word photographed and the words repeating a picture of their day), then tones and square copies for scene photos
  stored before PLAN-UI-3; idempotent, re-runnable after a rate limit. The image endpoint heals a
  missing copy on its own, so the copies part is an optimisation; the tones only come from here.
- Ops: `plan:speak-backfill {--plan=*} {--count} {--drop-unread} {--drop-only}` (DAY-UI-3, TTS-2) — what scenes still do not say in the
  server's voice, scene by scene the way a fresh day is voiced (every line its own call); `--drop-unread` deletes, right
  before a scene is bought, its lines filed under a voice their speaker no longer has (a voice of the pack changed), rows
  and files; the plans of real learners first, then QA accounts', newest first; `--plan` only the plans named, in that
  order — and on a database voiced only by name (the e2e stand) nothing is bought without it. The whole run is one run of
  the credits cap. Waits out the concurrency limit a few times, stops on a refusal of the vendor account (its code
  printed), at the cap and at the fuse; prints what is not voiced yet by five kinds, before and after, and what the run
  bought (lines, characters, dollars, credits, calls); `--count` only counts and names the price of what is owed
  (credits · characters · $) — to be agreed before buying; `--drop-only` only deletes the lines of a voice their speaker
  no longer has, rows and files, and buys nothing.
- Ops: `plan:speak-report {--plan=} {--day=}` (TTS-2) — what the voice cost: every plan, a plan by day, a day by
  kind of line — characters, dollars, credits, calls (distinct vendor request ids).
- Ops: `plan:audio-durations {--dry}` (SESSION-1a) — fills `plan_line_audios.duration_ms` where it is null, from
  the stored file, with the writer's own estimate over its bytes (`SpeechCost::mp3DurationMs`, mp3 at 128 kbit/s):
  neither getID3 nor ffprobe is in the containers, and the order bought no new package. Idempotent, buys nothing,
  writes that one column, prints «без длительности: было N / стало M»; `--dry` only counts. A row of another
  format, a file gone from the disk or an empty one keeps its null and is named on its own line — a guessed length
  would lie to the player.
- The plan languages are the server's list (`plan.languages`, `GET /plans/languages`), and
  `POST /plans` validates against it.
- Notifications: `plan:notify-tick` (Presentation/Console, every 15 min in `routes/console.php`, run
  by the `scheduler` compose service) writes `event_today` / `event_passed` and the daily reminder;
  `plan:notify-test {user} {kind}` sends one letter now through the same handler. The server does NOT
  detect skipped days: `days_skipped_rebuilt` comes only from a shortening `PATCH …/schedule`.
