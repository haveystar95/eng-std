import 'dart:async';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../data/api_client.dart';
import '../../../data/app_version.dart';
import '../../../data/languages.dart' show sttLocaleFor;
import '../../../data/plan/plan_models.dart';
import '../../../data/plan/session/session_models.dart';
import '../../../data/providers.dart';
import '../../profile/qa_report_button.dart' show QaReportHidden;
import '../plan_providers.dart';
import 'cards/card_host.dart';
import 'cards/card_kit.dart';
import 'parts/session_bits.dart';
import 'parts/session_chrome.dart';
import 'parts/session_stage.dart';
import 'session_controller.dart';
import 'session_mic.dart';
import 'session_texts.dart';
import 'session_voice.dart';

/// DAY SESSION (work order SESSION-1b; canvas `session-canvas.dc.html`, series 30–32) — one screen: stage entry
/// (30-1), cards with the header (30-2) and the scene strip (30-2b), stage summary (30-6), exit (30-8).
///
/// Words and phrases have screens; Dialogue, Listen and answer and Speak myself stand «ahead», entry into them is
/// blocked by the caption «in the next build», there are no answers for them, the day does not close. The server
/// is the source of truth: every entry reads the day anew and continues from the first unanswered card.
class SessionScreen extends ConsumerStatefulWidget {
  const SessionScreen({super.key, required this.plan, required this.number, this.backend});

  final Plan plan;
  final int number;

  /// The session server; null — the real API. A test substitutes its own.
  final SessionBackend? backend;

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

  @override
  void initState() {
    super.initState();
    _session = SessionController(
      backend: widget.backend ?? ApiSessionBackend(ref.read(apiClientProvider)),
      plan: widget.plan,
      number: widget.number,
      store: ref.read(planStoreProvider),
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
      if (phase == SessionPhase.failed && problemCodeOf(_session.error) == 'plan_lesson_not_ready') _waitedForLesson = true;
      // The day is ready after waiting for its lesson to be built.
      if (phase == SessionPhase.entry && _waitedForLesson) {
        _waitedForLesson = false;
        SessionSounds.play(SessionSounds.ready);
      }
      // The stage summary (30-6) opens.
      if (phase == SessionPhase.summary) SessionSounds.play(SessionSounds.stageDone);
      _lastPhase = phase;
    }
    final day = _session.day;
    // Audio of the stages that have screens goes to disk at once, as soon as the day is read (and after a re-read).
    if (day != null && identityHashCode(day) != _preparedFor) {
      _preparedFor = identityHashCode(day);
      unawaited(_voice.prepare([
        for (final s in day.stages)
          if (SessionController.hasScreens(s.stage))
            for (final c in s.cards) ...c.payload.audios,
      ]));
    }
    setState(() {});
  }

  SessionMic _makeMic(String expected, List<String> contextual) {
    final strings = <String>{
      for (final s in contextual)
        if (s.trim().isNotEmpty) s.trim(),
    };
    return SessionMic(
      recognizer: ref.read(speechRecognizerProvider),
      diagnostics: ref.read(speechDiagnosticsProvider),
      localeId: sttLocaleFor(widget.plan.targetLang),
      expected: expected,
      contextualStrings: strings.take(50).toList(),
    );
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
      case SessionPhase.failed:
        final building = problemCodeOf(_session.error) == 'plan_lesson_not_ready';
        return Center(
          child: Padding(
            padding: const EdgeInsets.all(kSessionGutter),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  building ? l.planSessionLessonBuilding : l.planSessionLoadFailed,
                  textAlign: TextAlign.center,
                  style: AppTextSession.body,
                ),
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
    }
  }

  List<StageRow> _rows({required PlanStage current}) {
    final q = _session.queue;
    return [
      for (final s in PlanStage.known)
        (
          stage: s,
          status: s == current
              ? StageRowStatus.current
              : (q?.isDone(s) ?? false)
              ? StageRowStatus.done
              : StageRowStatus.ahead,
          started: q != null && q.cardsOf(s).any((c) => c.isAnswered),
        ),
    ];
  }

  Widget _entry(BuildContext context) {
    final l = AppLocalizations.of(context);
    final stage = _session.stage;
    final q = _session.queue!;
    final version = ref.watch(appVersionProvider).value;
    final open = SessionController.hasScreens(stage) && q.nextIn(stage) != null;
    return SessionStageEntry(
      stage: stage,
      stageName: (s) => SessionTexts.stage(l, s),
      description: SessionTexts.description(l, stage, q.unitsOf(stage).length),
      minutes: _session.day?.minutesLeft(stage),
      rows: _rows(current: stage),
      scene: _session.scene,
      noHints: _session.noHints,
      onNoHints: (v) => unawaited(_session.setNoHints(v)),
      onStart: open ? _session.startStage : null,
      onBack: () => Navigator.of(context).maybePop(),
      buildLabel: version == null ? null : l.planSessionBuild(version),
    );
  }

  Widget _cardPhase(BuildContext context) {
    final l = AppLocalizations.of(context);
    final stage = _session.stage;
    final q = _session.queue!;
    final card = _session.card!;
    final env = CardEnv(
      card: card,
      voice: _voice,
      targetLang: widget.plan.targetLang,
      localeId: sttLocaleFor(widget.plan.targetLang),
      role: _session.scene?.partnerRoleNative?.trim() ?? '',
      submit: (answer) => _session.submit(card, answer),
      next: () async {
        await _voice.stop();
        await _session.next();
        if (mounted && _noMic) setState(() => _noMic = false);
      },
      judge: (heard) => _session.judge(card, heard),
      makeMic: _makeMic,
      reportNoMic: (value) {
        if (mounted && value != _noMic) setState(() => _noMic = value);
      },
      openSettings: _openSettings,
      outcome: _session.outcomeOf(card.id),
      advancing: _session.advancing,
      frameSentence: (ref) => _session.day?.frameSentence(ref),
      termText: (ref) => _session.day?.termText(ref),
    );
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
            left: SessionTexts.left(l, stage, q.unitsLeft(stage)),
            beads: q.beads(stage, currentUnit: card.unit.ref),
            onClose: () => unawaited(_exit()),
          ),
        SessionSceneStrip(scene: _session.scene),
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

  Widget _summary(BuildContext context) {
    final l = AppLocalizations.of(context);
    final stage = _session.stage;
    final q = _session.queue!;
    final units = q.unitsOf(stage);
    final returning = [for (final ref in q.returningUnits(stage)) _returning(stage, ref)];
    final next = _session.nextStage;
    return SessionStageSummary(
      title: SessionTexts.done(l, stage, _session.minutesOf(stage) ?? 0),
      rows: _rows(current: next ?? stage),
      returning: returning,
      closedLine: SessionTexts.closed(l, stage, closed: units.length - returning.length, someReturn: returning.isNotEmpty),
      nextStage: next,
      nextName: next == null ? null : SessionTexts.stage(l, next),
      nextMinutes: next == null ? null : _session.day?.minutesLeft(next),
      scene: _session.scene,
      onClose: () => Navigator.of(context).maybePop(),
      onNext: next == null ? null : _session.continueAfterSummary,
    );
  }

  /// A unit's captions for «Coming back tomorrow» — from the stage's cards: a word — its term and photo,
  /// a phrase — as it was said.
  ReturningUnit _returning(PlanStage stage, String ref) {
    final q = _session.queue!;
    final term = q.payloadOfUnit<WordIntroPayload>(stage, ref)?.term ??
        q.payloadOfUnit<WordRepeatPayload>(stage, ref)?.term ??
        q.payloadOfUnit<WordAssemblePayload>(stage, ref)?.term;
    if (term != null) return (target: term.textTarget, native: term.textNative, image: term.image);
    final said = q.payloadOfUnit<PhraseIntroPayload>(stage, ref)?.said;
    if (said != null) return (target: said.textTarget, native: said.textNative, image: null);
    final repeat = q.payloadOfUnit<PhraseRepeatPayload>(stage, ref);
    if (repeat != null) {
      final f = repeat.frame.filler(repeat.fillerIndex);
      return (target: repeat.expectedText, native: f?.nativeLine ?? repeat.frame.frameNative, image: null);
    }
    final choose = q.payloadOfUnit<WordChoosePayload>(stage, ref);
    if (choose != null) {
      final target = choose.termToNative ? choose.promptTextTarget : choose.correctOption?.text;
      final native = choose.termToNative ? choose.correctOption?.text : choose.promptTextNative;
      return (target: target ?? ref, native: native ?? '', image: choose.promptImage);
    }
    return (target: ref, native: '', image: null);
  }
}
