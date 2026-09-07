import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'config.dart';
import 'speech/speech_diagnostics.dart';

/// ГДЕ ЧЕЛОВЕК СЕЙЧАС НАХОДИТСЯ — наряд DAY-GATE-1, Ч.0.5.
///
/// «Жалоба» бесполезна без адреса: снимок экрана показывает, ЧТО было видно, но не говорит, какой
/// это план, какой день, какая посадка и какая карточка — а именно этих идентификаторов не хватало,
/// чтобы за поломкой 07.09 сходить в базу. Экраны кладут сюда свой адрес, кнопка его забирает.
///
/// Поля необязательны все до одного: с главного экрана «жалоба» тоже должна нажиматься, и отчёт
/// без плана — это отчёт без плана, а не ошибка.
@immutable
class QaContext {
  const QaContext({
    this.planId,
    this.dayIndex,
    this.dayId,
    this.sessionId,
    this.stage,
    this.cardTermId,
    this.cardMode,
    this.screen,
  });

  final String? planId;
  final int? dayIndex;
  final String? dayId;
  final String? sessionId;

  /// Код этапа дня (`material` · `conversation` · `rehearsal` · `retrain`), как его прислал сервер.
  final String? stage;

  final String? cardTermId;
  final String? cardMode;

  /// Человеческое имя экрана — для случая, когда идентификаторов нет вовсе.
  final String? screen;

  Map<String, dynamic> toJson() => {
    if (screen != null) 'screen': screen,
    if (planId != null) 'plan_id': planId,
    if (dayIndex != null) 'day_index': dayIndex,
    if (dayId != null) 'day_id': dayId,
    if (sessionId != null) 'session_id': sessionId,
    if (stage != null) 'stage': stage,
    if (cardTermId != null) 'card_term_id': cardTermId,
    if (cardMode != null) 'card_mode': cardMode,
  };

  QaContext copyWith({
    String? planId,
    int? dayIndex,
    String? dayId,
    String? sessionId,
    String? stage,
    String? cardTermId,
    String? cardMode,
    String? screen,
  }) => QaContext(
    planId: planId ?? this.planId,
    dayIndex: dayIndex ?? this.dayIndex,
    dayId: dayId ?? this.dayId,
    sessionId: sessionId ?? this.sessionId,
    stage: stage ?? this.stage,
    cardTermId: cardTermId ?? this.cardTermId,
    cardMode: cardMode ?? this.cardMode,
    screen: screen ?? this.screen,
  );
}

/// Адрес текущего экрана. Пишут экраны, читает кнопка «жалобы».
class QaContextNotifier extends Notifier<QaContext> {
  @override
  QaContext build() => const QaContext();

  /// Экран вошёл: он называет ВСЁ, что про себя знает, а не дописывает к чужому. Иначе идентификатор
  /// вчерашней посадки уехал бы в отчёт с сегодняшнего главного экрана — то есть отчёт врал бы
  /// адресом, а неверный адрес хуже отсутствующего.
  void enter(QaContext context) => state = context;

  /// Карточка сменилась внутри той же посадки.
  ///
  /// Молчит, когда ничего не менялось: зовётся из построения экрана, то есть потенциально каждый
  /// кадр, а состояние, переписываемое кадром без изменений, — это лишние объекты и лишние
  /// оповещения ради одинакового значения.
  void card({String? termId, String? mode, String? stage}) {
    if (state.cardTermId == termId && state.cardMode == mode && state.stage == stage) return;
    state = state.copyWith(cardTermId: termId, cardMode: mode, stage: stage);
  }
}

final qaContextProvider = NotifierProvider<QaContextNotifier, QaContext>(QaContextNotifier.new);

/// «Я — ВОТ ЭТОТ ЭКРАН». Ничего не рисует; ставится в дерево там, где адрес известен.
///
/// Виджет, а не строчка в `build`, по одной причине, и она не про красоту: Riverpod запрещает
/// менять провайдер во время построения дерева, а экраны плана — `ConsumerWidget` без `mounted`,
/// то есть без безопасного места для отложенной записи. У этого виджета такое место есть.
class QaAddress extends ConsumerStatefulWidget {
  const QaAddress(this.address, {super.key});

  final QaContext address;

  @override
  ConsumerState<QaAddress> createState() => _QaAddressState();
}

class _QaAddressState extends ConsumerState<QaAddress> {
  @override
  void initState() {
    super.initState();
    _write();
  }

  @override
  void didUpdateWidget(QaAddress old) {
    super.didUpdateWidget(old);
    _write();
  }

  void _write() => WidgetsBinding.instance.addPostFrameCallback((_) {
    if (mounted) ref.read(qaContextProvider.notifier).enter(widget.address);
  });

  @override
  Widget build(BuildContext context) => const SizedBox.shrink();
}

/// СЛЕПОК МОМЕНТА, который уходит в «жалобу» — всё, кроме снимка экрана.
///
/// Собран в одном месте и без виджетов: кнопка только рисует, а состав отчёта — это решение о том,
/// что понадобится завтра при разборе, и его должно быть видно целиком.
Map<String, dynamic> buildQaReport({
  required QaContext context,
  required SpeechDiagnostics speech,
  required String backendCommit,
  String? note,
}) => {
  'client': {
    'build_sha': AppConfig.buildSha,
    'build_at': AppConfig.buildAt,
    'api_base_url': AppConfig.apiBaseUrl,
  },
  'server': {'commit': backendCommit},
  'context': context.toJson(),
  // МИКРОФОН ЦЕЛИКОМ (Ч.0.1): статусы разрешений, стадия хода, код отказа и последние строки
  // журнала. Именно этих сведений не было 07.09, и именно за ними «жалоба» и заведена.
  'speech': {
    'phase': speech.phase.name,
    'last_error': speech.lastErrorCode,
    'last_partial': speech.lastPartial,
    'echoes': speech.echoes,
    'permissions': {
      'microphone': speech.probe?.microphone.name,
      'recognition': speech.probe?.recognition.name,
      'locale': speech.probe?.localeId,
      'recognizer_supported': speech.probe?.recognizerSupported,
      'recognizer_available': speech.probe?.recognizerAvailable,
      'on_device_supported': speech.probe?.onDeviceSupported,
    },
    'log': speech.log,
  },
  if (note != null && note.trim().isNotEmpty) 'note': note.trim(),
};
