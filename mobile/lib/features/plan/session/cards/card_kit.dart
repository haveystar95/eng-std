import 'dart:async';

import 'package:flutter/material.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../../data/plan/plan_models.dart';
import '../../../../data/plan/session/session_models.dart';
import '../../../../data/plan/session/session_outcomes.dart';
import '../../../../data/plan/session/session_rules.dart';
import '../../../../data/plan/session/speech_coverage.dart';
import '../../../../data/plan/session/speech_stop.dart';
import '../../../../data/speech/speech_turn.dart';
import '../parts/session_bits.dart';
import '../parts/session_choice.dart';
import '../parts/session_mic_panel.dart';
import '../session_mic.dart';
import '../session_voice.dart';

/// WHAT A CARD KNOWS BEYOND ITS PAYLOAD — one object for all 15 kinds of 1b.
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
    this.frameSentence,
    this.termText,
  });

  final SessionCard card;
  final SessionVoice voice;
  final String targetLang;

  /// Recognition locale — `en_US`.
  final String localeId;

  /// The partner's role in the nominative case, as the server sent it (native «Receptionist»); empty — no role.
  final String role;
  final void Function(SessionAnswer answer) submit;
  final Future<void> Function() next;
  final Future<SessionJudgeOutcome> Function(String heard) judge;

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

  /// The day's frame as a whole phrase by its `ref` ([SessionDay.frameSentence]) — the «Combination» options.
  final String? Function(String frameRef)? frameSentence;

  /// The day's word by its unit `ref` ([SessionDay.termText]) — what the phone reads on «By ear» without a file.
  final String? Function(String unitRef)? termText;

  Set<String> get articles => SpeechCoverage.articlesFor(targetLang);

  /// After the second failure the unit comes back tomorrow — the server said so in its answer.
  bool get returnsTomorrow => outcome?.unit.returnsTomorrow ?? false;
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
    this.bottom,
    this.centerBody = false,
    this.taskInBody = false,
    this.overlayDock = true,
    this.taskGap = 8,
    this.bodyGap = 12,
    this.fadeStop = 0.22,
  });

  final Widget task;
  final Widget body;
  final Widget? bottom;

  /// The sheet centred in the free field (voice cards, tiles) rather than under the task line.
  final bool centerBody;

  /// Text-only sheet top (30-9 «question · text top»): the task line and the sheet are one group centred in the
  /// free field.
  final bool taskInBody;

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
    final field = CustomScrollView(
      // Under an overlay dock the field is not clipped: the bottom that did not fit is covered by the dock itself.
      clipBehavior: dock == null || !overlayDock ? Clip.hardEdge : Clip.none,
      slivers: [
        if (!taskInBody)
          SliverPadding(
            padding: EdgeInsets.fromLTRB(kSessionGutter, taskGap, kSessionGutter, 0),
            sliver: SliverToBoxAdapter(child: task),
          ),
        SliverFillRemaining(
          hasScrollBody: false,
          child: Padding(
            padding: EdgeInsets.fromLTRB(kSessionGutter, taskInBody ? taskGap : bodyGap, kSessionGutter, dock == null ? 16 : 0),
            child: switch ((taskInBody, centerBody)) {
              (true, _) => Center(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [task, SizedBox(height: bodyGap), body],
                ),
              ),
              (false, true) => Center(child: body),
              (false, false) => Align(alignment: Alignment.topCenter, child: body),
            },
          ),
        ),
      ],
    );
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
mixin VoiceCardState<T extends StatefulWidget> on State<T> {
  CardEnv get env;

  late final SessionMic mic;
  int _attempts = 0;
  bool _done = false;
  String _heard = '';

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

  /// The same rule without side effects — for stopping the recording early on a partial result.
  bool wouldAccept(String heard) => accepts(heard);

  /// Passed — the card shows its own «heard».
  void onAccepted(String heard) {}

  void initVoice() {
    mic = env.makeMic(expectedSpeech, contextual)
      ..onTurn = _onTurn
      // Passed on a partial result — the recording stops after 500 ms without waiting for silence (1b′, item 6).
      ..autoStop = (partial) => SpeechStop.voice(partial, wouldAccept);
    mic.addListener(_onMic);
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

  void _onTurn(MicTurn turn) {
    if (_done || !mounted) return;
    _heard = turn.transcript;
    _attempts++;
    final ok = turn.outcome == SpeechTurnOutcome.heard && accepts(turn.transcript);
    if (ok) {
      _done = true;
      mic.settle(accepted: true);
      SessionSounds.verdict(correct: true);
      onAccepted(turn.transcript);
      env.submit(SessionAnswer(result: SessionResult.passed, attempts: _attempts, response: SessionResponse(heard: turn.transcript)));
      unawaited(Future<void>.delayed(AppMotion.sessionAutoAdvance, () {
        if (mounted) unawaited(env.next());
      }));
      setState(() {});
      return;
    }
    mic.settle(accepted: false);
    if (_attempts >= SessionRules.voiceAttempts) {
      _done = true;
      skippedAfterMisses = true;
      env.submit(SessionAnswer(
        result: SessionResult.skipped,
        attempts: _attempts,
        response: SessionResponse(heard: turn.transcript.isEmpty ? null : turn.transcript),
      ));
    }
    setState(() {});
  }

  /// «Skip» at the microphone.
  void skip({bool noMic = false}) {
    if (_done) return;
    _done = true;
    unawaited(env.voice.stop());
    env.submit(SessionAnswer(
      result: SessionResult.skipped,
      attempts: _attempts < 1 ? 1 : _attempts,
      response: SessionResponse(heard: _heard.isEmpty ? null : _heard, noMic: noMic ? true : null),
    ));
    env.reportNoMic(false);
    unawaited(env.next());
  }

  /// «Allow» on the «Microphone needed» screen.
  Future<void> allowMic() async {
    final ok = await mic.askAgain();
    if (!ok && mic.blockedInSettings) await env.openSettings();
  }

  /// The voice card's dock: the microphone, or «Next» after the second attempt.
  Widget voiceDock(BuildContext context, {bool showHeardLine = true, String? heardText}) {
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
    );
  }

  /// The card body without a microphone — «Microphone needed» (30-3).
  Widget? noMicBody(String Function(PlanStage) stageName) => mic.state == MicState.unavailable
      ? SessionNoMicView(onAllow: () => unawaited(allowMic()), onSkip: () => skip(noMic: true), stageName: stageName)
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
    if (correct) {
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

/// «Listen» with a wave while exactly this sound is playing.
class CardListen extends StatelessWidget {
  const CardListen({super.key, required this.env, required this.audio, required this.fallback, required this.playKey, this.size = 44, this.rate = 1.0});

  final CardEnv env;
  final CardAudio? audio;
  final String fallback;
  final Object playKey;
  final double size;
  final double rate;

  @override
  Widget build(BuildContext context) => ValueListenableBuilder<Object?>(
    valueListenable: env.voice.playing,
    builder: (context, playing, _) => SessionListenButton(
      size: size,
      label: AppLocalizations.of(context).planWindowListen,
      playing: playing == playKey,
      onTap: () => unawaited(env.voice.play(audio, fallback: fallback, rate: rate, key: playKey)),
    ),
  );
}
