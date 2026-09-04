import 'dart:convert';
import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:path/path.dart' as p;

import 'package:eng_std/data/line_audio.dart';

/// СЕРВЕРНАЯ ОЗВУЧКА РЕПЛИК на телефоне (наряд TTS-1, Ч.2.1).
///
/// Пять обещаний, и каждое — про то, что человек услышит:
///   реплика, чей файл ещё не приехал, НЕ считается готовой — «Готовим озвучку» стоит именно на
///   этом, и без него экран прочитал бы её системным голосом «пока что» (канон §7);
///   строка, которую сервер не озвучивает, готова всегда — ей нечего ждать;
///   скачанное живёт между запусками, поэтому повторный вход в день не ходит в сеть;
///   смена голоса меняет URL, и старый файл перестаёт считаться этой репликой;
///   оборванная докачка не оставляет в кэше половину файла под именем целого.
void main() {
  late Directory dir;

  setUp(() => dir = Directory.systemTemp.createTempSync('line_audio_test'));
  tearDown(() {
    if (dir.existsSync()) dir.deleteSync(recursive: true);
  });

  /// A Dio that serves bytes for every URL — the network, without one.
  Dio serving(List<int> bytes, {int status = 200, void Function(String url)? onGet}) {
    final dio = Dio();
    dio.httpClientAdapter = _FakeAdapter(bytes, status, onGet);

    return dio;
  }

  LineAudioCache cache({Dio? http}) =>
      LineAudioCache(http: http ?? serving([1, 2, 3]), directory: dir);

  test('a line whose file has not arrived is NOT ready', () async {
    final c = cache(http: serving([], status: 503));
    await c.preload([(text: 'Thanks for joining today.', url: 'https://x/api/v1/audio/lines/A.mp3')]);

    // Это и есть кадр DL·08: реплика известна, файла нет, на слух её не подают.
    expect(c.knows('Thanks for joining today.'), isTrue);
    expect(c.isReady('Thanks for joining today.'), isFalse);
  });

  test('упавшая докачка повторяется — иначе «Готовим озвучку» стоит навсегда', () async {
    // Одна попытка на входе в день была ЕДИНСТВЕННОЙ: 503, обрыв сети или 401 на непрогретом
    // токене оставляли реплику не готовой до конца посадки, а экран честно ждал того, чего никто
    // больше не просил (доп. к наряду DAY-2-FIX).
    const line = 'Tell me a little about your background.';
    const url = 'https://x/api/v1/audio/lines/A.mp3';

    // Сеть, которая падает один раз и потом работает, — то, что и происходит на телефоне.
    final asked = <String>[];
    final flaky = Dio();
    flaky.httpClientAdapter = _FlakyAdapter([1, 2, 3], failFirst: 1, onGet: asked.add);

    final c = cache(http: flaky);
    await c.preload([(text: line, url: url)]);
    expect(c.isReady(line), isFalse);
    expect(c.trouble.downloads, 1);

    await c.retryMissing();

    expect(asked, [url, url]);
    expect(c.isReady(line), isTrue);
  });

  test('a line the server does not voice is ready the moment it is asked about', () async {
    final c = cache();

    // Слово, связка, что угодно вне полки реплик: его всегда собирался читать телефон.
    expect(c.knows('reservation'), isFalse);
    expect(c.isReady('reservation'), isTrue);
  });

  test('matches the same line however it is cased or padded', () async {
    final c = cache();
    await c.preload([(text: 'Thanks for joining today.', url: 'https://x/api/v1/audio/lines/A.mp3')]);

    // Одна реплика приезжает пузырём, карточкой и шпаргалкой — совпадать они обязаны как строки.
    expect(c.fileFor('  thanks for joining TODAY. '), isNotNull);
  });

  test('what was downloaded yesterday is playable today, with no network at all', () async {
    final first = cache();
    await first.preload([(text: 'Could you repeat that, please?', url: 'https://x/api/v1/audio/lines/B.mp3')]);
    expect(first.isReady('Could you repeat that, please?'), isTrue);

    // Второй запуск: та же папка, сети нет вообще (адаптер отвечает 503).
    final second = LineAudioCache(http: serving([], status: 503), directory: dir);
    await second.load();

    expect(second.isReady('Could you repeat that, please?'), isTrue);
    expect(second.fileFor('Could you repeat that, please?'), isNotNull);
  });

  test('a new voice is a new address, and the old file stops being this line', () async {
    final c = cache();
    await c.preload([(text: 'Hi, can you hear me clearly?', url: 'https://x/api/v1/audio/lines/OLD.mp3')]);
    expect(c.fileFor('Hi, can you hear me clearly?'), endsWith('OLD.mp3'));

    // Пакет сменил голос → сервер отдал другой id. Старый файл на диске цел, но играть его за новый
    // голос нельзя: для уха это другая реплика.
    final offline = LineAudioCache(http: serving([], status: 503), directory: dir);
    await offline.preload([(text: 'Hi, can you hear me clearly?', url: 'https://x/api/v1/audio/lines/NEW.mp3')]);

    expect(offline.knows('Hi, can you hear me clearly?'), isTrue);
    expect(offline.isReady('Hi, can you hear me clearly?'), isFalse);
    expect(offline.fileFor('Hi, can you hear me clearly?'), isNull);
  });

  test('downloads each address once, however many lines share it', () async {
    final seen = <String>[];
    final c = cache(http: serving([9, 9], onGet: seen.add));

    await c.preload([
      (text: 'One moment, let me check.', url: 'https://x/api/v1/audio/lines/C.mp3'),
      (text: 'One moment, let me check.', url: 'https://x/api/v1/audio/lines/C.mp3'),
    ]);
    await c.preload([(text: 'One moment, let me check.', url: 'https://x/api/v1/audio/lines/C.mp3')]);

    expect(seen, hasLength(1));
  });

  test('a refused download leaves no half file behind', () async {
    final c = cache(http: serving([], status: 404));
    await c.preload([(text: 'Is my video visible?', url: 'https://x/api/v1/audio/lines/D.mp3')]);

    expect(File(p.join(dir.path, 'D.mp3')).existsSync(), isFalse);
    expect(c.isReady('Is my video visible?'), isFalse);
  });

  test('the manifest names a file only while the file is actually there', () async {
    final c = cache();
    await c.preload([(text: 'Should I rejoin?', url: 'https://x/api/v1/audio/lines/E.mp3')]);
    File(p.join(dir.path, 'E.mp3')).deleteSync();

    // Диск почистили. Строка в манифесте осталась — и обещание без файла читается как готовая
    // озвучка, которой нет, то есть как тишина. Поэтому запись перечитывается по факту.
    final reopened = LineAudioCache(http: serving([], status: 503), directory: dir);
    await reopened.load();

    expect(reopened.knows('Should I rejoin?'), isTrue);
    expect(reopened.isReady('Should I rejoin?'), isFalse);
    expect(jsonDecode(File(p.join(dir.path, 'manifest.json')).readAsStringSync()), isA<Map>());
  });
}

/// Отдаёт одни и те же байты на любой GET — сеть, которой нет.
class _FakeAdapter implements HttpClientAdapter {
  _FakeAdapter(this.bytes, this.status, this.onGet);

  final List<int> bytes;
  final int status;
  final void Function(String url)? onGet;

  @override
  void close({bool force = false}) {}

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<List<int>>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    onGet?.call(options.uri.toString());

    return ResponseBody.fromBytes(bytes, status);
  }
}

/// Сеть, которая падает первые [failFirst] раз и потом работает.
class _FlakyAdapter implements HttpClientAdapter {
  _FlakyAdapter(this.bytes, {required this.failFirst, this.onGet});

  final List<int> bytes;
  final int failFirst;
  final void Function(String url)? onGet;
  int _seen = 0;

  @override
  void close({bool force = false}) {}

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<List<int>>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    onGet?.call(options.uri.toString());
    _seen++;

    return _seen <= failFirst
        ? ResponseBody.fromBytes(const [], 503)
        : ResponseBody.fromBytes(bytes, 200);
  }
}
