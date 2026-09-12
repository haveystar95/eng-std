import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';

import 'entry_state.dart';

/// КАРКАС ВХОДА (кадры 22-1 … 22-3b).
///
/// Что канва вычла из шапки: «Отмена», «Новый план» и «Далее». Осталось ДВА элемента — стрелка
/// назад и четыре точки шага; единственное действие шага стоит ВНИЗУ, кнопкой 52, и оттуда не
/// уезжает. Причина в том, что «Далее» в правом верхнем углу и «Далее» внизу — это два обещания
/// на одном экране, и человек ищет то, которое ближе к пальцу.
///
/// Фон входа — ground #EFEBE3, а не бумага: поле и карточки историй стоят НА нём светлой бумагой,
/// и на бумажном фоне они бы пропали.
class EntryScaffold extends StatelessWidget {
  const EntryScaffold({
    super.key,
    required this.step,
    required this.onBack,
    required this.child,
    this.dock,
  });

  /// Шаг, который держит точку. Превью точки не занимает — это уже не вопрос.
  final EntryStep step;

  /// Стрелка назад: на первом шаге закрывает вход, дальше возвращает на шаг назад.
  final VoidCallback onBack;

  final Widget child;

  /// Кнопка шага над безопасной зоной — «Далее» / «Собрать план» / «Начать».
  final Widget? dock;

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: AppColors.ground,
    resizeToAvoidBottomInset: true,
    body: SafeArea(
      bottom: false,
      child: Stack(
        children: [
          Column(
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(20, 4, 20, 0),
                child: Row(
                  children: [
                    Semantics(
                      button: true,
                      child: InkResponse(
                        radius: 22,
                        onTap: () {
                          AppHaptics.light();
                          onBack();
                        },
                        child: const SizedBox(
                          width: 22,
                          height: AppSpacing.minTap,
                          child: Icon(LucideIcons.arrowLeft, size: 22, color: AppColors.ink),
                        ),
                      ),
                    ),
                    const SizedBox(width: 14),
                    Expanded(child: Center(child: _Dots(step: step))),
                    // Пустой слот шириной стрелки — точки стоят по центру экрана, а не по центру
                    // остатка строки.
                    const SizedBox(width: 14),
                    const SizedBox(width: 22),
                  ],
                ),
              ),
              Expanded(child: child),
            ],
          ),
          if (dock != null) Positioned(left: 0, right: 0, bottom: 0, child: dock!),
        ],
      ),
    ),
  );
}

/// ЧЕТЫРЕ ТОЧКИ ШАГА: шаг в руке — пилюля 18 × 6 ink, пройденные — точки 6 ink, будущие — точки
/// 6 rgba(ink,.20). Форма говорит «здесь я», цвет — «это уже сделано».
class _Dots extends StatelessWidget {
  const _Dots({required this.step});

  final EntryStep step;

  @override
  Widget build(BuildContext context) {
    final dots = EntryStep.values.where((s) => s != EntryStep.preview).toList();

    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        for (final s in dots) ...[
          if (s != dots.first) const SizedBox(width: 6),
          AnimatedContainer(
            duration: const Duration(milliseconds: 180),
            width: s == step ? 18 : 6,
            height: 6,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(3),
              color: s.index <= step.index ? AppColors.ink : AppColors.ink.withValues(alpha: .20),
            ),
          ),
        ],
      ],
    );
  }
}

/// ВОПРОС ШАГА — Literata 26/500, −.015em (канва 22-1 … 22-3b).
class EntryQuestion extends StatelessWidget {
  const EntryQuestion(this.text, {super.key});

  final String text;

  @override
  Widget build(BuildContext context) => Text(
    text,
    style: const TextStyle(
      fontFamily: AppFonts.literata,
      fontSize: 26,
      fontWeight: FontWeight.w500,
      letterSpacing: -0.39,
      height: 1.18,
      color: AppColors.ink,
    ),
  );
}

/// Прокручиваемое содержимое шага. Нижний отступ оставляет место закреплённой кнопке.
class EntryContent extends StatelessWidget {
  const EntryContent({super.key, required this.children});

  final List<Widget> children;

  @override
  Widget build(BuildContext context) => ListView(
    keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
    padding: EdgeInsets.fromLTRB(20, 8, 20, 110 + MediaQuery.viewPaddingOf(context).bottom),
    children: children,
  );
}

/// КНОПКА ШАГА — 52 / radius 16 / 17 / 700 под градиентом, которым содержимое уходит под неё.
class EntryDock extends StatelessWidget {
  const EntryDock({super.key, required this.label, required this.enabled, this.onTap});

  final String label;
  final bool enabled;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final on = enabled && onTap != null;

    return DecoratedBox(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: [AppColors.groundClear, AppColors.ground],
          stops: [0, .34],
        ),
      ),
      child: Padding(
        padding: EdgeInsets.fromLTRB(20, 14, 20, 30 + MediaQuery.viewPaddingOf(context).bottom),
        child: Semantics(
          button: true,
          enabled: on,
          label: label,
          child: Material(
            color: on ? AppColors.ink : AppColors.ink.withValues(alpha: .14),
            borderRadius: BorderRadius.circular(16),
            clipBehavior: Clip.antiAlias,
            child: InkWell(
              onTap: on
                  ? () {
                      AppHaptics.light();
                      onTap!();
                    }
                  : null,
              child: SizedBox(
                height: 52,
                child: Center(
                  child: Text(
                    label,
                    style: TextStyle(
                      fontFamily: AppFonts.inter,
                      fontSize: 17,
                      fontWeight: FontWeight.w700,
                      color: on ? AppColors.paper : AppColors.ink.withValues(alpha: .45),
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
