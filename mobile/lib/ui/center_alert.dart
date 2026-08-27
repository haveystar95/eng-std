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

/// The same alert with ONE LINE TO TYPE IN — «как назвать коллекцию».
///
/// Same frame, same width, same two actions, because it is the same kind of moment: a small
/// decision taken in the middle of something else. It exists so that asking for a name does not
/// have to reach for Material's `AlertDialog`, which arrives with its own surface, its own radius
/// and its own type — three things this design settles centrally (`tokens.html`), and the one place
/// in the app that still contradicted them.
///
/// Confirm is disabled while the field is empty: a collection with no name is not a shorter name.
class CenterPrompt extends StatefulWidget {
  const CenterPrompt({
    super.key,
    required this.title,
    this.message,
    this.initialValue = '',
    this.hintText,
    required this.confirmLabel,
    required this.cancelLabel, // caller-supplied: lib/ui/ knows no languages
  });

  final String title;
  final String? message;
  final String initialValue;
  final String? hintText;
  final String confirmLabel;
  final String cancelLabel;

  @override
  State<CenterPrompt> createState() => _CenterPromptState();
}

class _CenterPromptState extends State<CenterPrompt> {
  late final TextEditingController _input = TextEditingController(text: widget.initialValue);

  @override
  void dispose() {
    _input.dispose();
    super.dispose();
  }

  void _submit() {
    final value = _input.text.trim();
    if (value.isEmpty) return;
    Navigator.of(context).pop(value);
  }

  @override
  Widget build(BuildContext context) {
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
                      widget.title,
                      textAlign: TextAlign.center,
                      style: AppText.stepTitle.copyWith(fontSize: 18),
                    ),
                    if (widget.message != null) ...[
                      const SizedBox(height: AppSpacing.s8),
                      Text(
                        widget.message!,
                        textAlign: TextAlign.center,
                        style: AppText.translation.copyWith(height: 1.35),
                      ),
                    ],
                    const SizedBox(height: AppSpacing.s16),
                    // A hairline UNDER the text, not a box around it: the alert already is the box,
                    // and a second frame inside it is the shape this design does not draw.
                    TextField(
                      controller: _input,
                      autofocus: true,
                      textAlign: TextAlign.center,
                      textInputAction: TextInputAction.done,
                      onSubmitted: (_) => _submit(),
                      onChanged: (_) => setState(() {}),
                      style: AppText.translation,
                      cursorColor: AppColors.ink,
                      decoration: InputDecoration(
                        isDense: true,
                        hintText: widget.hintText,
                        hintStyle: AppText.translation.copyWith(color: AppColors.tertiary),
                        contentPadding: const EdgeInsets.symmetric(vertical: AppSpacing.s8),
                        enabledBorder: const UnderlineInputBorder(
                          borderSide: BorderSide(color: AppColors.hairline),
                        ),
                        focusedBorder: const UnderlineInputBorder(
                          borderSide: BorderSide(color: AppColors.ink),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              const Divider(height: 1, thickness: 1, color: AppColors.hairline),
              IntrinsicHeight(
                child: Row(
                  children: [
                    Expanded(
                      child: _AlertAction(
                        label: widget.cancelLabel,
                        color: AppColors.secondary,
                        weight: FontWeight.w600,
                        onTap: () => Navigator.of(context).pop(),
                      ),
                    ),
                    const VerticalDivider(width: 1, thickness: 1, color: AppColors.hairline),
                    Expanded(
                      child: _AlertAction(
                        label: widget.confirmLabel,
                        // Unreachable while the field is empty, and it SAYS so rather than
                        // swallowing the tap: a dead button that looks alive reads as a broken app.
                        color: _input.text.trim().isEmpty ? AppColors.tertiary : AppColors.ink,
                        weight: FontWeight.w700,
                        onTap: _input.text.trim().isEmpty ? null : _submit,
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
        child: Text(
          label,
          style: AppText.sheetButton.copyWith(color: color, fontWeight: weight),
        ),
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

/// Показать центральный prompt. Возвращает введённую строку (уже без пробелов по краям)
/// или `null` при отмене / тапе мимо.
Future<String?> showCenterPrompt({
  required BuildContext context,
  required String title,
  String? message,
  String initialValue = '',
  String? hintText,
  required String confirmLabel,
  required String cancelLabel, // caller-supplied: lib/ui/ knows no languages
}) {
  return showDialog<String>(
    context: context,
    barrierColor: AppColors.scrim,
    builder: (context) => CenterPrompt(
      title: title,
      message: message,
      initialValue: initialValue,
      hintText: hintText,
      confirmLabel: confirmLabel,
      cancelLabel: cancelLabel,
    ),
  );
}
