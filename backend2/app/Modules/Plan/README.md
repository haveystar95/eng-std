# Plan module

**Owns:** the learning plan — a preparation for one event over 1–10 days: the plan and its scenes
(the model's briefs and lessons), the calendar of days, the dealt cards of a day, the terms of a
scene, the partner-line audio, the check counters. Canon: `docs/plan-v2.md`; contract:
`docs/plan-api.md` + `openapi/openapi.yaml` (tag `Plans`).

Tables: `plans`, `plan_scenes`, `plan_days`, `day_cards`, `plan_terms`, `plan_line_audios`,
`plan_check_counters`.

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
their own columns instead (`saveScene`, `attachSceneImage`, `attachCoverImage`, `PlanTermRepository
::attachImage`), and the plan builder — whose write IS the aggregate — re-reads under
`lockForUpdate` inside the writing transaction and re-checks the status first. Paid for once: on
11.09 a lesson job and a photo job put their pre-start snapshot back over a started plan, and the
plan fell to `ready` with no start date and day 1 locked again (`docs/research/plan-api-fix-1/`).
| `DayCard` | answered once; first failure requeues, second returns the unit tomorrow; a skip has no consequence |
| `PlanTerm` | written once from the lesson (`fromLesson`), refs `v*`/`p*` are how cards point at terms |

Pure services: `PlanCalendar` (layout 1…10, days until the event), `DayAssembler` + stages (the
day, dealt deterministically), `LessonChecker` / `BlueprintChecker` (the §5 checks in
observe/drop/gate), `Words` / `PhraseInMessage` / `NativeScript` (the text rules the checks and
the assembly share), `DayMetricsCalculator`, `NativeStrings`, `Shuffle`.

## Public surface (what other modules may call)

- Queries: `GetCheckCounters` → `list<CheckCounterRow>` (Admin reads it through its own handler).
- Port fulfilled for Identity: `PlanAccountEraser` (the account eraser).

Nothing else. The HTTP surface is the client's. Admin reads `plans.cost_usd_plan` and
`plan_scenes.cost_usd_lesson` as a reporting projection (its own README's rule); no other module
reads plan tables.

## Depends on

| Module | How | Why |
|---|---|---|
| `Generation` | `ContentModelCatalog` → `ContentModelPort` (purpose `plan`, own timeout); `ImageSearchPort`; `SpeechSynthesizerPort` | the two model calls, the photos, the partner-line audio |
| `Identity` | `UserReader` | the learner's timezone and native language |
| `Vocabulary` | `ImportTerm`; `NativeDistractorReader` | a closed day's words and phrases become terms (dedup, provenance); catalogue translations as wrong options for a thin Beginner choice |
| `Collections` | `CreateGeneratedCollection` (origin `plan`), `AddTermToCollection` | the plan's collection |
| `Observability` | `OutboundCallContext` | the image job labels its calls |

## Ports (outbound interfaces)

| Port | Implementations |
|---|---|
| `PlanModelPort` | `ContentModelPlanBuilder` (over the catalogue, prompt files + strict schemas), `FakePlanModel` (tests / `PLAN_MODEL_DRIVER=fake`) |
| `PlanDispatcher` | `QueuedPlanDispatcher` (`BuildPlanJob`, `BuildLessonJob`, `AttachPlanImagesJob`, `SpeakSceneLinesJob`) |
| `LearnerCalendar` | `IdentityLearnerCalendar` |
| `BuildVersion` | `StampedBuildVersion` (`APP_COMMIT` / `storage/app/commit`) |
| `PlanImageFinder` | `PexelsPlanImageFinder` |
| `LineSpeaker` | `GenerationLineSpeaker` (off when `SPEECH_ENABLED=false`) |
| `LineAudioStore` | `EloquentLineAudioStore` (private disk `plan.audio_disk`) |
| `PlanCollectionWriter` | `VocabularyPlanCollectionWriter` |
| `NativeDistractorSource` | `VocabularyNativeDistractorSource` (over Vocabulary's `NativeDistractorReader` — catalogue translations for a thin Beginner choice) |
| `CheckCounters` | `EloquentCheckCounters` |
| `PlanListReader`, `SceneLocator`, repositories | `EloquentPlanRepository` (+ `PlanMapper`), `EloquentDayCardRepository`, `EloquentPlanTermRepository` |

## Notes

- The prompt files under `Infrastructure/Prompt/` are FROZEN; the version is the file name. The
  loader cuts the `TEST INPUT` section and sends the real inputs as the user message.
- The lesson is stored as the model wrote it (`plan_scenes.lesson_json`) and re-parsed on read;
  a check in `drop` mode stores the corrected lesson.
- Every check ships in `observe`; modes are flipped in `config/plan.php`, never in code.
- QA: `plan:shift-day` (the simulator's calendar), `plan:seed-load` (a load for EXPLAIN).
