import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../data/line_audio.dart';
import '../../../data/plan/day_providers.dart';
import '../../../data/plan/day_window.dart';
import '../../../data/plan/plan_models.dart';
import '../../../data/providers.dart';
import '../plan_providers.dart';
import '../plan_tab_parts.dart' show PlanLoadFailedCard;
import 'day_session_screen.dart';
import 'day_voice.dart';
import 'window/window_action_bar.dart';
import 'window/window_scroll.dart';
import 'window/window_texts.dart';

/// ОКНО ДНЯ (кадры 23-0a…0d, наряд DAY-UI-2) — один экран, одна лента: плита на фото дня, вкладки
/// программы и одна кнопка внизу по `allowed_action` сервера — «Начать», «Продолжить», «Ещё раз».
///
/// Всё, что окно пишет, посчитано сервером (`PlanDayRoom.window`); окно складывает слова. Запертого
/// дня здесь нет: таб его не открывает, а пришедший ответ со словом, которого у окна нет, — честная
/// ошибка загрузки, а не нарисованное состояние.
class DayWindowScreen extends ConsumerStatefulWidget {
  const DayWindowScreen({super.key, required this.plan, required this.number});

  final Plan plan;
  final int number;

  @override
  ConsumerState<DayWindowScreen> createState() => _DayWindowScreenState();
}

class _DayWindowScreenState extends ConsumerState<DayWindowScreen> {
  /// План окна — тот, с которым пришли, или он же уже запущенный (см. [_act]).
  late Plan _plan = widget.plan;
  DayVoice? _voice;

  /// Этапы, закрытые с прошлого ответа сервера, — их галки появятся `om-check-pop`.
  Set<PlanStage> _popped = const {};

  DayAddress get _address => (planId: widget.plan.id, number: widget.number);

  @override
  void dispose() {
    unawaited(_voice?.release());
    super.dispose();
  }

  DayVoice get _voiceNow => _voice ??= DayVoice(lines: ref.read(lineAudioCacheProvider), targetLang: _plan.targetLang)..warmUp();

  /// Голос реплик собеседника — докачка файлов сервера, как только окно их узнало. Фразы ученика
  /// сервер не озвучивает: премиум-голос — реплики роли, фразу читает телефон.
  void _prepareVoice(DayWindow window) {
    final lines = <LineAudioRef>[
      for (final pair in window.program.dialogue)
        if (pair.partner?.audioUrl case final url?) (text: pair.partner!.text, url: url),
    ];
    if (lines.isNotEmpty) unawaited(_voiceNow.preload(lines));
  }

  void _listen(String text, {required bool partner}) {
    final voice = _voiceNow;
    unawaited(partner ? voice.speakLine(text) : voice.speak(text));
  }

  Future<void> _act(WindowAction action, PlanDayRoom room) async {
    var current = room;
    // НЕНАЧАТЫЙ ПЛАН (13.09): «Начать» дня 1 плана, который собран и не запущен, — это «Начать»
    // плана. День такого плана сервер не откроет (409 `plan_state`), поэтому сначала `POST /start`.
    if (action == WindowAction.start && _plan.status == PlanStatus.ready) {
      try {
        _plan = await ref.read(planTabProvider.notifier).start(_plan.id);
      } catch (_) {
        AppHaptics.warning();
        unawaited(ref.read(planTabProvider.notifier).refresh());

        return;
      }
      if (!mounted) return;
      ref.invalidate(dayRoomProvider(_address));
      try {
        current = await ref.read(dayRoomProvider(_address).future);
      } catch (_) {
        return;
      }
      if (!mounted) return;
    }
    final exit = await Navigator.of(context).push<DaySessionExit>(
      MaterialPageRoute(
        builder: (_) => DaySessionScreen(plan: _plan, room: current, rehearsal: action == WindowAction.again),
      ),
    );
    if (!mounted) return;
    ref.invalidate(dayRoomProvider(_address));
    unawaited(ref.read(planTabProvider.notifier).refresh());
    if (exit == DaySessionExit.dayClosed) AppFeedback.dayClosed();
  }

  @override
  Widget build(BuildContext context) {
    ref.listen(dayRoomProvider(_address), (previous, next) {
      final before = _windowOf(previous?.value);
      final after = _windowOf(next.value);
      if (after != null) _prepareVoice(after);
      if (before == null || after == null) return;
      final wasDone = {for (final s in before.stages) if (s.state == WindowStageState.done) s.stage};
      setState(() => _popped = {for (final s in after.stages) if (s.state == WindowStageState.done && !wasDone.contains(s.stage)) s.stage});
    });
    final room = ref.watch(dayRoomProvider(_address));
    Widget failed() => SafeArea(child: PlanLoadFailedCard(onRetry: () => ref.invalidate(dayRoomProvider(_address))));

    return Scaffold(
      backgroundColor: AppColors.ground,
      body: room.when(
        loading: () => const Center(child: CircularProgressIndicator(color: AppColors.ink)),
        error: (_, _) => failed(),
        data: (r) {
          final window = _windowOf(r);
          if (window == null) return failed();
          final action = window.action;

          return Stack(
            children: [
              WindowScroll(
                window: window,
                onListen: _listen,
                onBack: () => Navigator.of(context).maybePop(),
                poppedStages: _popped,
                bottomCover: action == null ? 0 : WindowActionBar.coverOf(context),
              ),
              if (action != null)
                Positioned(
                  left: 0,
                  right: 0,
                  bottom: 0,
                  child: WindowActionBar(
                    label: WindowTexts.action(AppLocalizations.of(context), action),
                    onTap: () => unawaited(_act(action, r)),
                  ),
                ),
            ],
          );
        },
      ),
    );
  }

  /// Окно из ответа, или null — ответа нет, или в нём слово, которого у окна нет.
  static DayWindow? _windowOf(PlanDayRoom? room) {
    if (room == null) return null;
    try {
      return DayWindow.fromJson(room.windowJson);
    } on PlanContractError catch (e) {
      debugPrint('[day-window] $e');

      return null;
    }
  }
}
