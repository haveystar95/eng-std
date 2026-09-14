/// СЕРВЕРНАЯ ОЗВУЧКА РЕПЛИК на телефоне — докачка, кэш и подача (наряд TTS-1, Ч.2).
///
/// Канон `../backend2/docs/plan-dialogue.md` §7: ярус «понимаю», такт 1, прогон и разогрев стоят на
/// слушании, темп реплик ниже темпа слов, и «никакая реплика не подаётся на слух, пока озвучка не
/// готова». С DAY-UI-3 сервер озвучивает всё, что звучит в дне: реплики обоих, фразы и слова — у
/// каждой строки свой файл, а системный синтез — временная замена, пока файла нет. Докачивает общий
/// [AudioLoader] (шесть параллельно, диск, повторы).
///
/// ## Ключ — ТЕКСТ, а не карточка, и это несущее решение
///
/// Голос в приложении вызывается текстом: `Pronouncer.speak('Thanks for joining today.')`. Понятия
/// «карточка» у него нет и не должно быть — одну и ту же реплику произносят пузырь диалога, кнопка
/// повтора, панель спасателей, шпаргалка и разогрев, и это пять разных экранов с пятью разными
/// объектами. Кэш, ключуемый текстом, обслуживает все пять одной строкой и без единого проброса
/// `term_id` через виджеты. Термины дедуплицированы глобально, поэтому «тот же текст» и «та же
/// карточка» — это одно и то же.
///
/// ## Что значит «знает реплику»
///
/// Кэш наполняется из пейлоада ДО того, как файлы скачаны: сначала он знает, что строка — реплика,
/// и только потом — где её файл. Разница видна на экране: пока файла нет, диалог показывает
/// «Готовим озвучку» (кадр DL·08) вместо того, чтобы читать реплику системным голосом «пока что».
/// Знание переживает перезапуск (манифест на диске), поэтому повторный вход в день работает без
/// сети — второе требование Ч.2.1.
library;

import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';

import 'audio_loader.dart';
import 'config.dart';

/// One line the server can play from a file: what it says, and where the file is.
typedef LineAudioRef = ({String text, String url});

/// ЧТО ПОШЛО НЕ ТАК с озвучкой, и это не отладочная роскошь.
///
/// Труба падает ТИХО по построению: файла нет — читает системный синтез, и урок продолжается. Ровно
/// поэтому сломанная труба неотличима от выключенной, и ровно это уже случилось живьём (наряд
/// TTS-1: сервер отдавал `http://`, iOS резал запрос по ATS, все реплики ушли на системный голос, и
/// на экране это выглядело нормально). Счётчик существует, чтобы разница была ВИДНА в дев-сборке.
///
/// `silentFallbacks` — самое важное число: у реплики БЫЛ адрес, а прозвучала она системным голосом.
typedef VoiceTrouble = ({int downloads, int silentFallbacks, String? lastReason});

/// Пара «реплика → файл» на телефоне.
///
/// Один экземпляр на приложение (провайдер в `providers.dart`): манифест на диске один, и два
/// объекта, пишущих его порознь, — это две карты, которые однажды разойдутся.
class LineAudioCache {
  LineAudioCache({
    Dio? http,
    MethodChannel? channel,
    Directory? directory,
    String? Function()? bearer,
    Duration? retryBackoff,
  }) : _http = http ?? Dio(),
       _channel = channel ?? const MethodChannel('com.denis.engstd/line_audio'),
       _given = directory,
       // ignore: prefer_initializing_formals
       _bearer = bearer,
       // ignore: prefer_initializing_formals
       _retryBackoff = retryBackoff;

  final Dio _http;
  final MethodChannel _channel;

  /// ТОКЕН — СПРАШИВАЕТСЯ В МОМЕНТ ЗАПРОСА, а не передаётся снимком.
  ///
  /// Файл озвучки лежит за `auth:sanctum`, как и весь остальной API, и качается он тем же токеном.
  /// Раньше токен приезжал сюда СТРОКОЙ, снятой на входе в день (`initState`), и этого хватило,
  /// чтобы живьём все десять докачек ушли без заголовка вовсе: лог сервера показал десять
  /// `401` подряд, у которых ключа `authorization` в запросе нет, а соседние вызовы API в ту же
  /// минуту прошли с токеном. Снимок — это одно мгновение, и любое мгновение, в котором токен ещё
  /// не поднят из кейчейна или уже обновлён, превращает докачку в анонимную.
  ///
  /// Функция вместо строки убирает весь класс: спрашивают в момент запроса, значит спрашивают у
  /// того, кто знает. Null — «токена нет», и это законно: харнессы и тесты качают без него.
  final String? Function()? _bearer;

  /// Куда класть файлы, если каталог задан снаружи. В приложении — null (спрашиваем систему); в
  /// тестах — временный каталог, ровно как у [ImageDiskCache], и по той же причине: спрашивать
  /// `path_provider` в тесте значит проверять мок, а не кэш.
  final Directory? _given;

  /// `нормализованный текст` → адрес файла на сервере. Известно, что это РЕПЛИКА.
  final Map<String, String> _urlOf = {};

  /// `нормализованный текст` → имя файла в кэше. Известно, что реплика УЖЕ СКАЧАНА.
  final Map<String, String> _fileOf = {};

  /// Строки, чья докачка УПАЛА и не повторяется прямо сейчас.
  ///
  /// Ждать их бессмысленно: файла не будет, пока кто-нибудь не попробует снова. Поэтому такая
  /// строка считается готовой ([isReady]) — она прозвучит системным голосом, а кэш засчитает тихий
  /// фолбэк, который увидит дев-бейдж. Экран, который вместо этого показывал «Готовим озвучку»,
  /// ждал того, что уже не случилось (живой прогон: десять `401`, ноль системных).
  final Set<String> _failed = {};

  /// Строки, про которые известно ТОЛЬКО ОДНО: это реплики, файла у них нет и не будет.
  ///
  /// Нужны ради ТЕМПА. Канон §7 просит читать реплики медленнее слов независимо от того, кто их
  /// читает, а с выключенной трубой (или у языка без голоса в пакете) у реплики нет ни `audio_url`,
  /// ни строки в `line_audio` — и системный синтез читал бы восьмисловный вопрос со скоростью слова.
  /// Посадка называет свои реплики отдельно ([note]), и темп оказывается правильным на обоих путях.
  ///
  /// В манифест не пишутся: манифест — про файлы, а это знание про сегодняшний пейлоад.
  final Set<String> _noted = {};

  Directory? _dir;
  Future<void>? _loading;

  /// Пауза перед повтором упавшей докачки (тесты ставят ноль).
  final Duration? _retryBackoff;

  /// ОБЩИЙ ЗАГРУЗЧИК ЗВУКА (DAY-UI-3): шесть докачек параллельно, диск, повторы на обрыв и 5xx, одна
  /// докачка на адрес. Поднимается вместе с каталогом в [load]; до этого докачивать некуда.
  AudioLoader? _loader;

  /// Загрузчик — для замера холодного и тёплого входа в день (`timings`).
  AudioLoader? get loader => _loader;

  int _downloadFailures = 0;
  int _silentFallbacks = 0;
  String? _lastReason;

  /// См. [VoiceTrouble]. Ноль по всем трём — труба либо работает, либо выключена; отличить их
  /// можно по тому, знает ли кэш хоть одну реплику ([knows]).
  VoiceTrouble get trouble =>
      (downloads: _downloadFailures, silentFallbacks: _silentFallbacks, lastReason: _lastReason);

  /// Ключ кэша. Регистр и хвостовые пробелы срезаются, потому что одна и та же реплика приезжает
  /// пузырём, карточкой и шпаргалкой, и совпадать они обязаны как строки, а не как байты.
  static String keyOf(String text) => text.trim().toLowerCase();

  /// АДРЕС ФАЙЛА, ПРИВЕДЁННЫЙ К СХЕМЕ API. Возвращает [url] без изменений, если приводить нечего.
  ///
  /// Живой дефект, стоивший десяти реплик подряд: посадка ДОЛГОВЕЧНА — присест переживает выход из
  /// приложения, и его пейлоад лежит на диске целиком, вместе с абсолютными адресами файлов
  /// (`plan_sitting_store.dart`). Адрес — факт про СЕРВЕР в момент сборки, а не про присест, и
  /// сохранённый пейлоад нёс адреса, сгенерированные до того, как сервер научился доверять
  /// `X-Forwarded-Proto`: `http://…`.
  ///
  /// Дальше механика беспощадная и тихая: ngrok отвечает на `http` редиректом `307` на `https`,
  /// `dart:io` редирект послушно идёт — и **срезает `Authorization`**, потому что не переносит
  /// авторизацию через переход. До сервера доезжает анонимный запрос, сервер отвечает `401`, а в
  /// логе у этих запросов ключа `authorization` нет вовсе — при том, что соседние вызовы API той же
  /// минуты прошли с токеном (лог `api_request_logs`, 05.09 01:19).
  ///
  /// Поэтому схема приводится ЗДЕСЬ, а не чинится на сервере: сервер уже чинили
  /// ({@link bootstrap/app.php} `trustProxies`), а адреса, уже лежащие на телефонах, он переписать
  /// не может. И правило шире одного дефекта: токен не должен ехать по cleartext ни при каких
  /// обстоятельствах — ни ради озвучки, ни ради чего-либо ещё.
  ///
  /// Трогается только СВОЙ хост: чужой адрес с чужой схемой — не наша забота и не наш токен.
  static String normalizeUrl(String url, {String? apiBase}) {
    final base = Uri.tryParse(apiBase ?? AppConfig.apiBaseUrl);
    final target = Uri.tryParse(url);
    if (base == null || target == null) return url;
    if (base.scheme != 'https' || target.scheme != 'http') return url;
    if (target.host.isEmpty || target.host != base.host) return url;

    return target.replace(scheme: 'https').toString();
  }

  /// Имя файла — id строки озвучки из URL. Смена голоса даёт новый id, поэтому новый файл встаёт
  /// рядом со старым, а не поверх него: «старый кэш не играется за новый голос» держится именем.
  static String fileNameOf(String url) => AudioLoader.fileNameOf(url);

  /// Известна ли эта строка КАК РЕПЛИКА — независимо от того, скачан ли уже файл и будет ли он.
  bool knows(String text) {
    final key = keyOf(text);

    return _urlOf.containsKey(key) || _noted.contains(key);
  }

  /// «Это реплики сцены» — сказать заранее, ничего не обещая про файлы.
  ///
  /// Зовётся посадкой на всём, что в ней является репликой (`kind = line`), включая то, что сервер
  /// не озвучивает. Ждать по ним нечего — [isReady] про них правду говорит и так.
  void note(Iterable<String> texts) {
    for (final text in texts) {
      final key = keyOf(text);
      if (key.isNotEmpty) _noted.add(key);
    }
  }

  /// Готова ли реплика к подаче на слух — то есть можно ли её сейчас произнести хоть как-нибудь.
  ///
  /// Три «да» и одно «нет»: не реплика вовсе (её всегда читал телефон), файл уже приехал, докачка
  /// упала (файла не будет — читает телефон). «Нет» остаётся ровно за одним состоянием: файл ЕЩЁ
  /// едет, и его стоит подождать (кадр DL·08).
  bool isReady(String text) {
    final key = keyOf(text);

    return !_urlOf.containsKey(key) || _fileOf.containsKey(key) || _failed.contains(key);
  }

  /// Упала ли докачка этой строки — для дев-бейджа и для тестов.
  bool hasFailed(String text) => _failed.contains(keyOf(text));

  /// Путь к скачанному файлу, или null.
  String? fileFor(String text) {
    final name = _fileOf[keyOf(text)];
    final dir = _dir;

    return (name == null || dir == null) ? null : p.join(dir.path, name);
  }

  /// Сколько реплик из известных уже скачано — числитель и знаменатель «Готовим озвучку».
  ({int ready, int total}) get progress =>
      (ready: _fileOf.keys.where(_urlOf.containsKey).length, total: _urlOf.length);

  /// Поднять манифест с диска. Идемпотентно и безопасно звать сколько угодно раз.
  Future<void> load() => _loading ??= _load();

  Future<void> _load() async {
    try {
      final given = _given;
      final dir = given ?? Directory(p.join((await getApplicationSupportDirectory()).path, 'line_audio'));
      if (!dir.existsSync()) dir.createSync(recursive: true);
      _dir = dir;
      _loader = AudioLoader(http: _http, directory: dir, bearer: _bearer, firstBackoff: _retryBackoff);

      final manifest = File(p.join(dir.path, 'manifest.json'));
      if (!manifest.existsSync()) return;

      final raw = jsonDecode(manifest.readAsStringSync());
      if (raw is! Map) return;
      for (final entry in raw.entries) {
        final row = entry.value;
        if (row is! Map) continue;
        final url = row['url'], file = row['file'];
        if (url is! String || file is! String) continue;
        _urlOf['${entry.key}'] = url;
        // Строка в манифесте — обещание, что файл есть. Обещание без файла читалось бы как готовая
        // озвучка, которой нет, то есть как тишина вместо голоса; проверяем.
        if (File(p.join(dir.path, file)).existsSync()) _fileOf['${entry.key}'] = file;
      }
    } catch (e) {
      // Кэш — удобство, а не зависимость: не поднялся — реплики звучат системным голосом.
      debugPrint('[line-audio] manifest unreadable: $e');
    }
  }

  /// ЗАПОМНИТЬ, ЧТО ЭТО РЕПЛИКИ, и начать докачку недостающих.
  ///
  /// Возвращает управление сразу же — вход в день не ждёт сети. Экран смотрит на [isReady] той
  /// строки, которая вот-вот прозвучит, а не на общий прогресс: ждать чужой файл, чтобы сыграть
  /// свой, — это тишина без причины.
  Future<void> preload(Iterable<LineAudioRef> lines) async {
    await load();

    final wanted = <String, String>{};
    for (final line in lines) {
      final key = keyOf(line.text);
      if (key.isEmpty || line.url.isEmpty) continue;
      wanted[key] = normalizeUrl(line.url);
    }
    if (wanted.isEmpty) return;

    var changed = false;
    for (final entry in wanted.entries) {
      // Голос сменился → другой URL → строка перестаёт быть скачанной, даже если старый файл на
      // диске цел. Играть его за новый голос нельзя: для уха это другая реплика.
      if (_urlOf[entry.key] != entry.value) {
        _urlOf[entry.key] = entry.value;
        _fileOf.remove(entry.key);
        changed = true;
      }
    }
    if (changed) await _save();

    await Future.wait([
      for (final entry in wanted.entries)
        if (!_fileOf.containsKey(entry.key)) _fetch(entry.key, entry.value),
    ]);
  }

  /// ДОКАЧАТЬ ВСЁ, ЧТО ИЗВЕСТНО КАК РЕПЛИКА, НО ЕЩЁ НЕ СКАЧАНО.
  ///
  /// Ровно одна причина существовать: докачка на входе в день — ОДНА попытка, и упавшая попытка не
  /// повторялась ничем. Реплика с адресом и без файла оставалась не готова навсегда, а экран честно
  /// показывал «Готовим озвучку» — вечно, потому что ждать было нечего.
  ///
  /// Обрыв сети на входе в день, 401 на непрогретом токене, файл, вычищенный системой между
  /// запусками, — все они лечатся повтором, и ни один из них не лечился.
  Future<void> retryMissing() async {
    await load();
    final pending = [
      for (final entry in _urlOf.entries)
        if (!_fileOf.containsKey(entry.key)) entry,
    ];
    if (pending.isEmpty) return;

    // Повтор снимает отметку об отказе: пока он идёт, строку снова стоит подождать.
    _failed.removeAll(pending.map((e) => e.key));
    await Future.wait([for (final entry in pending) _fetch(entry.key, entry.value)]);
  }

  Future<void> _fetch(String key, String url) async {
    final loader = _loader;
    if (loader == null) return;
    // Токен спрашивается в момент запроса — внутри загрузчика (см. [_bearer]).
    final result = await loader.load(url);
    if (result.path != null) {
      _fileOf[key] = fileNameOf(url);
      _failed.remove(key);
      await _save();

      return;
    }
    _downloadFailures++;
    _failed.add(key);
    _lastReason = result.reason;
    debugPrint('[line-audio] $url: ${result.reason}');
  }

  Future<void> _save() async {
    final dir = _dir;
    if (dir == null) return;
    try {
      final rows = <String, Map<String, String>>{};
      for (final entry in _urlOf.entries) {
        rows[entry.key] = {
          'url': entry.value,
          if (_fileOf[entry.key] != null) 'file': _fileOf[entry.key]!,
        };
      }
      File(p.join(dir.path, 'manifest.json')).writeAsStringSync(jsonEncode(rows), flush: true);
    } catch (e) {
      debugPrint('[line-audio] manifest unwritable: $e');
    }
  }

  /// Сыграть скачанный файл этой реплики. False — файла нет, зовите системный голос.
  Future<bool> play(String text) async {
    final path = fileFor(text);
    if (path == null) {
      // У реплики ЕСТЬ адрес, а играть нечего — значит она сейчас прозвучит системным голосом,
      // и это дефект, а не режим работы. Строка, которой сервер не озвучивает, сюда не попадает.
      if (_urlOf.containsKey(keyOf(text))) _silentFallbacks++;

      return false;
    }
    try {
      await _channel.invokeMethod<void>('play', {'path': path});
      // Какой файл прозвучал — видно в логе устройства: живой прогон иначе не отличит голос сервера от телефона.
      debugPrint('[line-audio] play «$text» ${p.basename(path)}');

      return true;
    } on PlatformException catch (e) {
      // Файл пропал из-под нас (чистка диска). Забываем и отдаём ход системному голосу — тишины
      // на экране быть не должно ни в одном исходе.
      debugPrint('[line-audio] play failed: ${e.code}');
      _fileOf.remove(keyOf(text));
      _silentFallbacks++;
      _lastReason = 'play: ${e.code}';

      return false;
    } on MissingPluginException {
      return false; // не iOS (тесты, preview) — системный голос
    }
  }

  Future<void> stop() async {
    try {
      await _channel.invokeMethod<void>('stop');
    } on MissingPluginException {
      // nothing to stop
    }
  }
}
