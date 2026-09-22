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

/// «ЕЩЁ РАЗ» ОВЕР A WALKED TALK (наряд FIX-3 §5; кадр 30-1 «день пройден», `window.stages[].again`) — a new
/// talk over a walked day of any kind, on the SAME screens the day's session shows: the ribbon (37-6…37-11) and the
/// summary (37-12). The day window made the one POST before it came here and read the answer — a 409
/// `plan_conversation_replay_limit` is said there, on a sheet, and never opens this screen.
///
/// The screen owns the [talk] and the [voice] it is given from here on. «Дальше» on the summary is the way back to the
/// window, which reads the day again; another replay is started from the day's own plate («ещё раз» of the row, наряд
/// FIX-3 §5), where a spent limit is said on a sheet.
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
    contextualStrings: [...?_talk.talk?.targets.map((t) => t.saidWith(t.exampleTarget))].take(50).toList(),
    config: const SpeechTurnConfig(silenceAfterSpeech: ConversationController.silenceClosesTurn),
  );

  Future<void> _openSettings() async {
    try {
      await launchUrl(Uri.parse('app-settings:'));
    } catch (_) {
      // Settings did not open — the talk's own exits remain.
    }
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
                    onClose: () => unawaited(_leave()),
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
