import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../../data/local/cached_image_provider.dart';
import '../../../../data/plan/plan_models.dart';
import '../../../../data/plan/session/session_queue.dart';
import '../../../../ui/scene_circle.dart';
import '../session_texts.dart';
import 'session_bits.dart';

/// SESSION HEADER (canvas 30-2): cross, stage name, progress bar, on the right in words «N words left», below it
/// beads per unit — done ones in sage, the current one in brass, ahead — outlined. No minutes in the header.
class SessionHeader extends StatelessWidget {
  const SessionHeader({
    super.key,
    required this.stageName,
    required this.progress,
    required this.left,
    required this.beads,
    required this.onClose,
  });

  final String stageName;

  /// The share of the stage's cards that are answered.
  final double progress;

  /// «4 words left».
  final String left;
  final List<SessionBead> beads;
  final VoidCallback onClose;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final reduce = MediaQuery.maybeDisableAnimationsOf(context) ?? false;
    return Padding(
      padding: const EdgeInsets.fromLTRB(kSessionGutter - kSessionCloseInset, 4, kSessionGutter, 0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            height: 24,
            child: CustomMultiChildLayout(
              delegate: _HeaderRow(),
              children: [
                LayoutId(id: _HeaderSlot.close, child: SessionCloseButton(onTap: onClose, label: l.planSessionClose)),
                LayoutId(id: _HeaderSlot.stage, child: Text(stageName, style: AppTextSession.headerStage)),
                LayoutId(
                  id: _HeaderSlot.bar,
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(2),
                    child: SizedBox(
                      height: 4,
                      child: LayoutBuilder(
                        builder: (_, c) => Stack(
                          children: [
                            const Positioned.fill(child: ColoredBox(color: AppColors.markerOutline)),
                            TweenAnimationBuilder<double>(
                              tween: Tween(end: progress.clamp(0.0, 1.0)),
                              duration: reduce ? Duration.zero : AppMotion.sessionBarFill + AppMotion.sessionBarFillDelay,
                              curve: reduce
                                  ? Curves.linear
                                  : const Interval(120 / 380, 1, curve: Curves.easeInOut),
                              builder: (_, v, _) => Container(
                                width: c.maxWidth * v,
                                height: 4,
                                decoration: BoxDecoration(
                                  color: AppColors.verdictKnown,
                                  borderRadius: BorderRadius.circular(2),
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                ),
                LayoutId(
                  id: _HeaderSlot.left,
                  child: FittedBox(
                    fit: BoxFit.scaleDown,
                    alignment: Alignment.centerRight,
                    child: Text(left, style: AppTextSession.meta.copyWith(height: 1)),
                  ),
                ),
              ],
            ),
          ),
          // No beads, no row (37-3: the recall sheet's header stands alone over the strip).
          if (beads.isNotEmpty) ...[
            const SizedBox(height: 8),
            Padding(
              padding: const EdgeInsets.only(left: kSessionCloseInset),
              child: SizedBox(
                height: 8,
                child: Wrap(
                  spacing: 4,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  children: [for (final b in beads) _Bead(b)],
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

/// How far the cross's tap area (44) extends beyond its icon (24) to the left.
const double kSessionCloseInset = 10;

enum _HeaderSlot { close, stage, bar, left }

/// THE HEADER ROW: the cross, the stage name and the count at their own widths, 12 between them and the bar, the bar
/// takes what is left. A long name with a long count on a 375 phone («Слушаю и отвечаю» + «последний вопрос», SESSION-1c)
/// is a few pixels wider than the row: then the count scales down into the room left rather than running past the
/// gutter; wherever it fits, the row is the one the canvas draws.
class _HeaderRow extends MultiChildLayoutDelegate {
  static const double _gap = 12;

  @override
  void performLayout(Size size) {
    final loose = BoxConstraints.loose(size);
    final close = layoutChild(_HeaderSlot.close, loose);
    final stage = layoutChild(_HeaderSlot.stage, loose);
    final barStart = close.width + _gap - kSessionCloseInset + stage.width + _gap;
    final left = layoutChild(
      _HeaderSlot.left,
      BoxConstraints(maxWidth: math.max(0, size.width - barStart - _gap), maxHeight: size.height),
    );
    final bar = layoutChild(_HeaderSlot.bar, BoxConstraints.tightFor(width: math.max(0, size.width - barStart - _gap - left.width)));
    double middle(Size child) => (size.height - child.height) / 2;
    positionChild(_HeaderSlot.close, Offset(0, middle(close)));
    positionChild(_HeaderSlot.stage, Offset(close.width + _gap - kSessionCloseInset, middle(stage)));
    positionChild(_HeaderSlot.bar, Offset(barStart, middle(bar)));
    positionChild(_HeaderSlot.left, Offset(size.width - left.width, middle(left)));
  }

  @override
  bool shouldRelayout(_HeaderRow oldDelegate) => false;
}

class _Bead extends StatelessWidget {
  const _Bead(this.state);

  final SessionBead state;

  @override
  Widget build(BuildContext context) {
    final current = state == SessionBead.current;
    return AnimatedContainer(
      duration: AppMotion.sessionBeadFill,
      width: current ? 8 : 6,
      height: current ? 8 : 6,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: switch (state) {
          SessionBead.done => AppColors.verdictKnown,
          SessionBead.current => AppColors.brassInk,
          SessionBead.ahead => Colors.transparent,
        },
        border: state == SessionBead.ahead ? Border.all(color: AppColors.markerOutline, width: 1.5) : null,
      ),
    );
  }
}

/// Cross / back arrow 24 — tap area 44.
class SessionCloseButton extends StatelessWidget {
  const SessionCloseButton({super.key, required this.onTap, required this.label, this.back = false});

  final VoidCallback onTap;
  final String label;

  /// A «back» arrow (stage entry 30-1) instead of the cross.
  final bool back;

  /// Tap area 44 wide (the 24 icon sits on the edge of the screen margin, the tap area extends left by
  /// [kSessionCloseInset]) and 24 high, the height of the header row. The caller places the button with an inset of
  /// `kSessionGutter - kSessionCloseInset`.
  @override
  Widget build(BuildContext context) => Semantics(
    button: true,
    label: label,
    child: GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: onTap,
      child: SizedBox(
        width: 24 + 2 * kSessionCloseInset,
        height: 24,
        child: Center(child: Icon(back ? LucideIcons.arrowLeft : LucideIcons.x, size: 24, color: AppColors.ink)),
      ),
    ),
  );
}

/// SCENE STRIP (canvas 30-2b, 37-x): scene photo 32 and «Приём у врача · врач» (native; the role in the nominative,
/// as the server gave it, with its first letter lowered — the role CONTINUES the line, it does not start one; headings
/// that begin with the role keep the server's capital). Not tappable — it is a reminder.
///
/// THE CIRCLE ON THE RIGHT IS GONE (work order FIX-2, item 6). It held the LEARNER'S own avatar, which on the
/// owner's account is Google's generated one — a red disc with «О» — standing over a strip that is about the SCENE
/// and the partner, and answering a question nobody asked: whose session this is. 30-2b puts the partner's face
/// there, and the contract has no partner photo to put; the partner's role stays where the frame also keeps it, in
/// the line itself («· с врачом»).
class SessionSceneStrip extends StatelessWidget {
  const SessionSceneStrip({super.key, required this.scene, this.title, this.role});

  final PlanScene? scene;

  /// The scene's name and the role, when a document names them itself rather than the plan's scene — the talk's
  /// `scene` and `partner`, which move from scene to scene on the rehearsal (наряд CLIENT-CONV-1b). The photo stays
  /// [scene]'s.
  final String? title;
  final String? role;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final s = scene;
    final role = SessionTexts.roleInline((this.role ?? s?.partnerRoleNative)?.trim() ?? '');
    final title = (this.title ?? s?.titleNative)?.trim() ?? '';
    final line = role.isEmpty ? title : (title.isEmpty ? role : l.planSessionSceneLine(title, role));
    final dpr = MediaQuery.maybeDevicePixelRatioOf(context) ?? 2;
    final photo = s?.image;
    return Padding(
      padding: const EdgeInsets.fromLTRB(kSessionGutter, 14, kSessionGutter, 0),
      // The line wraps in full — nothing above a session card is cut; the canvas height 48 is only the minimum.
      child: ConstrainedBox(
        constraints: const BoxConstraints(minHeight: 48),
        child: Row(
          children: [
            SceneCircle(
              image: photo == null ? null : CachedNetworkImage(photo.urlFor(32, dpr)),
              tone: AppColors.wireTone(photo?.tone),
              size: 32,
            ),
            const SizedBox(width: 12),
            Expanded(child: Text(line, style: AppTextSession.sceneLine)),
          ],
        ),
      ),
    );
  }
}

/// «NO CONNECTION» BANNER — the answer is waiting to be sent; the next card will not open until it has gone out.
class SessionOfflineBanner extends StatelessWidget {
  const SessionOfflineBanner({super.key});

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(kSessionGutter, 10, kSessionGutter, 0),
    child: Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      decoration: BoxDecoration(color: AppColors.paper, borderRadius: BorderRadius.circular(12), boxShadow: kSessionSheetShadow),
      child: Row(
        children: [
          const Icon(LucideIcons.wifiOff, size: 16, color: AppColors.secondary),
          const SizedBox(width: 10),
          Expanded(child: Text(AppLocalizations.of(context).planSessionOffline, style: AppTextSession.meta)),
        ],
      ),
    ),
  );
}
