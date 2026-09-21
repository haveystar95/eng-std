import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';

import 'package:eng_std/theme/theme.dart';

import '../../../data/languages.dart' show sttLocaleFor;
import '../../../data/plan/plan_models.dart';
import '../../../data/providers.dart';
import '../../../data/speech/speech_turn.dart' show SpeechTurnConfig;
import '../../profile/qa_report_button.dart' show QaReportHidden;
import '../session/session_mic.dart';
import '../session/session_voice.dart';
import 'conversation_controller.dart';
import 'talk_screen.dart';
import 'talk_summary.dart';

/// «ПОВТОРИТЬ РАЗГОВОР» (наряд CLIENT-CONV-1c §9г; кадр 37-1 «пройден», `window.talk_again` of BACK-TAILS-2) — a new
/// talk over a walked day of any kind, on the SAME screens the day's session shows: the ribbon (37-6…37-11) and the
/// summary (37-12). The day window made the one POST before it came here and read the answer — a 409
/// `plan_conversation_replay_limit` is said there, on a sheet, and never opens this screen.
///
/// The screen owns the [talk] and the [voice] it is given from here on. «Дальше» on the summary is the way back to the
/// window, which reads the day again; «Ещё раз» starts another replay, and a limit reached then is said where the talk
/// would have started (the talk's own «could not start» line).
class TalkReplayScreen extends ConsumerStatefulWidget {
  const TalkReplayScreen({super.key, required this.plan, required this.number, required this.talk, required this.voice});

  final Plan plan;
  final int number;
  final ConversationController talk;
  final SessionVoice voice;

  @override
  ConsumerState<TalkReplayScreen> createState() => _TalkReplayScreenState();
}

class _TalkReplayScreenState extends ConsumerState<TalkReplayScreen> {
  bool _summary = false;
  bool _starting = false;

  ConversationController get _talk => widget.talk;

  @override
  void initState() {
    super.initState();
    // The owner's short sounds — the microphone's «on» among them — as the session has them.
    unawaited(SessionSounds.load());
  }

  @override
  void dispose() {
    _talk.dispose();
    unawaited(widget.voice.release());
    unawaited(SessionSounds.release());
    super.dispose();
  }

  PlanScene? get _scene {
    for (final d in widget.plan.days) {
      if (d.number == widget.number) return widget.plan.sceneOf(d);
    }
    return null;
  }

  SessionMic _mic() => SessionMic(
    recognizer: ref.read(speechRecognizerProvider),
    diagnostics: ref.read(speechDiagnosticsProvider),
    localeId: sttLocaleFor(widget.plan.targetLang),
    expected: '',
    contextualStrings: [...?_talk.talk?.targets.map((t) => t.textTarget)].take(50).toList(),
    config: const SpeechTurnConfig(silenceAfterSpeech: ConversationController.silenceClosesTurn),
  );

  Future<void> _openSettings() async {
    try {
      await launchUrl(Uri.parse('app-settings:'));
    } catch (_) {
      // Settings did not open — the talk's own exits remain.
    }
  }

  /// «Ещё раз» on the summary — another replay; the old one the server closes as `replayed`.
  Future<void> _again() async {
    setState(() {
      _starting = true;
      _summary = false;
    });
    await _talk.open(again: true);
    if (mounted) setState(() => _starting = false);
  }

  Future<void> _leave() async {
    await widget.voice.stop();
    if (mounted) Navigator.of(context).pop();
  }

  @override
  Widget build(BuildContext context) {
    final document = _talk.talk;
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark,
      child: QaReportHidden(
        child: Scaffold(
          backgroundColor: AppColors.ground,
          body: SafeArea(
            bottom: false,
            child: _summary && document?.summary != null
                ? TalkSummaryView(
                    talk: document!,
                    scene: _scene,
                    sceneById: widget.plan.sceneById,
                    voice: widget.voice,
                    busy: _starting,
                    onClose: () => unawaited(_leave()),
                    onAgain: () => unawaited(_again()),
                    onNext: () => unawaited(_leave()),
                  )
                : TalkView(
                    controller: _talk,
                    scene: _scene,
                    sceneById: widget.plan.sceneById,
                    voice: widget.voice,
                    makeMic: _mic,
                    openSettings: _openSettings,
                    onSummary: () => setState(() => _summary = true),
                    onClose: () => unawaited(_leave()),
                  ),
          ),
        ),
      ),
    );
  }
}
