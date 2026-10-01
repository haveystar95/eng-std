import 'dart:math' as math;

import 'package:flutter/material.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/native_text.dart';

import '../../../../data/plan/day_window.dart';
import 'window_bits.dart';
import 'window_texts.dart';

/// КАРТОЧКА СЛОВА — шит 23-0e поверх окна дня (DAY-UI-3).
///
/// Шит снизу на 86 % экрана, бумага, скругление сверху 22, тень `0 −12 40 .18`, окно под ним затемнено до
/// 40 % (не скрыто). Поля 24: ручка 36 × 4; фото 16:9 (тон → фото); слово Literata 30 и «прослушать» 44;
/// чтение; перевод; определение; «В разговоре» — реплика дня, где слово звучит, со словом латунью и своим
/// «прослушать»; строка состояния словами; одна текстовая кнопка «Закрыть». Закрывается и тягой вниз.
/// Всё, что шит пишет, пришло с сервера: место слова в реплике — `usage.offset/length`, день возврата —
/// `returns_day`. Подъём — 320 мс, фон — 320 мс, закрытие — 260 мс, ease-out-cubic.
Future<void> showWindowWordSheet(BuildContext context, {required WindowWord word, required WindowListen onListen}) {
  final reduce = MediaQuery.of(context).disableAnimations;

  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    backgroundColor: Colors.transparent,
    elevation: 0,
    barrierColor: AppColors.windowSheetScrim,
    sheetAnimationStyle: AnimationStyle(
      duration: reduce ? Duration.zero : AppMotion.windowSheetRise,
      reverseDuration: reduce ? Duration.zero : AppMotion.windowSheetClose,
      curve: AppMotion.windowEaseOutCubic,
      reverseCurve: AppMotion.windowEaseOutCubic,
    ),
    builder: (context) => WindowWordSheet(word: word, onListen: onListen),
  );
}

class WindowWordSheet extends StatelessWidget {
  const WindowWordSheet({super.key, required this.word, required this.onListen});

  final WindowWord word;
  final WindowListen onListen;

  /// Высота шита — доля экрана.
  static const share = .86;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final media = MediaQuery.of(context);
    final usage = word.usage;

    return Container(
      height: media.size.height * share,
      decoration: const BoxDecoration(
        color: AppColors.paper,
        borderRadius: BorderRadius.vertical(top: Radius.circular(22)),
        boxShadow: [BoxShadow(color: AppColors.windowSheetShadow, offset: Offset(0, -12), blurRadius: 40)],
      ),
      // Поля 24 — и не меньше домашней полосы телефона: «Закрыть» не встаёт под неё.
      padding: EdgeInsets.fromLTRB(24, 0, 24, math.max(24, media.padding.bottom)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const SizedBox(height: 8),
          Center(
            child: Container(
              width: 36,
              height: 4,
              decoration: BoxDecoration(color: AppColors.markerOutline, borderRadius: BorderRadius.circular(2)),
            ),
          ),
          Expanded(
            child: SingleChildScrollView(
              physics: const ClampingScrollPhysics(),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const SizedBox(height: 16),
                  ClipRRect(
                    borderRadius: BorderRadius.circular(16),
                    child: AspectRatio(
                      aspectRatio: 16 / 9,
                      child: WindowPhoto(url: word.image?.url, tone: AppColors.wireTone(word.imageTone) ?? AppColors.photoSlot),
                    ),
                  ),
                  const SizedBox(height: 20),
                  Row(
                    children: [
                      Expanded(child: Text(word.term, style: AppTextWindow.sheetWord)),
                      const SizedBox(width: 12),
                      WindowListenButton(size: 44, onTap: () => onListen(word.term, word.audioUrl)),
                    ],
                  ),
                  if (word.pronunciation case final reading?) ...[
                    const SizedBox(height: 4),
                    Text(reading, style: AppTextWindow.sheetReading),
                  ],
                  const SizedBox(height: 4),
                  Text(context.nativeText(word.translation), style: AppTextWindow.sheetTranslation),
                  if (word.definition case final definition?) ...[
                    const SizedBox(height: 12),
                    Text(definition, style: AppTextWindow.translation),
                  ],
                  if (usage != null) ...[
                    const SizedBox(height: 32),
                    Text(l.planWindowSheetTalk.toUpperCase(), style: AppTextWindow.tabBrow),
                    const SizedBox(height: 14),
                    Container(
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(color: AppColors.ground, borderRadius: BorderRadius.circular(16)),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text.rich(WindowUsageLine.span(usage), style: AppTextWindow.target),
                                const SizedBox(height: 4),
                                Text(context.nativeText(usage.translation), style: AppTextWindow.translation),
                              ],
                            ),
                          ),
                          const SizedBox(width: 12),
                          WindowListenButton(onTap: () => onListen(usage.text, usage.audioUrl)),
                        ],
                      ),
                    ),
                  ],
                  const SizedBox(height: 24),
                  Row(
                    children: [
                      _StateMark(state: word.state),
                      const SizedBox(width: 12),
                      Expanded(child: Text(WindowTexts.sheetState(l, word), style: AppTextWindow.sheetState)),
                    ],
                  ),
                ],
              ),
            ),
          ),
          Semantics(
            button: true,
            child: GestureDetector(
              behavior: HitTestBehavior.opaque,
              onTap: () => Navigator.of(context).maybePop(),
              child: SizedBox(
                height: 44,
                child: Center(child: Text(l.planWindowSheetClose, style: AppTextWindow.sheetClose)),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// РЕПЛИКА «В РАЗГОВОРЕ» СО СЛОВОМ ЛАТУНЬЮ — по месту, которое назвал сервер: `offset` и `length` в
/// символах (кодовых точках), а строка Dart — в кодовых единицах UTF-16, поэтому режется по рунам. Место
/// вне строки — реплика без подсветки, а не падение.
abstract final class WindowUsageLine {
  static TextSpan span(WindowUsage usage) {
    final runes = usage.text.runes.toList();
    final start = usage.offset;
    final end = usage.offset + usage.length;
    if (start < 0 || usage.length <= 0 || end > runes.length) return TextSpan(text: usage.text);

    return TextSpan(
      children: [
        TextSpan(text: String.fromCharCodes(runes.sublist(0, start))),
        TextSpan(text: String.fromCharCodes(runes.sublist(start, end)), style: const TextStyle(color: AppColors.brassInk)),
        TextSpan(text: String.fromCharCodes(runes.sublist(end))),
      ],
    );
  }
}

/// Кружок состояния 20: контур — не начато; шалфей с галкой — пройдено (и у слова, которое вернётся).
class _StateMark extends StatelessWidget {
  const _StateMark({required this.state});

  final WindowUnitState state;

  @override
  Widget build(BuildContext context) => state == WindowUnitState.pending
      ? Container(
          width: 20,
          height: 20,
          decoration: BoxDecoration(shape: BoxShape.circle, border: Border.all(color: AppColors.markerOutline, width: 1.5)),
        )
      : const WindowCheck(size: 20, glyph: 11);
}
