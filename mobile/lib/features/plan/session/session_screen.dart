import 'dart:async';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/day_plate.dart';
import 'package:eng_std/ui/native_text.dart';

import '../../../data/app_version.dart';
import '../../../data/languages.dart' show sttLocaleFor;
import '../../../data/plan/day_window.dart' show WindowPhrase, WindowSourceRef, WindowStageState;
import '../../../data/plan/plan_models.dart';
import '../../../data/plan/session/dialogue_feed.dart';
import '../../../data/plan/session/session_models.dart';
import '../../../data/plan/session/session_summary.dart';
import '../../../data/plan/session/speech_match.dart';
import '../../../data/providers.dart';
import '../../../data/speech/speech_turn.dart' show SpeechTurnConfig;
import '../../profile/profile_screen.dart';
import '../../profile/qa_report_button.dart' show QaReportHidden;
import '../conversation/conversation_controller.dart';
import '../conversation/talk_entry.dart';
import '../conversation/talk_screen.dart';
import '../conversation/talk_summary.dart';
import '../notify_prompt.dart';
import '../plan_providers.dart';
import 'cards/card_host.dart';
import 'cards/card_kit.dart';
import 'parts/session_bits.dart';
import 'parts/session_chrome.dart';
import 'parts/session_stage.dart';
import 'session_controller.dart';
import 'mic_ask.dart';
import 'session_mic.dart';
import 'session_texts.dart';
import 'session_voice.dart';

/// DAY SESSION (work orders SESSION-1b, SESSION-1c; canvas `session-canvas.dc.html`, series 30–35) — one screen: stage
/// entry (30-1), cards with the header (30-2) and the scene strip (30-2b), stage summaries (30-6, 33-8, 34-8, 35-6),
/// the day summary with «Close the day» (30-7), exit (30-8).
///
/// Every stage has its screens. The server is the source of truth: every entry reads the day anew and continues from
/// the first unanswered card; a day with every card answered opens on its summary. «Close the day» returns to the day
/// window, which reads the plan again. [replay] — «Once more» on a passed day: the stage is walked again on the phone,
/// nothing is sent, and the day summary follows (SESSION-2a §4).
class SessionScreen extends ConsumerStatefulWidget {
  const SessionScreen({
    super.key,
    required this.plan,
    required this.number,
    this.backend,
    this.talkBackend,
    this.replayStage,
  });

  final Plan plan;
  final int number;

  /// «Ещё раз» ряда (наряд FIX-3 §5): этот этап проходится снова на телефоне, ничего не отправляется. Null — сессия.
  final PlanStage? replayStage;

  bool get replay => replayStage != null;

  /// The session server; null — the real API. A test substitutes its own.
  final SessionBackend? backend;

  /// The talk's server (наряд CLIENT-CONV-1a); null — the real API.
  final ConversationBackend? talkBackend;

  @override
  ConsumerState<SessionScreen> createState() => _SessionScreenState();
}

class _SessionScreenState extends ConsumerState<SessionScreen> {
  late final SessionController _session;
  late final SessionVoice _voice;
  StreamSubscription<List<ConnectivityResult>>? _online;

  /// A card reported «no microphone» — instead of the stage header, only the cross and the scene strip (30-3).
  bool _noMic = false;
  int _preparedFor = -1;

  /// THE TALK (37-5…37-12) — built when the session first reaches the sixth stage.
  ConversationController? _talk;

  /// A start («Начать разговор», «Ещё раз») is on its way: it waits on a model and a voice.
  bool _starting = false;
  bool _talkStartFailed = false;

  /// The scene a card that walks several scenes has on screen (37-3), for the card [_shownFor] — the strip follows it.
  String? _shownScene;
  int _shownFor = -1;

  /// The talk's server — the talk itself and, for a session opened again after its goodbye, the owed summary (§4).
  ConversationBackend get _talkBackend => widget.talkBackend ?? ApiConversationBackend(ref.read(apiClientProvider));

  @override
  void initState() {
    super.initState();
    _session = SessionController(
      backend: widget.backend ?? ApiSessionBackend(ref.read(apiClientProvider)),
      plan: widget.plan,
      number: widget.number,
      store: ref.read(planStoreProvider),
      replayStage: widget.replayStage,
      readTalk: widget.replay ? null : (id) => _talkBackend.read(widget.plan.id, id),
    )..addListener(_onSession);
    _voice = SessionVoice(lines: ref.read(lineAudioCacheProvider), targetLang: widget.plan.targetLang);
    unawaited(_voice.warmUp().catchError((Object _) {}));
    // The owner's six sounds — decoded and registered while the session is open (SessionSounds).
    unawaited(SessionSounds.load());
    try {
      _online = Connectivity().onConnectivityChanged.listen((_) => _session.outbox.retryNow());
    } catch (_) {
      // No plugin (test) — the retry runs after the pause.
    }
    unawaited(_session.load());
  }

  @override
  void dispose() {
    unawaited(_online?.cancel());
    _session.removeListener(_onSession);
    _session.dispose();
    _talk?.dispose();
    unawaited(_voice.release());
    unawaited(SessionSounds.release());
    super.dispose();
  }

  /// The phase the last notification left — to hear the transitions the owner's sound map names.
  SessionPhase _lastPhase = SessionPhase.loading;
  bool _waitedForLesson = false;

  void _onSession() {
    if (!mounted) return;
    final phase = _session.phase;
    if (phase != _lastPhase) {
      if (phase == SessionPhase.building) _waitedForLesson = true;
      // The day is ready after waiting for its lesson to be built.
      if (phase == SessionPhase.entry && _waitedForLesson) {
        _waitedForLesson = false;
        SessionSounds.play(SessionSounds.ready);
      }
      // A stage summary (30-6, 33-8, 34-8, 35-6) opens.
      if (phase == SessionPhase.summary) SessionSounds.play(SessionSounds.stageDone);
      // The day summary (30-7) opens.
      if (phase == SessionPhase.daySummary) SessionSounds.dayCompleted();
      _lastPhase = phase;
    }
    final day = _session.day;
    // Every card's audio goes to disk at once, as soon as the day is read (and after a re-read).
    if (day != null && identityHashCode(day) != _preparedFor) {
      _preparedFor = identityHashCode(day);
      unawaited(_voice.prepare([
        for (final s in day.stages)
          for (final c in s.cards) ...c.payload.audios,
      ]));
    }
    setState(() {});
  }

  /// The card's microphone in the target language's locale.
  ///
  /// THE FIRST MICROPHONE OF A CARD ASKS FIRST (41-3, work order CLIENT-START §4): while iOS has not been asked, a card
  /// that shows a microphone raises the pre-permission sheet; the system's dialogs come only after «Разрешить
  /// микрофон». «Позже» — this card offers «Skip» (the «Microphone needed» screen), and the next microphone card asks
  /// again. A card that makes another microphone for itself (a new round, another chip) inherits its answer.
  SessionMic Function(String expected, List<String> contextual) _makeMic(String localeId) => (expected, contextual) {
    final strings = <String>{
      for (final s in contextual)
        if (s.trim().isNotEmpty) s.trim(),
    };
    final mic = SessionMic(
      recognizer: ref.read(speechRecognizerProvider),
      diagnostics: ref.read(speechDiagnosticsProvider),
      localeId: localeId,
      expected: expected,
      contextualStrings: strings.take(50).toList(),
      rules: _session.day?.speech ?? SpeechRules.none,
    );
    final serial = _session.cardSerial;
    if (_micDeferredFor == serial) {
      mic.markUnavailable(blockedInSettings: false);
    } else if (_micAskedFor != serial) {
      _micAskedFor = serial;
      WidgetsBinding.instance.addPostFrameCallback((_) async {
        final allowed = await _askMic(localeId);
        if (allowed || !mounted || _session.cardSerial != serial) return;
        _micDeferredFor = serial;
        mic.markUnavailable(blockedInSettings: false);
      });
    }
    return mic;
  };

  int? _micAskedFor;
  int? _micDeferredFor;
  bool _micSheetUp = false;

  /// True — the card may listen (allowed now, or nothing to ask). One sheet at a time: a second card asking while the
  /// first sheet is up waits for nothing and offers «Skip».
  Future<bool> _askMic(String localeId) async {
    if (_micSheetUp || !mounted) return false;
    _micSheetUp = true;
    try {
      return await askMicOnce(
        context,
        probe: () => ref.read(speechDiagnosticsProvider).refresh(localeId),
        allow: () => ref.read(speechRecognizerProvider).prepare(),
      );
    } finally {
      _micSheetUp = false;
    }
  }

  Future<void> _openSettings() async {
    try {
      await launchUrl(Uri.parse('app-settings:'));
    } catch (_) {
      // Settings did not open — «Skip» remains.
    }
  }

  Future<void> _exit() async {
    if (_session.phase != SessionPhase.card) {
      await _voice.stop();
      if (mounted) Navigator.of(context).pop();
      return;
    }
    final l = AppLocalizations.of(context);
    AppHaptics.light();
    final leave = await showSessionExitSheet(context, stageName: SessionTexts.stage(l, _session.stage));
    if (!leave || !mounted) return;
    await _voice.stop();
    if (mounted) Navigator.of(context).pop();
  }

  @override
  Widget build(BuildContext context) => AnnotatedRegion<SystemUiOverlayStyle>(
    value: SystemUiOverlayStyle.dark,
    // There is no «Report» in the session: not on the cards, not on the stage entry, not in the summary
    // (a fix from the 1b screenshots).
    child: QaReportHidden(
      child: PopScope(
        canPop: _session.phase != SessionPhase.card,
        onPopInvokedWithResult: (didPop, _) {
          if (!didPop) unawaited(_exit());
        },
        child: Scaffold(
          backgroundColor: AppColors.ground,
          body: SafeArea(bottom: false, child: _body(context)),
        ),
      ),
    ),
  );

  Widget _body(BuildContext context) {
    final l = AppLocalizations.of(context);
    switch (_session.phase) {
      case SessionPhase.loading:
        return const Center(child: CircularProgressIndicator(color: AppColors.ink));
      case SessionPhase.building || SessionPhase.lessonFailed || SessionPhase.lockedBySubscription:
        return _lessonPlate(context);
      case SessionPhase.failed:
        return Center(
          child: Padding(
            padding: const EdgeInsets.all(kSessionGutter),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(l.planSessionLoadFailed, textAlign: TextAlign.center, style: AppTextSession.body),
                const SizedBox(height: 18),
                DockButton(label: l.planTabRetry, onTap: () => unawaited(_session.load())),
                const SizedBox(height: 8),
                TextButton(onPressed: () => Navigator.of(context).pop(), child: Text(l.planWindowBack, style: AppTextSession.skip)),
              ],
            ),
          ),
        );
      case SessionPhase.entry:
        return _entry(context);
      case SessionPhase.card:
        return _cardPhase(context);
      case SessionPhase.summary:
        return _summary(context);
      case SessionPhase.talkEntry:
        return _talkEntry(context);
      case SessionPhase.talk:
        return _talkPhase(context);
      case SessionPhase.talkSummary:
        return _talkSummaryPhase(context);
      case SessionPhase.daySummary:
        return _daySummary(context);
    }
  }

  /// The day whose lesson is not there (GEN-3 §11): the plate the plan tab shows — «Building day N» while the server
  /// writes it (no button, no error: the session asks the plan until it is ready), «The day did not come together»
  /// with «Retry» when it failed.
  Widget _lessonPlate(BuildContext context) {
    final l = AppLocalizations.of(context);
    final plan = _session.currentPlan;
    final route = plan.days.where((d) => d.number == widget.number).firstOrNull;
    final title = context.nativeText((route == null ? null : (route.titleNative ?? plan.sceneOf(route)?.titleNative)) ?? plan.displayTitle);
    final failed = _session.phase == SessionPhase.lessonFailed;
    final bySubscription = _session.phase == SessionPhase.lockedBySubscription;
    if (bySubscription) {
      // 409 `plan_day_locked` · subscription: the plate of 21-3 «по подписке» — no toast (CLIENT-START §6).
      return Padding(
        padding: const EdgeInsets.fromLTRB(kSessionGutter, 4, kSessionGutter, 0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Align(alignment: Alignment.centerLeft, child: SessionCloseButton(onTap: () => Navigator.of(context).maybePop(), label: l.planSessionClose)),
            Expanded(
              child: Center(
                child: DayPlate(
                  key: const ValueKey('session-locked-subscription'),
                  label: l.planPlateLabel(widget.number),
                  title: title,
                  meta: l.planPlateBySubscription,
                  stages: const [],
                  footer: DayPlateFooter.locked(
                    note: l.planPlateOpensWithSubscription,
                    action: l.planPlateSubscription,
                    onTap: () => Navigator.of(context).push(
                      MaterialPageRoute(builder: (_) => const ProfileScreen(pushed: true, focusSubscription: true)),
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      );
    }
    return Padding(
      padding: const EdgeInsets.fromLTRB(kSessionGutter, 4, kSessionGutter, 0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Align(alignment: Alignment.centerLeft, child: SessionCloseButton(onTap: () => Navigator.of(context).maybePop(), label: l.planSessionClose)),
          Expanded(
            child: Center(
              child: DayPlate(
                key: ValueKey(failed ? 'session-lesson-failed' : 'session-lesson-building'),
                label: plan.catchUp ? l.planPlateLabelCatchUp(widget.number) : l.planPlateLabel(widget.number),
                title: title,
                stages: const [],
                notice: failed
                    ? DayPlateNotice(
                        title: l.planPlateFailedTitle,
                        sub: _session.retryOffline ? l.planPlateNoNetwork : null,
                        offline: _session.retryOffline,
                      )
                    : DayPlateNotice(
                        title: l.planPlateBuildingTitle(widget.number),
                        sub: l.planPlateBuildingSub,
                        preloaderLines: [l.planEntryPreviewLine1, l.planEntryPreviewLine2, l.planEntryPreviewLine3],
                      ),
                footer: failed
                    ? DayPlateFooter.button(
                        label: l.planPlateCtaRetry,
                        onTap: _session.retrying ? null : () => unawaited(_session.retryLesson()),
                      )
                    : const DayPlateFooter.none(),
              ),
            ),
          ),
        ],
      ),
    );
  }

  /// THE DAY'S ROWS ON 30-1 — exactly the stages the SERVER dealt, in its order (наряд CLIENT-CONV-1a): six on a day
  /// with a talk, five on one dealt without it — each in the SERVER's state, the day window's `stages[].state`
  /// (приёмка CLIENT-CONV-1c 22.09: «состояние ряда — из state сервера, не из счёта карточек»), so the entry and the
  /// window say the same word. The stage replayed from the day summary is the current one (the server has it done).
  /// Only a day whose window did not parse falls back to what its cards say.
  List<StageRow> _rows({required PlanStage current}) {
    final q = _session.queue;
    StageRowStatus fromCards(PlanStage s) => s == current
        ? StageRowStatus.current
        : (s == PlanStage.conversation ? _session.talkDone : q?.isDone(s) ?? false)
        ? StageRowStatus.done
        : StageRowStatus.ahead;
    return [
      for (final s in _session.dayStages)
        (
          stage: s,
          status: widget.replay && s == current
              ? StageRowStatus.current
              : switch (_session.serverStateOf(s)) {
                  WindowStageState.done => StageRowStatus.done,
                  WindowStageState.current => StageRowStatus.current,
                  WindowStageState.locked => StageRowStatus.ahead,
                  null => fromCards(s),
                },
          replay: widget.replay && s == current,
        ),
    ];
  }

  Widget _entry(BuildContext context) {
    final l = AppLocalizations.of(context);
    final stage = _session.stage;
    final q = _session.queue!;
    final version = ref.watch(appVersionProvider).value;
    return SessionStageEntry(
      stage: stage,
      stageName: (s) => SessionTexts.stage(l, s),
      description: SessionTexts.description(l, stage, q.unitsOf(stage).length),
      minutes: _session.day?.minutesLeft(stage),
      rows: _rows(current: stage),
      scene: _session.scene,
      noHints: _session.noHints,
      onNoHints: (v) => unawaited(_session.setNoHints(v)),
      onStart: q.nextIn(stage) != null ? _session.startStage : null,
      onBack: () => Navigator.of(context).maybePop(),
      rehearsal: _session.day?.day.type == PlanDayType.rehearsal,
      buildLabel: version == null ? null : l.planSessionBuild(version),
    );
  }

  Widget _cardPhase(BuildContext context) {
    final l = AppLocalizations.of(context);
    final stage = _session.stage;
    final q = _session.queue!;
    final card = _session.card!;
    final plan = widget.plan;
    final stageCards = q.cardsOf(stage);
    // THE CARD'S OWN SCENE (наряд CLIENT-CONV-1b): a review and the rehearsal walk several scenes, and the strip names
    // the one the card is about; a card that turns scenes itself (37-3) names the one on screen.
    final shown = _shownFor == _session.cardSerial ? _shownScene : null;
    final scene = (shown == null ? null : _session.currentPlan.sceneById(shown)) ?? _session.sceneOfCard(card);
    // Every kind is recognized in the target language: the native retelling died with BACK-TAILS-1 §1.1.
    final localeId = sttLocaleFor(plan.targetLang);
    final env = CardEnv(
      card: card,
      voice: _voice,
      targetLang: plan.targetLang,
      localeId: localeId,
      // The partner's role, in the learner's language — set once here for every card that names it (CLIENT-22-1 §2).
      role: context.nativeText(scene?.partnerRoleNative?.trim() ?? ''),
      submit: (answer) => _session.submit(card, answer),
      next: () async {
        await _voice.stop();
        await _session.next();
        if (mounted && _noMic) setState(() => _noMic = false);
      },
      judge: (heard, {bool hinted = false}) => _session.judge(card, heard, hinted: hinted),
      makeMic: _makeMic(localeId),
      reportNoMic: (value) {
        if (mounted && value != _noMic) setState(() => _noMic = value);
      },
      openSettings: _openSettings,
      outcome: _session.outcomeOf(card.id),
      advancing: _session.advancing,
      replay: widget.replay,
      frameSentence: (ref) => _session.day?.frameSentence(ref),
      termText: (ref) => _session.day?.termText(ref),
      exchangeOf: (line) => _session.day?.exchangeOf(line),
      level: plan.level,
      noHints: _session.noHints,
      feed: stage == PlanStage.dialogue ? DialogueFeed.before(stageCards, card) : const [],
      stageCards: stageCards,
      scene: scene,
      stageDone: q.isDone,
      speech: _session.day?.speech ?? SpeechRules.none,
      showScene: (id) {
        if (!mounted || (_shownFor == _session.cardSerial && _shownScene == id)) return;
        setState(() {
          _shownFor = _session.cardSerial;
          _shownScene = id;
        });
      },
    );
    final listen = stage == PlanStage.listen;
    // «Вспомнить» (37-3): the overview is the stage's sheet, not one of its lines — the header carries the stage's
    // minutes where the lines' count stands on 35-4, and no beads.
    final overview = card.kind == SessionKind.recallScenes;
    final overviewMinutes = overview ? _session.day?.minutesLeft(stage) : null;
    final reduce = MediaQuery.maybeDisableAnimationsOf(context) ?? false;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (_noMic)
          Padding(
            padding: const EdgeInsets.fromLTRB(kSessionGutter, 4, kSessionGutter, 0),
            child: Align(alignment: Alignment.centerLeft, child: SessionCloseButton(onTap: () => unawaited(_exit()), label: l.planSessionClose)),
          )
        else
          SessionHeader(
            stageName: SessionTexts.stage(l, stage),
            progress: q.progress(stage),
            left: overview
                ? (overviewMinutes == null ? '' : l.planSessionApproxMinutes(overviewMinutes))
                : listen
                ? SessionTexts.listenLeft(l, card.kind, stageCards)
                : SessionTexts.left(
                    l,
                    stage,
                    q.unitsLeft(
                      stage,
                      openUnit: stage == PlanStage.dialogue ||
                              stage == PlanStage.speak ||
                              stage == PlanStage.repetition ||
                              stage == PlanStage.recall
                          ? q.unitKey(card)
                          : null,
                    ),
                  ),
            beads: overview
                ? const []
                : listen
                ? q.cardBeads(stage, kinds: SessionSummaries.listenQuestions, currentCardId: card.id)
                : q.beads(stage, currentUnit: q.unitKey(card)),
            onClose: () => unawaited(_exit()),
          ),
        SessionSceneStrip(scene: scene),
        if (_session.offline) const SessionOfflineBanner(),
        Expanded(
          child: AnimatedSwitcher(
            duration: reduce ? Duration.zero : AppMotion.sessionCardChange,
            switchInCurve: AppMotion.sessionEaseOut,
            switchOutCurve: AppMotion.sessionEaseOut,
            transitionBuilder: (child, animation) => FadeTransition(
              opacity: animation,
              child: reduce
                  ? child
                  : AnimatedBuilder(
                      animation: animation,
                      builder: (_, c) => Transform.translate(offset: Offset(AppMotion.sessionCardShift * (1 - animation.value), 0), child: c),
                      child: child,
                    ),
            ),
            layoutBuilder: (current, previous) => Stack(fit: StackFit.expand, children: [...previous, ?current]),
            child: KeyedSubtree(key: ValueKey('card-${_session.cardSerial}'), child: sessionCardFor(env)),
          ),
        ),
      ],
    );
  }

  /// THE TALK (наряд CLIENT-CONV-1a, кадры 37-5…37-12) — its own three phases inside the session:
  /// the entry in place of 30-1, the ribbon, the summary. The controller is built once, when the
  /// session first reaches the sixth stage, and lives until the session is left.
  ConversationController _talkController() {
    final plan = widget.plan;
    return _talk ??= ConversationController(
      backend: _talkBackend,
      planId: plan.id,
      day: widget.number,
      voice: _voice,
      hints: !_session.noHints,
    )..addListener(_onTalk);
  }

  /// The talk whose goodbye the session has already written down as «summary owed» (§4).
  String? _finishedTalk;

  /// THE ROLE HAS SAID GOODBYE — the session owes this talk's summary until «Дальше» on it.
  void _onTalk() {
    final talk = _talk;
    final document = talk?.talk;
    if (talk == null || document == null || talk.phase != TalkPhase.ended || _finishedTalk == document.id) return;
    _finishedTalk = document.id;
    unawaited(_session.talkFinished(document.id));
  }

  /// The talk's microphone: free speech in the target language, and a pause of its own
  /// ([ConversationController.silenceClosesTurn]).
  SessionMic _talkMic() => SessionMic(
    recognizer: ref.read(speechRecognizerProvider),
    diagnostics: ref.read(speechDiagnosticsProvider),
    localeId: sttLocaleFor(widget.plan.targetLang),
    expected: '',
    contextualStrings: _talkPhrases.values.take(50).toList(),
    config: const SpeechTurnConfig(silenceAfterSpeech: ConversationController.silenceClosesTurn),
  );

  /// The day's phrases — what the recogniser is told to expect of this talk; the talk's own constructions come with
  /// the document, and the microphone is built before it.
  Map<String, String> get _talkPhrases => {
    for (final p in _session.day?.window?.program.phrases ?? const <WindowPhrase>[]) p.ref: p.text,
  };

  /// 37-5 — and 37-5b for a talk that walks several scenes: its constructions by scene in the day's order of scenes
  /// (`window.sources[]`), the strip on the first of them.
  Widget _talkEntry(BuildContext context) {
    final plan = _session.currentPlan;
    final scenes = talkEntryScenes(
      _session.talkTargets,
      order: [
        for (final s in _session.day?.window?.sources ?? const <WindowSourceRef>[])
          (sceneId: s.sceneId, title: s.titleNative, female: s.partnerFemale),
      ],
      sceneById: plan.sceneById,
    );
    return TalkEntryView(
      scene: scenes.isEmpty ? _session.scene : (plan.sceneById(scenes.first.sceneId) ?? _session.scene),
      minutes: _session.talkMinutes,
      title: _session.talkTitle,
      scenesCount: _session.talkScenesCount,
      targets: _session.talkTargets,
      scenes: scenes,
      rehearsal: _session.day?.day.type == PlanDayType.rehearsal,
      noHints: _session.noHints,
      onNoHints: (v) => unawaited(_session.setNoHints(v)),
      starting: _starting,
      failure: _talkStartFailed ? AppLocalizations.of(context).planTalkOpenFailed : null,
      onBack: () => Navigator.of(context).maybePop(),
      onStart: _starting ? null : () => unawaited(_startTalk()),
    );
  }

  Future<void> _startTalk() async {
    final talk = _talkController();
    setState(() {
      _starting = true;
      _talkStartFailed = false;
    });
    // The ribbon opens on the server's answer, and the role's first line is said in it (37-6), not over the entry.
    await talk.openAnswered();
    if (!mounted) return;
    setState(() => _starting = false);
    if (talk.phase == TalkPhase.openFailed) {
      setState(() => _talkStartFailed = true);
      return;
    }
    _session.talkStarted();
  }

  /// 37-6…37-11.
  Widget _talkPhase(BuildContext context) {
    final talk = _talkController();
    return TalkView(
      controller: talk,
      scene: _session.scene,
      sceneById: _session.currentPlan.sceneById,
      voice: _voice,
      makeMic: _talkMic,
      speech: _session.day?.speech ?? SpeechRules.none,
      openSettings: _openSettings,
      onSummary: _session.talkEnded,
      onClose: () => Navigator.of(context).maybePop(),
    );
  }

  /// 37-12 — the talk this screen had, or the one a session opened again still owes the summary of (§4).
  Widget _talkSummaryPhase(BuildContext context) {
    final document = _session.owedTalk ?? _talkController().talk;
    if (document?.summary == null) return _talkPhase(context);
    return TalkSummaryView(
      talk: document!,
      scene: _session.scene,
      sceneById: _session.currentPlan.sceneById,
      onClose: () => Navigator.of(context).maybePop(),
      onNext: () => unawaited(_session.afterTalk()),
    );
  }

  /// THE STAGE SUMMARY (30-6) — one component for every stage with cards (решение архитектора 22.09); the talk's own is
  /// 37-12. What comes next is the next stage and the server's «≈ N мин» for it, or — after the day's last stage with
  /// cards — the day's total.
  Widget _summary(BuildContext context) {
    final l = AppLocalizations.of(context);
    final stage = _session.stage;
    final q = _session.queue!;
    final next = _session.nextStage;
    final nextMinutes = next == null ? null : _session.day?.minutesLeft(next);
    return SessionStageSummary(
      title: SessionTexts.passed(l, stage, _session.minutesOf(stage)),
      rows: _rows(current: next ?? stage),
      lines: SessionTexts.stageLines(
        l,
        stage,
        SessionSummaries.stageTally(q, stage, server: _session.summaryOf(stage)),
        role: context.nativeTextOrNull(_session.scene?.partnerRoleNative),
      ),
      next: next == null
          ? (stage: null, name: l.planSessionDayTotal, value: l.planMinutesCount(_session.dayMinutes))
          : (stage: next, name: SessionTexts.stage(l, next), value: nextMinutes == null ? null : l.planSessionApproxMinutes(nextMinutes)),
      scene: _session.scene,
      onClose: () => Navigator.of(context).maybePop(),
      onNext: _session.continueAfterSummary,
    );
  }

  /// 30-7.
  Widget _daySummary(BuildContext context) {
    final l = AppLocalizations.of(context);
    final q = _session.queue!;
    final nextDay = _session.nextDay;
    return SessionDaySummary(
      title: l.planSessionDayDoneTitle(l.planMinutesCount(_session.dayMinutes)),
      // EXACTLY THE STAGES THE SERVER DEALT — six on a day with a talk, five on one without it.
      stages: _session.dayStages,
      stageName: (s) => SessionTexts.stage(l, s),
      highlights: _session.highlights,
      returnsLine: SessionTexts.dayReturns(l, SessionSummaries.dayReturns(q)),
      nextDay: switch (SessionSummaries.nextDayLesson(nextDay)) {
        null => null,
        NextDayLesson.ready => l.planSessionNextDayReady(nextDay!.number),
        NextDayLesson.building => l.planSessionNextDayBuilding(nextDay!.number),
      },
      scene: _session.scene,
      closing: _session.closing,
      closeFailed: _session.closeFailed,
      onClose: () => Navigator.of(context).maybePop(),
      onCloseDay: () async {
        final closed = await _session.closeDay();
        if (!closed || !context.mounted) return;
        await _voice.stop();
        // 43-1: after day 1's summary (and day 2's, after «Не сейчас»), before the plan — while iOS has not been asked.
        if (!widget.replay && context.mounted) await offerReminders(context, ref, closedDay: widget.number);
        if (context.mounted) Navigator.of(context).pop();
      },
    );
  }
}
