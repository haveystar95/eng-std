import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';

/// КАРТОЧКА-ВЫБОР экранов плана — язык и уровень (22-2), длина (22-3a), дата (22-3b, 21-10).
///
/// Слоёная бумага #FCFAF6, radius 18, padding 12/16, min-height 64, тень карточки; выбранная —
/// заливка ink, текст paper, подстрока paper .62 И ГАЛКА 18 справа. Ни контура, ни радио: выбор
/// показан заливкой, а галка добавлена канвой, потому что на тёмной заливке «выбрано» читается
/// как «недоступно», пока рядом нет знака согласия.
///
/// [leading] — кружок 36 слева: монограмма языка («En») или значок уровня. Его тонирует
/// [ChoiceCardMark], чтобы вызывающий не повторял правило для выбранного и невыбранного.
class ChoiceCard extends StatelessWidget {
  const ChoiceCard({
    super.key,
    required this.title,
    required this.selected,
    required this.onTap,
    this.subtitle,
    this.leading,
    this.trailingCheck = true,
  });

  final String title;
  final String? subtitle;
  final bool selected;
  final VoidCallback onTap;

  /// Кружок 36 слева — [ChoiceCardMark]. Null — карточка без него (длина плана в 22-3a).
  final Widget? leading;

  /// Галка у выбранной. False там, где выбор и так единственный смысл строки.
  final bool trailingCheck;

  @override
  Widget build(BuildContext context) {
    final radius = BorderRadius.circular(18);

    return Semantics(
      button: true,
      selected: selected,
      label: title,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 140),
        curve: AppMotion.easeOut,
        constraints: const BoxConstraints(minHeight: 64),
        decoration: BoxDecoration(
          color: selected ? AppColors.ink : AppColors.surfaceRaised,
          borderRadius: radius,
          boxShadow: selected ? null : AppShadows.card,
        ),
        child: Material(
          type: MaterialType.transparency,
          borderRadius: radius,
          clipBehavior: Clip.antiAlias,
          child: InkWell(
            onTap: () {
              AppHaptics.light();
              onTap();
            },
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
              child: Row(
                children: [
                  if (leading != null) ...[leading!, const SizedBox(width: 13)],
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          title,
                          style: TextStyle(
                            fontFamily: AppFonts.inter,
                            fontSize: 17,
                            fontWeight: FontWeight.w600,
                            height: 1.25,
                            color: selected ? AppColors.paper : AppColors.ink,
                          ),
                        ),
                        if (subtitle != null) ...[
                          const SizedBox(height: 3),
                          Text(
                            subtitle!,
                            style: TextStyle(
                              fontFamily: AppFonts.inter,
                              fontSize: 13,
                              height: 1.3,
                              color: selected
                                  ? AppColors.paper.withValues(alpha: .62)
                                  : AppColors.tertiary,
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                  if (selected && trailingCheck) ...[
                    const SizedBox(width: 13),
                    const Icon(LucideIcons.check, size: 18, color: AppColors.paper),
                  ],
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// КРУЖОК 36 В КАРТОЧКЕ-ВЫБОРЕ: подложка paper .14 у выбранной и ground у остальных, содержимое
/// paper и латунь соответственно. Одно место знает это правило — карточек с кружком три сорта.
class ChoiceCardMark extends StatelessWidget {
  const ChoiceCardMark({super.key, required this.selected, this.text, this.child});

  final bool selected;

  /// Монограмма языка — «En» / «Es» / «De», Literata 15/500.
  final String? text;

  /// Значок вместо монограммы (уровень). Тонирует вызывающий — цвет берётся из [markColor].
  final Widget? child;

  /// Цвет содержимого кружка при данном выборе.
  static Color markColor(bool selected) => selected ? AppColors.paper : AppColors.brassInk;

  @override
  Widget build(BuildContext context) => Container(
    width: 36,
    height: 36,
    alignment: Alignment.center,
    decoration: BoxDecoration(
      shape: BoxShape.circle,
      color: selected ? AppColors.paper.withValues(alpha: .14) : AppColors.ground,
    ),
    child: child ??
        Text(
          text ?? '',
          style: TextStyle(
            fontFamily: AppFonts.literata,
            fontSize: 15,
            fontWeight: FontWeight.w500,
            height: 1,
            color: markColor(selected),
          ),
        ),
  );
}
