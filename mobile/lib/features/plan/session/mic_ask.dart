import 'package:flutter/material.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/ui/paper_sheet.dart';

import '../../../data/speech/speech_diagnostics.dart';

/// THE MICROPHONE'S PRE-PERMISSION (frame 41-3, work order CLIENT-START §4) — asked on the first card that shows a
/// microphone, never at launch, and only while iOS has not been asked: «Ritora слушает, как ты говоришь», a line about
/// where the microphone is needed, «Позже» in brass and «Разрешить микрофон». The system's own dialogs come only after
/// «Разрешить» ([allow] — the recognizer's `prepare`, which asks for speech recognition and then the microphone).
///
/// True — the card may listen: allowed now, or nothing to ask (already answered either way — a refusal stays as it
/// always was: the first tap finds out and the card offers «Skip» and the Settings; or no probe to ask with — a test, a
/// harness). False — «Позже», or the system said no just now: the card offers «Skip», and the next microphone card asks
/// again.
Future<bool> askMicOnce(
  BuildContext context, {
  required Future<SpeechProbe> Function() probe,
  required Future<bool> Function() allow,
}) async {
  final state = await probe();
  final undetermined =
      state.microphone == SpeechPermission.notDetermined || state.recognition == SpeechPermission.notDetermined;
  if (!undetermined || state.blockedInSettings || !context.mounted) return true;
  final l = AppLocalizations.of(context);
  final yes = await showPaperSheet<bool>(
    context: context,
    builder: (context) => PaperSheetBody(
      key: const ValueKey('mic-ask'),
      title: l.micAskTitle,
      body: l.micAskBody,
      stayLabel: l.micAskLater,
      onStay: () => Navigator.of(context).pop(false),
      actionLabel: l.micAskAllow,
      onAction: () => Navigator.of(context).pop(true),
    ),
  );
  if (yes != true) return false;
  return allow();
}
