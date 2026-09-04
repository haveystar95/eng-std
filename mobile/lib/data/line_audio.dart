/// СЕРВЕРНАЯ ОЗВУЧКА РЕПЛИК на телефоне — докачка, кэш и подача (наряд TTS-1, Ч.2).
///
/// Канон `../backend2/docs/plan-dialogue.md` §7: ярус «понимаю», такт 1, прогон и разогрев стоят на
/// слушании, темп реплик ниже темпа слов, и «никакая реплика не подаётся на слух, пока озвучка не
/// готова». Системный синтез остаётся голосом СЛОВ и связок; реплики играет файл.
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
  LineAudioCache({Dio? http, MethodChannel? channel, Directory? directory})
    : _http = http ?? Dio(),
      _channel = channel ?? const MethodChannel('com.denis.engstd/line_audio'),
      _given = directory;

  final Dio _http;
  final MethodChannel _channel;

  /// Куда класть файлы, если каталог задан снаружи. В приложении — null (спрашиваем систему); в
  /// тестах — временный каталог, ровно как у [ImageDiskCache], и по той же причине: спрашивать
  /// `path_provider` в тесте значит проверять мок, а не кэш.
  final Directory? _given;

  /// `нормализованный текст` → адрес файла на сервере. Известно, что это РЕПЛИКА.
  final Map<String, String> _urlOf = {};

  /// `нормализованный текст` → имя файла в кэше. Известно, что реплика УЖЕ СКАЧАНА.
  final Map<String, String> _fileOf = {};

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

  /// Скачивания, идущие прямо сейчас, — чтобы вход в день дважды не качал один файл дважды.
  final Set<String> _inFlight = {};

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

  /// Имя файла — id строки озвучки из URL. Смена голоса даёт новый id, поэтому новый файл встаёт
  /// рядом со старым, а не поверх него: «старый кэш не играется за новый голос» держится именем.
  static String fileNameOf(String url) {
    final name = p.basename(Uri.parse(url).path);

    return name.contains('.') ? name : '$name.mp3';
  }

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

  /// Готова ли реплика к подаче на слух. Строка, которую кэш не знает, готова всегда: она не
  /// реплика, её читает системный синтез, и ждать ей нечего.
  bool isReady(String text) {
    final key = keyOf(text);

    return !_urlOf.containsKey(key) || _fileOf.containsKey(key);
  }

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
  Future<void> preload(Iterable<LineAudioRef> lines, {String? bearer}) async {
    await load();

    final wanted = <String, String>{};
    for (final line in lines) {
      final key = keyOf(line.text);
      if (key.isEmpty || line.url.isEmpty) continue;
      wanted[key] = line.url;
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
        if (!_fileOf.containsKey(entry.key)) _fetch(entry.key, entry.value, bearer),
    ]);
  }

  Future<void> _fetch(String key, String url, String? bearer) async {
    final dir = _dir;
    if (dir == null || !_inFlight.add(url)) return;

    final name = fileNameOf(url);
    final target = File(p.join(dir.path, name));
    try {
      if (target.existsSync() && target.lengthSync() > 0) {
        _fileOf[key] = name;
        await _save();

        return;
      }

      final response = await _http.get<List<int>>(
        url,
        options: Options(
          responseType: ResponseType.bytes,
          headers: bearer == null ? null : {'Authorization': 'Bearer $bearer'},
          // 404 здесь — не исключение, а ответ «файла нет»: строка озвучки могла уехать вместе со
          // сменой голоса между сборкой посадки и докачкой.
          validateStatus: (code) => code != null && code < 500,
        ),
      );
      final bytes = response.data;
      if (response.statusCode != 200 || bytes == null || bytes.isEmpty) {
        _downloadFailures++;
        _lastReason = 'http ${response.statusCode}';

        return;
      }

      // Пишем во временный файл и переименовываем: оборванная докачка не должна оставить в кэше
      // половину файла под именем целого.
      final tmp = File('${target.path}.part');
      tmp.writeAsBytesSync(bytes, flush: true);
      tmp.renameSync(target.path);

      _fileOf[key] = name;
      await _save();
    } catch (e) {
      _downloadFailures++;
      // Первая строка причины: у Dio дальше идёт абзац про статус-коды, который в бейдж не влезет
      // и ничего не добавляет.
      _lastReason = e.toString().split('\n').first;
      debugPrint('[line-audio] $url: $e');
    } finally {
      _inFlight.remove(url);
    }
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
