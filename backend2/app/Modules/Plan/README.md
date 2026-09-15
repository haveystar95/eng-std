# Plan module

**Owns:** the learning plan — a preparation for one event over 1–10 days: the plan and its scenes
(the model's briefs and lessons), the calendar of days, the dealt cards of a day, the terms of a
scene, the spoken audio of a day (both speakers' lines, phrases, words), the check counters. Canon: `docs/plan-v2.md`; contract:
`docs/plan-api.md` + `openapi/openapi.yaml` (tag `Plans`).

Tables: `plans`, `plan_scenes`, `plan_days`, `day_cards`, `plan_terms`, `plan_line_audios`,
`plan_check_counters`. Photo tones (PLAN-UI-3): `plan_scenes.image_tone`, `plans.cover_image_tone`
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
| `Plan` (+ `PlanScene`, `PlanDay`) | one status machine (building → ready/unclear/failed → active → finished; overdue derived); the calendar layout by `PlanCalendar`; days open one per calendar day in the learner's zone and never ahead of an unclosed day; the core scene (priority 1) is never dropped or removed; a reschedule never cuts a walked day; a scene is claimed (`building`) before the model is asked |

**No job writes the aggregate.** `PlanRepository::save()` writes the whole plan — row, scenes and
days — from the snapshot in hand, so only a handler that read the plan under a lock and writes it
in the same transaction may call it. A job holds its snapshot across a model call or an image
search, and the learner is free to press «Начать» meanwhile: the lesson job and the photo job write
their own columns instead (`saveScene` — never the photo columns, `attachSceneImage`, `attachCoverImage`,
`PlanTermRepository::attachImage`), and the plan builder — whose write IS the aggregate — re-reads under
`lockForUpdate` inside the writing transaction and re-checks the status first. Paid for once: on
11.09 a lesson job and a photo job put their pre-start snapshot back over a started plan, and the
plan fell to `ready` with no start date and day 1 locked again (`docs/research/plan-api-fix-1/`).
| `DayCard` | answered once; first failure requeues, second returns the unit tomorrow; a skip has no consequence |
| `PlanTerm` | written once from the served lesson (`fromLesson`), refs `v*`/`p*` are how cards point at terms; a phrase keeps its frame (`frame_*`, `slot`) and reads as the frame said with its dialogue filler, a word keeps `used_in`; a P2R `--apply` rewrites the texts by ref, never the row or its photo |
| `PlanEvent` | a journal line, written once and never changed (no mutator; `PlanEventRepository` has `append`/`has`/`forPlan` only); a day event names its day; a rebuild carries `{from, to}` with `to < from`. Written inside the transaction of the handler whose change it records (`BuildPlanHandler`, `BuildLessonHandler`, `CloseDayHandler`, `ReschedulePlanHandler`) or by the tick; the letter is queued after the commit |

The lesson (`Domain/Lesson`, `lesson_day.v4.4`): `Lesson` — exchanges (`answer`/`ask`/`rescue`, each with its
`check`), phrases as frames (`Phrase` + `Slot` + `Filler`), the listening (`ListeningQuestion`, the lesson's own,
not an exchange's), vocabulary with `used_in`; `LessonParser` (shape only); `LessonAssembly` — the SERVED lesson
every reader deals from: a framed learner line is the server's assembly of frame and filler (`FrameText`), the
right answers of checks and listening stand at seeded shuffled places; `LessonCard` — one repairable card by its
address (P2R). A scene keeps the model's answer (stored, validated, repaired) and serves the assembled lesson.

Pure services: `PlanCalendar` (layout 1…10, days until the event), `DayAssembler` + stages (the
day, dealt deterministically), `RouteStages` (which stages a day on the route has and where each
stands — from card tallies, the dealer's outline or the day type), `BlueprintChecker` (the plan
checks in observe/drop/gate), `LessonValidator` + `Check/Lesson/*Rules` (the lesson's codes, each with its
card's address, `LessonCodes`), `LessonGate` (the five fatal codes, the card order a repair takes, at most two cards,
the `fatal: …` reason), `Words` / `FrameText` / `EnglishWords` /
`NativeWords` / `NativeScript` (the text rules the validator and the assembly share), `DayMetricsCalculator`,
`NativeStrings`, `Shuffle`.
The day window (DAY-UI-2, `window` of the day read, put together by `Application/Service/DayWindowViews`):
`DayWindowStages` (the five rows — a number only on the current one — and the day's progress),
`DayPace` (minutes by the stage's pace per card), `UnitStates` (a unit's state from its cards),
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
findings counted by code), `LessonGateKeeper` (a fatal finding holds the lesson: P2R for its card, at most two cards,
the repaired answer stored or the lesson failed with its codes; warnings pass) and `LessonCardRepairer` +
`ReviseLesson` (P2R: one card repaired by the model for what the validator finds at it — asked by the gate before
a lesson is stored, or by the command for a stored lesson, written only on `--apply` and before the day is dealt).
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
| `Generation` | `ContentModelCatalog` → `ContentModelPort` (purpose `plan`, own timeout); `ImageSearchPort`; `SpeechSynthesizerPort` | the two model calls, the photos (`searchMany`), the day's voice (`speakLines`, `balance`) |
| `Identity` | `UserReader`; `GetPushTokens` + `RemovePushToken`; `GetUsualVisitTime` | the learner's timezone, native language and gender (the lesson's LEARNER_GENDER); the device addresses a letter goes to (and forgetting a dead one); when the daily reminder is due |
| `Vocabulary` | `ImportTerm`; `NativeDistractorReader` | a closed day's words and phrases become terms (dedup, provenance); catalogue translations as wrong options for a thin Beginner choice |
| `Collections` | `CreateGeneratedCollection` (origin `plan`), `AddTermToCollection`; `DeleteCollection` | the plan's collection; its tombstone when the plan is dropped by the GEN-2a purge migration |
| `Observability` | `OutboundCallContext` | the image job, the photo-copy fetch and the backfill label their calls |

## Ports (outbound interfaces)

| Port | Implementations |
|---|---|
| `PlanModelPort` | `ContentModelPlanBuilder` (over the catalogue, prompt files + strict schemas; the plan, the lesson and the P2R card repair), `FakePlanModel` (tests / `PLAN_MODEL_DRIVER=fake`) |
| `PlanDispatcher` | `QueuedPlanDispatcher` (`BuildPlanJob`, `BuildLessonJob`, `AttachPlanImagesJob` — the route's photos, `IllustrateSceneJob` — a day's photos after its lesson, `VoiceSceneJob` — a scene's voice: waits out the concurrency limit, fails with the vendor's code on a refusal of the account, stops at the fuse) |
| `LearnerCalendar` | `IdentityLearnerCalendar` |
| `LearnerGender` | `IdentityLearnerGender` (the profile's gender, read when a lesson is written) |
| `BuildVersion` | `StampedBuildVersion` (`APP_COMMIT` / `storage/app/commit`) |
| `PlanImageFinder` | `PexelsPlanImageFinder` (search → photo + tone; `findMany` — a batch, six on the wire, over Generation's `searchMany`; `tone(url)` → Pexels `GET /photos/{id}` for the backfill) |
| `SceneImageStore` | `CdnSceneImageStore` (disk `plan.image_disk`; fetches the 112/448 square crops from the photo's CDN, labelled `images`; fetches nothing under the fake image driver) |
| `LineSpeaker` | `GenerationLineSpeaker` — every line on its own vendor call in the voice the pack gives its role and gender (`SpeechSynthesizerPort::speakLines`), the account's balance for the fuse; off when `SPEECH_ENABLED=false` |
| `LineAudioStore` | `EloquentLineAudioStore` (private disk `plan.audio_disk`) |
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
  (`plan-builder-v2`, `lesson_day.v4.4`, `lesson_card_repair.v1`). The loader cuts the lesson's `TEST INPUT`
  section and sends the real inputs as the user message; the repair wrapper quotes the lesson prompt's own
  sections for the card's kind.
- The lesson is stored as the model wrote it (`plan_scenes.lesson_json`), re-parsed on read and served assembled.
- Every plan check ships in `observe`; modes are flipped in `config/plan.php`, never in code. The lesson validator
  has no modes: it counts (`checks_json` of the scene, `plan_check_counters` by code); five codes are fatal by the
  architect's decision after GEN-2a (`LessonGate`) — a lesson with them is never stored before P2R repairs their
  card (at most two a day), else it fails `fatal: <codes>`.
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
- The plan languages are the server's list (`plan.languages`, `GET /plans/languages`), and
  `POST /plans` validates against it.
- Notifications: `plan:notify-tick` (Presentation/Console, every 15 min in `routes/console.php`, run
  by the `scheduler` compose service) writes `event_today` / `event_passed` and the daily reminder;
  `plan:notify-test {user} {kind}` sends one letter now through the same handler. The server does NOT
  detect skipped days: `days_skipped_rebuilt` comes only from a shortening `PATCH …/schedule`.
