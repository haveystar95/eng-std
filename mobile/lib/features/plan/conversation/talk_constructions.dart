import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../data/plan/conversation/conversation_models.dart';
import '../plan_stage_text.dart' show PlanDot;
import '../session/parts/session_bits.dart';

/// THE CONSTRUCTIONS OF THE TALK (наряд FIX-3 §3; кадры 37-5, 37-7…37-11, 37-8d, 37-12 серии 38).
///
/// The talk is for CONSTRUCTIONS, not for phrases: «I have pain in my ___.» with the lesson's own value grey beside it.
/// Everything here is the server's `targets[]` read as it came — the frame, the lesson's example, whether the server
/// has heard the construction (`said`) and what the learner put in its window (`value_target`). The phone matches
/// nothing and counts nothing: a plate it filled by its own rule would go sage where the summary says the construction
/// did not sound.
///
/// Three places, one shape: the entry lists them ([TalkConstructionRow]), the dock holds them as plates over the
/// microphone ([TalkConstructionChips]), the summary closes them ([TalkConstructionCard]); a tap on a plate opens the
/// one sheet ([showTalkConstructionSheet]).

/// THE FRAME WITH ITS WINDOW — the construction in one line: the window holds what the learner said when the server
/// heard it (sage outline, 8 % fill, the value in ink), and the bare `___` in brass until then. A construction whose
/// frame has no window is one whole line.
class TalkConstructionLine extends StatelessWidget {
  const TalkConstructionLine({super.key, required this.target, required this.style});

  final TalkTarget target;
  final TextStyle style;

  @override
  Widget build(BuildContext context) {
    final at = target.frameTarget.indexOf(TalkTarget.window);
    if (at < 0) return SessionFrameText.plain(target.frameTarget, style: style);
    final value = target.said ? target.valueTarget : null;
    return SessionFrameText(
      before: target.frameTarget.substring(0, at),
      after: target.frameTarget.substring(at + TalkTarget.window.length),
      style: style,
      slot: value ?? TalkTarget.window,
      look: target.said ? SlotLook.said : SlotLook.empty,
    );
  }
}

/// A ROW OF «СКАЖИ В РАЗГОВОРЕ» (кадр 37-5) — the frame in Literata 17 and, under it 2, the lesson's own example in
/// grey 13: «I have pain in my lower back. · У меня болит поясница.» A construction the lesson gave no example for
/// stands alone.
class TalkConstructionRow extends StatelessWidget {
  const TalkConstructionRow({super.key, required this.target});

  final TalkTarget target;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    // The lesson's example is a VALUE («lower back»), so the line is the frame said with it — on both languages.
    final example = target.exampleTarget == null
        ? null
        : (target.exampleNative == null
              ? target.saidWith(target.exampleTarget)
              : l.planDotPlain(target.saidWith(target.exampleTarget), target.nativeWith(target.exampleNative)));
    return Column(
      key: ValueKey('talk-entry-target-${target.sceneId}-${target.ref}'),
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        TalkConstructionLine(target: target, style: AppTextSession.phrase17),
        if (example != null) ...[const SizedBox(height: 2), Text(example, style: AppTextSession.meta)],
      ],
    );
  }
}

/// THE PLATES OVER THE MICROPHONE (кадры 37-7…37-11) — the talk's constructions in a row that scrolls sideways, 44
/// high, 8 apart, its edges fading into the dock. Said — a sage wash 15 % and a sage check; not said — paper in a
/// thin outline. The row is part of the dock and stands in EVERY state of the ribbon; a tap on a plate opens its
/// sheet.
///
/// It runs the dock's whole width, gutter to gutter: the plates go on under the edge instead of stopping short of it,
/// so it is plain that there are more of them.
class TalkConstructionChips extends StatelessWidget {
  const TalkConstructionChips({super.key, required this.targets, required this.onTap});

  final List<TalkTarget> targets;
  final void Function(TalkTarget target) onTap;

  static const double height = 44;

  @override
  Widget build(BuildContext context) => SizedBox(
    key: const ValueKey('talk-constructions'),
    height: height,
    child: LayoutBuilder(
      builder: (context, box) {
        final width = box.maxWidth + 2 * kSessionGutter;
        return OverflowBox(
          maxWidth: width,
          minWidth: width,
          child: ShaderMask(
            blendMode: BlendMode.dstIn,
            shaderCallback: (bounds) => const LinearGradient(
              begin: Alignment.centerLeft,
              end: Alignment.centerRight,
              colors: [AppColors.groundClear, AppColors.ground, AppColors.ground, AppColors.groundClear],
              stops: [0, 0.06, 0.94, 1],
            ).createShader(bounds),
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: kSessionGutter),
              itemCount: targets.length,
              separatorBuilder: (_, _) => const SizedBox(width: 8),
              itemBuilder: (_, i) => _Chip(target: targets[i], onTap: () => onTap(targets[i])),
            ),
          ),
        );
      },
    ),
  );
}

class _Chip extends StatelessWidget {
  const _Chip({required this.target, required this.onTap});

  final TalkTarget target;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Semantics(
    button: true,
    label: target.chipText,
    child: GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: () {
        AppHaptics.light();
        onTap();
      },
      child: AnimatedContainer(
        key: ValueKey('talk-construction-${target.sceneId}-${target.ref}'),
        duration: AppMotion.sessionSlotSage,
        height: TalkConstructionChips.height,
        padding: const EdgeInsets.symmetric(horizontal: 14),
        decoration: BoxDecoration(
          color: target.said ? AppColors.sessionSageWash : AppColors.paper,
          borderRadius: BorderRadius.circular(12),
          border: target.said ? null : Border.all(color: AppColors.markerOutline, width: 1.5),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            TalkConstructionLine(target: target, style: AppTextSession.phrase17),
            if (target.said) ...[const SizedBox(width: 8), const _SaidCheck()],
          ],
        ),
      ),
    ),
  );
}

/// The sage check 18 in its 20 box — the one mark of a construction that has sounded (37-7, 37-8d, 37-12).
class _SaidCheck extends StatelessWidget {
  const _SaidCheck();

  @override
  Widget build(BuildContext context) => const SizedBox(
    key: ValueKey('talk-construction-said'),
    width: 20,
    height: 20,
    child: Center(child: Icon(LucideIcons.check, size: 18, color: AppColors.verdictKnown)),
  );
}

/// A CARD OF THE SUMMARY (кадры 37-12, 37-12b) — the construction, and under it in grey what the learner said or when
/// it comes back: «ты сказал: I have pain in my lower back.», «вернётся завтра», «повтори перед событием». Said, it
/// lies on a sage wash with its check; not said, it stands in a thin outline.
class TalkConstructionCard extends StatelessWidget {
  const TalkConstructionCard({super.key, required this.target, required this.returnsTomorrow});

  final TalkTarget target;

  /// The day gives what did not sound back tomorrow (`summary.returns_tomorrow`). False — the rehearsal, where
  /// tomorrow is the event itself, and a replay, which returns nothing.
  final bool returnsTomorrow;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final under = target.said
        ? l.planTalkYouSaid(target.saidWith(target.valueTarget))
        : (returnsTomorrow ? l.planWindowSheetReturnsTomorrow : l.planTalkRepeatBefore);
    return Container(
      key: ValueKey('talk-construction-card-${target.sceneId}-${target.ref}'),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: target.said ? AppColors.sessionSageWash : null,
        borderRadius: BorderRadius.circular(16),
        border: target.said ? null : Border.all(color: AppColors.markerOutline, width: 1.5),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(child: TalkConstructionLine(target: target, style: AppTextSession.phrase17)),
              if (target.said) ...[const SizedBox(width: 10), const _SaidCheck()],
            ],
          ),
          const SizedBox(height: 6),
          Text(under, style: AppTextSession.meta),
        ],
      ),
    );
  }
}

/// THE CONSTRUCTION'S SHEET (кадр 37-8d) — from the bottom over a 40 % scrim: «Конструкция», the frame in Literata 30
/// with its window and the same frame on the learner's language under it, «ИЗ УРОКА» with the lesson's example, and
/// «ТЫ СКАЗАЛ» with what the learner said — the last two only when the server sent them.
///
/// The sheet follows the talk while it is open ([talk] notifies, [target] is read again): a construction the learner
/// says while the sheet is up fills in here too.
Future<void> showTalkConstructionSheet(
  BuildContext context, {
  required Listenable talk,
  required TalkTarget? Function() target,
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
      builder: (context, _) {
        final t = target();
        return t == null ? const SizedBox.shrink() : _ConstructionSheet(target: t);
      },
    ),
  );
}

class _ConstructionSheet extends StatelessWidget {
  const _ConstructionSheet({required this.target});

  final TalkTarget target;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final media = MediaQuery.of(context);
    final example = target.exampleTarget;
    final said = target.said ? target.saidWith(target.valueTarget) : null;
    return ConstrainedBox(
      constraints: BoxConstraints(maxHeight: media.size.height * 0.85),
      child: Padding(
        key: const ValueKey('talk-construction-sheet'),
        padding: EdgeInsets.fromLTRB(24, 24, 24, 24 + media.padding.bottom),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            SizedBox(
              height: 24,
              child: Row(
                children: [
                  Expanded(child: Text(l.planTalkConstruction, style: AppTextSession.sheetTitle)),
                  Semantics(
                    button: true,
                    label: l.planSessionClose,
                    child: GestureDetector(
                      key: const ValueKey('talk-construction-sheet-close'),
                      behavior: HitTestBehavior.opaque,
                      onTap: () => Navigator.of(context).pop(),
                      child: const SizedBox(width: 24, height: 24, child: Icon(LucideIcons.x, size: 24, color: AppColors.ink)),
                    ),
                  ),
                ],
              ),
            ),
            Flexible(
              child: SingleChildScrollView(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const SizedBox(height: 14),
                    TalkConstructionLine(target: target, style: AppTextSession.frame),
                    const SizedBox(height: 8),
                    Text(target.frameNative, key: const ValueKey('talk-construction-native'), style: AppTextSession.body),
                    if (example != null) ...[
                      const SizedBox(height: 32),
                      SessionEyebrow(l.planTalkFromLesson),
                      const SizedBox(height: 14),
                      Text(target.saidWith(example), style: AppTextSession.phrase17.copyWith(color: AppColors.secondary)),
                      if (target.exampleNative case final native?) ...[
                        const SizedBox(height: 2),
                        Text(target.nativeWith(native), style: AppTextSession.body),
                      ],
                    ],
                    if (said != null) ...[
                      const SizedBox(height: 32),
                      SessionEyebrow(l.planTalkYouSaidLabel),
                      const SizedBox(height: 14),
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Expanded(child: Text(said, key: const ValueKey('talk-construction-said-line'), style: AppTextSession.target22)),
                          const SizedBox(width: 12),
                          const _SaidCheck(),
                        ],
                      ),
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
