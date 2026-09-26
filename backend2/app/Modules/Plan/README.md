# Plan module

**Owns:** the learning plan — a preparation for one event over 1–10 days: the plan and its scenes
(the model's briefs and lessons), the calendar of days, the dealt cards of a day, the terms of a
scene, the spoken audio of a day (both speakers' lines, phrases, words), the check counters. Canon: `docs/plan-v2.md`; contract:
`docs/plan-api.md` + `openapi/openapi.yaml` (tag `Plans`).

Tables: `plans`, `plan_scenes`, `plan_days`, `day_cards`, `plan_terms`, `plan_line_audios`,
`plan_check_counters`, and — since наряд CONV-1 — `conversations` + `conversation_turns`: the talk with the agent
that is the SIXTH stage of a day, and its append-only journal of lines (`docs/plan-v2.md` §11); since наряд CONV-2 —
`plan_stage_passages`, the append-only journal of walked stages (one row per day and stage, never changed; its first
user is the talk: the first talk of a day that ended of its own walks the sixth stage, and «Ещё раз» after it is a replay; since
наряд ACC-1 §3 a row with no talk — `conversation_id` null — is the sixth stage SKIPPED: a day dealt with nothing to talk about,
or on five stages before the talk; `plan_days.has_conversation` and the rollout switch are gone); since наряд FIX-4 —
`conversation_rejections`, the append-only journal of what the server refused of a talk's role (an answer asked again, a
door dropped — its line, attempt, reason and `model_calls` id), and on the lines themselves the scene each was said in
and a scene's greeting or goodbye (`conversation_turns.scene_id`, `scene_event`) and the constructions a move said
almost (`phrases_almost`); `plan_scenes.built_at` — the end of a scene's lesson build. A `day_cards` row carries one kind of the registry of day trainers (наряд SESSION-1a:
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
learner's lines, phrases, fillers and words take the other; the voice is picked by role and gender. The partner has TWO
voices of each gender (FIX-4c §1): the scene's own is `plan_scenes.partner_voice_id`, cast ONCE when its lesson is
accepted (`BuildLessonHandler` → `Application/Service/PartnerVoices` → `Domain/Service/PartnerVoiceRota`: the other voice of
the nearest earlier scene of its gender, the plan's scene rows locked in order first), and every partner line of the scene —
lesson, recall, talk — speaks in it (`VoiceCast::voiceOf`, `LineToSay::$voice`); the scenes there before were given theirs by
the migration's `Infrastructure/Eloquent/PartnerVoiceBackfill`. A lesson written is
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
| `Conversation` (+ `ConversationTurn`) | the talk with the agent (наряд CONV-1): the journal is APPEND-ONLY and numbered by the aggregate (a client cannot insert, reorder or rewrite a line it is shown); a move is taken only when the state IS `your_turn` (a second `POST …/turn` in flight does not buy a second reply); an ended talk takes nothing more — «Ещё раз» is a NEW talk (`replayed`), never this one reopened; checkpoints walk FORWARD and a scene marked done stays done; the money — model plus voice — is added up in one place so the cap is asked of one number. Turns of the scene and money are two budgets: a rescue spends the money and none of the turns. Since CONV-2: whether a talk walks the stage is the talk's own answer (`passesStage()` — ended of its own, never `replayed`), its minutes are the time it was talked (`activeSeconds()`: gaps between lines, each up to `MAX_GAP_SECONDS` = 60), and the line a rescue asks to hear again is `lineBeforeLastMove()` |
| `PlanEvent` | a journal line, written once and never changed (no mutator; `PlanEventRepository` has `append`/`has`/`forPlan` only); a day event names its day; a rebuild carries `{from, to}` with `to < from`. Written inside the transaction of the handler whose change it records (`BuildPlanHandler`, `BuildLessonHandler`, `CloseDayHandler`, `ReschedulePlanHandler`) or by the tick; the letter is queued after the commit |

The lesson (`Domain/Lesson`, `lesson_day.v4.10`; the rollback `v4.7` answers the same schema): `Lesson` — exchanges (`answer`/`ask`/`rescue`, each with its
`check`), phrases as frames (`Phrase` + `Slot` + `Filler`), the listening (`ListeningQuestion`, the lesson's own,
not an exchange's), vocabulary with `used_in`; `LessonParser` (shape only — and three things put right: a frame and a filler's native text lose the space before the mark they end with (доработка GEN-3, BACK-TAILS-1 §3.1); a text ending in two full stops keeps one (CONV-1); and — LANG-1, DECISIONS п. 437 — in a READING (`pronunciation_native` of a frame, a filler, a word, a learner line) every run of letters that holds a Cyrillic letter has its Latin look-alikes put back into Cyrillic, and a Latin acute vowel becomes the Cyrillic vowel with U+0301: «телефoн» → «телефон», «лекáжа» → «лека́жа»; a run with no Cyrillic, and letters of other writings, stay what `pronunciation.foreign_script` finds); `LessonAssembly` — the SERVED lesson
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

Pure services: `PlanCalendar` (layout 1…10, days until the event), `DayAssembler` + the stages of the
REGISTRY OF DAY TRAINERS (наряды SESSION-1a, CONV-1: `WordsStage`, `PhrasesStage`, `DialogueStage`, `ListenStage`,
`SpeakStage`, `RecallStage` — 29 dealt kinds over one served lesson; three cards per word spaced by `Spacing::interleave`
(`A[i]`, `B[i−1]`, `C[i−2]`), the words' checks walking ONE seeded circle over all the words — `WordChecks`
(SESSION-1e): a kind a word cannot have swapped with the nearest word that can, `word_choose` asked both ways by turns,
`word_listen` answered in the learner's language; per frame an intro, two or three
recognitions and a production — `PhraseSeries` (SESSION-1d) decides which filler and which kind each is, for the day,
a failed card's copy and a frame that comes back — spaced by `Spacing::apart` (`SpacingSearch`: at least two other
frames' cards between two of one frame); the units that failed twice returned at the end of their own stage, a frame
as the kind it failed as last with another filler; the review's ten `speak_answer` and the rehearsal's «Вспомнить», CONV-1),
their card builders (`WordCards`, `PhraseCards`, `DialogueCards`, `ListenCards`, `SpeakCards` — and `CardObjects`,
the one place a frame, a line, an exchange and the phrase as it is said are drawn, so no two stages show them
differently, and the one list of a frame's fillers every card shows — without a filler the seam judge said does not
read, unless the dialogue says it, SESSION-1e) and the helpers every kind shares: `SceneMaterial` (a scene's served
lesson, its terms, the packs of the plan's two languages and its `filler.native_seam` findings — `hides()`; whether a
line asks — `asks()`, which `phrase_combine` is dealt on), `Options` (the wrong ones never equal to the right one or to each other, ids `o1…`
in the SHOWN order, a card left with fewer than two options is not dealt), `Audio` (the sound stub a payload
carries until it is read), `PartnerLines` (the pace line of `listen_pace` and the longest partner line of a given length — since CONV-2 no card of «Говорю сам» says a partner line, and the echo stands on a learner line, `SpeakStage::learnerLines()`), `NumberValues`, `Retry` (the reshuffled options and tiles of a copy),
`RouteStages` (which stages a day on the route has and where each
stands — from card tallies, the dealer's outline or the day type, plus the talk's own node, which has no cards — `TalkStage`: ahead / open / passed / skipped, read off the journal of walked stages, CONV-2, ACC-1 §3),
`DayStages` (the stages of cards a day of a type deals, and whether the day walks the talk — every day, but the one whose
sixth stage is skipped, ACC-1 §3), `ConversationRules` (turns, minutes and the money cap of a talk, from
`plan.conversation`; since BACK-TAILS-2 also the replays a day allows a calendar day; since FIX-4 a scene's moves — its
targets and one more), `ConversationOutcomes` (the talk's summary read off its journal: said · almost · none per target,
«ещё вспомнил», `ended_by_limit`), `FrameJudge` + `FrameWords` + `WordBases` (FIX-4 §2: which constructions of the scene
the talk is in a move SAID or said ALMOST — a coherent phrase: the frame's part before its window where a sentence
begins, after its opening words or — FIX-4b §1 — straight after a conjunction that opens a clause (`clause_starters`), a
word of the learner's own in the window, the part after straight after it; a window with nothing after it ends before
the conjunction of the next construction the move says; a negative the same construction, contractions spelt out and
articles left out by the pack; the model is not asked),
`LineShare` (the share of a line the move had already said, which the echo guard reads — BACK-TAILS-2 §9),
`ConversationLead` (FIX-3 §7, FIX-4 §§3, 5: the door to lead the role to within its scene — a target said almost first
— and the hint: the whole sentence of the target just opened, else the first not said, with its exact line after an
almost; on the wire as the lesson has it, `hints.sentence`, FIX-4b §2), `ConversationTargets` (CONV-2: the up to seven phrases a talk is FOR, over its checkpoints in order — one list for the entry card, the ribbon's strip, the summary and — BACK-TAILS-2 §4 — the talk's row of the day window), `RoleLines` (CONV-2: the role's reply that says a learner line, or a rescue that says the rescued line again; BACK-TAILS-2: a sentence that says the learner's last move back — the guards `ConversationMoves` asks once more on, cuts, and replaces with the pack's neutral line), `IntentClause` (the line as the clause after «Скажи, что …» — the task of «Говорю сам», `task_clause_native`; the talk's `hints.native` of the build (20) is gone, ACC-1 §5), `InstrumentalRole` (the role in the instrumental for «Поговори с врачом», ru/uk, null where the ending hangs on stress),
`DayHighlights` («Что было хорошо», кадр 37-13), `BlueprintChecker` (the plan
checks in observe/drop/gate), `LessonValidator` + `Check/Lesson/*Rules` (the lesson's codes, each with its
card's address, `LessonCodes`), `Check/Language` — the rules' languages: `LanguagePack` (one language's words, marks
and patterns from `config/lesson/lang/<code>.php`; a key it lacks is a check skipped, `PackSkips`, never a finding).
Since наряд LANG-1 (DECISIONS п. 430) there are TEN packs — en, ru, uk, be, pl, ro, es, it, de, fr — and each writes every
key the code reads for its side (targets en pl ro es it de fr, natives ru uk be pl ro es it de fr), a rule that is not
the language's written as a no-op, never null (null is `lang.pack_missing`); the key spec is
`docs/research/lang-1/pack-keys.md`. A pack's words are asked and kept FOLDED (`LanguagePack::normal()`: ß → ss, œ → oe,
the Romanian cedilla letters with the comma below), and `speech()` hands its lists down in the text's canonical form, so a
pack is written in the language's own spelling. `LanguagePacks` hands every pack its NEIGHBOURS — each other pack's
`script_letters` and `common_words` (`asNeighbour()`) — for the translation guard (`ReplyNative`); `talkTitleTemplate()`
holds a pack's `talk_title_template` to its shape (it throws on a missing field; the pack tests call it — the title itself
is `NativeStrings::talkTitle`, which reads the key and falls back to English on a broken one);
`LanguageWords` (the same questions of any language, answered off its pack), `LessonGate` (the twelve fatal codes of `LessonGate::FATAL`, the
card order a repair takes — a word last —, at most two cards, the `fatal: …` reason), `Words` / `FrameText` / `FrameParts` (the text
rules the validator and the assembly share — and, since SESSION-1a, the frame without its window: its words, where
the slot stands), `DayMetricsCalculator`, `NativeStrings` (the server's own strings in the learner's language — ru, uk
and en; every other native reads them in English, L10N is not LANG-1's — but the talk's title, which for a native with no
declension here is its pack's `talk_title_template`: «Rozmowa: recepcjonistka i lekarz», «Gespräch: Rezeptionistin und
Arzt», nothing inflected, DECISIONS п. 434), `Shuffle`. Whether a spoken attempt counts is NOT this
module's: since наряд FIX-2 there is one rule in the kernel, `Shared/Domain/Service/SpeechMatch` — two modes, and the
card says which on the wire (`speech_mode`); this module hands it the target's `LanguagePack::speech()` and reads
back both the verdict and what of the heard text falls OUTSIDE the frame, which is what the judge is shown as the
slot.
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
queues no voice for a new day (`QueuedPlanDispatcher`). `RescueKits` (наряд LANG-1b §2: the plan's rescue kit is its
TARGET's — the pack's `rescue`, six lines translated into the learner's language — said in the learner's voice by
`VoiceRescueKitJob` → `VoiceRescueKitHandler` when a lesson is accepted, filed by (target, gender, voice, line) through
`Port/RescueAudioStore` → `Infrastructure/Adapter/DiskRescueAudioStore`, `plan-audio/rescue/` — one file for every plan of
that target and gender; served by `GET /plans/rescue-audio/{key}`).
`WordUsage` (the line of the day a word is said in — by the lesson's `used_in` — and its place in it, sheet 23-0e).
Application: `LessonBuildService` (the lesson call, one retry only for an answer off the schema, the validator's
findings counted by code, the checks the packs could not run counted as `lang.pack_missing`, the seam judge once after
the gate; a lesson that failed the gate is asked for anew ONCE in the same build — наряд LANG-1b §1, `lesson.auto_rebuild`), `LessonContexts` (a lesson's validation context — the pair of languages as their packs, by code),
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
- `Application/Inspection/PlanInspection` (наряд ADM-1, `docs/admin-plan.md`): the learner's plan page for the admin panel —
  read-only documents, one per section (header, issues, days, pipeline, lesson, passage, conversations, money, calls) and a
  plan's own sound. It reads the plan's rows (`PlanInspectionReader`), the aggregate for statuses and served lessons, and the
  call journal through `PlanCallJournal` (Infrastructure: `ObservabilityPlanCallJournal` over Observability's
  `CallLogReader`). «Что не так» — eleven pure checks in `Domain/Inspection/Check`, each with a canon test; the money canon
  is `plan.inspection`. A model call is read as the plan's by the window of a build or a talk it started in (`model_calls`
  names no plan) — the windows other plans overlap are flagged, never guessed.
- Port fulfilled for Identity: `PlanAccountEraser` (the account eraser: every plan of the learner, a deleted one too, with all
  that hangs on it; returns how many plans there were — the count `account_deletions` keeps — and deletes the folders of the
  learner's scenes and talks on the disks once the account's transaction has committed, ACC-1 §1).

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
| `PlanModelPort` | `ContentModelPlanBuilder` (over the catalogue, prompt files + strict schemas; the plan, the lesson, the P2R card repair, the seam judge, — CONV-1 — `conversationTurn` (`conversation_agent.v3.4` since ACC-1 §6, `plan.conversation.model`, ONE attempt, its own 20 s, journal purpose `conversation`; the role is told its scene only, its targets by the talk's short ids `T1…T7`, the learner's earlier lines as facts (`EARLIER`) and — on a scene's goodbye — `SCENE_END`; it answers its line, `understood`, `off_topic`, `opens` and `end` — which constructions were said and when a scene is over are not its to say; a move a guard refused is asked once more with `REDO` — the second call is billed to the same turn, and every refused attempt is journaled in `conversation_rejections` with its `model_calls` id) and — SESSION-1a — `judgeSlot`: the judge model, `plan.slot_judge.timeout`, ONE attempt through `ContentModelCatalog::get(retries: 1)`, the `plan.slot_judge` log line with its version, tokens, price and latency), `FakePlanModel` (tests / `PLAN_MODEL_DRIVER=fake`; its slot judge accepts by default and its closure may throw, to play the model's silence) |
| `PlanDispatcher` | `QueuedPlanDispatcher` (`BuildPlanJob`, `BuildLessonJob`, `AttachPlanImagesJob` — the route's photos, `IllustrateSceneJob` — a day's photos after its lesson, `VoiceSceneJob` — a scene's voice: waits out the concurrency limit, fails with the vendor's code on a refusal of the account, stops at the fuse) |
| `LearnerCalendar` | `IdentityLearnerCalendar` |
| `NextDayAccess` | `EveryNextDayAllowed` — may the learner have the next day; asked by `CloseDayHandler` before the next day's lesson is queued (GEN-3 §11). Always yes until PAY-1 — the lesson built on payment is its body; the days' paywall itself came with ACC-1 (`LearnerAccess` below) |
| `LearnerAccess` | `IdentityLearnerAccess` (ACC-1 §2) — does the learner have a subscription in force, asked of Identity's `GetAccess`; read by `Application/Service/Paywalls` only while `access.paywall_enabled` is on: «один план, день 1 бесплатно» — the free plan is the learner's first (`PlanRepository::freePlanIdOf`, a deleted one counts), the `Paywall` value object locks a day still to be opened past its day 1 (`lock_reason: subscription`, `Plan::openDay` / `effectiveDayStatus` / `lockReason`), and `POST /plans` is 402 / 409 by `PlanAllowance` under an advisory lock of the learner's plans (`lockPlansOf`) |
| `LearnerGender` | `IdentityLearnerGender` (the profile's gender: the learner's voice on every scene — FIX-3 §1, `Application/Service/VoiceCasts` — and the learner's gendered lines of the server; read when a lesson is written too) |
| `BuildVersion` | `StampedBuildVersion` (`APP_COMMIT` / `storage/app/commit`) |
| `PlanImageFinder` | `PexelsPlanImageFinder` (search → photo + tone; `findMany` — a batch, six on the wire, over Generation's `searchMany`; `tone(url)` → Pexels `GET /photos/{id}` for the backfill) |
| `SceneImageStore` | `CdnSceneImageStore` (disk `plan.image_disk`; fetches the 112/448 square crops from the photo's CDN, labelled `images`; fetches nothing under the fake image driver) |
| `LineSpeaker` | `GenerationLineSpeaker` — every line on its own vendor call in the voice the pack gives its role and gender (`SpeechSynthesizerPort::speakLines`), the account's balance for the fuse; off when `SPEECH_ENABLED=false`. Since LANG-1 (DECISIONS п. 436) every plan target has its six voices in `generation.speech.voices.<target>` (`SPEECH_VOICE_<LANG>_<SLOT>` → `SPEECH_VOICE_EN_<SLOT>` → the approved id) and every line goes with its language — the plan's target — as `language_code` (`SPEECH_LANGUAGE_CODE`, on by default); the file's voice key does not carry the language (п. 248) |
| `LineAudioStore` | `EloquentLineAudioStore` (private disk `plan.audio_disk`; `withoutDuration` / `read` / `setDuration` are what `plan:audio-durations` backfills `duration_ms` through) |
| `ConversationRepository` (Domain) | `EloquentConversationRepository` — the talk's row written whole, its lines only ever INSERTed (no update, no delete anywhere in the class), and `lockState()` for the re-check a write does after the model has answered; `findById`, `latestForDay` (by `started_at`, a tie of one second broken by the ULID — the talk the window's targets tick), `replaysSince` (the talks begun on a walked day since a moment, the one that walked it aside — the replay cap, BACK-TAILS-2 §7) and `walkedWithoutPassage` (the talks that ended of their own on days with no passage — what `plan:reconcile-talks` writes). A day's minutes read only the talk that walked its stage (`Application/Service/DayMetricsOf`) |
| `StagePassageRepository` (Domain) | `EloquentStagePassageRepository` — `plan_stage_passages`, INSERT … ON CONFLICT DO NOTHING (`insertOrIgnore`) and SELECTs; no UPDATE/DELETE. Written by ONE writer, `Application/Service/ConversationPassing::mark()`, inside the transaction that saves the talk (CONV-2, DECISIONS п. 367) |
| `TurnSpeaker` | `GenerationTurnSpeaker` (the pack's voice for one line of a talk, over `LineSpeaker`; the vendor's refusals are swallowed HERE, with the log line — a line without sound is still a line) |
| `ConversationAudioStore` | `DiskConversationAudioStore` (`plan-audio/conversations/<talk>/<turn>.mp3` on `plan.audio_disk`; served by the same `GET /plans/audio/{id}` — a turn's line is NOT a `plan_line_audios` row: that table is the SCENE's bill) |
| `SlotJudgeQuota` | `RedisSlotJudgeQuota` (cache connection, key `plan:slot_judge:{user}:{Y-m-d in the learner's zone}`, `INCR` and an `EXPIREAT` on the first call to the next local midnight) and `ArraySlotJudgeQuota` (in-process, `PLAN_SLOT_JUDGE_QUOTA_STORE=array` under test) — the judge is a paid call inside an HTTP request, so it is capped per learner per LOCAL day; past the cap the verdict is the code's and nothing is bought |
| `PlanCollectionWriter` | `VocabularyPlanCollectionWriter` |
| `NativeDistractorSource` | `VocabularyNativeDistractorSource` (over Vocabulary's `NativeDistractorReader` — catalogue translations for a thin Beginner choice) |
| `CheckCounters` | `EloquentCheckCounters` |
| `PlanListReader`, `SceneLocator` (plan of a scene, the owner's scene photo, scenes with photos, `voicesOf` — the partner's gender and the owner of each scene, one query by primary key, for the voices of returned cards), repositories | `EloquentPlanRepository` (+ `PlanMapper`), `EloquentDayCardRepository` (+ `stageTallies` — the route's one grouped query), `EloquentPlanTermRepository` |
| `PlanEventRepository` (Domain) | `EloquentPlanEventRepository` (INSERT … ON CONFLICT DO NOTHING + SELECT; no UPDATE/DELETE) |
| `NotificationLog` | `EloquentNotificationLog` (same shape) |
| `NotifiablePlans` | `EloquentNotifiablePlans` (stored `active` plans, over `plans_one_active_uidx`) |
| `NotificationDispatcher` | `QueuedNotificationDispatcher` (`SendPlanNotificationJob`, one try) |
| `PushSender` | `ApnsPushSender` (+ `ApnsProviderToken`: ES256 JWT, cached 50 min; HTTP/2; 410/`BadDeviceToken` → the address is forgotten) when `APNS_KEY_P8` is set, else `DryRunPushSender` (the letter to the log, `not_sent`) |
| `LearnerDevices` | `IdentityLearnerDevices` (Identity's `GetPushTokens` / `RemovePushToken`) |
| `LearnerHabits` | `IdentityLearnerHabits` (Identity's `GetUsualVisitTime`) |

## Notes

- The prompt files under `Infrastructure/Prompt/` are FROZEN; the version is the file name
  (`plan-builder-v2`, `lesson_day.v4.10`, `lesson_card_repair.v1.4`, `lesson_seam_judge.v1.1`, `slot_judge.v3`, `conversation_agent.v3.4`; `lesson_day.v4.7`
  and `lesson_card_repair.v1.3` stay beside them — a rollback is one constant of `PlanPromptFiles`; `lesson_day.v4.6`, `v4.8`,
  `v4.9` and `lesson_card_repair.v1.2` are gone, in git). `v4.8` (наряд LANG-1 §8, DECISIONS п. 435) was `v4.7` with ONE clause of FINAL INTERNAL
  VALIDATION changed: a reading is written in the letters of NATIVE_LANGUAGE's own alphabet (Cyrillic for Russian,
  Ukrainian and Belarusian, Latin for the others) where `v4.7` said «Cyrillic only when NATIVE_LANGUAGE is Russian». `v4.9`
  (наряд LANG-1b §5) is `v4.8` with the gender and the address of a target that has them: LEARNER_GENDER and role_gender
  shape the lines in both languages, and one sentence of TEXT QUALITY — the partner addresses the learner formally in
  TARGET_LANGUAGE, the learner never assumes the partner's gender (the repairs of a frame, an exchange and a line quote
  TEXT QUALITY and read it; `docs/research/lang-1b/v4.9.diff`). `v4.10` (наряд LANG-1b §10.1, DECISIONS п. 447) is `v4.9` with
  a word's definition in TARGET_LANGUAGE, never in English unless the target is English (VOCABULARY), a check's `text_target`
  in TARGET_LANGUAGE (CHECK PER EXCHANGE) and an ask exchange as a real question, never the learner's skill turned into one
  (EXCHANGE KINDS) — `docs/research/lang-1b/v4.10.diff`; `lesson_card_repair.v1.4` (§10.2, п. 448) keeps a word found only for
  its definition's language (and at most its `used_in`) and writes the definition anew. The
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
  its languages' packs cannot run); twelve codes are fatal by the architect's decisions after GEN-2a, in GEN-2b, GEN-3,
  BACK-TAILS-1, FIX-3 and LANG-1b (an option of a check copied out of the partner's line is a WARNING since наряд LANG-1b §1,
  `options.partner_fragment`; a word defined in another language than the target is FATAL since its §10.2,
  `vocab.definition_language`)
  (`LessonGate`; an abbreviation as a word of the day is a warning — whether the learner's language has an everyday word for
  it is the model's to judge) — a lesson with them is never stored before P2R repairs their card (at most two a day; a
  word a repair REPLACED is checked again by the server and refused when it is still a known word, a second id of a word or not
  where `used_in` says; a word the repair kept, its definition written anew, is the validator's alone); a lesson still held is asked for anew ONCE in the same build (наряд LANG-1b §1), and only then it
  fails `fatal: <codes>`. A failed lesson is asked for again only by the learner's retry — no open, close, reschedule or
  extension rebuilds it. Every answer of the plan's model is read without the characters that print nothing
  (`Domain/Service/ModelText`, at `ContentModelPlanBuilder`; `plan:clean-text` for what was stored before, наряд LANG-1b §6 —
  and, since its last step, the readings stored in `plan_terms` and the dealt cards, by the parser's own rule,
  `Domain/Service/ReadingLetters`). A lesson that passed is read once by the seam judge (`Application/Service/LessonSeamJudge`,
  `filler.native_seam`, a warning; `judge.unavailable` when it does not answer). The SLOT judge counts in the same
  table under its own prompt version (`slot_judge.v3`) and has that one code only: it judges a learner's attempt,
  not a lesson, so it writes no finding anywhere and its price goes to the outbound log, never to the scene. The
  conversation's guards count there too, under `conversation_agent.v3.4` (`conversation.learner_line`, `…_cut`, `…_kept`,
  `conversation.rescue_same_words`, `…_kept` — CONV-2; `conversation.learner_echo`, `…_cut`, `…_neutral`, `…_kept` —
  BACK-TAILS-2 §9, against every move since FIX-3 §11; `conversation.own_line`, `…_kept` and `conversation.early_end`,
  `…_kept` — FIX-3 §7; `conversation.native_missing`, `…_blanked` — FIX-4c §6, `Domain/Service/ReplyNative`: a
  translation empty, the same words or not in the learner's letters is asked for again, and a second one said with none;
  since LANG-1 §5 (DECISIONS п. 433) also a line in the learner's letters that holds fewer than two of the words only the
  learner's language has among its `common_words` and two or more of the words only a NEIGHBOUR in the same letters has —
  every other pack of the deployment whose `script_letters` is the same string, not the target alone).
- The day's build log (BACK-TAILS-2 §1, port `DayBuildLog`, adapter `LogDayBuildLog`): a scene day whose «Фразы» the
  ladder could not fit under their ceiling writes `plan.phrases_over_ceiling` (warning) with the rungs and the frames —
  the stop signal, not a failure; the day is dealt anyway.
- QA: `plan:shift-day` (the simulator's calendar), `plan:seed-load` (a load for EXPLAIN), `plan:repair-card`
  (P2R by hand — Presentation/Console).
- Ops: `plan:reconcile-talks {--dry}` (CONV-2) — writes the sixth-stage passage of every day whose talk ended of its own
  before `plan_stage_passages` existed (the first such talk of the day); idempotent, prints «было / стало». Backup first.
- Ops: `plan:reconcile-scenes {--apply}` (BACK-TAILS-2 §6) — renames the scenes of the «Вспомнить» sheets dealt before the
  sheet took the PLAN's name for a scene; dry by default (prints «план · день · сцена: было → стало»), `--apply` writes the
  names and nothing else; idempotent. Backup first.
- Ops: `plan:repace {--all} {--plan=*} {--dry}` (FIX-3 §2) — gives plans the price list of their days the config has
  now (`plans.pace`, read through `Application/Service/PlanPaces`) and prints the minutes of every day not closed
  «было → стало», read through the day room; idempotent (a second run writes nothing), `--dry` rolls its writes back. A
  step of the deploy. Backup first.
- Ops: `plan:revoice-learner --plan= {--scene=*} {--apply}` (FIX-3 §1) — the learner's lines owed in the learner's own
  voice (the profile's gender, `VoiceCasts`): per scene the lines, characters, credits and dollars; buys NOTHING without
  `--apply`, which voices the scenes under the cap and the fuse and drops the old voice's files only once the new ones are
  there.
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
- Ops: `plan:check-report {--since=}` (CHECK-1) — what the lesson validator finds, by code: findings in the stored
  lessons (`checks_json`), how many of them fatal, days the code stands on, days failed with it, the share of days, the
  counters `counted / gated / failed` over every attempt, and three examples a code with the plan and the day.
  Read-only; `--since` narrows the days, not the counters.
- Ops: `plan:audio-durations {--dry}` (SESSION-1a) — fills `plan_line_audios.duration_ms` where it is null, from
  the stored file, with the writer's own estimate over its bytes (`SpeechCost::mp3DurationMs`, mp3 at 128 kbit/s):
  neither getID3 nor ffprobe is in the containers, and the order bought no new package. Idempotent, buys nothing,
  writes that one column, prints «без длительности: было N / стало M»; `--dry` only counts. A row of another
  format, a file gone from the disk or an empty one keeps its null and is named on its own line — a guessed length
  would lie to the player.
- The plan languages (наряд LANG-1 §7, DECISIONS пп. 427–429) live in code in ONE place, `Shared`'s
  `LanguageRoles::planTargets()` (en, pl, ro, es, it, de, fr) and `planNatives()` (ru, uk, be, pl, ro, es, it, de, fr);
  `plan.languages` (`PLAN_LANGUAGES`) may only NARROW the targets, in `planTargets()`'s order (`PlanServiceProvider`), and
  unset is all seven — that effective list is `PlanConfig::$languages`. `GET /plans/languages` hands out its codes (the
  shape build (21) reads), `GET /languages` both sides named from `LanguageCatalog` (`{code, endonym, flag}`), one view
  (`GetPlanLanguagesHandler`). `POST /plans` takes the target only (a two-letter code — Laravel's 422 otherwise); the
  native is the profile's (`LearnerCalendar::nativeLangFor`), and `CreatePlanHandler` refuses the pair — a target off the
  effective list, a native off `planNatives()`, the two the same, or a profile native that is no language code at all — with
  422 `language_pair_invalid` (`meta {target, native}`) before the paywall and before anything is written.
- Notifications: `plan:notify-tick` (Presentation/Console, every 15 min in `routes/console.php`, run
  by the `scheduler` compose service) writes `event_today` / `event_passed` and the daily reminder;
  `plan:notify-test {user} {kind}` sends one letter now through the same handler. The server does NOT
  detect skipped days: `days_skipped_rebuilt` comes only from a shortening `PATCH …/schedule`.
