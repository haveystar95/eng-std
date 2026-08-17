import 'package:flutter/material.dart';

import 'package:eng_std/theme/theme.dart';

/// Центральный alert (rule 15): необратимые подтверждения — только по центру,
/// ширина 274, radius 22, поверхность alert-surface. Деструктивное действие —
/// текст #9A4430 без заливки (rule 20).
class CenterAlert extends StatelessWidget {
  const CenterAlert({
    super.key,
    required this.title,
    this.message,
    required this.confirmLabel,
    required this.cancelLabel, // caller-supplied: lib/ui/ knows no languages
    this.destructive = true,
    this.onConfirm,
    this.onCancel,
  });

  final String title;
  final String? message;
  final String confirmLabel;
  final String cancelLabel;
  final bool destructive;
  final VoidCallback? onConfirm;
  final VoidCallback? onCancel;

  @override
  Widget build(BuildContext context) {
    final confirmColor = destructive ? AppColors.destructiveText : AppColors.ink;
    return Center(
      child: Material(
        color: Colors.transparent,
        child: Container(
          width: 274,
          decoration: BoxDecoration(
            color: AppColors.alertSurface,
            borderRadius: BorderRadius.circular(AppRadii.alert),
            boxShadow: AppShadows.menu,
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(20, 22, 20, 18),
                child: Column(
                  children: [
                    Text(
                      title,
                      textAlign: TextAlign.center,
                      style: AppText.stepTitle.copyWith(fontSize: 18),
                    ),
                    if (message != null) ...[
                      const SizedBox(height: AppSpacing.s8),
                      Text(
                        message!,
                        textAlign: TextAlign.center,
                        style: AppText.translation.copyWith(height: 1.35),
                      ),
                    ],
                  ],
                ),
              ),
              const Divider(height: 1, thickness: 1, color: AppColors.hairline),
              IntrinsicHeight(
                child: Row(
                  children: [
                    Expanded(
                      child: _AlertAction(
                        label: cancelLabel,
                        color: AppColors.secondary,
                        weight: FontWeight.w600,
                        onTap: onCancel,
                      ),
                    ),
                    const VerticalDivider(width: 1, thickness: 1, color: AppColors.hairline),
                    Expanded(
                      child: _AlertAction(
                        label: confirmLabel,
                        color: confirmColor,
                        weight: FontWeight.w700,
                        onTap: onConfirm,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _AlertAction extends StatelessWidget {
  const _AlertAction({required this.label, required this.color, required this.weight, this.onTap});
  final String label;
  final Color color;
  final FontWeight weight;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      child: Container(
        height: 50,
        alignment: Alignment.center,
        child: Text(label, style: AppText.sheetButton.copyWith(color: color, fontWeight: weight)),
      ),
    );
  }
}

/// Показать центральный alert. Возвращает true при подтверждении, false/`null`
/// при отмене или тапе мимо.
Future<bool?> showCenterAlert({
  required BuildContext context,
  required String title,
  String? message,
  required String confirmLabel,
  required String cancelLabel, // caller-supplied: lib/ui/ knows no languages
  bool destructive = true,
}) {
  return showDialog<bool>(
    context: context,
    barrierColor: AppColors.scrim,
    builder: (context) => CenterAlert(
      title: title,
      message: message,
      confirmLabel: confirmLabel,
      cancelLabel: cancelLabel,
      destructive: destructive,
      onConfirm: () => Navigator.of(context).pop(true),
      onCancel: () => Navigator.of(context).pop(false),
    ),
  );
}
