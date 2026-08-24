import 'package:flutter/material.dart';

import 'package:eng_std/theme/theme.dart';

/// Bottom sheet на бумаге с ручкой-полоской, верхние углы radius 28 (rule 08).
/// Редактирование и ввод текста живут здесь (rule 15) — им нужна клавиатура.
///
/// Обёртка над [showModalBottomSheet]: бумажный фон, scrim, safe-area и отступ
/// под клавиатуру. [builder] отдаёт содержимое; ручку рисует сам шит.
Future<T?> showAppBottomSheet<T>({
  required BuildContext context,
  required WidgetBuilder builder,
  bool isScrollControlled = true,
}) {
  return showModalBottomSheet<T>(
    context: context,
    isScrollControlled: isScrollControlled,
    backgroundColor: Colors.transparent,
    barrierColor: AppColors.scrim,
    builder: (context) => AppBottomSheet(child: builder(context)),
  );
}

/// Одна строка списка внутри [AppBottomSheet] — иконка/флаг слева, текст, отметка справа.
///
/// Не `ListTile`: тот рисует фон и всплеск по ближайшему `Material`, а шит — это `Container` с
/// бумажным фоном, поэтому Flutter в debug кричит «background color or ink splashes may be
/// invisible» на каждый такой ряд. Своя строка ещё и попадает в типографику «Слов»: тот же
/// [AppText.translation], та же высота касания 44, никакой material-плотности.
class AppSheetRow extends StatelessWidget {
  const AppSheetRow({
    super.key,
    required this.title,
    this.leading,
    this.trailing,
    this.onTap,
    this.enabled = true,
  });

  final Widget title;
  final Widget? leading, trailing;
  final VoidCallback? onTap;

  /// `false` — строка видна, но неактивна: она сообщает факт, а не предлагает действие.
  final bool enabled;

  @override
  Widget build(BuildContext context) => Semantics(
    button: enabled && onTap != null,
    enabled: enabled,
    child: InkWell(
      onTap: enabled ? onTap : null,
      borderRadius: BorderRadius.circular(AppRadii.field),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: AppSpacing.s8, vertical: AppSpacing.s12),
        child: Row(
          children: [
            if (leading != null) ...[leading!, const SizedBox(width: AppSpacing.s12)],
            Expanded(child: title),
            if (trailing != null) ...[const SizedBox(width: AppSpacing.s8), trailing!],
          ],
        ),
      ),
    ),
  );
}

class AppBottomSheet extends StatelessWidget {
  const AppBottomSheet({super.key, required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    final bottomInset = MediaQuery.of(context).viewInsets.bottom;
    return Container(
      decoration: const BoxDecoration(
        color: AppColors.paper,
        borderRadius: BorderRadius.vertical(top: Radius.circular(AppRadii.sheetTop)),
      ),
      child: SafeArea(
        top: false,
        child: Padding(
          padding: EdgeInsets.only(
            left: AppSpacing.menuPadding,
            right: AppSpacing.menuPadding,
            top: AppSpacing.s8,
            bottom: bottomInset + AppSpacing.s16,
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // ручка
              Center(
                child: Container(
                  width: 40,
                  height: 4,
                  margin: const EdgeInsets.only(bottom: AppSpacing.s12),
                  decoration: BoxDecoration(
                    color: AppColors.track,
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
              ),
              Flexible(child: child),
            ],
          ),
        ),
      ),
    );
  }
}
