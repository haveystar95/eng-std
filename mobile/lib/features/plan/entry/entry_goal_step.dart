import 'dart:async';

import 'package:flutter/material.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import 'dictation_wave.dart';
import 'entry_scaffold.dart';
import 'entry_state.dart';
import 'goal_dictation.dart';

/// ШАГ ЦЕЛИ (кадры 22-1, 22-1c) — один вопрос, одно поле, микрофон рядом, три живые истории.
///
/// Канва вычла чипы «Врач · Аренда · Собеседование»: ярлык сужал ответ до категории, а план
/// строится из ситуации. Вместо них — «Так пишут другие»: три истории, тап подставляет текст в
/// поле, и они же работают подсказкой примером.
///
/// WHO YOU ARE AND WHAT MATTERS (наряд CLIENT-22-1 §1). The server builds the plan and the lessons from this text:
/// whatever the learner says about themselves becomes their details in the fillers («I worked at ___» → «a
/// restaurant»); with none, the model invents a job for them. So the step suggests, and never demands:
/// - the COMPANION under the field, always there — «Кто ты и что важно…»; a goal of fewer than four words (typed, or
///   left by the dictation) turns it to «Добавь пару слов о себе…» and back at four; «Далее» stays live either way;
/// - the EXAMPLES in the empty field — four goals with details, one at a time, a crossfade every 4 s. They turn only
///   while the field is empty and nobody is dictating: a focus in the field does not stop them, the first character
///   does (and an emptied field lets them go on).
/// Neither is sent anywhere: the goal leaves as it was entered ([PlanEntryScreen]).
class EntryGoalStep extends StatefulWidget {
  const EntryGoalStep({
    super.key,
    required this.controller,
    required this.focus,
    required this.dictation,
    required this.onMic,
    required this.onStory,
  });

  final TextEditingController controller;
  final FocusNode focus;

  /// Печать голосом — состояние микрофона, громкость, таймер, услышанное (22-1, 22-1c).
  final GoalDictation dictation;

  final VoidCallback onMic;
  final ValueChanged<String> onStory;

  /// «Короткая цель» (22-1c, CLIENT-22-1 §1c): fewer than four words. A word is a run with a letter or a digit in it —
  /// a lone dash is none.
  static bool isShort(String goal) {
    final words = goal.split(RegExp(r'\s+')).where((w) => w.contains(RegExp(r'[\p{L}\p{N}]', unicode: true))).length;

    return words > 0 && words < 4;
  }

  @override
  State<EntryGoalStep> createState() => _EntryGoalStepState();
}

class _EntryGoalStepState extends State<EntryGoalStep> {
  /// Which of the four examples the empty field shows.
  int _example = 0;
  Timer? _turn;

  /// The companion's text: the short-goal one or not. Read off the field after typing and when a dictation closes —
  /// not while it runs, or the line would flicker with every heard word.
  late bool _short = EntryGoalStep.isShort(widget.controller.text);

  @override
  void initState() {
    super.initState();
    widget.controller.addListener(_changed);
    widget.dictation.addListener(_changed);
    _rotate();
  }

  @override
  void dispose() {
    _turn?.cancel();
    widget.controller.removeListener(_changed);
    widget.dictation.removeListener(_changed);
    super.dispose();
  }

  void _changed() {
    if (!mounted) return;
    final short = widget.dictation.listening ? _short : EntryGoalStep.isShort(widget.controller.text);
    if (short != _short) setState(() => _short = short);
    _rotate();
  }

  /// The examples turn while the field is empty and nobody dictates — and stand still otherwise.
  void _rotate() {
    final turning = widget.controller.text.isEmpty && !widget.dictation.listening;
    if (turning && _turn == null) {
      _turn = Timer.periodic(AppMotion.goalExampleEvery, (_) {
        if (mounted) setState(() => _example = (_example + 1) % _GoalExamples.count);
      });
    } else if (!turning) {
      _turn?.cancel();
      _turn = null;
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final stories = [l.planEntryGoalStory1, l.planEntryGoalStory2, l.planEntryGoalStory3];

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
        ListenableBuilder(
          listenable: widget.dictation,
          builder: (context, _) => Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // Волна над полем — только пока человек говорит; гаснет за 160 мс (22-1c).
              AnimatedSize(
                duration: const Duration(milliseconds: 160),
                alignment: Alignment.topCenter,
                child: widget.dictation.listening
                    ? Padding(padding: const EdgeInsets.only(bottom: 10), child: DictationWave(level: widget.dictation.level))
                    : const SizedBox(width: double.infinity),
              ),
              _Field(controller: widget.controller, focus: widget.focus, example: _example, dictation: widget.dictation, onMic: widget.onMic),
            ],
          ),
        ),
        // The companion (CLIENT-22-1 §1a, §1c): always under the field; a short goal turns its text, never the button.
        const SizedBox(height: 10),
        AnimatedSwitcher(
          duration: AppMotion.goalCompanionFade,
          layoutBuilder: (current, previous) => Stack(
            alignment: Alignment.topLeft,
            children: [...previous, ?current],
          ),
          child: Text(
            _short ? l.planEntryGoalCompanionShort : l.planEntryGoalCompanion,
            key: ValueKey(_short ? 'goal-companion-short' : 'goal-companion'),
            style: const TextStyle(
              fontFamily: AppFonts.inter,
              fontSize: 13,
              height: 1.4,
              color: AppColors.tertiary,
            ),
          ),
        ),
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
}

/// THE FOUR EXAMPLES of the empty field (CLIENT-22-1 §1b) — all four laid out on top of each other, so the field is as
/// tall as the longest and does not jump when they change; the shown one is opaque, the rest fade out.
class _GoalExamples extends StatelessWidget {
  const _GoalExamples({required this.shown, required this.style});

  static const count = 4;

  final int shown;
  final TextStyle style;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final examples = [l.planEntryGoalExample1, l.planEntryGoalExample2, l.planEntryGoalExample3, l.planEntryGoalExample4];

    return Stack(
      children: [
        for (var i = 0; i < examples.length; i++)
          AnimatedOpacity(
            key: ValueKey('goal-example-${i + 1}'),
            opacity: i == shown % count ? 1 : 0,
            duration: AppMotion.goalExampleFade,
            child: Text(examples[i], style: style),
          ),
      ],
    );
  }
}

/// ПОЛЕ ЦЕЛИ — светлая бумага radius 20, min-height 132, и микрофон 44 в правом углу.
///
/// Пока человек говорит, вместо поля ввода стоит печатающийся текст ([DictatedText]): то, что было
/// до записи, и сказанное, последнее слово серым; внизу — «0:07 · говори, я слушаю» и латунный
/// микрофон со стопом. Запись закрылась — текст остаётся в обычном поле, курсор в конце.
class _Field extends StatelessWidget {
  const _Field({
    required this.controller,
    required this.focus,
    required this.example,
    required this.dictation,
    required this.onMic,
  });

  final TextEditingController controller;
  final FocusNode focus;
  /// Which example the empty field shows.
  final int example;
  final GoalDictation dictation;
  final VoidCallback onMic;

  static const _text = TextStyle(fontFamily: AppFonts.inter, fontSize: 17, height: 1.45, color: AppColors.ink);

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final listening = dictation.listening;
    final seconds = dictation.seconds;

    // Подпись у микрофона — по состоянию, и она всегда есть: значок не остаётся единственным
    // носителем смысла.
    final hint = switch (dictation.state) {
      EntryMicState.idle => controller.text.trim().isEmpty ? l.planEntryGoalDictate : null,
      EntryMicState.listening => l.planEntryGoalListening('${seconds ~/ 60}:${(seconds % 60).toString().padLeft(2, '0')}'),
      EntryMicState.done => l.planEntryGoalDictateEdit,
    };

    return Container(
      constraints: const BoxConstraints(minHeight: 132),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(color: AppColors.surfaceRaised, borderRadius: BorderRadius.circular(20), boxShadow: AppShadows.card),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          ConstrainedBox(
            constraints: const BoxConstraints(minHeight: 72),
            child: listening
                ? DictatedText(base: dictation.base, heard: dictation.heard, style: _text)
                : TextField(
                    controller: controller,
                    focusNode: focus,
                    maxLines: null,
                    textAlignVertical: TextAlignVertical.top,
                    keyboardType: TextInputType.multiline,
                    textCapitalization: TextCapitalization.sentences,
                    cursorColor: AppColors.ink,
                    style: _text,
                    decoration: InputDecoration(
                      isDense: true,
                      contentPadding: EdgeInsets.zero,
                      border: InputBorder.none,
                      hint: _GoalExamples(shown: example, style: _text.copyWith(color: AppColors.planInactive)),
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
                        style: TextStyle(
                          fontFamily: AppFonts.inter,
                          fontSize: 13,
                          fontWeight: listening ? FontWeight.w600 : FontWeight.w400,
                          color: listening ? AppColors.brassInk : AppColors.tertiary,
                          fontFeatures: const [FontFeature.tabularFigures()],
                        ),
                      ),
              ),
              const SizedBox(width: 10),
              _MicButton(key: const ValueKey('goal-mic'), listening: listening, onTap: onMic),
            ],
          ),
        ],
      ),
    );
  }
}

/// Микрофон 44: в покое — кружок ground со значком, пока говорит — латунный со стопом.
class _MicButton extends StatelessWidget {
  const _MicButton({super.key, required this.listening, required this.onTap});

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
        decoration: BoxDecoration(shape: BoxShape.circle, color: listening ? AppColors.brassInk : AppColors.ground),
        child: listening
            ? Container(
                width: 14,
                height: 14,
                decoration: BoxDecoration(color: AppColors.paper, borderRadius: BorderRadius.circular(3)),
              )
            : const PlanIconMark(icon: PlanIcon.mic, color: AppColors.ink, size: 22),
      ),
    ),
  );
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
