import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';
import 'package:eng_std/l10n/app_localizations.dart';

import '../../data/pronouncer.dart';
import '../../data/api_client.dart';
import '../../data/app_settings.dart';
import '../../data/languages.dart';
import '../../data/line_audio.dart';
import '../../data/models.dart';
import '../../data/perf_log.dart';
// For the shelf names alone — the session plays a plan's cards through the envelope in
// `models.dart` and knows nothing else about a plan.
import '../../data/plan_models.dart' show PlanSession, PlanSessionTask, PlanTermRow;
import '../../data/plan_sitting_store.dart';
import '../../data/practice/recognition_replay.dart';
import '../../data/providers.dart';
import '../home/home_providers.dart';
import '../plan/plan_day_summary.dart';
import '../plan/plan_dialogue.dart';
import '../plan/plan_rehearsal_done.dart';
import '../plan/plan_ui.dart';
import 'session/intro_card.dart';
import 'session/session_exercise.dart';
import 'session/session_grading.dart';
import 'session/sitting_queue.dart';
import 'triage_swipe.dart';

/// One exercise session (кадры 12a–12k): due then new cards from `/study/sessions`, one card per
/// exercise, played offline-capable per card. The paper/ink «Слова» design (A3.8). Behaviour: the
/// client sends the RAW answer + a `client_seq`; the SERVER grades and schedules (the local check
/// is feedback-only and never stricter than the server). A [practice] session introduces no new
/// terms and never schedules — «Свободная тренировка».
class SessionScreen extends ConsumerStatefulWidget {
  const SessionScreen({
    super.key,
    required this.title,
    this.collectionId,
    this.practice = false,
    this.learn = false,
    this.limit = 20,
    this.targetLang,
    this.onlyTermId,
    this.planId,
    this.planDayIndex,
    this.planIsFinalDay = false,
  });

  final String title;
  final String? collectionId;
  final bool practice;

  /// A PLAN DAY'S SESSION (кадр 1c · 03), built by `POST /plans/{id}/days/{n}/session`.
  ///
  /// The same screen and deliberately so: «механика тренажёров не меняется». Every card is the
  /// app's own card, played by the same exercise widget and graded the same way — what the plan adds
  /// is three sentences (the brass badge in the header, the stage over the task, and where a
  /// carried word came from) and its own summary. A second session screen for the plan would be a
  /// second place for every future trainer fix to land.
  final String? planId;

  /// Which day. Null with a [planId] set asks the server for the day the learner is ON — the focus
  /// is the server's to know, and recomputing it here is how a screen and a session come to
  /// disagree about which day is being studied.
  final int? planDayIndex;

  /// THE FINAL DAY — the run-through before the event, not a lesson (Д-27).
  ///
  /// It introduces nothing and owns no collection, so the server refuses to BUILD it (404) and the
  /// screen that offered «Собрать день» dead-ended there: the plan could not be finished from the
  /// app at all, and the live run closed it from tinker. Its session is the plan's own cards, once
  /// each, and reaching the end of it is what finishes the plan.
  final bool planIsFinalDay;

  /// «Тренировать слово» from a word's expanded card (кадр 16e): a practice session whose pool is
  /// this ONE term. Practice-only by construction — a scheduling session's composition is the
  /// server's to fix, and drilling one word must not spend the daily quota on it.
  final String? onlyTermId;

  /// Opened from the «Учить N» CTA (device-batch F8): the caller knew there were learnable words
  /// (triaged-«не знаю», no progress row). If the built session is still empty, the only cause is
  /// the daily new-words quota being spent — so the empty state says «come back tomorrow», not the
  /// misleading «nothing here yet». (The CTA itself is not gated on the quota — that's F13/F17.)
  final bool learn;

  final int limit;

  /// The language to pronounce answers in — the scoped collection's language (F16). Null for a
  /// cross-collection session, which falls back to the profile's target language.
  final String? targetLang;

  /// THE SAME SESSION, ONCE MORE — «Ещё раз» on a practice summary.
  ///
  /// Every field rides along, and [onlyTermId] is the one that has to: «ещё раз» after drilling ONE
  /// word means that word again. Dropping it turned the repeat into a session over the whole
  /// collection — a different session under the same button, and from «Мои слова» (no collection at
  /// all) it would have been a build with no scope left.
  ///
  /// A method rather than a copy at the call site, so «which fields does a repeat carry» is one
  /// answer in one place, and a field added later cannot be forgotten here silently.
  SessionScreen repeat() => SessionScreen(
    title: title,
    collectionId: collectionId,
    practice: practice,
    learn: learn,
    limit: limit,
    targetLang: targetLang,
    onlyTermId: onlyTermId,
    planId: planId,
    planDayIndex: planDayIndex,
    planIsFinalDay: planIsFinalDay,
  );

  @override
  ConsumerState<SessionScreen> createState() => _SessionScreenState();
}

class _SessionScreenState extends ConsumerState<SessionScreen> {
  // Minted once so `POST /study/sessions` is idempotent — a rebuild reuses the fixed composition.
  final String _sessionId = ApiClient.ulid();

  // F20: pre-warm the iOS keyboard while the session is still loading (behind the spinner), so the
  // ~600 ms first-keyboard-init doesn't freeze the first typing/cloze card. We briefly focus a hidden
  // field to spin the keyboard process up, then unfocus before any card needs it.
  final FocusNode _kbWarm = FocusNode(skipTraversal: true, canRequestFocus: true);

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      _kbWarm.requestFocus();
      // Drop focus next frame — the keyboard engine stays warm after this, but nothing is shown.
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) _kbWarm.unfocus();
      });
    });
  }

  @override
  void dispose() {
    _kbWarm.dispose();
    super.dispose();
  }

  /// «Ещё раз» on a practice summary: start a brand-new practice session immediately (a fresh
  /// SessionScreen mints a new id → the pool is reshuffled). Replaces the route so the back stack
  /// doesn't fill up with finished sessions. What «the same session» means is [SessionScreen.repeat].
  void _again() {
    Navigator.of(
      context,
    ).pushReplacement(MaterialPageRoute(builder: (_) => widget.repeat()));
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final args = (
      sessionId: _sessionId,
      collectionId: widget.collectionId,
      practice: widget.practice,
      limit: widget.limit,
      onlyTermId: widget.onlyTermId,
    );
    final planArgs = (
      planId: widget.planId ?? '',
      dayIndex: widget.planDayIndex,
      sessionId: _sessionId,
    );
    // ONE screen, two builders. The plan's cards arrive wrapped in an envelope the ordinary session
    // has no use for, so the two providers are separate; everything from here down reads the same
    // [StudySession] and cannot tell which one produced it.
    final session = widget.planId != null
        ? ref.watch(planSessionProvider(planArgs))
        : ref.watch(studySessionProvider(args));
    void retry() => widget.planId != null
        ? ref.invalidate(planSessionProvider(planArgs))
        : ref.invalidate(studySessionProvider(args));

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark,
      child: Scaffold(
        backgroundColor: AppColors.paper,
        body: SafeArea(
          bottom: false,
          child: Stack(
            children: [
              session.when(
                loading: () => const Center(child: CircularProgressIndicator(color: AppColors.ink)),
                // Sessions are still built server-side, so no network means no session. Say that
                // in words instead of printing a DioException at the user; the detail goes to the
                // log. Retry re-runs the provider — the session id is minted once, and the build
                // is idempotent under it, so a retry returns the same composition.
                error: (e, st) {
                  debugPrint('[session] build failed: $e\n$st');
                  return _CenteredMessage(
                    text: isOffline(e) ? l.sessionOffline : l.sessionLoadFailed,
                    icon: isOffline(e) ? LucideIcons.cloudOff : LucideIcons.triangleAlert,
                    // Not the green of a right answer: this screen is a failure, and the warning
                    // triangle was drawn in the success colour (QA-OBS-30).
                    iconColor: AppColors.destructiveText,
                    actionLabel: l.generationRetry,
                    onAction: retry,
                  );
                },
                data: (s) => s.cards.isEmpty
                    ? _CenteredMessage(
                        text: widget.learn ? l.sessionDailyNewLimit : l.sessionEmpty,
                        icon: widget.learn ? LucideIcons.clock : LucideIcons.check,
                      )
                    : _SessionShell(
                        session: s,
                        practice: widget.practice,
                        targetLang: widget.targetLang,
                        onAgain: _again,
                        planIsFinalDay: widget.planIsFinalDay,
                      ),
              ),
              // Invisible keyboard-warmup field (F20). 1×1, transparent, non-interactive.
              Positioned(
                left: 0,
                top: 0,
                width: 1,
                height: 1,
                child: Opacity(
                  opacity: 0,
                  child: IgnorePointer(child: TextField(focusNode: _kbWarm, autofocus: false)),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _SessionShell extends ConsumerStatefulWidget {
  const _SessionShell({
    required this.session,
    required this.practice,
    required this.onAgain,
    this.targetLang,
    this.planIsFinalDay = false,
  });

  final StudySession session;
  final bool practice;
  final String? targetLang;

  /// The plan's run-through before the event — see [SessionScreen.planIsFinalDay].
  final bool planIsFinalDay;

  /// Start another practice session (used by the practice summary's «Ещё раз»).
  ///
  /// The SAME act as «Дотренировать» on a plan day that did not close (кадр D·06б): a fresh sitting
  /// of the same material, in place of this one. The plan's ladder owes only what is still open, so
  /// what comes back is the remainder rather than the day over again — which is why one callback
  /// serves both and there is no second way to re-enter a sitting.
  final VoidCallback onAgain;

  @override
  ConsumerState<_SessionShell> createState() => _SessionShellState();
}

class _SessionShellState extends ConsumerState<_SessionShell> {
  /// The one voice of the sitting — see [Pronouncer]. Built with the line cache so a reply of the
  /// scene is played from its own file and everything else keeps the system synthesiser
  /// (наряд TTS-1, Ч.2.2).
  late final Pronouncer _pronouncer;

  /// СЕРВЕРНАЯ ОЗВУЧКА РЕПЛИК этой посадки.
  late final LineAudioCache _lineAudio;
  final _scroll = ScrollController();
  int _pos = 0;
  bool _finished = false;
  bool _answered = false; // current card answered → the pinned «Дальше» bar shows
  bool _bannerDismissed = false;
  final List<({SessionCard card, LocalCheck verdict})> _results = [];

  List<SessionCard> get _cards => widget.session.cards;

  /// THE RUNNING ORDER of this sitting — where the next card is, and where the breaks fall.
  ///
  /// Everything about it lives in [SittingQueue]: a card answered wrong comes back once at the end
  /// of its own присест (Ч-5), and the boundaries the server cut move with that tail (Ч-6). Kept out
  /// of this shell because it is arithmetic, and this shell is a screen with a speech engine and an
  /// image cache in it.
  late SittingQueue _queue;

  /// True while the learner is between two присесты — the minimal service screen.
  bool _betweenSittings = false;

  /// Scenes whose dialogue has already been OPENED — кадр DL·01 is shown once per conversation.
  ///
  /// Keyed by the scene's own day, because a sitting can hold two of them: today's introduction and
  /// yesterday's conversation, and each conversation gets its own opening screen.
  final Set<int> _dialogueOpened = {};

  /// The scene whose conversation has just ENDED and whose finale (кадр DL·10) is owed, or null.
  int? _dialogueFinished;

  /// The speech engine has been raised — see the warm-up in [initState] and [PlanDialogueShell].
  bool _voiceWarm = false;

  /// The card index being played at the current position — the ORDER's, before the replay resolves
  /// which rung of it to deal.
  int get _slot => _queue.cardAt(_pos);

  /// A recognition slot is played at the pair's CURRENT rung, so a failed rung 1 is replayed rather
  /// than followed by rung 2 (QA-9). Resolved at DISPLAY time, which is the only moment that knows
  /// how the earlier cards went — the session itself was dealt before any of them were answered.
  late final RecognitionReplay _replay = RecognitionReplay(_cards, enabled: !widget.practice);

  /// The index actually being played at the current position — [_slot] unless it is replaying a
  /// rung the learner has not passed yet.
  int get _playing => _replay.resolve(_slot);
  SessionCard get _card => _cards[_playing];

  @override
  void initState() {
    super.initState();
    PerfLog.instance.screen = 'session'; // stall monitor: which screen a hitch belongs to
    _lineAudio = ref.read(lineAudioCacheProvider);
    _pronouncer = Pronouncer(null, _lineAudio);
    unawaited(_preloadVoices());
    // Raise the iOS audio session and prime the synthesizer ONCE, behind the loading spinner —
    // never on the first listening card, whose whole content is the sound (F20-r).
    // The pairs are not resolved yet, so this primes the engine with the session's fallback; every
    // utterance sets the language of the card it belongs to before speaking.
    // The result is WATCHED as well as awaited, because the dialogue screen has to know: канон §7
    // forbids handing the learner a line to answer before its voice can say it, and кадр DL·08 is
    // what stands in the bubble's place until then. One warm-up for the whole sitting — the shell
    // asks this engine to speak rather than raising one of its own.
    unawaited(
      _pronouncer
          .warmUp(targetLang: _sessionLang)
          // Settled either way: a device with no voice for this language is not a device the
          // learner can do anything about, and holding the conversation shut for ever over it would
          // be worse than a line they have to read.
          .whenComplete(() {
            if (mounted) setState(() => _voiceWarm = true);
          }),
    );
    // F20: warm the first few cards' photos up front so opening photo cards aren't cold network
    // loads (the lag the user saw was photo cards fetching + decoding late).
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _prepareCard(0);
      _prepareCard(1);
      _prepareCard(2);
    });
    unawaited(_resolvePairs());
    _queue = SittingQueue.of(
      cards: _cards.length,
      sittings: widget.session.plan?.sittings ?? const [],
    );
    unawaited(_restoreSitting());
  }

  /// ДОКАЧКА ОЗВУЧКИ ВСЕЙ ПОСАДКИ — вход в день, один залп, в фоне (наряд TTS-1, Ч.2.1).
  ///
  /// Всей, а не «до первой карточки»: реплики ВТОРОГО присеста обязаны быть готовы к его началу, а
  /// спасателей сегодня может не быть ни на одной карточке, хотя панель и разогрев их произносят.
  /// Пейлоад отдаёт весь список парами «текст → файл» именно для этого.
  ///
  /// Ничего не ждёт и ничего не блокирует: экран уже нарисован, а «Готовим озвучку» стоит ровно над
  /// той репликой, чей файл ещё не приехал ([_voiceReadyFor]). Уже скачанное живёт между сессиями,
  /// поэтому повторный вход в тот же день не ходит в сеть вообще.
  Future<void> _preloadVoices() async {
    final plan = widget.session.plan;
    if (plan == null) return;

    // СНАЧАЛА — «вот это реплики», и только потом файлы. Темп реплик ниже темпа слов НЕЗАВИСИМО от
    // того, кто их читает (канон §7): с выключенной трубой файлов не будет вовсе, а вопрос в восемь
    // слов всё равно не должен звучать со скоростью одиночного слова.
    _lineAudio.note([
      for (final dialogue in plan.dialogues)
        for (final turn in dialogue.turns) turn.text,
      for (var i = 0; i < _cards.length; i++)
        if (plan.kindAt(i) == 'line') _cards[i].answerText,
    ]);

    final lines = plan.lineAudio;
    if (lines.isEmpty) return;

    await _lineAudio.preload(lines, bearer: ref.read(tokenStoreProvider).current);
    if (mounted) setState(() {});
  }

  /// Можно ли подавать ЭТУ строку на слух: движок поднят, и её файл приехал, если он вообще есть.
  ///
  /// Строка, которую сервер не озвучивает, готова вместе с движком — её всегда собирался читать
  /// телефон, и ждать ей нечего.
  bool _voiceReadyFor(String? text) =>
      _voiceWarm && (text == null || _lineAudio.isReady(text));

  /// PICK THE SITTING BACK UP where it was left (Ч-6).
  ///
  /// The session itself has already been restored from the stored payload by the provider — this is
  /// the other half, the running ORDER, which is the only part of a присест the payload cannot
  /// carry: the tail a wrong answer added, the boundaries that moved with it, and the card that is
  /// next.
  ///
  /// Refused rather than half-applied when the stored order does not fit the session in hand: an
  /// index past the end of the cards would open a sitting on a card that is not there, and starting
  /// the day over is a far smaller loss than that.
  Future<void> _restoreSitting() async {
    final plan = widget.session.plan;
    if (plan == null || widget.practice || !plan.strict) return;

    final saved = await ref
        .read(planSittingStoreProvider)
        .restore(planId: plan.planId, dayIndex: plan.dayIndex);
    if (saved == null || !mounted) return;
    final resumed = SittingQueue.resume(
      cards: _cards.length,
      order: saved.order,
      ends: saved.sittingEnds,
      requeued: saved.requeued,
    );
    if (resumed == null) return;

    setState(() {
      _queue = resumed;
      _pos = saved.position.clamp(0, resumed.length - 1);
      _answered = false;
      // A resumed sitting opens ON its next card, never on the «Присест N пройден» plaque: the
      // learner already chose to continue by coming back.
      _betweenSittings = false;
    });
    _prepareCard(_pos);
  }

  /// WHICH PAIR each card belongs to — «EN→RU» over the card, resolved from the local mirror.
  ///
  /// A study session is MIXED by design (DECISIONS п. 128): the pool holds words from folders of
  /// different languages and deals them in one stream. Without a label a Polish word arriving in
  /// what the learner took to be an English session reads as a bug in the app.
  ///
  /// WHERE it is drawn depends on the session: when every card shares one pair — a collection-scoped
  /// session, and most of them — it is stated ONCE in the header, beside the phase, because
  /// repeating it over twenty cards is noise about something that is not changing. As soon as two
  /// pairs are in the same session it moves ONTO the card, where it changes with the card and can be
  /// read against the word it belongs to.
  Map<String, ({String learned, String support})> _pairs = const {};

  /// True when this session spans more than one pair — the case the per-card badge exists for.
  bool get _mixedPairs => _pairs.values.map((p) => '${p.learned}:${p.support}').toSet().length > 1;

  ({String learned, String support})? get _cardPair => _pairs[_card.termId];

  /// The plain HEADWORD of each card's term, out of the local mirror — what the seam's footnote
  /// names the card by ([_carriedCaption]).
  ///
  /// Off the term row rather than off the card, because a card's `answer` is its GRADING KEY and on
  /// two of the trainers that key is not words at all. Empty until the lookup lands, and the caption
  /// falls back to [SessionCard.answerText] meanwhile — a term missing from the mirror is ordinary
  /// (the pool outlives a deleted folder) and must not blank the line.
  Map<String, String> _termTexts = const {};

  Future<void> _resolvePairs() async {
    final ids = _cards.map((c) => c.termId).toList(growable: false);
    final db = ref.read(appDatabaseProvider);
    final pairs = await db.pairByTerms(ids);
    final texts = await db.termTextsByIds(ids);
    if (mounted) {
      setState(() {
        _pairs = pairs;
        _termTexts = texts;
      });
    }
  }

  /// The resolved photo url per card index. A present KEY means the lookup finished, which is what
  /// lets the card size its banner on the first frame instead of reserving 150 px and collapsing a
  /// moment later — that collapse was a 164 px jump right after the slide (F20-r).
  final Map<int, String?> _photoUrl = {};

  /// Indices already prepared. Each card used to be prepared TWICE (once as `+1`, once as `+2`),
  /// so a 20-card session paid 40 lookups and 40 decodes.
  final Set<int> _prepared = {};

  /// Only `multiple_choice` renders the banner in its prompt, so only those are worth decoding up
  /// front. Everything else pays a ~2.7 MB decode for a picture it never shows.
  bool _showsPhoto(int index) =>
      _photoUrl[index] != null && _cards[index].mode == ExerciseMode.multipleChoice;

  /// Resolve the card's photo (always — the layout needs to know) and warm it (only when the card
  /// actually shows one). No-op for an out-of-range or already-prepared index.
  void _prepareCard(int index) {
    if (index < 0 || index >= _cards.length || !mounted) return;
    if (!_prepared.add(index)) return;
    final card = _cards[index];
    ref.read(appDatabaseProvider).termById(card.termId).then((term) async {
      if (!mounted) return;
      final raw = term?.imageUrl ?? '';
      final url = raw.isEmpty ? null : raw;
      // Only the on-screen card needs a rebuild; a card resolved ahead of time is simply recorded.
      if (index <= _pos) {
        setState(() => _photoUrl[index] = url);
      } else {
        _photoUrl[index] = url;
      }
      if (url != null && card.mode == ExerciseMode.multipleChoice) {
        await precacheSessionImage(context, url);
      }
    });
  }

  @override
  void dispose() {
    PerfLog.instance.screen = 'app';
    // Hands the iOS audio session back (and un-ducks other audio) exactly once, here — not after
    // every spoken word, which is what froze the trainer for ~600 ms per utterance (F20-r).
    unawaited(_pronouncer.release());
    _scroll.dispose();
    super.dispose();
  }

  /// The session's FALLBACK language: the scoped collection's (F16), else the profile's. It is what
  /// a card is spoken in only while its own pair is unknown — the warm-up, which runs before
  /// [_resolvePairs] has answered, and a card whose collection is not in the local mirror.
  String get _sessionLang =>
      widget.targetLang ?? ref.read(authControllerProvider).value?.profile?.targetLanguage ?? 'en';

  /// The language a CARD is in — read off that card's own pair, the way the pair badge is.
  ///
  /// A study session is mixed by design (DECISIONS п. 128), so «what language is this» is a property
  /// of the card, never of the session or of the profile. Pinned to the profile it was the bug from
  /// the owner's phone: an Italian card read out by an English voice, in a session that also held
  /// English cards, with listening and dictation — whose whole content IS the sound — asking one
  /// language and pronouncing another.
  String _langOfCard(SessionCard card) => _pairs[card.termId]?.learned ?? _sessionLang;

  Future<void> _speak(String lang, String text, {bool slow = false}) async {
    // Reuse the Pronouncer, which speaks a Word — wrap the raw target text. [slow] backs the
    // listening card's «замедленно» replay.
    await _pronouncer.speak(
      Word(termId: '', term: text, translation: '', type: 'word'),
      targetLang: lang,
      slow: slow,
    );
  }

  void _onAnswered(SessionAnswer a) {
    // Read BEFORE the ladder moves: this is the card that was actually on screen, and after
    // [RecognitionReplay.record] the same slot may resolve elsewhere.
    final played = _card;
    // F20: while the user reads the feedback, warm the next TWO cards' photos so an upcoming photo
    // card meets a ready image instead of a cold network load (precache is the main image fix).
    _prepareCard(_pos + 1);
    _prepareCard(_pos + 2);
    // A wrong answer shows the photo in the feedback whatever the mode, and only `multiple_choice`
    // was warmed up front — so warm this one now. It lands on a static screen, never on a slide.
    if (a.verdict == LocalCheck.wrong && !_showsPhoto(_pos)) {
      final url = _photoUrl[_pos];
      if (url != null) precacheSessionImage(context, url);
    }
    _results.add((card: played, verdict: a.verdict));
    ref
        .read(reviewSyncProvider)
        .record(
          termId: played.termId,
          exerciseMode: played.mode.wire,
          response: a.response,
          usedHint: a.usedHint,
          isPractice: widget.practice,
          latencyMs: a.latencyMs,
          sessionId: widget.session.sessionId,
          // Echo the rung the card was dealt at — the rung of the card SHOWN, which after a replay
          // is not the one the slot was planned at. The server needs it to know a rung-1 answer is a
          // TAP graded by identity; without it the tapped term id is graded as text and a correct
          // tap becomes a lapse. It is also what makes the review log's rung column true.
          ladderStep: played.ladderStep,
        );
    // Move the session's own view of the ladder. A failed recognition leaves the pair where it is,
    // which is what makes the term's next slot replay this rung instead of dealing the next one.
    _replay.record(played, accepted: a.verdict.isAccepted);
    _requeueIfMissed(a.verdict);
    // Reveal the pinned «Дальше» bar. It lives OUTSIDE the scroll view, so it stays reachable no
    // matter how tall the feedback grows (the photo loads async and kept pushing an in-scroll
    // button below the fold — device-batch F9).
    setState(() => _answered = true);
    // AND THE SITTING HAS MOVED ON — «Проверить» is what passes a card, «Дальше» only turns the
    // page (E2E-SIM-2, С-13).
    //
    // The answer is already durable at this point (the review queue owns it), and the stored
    // POSITION used to move only on «Дальше». An app killed between the two came back on the card
    // just answered, with an empty field, and a second answer went into the append-only log in the
    // same mode. It did not move the ladder — `walk()` closes the first open step — but the learner
    // was asked a question they had already answered, which is the part they can see.
    _rememberFrom(_pos + 1);
  }

  /// Store the sitting's position as `$at`, or forget it when the sitting has run out.
  ///
  /// Forgetting is the honest end of the second case: a kill after the LAST answer has nothing left
  /// to resume, and a stored index past the end would be clamped back onto the final card — the very
  /// re-ask this is here to stop. The next visit asks the server, which deals whatever the ladder
  /// still owes, i.e. nothing for a day that is finished.
  void _rememberFrom(int at) {
    if (at >= _queue.length) {
      _forgetPosition();

      return;
    }
    _rememberPosition(at: at);
  }

  /// A CARD ANSWERED WRONG COMES BACK ONCE, at the end of the присест it was in (наряд SIT-1, Ч-5).
  ///
  /// «В конец очереди ТЕКУЩЕЙ посадки», and every word of that is load-bearing. Not immediately —
  /// re-showing a card the learner just got wrong tests the last twenty seconds and nothing else.
  /// Not tomorrow only — a slip they never revisit is a slip that had a whole evening to set. Once —
  /// the second miss belongs to the server: tomorrow's warm-up carries it (Ч-4) and the day's next
  /// visit re-owes its checklist step.
  ///
  /// The PROGRESS BAR does not roll back, and that falls out of doing it this way: the position
  /// stands still and the order grows, so what was passed stays passed and the tail simply gets
  /// longer. Rewinding the position would have been the other way to «play it again», and it would
  /// have told the learner they had un-done work they actually did.
  ///
  /// Plan sittings only, and strict ones only: free practice schedules nothing and the final day's
  /// run-through grades nothing, so in neither is there a mistake to make good.
  void _requeueIfMissed(LocalCheck verdict) {
    final plan = widget.session.plan;
    if (plan == null || widget.practice || !plan.strict) return;
    if (verdict.isAccepted) return;
    _queue.requeue(_pos);
  }

  /// WRITE DOWN WHERE WE ARE — after every move, so a kill costs nothing (Ч-6).
  ///
  /// The answers are already durable (the review queue owns them); what only this shell knows is the
  /// ORDER — which cards were sent to the tail, where the присест boundaries moved to, and which
  /// card is next. That is what is stored, beside the payload the sitting was dealt from, so
  /// «продолжить» resumes the sitting instead of asking the server to deal the day again.
  void _rememberPosition({int? at}) {
    final plan = widget.session.plan;
    if (plan == null || widget.practice || !plan.strict || plan is! PlanSession) return;

    unawaited(
      ref
          .read(planSittingStoreProvider)
          .save(
            PlanSittingState(
              planId: plan.planId,
              dayIndex: plan.dayIndex,
              payload: plan.raw,
              order: _queue.order,
              sittingEnds: _queue.ends,
              // Where the sitting RESUMES, which is not always where it is standing: a card that has
              // been answered is behind us even while its feedback is still on the screen (С-13).
              position: at ?? _pos,
              requeued: _queue.requeued,
            ),
          ),
    );
  }

  /// The day is over — the stored position goes with it, or the next visit would resume a sitting
  /// that has already been finished and closed.
  void _forgetPosition() {
    if (widget.session.plan == null) return;
    unawaited(ref.read(planSittingStoreProvider).clear());
  }

  /// A speaking card the MICROPHONE lost — «Пропустить» after a few failed attempts.
  ///
  /// It writes NOTHING outside a plan: no `_results` entry, so no tick or cross in the summary, and
  /// the word comes back on its own schedule as if this card had never been dealt — the honest
  /// reading of «the room was too noisy». The counter moves, so a card can never trap the learner.
  ///
  /// ## INSIDE A PLAN IT WRITES A LAPSE, and that is the change (E2E-SIM-2, С-4)
  ///
  /// A plan's stage is a CHECKLIST, and a checklist step is closed by an answer. A skip that wrote
  /// nothing left the speaking step of stage A open for ever — and stage A has to close for the day
  /// to pass, so a learner whose microphone was refused could not finish the day at all. Nothing on
  /// the screen said so: the card went by, the counter moved, and the day quietly stayed `ready`.
  ///
  /// So the step is closed HONESTLY rather than silently: an empty response, which the server grades
  /// `again` exactly as «Не помню» does. The card is re-owed tomorrow instead of being owed for ever.
  /// It is deliberately not a pass — a microphone is not evidence that the learner knew the line —
  /// and it is deliberately not silence either, because silence is what broke the day.
  void _skipCard() {
    PerfLog.instance.tapHandled('skip');
    final played = _card;
    if (widget.session.plan != null && !widget.practice) {
      ref
          .read(reviewSyncProvider)
          .record(
            termId: played.termId,
            exerciseMode: played.mode.wire,
            response: '',
            usedHint: false,
            isPractice: widget.practice,
            latencyMs: null,
            sessionId: widget.session.sessionId,
            ladderStep: played.ladderStep,
          );
      // The sitting has moved on for the same reason it does on «Проверить»: the answer is recorded,
      // so a kill here must not bring the card back (С-13).
      _rememberFrom(_pos + 1);
    }
    _prepareCard(_pos + 1);
    _prepareCard(_pos + 2);
    _next();
  }

  /// «Понятно» on an intro card. Nothing is graded and nothing reaches the review queue: the card
  /// asked for nothing, so there is no retrieval to log. What it produces is an EXPOSURE — durable,
  /// idempotent on the pair — plus the local ladder step, so the word's recognition cards later in
  /// this same session know it has been met even with the network off from start to summary.
  Future<void> _acknowledgeIntro() async {
    final termId = _card.termId;
    // Deliberately NOT added to [_results]: the summary lists answers, and an intro is not one —
    // a tick beside it would claim the word was got right. The word still reaches the summary,
    // through the recognition cards it comes back as later in this same session.
    await ref
        .read(exposureSyncProvider)
        .record(termId: termId, sessionId: widget.session.sessionId);
    if (!mounted) return;
    _prepareCard(_pos + 1);
    _prepareCard(_pos + 2);
    _next();
  }

  /// THE RUN IS OVER — and the server is told so HERE, not by whichever summary happens to be drawn.
  ///
  /// This is Д-1/Д-28. `sessionCompletionSync.record` used to live in the summary WIDGETS, one copy
  /// each, and the plan's summary was the second one — so for the whole live run
  /// `study_sessions.ended_at` stayed null on every plan sitting, `CompleteStudySession` never ran,
  /// the day stayed `ready` and day n+1 was never queued. 103 answers, zero
  /// `POST /study/sessions/{id}/complete`. The day only turned `done` when the learner opened the
  /// NEXT plan session, which is the very path PLAN-SESSION-FIX had declared closed, and the plan
  /// screen went on offering «Продолжить день 2» after day 3 (Д-40).
  ///
  /// Ending a session is a fact about the SESSION, so it belongs to the thing that owns one. Every
  /// way to the end funnels through [_next], and a summary widget is now free to be only a screen.
  ///
  /// Both writes are durable queues, so a day finished in airplane mode — or an app killed on the
  /// summary before the request left — still reaches `ended_at` on the next launch, when the home
  /// screen drains them.
  void _closeRun() {
    ref.read(reviewSyncProvider).flush();
    ref.read(sessionCompletionSyncProvider).record(sessionId: widget.session.sessionId);
  }

  void _next() {
    PerfLog.instance.tapHandled('next');
    // The verdict's auto-pronounce is fired on a timer AFTER the feedback settles, so a «Дальше»
    // tapped while it is still speaking used to carry the previous card's word over the slide and
    // finish it on top of the next card (QA-21). Cutting it here covers every way forward — the
    // «Дальше» bar, a microphone skip, an intro's «Понятно» — because all of them funnel through
    // this one method, including the last card's jump to the summary.
    unawaited(_pronouncer.stop());
    // A CONVERSATION THAT HAS JUST ENDED owes its finale (кадр DL·10) — the whole feed and three
    // facts about it. Read BEFORE the position moves, because «which scene were we in» is a
    // question about the card just answered; shown after, on whichever screen comes next.
    final leaving = _dialogueLeftAfter(_pos);
    if (_pos + 1 >= _queue.length) {
      _closeRun();
      _forgetPosition();
      setState(() {
        _dialogueFinished = leaving;
        _finished = true;
      });
    } else if (leaving != null) {
      // The finale comes FIRST and the присест break after it, when both fall here: one is the end
      // of a conversation and the other is the end of a stretch of work, and the second reads as an
      // anticlimax over the first.
      setState(() {
        _pos++;
        _answered = false;
        _dialogueFinished = leaving;
        _betweenSittings = _queue.breaksAfter(_pos - 1);
      });
      _rememberPosition();
    } else if (_queue.breaksAfter(_pos)) {
      // THE END OF A ПРИСЕСТ, and not of the day. The position moves — the card just answered is
      // behind us — and the learner is handed the service screen rather than the next section, so
      // stopping here is a decision they make instead of a thing that happens to them.
      setState(() {
        _pos++;
        _answered = false;
        _betweenSittings = true;
      });
      _rememberPosition();
    } else {
      setState(() {
        _pos++;
        _answered = false;
      });
      _rememberPosition();
      // Release the photo of a card three back: it is off-screen, out of the outgoing animation,
      // and nothing can navigate to it again. Keeps the session's decoded-image footprint flat
      // instead of climbing pass after pass within one app launch (F20-r).
      unawaited(evictSessionImage(context, _photoUrl[_pos - 3]));
      // New card starts at the top (the previous one may have been scrolled to its feedback).
      if (_scroll.hasClients) _scroll.jumpTo(0);
    }
  }

  /// The conversation the card at the FRONT belongs to — the shell's whole switch.
  PlanDialogue? get _dialogueHere => _dialogueAtPosition(_pos);

  /// Term ids of this sitting's answered cards — what puts «сказано вслух» under a bubble already
  /// in the feed (кадр DL·05). A fact, never a grade: it says the turn was taken, not that it was
  /// right.
  Set<String> get _spokenTerms => {for (final r in _results) r.card.termId};

  /// The sitting's conversation for the scene taught on [day], or null when it carries none.
  static PlanDialogue? _dialogueOf(PlanSessionEnvelope plan, int day) {
    for (final dialogue in plan.dialogues) {
      if (dialogue.dayIndex == day) return dialogue;
    }

    return null;
  }

  /// The conversation the card at position [at] belongs to, or null when it is not in one.
  PlanDialogue? _dialogueAtPosition(int at) {
    final plan = widget.session.plan;
    if (plan == null || at < 0 || at >= _queue.length) return null;

    return planDialogueAt(plan, _replay.resolve(_queue.cardAt(at)));
  }

  /// The scene whose conversation ENDS after the card at [at] — null when it does not.
  ///
  /// «Ends» means the next card of the sitting is in a different conversation or in none at all. The
  /// last card of the whole sitting ends one too: a day that stops on the last exchange still had a
  /// conversation in it.
  int? _dialogueLeftAfter(int at) {
    final here = _dialogueAtPosition(at);
    if (here == null) return null;
    final next = _dialogueAtPosition(at + 1);

    return next?.dayIndex == here.dayIndex ? null : here.dayIndex;
  }

  /// THE FACTS THE FINALE STATES — counted off the sitting, never guessed (кадр DL·10).
  ///
  /// «Отвечал сам» is the learner's own turns of this conversation that the sitting actually asked
  /// them to take; «разобрал на слух» is the role lines they were asked the meaning of. A turn the
  /// ladder did not owe today is in the FEED and not in the count — it is part of the conversation
  /// and it is not something the learner did this evening.
  ({int answered, int answerable, int heard, int hearable}) _dialogueFacts(PlanDialogue dialogue) {
    var answered = 0, answerable = 0, heard = 0, hearable = 0;
    // ACCEPTED, not «exactly right»: a typo is an accepted answer everywhere else in the product,
    // and a conversation is the last place to start counting it as a miss.
    final byTerm = <String, bool>{};
    for (final result in _results) {
      byTerm[result.card.termId] = result.verdict.isAccepted;
    }

    for (final turn in dialogue.turns) {
      final graded = byTerm[turn.termId];
      if (graded == null) continue;
      if (turn.isRole) {
        hearable++;
        if (graded) heard++;
      } else {
        answerable++;
        if (graded) answered++;
      }
    }

    return (answered: answered, answerable: answerable, heard: heard, hearable: hearable);
  }

  /// The plan's rescue phrases as this sitting holds them — the panel's whole content (кадр DL·09).
  ///
  /// Read off the WARM-UP's own cards rather than out of a config: the five phrases are ordinary
  /// cards of day 1 and the server marks them by shelf, so a client that matched them by text would
  /// show a different set the day somebody fixed a comma in the language pack.
  List<({String text, String? translation})> _rescuePhrases() {
    final plan = widget.session.plan;
    if (plan == null) return const [];

    final out = <({String text, String? translation})>[];
    final seen = <String>{};
    for (var i = 0; i < _cards.length; i++) {
      if (!plan.isWarmupAt(i)) continue;
      final card = _cards[i];
      final text = card.answerText.trim();
      if (text.isEmpty || !seen.add(text)) continue;
      // The cue is the phrase's own meaning in the learner's language wherever the card has one; a
      // card that has none shows the phrase alone rather than a string borrowed from elsewhere.
      final cue = card.prompt?.trim();
      out.add((text: text, translation: cue == null || cue.isEmpty || cue == text ? null : cue));
    }

    return out;
  }

  /// THE BAR OF A PLAN SITTING: two groups — «Разогрев» and «Сцена» — and the second one divided
  /// by part (наряд DAY-2, Ч.2.2; кадр D-02).
  ///
  /// The WARM-UP is its own group in brass — it is the same five-plus-five cards every morning and
  /// it belongs to the PLAN rather than to any scene (канон §5), so counting it into the scene would
  /// make the scene look longer than it is on exactly the mornings that were hardest. Everything
  /// else is «Сцена», with a division per part, each as wide as the number of cards in it, so a
  /// glance answers «сколько осталось» and «что дальше» at once.
  ///
  /// ## The group's number counts exactly the divisions it draws
  ///
  /// It used to count only TODAY's own cards while drawing the revision's divisions beside them, so
  /// the label and the bar were two statements about two different things and the label carried
  /// numbers from neither: «День 4/69». Both halves of that are gone — the label is «Сцена», which
  /// is what all of those cards are, and it counts them all.
  ///
  /// Read off the ORDER rather than off the payload: a card sent to the tail (Ч-5) belongs to the
  /// part it was in, so its own division grows and every other one stays exactly where it was —
  /// which is «пройденное не сгорает», drawn.
  _PlanProgress _planProgress() {
    final plan = widget.session.plan;
    final segments = <_ProgressSegment>[];
    var warmupTotal = 0;
    var warmupDone = 0;
    String? group;

    for (var at = 0; at < _queue.length; at++) {
      final card = _queue.cardAt(at);
      final done = at < _pos;
      if (plan!.isWarmupAt(card)) {
        warmupTotal++;
        if (done) warmupDone++;

        continue;
      }

      final key = planSeamGroupOf(plan, card);
      if (segments.isEmpty || group != key) {
        segments.add(_ProgressSegment(key: key, isDay: plan.isDayTaskAt(card)));
        group = key;
      }
      final segment = segments.last;
      segment.total++;
      if (done) segment.done++;
    }

    return _PlanProgress(
      warmupTotal: warmupTotal,
      warmupDone: warmupDone,
      segments: segments,
      sceneDone: segments.fold(0, (sum, s) => sum + s.done),
      sceneTotal: segments.fold(0, (sum, s) => sum + s.total),
    );
  }

  Future<bool> _confirmExit() async {
    // Silence the current utterance as the dialog opens, not after the pop: `dispose`'s `release`
    // does stop the engine, but it only runs once the screen is actually gone, so the word carried
    // on over the confirm dialog (QA-21). Staying (a cancelled dialog) simply leaves it quiet,
    // which is the right outcome for a word the learner has already read.
    unawaited(_pronouncer.stop());
    final l = AppLocalizations.of(context);
    final leave = await showCenterAlert(
      context: context,
      title: l.sessionExitTitle,
      message: l.sessionExitBody,
      confirmLabel: l.sessionExitConfirm, // «Выйти» — destructive
      cancelLabel: l.sessionExitCancel, // «Продолжить» — default
    );
    return leave ?? false;
  }

  /// «Слово worse идёт со дня 1 — сегодня оно на ступени B», or null when the line has nothing true
  /// to say. The seam's own footnote (E2E-SIM-2, С-3 and С-6).
  ///
  /// Three conditions, and each one of them is a defect this caption actually had:
  ///
  ///   ANSWERED   it used to be drawn from the first frame, so on «Ты ответишь» it printed the reply
  ///              the learner was about to choose out of four, and on the run-through's dictation
  ///              the sentence they were being asked to type. A note about where a card came from
  ///              reads exactly as well after the answer.
  ///   A STAGE    a task with no stage (the run-through) has no «сегодня оно на ступени N» to say,
  ///              and on the run-through every card is carried, so the line would be on all of them.
  ///   THE WORD   the term's own TEXT, out of the local mirror — never `card.answer`, which is the
  ///              GRADING KEY. On `situational_hear` that key is the id of an option, and the
  ///              caption printed «Слово «01M1MH57RKS1EPN0VRKJ5AXYC2» идёт со дня 1» (С-6).
  ///              [SessionCard.answerText] is the fallback: it already knows the identity-graded
  ///              recognition card, and it is what every other surface prints.
  String? _carriedCaption(AppLocalizations l, PlanSessionEnvelope plan) {
    if (!_answered) return null;
    final stage = plan.stageLetterAt(_playing);
    final from = plan.carriedFromAt(_playing);
    if (stage == null || from == null) return null;

    final text = (_termTexts[_card.termId] ?? _card.answerText).trim();

    return text.isEmpty ? null : l.planSessionCarried(text, from, stage);
  }

  /// THE RUNG'S OWN NAME for the card at the front — the same five words the word card, the pool row
  /// and the ladder strip use (Ч.4). Capitalised because it is a header; the ladder strip sets the
  /// identical words in lower case as captions, and [_taskDoing] lower-cases it again.
  String _phaseWord(AppLocalizations l) =>
      switch (sessionHeaderFor(mode: _card.mode, ladderStep: _card.ladderStep)) {
        SessionHeader.rungMeeting => _capitalized(l.ladderStep0),
        SessionHeader.rungRecognition => _capitalized(l.ladderStep1),
        SessionHeader.rungAssembly => _capitalized(l.ladderStep3),
        SessionHeader.rungWriting => _capitalized(l.ladderStep4),
        SessionHeader.rungDictation => _capitalized(l.ladderStep5),
        SessionHeader.phaseIntro => l.sessionPhaseIntro,
        SessionHeader.phaseAssemble => l.sessionPhaseAssemble,
        SessionHeader.phaseReview => l.sessionPhaseReview,
      };

  /// The rung names are captions first («узнавание», under a dot) and a header second. One string
  /// in the deck rather than two, capitalised where the layout calls for it — two entries would be
  /// two entries to keep in step, and this is exactly the drift Ч.4 exists to undo.
  static String _capitalized(String s) =>
      s.isEmpty ? s : s[0].toUpperCase() + s.substring(1);

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);

    final plan = widget.session.plan;

    // THE END OF A CONVERSATION — кадр DL·10, and it comes before every other interstitial.
    //
    // The whole feed and three facts about it, with not one percentage among them. It is the
    // milestone of the scene's stage B, so it is shown even when the sitting ends here: a day that
    // stopped on the last exchange still had a conversation in it.
    if (_dialogueFinished case final day? when plan != null) {
      final dialogue = _dialogueOf(plan, day);
      if (dialogue != null) {
        final facts = _dialogueFacts(dialogue);

        return PlanDialogueDone(
          dialogue: dialogue,
          answeredSelf: facts.answered,
          answerable: facts.answerable,
          heardOut: facts.heard,
          hearable: facts.hearable,
          // The rescue phrases are trained in the warm-up and are not counted as a move inside the
          // conversation yet — RESQ-1 owns «сколько раз просил повторить», and a zero invented here
          // would be a number the app does not know. The row is simply absent (кадр DL·10 draws it
          // only when there is something to draw).
          rescueUsed: 0,
          onDone: () => setState(() => _dialogueFinished = null),
        );
      }
      // A conversation this build cannot find has no finale to draw — carry on rather than freeze.
      _dialogueFinished = null;
    }

    // THE OPENING OF A CONVERSATION — кадр DL·01, once per scene.
    //
    // Two honest warnings and one action: the lines SOUND rather than being written, and answering
    // will be a move of the learner's own. Shown before the first card of the conversation, so the
    // first thing the learner meets is the scene and not an exercise.
    if (plan != null && !_betweenSittings && !_finished) {
      final opening = _dialogueAtPosition(_pos);
      if (opening != null && !_dialogueOpened.contains(opening.dayIndex)) {
        return PlanDialogueIntro(
          dialogue: opening,
          rescueCount: _rescuePhrases().length,
          onStart: () => setState(() => _dialogueOpened.add(opening.dayIndex)),
        );
      }
    }

    // BETWEEN TWO ПРИСЕСТЫ — the minimal service screen (Ч-6). Deliberately служебный: «красота —
    // DAY-2», and a milestone screen here would compete with the one the day itself ends on.
    if (_betweenSittings && plan != null) {
      return _SittingBreak(
        sitting: _queue.sittingAt(_pos - 1),
        sittings: _queue.sittings,
        remaining: _queue.length - _pos,
        onContinue: () => setState(() => _betweenSittings = false),
        onStop: () => Navigator.of(context).pop(),
      );
    }

    if (_finished) {
      // THE RUN-THROUGH ENDS THE PLAN, not a day (Д-27). The final day teaches nothing new — it is
      // the plan's own cards once each — so the milestone at the end of it is «подготовка
      // завершена», and that is the one act the app had no way of performing.
      if (plan != null && widget.planIsFinalDay) {
        return PlanRehearsalDone(
          planId: plan.planId,
          onDone: () => Navigator.of(context).pop(),
        );
      }

      // A plan day ends on its OWN summary (кадр 1c · 04): «День 1 пройден · 9 фраз и слов в
      // работе», the two lines that explain the stages, and what comes next. The ordinary summary
      // answers a different question — how the run went — and printing «повторено 14» over a day
      // the learner just finished would be a receipt where a milestone belongs.
      //
      // Neither of them closes the run any more: that happens in [_closeRun], when the last card is
      // answered, because ending a session is a fact about the session and not about which screen
      // is drawn over it (Д-1, Д-28).
      if (plan != null) {
        return PlanDaySummary(
          envelope: plan,
          cards: widget.session.cards,
          // HOW THE EVENING WENT, from the sitting's own verdicts: «Далось» and «Не далось» by name
          // (кадр D·06). Asking the server which cards went wrong would be a second opinion about an
          // evening it did not watch.
          results: _results,
          // «ДОТРЕНИРОВАТЬ» — the choice кадр D·06б offers when the day did not close.
          //
          // A FRESH SESSION for the same day, in place of this one: the plan is asked to deal the
          // day again and the ladder owes only what is still open, so «доделать» is one more short
          // sitting rather than the whole day over. `pushReplacement` keeps the back stack from
          // filling with finished sittings, exactly as «Ещё раз» does on a practice summary.
          onTrainMore: widget.planIsFinalDay ? null : widget.onAgain,
          onDone: () => Navigator.of(context).pop(),
        );
      }

      return _SessionSummary(
        results: _results,
        practice: widget.practice,
        onAgain: widget.onAgain,
        // Counted from the session's own cards, not from the answers: an intro produces no answer,
        // so it is not in [_results] at all — and the summary is reached only by playing every card,
        // which is what makes «dealt an intro» and «met the word» the same fact here.
        newWords: newWordCount(widget.session.cards),
      );
    }

    // THE ORDER, not the deck: a card sent to the tail lengthens the sitting, and the counter has to
    // say so or «5 из 27» would stay 27 while there are 28 cards left to play.
    final total = _queue.length;
    final phaseLabel = widget.practice ? l.sessionPhasePractice : _phaseWord(l);

    final autoPronounce = ref.watch(appSettingsProvider).value?.autoPronounce ?? true;

    final builtAt = _pos; // the index this card is built for — used to cancel its deferred effects
    final isIntro = _card.mode == ExerciseMode.intro;
    // Bound to THIS CARD rather than to «the current position»: a verdict's auto-pronounce fires
    // after [RecognitionReplay.record], by which point the slot may resolve to a different term —
    // and the word being spoken is still the one the learner just answered.
    //
    // The LOOKUP, though, happens at speak time, not here: [_resolvePairs] answers asynchronously,
    // and a card built before it landed would otherwise carry the fallback language for as long as
    // it is on screen — which is precisely the first card of the session.
    final played = _card;
    final cardLang = _langOfCard(played);
    Future<void> speakCard(String text, {bool slow = false}) =>
        _speak(_langOfCard(played), text, slow: slow);
    // The intro is not an exercise, so it is not the exercise widget. It has no options, no input
    // and no verdict — giving it its own widget is what keeps an "answer" with no answer in it out
    // of the card that owns answering.
    final card = isIntro
        ? SessionIntroCard(
            key: ValueKey(_pos),
            card: _card,
            // A LINE HAS NO EXAMPLE (канон §7): it IS the sentence being learned, so a sentence
            // written around it is a second card on the intro. The server stopped writing them and
            // cleaned out the ones it had written; the card refuses to draw one either way, because
            // a day generated before that fix still carries them in the local mirror.
            showExample: plan?.kindAt(_playing) != 'line',
            autoPronounce: autoPronounce,
            onSpeak: speakCard,
            photoUrl: _photoUrl[_pos],
            photoResolved: _photoUrl.containsKey(_pos),
            speechLocaleId: sttLocaleFor(cardLang),
            isCurrent: () => mounted && _pos == builtAt,
          )
        : SessionExerciseCard(
            key: ValueKey(_pos),
            card: _card,
            autoPronounce: autoPronounce,
            onAnswered: _onAnswered,
            onSpeak: speakCard,
            onSkipped: _skipCard,
            // The learner is speaking the language being LEARNED, whatever the app's own language
            // is — the same value the pronouncer speaks in, off the same card's pair.
            speechLocaleId: sttLocaleFor(cardLang),
            // And TYPING it in the same language. One value, three consumers (voice out, voice in,
            // keyboard + the layout guard) — a mixed session must not judge an Italian answer by
            // English's alphabet any more than it may read it out in an English voice.
            answerLang: cardLang,
            photoUrl: _photoUrl[_pos],
            photoResolved: _photoUrl.containsKey(_pos),
            showDue: !widget.practice,
            // THE POSITION and the spoken half — both facts about the DAY, so both come off the
            // plan's envelope and are null/false on every ordinary card.
            // THE «СИТУАЦИЯ» BLOCK IS THE SHELL'S JOB INSIDE A CONVERSATION, and the card must not
            // draw its own (наряд DAY-2, Ч.1.5: «их место занимает диалог»).
            //
            // Measured on the live run: the bubble said «говорит собеседник · текст скрыт» and the
            // card printed that very line underneath it — the screen hiding a sentence and spoiling
            // it in the same frame. The position is the conversation now: the line SOUNDS from the
            // bubble, «Показать текст» reveals it if the learner asks, and the вводка stands on the
            // dialogue's own opening screen.
            situation: _dialogueHere != null ? null : plan?.situationAt(_playing),
            speaksAfterChoice: plan?.speaksAfterChoiceAt(_playing) ?? false,
            // F20: still the on-screen card? A fast «Дальше» moves _pos on, so the outgoing card's
            // deferred speak/focus is cancelled instead of firing on the next card.
            isCurrent: () => mounted && _pos == builtAt,
          );

    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) async {
        if (didPop) return;
        if (await _confirmExit() && context.mounted) Navigator.of(context).pop();
      },
      // Stamps every touch so a handler can report how long the tap waited (see [PerfLog]).
      child: Listener(
        onPointerDown: (_) => PerfLog.instance.pointerDown(),
        child: Column(
          children: [
            if (widget.practice && !_bannerDismissed)
              _PracticeBanner(onClose: () => setState(() => _bannerDismissed = true)),
            Padding(
              padding: const EdgeInsets.fromLTRB(AppSpacing.screenH, 14, AppSpacing.screenH, 0),
              child: _SessionHeader(
                phaseLabel: phaseLabel,
                // THE PROGRESS OF A PLAN SITTING IS TWO GROUPS (кадр 6b): the warm-up in brass, and
                // the day with a division per section. Widths are proportional to the number of
                // cards, so the bar does not lie about how much is left — and the seam says what is
                // coming rather than only how far along we are.
                planProgress: plan == null
                    ? null
                    : _planProgress(),
                // WHAT PART OF THE SITTING THIS IS, beside the group's numbers — the part is the
                // sentence a person reads, the numbers are how far through it they are.
                sectionLabel: plan == null ? null : planSectionCaption(l, plan, _playing),
                // «A» / «B» — the rung, in brass, in the corner (кадр 6b). It is a mark for oneself
                // and not a grade, so it is never explained: the stage's meaning is on the day
                // screen, and repeating it over every card would be a legend nobody reads twice.
                stageBadge: plan?.stageLetterAt(_playing),
                // The plan's brass mark REPLACES the phase word in the header (кадр 1c · 03): the
                // rung's own name moves down to the caption over the task, where it can be said in
                // full beside the stage. Two labels competing for the one centred slot is how a
                // header ends up saying «Узнавание» over a session the learner opened from a plan.
                planBadge: plan == null
                    ? null
                    : l.planSessionBadge(plan.dayIndex),
                // One pair for the whole session: say it once, here, beside the phase. Mixed:
                // null, and the badge rides each card instead — see [_pairs].
                pair: _mixedPairs ? null : _pairs.values.firstOrNull,
                current: _pos + 1,
                total: total,
                onClose: () async {
                  if (await _confirmExit() && context.mounted) Navigator.of(context).pop();
                },
              ),
            ),
            Expanded(
              child: SingleChildScrollView(
                controller: _scroll,
                padding: const EdgeInsets.fromLTRB(
                  AppSpacing.screenH,
                  18,
                  AppSpacing.screenH,
                  AppSpacing.s26,
                ),
                child: _SlideSwitcher(
                  index: _pos,
                  // INSIDE A CONVERSATION the card is not alone on the screen: the feed stands above
                  // it, the line it answers sounds from the bubble in front of it, and the rescue
                  // button never leaves (серия «Диалог v1»). The card itself is untouched — такт 1
                  // and такт 2 are the situational trainers the app already has, and re-implementing
                  // them inside the shell is how two answers to one question come to differ.
                  child: (plan != null && _dialogueHere != null)
                      ? PlanDialogueShell(
                          dialogue: _dialogueHere!,
                          turnIndex: _dialogueHere!.turns.indexWhere(
                            (t) => t.termId == _card.termId,
                          ),
                          voiceReady: _voiceReadyFor(
                            PlanDialogueShell.liveRoleTurnOf(
                              _dialogueHere!,
                              _dialogueHere!.turns.indexWhere((t) => t.termId == _card.termId),
                            )?.text,
                          ),
                          onSpeak: (text) => unawaited(
                            _pronouncer.speakText(text, targetLang: _sessionLang),
                          ),
                          rescue: _rescuePhrases(),
                          answeredAloud: _spokenTerms,
                          card: card,
                        )
                      : (plan != null)
                      ? Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            // THE SEAMS of the sitting — see [planSeamCaption]. The warm-up, the
                            // pieces, meeting the scene's lines, the conversation; drawn once, on
                            // the first card of each, because a label over every card would be
                            // noise. The warm-up says WHY it is here, once, on its first card
                            // (кадр D-01): five phrases with no reason given read as a chore.
                            if (planSeamCaption(l, plan, _playing) case final seam?) ...[
                              _SectionSeam(
                                label: seam,
                                note: plan.isWarmupAt(_playing) ? l.planWarmupWhy : null,
                              ),
                              const SizedBox(height: 18),
                            ],
                            // «СТУПЕНЬ B · СБОРКА» USED TO STAND HERE, and nothing replaced it in
                            // the same slot (наряд DAY-2, Ч.2.2).
                            //
                            // Both halves of it had a better home already. The STAGE is a mark for
                            // oneself and wears the brass pill in the header. The rung's name —
                            // «сборка» — was read off `ladder_step`, which on a plan card is the
                            // PLAN's step number and not the pool's rung, so a `multiple_choice`
                            // card came out «СБОРКА» over its own honest instruction. And what the
                            // learner is DOING is on the card itself, where it has always been and
                            // where it is written per trainer: «фраза · выбери английский
                            // эквивалент», «фраза · скажи слово вслух»
                            // ({@see SessionExercise._instructionLine}). The part of the sitting
                            // moved to the progress bar, over the divisions it is about.
                            // WHOSE LINE THIS IS. Only on the interlocutor's — the learner's own
                            // needs no caption, and a label on every card would be noise. Without
                            // it a `role` line is dealt as a card like any other and reads as one
                            // to learn to SAY: the live run had the learner assembling and reading
                            // aloud «Hello. What seems to be the problem with your child?» (Д-8).
                            //
                            // Asked as «only ever recognised» rather than «speaker is the role»:
                            // `tier: understand` says the same thing about a card the server will
                            // never deal a production trainer for, and the caption has to hold for
                            // both or the newer half arrives unlabelled.
                            if (plan.isRecognitionOnlyAt(_playing)) ...[
                              Text(
                                l.planSpeakerRole.toUpperCase(),
                                style: AppText.blockLabel.copyWith(
                                  letterSpacing: 1.32,
                                  color: AppColors.brassInk,
                                ),
                              ),
                              const SizedBox(height: 14),
                            ],
                            card,
                            // «Слово worse идёт со дня 1 — сегодня оно на ступени B.» Drawn only
                            // for a word carried in from an EARLIER day, because for today's own
                            // words the sentence would say nothing.
                            //
                            // AFTER THE ANSWER, and never before it (E2E-SIM-2, С-3). The line
                            // names the card's own word, and on half the trainers that word IS the
                            // answer: on «Ты ответишь» it printed the very line the learner was
                            // about to pick out of four options, and on the final day's dictation it
                            // printed the sentence they were being asked to type. The caption is a
                            // note about where a card came from — it costs nothing to read it a
                            // moment later, and printing it early is simply the answer key.
                            //
                            // A card with no STAGE (the run-through) gets no caption at all: there
                            // every card comes from an earlier day, so the line would be on all of
                            // them and would have no stage to name.
                            if (_carriedCaption(l, plan) case final caption?) ...[
                              const SizedBox(height: AppSpacing.s26),
                              Container(
                                padding: const EdgeInsets.only(top: 14),
                                decoration: const BoxDecoration(
                                  border: Border(top: BorderSide(color: AppColors.dividerFaint)),
                                ),
                                child: Text(
                                  caption,
                                  style: AppText.translation.copyWith(
                                    fontSize: 13,
                                    height: 1.5,
                                    color: AppColors.tertiary,
                                  ),
                                ),
                              ),
                            ],
                          ],
                        )
                      : (_mixedPairs && _cardPair != null)
                      ? Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            // Top-right, above the card and outside it: the corner the eye reaches
                            // last, so it answers «which language» without competing with the word.
                            Padding(
                              padding: const EdgeInsets.only(bottom: 6),
                              child: Align(
                                alignment: Alignment.centerRight,
                                child: PairBadge(
                                  learned: _cardPair!.learned,
                                  support: _cardPair!.support,
                                ),
                              ),
                            ),
                            card,
                          ],
                        )
                      : card,
                ),
              ),
            ),
            // «Дальше» pinned below the scroll view so a tall feedback (async photo) can't push it
            // off-screen (device-batch F9). Appears only once the card is answered.
            //
            // The intro's «Понятно →» sits in the same bar and is there from the start: there is
            // nothing to answer first, so nothing to wait for. One exit, no verdict.
            if (isIntro)
              _NextBar(onNext: _acknowledgeIntro, label: l.sessionIntroGot)
            else if (_answered)
              _NextBar(onNext: _next),
          ],
        ),
      ),
    );
  }
}

/// The pinned bottom action bar — the session's «Дальше», always reachable regardless of how far
/// the feedback content scrolls. Carries the bottom safe-area inset itself (the shell's SafeArea
/// has `bottom: false`).
class _NextBar extends StatelessWidget {
  const _NextBar({required this.onNext, this.label});
  final VoidCallback onNext;

  /// Overridden by the intro card, whose single exit reads «Понятно →» — it acknowledges a word
  /// that was shown rather than advancing past one that was answered.
  final String? label;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Container(
      decoration: const BoxDecoration(
        color: AppColors.paper,
        border: Border(top: BorderSide(color: AppColors.hairline)),
      ),
      padding: EdgeInsets.fromLTRB(
        AppSpacing.screenH,
        12,
        AppSpacing.screenH,
        12 + MediaQuery.of(context).viewPadding.bottom,
      ),
      child: PrimaryButton(
        label: label ?? l.sessionNext,
        trailingIcon: LucideIcons.arrowRight,
        onPressed: onNext,
      ),
    );
  }
}

/// Header: close (×) · phase label · «N из M» · session segments (§2б). The segment bar reuses
/// [SessionSegments] — the same one the triage header uses.
class _SessionHeader extends StatelessWidget {
  const _SessionHeader({
    required this.phaseLabel,
    required this.current,
    required this.total,
    required this.onClose,
    this.pair,
    this.planBadge,
    this.planProgress,
    this.stageBadge,
    this.sectionLabel,
  });

  /// The part of the sitting the learner is in right now — «Диалог сцены» — drawn beside the scene
  /// group's numbers (кадры D-03 / D-04). Null outside a plan, and null for a part this build has no
  /// name for.
  final String? sectionLabel;

  /// The two-group bar of a plan sitting (кадр 6b), or null in an ordinary session — which keeps the
  /// one-line [SessionSegments] it has always had.
  final _PlanProgress? planProgress;

  /// «A» / «B» — the rung, in brass, in the header's right corner. A mark for oneself, not a grade.
  final String? stageBadge;

  /// «План · День 2» — the brass pill a plan session wears instead of the phase word.
  final String? planBadge;

  /// The session's pair when it has just one. Null when the session mixes pairs — the badge then
  /// belongs on the card, which is the only place it can change with the card.
  final ({String learned, String support})? pair;

  final String phaseLabel;
  final int current;
  final int total;
  final VoidCallback onClose;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Column(
      children: [
        Row(
          children: [
            Semantics(
              button: true,
              label: l.sessionClose,
              child: InkResponse(
                onTap: onClose,
                radius: 22,
                child: const SizedBox(
                  width: AppSpacing.minTap,
                  height: AppSpacing.minTap,
                  child: Icon(LucideIcons.x, size: 20, color: AppColors.secondary),
                ),
              ),
            ),
            Expanded(
              child: planBadge != null
                  // «День 2 из 4» AND NOTHING ELSE. The bare «5 из 27» that used to sit beside it
                  // was a number with no address (наряд DAY-2, Ч.2.2): it counted the whole sitting,
                  // the bar below counted parts, and the two disagreed on every screen the learner
                  // could compare them on. The counting lives on the bar now, where each number
                  // stands over the divisions it is about.
                  ? Center(
                      child: FittedBox(
                        fit: BoxFit.scaleDown,
                        child: PlanPill(planBadge!),
                      ),
                    )
                  : Text(
                      phaseLabel,
                      textAlign: TextAlign.center,
                      style: AppTextExercise.sessionHeader,
                    ),
            ),
            // A MINIMUM width, not a fixed one: «1 из 14» / «1 of 12» does not fit 44pt and was
            // wrapped to two lines the moment the denominator went double-digit (QA-OBS-28). The
            // 44pt floor is still there to balance the × on the left when the counter is short.
            ConstrainedBox(
              constraints: const BoxConstraints(minWidth: AppSpacing.minTap),
              // THE RUNG, in brass, in the corner the eye reaches last (кадр 6b) — a mark for
              // oneself, never explained. A plan card that has no stage (the warm-up's light touch,
              // the run-through) leaves the corner empty rather than borrowing the counter back: the
              // counter has moved, and two homes for one number is how they drift apart.
              child: stageBadge != null
                  ? Align(alignment: Alignment.centerRight, child: _StagePill(stageBadge!))
                  : planBadge != null
                  ? const SizedBox.shrink()
                  : Text(
                      l.triageCounter(current, total),
                      maxLines: 1,
                      softWrap: false,
                      textAlign: TextAlign.right,
                      style: AppTextExercise.sessionHeader,
                    ),
            ),
          ],
        ),
        // Centred UNDER the phase rather than beside it: the header row already carries a × and a
        // counter, and the counter has form — it wrapped to two lines the moment the denominator
        // went double-digit (QA-OBS-28). A third item competing for that row would find the same
        // edge on a narrow phone.
        if (pair case final p?) ...[
          const SizedBox(height: 4),
          Center(child: PairBadge(learned: p.learned, support: p.support)),
        ],
        const SizedBox(height: 10),
        if (planProgress case final progress?)
          _PlanProgressBar(progress: progress, sectionLabel: sectionLabel)
        else
          SessionSegments(done: current - 1, total: total),
      ],
    );
  }
}

/// «A» / «B» — the rung, latunью, bordered, and never explained (кадр 6b).
class _StagePill extends StatelessWidget {
  const _StagePill(this.letter);

  final String letter;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
    decoration: BoxDecoration(
      border: Border.all(color: AppColors.brassInk.withValues(alpha: .38)),
      borderRadius: BorderRadius.circular(5),
    ),
    child: Text(
      letter.toUpperCase(),
      style: AppText.blockLabel.copyWith(color: AppColors.brassInk, letterSpacing: .4),
    ),
  );
}

/// One section of the day inside the bar — how many cards it holds and how many are behind us.
class _ProgressSegment {
  _ProgressSegment({required this.key, required this.isDay});

  final String key;

  /// Is this part the DAY's own material? False for the revision of earlier days, which is drawn on
  /// the bar and counted out of «День N/M» — see [_SessionShellState._planProgress].
  final bool isDay;

  int total = 0;
  int done = 0;
}

/// The two groups of a plan sitting's bar — see [_SessionShellState._planProgress].
class _PlanProgress {
  const _PlanProgress({
    required this.warmupTotal,
    required this.warmupDone,
    required this.segments,
    required this.sceneDone,
    required this.sceneTotal,
  });

  final int warmupTotal, warmupDone, sceneDone, sceneTotal;
  final List<_ProgressSegment> segments;
}

/// THE BAR (кадр 6b): «Разогрев 5/5» in brass, a hairline, then «День 11/22» divided by section.
///
/// The two groups are laid out in proportion to the number of cards they hold, and each section's
/// division is as wide as its own share of the day — so the bar is a map of the sitting rather than
/// a percentage. Nothing about it moves backwards: a card sent to the tail lengthens its own
/// section and leaves every other one where it was.
class _PlanProgressBar extends StatelessWidget {
  const _PlanProgressBar({required this.progress, this.sectionLabel});

  final _PlanProgress progress;

  /// The part the learner is in right now — «Диалог сцены» — beside the group's own numbers
  /// (кадры D-03 / D-04). Null when this build has no name for it.
  final String? sectionLabel;

  static const _height = 5.0;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final hasWarmup = progress.warmupTotal > 0;
    final hasDay = progress.sceneTotal > 0;
    if (!hasWarmup && !hasDay) return const SizedBox.shrink();

    final scene = l.planSceneProgress(progress.sceneDone, progress.sceneTotal);

    return Row(
      crossAxisAlignment: CrossAxisAlignment.end,
      children: [
        if (hasWarmup)
          Expanded(
            flex: progress.warmupTotal,
            child: _group(
              label: l.planWarmupProgress(progress.warmupDone, progress.warmupTotal),
              labelColor: AppColors.brassInk,
              bars: [
                for (var i = 0; i < progress.warmupTotal; i++)
                  Expanded(
                    child: _bar(filled: i < progress.warmupDone ? 1 : 0),
                  ),
              ],
            ),
          ),
        if (hasWarmup && hasDay) ...[
          const SizedBox(width: 9),
          Container(width: 1, height: 16, color: AppColors.brassInk.withValues(alpha: .4)),
          const SizedBox(width: 9),
        ],
        if (hasDay)
          Expanded(
            flex: progress.sceneTotal,
            child: _group(
              // «Сцена 11/22 · Диалог сцены» — the numbers and the part in one line, which is
              // where кадр D-03 puts them. The part is what the learner is DOING; the numbers say
              // how far through the scene's material this sitting is.
              label: sectionLabel == null
                  ? scene
                  : l.planProgressWithSection(scene, sectionLabel!),
              labelColor: AppColors.tertiary,
              bars: [
                for (final segment in progress.segments)
                  Expanded(
                    flex: segment.total,
                    child: _bar(filled: segment.done / segment.total),
                  ),
              ],
            ),
          ),
      ],
    );
  }

  Widget _group({
    required String label,
    required Color labelColor,
    required List<Widget> bars,
  }) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    mainAxisSize: MainAxisSize.min,
    children: [
      Text(
        label,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: AppText.blockLabel.copyWith(color: labelColor, fontSize: 10),
      ),
      const SizedBox(height: 5),
      Row(children: [
        for (var i = 0; i < bars.length; i++) ...[
          bars[i],
          if (i != bars.length - 1) const SizedBox(width: 2),
        ],
      ]),
    ],
  );

  /// One division, filled LEFT-TO-RIGHT by how much of its own part is done.
  ///
  /// Two things about this were wrong and both were invisible rather than crashing — «точки разогрева
  /// не закрашиваются», скрин 04.09:
  ///
  ///   the fill was an unpositioned `Stack` child, so it was laid out under LOOSE constraints and a
  ///   `ColoredBox` with no child takes the smallest size it is allowed — zero height. The colour was
  ///   there and nothing was ever drawn with it;
  ///   `FractionallySizedBox` centres its child by default, so even at full height a half-done part
  ///   would have grown out of the middle of its own division.
  ///
  /// `Positioned.fill` makes the height tight and the alignment says which end the bar grows from.
  Widget _bar({required double filled}) => ClipRRect(
    borderRadius: BorderRadius.circular(2),
    child: SizedBox(
      height: _height,
      child: Stack(
        children: [
          Positioned.fill(child: ColoredBox(color: AppColors.track)),
          Positioned.fill(
            child: FractionallySizedBox(
              alignment: Alignment.centerLeft,
              widthFactor: filled.clamp(0, 1),
              child: const ColoredBox(color: AppColors.brassInk),
            ),
          ),
        ],
      ),
    ),
  );
}

/// «ПРИСЕСТ N ПРОЙДЕН» — the service screen between two sittings (Ч-6).
///
/// Deliberately plain: «красота — DAY-2», and anything more here would compete with the milestone
/// the DAY ends on. What it has to do is exactly two things — say that a stopping point has been
/// reached, and make continuing and stopping equally easy, because the whole point of a присест is
/// that leaving costs nothing.
class _SittingBreak extends StatelessWidget {
  const _SittingBreak({
    required this.sitting,
    required this.sittings,
    required this.remaining,
    required this.onContinue,
    required this.onStop,
  });

  /// Zero-based index of the sitting just finished, and how many there are in the day.
  final int sitting, sittings;

  /// Cards left in the whole day — what «Продолжить · осталось 11» counts.
  final int remaining;

  final VoidCallback onContinue, onStop;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);

    return Padding(
      padding: const EdgeInsets.fromLTRB(
        AppSpacing.screenH,
        AppSpacing.s26,
        AppSpacing.screenH,
        AppSpacing.s26,
      ),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            l.planSittingDone(sitting, sittings).toUpperCase(),
            textAlign: TextAlign.center,
            style: AppText.blockLabel.copyWith(color: AppColors.brassInk, letterSpacing: 1.32),
          ),
          const SizedBox(height: AppSpacing.s12),
          Text(
            l.planSittingRemaining(remaining),
            textAlign: TextAlign.center,
            style: AppText.translation.copyWith(height: 1.5),
          ),
          const SizedBox(height: AppSpacing.s26),
          PrimaryButton(label: l.planSittingContinue, onPressed: onContinue),
          const SizedBox(height: AppSpacing.s12),
          QuietButton(label: l.planSittingStop, onPressed: onStop),
        ],
      ),
    );
  }
}

/// The quiet practice plaque (кадр 12f): 6 % ink, no colour, closes with an × and doesn't
/// return until the next session.
class _PracticeBanner extends StatelessWidget {
  const _PracticeBanner({required this.onClose});
  final VoidCallback onClose;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Padding(
      padding: const EdgeInsets.fromLTRB(AppSpacing.screenH, 10, AppSpacing.screenH, 0),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        decoration: BoxDecoration(
          color: AppColors.faintInk,
          borderRadius: BorderRadius.circular(14),
        ),
        child: Row(
          children: [
            Container(
              width: 8,
              height: 8,
              decoration: const BoxDecoration(color: AppColors.tertiary, shape: BoxShape.circle),
            ),
            const SizedBox(width: 9),
            Expanded(
              child: Text(
                l.sessionPracticeBanner,
                style: AppText.translation.copyWith(color: AppColors.inkBody),
              ),
            ),
            InkResponse(
              onTap: onClose,
              radius: 18,
              child: const Icon(LucideIcons.x, size: 16, color: AppColors.tertiary),
            ),
          ],
        ),
      ),
    );
  }
}

/// Card-to-card transition (§4е «Переход к следующему заданию»): the outgoing card fades and
/// slides left, the incoming one arrives from the right. Reduce-motion → an instant swap.
class _SlideSwitcher extends StatelessWidget {
  const _SlideSwitcher({required this.index, required this.child});
  final int index;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    if (MediaQuery.of(context).disableAnimations) {
      return KeyedSubtree(key: ValueKey(index), child: child);
    }
    return AnimatedSwitcher(
      duration: AppMotion.nextTaskEnter,
      switchInCurve: AppMotion.easeOut,
      switchOutCurve: AppMotion.easeIn,
      transitionBuilder: (child, anim) {
        final incoming = child.key == ValueKey(index);
        final offset = Tween<Offset>(
          begin: Offset(incoming ? 0.06 : -0.06, 0),
          end: Offset.zero,
        ).animate(anim);
        return FadeTransition(
          opacity: anim,
          child: SlideTransition(position: offset, child: child),
        );
      },
      layoutBuilder: (current, previous) =>
          Stack(alignment: Alignment.topCenter, children: [...previous, ?current]),
      child: KeyedSubtree(key: ValueKey(index), child: child),
    );
  }
}

// ── summary (кадр 12e) ────────────────────────────────────────────────────────

class _SessionSummary extends ConsumerStatefulWidget {
  const _SessionSummary({
    required this.results,
    required this.practice,
    required this.onAgain,
    this.newWords = 0,
  });

  final List<({SessionCard card, LocalCheck verdict})> results;
  final bool practice;

  /// Words INTRODUCED in this run — see [newWordCount]. Practice introduces nothing and never
  /// shows this stat.
  final int newWords;

  /// «Ещё раз» (practice only): start a fresh practice session right away.
  final VoidCallback onAgain;

  @override
  ConsumerState<_SessionSummary> createState() => _SessionSummaryState();
}

class _SessionSummaryState extends ConsumerState<_SessionSummary> {
  @override
  void initState() {
    super.initState();
    // A gentle success — no confetti (§4е). The run itself was closed when the last card was
    // answered ({@see _SessionShellState._closeRun}), not here: which screen is drawn over the end
    // of a session is not what «the session ended» means (Д-1, Д-28).
    WidgetsBinding.instance.addPostFrameCallback((_) => AppHaptics.success());
  }

  int get _total => widget.results.length;
  int get _errors => widget.results.where((r) => r.verdict == LocalCheck.wrong).length;
  // Words met for the first time in this run, counted by the shell from the session's own cards
  // (see [newWordCount]) — the answers alone cannot tell, since an intro produces none.
  int get _new => widget.newWords;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final struggling = widget.results.where((r) => r.verdict == LocalCheck.wrong).toList();
    final practice = widget.practice;

    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(
        AppSpacing.screenH,
        14,
        AppSpacing.screenH,
        AppSpacing.s26,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(l.sessionSummaryTitle, style: AppTextExercise.summaryTitle),
          const SizedBox(height: 18),
          // IntrinsicHeight bounds the row's height so the vertical dividers can stretch to it.
          // Without it, `CrossAxisAlignment.stretch` under the scroll view's unbounded height blew
          // the row up in RELEASE (asserts off), pushing the goal card, word list and Done button
          // off-screen — the whole summary looked like just three counters (device-batch F11).
          //
          // Practice gets a COMPACT two-stat tally («прошёл N, ошибки M») — there's no «New» (it
          // introduces nothing) and no daily-goal block (it moves nothing), per Training Loop v2/F17.
          IntrinsicHeight(
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              // Every label takes its own count: «1 НОВОЕ», not «1 НОВЫХ» (QA-OBS-12). The four
              // counter strings are plural-shaped even where the word doesn't inflect, so a call
              // site can't pass one counter's number with another's label.
              children: practice
                  ? [
                      _Stat(value: _total, label: l.sessionPracticeStatDone(_total)),
                      const _StatDivider(),
                      _Stat(value: _errors, label: l.sessionStatErrors(_errors)),
                    ]
                  : [
                      _Stat(value: _total, label: l.sessionStatReviewed(_total)),
                      const _StatDivider(),
                      _Stat(value: _new, label: l.sessionStatNew(_new)),
                      const _StatDivider(),
                      _Stat(value: _errors, label: l.sessionStatErrors(_errors)),
                    ],
            ),
          ),
          if (!practice) ...[const SizedBox(height: 18), const _GoalCard()],
          const SizedBox(height: 20),
          Text(l.sessionSessionWords.toUpperCase(), style: AppText.sectionLabel),
          const SizedBox(height: 6),
          // Practice never schedules, so a «увидишь через N дней» line would be a lie — hide it.
          for (final r in widget.results)
            _SummaryWordRow(card: r.card, verdict: r.verdict, showDue: !practice),
          // «Проседает → Новый пример» regenerates content, which is about progress-bearing study —
          // omit it in practice (compact итог).
          if (!practice && struggling.isNotEmpty) ...[
            const SizedBox(height: 16),
            _StrugglingCard(
              termId: struggling.first.card.termId,
              term: struggling.first.card.answerText,
            ),
          ],
          const SizedBox(height: 20),
          if (practice) ...[
            // «Ещё раз» → a fresh practice session immediately; «Готово» exits.
            PrimaryButton(
              label: l.sessionPracticeAgain,
              trailingIcon: LucideIcons.rotateCw,
              onPressed: widget.onAgain,
            ),
            const SizedBox(height: 10),
            Center(
              child: QuietButton(
                label: l.sessionDone,
                onPressed: () => Navigator.of(context).maybePop(),
              ),
            ),
          ] else
            PrimaryButton(label: l.sessionDone, onPressed: () => Navigator.of(context).maybePop()),
        ],
      ),
    );
  }
}

class _Stat extends StatelessWidget {
  const _Stat({required this.value, required this.label});
  final int value;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('$value', style: AppTextExercise.summaryNumber),
          const SizedBox(height: 5),
          Text(label.toUpperCase(), style: AppTextExercise.summaryLabel),
        ],
      ),
    );
  }
}

class _StatDivider extends StatelessWidget {
  const _StatDivider();
  @override
  Widget build(BuildContext context) => Container(
    width: 1,
    margin: const EdgeInsets.symmetric(horizontal: 16),
    color: AppColors.hairline,
  );
}

/// Daily-goal card: the day's NEW WORDS against the day's goal, plus the streak. Filled and
/// labelled «закрыта» once the goal is met (кадр 12e).
///
/// Reads [dailyGoalProvider] — the same counter the home screen's ring reads, which is the whole
/// point of it existing (QA-BUG-2). This card used to print today's ANSWERS here («8 / 20») while
/// the home screen printed the new words («3 / 20») on the same day, and both called it «Дневная
/// цель». The session's own answer count is still on this screen — as the «повторено» stat above,
/// where it is a fact about the run and nothing is divided by a goal.
class _GoalCard extends ConsumerWidget {
  const _GoalCard();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final ring = ref.watch(dailyGoalProvider);
    final today = ring.done;
    final goal = ring.goal;
    final streak = ref.watch(statsProvider).value?.streakDays ?? 0;
    final done = today >= goal;

    return PaperCard(
      radius: AppRadii.alert,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  done ? l.sessionGoalClosed : l.sessionDailyGoal,
                  style: AppText.sheetButton.copyWith(fontSize: 14),
                ),
              ),
              Text('$today / $goal', style: AppText.counterHeader.copyWith(fontSize: 13)),
            ],
          ),
          const SizedBox(height: 10),
          ProgressLine(value: goal == 0 ? 1 : today / goal, height: 4),
          if (streak > 0) ...[
            const SizedBox(height: 9),
            Text(
              l.sessionStreak(streak),
              style: AppText.translation.copyWith(fontSize: 12.5, color: AppColors.inkBody),
            ),
          ],
        ],
      ),
    );
  }
}

class _SummaryWordRow extends ConsumerWidget {
  const _SummaryWordRow({required this.card, required this.verdict, this.showDue = true});
  final SessionCard card;
  final LocalCheck verdict;

  /// Practice sessions move no schedule → hide the «увидишь через N дней» line (it would be a lie).
  final bool showDue;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final prog = showDue ? ref.watch(termProgressForProvider(card.termId)) : null;
    final due = prog?.value?.dueAt;
    final relative = due == null
        ? null
        : () {
            final days = daysUntil(due.toLocal(), DateTime.now());
            return days == 0
                ? l.sessionDueToday
                : days == 1
                ? l.sessionDueTomorrow
                : l.sessionDueInDays(days);
          }();

    return Container(
      padding: const EdgeInsets.symmetric(vertical: 11),
      decoration: const BoxDecoration(
        border: Border(bottom: BorderSide(color: AppColors.hairline)),
      ),
      child: Row(
        children: [
          _VerdictMark(verdict: verdict),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // The reviewed word, as words — a rung-1 card's [answer] is its term id (see
                // SessionCard.answerText), and the mistakes list is exactly where it would show.
                Text(
                  card.answerText,
                  style: AppText.termInList,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 2),
                // The TRANSLATION, never the prompt: on a rung-1 card the prompt is the term itself,
                // and printing it under the headline gave «cold / cold» — a word explained by itself
                // (see [SessionCard.translationText]).
                Text(
                  card.translationText,
                  style: AppText.translation.copyWith(fontSize: 12),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ),
          if (relative != null) ...[
            const SizedBox(width: 10),
            Text(relative, style: AppText.counterSmall.copyWith(color: AppColors.tertiary)),
          ],
        ],
      ),
    );
  }
}

class _VerdictMark extends StatelessWidget {
  const _VerdictMark({required this.verdict});
  final LocalCheck verdict;

  @override
  Widget build(BuildContext context) {
    // Correct → sage check; typo → amber dash (accepted but shaky); wrong → terracotta cross.
    return switch (verdict) {
      LocalCheck.correct => const Icon(LucideIcons.check, size: 18, color: AppColors.verdictKnown),
      LocalCheck.typo => Container(width: 18, height: 2, color: AppColors.verdictUnsure),
      LocalCheck.wrong => const Icon(LucideIcons.x, size: 18, color: AppColors.destructiveText),
    };
  }
}

/// «Проседает» block (кадр 12e): for a word missed this session, offer a fresh example
/// (B6 `POST /terms/{id}/regenerate-example`) — sometimes it's the context, not the word. Counts
/// against the daily quota (429 → «лимит исчерпан»).
class _StrugglingCard extends ConsumerStatefulWidget {
  const _StrugglingCard({required this.termId, required this.term});
  final String termId;
  final String term;

  @override
  ConsumerState<_StrugglingCard> createState() => _StrugglingCardState();
}

class _StrugglingCardState extends ConsumerState<_StrugglingCard> {
  bool _busy = false;
  bool _done = false;
  String? _error;

  Future<void> _regenerate() async {
    if (_busy || _done) return;
    final l = AppLocalizations.of(context);
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await ref.read(apiClientProvider).regenerateExample(widget.termId);
      // The new example replaces the stored one server-side; it arrives on the next sync/study.
      ref.read(syncServiceProvider).sync();
      if (mounted) setState(() => _done = true);
    } on DioException catch (e) {
      if (mounted) {
        setState(
          () => _error = e.response?.statusCode == 429
              ? l.sessionNewExampleExhausted
              : e.message ?? '',
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return PaperCard(
      radius: AppRadii.alert,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            l.sessionStrugglingTitle(widget.term),
            style: AppText.sheetButton.copyWith(fontSize: 14),
          ),
          const SizedBox(height: 6),
          Text(
            l.sessionStrugglingBody,
            style: AppText.translation.copyWith(
              fontSize: 12.5,
              color: AppColors.inkBody,
              height: 1.45,
            ),
          ),
          const SizedBox(height: 12),
          if (_error != null) ...[
            Text(
              _error!,
              style: AppText.translation.copyWith(fontSize: 12.5, color: AppColors.destructiveText),
            ),
            const SizedBox(height: 10),
          ],
          Align(
            alignment: Alignment.centerLeft,
            child: _done
                ? Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(LucideIcons.check, size: 17, color: AppColors.verdictKnown),
                      const SizedBox(width: 8),
                      Text(
                        l.sessionNewExample,
                        style: AppTextExercise.answerAuxButton.copyWith(
                          color: AppColors.verdictKnown,
                        ),
                      ),
                    ],
                  )
                : QuietButton(
                    label: l.sessionNewExample,
                    icon: _busy ? null : LucideIcons.sparkles,
                    foreground: AppColors.ink,
                    onPressed: _busy ? null : _regenerate,
                  ),
          ),
        ],
      ),
    );
  }
}

class _CenteredMessage extends StatelessWidget {
  const _CenteredMessage({
    required this.text,
    this.icon,
    this.iconColor = AppColors.verdictKnown,
    this.actionLabel,
    this.onAction,
  });
  final String text;
  final IconData? icon;

  /// Green by default — the empty states this screen shows are «всё сделано», not faults. A
  /// failure passes its own colour (QA-OBS-30).
  final Color iconColor;

  /// Optional recovery action — «Повторить» on a failed session build. Absent for the empty
  /// states, where there is nothing to retry.
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        Positioned(
          top: 8,
          left: 8,
          child: Semantics(
            button: true,
            label: AppLocalizations.of(context).sessionClose,
            child: InkResponse(
              onTap: () => Navigator.of(context).maybePop(),
              radius: 22,
              child: const SizedBox(
                width: AppSpacing.minTap,
                height: AppSpacing.minTap,
                child: Icon(LucideIcons.x, size: 20, color: AppColors.secondary),
              ),
            ),
          ),
        ),
        Center(
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.s26),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                if (icon != null) ...[
                  Icon(icon, size: 48, color: iconColor),
                  const SizedBox(height: 12),
                ],
                Text(
                  text,
                  textAlign: TextAlign.center,
                  style: AppText.stepTitle.copyWith(fontSize: 20),
                ),
                if (actionLabel != null && onAction != null) ...[
                  const SizedBox(height: AppSpacing.s16),
                  QuietButton(label: actionLabel!, icon: LucideIcons.rotateCw, onPressed: onAction),
                ],
              ],
            ),
          ),
        ),
      ],
    );
  }
}

/// WHICH PART OF THE SITTING the card at [i] stands in — the thing the seams are drawn between.
///
/// A key rather than a caption, because two shelves share one: канон §2 puts «слова и связки» under
/// a single heading, so `words` and `chunks` are one part of the sitting and a seam between them
/// would announce a change the learner cannot see.
///
/// A shelf this build has never heard of is returned as itself — it groups with its own kind and
/// gets no caption below, which is the honest answer for a shelf whose name we do not know.
String planSeamGroupOf(PlanSessionEnvelope plan, int i) => _planSeamGroup(plan, i);

String _planSeamGroup(PlanSessionEnvelope plan, int i) {
  // THE SERVER NAMES THE PART, since DAY-2. The client cannot derive it any more and it should never
  // have had to: meeting a reply and speaking it in the conversation are two parts of the sitting,
  // and `shelf` says `say` for both. The DAY rides in the key because one sitting can hold two
  // scenes — today's introduction and yesterday's conversation — and «Диалог» announced once over
  // two conversations is a lie about both.
  final code = plan.sectionCodeAt(i);
  if (code != null && code.isNotEmpty) {
    return code == PlanSessionTask.sectionCodeWarmup
        ? code
        : '$code#${plan.carriedFromAt(i) ?? 0}';
  }

  // A payload from a server that predates the field: the shelf decides, exactly as it did.
  if (plan.isWarmupAt(i)) return PlanTermRow.shelfRescue;
  // Everything that is not today's own material is the revision, whatever shelf it came off
  // originally: it is being replayed, not taught.
  if (!plan.isDayTaskAt(i)) return _seamGroupReview;

  return switch (plan.shelfAt(i)) {
    PlanTermRow.shelfChunks => PlanTermRow.shelfWords,
    final shelf? => shelf,
    // A day written before the scene: one undivided block, exactly as it is drawn today.
    null => _seamGroupDay,
  };
}

const _seamGroupReview = 'review';
const _seamGroupDay = 'day';

/// The caption to draw ABOVE the card at [i], or null when this card needs none.
///
/// Null in the two cases that are not a seam: the card stands in the same part of the sitting as
/// the one before it, or its part has nothing to announce — the day's own undivided block on an
/// older payload, the numbers the server stores but does not deal yet, a shelf a newer server
/// invented. Silence there is deliberate: a caption made up from a shelf name we cannot read would
/// be the plan telling the learner something the plan does not know.
///
/// Driven off the CARDS and not off an assumed running order. The server fixes only «разогрев
/// первым» today and the full order is a later наряд, so the seam is «первая карточка, у которой
/// полка другая» — which stays true whatever the order becomes.
String? planSeamCaption(AppLocalizations l, PlanSessionEnvelope plan, int i) {
  final group = _planSeamGroup(plan, i);
  if (i > 0 && _planSeamGroup(plan, i - 1) == group) return null;

  return planSectionCaption(l, plan, i);
}

/// THE CONVERSATION the card at [i] belongs to, or null when it is not part of one.
///
/// Two conditions and both are the server's: the card is in the `dialogue` part of the sitting, and
/// the payload carries that scene's chain. A card of the conversation whose chain never arrived —
/// an older server, a scene whose chain the day lost — falls back to the ordinary card layout,
/// which is what the sitting looked like before this наряд.
PlanDialogue? planDialogueAt(PlanSessionEnvelope plan, int i) {
  if (plan.sectionCodeAt(i) != PlanSessionTask.sectionCodeDialogue) return null;
  // The card's OWN scene, not the day being studied: the conversation dealt on day 2 is scene 1's.
  final day = plan.carriedFromAt(i) ?? plan.dayIndex;
  for (final dialogue in plan.dialogues) {
    if (dialogue.dayIndex == day) return dialogue;
  }

  return null;
}

/// THE NAME OF THE PART the card at [i] stands in — «Разогрев», «Слова и связки», «Знакомство с
/// репликами», «Диалог сцены» — or null when this build has nothing true to call it.
///
/// Drawn in two places and therefore written once: as the seam over the first card of a part, and
/// beside the progress group's own numbers («Сцена 11/22 · Диалог сцены», кадры D-03 / D-04).
///
/// A part that belongs to an EARLIER scene says so — «Диалог сцены · сцена 1» — because a sitting
/// can hold two of them and the learner is entitled to know which conversation they are in.
///
/// Silence for a code this build has never heard of is deliberate: a caption invented from a code
/// we cannot read would be the plan telling the learner something the plan does not know.
String? planSectionCaption(AppLocalizations l, PlanSessionEnvelope plan, int i) {
  final code = plan.sectionCodeAt(i);
  final name = code == null || code.isEmpty
      ? _legacySectionName(l, plan, i)
      : switch (code) {
          PlanSessionTask.sectionCodeWarmup => l.planWarmupSection,
          PlanSessionTask.sectionCodeWords => l.planShelfWords,
          PlanSessionTask.sectionCodeDialogueIntro => l.planSectionDialogueIntro,
          PlanSessionTask.sectionCodeDialogue => l.planSectionDialogue,
          PlanSessionTask.sectionCodeNumbers => l.planSectionNumbers,
          PlanSessionTask.sectionCodeRehearsal => l.planSectionRehearsal,
          PlanSessionTask.sectionCodeReview => l.planReviewSection,
          _ => null,
        };
  if (name == null) return null;

  // The WARM-UP belongs to the plan and to no scene (канон §5: «набор принадлежит ПЛАНУ»), and its
  // cards live on day 1 — so the day they came from would name a scene they are not part of.
  final from = code == PlanSessionTask.sectionCodeWarmup || plan.isWarmupAt(i)
      ? null
      : plan.carriedFromAt(i);

  return from == null ? name : l.planSectionOfScene(name, from);
}

/// The part's name off the SHELF — for a payload written before the server named it.
String? _legacySectionName(AppLocalizations l, PlanSessionEnvelope plan, int i) {
  if (plan.isWarmupAt(i)) return l.planWarmupSection;
  if (!plan.isDayTaskAt(i)) return l.planReviewSection;

  return switch (plan.shelfAt(i)) {
    PlanTermRow.shelfHear => l.planShelfHear,
    PlanTermRow.shelfSay => l.planShelfSay,
    PlanTermRow.shelfAsk => l.planShelfAsk,
    PlanTermRow.shelfWords || PlanTermRow.shelfChunks => l.planShelfWords,
    _ => null,
  };
}

/// «— ПОВТОРЕНИЕ —»: a line with a word in it between two parts of a plan sitting.
///
/// A rule rather than a header: the cards after it are played exactly the same way, so the seam has
/// to be visible without claiming to be a new screen.
class _SectionSeam extends StatelessWidget {
  const _SectionSeam({required this.label, this.note});

  final String label;

  /// One line under the rule saying WHY this part is here — «чтобы было чем ответить, если
  /// растеряешься». Only the warm-up has one: the other parts are self-explanatory once named, and
  /// a note under every seam would be a legend nobody reads twice.
  final String? note;

  @override
  Widget build(BuildContext context) => Column(
    children: [
      Row(
        children: [
          const Expanded(child: Divider(height: 1, thickness: 1, color: AppColors.dividerFaint)),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: AppSpacing.s12),
            child: Text(
              label.toUpperCase(),
              style: AppText.blockLabel.copyWith(letterSpacing: 1.32, color: AppColors.tertiary),
            ),
          ),
          const Expanded(child: Divider(height: 1, thickness: 1, color: AppColors.dividerFaint)),
        ],
      ),
      if (note != null) ...[
        const SizedBox(height: 8),
        Text(
          note!,
          textAlign: TextAlign.center,
          style: AppText.translation.copyWith(
            fontSize: 13,
            height: 1.45,
            color: AppColors.tertiary,
          ),
        ),
      ],
    ],
  );
}
