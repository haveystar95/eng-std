import 'package:flutter/material.dart';

import 'package:eng_std/theme/theme.dart';

/// КАРТОЧКА-ВЫБОР экранов плана — уровень (кадр 22-2), дата (22-3b, 21-10).
///
/// Слоёная бумага #FCFAF5, radius 18, padding 14/18, тень карточки; выбранная — заливка ink,
/// текст paper, подстрока paper .7. Ни контура, ни радио, ни галочки: «выбор показан заливкой
/// ink, как ценовая карточка 4ж» (записка). Заголовок 16/700, подстрока 14 tertiary.
class ChoiceCard extends StatelessWidget {
  const ChoiceCard({
    super.key,
    required this.title,
    required this.selected,
    required this.onTap,
    this.subtitle,
  });

  final String title;
  final String? subtitle;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final radius = BorderRadius.circular(18);

    return Semantics(
      button: true,
      selected: selected,
      label: title,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 160),
        curve: AppMotion.easeOut,
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
              padding: const EdgeInsets.fromLTRB(18, 14, 18, 14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: TextStyle(
                      fontFamily: AppFonts.inter,
                      fontSize: 16,
                      fontWeight: FontWeight.w700,
                      color: selected ? AppColors.paper : AppColors.ink,
                    ),
                  ),
                  if (subtitle != null) ...[
                    const SizedBox(height: 3),
                    Text(
                      subtitle!,
                      style: TextStyle(
                        fontFamily: AppFonts.inter,
                        fontSize: 14,
                        height: 1.35,
                        color: selected ? AppColors.paper.withValues(alpha: .7) : AppColors.tertiary,
                      ),
                    ),
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
