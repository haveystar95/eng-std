import 'dart:async';

import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../data/plan/conversation/conversation_models.dart';
import '../session/parts/session_bits.dart';
import '../session/session_voice.dart';
import 'talk_ribbon.dart' show kTalkPlateShadow;

/// THE PHRASES OF THE TALK ON THE RIBBON (наряд CLIENT-CONV-1c §4; SESSION-DES-4, кадры 37-6…37-11, 37-8d): the strip
/// «фразы · N из M» over the microphone, and the sheet it opens.
///
/// Both are the SERVER'S LIST read as it came: `targets[]` of the latest answer, `said` recounted by the server on every
/// move. The phone does not match a phrase to what was said and does not keep a tally of its own — a strip that counted
/// on the phone would say «3 из 5» where the summary says «2 из 5».

/// THE PHRASE STRIP (37-6…37-11) — a plate 44 over the microphone's dock: «фразы · N из M» in ink on the left, the
/// chevron 20 on the right; paper, corners 16, the ribbon's shadow. A phrase has just sounded (N grew with the latest
/// answer) — the plate goes sage 15 % and a sage check stands left of the chevron for 260 ms, then back to paper (37-8b).
/// A tap opens the sheet.
class TalkPhraseStrip extends StatefulWidget {
  const TalkPhraseStrip({super.key, required this.said, required this.total, required this.onTap});

  final int said;
  final int total;
  final VoidCallback onTap;

  @override
  State<TalkPhraseStrip> createState() => _TalkPhraseStripState();
}

class _TalkPhraseStripState extends State<TalkPhraseStrip> {
  bool _flash = false;
  Timer? _off;

  @override
  void didUpdateWidget(TalkPhraseStrip old) {
    super.didUpdateWidget(old);
    if (widget.said > old.said) {
      _off?.cancel();
      _flash = true;
      _off = Timer(AppMotion.talkStripFlash, () {
        if (mounted) setState(() => _flash = false);
      });
    }
  }

  @override
  void dispose() {
    _off?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final text = l.planTalkStrip(widget.said, widget.total);
    return Semantics(
      button: true,
      label: text,
      child: GestureDetector(
        behavior: HitTestBehavior.opaque,
        onTap: () {
          AppHaptics.light();
          widget.onTap();
        },
        child: Container(
          key: const ValueKey('talk-strip'),
          // 44 as the frame draws it; a line that wraps grows the plate — nothing on the ribbon is cut.
          constraints: const BoxConstraints(minHeight: 44),
          padding: const EdgeInsets.symmetric(horizontal: 16),
          decoration: BoxDecoration(
            color: _flash ? AppColors.sessionSageWash : AppColors.paper,
            borderRadius: BorderRadius.circular(16),
            boxShadow: kTalkPlateShadow,
          ),
          child: Row(
            children: [
              Expanded(child: Text(text, key: const ValueKey('talk-strip-text'), style: AppTextSession.text15)),
              if (_flash) ...[
                const SizedBox(width: 10),
                const SizedBox(
                  key: ValueKey('talk-strip-check'),
                  width: 20,
                  height: 20,
                  child: Center(child: Icon(LucideIcons.check, size: 18, color: AppColors.verdictKnown)),
                ),
              ],
              const SizedBox(width: 10),
              const Icon(LucideIcons.chevronUp, size: 20, color: AppColors.tertiary),
            ],
          ),
        ),
      ),
    );
  }
}

/// THE PHRASE SHEET (37-8d) — from the bottom over a 40 % scrim, corners 22 at the top, 24 inside: «Фразы дня» 17/600 and
/// the cross 24, then the talk's phrases 12 apart. Said — on a sage wash 15 % with a sage check 20 on the right; not said
/// — in an ink outline 1.5, no fill. Each row: the phrase in Literata 17, its translation 15 under it, «прослушать» 28;
/// the circles and the checks stand on one vertical.
///
/// The rows follow the talk while the sheet is open — [targets] is read again whenever [talk] notifies — so a move
/// answered behind the sheet ticks its phrase off here too.
Future<void> showTalkPhraseSheet(
  BuildContext context, {
  required Listenable talk,
  required List<TalkTarget> Function() targets,
  required SessionVoice voice,
}) async {
  AppHaptics.light();
  await showModalBottomSheet<void>(
    context: context,
    backgroundColor: AppColors.ground,
    barrierColor: AppColors.windowSheetScrim,
    elevation: 0,
    isScrollControlled: true,
    sheetAnimationStyle: const AnimationStyle(duration: AppMotion.talkPhraseSheet, curve: AppMotion.windowEaseOutCubic),
    shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(22))),
    builder: (sheet) => ListenableBuilder(
      listenable: talk,
      builder: (context, _) => _PhraseSheet(targets: targets(), voice: voice),
    ),
  );
}

class _PhraseSheet extends StatelessWidget {
  const _PhraseSheet({required this.targets, required this.voice});

  final List<TalkTarget> targets;
  final SessionVoice voice;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final media = MediaQuery.of(context);
    return ConstrainedBox(
      constraints: BoxConstraints(maxHeight: media.size.height * 0.85),
      child: Padding(
        key: const ValueKey('talk-phrase-sheet'),
        padding: EdgeInsets.fromLTRB(24, 24, 24, 24 + media.padding.bottom),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            SizedBox(
              height: 24,
              child: Row(
                children: [
                  Expanded(child: Text(l.planTalkSheetTitle, style: AppTextSession.sheetTitle)),
                  Semantics(
                    button: true,
                    label: l.planSessionClose,
                    child: GestureDetector(
                      key: const ValueKey('talk-phrase-sheet-close'),
                      behavior: HitTestBehavior.opaque,
                      onTap: () => Navigator.of(context).pop(),
                      child: const SizedBox(width: 24, height: 24, child: Icon(LucideIcons.x, size: 24, color: AppColors.ink)),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Flexible(
              child: SingleChildScrollView(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    for (final (i, t) in targets.indexed) ...[
                      if (i > 0) const SizedBox(height: 12),
                      _PhraseRow(target: t, voice: voice),
                    ],
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// A row of the sheet — said on the sage wash with its check, not said in the outline; «прослушать» 28 on the right. The
/// circle's 44 touch box is lifted by the 8 it adds, so the circle stands level with the phrase, where the frame puts it.
class _PhraseRow extends StatelessWidget {
  const _PhraseRow({required this.target, required this.voice});

  final TalkTarget target;
  final SessionVoice voice;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final key = 'talk-target-${target.sceneId}-${target.ref}';
    return ValueListenableBuilder<Object?>(
      valueListenable: voice.playing,
      builder: (_, playing, _) => Container(
        key: ValueKey('talk-phrase-${target.sceneId}-${target.ref}'),
        padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
        decoration: BoxDecoration(
          color: target.said ? AppColors.sessionSageWash : null,
          borderRadius: BorderRadius.circular(16),
          border: target.said ? null : Border.all(color: AppColors.markerOutline, width: 1.5),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(target.textTarget, style: AppTextSession.phrase17),
                  const SizedBox(height: 2),
                  Text(target.textNative, style: AppTextSession.body),
                ],
              ),
            ),
            const SizedBox(width: 2),
            Transform.translate(
              offset: const Offset(0, -8),
              child: SessionListenButton(
                size: 28,
                brass: true,
                label: l.planWindowListen,
                playing: playing == key,
                // The talk's phrases carry no sound of their own: the phone plays the file the day already has for this
                // text, or reads it.
                onTap: () => unawaited(voice.play(null, fallback: target.textTarget, key: key)),
              ),
            ),
            const SizedBox(width: 2),
            SizedBox(
              width: 20,
              height: 20,
              child: target.said
                  ? const Center(
                      key: ValueKey('talk-phrase-said'),
                      child: Icon(LucideIcons.check, size: 18, color: AppColors.verdictKnown),
                    )
                  : null,
            ),
          ],
        ),
      ),
    );
  }
}
