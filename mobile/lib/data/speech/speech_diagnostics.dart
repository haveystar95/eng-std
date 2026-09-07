/// ЧТО ИМЕННО СЕЙЧАС ДЕЛАЕТ МИКРОФОН — наряд DAY-GATE-1, Ч.0.1.
///
/// Живой прогон 07.09 кончился словами «нажал — „Слушаю…“, текст не появляется, ничего не
/// засчитывается», и назвать причину было нечем: движок ({@see SpeechTurn}) отдаёт наружу ОДИН исход
/// хода, а всё, что было до исхода, оставалось внутри — разрешения, доступность распознавателя,
/// момент открытия, эхо-сбросы, код ошибки плагина. Одна и та же картинка «Слушаю…» получалась из
/// пяти разных поломок, и ни одну из них нельзя было отличить с телефона в руке.
///
/// Поэтому здесь не логгер «на всякий случай», а ОДНО МЕСТО, где написано состояние канала, и
/// служебная строка дев-двери, которая его печатает. Читает — QA-дверь; пишет — движок и
/// распознаватель; в релизе у боевого аккаунта строка не рисуется, но состояние копится всё равно:
/// «жалоба» (Ч.0.5) прикладывает последние [_logLimit] строк к отчёту, и они должны быть, когда
/// поломка уже случилась, а не с момента, когда кто-то догадался включить журнал.
library;

import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';

/// СТАДИЯ ХОДА — ровно те шесть слов, которых не хватало на устройстве (наряд, Ч.0.1).
enum SpeechPhase {
  /// Ничего не происходит: карточка не говорения, или ход уже отдан.
  idle,

  /// Ждём, пока договорит собеседник — прогон открывает микрофон ПОСЛЕ реплики роли.
  waitingForRole,

  /// Микрофон просят открыть: разрешения, движок, аудиосессия. Тут же живут отказы.
  opening,

  /// Открыт и слушает.
  listening,

  /// Закрылся тишиной после речи — человек договорил.
  closedBySilence,

  /// Закрылся сторожем: тишина от открытия, человек так и не начал.
  closedByTimeout,

  /// Закрылся тем, что ключ узнан по дороге.
  closedByAnswer,

  /// Канал не поднялся или упал. [SpeechDiagnostics.lastErrorCode] называет чем.
  failed,
}

/// Ответ ОС про одно разрешение. `unknown` — ещё не спрашивали (нативная дверь не отвечала).
enum SpeechPermission { unknown, notDetermined, denied, restricted, granted }

SpeechPermission _permissionFromWire(Object? wire) => switch (wire) {
  'granted' || 'authorized' => SpeechPermission.granted,
  'denied' => SpeechPermission.denied,
  'restricted' => SpeechPermission.restricted,
  'not_determined' => SpeechPermission.notDetermined,
  _ => SpeechPermission.unknown,
};

/// ЧТО ОТВЕТИЛА ОС — снимок, а не мнение приложения.
///
/// Все пять полей спрашиваются НАПРЯМУЮ у системы (`SFSpeechRecognizer`, `AVAudioSession`), а не
/// выводятся из того, поднялся ли плагин: «плагин не поднялся» — это следствие, и лечить по нему
/// значит гадать. Разрешение распознавания и разрешение микрофона — ДВА РАЗНЫХ разрешения, и
/// главная поломка, которую эта пара ловит, в том, что они могут разойтись (см. `UPSTREAM.md`,
/// правка 2: плагин просил микрофон только по дороге через запрос распознавания).
@immutable
class SpeechProbe {
  const SpeechProbe({
    required this.localeId,
    this.microphone = SpeechPermission.unknown,
    this.recognition = SpeechPermission.unknown,
    this.recognizerAvailable = false,
    this.recognizerSupported = false,
    this.onDeviceSupported = false,
  });

  /// Язык, про который спрашивали, — «доступен ли распознаватель» это вопрос ПРО ЯЗЫК.
  final String localeId;

  final SpeechPermission microphone;
  final SpeechPermission recognition;

  /// `SFSpeechRecognizer(locale:)` для [localeId] вообще создался — язык поддерживается.
  final bool recognizerSupported;

  /// …и он `isAvailable` прямо сейчас: модель на месте, движок не занят.
  final bool recognizerAvailable;

  /// Умеет ли он работать без сети на этом устройстве. Мы всегда просим `onDevice: true`, поэтому
  /// «нет» здесь означает, что попытка кончится отказом, сколько бы человек ни говорил.
  final bool onDeviceSupported;

  /// Оба разрешения на месте — единственное состояние, из которого ход вообще может состояться.
  bool get permitted =>
      microphone == SpeechPermission.granted && recognition == SpeechPermission.granted;

  /// РАЗРЕШЕНИЕ ОТКАЗАНО НАСОВСЕМ: спрашивать заново нечем, помогает только «Настройки».
  bool get blockedInSettings =>
      microphone == SpeechPermission.denied ||
      microphone == SpeechPermission.restricted ||
      recognition == SpeechPermission.denied ||
      recognition == SpeechPermission.restricted;

  static SpeechProbe fromWire(String localeId, Map<Object?, Object?> wire) => SpeechProbe(
    localeId: localeId,
    microphone: _permissionFromWire(wire['microphone']),
    recognition: _permissionFromWire(wire['recognition']),
    recognizerSupported: wire['recognizer_supported'] == true,
    recognizerAvailable: wire['recognizer_available'] == true,
    onDeviceSupported: wire['on_device_supported'] == true,
  );
}

/// ЖИВОЕ СОСТОЯНИЕ КАНАЛА. Один экземпляр на приложение (провайдер в `providers.dart`).
///
/// [ChangeNotifier], а не Riverpod-состояние, по одной причине: писать сюда должны движок и
/// распознаватель — слои, которым `ref` не положен, — а читать одна служебная строка. Обратное
/// направление (провайдер, который дёргают из `SpeechTurn`) протащило бы Riverpod в `data/speech/`.
class SpeechDiagnostics extends ChangeNotifier {
  SpeechDiagnostics({@visibleForTesting MethodChannel? channel, DateTime Function()? now})
    : _channel = channel ?? const MethodChannel(_channelName),
      _now = now ?? DateTime.now;

  static const _channelName = 'com.denis.engstd/speech_probe';

  /// Сколько строк журнала держим. Ровно столько прикладывает «жалоба» (Ч.0.5).
  static const logLimit = 50;

  /// Дольше этого нативная дверь не отвечает — см. [refresh].
  static const _probeTimeout = Duration(seconds: 2);

  final MethodChannel _channel;
  final DateTime Function() _now;

  SpeechPhase _phase = SpeechPhase.idle;
  String? _lastErrorCode;
  String _lastPartial = '';
  int _echoes = 0;
  SpeechProbe? _probe;
  final List<String> _log = [];

  /// ЖУРНАЛ НЕ ИМЕЕТ ПРАВА УРОНИТЬ ТО, ЗА ЧЕМ НАБЛЮДАЕТ.
  ///
  /// Ход голосом живёт дольше экрана: карточку покидают, `cancel()` долетает следующим тактом, а
  /// область провайдеров к тому моменту уже снесена. `ChangeNotifier` в этом месте бросает — и
  /// диагностика, падающая ровно в момент поломки, которую она заведена ловить, была бы худшим из
  /// возможных инструментов. После [dispose] здесь всё превращается в тишину.
  bool _disposed = false;

  @override
  void dispose() {
    _disposed = true;
    super.dispose();
  }

  void _changed() {
    if (_disposed) return;
    notifyListeners();
  }

  SpeechPhase get phase => _phase;

  /// Код последнего отказа — плагина (`error_speech_recognizer_request_not_authorized` и прочие)
  /// или наш собственный. Null, пока ничего не падало в этом ходу.
  String? get lastErrorCode => _lastErrorCode;

  /// Последний частичный текст распознавания. Пустой — движок не услышал ни слова.
  String get lastPartial => _lastPartial;

  /// Сколько раз склейку выбросил эхо-замок: «Слушаю…» без текста выглядит одинаково и когда
  /// микрофон мёртв, и когда он исправно слышит динамик и честно это выкидывает.
  int get echoes => _echoes;

  SpeechProbe? get probe => _probe;

  /// Журнал, старое сверху. Копия — читателю нечего менять.
  List<String> get log => List.unmodifiable(_log);

  /// СПРОСИТЬ ОС ЗАНОВО. Дёшево и без побочных эффектов: ни одного разрешения не запрашивает —
  /// только читает статусы, поэтому служебная строка может обновляться сама, не показывая человеку
  /// системных окон.
  Future<SpeechProbe> refresh(String localeId) async {
    try {
      final wire = await _channel
          .invokeMethod<Map<Object?, Object?>>('probe', {'locale': localeId})
          // Нативная дверь либо отвечает мгновенно (это пять чтений статусов), либо её нет вовсе —
          // харнесс, виджет-тест, не-iOS. Незавершённое будущее отсюда — это ещё одно место, где
          // экран мог бы залипнуть, и оно закрыто здесь, а не бдительностью вызывающих.
          .timeout(_probeTimeout);
      final probe = wire == null
          ? SpeechProbe(localeId: localeId)
          : SpeechProbe.fromWire(localeId, wire);
      _probe = probe;
      _changed();

      return probe;
    } catch (e) {
      // ЛОВИМ ВСЁ, И ЭТО НЕ ЛЕНЬ. Канала нет (тесты, харнессы, не-iOS), плагин ответил не тем,
      // формат разъехался — любой из этих случаев не является поломкой микрофона и НЕ ИМЕЕТ ПРАВА
      // ронять того, кто спрашивает: `refresh` вызывается изнутри хода голосом, и исключение отсюда
      // оставило бы карточку без микрофона и без объяснения — ровно та поломка, ради которой этот
      // класс и заведён. Врать про разрешения тоже нельзя: остаётся `unknown`.
      note('probe failed: ${e is PlatformException ? e.code : e.runtimeType}');
      final probe = SpeechProbe(localeId: localeId);
      _probe = probe;
      _changed();

      return probe;
    }
  }

  /// Стадия сменилась. [code] — код отказа, когда стадия [SpeechPhase.failed].
  void phaseIs(SpeechPhase phase, {String? code}) {
    _phase = phase;
    if (code != null) _lastErrorCode = code;
    note(code == null ? phase.name : '${phase.name} · $code');
    _changed();
  }

  /// Новый ход: журнал не чистится (он и нужен через границу хода), а вот код прошлого отказа и
  /// прошлый текст — да, иначе строка показывала бы позавчерашнюю ошибку как сегодняшнюю.
  void turnStarted() {
    _lastErrorCode = null;
    _lastPartial = '';
    _echoes = 0;
    phaseIs(SpeechPhase.opening);
  }

  void partial(String text) {
    if (text == _lastPartial) return;
    _lastPartial = text;
    if (text.trim().isNotEmpty) note('partial: $text');
    _changed();
  }

  void echoDropped() {
    _echoes++;
    note('echo dropped (#$_echoes)');
    _changed();
  }

  /// Одна строка в журнал, со временем. Публично, потому что писать в него имеют право и
  /// распознаватель, и карточка — «последние 50 строк лога SpeechTurn» в «жалобе» это они все.
  void note(String line) {
    final t = _now();
    final stamp =
        '${t.hour.toString().padLeft(2, '0')}:'
        '${t.minute.toString().padLeft(2, '0')}:'
        '${t.second.toString().padLeft(2, '0')}.'
        '${t.millisecond.toString().padLeft(3, '0')}';
    _log.add('$stamp  $line');
    if (_log.length > logLimit) _log.removeRange(0, _log.length - logLimit);
  }
}
