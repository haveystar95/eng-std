import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

/// СВОДКИ УЖЕ ОТВЕЧЕННОГО в шапке шага (кадры 22-2, 22-3a, 22-3b).
///
/// Канва вычла отсюда кнопку «Изм.»: ВСЯ СТРОКА — тап-цель с шевроном, и она ведёт на свой шаг.
/// Кнопка рядом со строкой делала вид, что строка — не кнопка, и человек жал в неё впустую.
///
/// Строка носит иконку своего смысла слева (12 до текста) и никогда не остаётся без подписи
/// словами — правило постановки иконок серии 22.
///
/// Цель показана ДВУМЯ строками (max-height 41 — ровно две по 15/1.35), и правый нижний угол
/// гаснет заплаткой фона 40 × 20: длинная формулировка уходит на воздух, а не обрывается
/// троеточием посреди слова.
class EntrySummaryRow extends StatelessWidget {
  const EntrySummaryRow({
    super.key,
    required this.icon,
    required this.label,
    required this.value,
    required this.onTap,
    this.twoLines = false,
    this.last = false,
  });

  final PlanIcon icon;

  /// «Цель» / «Язык» / «План» / «Дата» — 13 tertiary в колонке 44.
  final String label;

  /// Ответ человека — ink 15.
  final String value;

  final VoidCallback onTap;

  /// Цель занимает две строки и гаснет; остальные сводки — одна строка.
  final bool twoLines;

  /// Последняя сводка в столбце несёт ещё и нижнюю волосяную линию.
  final bool last;

  static const _hairline = Color(0x1A2E2620); // rgba(46,38,32,.10)

  @override
  Widget build(BuildContext context) {
    final text = Text(
      value,
      maxLines: twoLines ? 2 : 1,
      overflow: TextOverflow.clip,
      style: const TextStyle(
        fontFamily: AppFonts.inter,
        fontSize: 15,
        height: 1.35,
        color: AppColors.ink,
      ),
    );

    return Semantics(
      button: true,
      label: '$label: $value',
      child: InkWell(
        onTap: () {
          AppHaptics.light();
          onTap();
        },
        child: Container(
          constraints: const BoxConstraints(minHeight: 48),
          padding: const EdgeInsets.symmetric(vertical: 6),
          decoration: BoxDecoration(
            border: Border(
              top: const BorderSide(color: _hairline),
              bottom: last ? const BorderSide(color: _hairline) : BorderSide.none,
            ),
          ),
          child: Row(
            children: [
              PlanIconMark(icon: icon),
              const SizedBox(width: 12),
              SizedBox(
                width: 44,
                child: Text(
                  label,
                  style: const TextStyle(
                    fontFamily: AppFonts.inter,
                    fontSize: 13,
                    color: AppColors.tertiary,
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: twoLines
                    ? ClipRect(
                        child: SizedBox(
                          height: 41,
                          child: Stack(
                            children: [
                              Align(alignment: Alignment.topLeft, child: text),
                              // Заплатка фона в правом нижнем углу — гасит хвост второй строки.
                              const Positioned(
                                right: 0,
                                bottom: 0,
                                width: 40,
                                height: 20,
                                child: DecoratedBox(
                                  decoration: BoxDecoration(
                                    gradient: LinearGradient(
                                      colors: [AppColors.groundClear, AppColors.ground],
                                      stops: [0, .7],
                                    ),
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      )
                    : text,
              ),
              const SizedBox(width: 10),
              const Icon(LucideIcons.chevronRight, size: 16, color: AppColors.tertiary),
            ],
          ),
        ),
      ),
    );
  }
}
