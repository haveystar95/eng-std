import 'package:flutter/material.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import 'entry_state.dart';

/// THE FRAME EVERY STEP OF THE ENTRY WEARS (кадры 22-1 … 22-4): «Отмена» tertiary 15 on the left,
/// «Новый план» 16.5/700 in the middle, «Далее» 17/700 ink on the right — transparent on the
/// preview, where the one action is «Начать» at the bottom. Under it the four dots of 6 px, then
/// the step's own content on paper with fields of 26 (каркас онбординга 10b–10d).
class EntryScaffold extends StatelessWidget {
  const EntryScaffold({
    super.key,
    required this.step,
    required this.onCancel,
    required this.child,
    this.onNext,
    this.nextEnabled = true,
    this.showNext = true,
    this.dock,
  });

  final EntryStep step;
  final VoidCallback onCancel;
  final VoidCallback? onNext;
  final bool nextEnabled;

  /// False on the preview: the «Далее» slot stays, its text goes transparent (кадр 22-4).
  final bool showNext;
  final Widget child;

  /// What is pinned above the safe area — the preview's hint and «Начать».
  final Widget? dock;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);

    return Scaffold(
      backgroundColor: AppColors.paper,
      resizeToAvoidBottomInset: true,
      body: SafeArea(
        child: Column(
          children: [
            SizedBox(
              height: 44,
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: AppSpacing.s22),
                child: Row(
                  children: [
                    _HeaderAction(
                      label: l.planEntryNavCancel,
                      style: const TextStyle(fontFamily: AppFonts.inter, fontSize: 15, color: AppColors.tertiary),
                      onTap: onCancel,
                      alignment: Alignment.centerLeft,
                    ),
                    Expanded(
                      child: Text(
                        l.planEntryNavTitle,
                        textAlign: TextAlign.center,
                        style: const TextStyle(
                          fontFamily: AppFonts.inter,
                          fontSize: 16.5,
                          fontWeight: FontWeight.w700,
                          color: AppColors.ink,
                        ),
                      ),
                    ),
                    _HeaderAction(
                      label: l.planEntryNext,
                      style: TextStyle(
                        fontFamily: AppFonts.inter,
                        fontSize: 17,
                        fontWeight: FontWeight.w700,
                        color: !showNext
                            ? Colors.transparent
                            : (nextEnabled ? AppColors.ink : AppColors.ink.withValues(alpha: .35)),
                      ),
                      onTap: showNext && nextEnabled ? onNext : null,
                      alignment: Alignment.centerRight,
                    ),
                  ],
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(AppSpacing.s26, 10, AppSpacing.s26, 0),
              child: Align(alignment: Alignment.centerLeft, child: _Dots(step: step)),
            ),
            Expanded(child: child),
            ?dock,
          ],
        ),
      ),
    );
  }
}

class _HeaderAction extends StatelessWidget {
  const _HeaderAction({
    required this.label,
    required this.style,
    required this.alignment,
    this.onTap,
  });

  final String label;
  final TextStyle style;
  final VoidCallback? onTap;
  final Alignment alignment;

  @override
  Widget build(BuildContext context) => Semantics(
    button: onTap != null,
    label: label,
    child: InkWell(
      borderRadius: BorderRadius.circular(12),
      onTap: onTap == null
          ? null
          : () {
              AppHaptics.light();
              onTap!();
            },
      child: Container(
        constraints: const BoxConstraints(minWidth: 64, minHeight: AppSpacing.minTap),
        alignment: alignment,
        child: Text(label, style: style),
      ),
    ),
  );
}

/// Four dots of 6 px: the steps behind and the one in hand are ink, the rest .18.
class _Dots extends StatelessWidget {
  const _Dots({required this.step});

  final EntryStep step;

  @override
  Widget build(BuildContext context) => Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      for (final s in EntryStep.values) ...[
        if (s != EntryStep.goal) const SizedBox(width: 8),
        AnimatedContainer(
          duration: const Duration(milliseconds: 180),
          width: 6,
          height: 6,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: s.index <= step.index ? AppColors.ink : AppColors.ink.withValues(alpha: .18),
          ),
        ),
      ],
    ],
  );
}

/// The question every step opens with — Literata 30/500, −.02em (исключение раздела 2).
class EntryQuestion extends StatelessWidget {
  const EntryQuestion(this.text, {super.key});

  final String text;

  @override
  Widget build(BuildContext context) => Text(
    text,
    style: const TextStyle(
      fontFamily: AppFonts.literata,
      fontSize: 30,
      fontWeight: FontWeight.w500,
      letterSpacing: -0.6,
      height: 1.1,
      color: AppColors.ink,
    ),
  );
}

/// The step's scrolling content with the entry's fields of 26.
class EntryContent extends StatelessWidget {
  const EntryContent({super.key, required this.children});

  final List<Widget> children;

  @override
  Widget build(BuildContext context) => ListView(
    keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
    padding: const EdgeInsets.fromLTRB(AppSpacing.s26, 16, AppSpacing.s26, AppSpacing.s26),
    children: children,
  );
}
