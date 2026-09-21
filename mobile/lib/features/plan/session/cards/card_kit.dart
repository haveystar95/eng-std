import 'dart:async';

import 'package:flutter/material.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../../data/plan/day_window.dart' show WindowPair;
import '../../../../data/plan/plan_models.dart';
import '../../../../data/plan/session/dialogue_feed.dart';
import '../../../../data/plan/session/session_models.dart';
import '../../../../data/plan/session/session_outcomes.dart';
import '../../../../data/plan/session/session_rules.dart';
import '../../../../data/plan/session/speech_match.dart';
import '../../../../data/speech/speech_turn.dart';
import '../parts/session_bits.dart';
import '../parts/session_choice.dart';
import '../parts/session_mic_panel.dart';
import '../session_mic.dart';
import '../session_voice.dart';

/// WHAT A CARD KNOWS BEYOND ITS PAYLOAD — one object for all 28 kinds (work orders SESSION-1b, SESSION-1c).
///
/// The card judges the answer itself (choice, tiles, voice — [SessionRules]) and hands the result to [submit]; then
/// [next] (waits until the answer is sent to the server). The judge-graded kind asks [judge]. Nothing else of the
/// session is visible to the card.
class CardEnv {
  const CardEnv({
    required this.card,
    required this.voice,
    required this.targetLang,
    required this.localeId,
    required this.role,
    required this.submit,
    required this.next,
    required this.judge,
    required this.makeMic,
    required this.reportNoMic,
    required this.openSettings,
    this.outcome,
    this.advancing = false,
    this.replay = false,
    this.frameSentence,
    this.termText,
    this.exchangeOf,
    this.level = PlanLevel.intermediate,
    this.noHints = false,
    this.feed = const [],
    this.stageCards = const [],
    this.scene,
    this.stageDone,
    this.speech = SpeechRules.none,
    this.showScene,
  });

  final SessionCard card;
  final SessionVoice voice;
  final String targetLang;

  /// Recognition locale of THIS card — the target language's (`en_US`).
  final String localeId;

  /// The partner's role in the nominative case, as the server sent it (native «Receptionist»); empty — no role.
  final String role;
  final void Function(SessionAnswer answer) submit;
  final Future<void> Function() next;

  /// The slot judge; [hinted] — the frame was on screen before this attempt.
  final Future<SessionJudgeOutcome> Function(String heard, {bool hinted}) judge;

  /// The card's microphone: what should be said and hint words for the recognizer.
  final SessionMic Function(String expected, List<String> contextual) makeMic;

  /// No microphone — the screen shows «Microphone needed» instead of the stage header.
  final ValueChanged<bool> reportNoMic;

  /// «Allow» when permission is denied for good — the phone's settings.
  final Future<void> Function() openSettings;

  /// The server's answer for this card, once it has arrived (`unit.returns_tomorrow`).
  final SessionAnswerOutcome? outcome;

  /// «Next» waits until the answer is sent.
  final bool advancing;

  /// «Once more» from the day summary (SESSION-2a §4): the stage is walked again on the phone and nothing is sent.
  /// The cards grade as they always do — the phone's coverage takes the judge's place (FIX-1 §5), so a replay is a
  /// pass like any other and only its result goes nowhere.
  final bool replay;

  /// The day's frame as a whole phrase by its `ref` ([SessionDay.frameSentence]) — the «Combination» options.
  final String? Function(String frameRef)? frameSentence;

  /// The day's word by its unit `ref` ([SessionDay.termText]) — what the phone reads on «By ear» without a file.
  final String? Function(String unitRef)? termText;

  /// THE EXCHANGE THE LEARNER'S LINE BELONGS TO ([SessionDay.exchangeOf]) — «В разговоре» on 32-1,
  /// third state. Null when the day's dialogue does not hold that line: the block is then not drawn.
  final WindowPair? Function(String ownLineTarget)? exchangeOf;

  /// The plan's level — the dialogue's mode (SESSION-1c, section 2).
  final PlanLevel level;

  /// «No hints» (30-1, per plan): the dialogue asks blind, «Speak myself» shows no frame.
  final bool noHints;

  /// The conversation so far — the bubbles above a dialogue card (33-7, [DialogueFeed.before]).
  final List<FeedLine> feed;

  /// The cards of this card's stage as the session holds them — the review's results (34-3).
  final List<SessionCard> stageCards;

  /// The day's scene — the partner's circle of the visit player (34-1).
  final PlanScene? scene;

  /// Whether a stage of the day is answered in full — «Microphone needed» names the voice stages' state; null — both
  /// voice stages stand ahead (the words and phrases never see them done).
  final bool Function(PlanStage stage)? stageDone;

  /// The target language's spoken rules, as the DAY sent them (work order FIX-2, item 2) — the phone judges a
  /// spoken attempt by the server's own lists, never by a copy of English in Dart.
  final SpeechRules speech;

  /// A card that walks several scenes names the one on screen — the strip above it follows (37-3, «Вспомнить»).
  final ValueChanged<String>? showScene;

  /// After the second failure the unit comes back tomorrow — the server said so in its answer.
  bool get returnsTomorrow => outcome?.unit.returnsTomorrow ?? false;

  /// The judge is the server's, so a replay has none: what it says about a free answer is the phone's own coverage
  /// ([SessionRules.replayAccepted]), and «by meaning ✓» — a verdict only the judge can give — is not shown.
  bool get judgedHere => !replay;
}

/// SHARED CARD LAYOUT: the task line on top, the material sheet (pinned to the task line or centred in the free
/// field), the answer zone pinned to the bottom as a dock. The dock lies OVER the field, as in the canvas
/// (`position:absolute`, gradient on top): the field runs under the dock's transparent top inset, and whatever
/// does not fit goes under its solid part instead of being cut by the scroll edge.
class CardLayout extends StatelessWidget {
  const CardLayout({
    super.key,
    required this.task,
    required this.body,
    this.above,
    this.bottom,
    this.centerBody = false,
    this.taskInBody = false,
    this.overlayDock = true,
    this.taskGap = 8,
    this.bodyGap = 12,
    this.fadeStop = 0.22,
    this.feed = false,
  });

  /// The task line at the top of the screen; null — this card says what to do inside its own body (the check of a
  /// dialogue puts its question beside the bubble it asks about, FIX-1 доработка).
  final Widget? task;
  final Widget body;

  /// THE CONVERSATION SO FAR, above the card's own rows (series 33, [feed] only) — the ONLY part of the field that
  /// may be scrolled out of sight. It takes what is left after the task line and the card's own rows and scrolls
  /// inside that, resting on its newest line.
  ///
  /// It is a slot of its own, and that is the whole point (work order FIX-1 §1): while the conversation stood inside
  /// [body], a long one pushed the task line and the question off the top of the screen — four options and nothing
  /// saying what they answer (the live pass of 18.09, exchange x6). The canvas draws the check with no conversation
  /// above it at all (33-1, 33-5 «ответ закрыт · вопрос»); here it stays, one scroll away.
  final Widget? above;
  final Widget? bottom;

  /// The sheet centred in the free field (voice cards, tiles) rather than under the task line.
  final bool centerBody;

  /// Text-only sheet top (30-9 «question · text top»): the task line and the sheet are one group centred in the
  /// free field.
  final bool taskInBody;

  /// A conversation (series 33, 34-3, 34-5, 35-2, SESSION-1c): the task line and the bubbles are one group pinned
  /// to the dock — it grows upward, the beginning is reached by scrolling up, the air stands above the task (the
  /// canvas's own exception to «no empty field»). What grows is [above]; the task line and [body] always stand.
  final bool feed;

  /// The dock lies over the field (default). `false` — the dock sits flush under the field, the field is clipped
  /// by its own edge: that is for a card whose dock is tall and changes height across states (32-9 — chips, live
  /// line, microphone), and nothing of the field may end up under it.
  final bool overlayDock;

  /// From the scene strip to the task line.
  final double taskGap;

  /// From the task line to the sheet.
  final double bodyGap;
  final double fadeStop;

  @override
  Widget build(BuildContext context) {
    final dock = bottom;
    final field = feed ? _feedField(dock) : _field(dock);
    return _withDock(field, dock);
  }

  /// THE FIELD OF A CONVERSATION CARD. THE TASK LINE NEVER SCROLLS AWAY (FIX-1 §1): it and the question under it
  /// stand outside the scrolling area, and the conversation with the card's own rows takes what is left of the
  /// field, resting on its newest line — the beginning is one scroll up. Everything fits — the group hugs the dock
  /// with the air above the task, exactly as before.
  Widget _feedField(Widget? dock) => Padding(
    // 16 of air above the dock's solid part (33-1: the group stands 16 over the dock).
    padding: EdgeInsets.fromLTRB(kSessionGutter, taskGap, kSessionGutter, dock == null ? 16 : SessionDock.topInset + 16),
    child: Column(
      mainAxisAlignment: MainAxisAlignment.end,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (task case final line?) ...[line, SizedBox(height: bodyGap)],
        Flexible(
          child: SingleChildScrollView(
            // The newest line is the one that matters: the view rests at the bottom and older lines stand above it.
            reverse: true,
            child: above == null
                ? body
                : Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [above!, body]),
          ),
        ),
      ],
    ),
  );

  Widget _field(Widget? dock) => CustomScrollView(
    // Under an overlay dock the field is not clipped: the bottom that did not fit is covered by the dock itself.
    clipBehavior: dock == null || !overlayDock ? Clip.hardEdge : Clip.none,
    slivers: [
      if (!taskInBody && task != null)
        SliverPadding(
          padding: EdgeInsets.fromLTRB(kSessionGutter, taskGap, kSessionGutter, 0),
          sliver: SliverToBoxAdapter(child: task),
        ),
      SliverFillRemaining(
        hasScrollBody: false,
        child: Padding(
          padding: EdgeInsets.fromLTRB(kSessionGutter, taskInBody || task == null ? taskGap : bodyGap, kSessionGutter, dock == null ? 16 : 0),
          child: switch ((taskInBody, centerBody)) {
            (true, _) => Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [if (task case final line?) ...[line, SizedBox(height: bodyGap)], body],
              ),
            ),
            (false, true) => Center(child: body),
            (false, false) => Align(alignment: Alignment.topCenter, child: body),
          },
        ),
      ),
    ],
  );

  Widget _withDock(Widget field, Widget? dock) {
    if (dock == null) return field;
    if (!overlayDock) {
      // The dock never overflows the card: taller than the whole card (a low phone, the debug field) it scrolls
      // inside its own height, and the field gets what is left.
      return CustomMultiChildLayout(
        delegate: _DockUnderField(),
        children: [
          LayoutId(id: _CardSlot.field, child: field),
          LayoutId(
            id: _CardSlot.dock,
            child: SingleChildScrollView(primary: false, child: SessionDock(fadeStop: fadeStop, child: dock)),
          ),
        ],
      );
    }
    return ClipRect(
      child: CustomMultiChildLayout(
        delegate: _DockOverField(),
        children: [
          LayoutId(id: _CardSlot.field, child: field),
          LayoutId(id: _CardSlot.dock, child: SessionDock(fadeStop: fadeStop, child: dock)),
        ],
      ),
    );
  }
}

enum _CardSlot { field, dock }

/// The dock is pinned to the bottom; the field spans from the top to the dock's top plus its transparent top inset.
class _DockOverField extends MultiChildLayoutDelegate {
  @override
  void performLayout(Size size) {
    final dock = layoutChild(_CardSlot.dock, BoxConstraints(minWidth: size.width, maxWidth: size.width, maxHeight: size.height));
    positionChild(_CardSlot.dock, Offset(0, size.height - dock.height));
    final field = (size.height - dock.height + SessionDock.topInset).clamp(0.0, size.height);
    layoutChild(_CardSlot.field, BoxConstraints.tight(Size(size.width, field)));
    positionChild(_CardSlot.field, Offset.zero);
  }

  @override
  bool shouldRelayout(_DockOverField oldDelegate) => false;
}

/// The dock is pinned to the bottom at most as tall as the card; the field takes the rest above it.
class _DockUnderField extends MultiChildLayoutDelegate {
  @override
  void performLayout(Size size) {
    final dock = layoutChild(_CardSlot.dock, BoxConstraints(minWidth: size.width, maxWidth: size.width, maxHeight: size.height));
    positionChild(_CardSlot.dock, Offset(0, size.height - dock.height));
    layoutChild(_CardSlot.field, BoxConstraints.tight(Size(size.width, size.height - dock.height)));
    positionChild(_CardSlot.field, Offset.zero);
  }

  @override
  bool shouldRelayout(_DockUnderField oldDelegate) => false;
}

/// VOICE CARD — the shared flow «recording → pass → two attempts → skip» for `word_repeat`, `phrase_repeat`,
/// `phrase_other_slot` (canvases 31-2, 32-6, 32-7).
///
/// Voice never writes `failed`: pass — `passed` and auto-advance after 600 ms; a second attempt without a pass —
/// `skipped` and «Next» by hand; «Skip» — `skipped` at once. No microphone — «Microphone needed», where «Skip»
/// writes `skipped` with `no_mic`.
///
/// Rounds (polish pass SESSION-1b′, item 12): a card with [roundCount] > 1 is said round by round — a passed round
/// stays on screen for a beat, then the next one starts with its own phrase ([onRoundStarted]); two misses in any round
/// close the card as `skipped`; one answer goes to the server at the end, `filler_index` — the last filler said.
mixin VoiceCardState<T extends StatefulWidget> on State<T> {
  CardEnv get env;

  late SessionMic mic;
  int _attempts = 0;
  bool _done = false;
  String _heard = '';

  int _round = 0;
  int _roundAttempts = 0;
  int _lastPassedRound = -1;
  bool _roundPassed = false;

  /// How many rounds the card has; one — a card without rounds.
  int get roundCount => 1;

  /// The round being said, from 0.
  int get round => _round;

  /// A round other than the last has just been passed — it stays on screen until the next one starts.
  bool get roundPassed => _roundPassed;

  /// The round's `filler_index` — for the answer of a card with rounds.
  int? fillerIndexOfRound(int round) => null;

  /// The `filler_index` the answer carries: a card with rounds names the last filler said. Null — the card names no
  /// filler, which is what [fillerIndexOfRound] answers by default.
  ///
  /// The count of rounds is NOT what decides it. It was, and it stopped being true twice over: a `phrase_repeat` is
  /// one round since FIX-2 §5 and names its filler all the same, and a «Скажи целиком» whose window has one value
  /// can end up a single round once the stage's ceiling takes its own word (DECISIONS п. 354).
  int? get answerFillerIndex => _lastPassedRound >= 0 ? fillerIndexOfRound(_lastPassedRound) : null;

  /// The next round has started — the card shows its phrase.
  void onRoundStarted(int round) {}

  /// The card is closed by a skip after the second attempt — «Next» by hand.
  bool skippedAfterMisses = false;

  bool get done => _done;
  int get attempts => _attempts;
  String get heard => _heard;

  /// What should be said.
  String get expectedSpeech;

  /// Hint words for the recognizer — by default the words of the expected text.
  List<String> get contextual => [
    expectedSpeech,
    ...expectedSpeech.split(RegExp(r'\s+')).where((w) => w.trim().isNotEmpty),
  ];

  /// Pass for what was heard — the kind's rule (may update the card's screen).
  bool accepts(String heard);

  /// THE VERDICT ON WHAT WAS HEARD, which may take time: the own-word round of «Say it whole» asks the slot judge
  /// over the network (FIX-1 §6), and the microphone stays closed until it answers. Everything else answers at once
  /// out of [accepts].
  Future<bool> grade(String heard) async => accepts(heard);

  /// Passed — the card shows its own «heard».
  void onAccepted(String heard) {}

  /// The last attempt is spent and none passed — the card is closed as `skipped`. What the exchange still owes the
  /// learner happens here (33-5: the partner's reply sounds and its check is asked all the same, FIX-1 §1).
  void onMissedOut(String heard) {}

  /// `response.mode` of the answer — the dialogue's voice mode (SESSION-1c); null — none.
  String? get responseMode => null;

  /// A pass leaves by itself after 600 ms; false — the card keeps what the pass opened (the echo's revealed line,
  /// 35-3) and waits for «Next».
  bool get autoAdvanceOnPass => true;

  /// Every attempt ended — the card may show what was said (33-3: in the own bubble; 35-3: the revealed line).
  void onAttempt(String heard, {required bool accepted}) {}

  void initVoice() {
    mic = env.makeMic(expectedSpeech, contextual)..onTurn = _onTurn;
    mic.addListener(_onMic);
  }

  /// What must be said has changed while the card is open (32-7 — another chip): a new microphone with the new
  /// reference. The attempts already made stay: it is the same card.
  void refreshVoice() {
    mic.removeListener(_onMic);
    mic.dispose();
    initVoice();
    setState(() {});
  }

  /// The answer's response. A card with rounds carries the last filler said (none said — none); `mode = "rounds"`
  /// of item 12 is NOT sent: the server accepts only `chips` / `tiles` / `voice_hint` / `voice_blind`
  /// (`AnswerCardRequest::MODES`) and answers anything else with 422, which drops the answer.
  SessionResponse _response({String? heard, bool noMic = false}) => SessionResponse(
    heard: heard == null || heard.isEmpty ? null : heard,
    noMic: noMic ? true : null,
    fillerIndex: answerFillerIndex,
    mode: responseMode,
  );

  void _startRound(int next) {
    mic.removeListener(_onMic);
    mic.dispose();
    _round = next;
    _roundAttempts = 0;
    _roundPassed = false;
    _heard = '';
    initVoice();
    onRoundStarted(next);
    setState(() {});
  }

  void disposeVoice() {
    mic.removeListener(_onMic);
    mic.dispose();
  }

  bool _noMicReported = false;

  void _onMic() {
    final noMic = mic.state == MicState.unavailable;
    if (noMic != _noMicReported) {
      _noMicReported = noMic;
      env.reportNoMic(noMic);
    }
    if (mounted) setState(() {});
  }

  Future<void> _onTurn(MicTurn turn) async {
    if (_done || _roundPassed || !mounted) return;
    _heard = turn.transcript;
    _attempts++;
    _roundAttempts++;
    final ok = turn.outcome == SpeechTurnOutcome.heard && await grade(turn.transcript);
    // The judge answers over the network: the card may be gone, or closed by «Skip», by the time it does.
    if (!mounted || _done || _roundPassed) return;
    onAttempt(turn.transcript, accepted: ok);
    if (ok) {
      mic.settle(accepted: true);
      SessionSounds.verdict(correct: true);
      _lastPassedRound = _round;
      onAccepted(turn.transcript);
      if (_round < roundCount - 1) {
        // The passed round stays for a beat, then the next one starts; no answer until the last round.
        _roundPassed = true;
        final next = _round + 1;
        unawaited(Future<void>.delayed(AppMotion.sessionAutoAdvance, () {
          if (mounted && !_done && _roundPassed) _startRound(next);
        }));
        setState(() {});
        return;
      }
      _done = true;
      submitAnswer(SessionAnswer(result: SessionResult.passed, attempts: _attempts, response: _response(heard: turn.transcript)));
      if (autoAdvanceOnPass) {
        unawaited(Future<void>.delayed(AppMotion.sessionAutoAdvance, () {
          if (mounted) unawaited(env.next());
        }));
      }
      setState(() {});
      return;
    }
    mic.settle(accepted: false);
    if (_roundAttempts >= SessionRules.voiceAttempts) {
      _done = true;
      skippedAfterMisses = true;
      submitAnswer(SessionAnswer(result: SessionResult.skipped, attempts: _attempts, response: _response(heard: turn.transcript)));
      onMissedOut(turn.transcript);
    }
    setState(() {});
  }

  /// WHERE THE CARD'S ANSWER GOES. A card that must add something to the answer before it flies — 33-5 adds the
  /// check's `choice` — overrides this, holds the answer and sends it itself.
  void submitAnswer(SessionAnswer answer) => env.submit(answer);

  /// «Skip» at the microphone.
  void skip({bool noMic = false}) {
    if (_done) return;
    _done = true;
    unawaited(env.voice.stop());
    submitAnswer(SessionAnswer(
      result: SessionResult.skipped,
      attempts: _attempts < 1 ? 1 : _attempts,
      response: _response(heard: _heard, noMic: noMic),
    ));
    env.reportNoMic(false);
    unawaited(env.next());
  }

  /// «Allow» on the «Microphone needed» screen.
  Future<void> allowMic() async {
    final ok = await mic.askAgain();
    if (!ok && mic.blockedInSettings) await env.openSettings();
  }

  /// The voice card's dock: the microphone, or «Next» after the second attempt. [liveLineInDock] false — the live
  /// line stands in the card itself (the own bubble of the dialogue, 33-3), the dock keeps the wave and the captions;
  /// [showIdleCaption] false — no «tap to speak» at all, the card's task line says it (32-7).
  Widget voiceDock(
    BuildContext context, {
    bool showHeardLine = true,
    String? heardText,
    bool liveLineInDock = true,
    bool showIdleCaption = true,
    String? missedCaption,
    String? missedHeard,
    bool skipBrass = false,
  }) {
    final l = AppLocalizations.of(context);
    // Second attempt without a pass: «once more» is no longer offered — only «Next».
    if (skippedAfterMisses) {
      return SessionDockButton(label: l.planSessionNext, busy: env.advancing, onTap: () => unawaited(env.next()));
    }
    return SessionMicPanel(
      mic: mic,
      expected: expectedSpeech,
      onSkip: _done ? null : () => skip(),
      showHeardLine: showHeardLine,
      heardText: heardText,
      showIdleCaption: showIdleCaption,
      // The judge's reason stands where «didn't catch that» would (32-7's own-word round, FIX-1 §6), and what the
      // phone heard under it (CLIENT-CONV-1b).
      missedCaption: missedCaption == null || missedCaption.isEmpty ? null : missedCaption,
      missedHeard: missedHeard,
      skipBrass: skipBrass,
      liveLine: liveLineInDock ? MicLiveLine.target : MicLiveLine.none,
    );
  }

  /// The card body without a microphone — «Microphone needed» (30-3).
  Widget? noMicBody(String Function(PlanStage) stageName) => mic.state == MicState.unavailable
      ? SessionNoMicView(
          onAllow: () => unawaited(allowMic()),
          onSkip: () => skip(noMic: true),
          stageName: stageName,
          current: env.card.stage,
          done: env.stageDone,
        )
      : null;
}

/// CHOICE CARD — template 30-9: a tap on an option is the answer; correct — a sage backing and auto-advance after
/// 600 ms; wrong — an ink outline on the chosen one, sage on the correct one and «Next» by hand; the second mistake
/// on a unit — the correct one gets the «comes back tomorrow» dot, when the server said so.
mixin ChoiceCardState<T extends StatefulWidget> on State<T> {
  CardEnv get env;
  ChoicePayload get choice;

  String? _chosen;
  int _shake = 0;

  bool get answered => _chosen != null;
  bool get answeredCorrectly => _chosen != null && _chosen == choice.correct;
  bool get answeredWrong => _chosen != null && _chosen != choice.correct;

  /// A correct answer leaves by itself after 600 ms; false — the card decides when ([onChosen]).
  bool get autoAdvanceOnCorrect => true;

  /// The answer is given and sent — the card may open what the answer revealed (34-5: the partner's reply sounds).
  void onChosen({required bool correct}) {}

  void choose(String optionId) {
    if (_chosen != null) return;
    final correct = SessionRules.choiceCorrect(choice, optionId);
    setState(() {
      _chosen = optionId;
      if (!correct) _shake++;
    });
    if (correct) {
      AppHaptics.success();
    } else {
      AppHaptics.warning();
    }
    SessionSounds.verdict(correct: correct);
    env.submit(SessionAnswer(result: correct ? SessionResult.passed : SessionResult.failed, attempts: 1));
    onChosen(correct: correct);
    if (correct && autoAdvanceOnCorrect) {
      unawaited(Future<void>.delayed(AppMotion.sessionAutoAdvance, () {
        if (mounted) unawaited(env.next());
      }));
    }
  }

  OptionLook lookOf(String optionId) {
    if (_chosen == null) return OptionLook.idle;
    if (optionId == choice.correct) return env.returnsTomorrow ? OptionLook.returns : OptionLook.correct;
    if (optionId == _chosen) return OptionLook.wrong;
    return OptionLook.settled;
  }

  int shakeOf(String optionId) => optionId == _chosen ? _shake : 0;

  /// The options and, after a wrong one, «Next».
  Widget optionsDock(BuildContext context, {bool target = false, Widget? Function(CardOption option)? listen}) {
    final l = AppLocalizations.of(context);
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (final o in choice.options) ...[
          if (o != choice.options.first) const SizedBox(height: 8),
          SessionOption(
            key: ValueKey('option-${o.id}'),
            text: o.text,
            target: target,
            look: lookOf(o.id),
            shake: shakeOf(o.id),
            listen: listen?.call(o),
            onTap: answered ? null : () => choose(o.id),
          ),
        ],
        if (answeredWrong) ...[
          const SizedBox(height: 8),
          SessionDockButton(label: l.planSessionNext, busy: env.advancing, onTap: () => unawaited(env.next())),
        ],
      ],
    );
  }

  /// The «comes back tomorrow» eyebrow on the right, when the server said the unit comes back.
  String? eyebrowTrailing(AppLocalizations l) => env.returnsTomorrow ? l.planWindowSheetReturnsTomorrow : null;
}

/// «Listen» with a wave while exactly this sound is playing; [brass] — the bubble's «listen» 28 (23-0d).
class CardListen extends StatelessWidget {
  const CardListen({
    super.key,
    required this.env,
    required this.audio,
    required this.fallback,
    required this.playKey,
    this.size = 44,
    this.rate = 1.0,
    this.brass = false,
    this.onPaper = false,
  });

  final CardEnv env;
  final CardAudio? audio;
  final String fallback;
  final Object playKey;
  final double size;
  final double rate;
  final bool brass;

  /// See [SessionListenButton.onPaper].
  final bool onPaper;

  @override
  Widget build(BuildContext context) => ValueListenableBuilder<Object?>(
    valueListenable: env.voice.playing,
    builder: (context, playing, _) => SessionListenButton(
      size: size,
      brass: brass,
      onPaper: onPaper,
      label: AppLocalizations.of(context).planWindowListen,
      playing: playing == playKey,
      onTap: () => unawaited(env.voice.play(audio, fallback: fallback, rate: rate, key: playKey)),
    ),
  );
}
