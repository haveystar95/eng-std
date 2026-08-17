import 'package:flutter/material.dart';

import 'package:eng_std/theme/theme.dart';

/// Одна строка плавающего контекстного меню (§4в).
class ContextMenuAction {
  const ContextMenuAction({
    required this.icon,
    required this.label,
    this.destructive = false,
    required this.onSelected,
  });

  final IconData icon;
  final String label;

  /// Деструктив — иконка и текст #9A4430, всегда последней строкой.
  final bool destructive;
  final VoidCallback onSelected;
}

/// Плавающее контекстное меню (§4в). Custom overlay (не CupertinoContextMenu):
/// метрики карточки, тап-открытие по кнопке и отсутствие кнопки «Закрыть» —
/// точны по токен-листу. Rule 15: действия над объектом. Rule 18: не дублируй
/// действия, уже видимые на элементе (напр. озвучку).
///
/// Закрытие — тап мимо (scrim .46). Открытие/закрытие — scale .94→1 / opacity.
/// Раскрывается от точки вызова [anchorContext].
Future<void> showFloatingContextMenu({
  required BuildContext context,
  required BuildContext anchorContext,
  required List<ContextMenuAction> actions,
  required String barrierLabel, // a11y dismiss label; caller-supplied (lib/ui/ knows no languages)
}) {
  final box = anchorContext.findRenderObject() as RenderBox?;
  final overlay = Overlay.of(context).context.findRenderObject() as RenderBox?;
  Rect anchor;
  if (box != null && overlay != null && box.hasSize) {
    final topLeft = box.localToGlobal(Offset.zero, ancestor: overlay);
    anchor = topLeft & box.size;
  } else {
    anchor = Rect.zero;
  }

  // деструктив всегда в конце, порядок внутри групп сохраняется
  final ordered = [
    ...actions.where((a) => !a.destructive),
    ...actions.where((a) => a.destructive),
  ];

  return showGeneralDialog<void>(
    context: context,
    barrierDismissible: true,
    barrierLabel: barrierLabel,
    barrierColor: AppColors.menuScrim,
    transitionDuration: AppMotion.menuOpen,
    pageBuilder: (context, _, _) => _MenuLayout(anchor: anchor, actions: ordered),
    transitionBuilder: (context, anim, _, child) {
      final curved = CurvedAnimation(
        parent: anim,
        curve: AppMotion.easeOut,
        reverseCurve: AppMotion.easeIn,
      );
      return FadeTransition(
        opacity: curved,
        child: ScaleTransition(
          scale: Tween(begin: 0.94, end: 1.0).animate(curved),
          alignment: Alignment.topCenter,
          child: child,
        ),
      );
    },
  );
}

class _MenuLayout extends StatelessWidget {
  const _MenuLayout({required this.anchor, required this.actions});

  final Rect anchor;
  final List<ContextMenuAction> actions;

  static const double _width = 236;
  static const double _rowH = 48;
  static const double _margin = 8;
  static const double _gap = 6; // зазор от якоря

  @override
  Widget build(BuildContext context) {
    final size = MediaQuery.of(context).size;
    final estHeight = actions.length * _rowH + 12;

    // по горизонтали — прижать к левому краю якоря, но не вылезать за поля
    double left = anchor.left;
    left = left.clamp(_margin, size.width - _width - _margin);

    // по вертикали — под якорь; если не влезает, над якорем
    final belowTop = anchor.bottom + _gap;
    final fitsBelow = belowTop + estHeight <= size.height - _margin;
    final top = fitsBelow ? belowTop : (anchor.top - _gap - estHeight).clamp(_margin, size.height);

    return Stack(
      children: [
        Positioned(
          left: left,
          top: top,
          child: _MenuCard(actions: actions),
        ),
      ],
    );
  }
}

class _MenuCard extends StatelessWidget {
  const _MenuCard({required this.actions});
  final List<ContextMenuAction> actions;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: Container(
        width: _MenuLayout._width,
        padding: const EdgeInsets.symmetric(vertical: 6),
        decoration: BoxDecoration(
          color: AppColors.surfaceRaised,
          borderRadius: BorderRadius.circular(AppRadii.menu),
          boxShadow: AppShadows.menu,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            for (var i = 0; i < actions.length; i++) ...[
              if (i > 0)
                const Padding(
                  padding: EdgeInsets.only(left: 46), // от текста (icon17+gap13+pad16)
                  child: Divider(height: 1, thickness: 1, color: AppColors.dividerFaint),
                ),
              _MenuRow(action: actions[i]),
            ],
          ],
        ),
      ),
    );
  }
}

class _MenuRow extends StatelessWidget {
  const _MenuRow({required this.action});
  final ContextMenuAction action;

  @override
  Widget build(BuildContext context) {
    final color = action.destructive ? AppColors.destructiveText : AppColors.ink;
    return InkWell(
      onTap: () {
        Navigator.of(context).pop();
        action.onSelected();
      },
      child: SizedBox(
        height: _MenuLayout._rowH,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16),
          child: Row(
            children: [
              Icon(action.icon, size: 17, color: color),
              const SizedBox(width: 13),
              Expanded(
                child: Text(
                  action.label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: AppText.sheetButton.copyWith(fontWeight: FontWeight.w600, color: color),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
