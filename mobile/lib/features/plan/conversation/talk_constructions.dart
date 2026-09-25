import 'dart:async';

import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../data/plan/conversation/conversation_models.dart';
import '../plan_stage_text.dart' show PlanDot;
import '../session/parts/session_bits.dart';

/// THE CONSTRUCTIONS OF THE TALK (наряды FIX-3 §3, CLIENT-FIX-4 §2; кадры 37-5, 37-5b, 37-7…37-11, 37-8d, 37-12, 39-1).
///
/// The talk is for CONSTRUCTIONS, not for phrases: «I have pain in my ___.» with the lesson's own value grey beside it.
/// Everything here is the server's `targets[]` read as it came — the frame, the lesson's example, where the server's
/// judge left the construction (`state`: none · almost · said) and what the learner put in its window (`value_target`).
/// The phone matches nothing and counts nothing: a plate it filled by its own rule would go sage where the summary says
/// the construction did not sound.
///
/// Four places, one shape: the entry and the transition card list them ([TalkConstructionRow]), the dock holds the
/// ones still to say as plates over the microphone ([TalkConstructionChips]), the sheet and the summary close them as
/// cards ([TalkConstructionCard]); a tap on the row opens the sheet of the scene ([showTalkConstructionSheet]).

/// THE FRAME WITH ITS WINDOW — the construction in one line: the window holds what the learner said when the server
/// heard it (sage outline, 8 % fill, the value in ink), the bare `___` in brass until then; a construction said ALMOST
/// is all brass — its words and its window (37-8e). A construction whose frame has no window is one whole line.
class TalkConstructionLine extends StatelessWidget {
  const TalkConstructionLine({super.key, required this.target, required this.style});

  final TalkTarget target;
  final TextStyle style;

  @override
  Widget build(BuildContext context) {
    final at = target.frameTarget.indexOf(TalkTarget.window);
    final brass = target.almost ? AppColors.brassInk : null;
    if (at < 0) return SessionFrameText.plain(target.frameTarget, style: style, frameColor: brass);
    final value = target.said ? target.valueTarget : null;
    return SessionFrameText(
      before: target.frameTarget.substring(0, at),
      after: target.frameTarget.substring(at + TalkTarget.window.length),
      style: style,
      slot: value ?? TalkTarget.window,
      look: target.said ? SlotLook.said : (target.almost ? SlotLook.almost : SlotLook.empty),
      frameColor: brass,
    );
  }
}

/// A ROW OF «СКАЖИ В РАЗГОВОРЕ» (кадры 37-5, 39-1) — the frame in Literata 17 and, under it 2, the lesson's own example
/// in grey 13: «I have pain in my lower back. · У меня болит поясница.» A construction the lesson gave no example for
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
        : (target.exampleNative == null ? target.lessonLine : l.planDotPlain(target.lessonLine, target.lessonNative));
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

/// THE PLATES OVER THE MICROPHONE (кадры 37-7…37-11; наряд CLIENT-FIX-4 §2) — the constructions of the scene the talk is
/// in that are STILL TO SAY, in the server's order, in a row that scrolls sideways, 44 high, 8 apart, its edges fading
/// into the dock. Not yet — paper in a thin ink outline; almost — a brass outline, brass words and a brass dot 6 on the
/// left (37-8e).
///
/// SAID, A PLATE LEAVES: for a moment it lies on a 15 % sage wash with its check (37-8), and after 600 ms it slides out
/// to the left while the others keep their order (37-8b). The last one gone, the row folds and the dock goes down by
/// its 44 and the 14 under it (37-11b). Under «Уменьшение движения» the plate goes after its sage moment without the
/// slide. A tap anywhere on the row opens the sheet of the scene (37-8d).
///
/// It runs the dock's whole width, gutter to gutter: the plates go on under the edge instead of stopping short of it,
/// so it is plain that there are more of them. It brings the 14 under itself, so a folded row takes no room at all.
class TalkConstructionChips extends StatefulWidget {
  const TalkConstructionChips({super.key, required this.targets, required this.onTap});

  /// Every construction of the scene, in the server's order and in every state: the row decides which of them stand.
  final List<TalkTarget> targets;
  final VoidCallback onTap;

  static const double height = 44;

  /// Between the row and what stands under it in the dock.
  static const double gap = 14;

  @override
  State<TalkConstructionChips> createState() => _TalkConstructionChipsState();
}

class _TalkConstructionChipsState extends State<TalkConstructionChips> with TickerProviderStateMixin {
  /// The plates on the row, in order — the ones still to say and the ones leaving.
  late List<TalkTarget> _plates = [for (final t in widget.targets) if (!t.said) t];

  /// A plate just said: its sage moment, then its slide out.
  final Map<String, Timer> _holds = {};
  final Map<String, AnimationController> _leaving = {};

  bool get _reduce => MediaQuery.maybeDisableAnimationsOf(context) ?? false;

  @override
  void didUpdateWidget(TalkConstructionChips old) {
    super.didUpdateWidget(old);
    final standing = {for (final p in _plates) p.key};
    final next = <TalkTarget>[];
    for (final t in widget.targets) {
      if (!t.said) {
        next.add(t);
      } else if (standing.contains(t.key)) {
        // Said by the latest answer while it stood on the row: it stays, in sage, until it has left.
        next.add(t);
        _holds.putIfAbsent(t.key, () => Timer(AppMotion.talkPlateSaidHold, () => _leave(t.key)));
      }
    }
    for (final key in standing.difference({for (final t in next) t.key})) {
      _drop(key);
    }
    _plates = next;
  }

  void _leave(String key) {
    if (!mounted) return;
    if (_reduce) {
      setState(() => _remove(key));
      return;
    }
    final controller = AnimationController(vsync: this, duration: AppMotion.talkPlateLeave);
    _leaving[key] = controller;
    controller.forward().whenComplete(() {
      if (mounted && _leaving[key] == controller) setState(() => _remove(key));
    });
    setState(() {});
  }

  void _remove(String key) {
    _drop(key);
    _plates = [for (final p in _plates) if (p.key != key) p];
  }

  void _drop(String key) {
    _holds.remove(key)?.cancel();
    _leaving.remove(key)?.dispose();
  }

  @override
  void dispose() {
    for (final t in _holds.values) {
      t.cancel();
    }
    for (final c in _leaving.values) {
      c.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final content = _plates.isEmpty
        ? const SizedBox(width: double.infinity)
        : Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [_row(), const SizedBox(height: TalkConstructionChips.gap)],
          );
    // Under «Уменьшение движения» the row simply is or is not there: a size animation of no length re-lays itself out
    // in the middle of its own layout.
    if (_reduce) return content;
    return AnimatedSize(
      duration: AppMotion.talkRowFold,
      curve: AppMotion.sessionEaseOut,
      alignment: Alignment.topCenter,
      child: content,
    );
  }

  Widget _row() => GestureDetector(
    behavior: HitTestBehavior.opaque,
    onTap: () {
      AppHaptics.light();
      widget.onTap();
    },
    child: SizedBox(
      key: const ValueKey('talk-constructions'),
      height: TalkConstructionChips.height,
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
              child: ListView.builder(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: kSessionGutter),
                itemCount: _plates.length,
                itemBuilder: (_, i) => _leavingPlate(_plates[i], last: i == _plates.length - 1),
              ),
            ),
          );
        },
      ),
    ),
  );

  /// A plate with the 8 after it — both fold together when it leaves.
  Widget _leavingPlate(TalkTarget target, {required bool last}) {
    final plate = Padding(
      padding: EdgeInsets.only(right: last ? 0 : 8),
      child: _Chip(target: target),
    );
    final leaving = _leaving[target.key];
    if (leaving == null) return plate;
    final fold = CurvedAnimation(parent: leaving, curve: AppMotion.sessionEaseOut);
    return SizeTransition(
      axis: Axis.horizontal,
      alignment: Alignment.centerRight,
      sizeFactor: ReverseAnimation(fold),
      child: FadeTransition(
        opacity: ReverseAnimation(fold),
        child: SlideTransition(position: Tween(begin: Offset.zero, end: const Offset(-0.35, 0)).animate(fold), child: plate),
      ),
    );
  }
}

class _Chip extends StatelessWidget {
  const _Chip({required this.target});

  final TalkTarget target;

  @override
  Widget build(BuildContext context) => Semantics(
    label: target.chipText,
    child: AnimatedContainer(
      key: ValueKey('talk-construction-${target.sceneId}-${target.ref}'),
      duration: AppMotion.sessionSlotSage,
      height: TalkConstructionChips.height,
      padding: const EdgeInsets.symmetric(horizontal: 14),
      decoration: BoxDecoration(
        color: target.said ? AppColors.sessionSageWash : AppColors.paper,
        borderRadius: BorderRadius.circular(12),
        border: target.said ? null : Border.all(color: target.almost ? AppColors.brassInk : AppColors.markerOutline, width: 1.5),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (target.almost) ...[const TalkAlmostDot(), const SizedBox(width: 8)],
          TalkConstructionLine(target: target, style: AppTextSession.phrase17),
          if (target.said) ...[const SizedBox(width: 8), const TalkSaidCheck(size: 16)],
        ],
      ),
    ),
  );
}

/// The brass dot 6 of a construction said almost (37-8e, 37-8d).
class TalkAlmostDot extends StatelessWidget {
  const TalkAlmostDot({super.key});

  @override
  Widget build(BuildContext context) => const SizedBox(
    key: ValueKey('talk-construction-almost'),
    width: 6,
    height: 6,
    child: DecoratedBox(decoration: BoxDecoration(shape: BoxShape.circle, color: AppColors.brassInk)),
  );
}

/// The sage check in its 20 box — the one mark of a construction that has sounded: 16 on the row and the sheet, 18 on
/// the summary's targets (37-7, 37-8d, 37-12).
class TalkSaidCheck extends StatelessWidget {
  const TalkSaidCheck({super.key, this.size = 18});

  final double size;

  @override
  Widget build(BuildContext context) => SizedBox(
    key: const ValueKey('talk-construction-said'),
    width: 20,
    height: 20,
    child: Center(child: Icon(LucideIcons.check, size: size, color: AppColors.verdictKnown)),
  );
}

/// A CARD OF A CONSTRUCTION — the plates of the summary (37-12, 37-12b) and of the sheet (37-8d): the construction and,
/// under it in grey 13, [note] — what the learner said, what the lesson says, when it comes back. Said, it lies on a
/// 15 % sage wash with its check; almost, it stands in a brass outline with its brass dot; not said, in a thin ink
/// outline.
class TalkConstructionCard extends StatelessWidget {
  const TalkConstructionCard({super.key, required this.target, required this.note, this.checkSize = 18});

  final TalkTarget target;

  /// The grey line under the construction — the caller's words: «ты сказал: …», «вернётся завтра», «из урока: …».
  final String note;
  final double checkSize;

  @override
  Widget build(BuildContext context) => Container(
    key: ValueKey('talk-construction-card-${target.sceneId}-${target.ref}'),
    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
    decoration: BoxDecoration(
      color: target.said ? AppColors.sessionSageWash : null,
      borderRadius: BorderRadius.circular(16),
      border: target.said ? null : Border.all(color: target.almost ? AppColors.brassInk : AppColors.markerOutline, width: 1.5),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          crossAxisAlignment: target.almost ? CrossAxisAlignment.center : CrossAxisAlignment.start,
          children: [
            if (target.almost) ...[const TalkAlmostDot(), const SizedBox(width: 8)],
            Expanded(child: TalkConstructionLine(target: target, style: AppTextSession.phrase17)),
            if (target.said) ...[const SizedBox(width: 10), TalkSaidCheck(size: checkSize)],
          ],
        ),
        const SizedBox(height: 6),
        Text(note, style: AppTextSession.meta),
      ],
    ),
  );
}

/// THE SHEET OF THE SCENE'S CONSTRUCTIONS (кадр 37-8d) — from the bottom over a 40 % scrim: «Конструкции в разговоре»
/// and the cross 24, then EVERY construction of the scene the talk is in, in the scene's order, as the cards of the
/// summary: said — sage with its check and «ты сказал: …»; almost — the brass outline with its dot and «почти — скажи
/// целиком: …»; not said — the thin outline and «из урока: …».
///
/// The sheet follows the talk while it is open ([talk] notifies, [targets] is read again): a construction the learner
/// says while the sheet is up turns sage here too.
Future<void> showTalkConstructionSheet(
  BuildContext context, {
  required Listenable talk,
  required List<TalkTarget> Function() targets,
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
      builder: (context, _) => _ConstructionsSheet(targets: targets()),
    ),
  );
}

class _ConstructionsSheet extends StatelessWidget {
  const _ConstructionsSheet({required this.targets});

  final List<TalkTarget> targets;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final media = MediaQuery.of(context);
    String note(TalkTarget t) => switch (t.state) {
      TalkTargetState.said => l.planTalkYouSaid(t.saidWith(t.valueTarget)),
      TalkTargetState.almost => l.planTalkAlmostLine(t.lessonLine),
      TalkTargetState.none => l.planTalkFromLessonLine(t.lessonLine),
    };
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
                  Expanded(child: Text(l.planTalkConstructions, style: AppTextSession.sheetTitle)),
                  const SizedBox(width: 12),
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
            const SizedBox(height: 14),
            Flexible(
              child: SingleChildScrollView(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    for (final (i, t) in targets.indexed) ...[
                      if (i > 0) const SizedBox(height: 8),
                      TalkConstructionCard(target: t, note: note(t), checkSize: 16),
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
