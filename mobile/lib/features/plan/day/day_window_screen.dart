import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../data/api_client.dart' show problemCodeOf;
import '../../../data/audio_loader.dart';
import '../../../data/image_loader.dart';
import '../../../data/line_audio.dart';
import '../../../data/plan/day_providers.dart';
import '../../../data/plan/day_window.dart';
import '../../../data/plan/plan_models.dart';
import '../../../data/providers.dart';
import '../conversation/conversation_controller.dart';
import '../conversation/talk_replay_screen.dart';
import '../plan_providers.dart';
import '../plan_tab_parts.dart' show PlanLoadFailedCard;
import '../session/parts/session_bits.dart' show SessionDockButton;
import '../session/session_screen.dart';
import '../session/session_voice.dart';
import 'day_voice.dart';
import 'window/window_action_bar.dart';
import 'window/window_scroll.dart';
import 'window/window_sources.dart';
import 'window/window_texts.dart';
import 'window/window_word_sheet.dart';

/// ОКНО ДНЯ (кадры 23-0a…0e, наряды DAY-UI-2, DAY-UI-3) — один экран, одна лента: плита на фото дня,
/// пилюля вкладок на шве, программа и одна кнопка внизу по `allowed_action` сервера — «Начать»,
/// «Продолжить», «Ещё раз». Тап по слову — его шит 23-0e.
///
/// Всё, что окно пишет, посчитано сервером (`PlanDayRoom.window`); окно складывает слова. Запертого
/// дня здесь нет: таб его не открывает, а пришедший ответ со словом, которого у окна нет, — честная
/// ошибка загрузки, а не нарисованное состояние.
///
/// ЗАГРУЗКА ДНЯ СРАЗУ (DAY-UI-3): как только окно узнало день, все его фото идут в общий `ImageLoader`, а
/// весь голос — слова, фразы, обе реплики каждого обмена — в общий загрузчик звука (шесть параллельно,
/// диск, повторы). К «Начать» и к «прослушать» файлы уже на диске; нет файла — читает телефон.
///
/// A REVIEW AND THE REHEARSAL (кадры 37-1, 37-2, наряд CLIENT-CONV-1b) open the same window with their own plate —
/// the day's kind, its title, one status line, the rows the server dealt — and under it, in place of the three tabs,
/// the list of what the day is made of ([WindowSourcesScroll]).
///
/// A WALKED ROW MAY BE WALKED AGAIN (`stages[].again`, наряд FIX-3 §5): «ещё раз» on a row of cards opens that stage
/// on the phone alone, and «ещё раз» on the talk's row starts a new talk over the walked stage on the talk's own
/// screens ([TalkReplayScreen]).
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

  /// «Повторить разговор» is on its way — the start waits on a model and a voice.
  bool _replaying = false;

  /// Этапы, закрытые с прошлого ответа сервера, — их галки появятся через 300 мс.
  Set<PlanStage> _popped = const {};

  /// Когда окно открылось — точка отсчёта замера «всё дня на диске» (холодный и тёплый вход).
  final Stopwatch _opened = Stopwatch()..start();
  bool _measured = false;

  DayAddress get _address => (planId: widget.plan.id, number: widget.number);

  @override
  void initState() {
    super.initState();
    // Каждый ответ дня — сразу в загрузку, и тот, что уже есть к первому кадру, тоже.
    ref.listenManual(dayRoomProvider(_address), (_, next) {
      if (_windowOf(next.value) case final window?) _prepareMedia(window);
    }, fireImmediately: true);
  }

  @override
  void dispose() {
    unawaited(_voice?.release());
    super.dispose();
  }

  DayVoice get _voiceNow => _voice ??= DayVoice(lines: ref.read(lineAudioCacheProvider), targetLang: _plan.targetLang)..warmUp();

  /// Всё, что день покажет и скажет, — на диск сразу, параллельно (DAY-UI-3): фото плиты, её кружок
  /// компактной шапки и фото каждого слова; голос каждого слова, его реплики «в разговоре», каждой фразы
  /// и обеих реплик каждого обмена.
  void _prepareMedia(DayWindow window) {
    final program = window.program;
    // Плотность — не из `MediaQuery`: первый ответ может прийти до первого кадра, из `initState`.
    final dpr = WidgetsBinding.instance.platformDispatcher.implicitView?.devicePixelRatio ?? 2;
    final images = <String?>[
      window.day.image?.url,
      window.day.image?.urlFor(32, dpr),
      for (final w in program.words) w.image?.url,
    ];
    final lines = <LineAudioRef>[
      for (final w in program.words) ...[
        if (w.audioUrl case final url?) (text: w.term, url: url),
        if (w.usage case WindowUsage(:final text, audioUrl: final url?)) (text: text, url: url),
      ],
      for (final p in program.phrases)
        if (p.audioUrl case final url?) (text: p.text, url: url),
      for (final pair in program.dialogue) ...[
        if (pair.partner case WindowLine(:final text, audioUrl: final url?)) (text: text, url: url),
        if (pair.learner case WindowLine(:final text, audioUrl: final url?)) (text: text, url: url),
      ],
    ];
    final photoUrls = images.whereType<String>().toSet();
    final audioUrls = {for (final line in lines) LineAudioCache.normalizeUrl(line.url)};
    final imageLog = ImageLoader.instance.timings.length;
    final audioLog = ref.read(lineAudioCacheProvider).loader?.timings.length ?? 0;
    final imagesDone = ImageLoader.instance.prefetch(photoUrls);
    final voiceDone = lines.isEmpty ? Future<void>.value() : _voiceNow.preload(lines);
    if (_measured) return;
    _measured = true;
    // ЗАМЕР ХОЛОДНОГО И ТЁПЛОГО ВХОДА (отчёт DAY-UI-3): сколько от открытия окна до последнего файла дня
    // на диске и сколько из них пришло сетью. Тёплый вход — сеть 0: всё уже лежало.
    unawaited(Future.wait([imagesDone, voiceDone]).then((_) {
      if (!mounted) return;
      final photos = ImageLoader.instance.timings.skip(imageLog).where((t) => photoUrls.contains(t.url));
      final audio = (ref.read(lineAudioCacheProvider).loader?.timings ?? const <AudioLoadTiming>[])
          .skip(audioLog)
          .where((t) => audioUrls.contains(t.url));
      debugPrint(
        '[day-media] day ${window.day.index}: every file on disk ${_opened.elapsedMilliseconds} ms after the window opened — '
        'photos ${photoUrls.length} (network ${photos.where((t) => !t.fromDisk).length}), '
        'voice ${audioUrls.length} (network ${audio.where((t) => !t.fromDisk).length})',
      );
    }));
  }

  /// «Прослушать» — файл строки, если он на диске; нет — голос телефона.
  void _listen(String text, String? audioUrl) => unawaited(_voiceNow.speak(text));

  void _openWord(WindowWord word) => unawaited(showWindowWordSheet(context, word: word, onListen: _listen));

  Future<void> _act(WindowAction? action, PlanDayRoom room, {PlanStage? replayStage}) async {
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
    // DAY SESSION (work orders SESSION-1b, SESSION-1c): «Start» and «Continue» lead into the session — it reads the
    // day itself and stops at the first unanswered card, or at the day summary (30-7) when every card is answered;
    // «Close the day» there pops back here, and the window reads the day and the plan again. «Once more» replays a
    // stage on the phone and ends on the day summary again, without sending anything (SESSION-2a §4).
    await Navigator.of(context).push<void>(
      MaterialPageRoute(
        builder: (_) => SessionScreen(plan: _plan, number: current.day.number, replayStage: replayStage),
      ),
    );
    if (!mounted) return;
    ref.invalidate(dayRoomProvider(_address));
    unawaited(ref.read(planTabProvider.notifier).refresh());
  }

  /// «ЕЩЁ РАЗ» У РЯДА РАЗГОВОРА (`stages[].again`, наряд FIX-3 §5): the one POST of a talk's start, here, so its
  /// answer is heard where the button is — started, the talk opens on the screen every talk has (37-6…37-12); a 409
  /// `plan_conversation_replay_limit` is a sheet over the window, «Разговор сегодня уже повторяли — вернись завтра»,
  /// and anything else the talk's own «could not start». «Ещё раз» of the cards is untouched: it is the other action.
  Future<void> _replayTalk() async {
    if (_replaying) return;
    setState(() => _replaying = true);
    final voice = SessionVoice(lines: ref.read(lineAudioCacheProvider), targetLang: _plan.targetLang);
    unawaited(voice.warmUp().catchError((Object _) {}));
    final talk = ConversationController(
      backend: ApiConversationBackend(ref.read(apiClientProvider)),
      planId: _plan.id,
      day: widget.number,
      voice: voice,
      hints: !await ref.read(planStoreProvider).noHints(_plan.id),
    );
    // The talk's screen opens on the server's answer: the role's first line is said there, not over the window.
    await talk.openAnswered();
    if (!mounted) {
      talk.dispose();
      unawaited(voice.release());
      return;
    }
    setState(() => _replaying = false);
    if (talk.phase == TalkPhase.openFailed) {
      final l = AppLocalizations.of(context);
      final limit = problemCodeOf(talk.openError) == 'plan_conversation_replay_limit';
      talk.dispose();
      unawaited(voice.release());
      AppHaptics.warning();
      await _showWindowNotice(context, limit ? l.planWindowTalkReplayLimit : l.planTalkOpenFailed);
      return;
    }
    await Navigator.of(context).push<void>(
      MaterialPageRoute(builder: (_) => TalkReplayScreen(plan: _plan, number: widget.number, talk: talk, voice: voice)),
    );
    if (!mounted) return;
    ref.invalidate(dayRoomProvider(_address));
    unawaited(ref.read(planTabProvider.notifier).refresh());
  }

  /// «ЕЩЁ РАЗ» РЯДА (наряд FIX-3 §5): этап карточек проходится снова на телефоне — ничего не отправляется, день не
  /// меняется; ряд разговора ведёт в повтор разговора (ручка сервера, 409 лимита — листом над окном).
  Future<void> _again(PlanStage stage, PlanDayRoom room) =>
      stage == PlanStage.conversation ? _replayTalk() : _act(null, room, replayStage: stage);

  @override
  Widget build(BuildContext context) {
    ref.listen(dayRoomProvider(_address), (previous, next) {
      final before = _windowOf(previous?.value);
      final after = _windowOf(next.value);
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
          final l = AppLocalizations.of(context);
          // A review and the rehearsal (37-1, 37-2) are the same window with other rows and, in place of the tabs,
          // the list of what the day is made of.
          final system = WindowTexts.system(l, window.day, planTitle: _plan.shortTitle ?? _plan.displayTitle, slot: r.day.slot);
          // ОДНА КНОПКА ДНЯ (кадры 23-0a…0c, 37-1c; наряд FIX-3 §5, приёмка окна 2 п. 3): «Начать» / «Продолжить», у
          // пройденного дня — его итог: «Итог дня» (30-7) у дня плана и «Итог» у дня-системы, как на 37-1c. Повтора
          // дня целиком нет, «ещё раз» живёт у каждого ряда этапа.
          final passed = window.day.status == WindowDayStatus.passed;
          final summary = system != null ? l.planWindowSummary : l.planWindowDaySummary;
          final barLabel = action != null ? WindowTexts.action(l, action) : (passed ? summary : null);
          final cover = barLabel != null ? WindowActionBar.coverOf(context) : 0.0;

          return Stack(
            children: [
              if (system != null)
                WindowSourcesScroll(
                  window: window,
                  system: system,
                  sources: WindowSources.of(l, plan: _plan, window: window),
                  onBack: () => Navigator.of(context).maybePop(),
                  poppedStages: _popped,
                  bottomCover: cover,
                  onStageAgain: (stage) => unawaited(_again(stage, r)),
                )
              else
                WindowScroll(
                  window: window,
                  onListen: _listen,
                  onOpenWord: _openWord,
                  onBack: () => Navigator.of(context).maybePop(),
                  poppedStages: _popped,
                  bottomCover: cover,
                  onStageAgain: (stage) => unawaited(_again(stage, r)),
                  sceneImageOf: (sceneId) => _plan.sceneById(sceneId)?.image,
                ),
              if (barLabel != null)
                Positioned(
                  left: 0,
                  right: 0,
                  bottom: 0,
                  child: WindowActionBar(
                    label: barLabel,
                    busy: _replaying,
                    onTap: () => unawaited(_act(action, r)),
                  ),
                ),
            ],
          );
        },
      ),
    );
  }

  /// A sentence on a sheet over the window, and «Понятно» — the replay limit of today (409
  /// `plan_conversation_replay_limit`), or a talk that did not start. The session's own sheet: the ground, corners 22,
  /// the handle, one sentence, one button.
  static Future<void> _showWindowNotice(BuildContext context, String text) => showModalBottomSheet<void>(
    context: context,
    backgroundColor: AppColors.ground,
    barrierColor: AppColors.windowSheetScrim,
    elevation: 0,
    isScrollControlled: true,
    sheetAnimationStyle: const AnimationStyle(duration: AppMotion.sessionExitSheet, curve: AppMotion.windowEaseOutCubic),
    shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(22))),
    builder: (sheet) => Padding(
      key: const ValueKey('window-notice-sheet'),
      padding: EdgeInsets.fromLTRB(24, 24, 24, 24 + MediaQuery.paddingOf(sheet).bottom),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Center(
            child: Container(
              width: 36,
              height: 4,
              decoration: BoxDecoration(color: AppColors.markerOutline, borderRadius: BorderRadius.circular(2)),
            ),
          ),
          const SizedBox(height: 24),
          Text(text, key: const ValueKey('window-notice-text'), style: AppTextSession.sheetTitle),
          const SizedBox(height: 32),
          SessionDockButton(label: AppLocalizations.of(sheet).planSheetCta, onTap: () => Navigator.of(sheet).pop()),
        ],
      ),
    ),
  );

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
