import 'dart:async';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/day_plate.dart';

import '../../../data/app_version.dart';
import '../../../data/languages.dart' show sttLocaleFor;
import '../../../data/plan/day_window.dart' show WindowPhrase;
import '../../../data/plan/plan_models.dart';
import '../../../data/plan/session/dialogue_feed.dart';
import '../../../data/plan/session/session_models.dart';
import '../../../data/plan/session/session_summary.dart';
import '../../../data/plan/session/speech_match.dart';
import '../../../data/providers.dart';
import '../../../data/speech/speech_turn.dart' show SpeechTurnConfig;
import '../../profile/qa_report_button.dart' show QaReportHidden;
import '../conversation/conversation_controller.dart';
import '../conversation/talk_entry.dart';
import '../conversation/talk_screen.dart';
import '../conversation/talk_summary.dart';
import '../plan_providers.dart';
import 'cards/card_host.dart';
import 'cards/card_kit.dart';
import 'parts/session_bits.dart';
import 'parts/session_bubbles.dart';
import 'parts/session_chrome.dart';
import 'parts/session_stage.dart';
import 'session_controller.dart';
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
    this.replay = false,
  });

  final Plan plan;
  final int number;
  final bool replay;

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

  @override
  void initState() {
    super.initState();
    _session = SessionController(
      backend: widget.backend ?? ApiSessionBackend(ref.read(apiClientProvider)),
      plan: widget.plan,
      number: widget.number,
      store: ref.read(planStoreProvider),
      replay: widget.replay,
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
  SessionMic Function(String expected, List<String> contextual) _makeMic(String localeId) => (expected, contextual) {
    final strings = <String>{
      for (final s in contextual)
        if (s.trim().isNotEmpty) s.trim(),
    };
    return SessionMic(
      recognizer: ref.read(speechRecognizerProvider),
      diagnostics: ref.read(speechDiagnosticsProvider),
      localeId: localeId,
      expected: expected,
      contextualStrings: strings.take(50).toList(),
      rules: _session.day?.speech ?? SpeechRules.none,
    );
  };

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
      case SessionPhase.building || SessionPhase.lessonFailed:
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
                SessionDockButton(label: l.planTabRetry, onTap: () => unawaited(_session.load())),
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
    final title = (route == null ? null : (route.titleNative ?? plan.sceneOf(route)?.titleNative)) ?? plan.displayTitle;
    final failed = _session.phase == SessionPhase.lessonFailed;
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
                    ? DayPlateNotice(title: l.planPlateFailedTitle, sub: l.planPlateFailedSub(widget.number))
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

  /// THE DAY'S ROWS ON 30-1 — exactly the stages the SERVER dealt, in its order (наряд
  /// CLIENT-CONV-1a): six on a day with a talk, five on one dealt without it. The talk is «пройден»
  /// when its own row says so — it has no cards to count.
  List<StageRow> _rows({required PlanStage current}) {
    final q = _session.queue;
    return [
      for (final s in _session.dayStages)
        (
          stage: s,
          status: s == current
              ? StageRowStatus.current
              : (s == PlanStage.conversation ? _session.talkDone : q?.isDone(s) ?? false)
              ? StageRowStatus.done
              : StageRowStatus.ahead,
          started: q != null && q.cardsOf(s).any((c) => c.isAnswered),
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
      role: scene?.partnerRoleNative?.trim() ?? '',
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
                      openUnit: stage == PlanStage.dialogue || stage == PlanStage.speak || stage == PlanStage.recall ? q.unitKey(card) : null,
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
      backend: widget.talkBackend ?? ApiConversationBackend(ref.read(apiClientProvider)),
      planId: plan.id,
      day: widget.number,
      voice: _voice,
      hints: !_session.noHints,
    );
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

  /// The day's phrases by `ref` — the text behind the server's `phrases_used`.
  Map<String, String> get _talkPhrases => {
    for (final p in _session.day?.window?.program.phrases ?? const <WindowPhrase>[]) p.ref: p.text,
  };

  /// 37-5.
  Widget _talkEntry(BuildContext context) => TalkEntryView(
    scene: _session.scene,
    minutes: _session.talkMinutes,
    rehearsal: _session.day?.day.type == PlanDayType.rehearsal,
    noHints: _session.noHints,
    onNoHints: (v) => unawaited(_session.setNoHints(v)),
    starting: _starting,
    failure: _talkStartFailed ? AppLocalizations.of(context).planTalkOpenFailed : null,
    onBack: () => Navigator.of(context).maybePop(),
    onStart: _starting ? null : () => unawaited(_startTalk()),
  );

  Future<void> _startTalk() async {
    final talk = _talkController();
    setState(() {
      _starting = true;
      _talkStartFailed = false;
    });
    await talk.open();
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
      phraseTexts: _talkPhrases,
      openSettings: _openSettings,
      onSummary: _session.talkEnded,
      onClose: () => Navigator.of(context).maybePop(),
    );
  }

  /// 37-12.
  Widget _talkSummaryPhase(BuildContext context) {
    final talk = _talkController();
    final document = talk.talk;
    if (document?.summary == null) return _talkPhase(context);
    return TalkSummaryView(
      talk: document!,
      scene: _session.scene,
      sceneById: _session.currentPlan.sceneById,
      voice: _voice,
      busy: _starting,
      onClose: () => Navigator.of(context).maybePop(),
      onAgain: () => unawaited(_againTalk()),
      onNext: () => unawaited(_session.afterTalk()),
    );
  }

  /// «Ещё раз» (37-12): a NEW talk — the server closes the old one as `replayed`.
  Future<void> _againTalk() async {
    final talk = _talkController();
    setState(() => _starting = true);
    _session.talkAgain();
    await talk.open(again: true);
    if (!mounted) return;
    setState(() => _starting = false);
  }

  Widget _summary(BuildContext context) {
    final l = AppLocalizations.of(context);
    final stage = _session.stage;
    final q = _session.queue!;
    final next = _session.nextStage;
    switch (stage) {
      case PlanStage.dialogue || PlanStage.listen || PlanStage.speak:
        return _stageTalkSummary(context, stage, next);
      // «Вспомнить» closes on 30-6 (кадр 37-4): «Вспомнил · около 4 минут» and «Дальше» — no line about what is closed,
      // a reminder has nothing to close.
      case PlanStage.words || PlanStage.phrases || PlanStage.recall || PlanStage.conversation || PlanStage.unknown:
        final units = q.unitsOf(stage);
        // The rehearsal is the plan's last day: nothing of it comes back tomorrow.
        final returning = stage == PlanStage.recall ? const <ReturningUnit>[] : [for (final key in q.returningUnits(stage)) _returning(stage, key)];
        return SessionStageSummary(
          title: SessionTexts.done(l, stage, _session.minutesOf(stage) ?? 0),
          rows: _rows(current: next ?? stage),
          returning: returning,
          closedLine: stage == PlanStage.recall
              ? null
              : SessionTexts.closed(l, stage, closed: units.length - returning.length, someReturn: returning.isNotEmpty),
          nextStage: next,
          nextName: next == null ? null : SessionTexts.stage(l, next),
          nextMinutes: next == null ? null : _session.day?.minutesLeft(next),
          scene: _session.scene,
          onClose: () => Navigator.of(context).maybePop(),
          onNext: _session.continueAfterSummary,
        );
    }
  }

  /// 33-8 · 34-8 · 35-6 — the summary of a CARD stage that is a conversation; the talk's own is 37-12.
  Widget _stageTalkSummary(BuildContext context, PlanStage stage, PlanStage? next) {
    final l = AppLocalizations.of(context);
    final q = _session.queue!;
    final cards = q.cardsOf(stage);
    final understood = SessionSummaries.understood(cards);
    final spoke = SessionSummaries.spoke(cards);
    final title = switch (stage) {
      PlanStage.listen => l.planSessionUnderstoodCount(understood.right, understood.total),
      PlanStage.speak => l.planSessionSpokeCount(spoke.said, spoke.total),
      _ => SessionTexts.done(l, stage, _session.minutesOf(stage) ?? 0),
    };
    final nextMinutes = next == null ? null : _session.day?.minutesLeft(next);
    // «Listen and answer» never returns anything: its unit is the whole visit.
    final returningKeys = stage == PlanStage.listen ? const <String>[] : q.returningUnits(stage);
    final allCards = [for (final s in PlanStage.known) ...q.cardsOf(s)];
    final returning = [
      for (final key in returningKeys)
        if (q.unitCard(stage, key) case final unit?)
          // The exchange is looked up in its own scene: a review's x3 of one day is not x3 of another.
          _pair(DialogueFeed.pairOf(allCards.where((c) => c.payload.sceneId == unit.payload.sceneId), unit.unit.ref)),
    ];
    final units = q.unitsOf(stage);
    return SessionTalkSummary(
      title: title,
      returning: returning,
      closedLine: stage == PlanStage.listen || units.isEmpty
          ? null
          : SessionTexts.closed(l, stage, closed: units.length - returning.length, someReturn: returning.isNotEmpty),
      nextLabel: next == null ? l.planSessionDayTotal : SessionTexts.stage(l, next),
      nextValue: next == null
          ? l.planMinutesCount(_session.dayMinutes)
          : nextMinutes == null
          ? null
          : l.planSessionApproxMinutes(nextMinutes),
      buttonLabel: next == null ? l.planSessionDayDoneAction : l.planSessionNext,
      scene: _session.scene,
      onClose: () => Navigator.of(context).maybePop(),
      onNext: _session.continueAfterSummary,
    );
  }

  /// A returning exchange as its two bubbles — the partner's line and the learner's, with the brass mark.
  Widget _pair(ExchangePair pair) {
    final partner = pair.partner;
    final own = pair.own;
    final rows = <Widget>[
      if (partner != null)
        SessionPartnerRow(
          bubble: SessionBubble(own: false, text: partner.textTarget, translation: partner.textNative),
          listen: SessionListenButton(
            size: 28,
            brass: true,
            label: AppLocalizations.of(context).planWindowListen,
            onTap: () => unawaited(_voice.play(partner.audio, fallback: partner.textTarget, key: 'summary-${partner.ref}')),
          ),
        ),
      if (own != null)
        SessionOwnRow(
          mark: FeedMark.returns,
          bubble: SessionBubble(own: true, text: own.textTarget, translation: own.textNative),
        ),
    ];
    final ordered = pair.learnerFirst ? rows.reversed.toList() : rows;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [for (final (i, row) in ordered.indexed) ...[if (i > 0) const SizedBox(height: 8), row]],
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
        if (context.mounted) Navigator.of(context).pop();
      },
    );
  }

  /// A unit's captions for «Coming back tomorrow» — from the stage's cards: a word — its term and photo,
  /// a phrase — as it was said. [key] — the unit's key in the stage ([SessionQueue.unitKey]).
  ReturningUnit _returning(PlanStage stage, String key) {
    final q = _session.queue!;
    final ref = q.unitCard(stage, key)?.unit.ref ?? key;
    final term = q.payloadOfUnit<WordIntroPayload>(stage, key)?.term ??
        q.payloadOfUnit<WordRepeatPayload>(stage, key)?.term ??
        q.payloadOfUnit<WordAssemblePayload>(stage, key)?.term;
    if (term != null) return (target: term.textTarget, native: term.textNative, image: term.image);
    final said = q.payloadOfUnit<PhraseIntroPayload>(stage, key)?.said;
    if (said != null) return (target: said.textTarget, native: said.textNative, image: null);
    final repeat = q.payloadOfUnit<PhraseRepeatPayload>(stage, key);
    if (repeat != null) {
      final f = repeat.frame.filler(repeat.fillerIndex);
      return (target: repeat.expectedText, native: f?.nativeLine ?? repeat.frame.frameNative, image: null);
    }
    final choose = q.payloadOfUnit<WordChoosePayload>(stage, key);
    if (choose != null) {
      final target = choose.termToNative ? choose.promptTextTarget : choose.correctOption?.text;
      final native = choose.termToNative ? choose.correctOption?.text : choose.promptTextNative;
      return (target: target ?? ref, native: native ?? '', image: choose.promptImage);
    }
    return (target: ref, native: '', image: null);
  }
}
