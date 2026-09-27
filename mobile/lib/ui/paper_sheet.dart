import 'package:flutter/material.dart';

import 'package:eng_std/theme/theme.dart';

import 'dock_button.dart';

/// THE SHEET OVER A SCREEN — the session's exit sheet 30-8, and every sheet the account series draws after it (41-3
/// microphone, 42-2 name, 42-3 delete, 42-4 reminders, 43-1 notifications): ground #EFEBE3, top corners 22, a 36 × 4
/// handle, padding 24, the scrim at .4, up in 280 ms on the canvas's ease-out-cubic. One recipe, so the six sheets cannot
/// drift apart.
///
/// [dismissible] false — the sheet cannot be dragged or tapped away (42-3b «Удаляем…»: «шит не закрывается тягой»).
/// The content rises above the keyboard with it (42-2: «при ней шит поднимается над ней целиком»).
Future<T?> showPaperSheet<T>({
  required BuildContext context,
  required WidgetBuilder builder,
  bool dismissible = true,
}) {
  return showModalBottomSheet<T>(
    context: context,
    backgroundColor: AppColors.ground,
    barrierColor: AppColors.windowSheetScrim,
    elevation: 0,
    isScrollControlled: true,
    isDismissible: dismissible,
    enableDrag: dismissible,
    sheetAnimationStyle: const AnimationStyle(duration: AppMotion.sessionExitSheet, curve: AppMotion.windowEaseOutCubic),
    shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(22))),
    builder: (context) => Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
      child: Padding(
        padding: EdgeInsets.fromLTRB(24, 24, 24, 24 + MediaQuery.paddingOf(context).bottom),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Center(
              child: Container(
                width: 36,
                height: 4,
                decoration: BoxDecoration(color: AppColors.markerOutline, borderRadius: BorderRadius.circular(2)),
              ),
            ),
            const SizedBox(height: 24),
            Builder(builder: builder),
          ],
        ),
      ),
    ),
  );
}

/// THE USUAL BODY OF [showPaperSheet]: a title (17/600), a sentence (15/20 grey), an optional note (13/18), then the way
/// out in brass text and the action button under it — 30-8's order, which every account sheet keeps («Позже» /
/// «Разрешить микрофон», «Не сейчас» / «Напоминать», «Отмена» / «Удалить аккаунт»).
class PaperSheetBody extends StatelessWidget {
  const PaperSheetBody({
    super.key,
    required this.title,
    required this.body,
    required this.stayLabel,
    required this.onStay,
    required this.actionLabel,
    required this.onAction,
    this.note,
    this.busy = false,
    this.destructive = false,
    this.busyLabel,
  });

  final String title;
  final String body;
  final String? note;

  /// The brass way out — returns to where the learner was.
  final String stayLabel;
  final VoidCallback? onStay;

  final String actionLabel;
  final VoidCallback? onAction;

  /// The action is on its way: both ways out go quiet (42-3b), the button says [busyLabel] or spins.
  final bool busy;
  final bool destructive;
  final String? busyLabel;

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(title, style: AppTextSession.sheetTitle),
        const SizedBox(height: 14),
        Text(body, style: AppTextSession.body),
        if (note != null) ...[
          const SizedBox(height: 8),
          Text(note!, style: AppTextStart.sheetNote),
        ],
        const SizedBox(height: 32),
        Center(
          child: GestureDetector(
            behavior: HitTestBehavior.opaque,
            onTap: busy ? null : onStay,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 14),
              child: Text(
                stayLabel,
                style: busy ? AppTextSession.sheetStay.copyWith(color: AppColors.tertiary) : AppTextSession.sheetStay,
              ),
            ),
          ),
        ),
        DockButton(
          label: busy && busyLabel != null ? busyLabel! : actionLabel,
          onTap: onAction,
          enabled: !busy,
          busy: busy && busyLabel == null,
          destructive: destructive,
        ),
      ],
    );
  }
}
