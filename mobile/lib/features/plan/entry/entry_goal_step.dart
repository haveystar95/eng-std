import 'dart:async';

import 'package:flutter/material.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import 'entry_scaffold.dart';
import 'entry_state.dart';

/// ШАГ ЦЕЛИ (кадры 22-1, 22-1c) — один вопрос, одно поле, микрофон рядом, три живые истории.
///
/// Канва вычла чипы «Врач · Аренда · Собеседование»: ярлык сужал ответ до категории, а план
/// строится из ситуации. Вместо них — «Так пишут другие»: три истории, тап подставляет текст в
/// поле, и они же работают подсказкой примером.
///
/// ПЛЕЙСХОЛДЕР ПЕЧАТАЕТСЯ и меняется по кругу, но его примеры — ДРУГИЕ, чем в списке ниже: иначе
/// человек читает одно и то же дважды. Фокус или первый символ останавливают цикл навсегда —
/// печатающая машинка за спиной у пишущего человека мешает.
///
/// «Далее» неактивна при пустом поле; счётчика символов нет. Одно слово — валидный ответ (22-1c):
/// подсказка под полем говорит, что даст уточнение, и НЕ блокирует.
class EntryGoalStep extends StatefulWidget {
  const EntryGoalStep({
    super.key,
    required this.controller,
    required this.focus,
    required this.mic,
    required this.micSeconds,
    required this.onMic,
    required this.onStory,
  });

  final TextEditingController controller;
  final FocusNode focus;

  /// Состояние микрофона — четыре, и все четыре живут в поле (22-1).
  final EntryMicState mic;

  /// Секунды записи для «0:07 · говори, я слушаю».
  final int micSeconds;

  final VoidCallback onMic;
  final ValueChanged<String> onStory;

  @override
  State<EntryGoalStep> createState() => _EntryGoalStepState();
}

class _EntryGoalStepState extends State<EntryGoalStep> {
  Timer? _type;

  /// Какой пример печатается и сколько его букв видно.
  int _example = 0;
  int _shown = 0;
  bool _erasing = false;

  /// Цикл остановлен навсегда — человек тронул поле.
  bool _stopped = false;

  @override
  void initState() {
    super.initState();
    widget.controller.addListener(_stopOnInput);
    widget.focus.addListener(_stopOnInput);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    // Снимок не должен зависеть от кадра, на котором его сняли: под «уменьшением движения»
    // плейсхолдер стоит целым первым примером.
    if (MediaQuery.of(context).disableAnimations) {
      _stopped = true;
      _shown = _examples(context).first.length;
    }
    _restart();
  }

  @override
  void dispose() {
    _type?.cancel();
    widget.controller.removeListener(_stopOnInput);
    widget.focus.removeListener(_stopOnInput);
    super.dispose();
  }

  void _stopOnInput() {
    if (_stopped) return;
    if (!widget.focus.hasFocus && widget.controller.text.isEmpty) return;
    _type?.cancel();
    setState(() => _stopped = true);
  }

  void _restart() {
    _type?.cancel();
    if (_stopped) return;
    // 24 зн./с печатает, 40 зн./с стирает, пауза 1.4 с на полном примере (канва 22-1).
    _type = Timer.periodic(Duration(milliseconds: _erasing ? 25 : 42), (_) {
      if (!mounted) return;
      final examples = _examples(context);
      final full = examples[_example % examples.length];
      setState(() {
        if (_erasing) {
          _shown = _shown > 0 ? _shown - 1 : 0;
          if (_shown == 0) {
            _erasing = false;
            _example++;
            _restart();
          }
        } else {
          _shown = _shown < full.length ? _shown + 1 : full.length;
          if (_shown == full.length) {
            _type?.cancel();
            _type = Timer(const Duration(milliseconds: 1400), () {
              if (!mounted || _stopped) return;
              setState(() => _erasing = true);
              _restart();
            });
          }
        }
      });
    });
  }

  /// Примеры ПЛЕЙСХОЛДЕРА — не те, что в списке историй.
  List<String> _examples(BuildContext context) {
    final l = AppLocalizations.of(context);

    return [l.planEntryGoalTyping1, l.planEntryGoalTyping2, l.planEntryGoalTyping3];
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final examples = _examples(context);
    final full = examples[_example % examples.length];
    final placeholder = _stopped ? examples.first : full.substring(0, _shown.clamp(0, full.length));
    final stories = [l.planEntryGoalStory1, l.planEntryGoalStory2, l.planEntryGoalStory3];
    final short = widget.controller.text.trim().isNotEmpty &&
        _isShort(widget.controller.text);

    return EntryContent(
      children: [
        EntryQuestion(l.planEntryGoalTitle),
        const SizedBox(height: 14),
        Text(
          l.planEntryGoalSub,
          style: const TextStyle(
            fontFamily: AppFonts.inter,
            fontSize: 15,
            height: 1.45,
            color: AppColors.secondary,
          ),
        ),
        const SizedBox(height: 14),
        _Field(
          controller: widget.controller,
          focus: widget.focus,
          placeholder: placeholder,
          mic: widget.mic,
          micSeconds: widget.micSeconds,
          onMic: widget.onMic,
        ),
        // 22-1c: одно слово проходит — подсказка объясняет выгоду, а не отчитывает.
        if (short) ...[
          const SizedBox(height: 10),
          Text(
            l.planEntryGoalShortHint,
            style: const TextStyle(
              fontFamily: AppFonts.inter,
              fontSize: 14,
              height: 1.4,
              color: AppColors.tertiary,
            ),
          ),
        ],
        const SizedBox(height: 32),
        Text(
          l.planEntryGoalStoriesTitle.toUpperCase(),
          style: const TextStyle(
            fontFamily: AppFonts.inter,
            fontSize: 11,
            fontWeight: FontWeight.w700,
            letterSpacing: 1.54,
            color: AppColors.tertiary,
          ),
        ),
        const SizedBox(height: 10),
        for (final story in stories) ...[
          if (story != stories.first) const SizedBox(height: 8),
          _Story(text: story, onTap: () => widget.onStory(story)),
        ],
      ],
    );
  }

  /// «Короткий ответ» — меньше восьми слов (то же правило, что у сервера в 22-4d).
  static bool _isShort(String goal) {
    final words = goal.trim().split(RegExp(r'\s+')).where((w) => w.isNotEmpty).length;

    return words > 0 && words < 8;
  }
}

/// ПОЛЕ ЦЕЛИ — светлая бумага radius 20 на ground, min-height 132, и микрофон 44 в правом углу.
class _Field extends StatelessWidget {
  const _Field({
    required this.controller,
    required this.focus,
    required this.placeholder,
    required this.mic,
    required this.micSeconds,
    required this.onMic,
  });

  final TextEditingController controller;
  final FocusNode focus;
  final String placeholder;
  final EntryMicState mic;
  final int micSeconds;
  final VoidCallback onMic;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final listening = mic == EntryMicState.listening;
    final recognising = mic == EntryMicState.recognising;

    // Подпись у микрофона — по состоянию, и она всегда есть: значок не остаётся единственным
    // носителем смысла.
    final hint = switch (mic) {
      EntryMicState.idle => controller.text.trim().isEmpty ? l.planEntryGoalDictate : null,
      EntryMicState.listening => l.planEntryGoalDictateStop,
      EntryMicState.recognising => null,
      EntryMicState.done => l.planEntryGoalDictateEdit,
    };

    return Container(
      constraints: const BoxConstraints(minHeight: 132),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.surfaceRaised,
        borderRadius: BorderRadius.circular(20),
        boxShadow: AppShadows.card,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Expanded(
            child: listening || recognising
                ? _Wave(flat: recognising, seconds: micSeconds)
                : TextField(
                    controller: controller,
                    focusNode: focus,
                    maxLines: null,
                    expands: true,
                    textAlignVertical: TextAlignVertical.top,
                    keyboardType: TextInputType.multiline,
                    textCapitalization: TextCapitalization.sentences,
                    cursorColor: AppColors.ink,
                    style: const TextStyle(
                      fontFamily: AppFonts.inter,
                      fontSize: 17,
                      height: 1.45,
                      color: AppColors.ink,
                    ),
                    decoration: InputDecoration(
                      isDense: true,
                      contentPadding: EdgeInsets.zero,
                      border: InputBorder.none,
                      hintText: placeholder,
                      hintStyle: const TextStyle(
                        fontFamily: AppFonts.inter,
                        fontSize: 17,
                        height: 1.45,
                        color: AppColors.planInactive,
                      ),
                    ),
                  ),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: hint == null
                    ? const SizedBox.shrink()
                    : Text(
                        hint,
                        style: const TextStyle(
                          fontFamily: AppFonts.inter,
                          fontSize: 13,
                          color: AppColors.tertiary,
                        ),
                      ),
              ),
              const SizedBox(width: 10),
              _MicButton(listening: listening, onTap: onMic),
            ],
          ),
        ],
      ),
    );
  }
}

/// Микрофон 44: покой и «распознаю» — кружок ground со значком, «слушаю» — латунный со стопом.
class _MicButton extends StatelessWidget {
  const _MicButton({required this.listening, required this.onTap});

  final bool listening;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Semantics(
    button: true,
    child: InkResponse(
      radius: 26,
      onTap: () {
        AppHaptics.light();
        onTap();
      },
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        width: 44,
        height: 44,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          color: listening ? AppColors.brassInk : AppColors.ground,
        ),
        child: listening
            ? Container(
                width: 14,
                height: 14,
                decoration: BoxDecoration(
                  color: AppColors.paper,
                  borderRadius: BorderRadius.circular(3),
                ),
              )
            : const PlanIconMark(icon: PlanIcon.mic, color: AppColors.ink, size: 22),
      ),
    ),
  );
}

/// ВОЛНА ЗАПИСИ — 22 полоски 3 px латунью, и она же ложится в ряд точек, пока идёт распознавание.
class _Wave extends StatelessWidget {
  const _Wave({required this.flat, required this.seconds});

  final bool flat;
  final int seconds;

  /// Высоты из канвы — не случайные: снимок волны должен быть один и тот же.
  static const List<double> _heights = [
    8, 16, 24, 12, 20, 26, 14, 9, 18, 22, 11, 25, 15, 8, 19, 23, 10, 17, 26, 13, 21, 9,
  ];

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          height: 26,
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              for (final h in _heights) ...[
                if (h != _heights.first) const SizedBox(width: 3),
                AnimatedContainer(
                  duration: const Duration(milliseconds: 180),
                  width: 3,
                  height: flat ? 3 : h,
                  decoration: BoxDecoration(
                    color: flat ? AppColors.ink.withValues(alpha: .25) : AppColors.brassInk,
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
              ],
            ],
          ),
        ),
        const SizedBox(height: 10),
        Text(
          flat
              ? l.planEntryGoalRecognising
              : l.planEntryGoalListening('0:${seconds.toString().padLeft(2, '0')}'),
          style: TextStyle(
            fontFamily: AppFonts.inter,
            fontSize: 13,
            fontWeight: flat ? FontWeight.w400 : FontWeight.w600,
            color: flat ? AppColors.tertiary : AppColors.brassInk,
          ),
        ),
      ],
    );
  }
}

/// ОДНА ИСТОРИЯ «так пишут другие» — кавычка слева, тап подставляет текст в поле.
class _Story extends StatelessWidget {
  const _Story({required this.text, required this.onTap});

  final String text;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Semantics(
    button: true,
    child: Material(
      color: AppColors.surfaceRaised,
      borderRadius: BorderRadius.circular(16),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () {
          AppHaptics.light();
          onTap();
        },
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Padding(
                padding: EdgeInsets.only(top: 2),
                child: PlanIconMark(icon: PlanIcon.quote, color: AppColors.tertiary, size: 16),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  text,
                  style: const TextStyle(
                    fontFamily: AppFonts.inter,
                    fontSize: 15,
                    height: 1.4,
                    color: AppColors.ink,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    ),
  );
}
