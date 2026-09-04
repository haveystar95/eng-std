import 'dart:async';
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

  test('a line whose file is still on its way is NOT ready', () async {
    // Кадр DL·08 стоит ровно на этом состоянии: файл ЕДЕТ. «Упал» — состояние другое, и с него
    // экран обязан сходить на системный голос, а не ждать (см. тест про 401 ниже).
    final pending = Dio();
    pending.httpClientAdapter = _HangingAdapter();
    final c = cache(http: pending);
    unawaited(c.preload([(text: 'Thanks for joining today.', url: 'https://x/api/v1/audio/lines/A.mp3')]));
    await Future<void>.delayed(Duration.zero);

    expect(c.knows('Thanks for joining today.'), isTrue);
    expect(c.isReady('Thanks for joining today.'), isFalse);
  });

  test('сохранённый присест с `http://` не роняет токен на редиректе', () async {
    // ПЕРВОПРИЧИНА живого `401`: присест долговечен, его пейлоад лежит на диске целиком — вместе с
    // абсолютными адресами, сгенерированными до того, как сервер научился доверять
    // `X-Forwarded-Proto`. ngrok отвечает на `http` редиректом `307`, `dart:io` его идёт и СРЕЗАЕТ
    // `Authorization` — до сервера доезжает анонимный запрос.
    const base = 'https://greedily-thermos-finer.ngrok-free.dev';
    expect(
      LineAudioCache.normalizeUrl('$base/api/v1/audio/lines/A.mp3'.replaceFirst('https', 'http'), apiBase: base),
      '$base/api/v1/audio/lines/A.mp3',
    );
    // Уже https — не трогаем; чужой хост — тем более (это не наш токен и не наша схема).
    expect(LineAudioCache.normalizeUrl('$base/x.mp3', apiBase: base), '$base/x.mp3');
    expect(LineAudioCache.normalizeUrl('http://cdn.example/x.mp3', apiBase: base), 'http://cdn.example/x.mp3');
    // Локальный http-стенд остаётся http: приводить не к чему.
    expect(
      LineAudioCache.normalizeUrl('http://localhost:8001/x.mp3', apiBase: 'http://localhost:8001'),
      'http://localhost:8001/x.mp3',
    );
  });

  test('докачка идёт С ТОКЕНОМ, и токен спрашивается в момент запроса', () async {
    // ЖИВОЙ ДЕФЕКТ: все десять файлов посадки ушли `401`, и в логе сервера у этих запросов нет
    // ключа `authorization` ВООБЩЕ, хотя соседние вызовы API в ту же минуту прошли с токеном.
    // Токен приезжал сюда СТРОКОЙ, снятой на входе в день; снимок — это одно мгновение.
    final seen = <String?>[];
    final dio = Dio();
    dio.httpClientAdapter = _HeaderSpyAdapter(seen);

    // Токена ещё нет — ровно то мгновение, на котором снимок и ломался.
    String? token;
    final c = LineAudioCache(http: dio, directory: dir, bearer: () => token);

    await c.preload([(text: 'A line.', url: 'https://x/api/v1/audio/lines/A.mp3')]);
    expect(seen, [null]);

    // Токен поднялся из кейчейна ПОСЛЕ входа в день — повтор обязан его подхватить, а не
    // повторить то же мгновение.
    token = 'T0K3N';
    await c.retryMissing();

    expect(seen, [null, 'Bearer T0K3N']);
  });

  test('401 — реплика не ждёт: она сразу считается готовой и звучит системным голосом', () async {
    // Живьём бейдж показал «0 системным, 10 не скачалось, http 401»: ноль означал, что реплики
    // ПРОСТО МОЛЧАЛИ — экран ждал файла, которого уже не будет. Упавшая докачка честна сразу.
    const line = 'Could you tell me about your background?';
    final c = cache(http: serving([], status: 401));
    await c.preload([(text: line, url: 'https://x/api/v1/audio/lines/A.mp3')]);

    expect(c.trouble.downloads, 1);
    expect(c.trouble.lastReason, 'http 401');
    expect(c.hasFailed(line), isTrue);
    // Готова — то есть «произноси»: файла не будет, читает телефон.
    expect(c.isReady(line), isTrue);

    // …и это засчитывается тихим фолбэком, потому что адрес у реплики БЫЛ.
    expect(await c.play(line), isFalse);
    expect(c.trouble.silentFallbacks, 1);
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
    expect(c.fileFor(line), isNull);
    expect(c.trouble.downloads, 1);

    await c.retryMissing();

    expect(asked, [url, url]);
    expect(c.fileFor(line), isNotNull);
    expect(c.hasFailed(line), isFalse);
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
    final pending = Dio();
    pending.httpClientAdapter = _HangingAdapter();
    final offline = LineAudioCache(http: pending, directory: dir);
    unawaited(offline.preload([(text: 'Hi, can you hear me clearly?', url: 'https://x/api/v1/audio/lines/NEW.mp3')]));
    await Future<void>.delayed(Duration.zero);

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
    expect(c.fileFor('Is my video visible?'), isNull);
    // …и реплика не ждёт того, чего не будет: 404 — это отказ, а не задержка.
    expect(c.hasFailed('Is my video visible?'), isTrue);
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

/// Запоминает заголовок `Authorization` каждого запроса. Первый — тот, на котором живой прогон и
/// сломался: заголовка не было вовсе.
class _HeaderSpyAdapter implements HttpClientAdapter {
  _HeaderSpyAdapter(this.seen);

  final List<String?> seen;

  @override
  void close({bool force = false}) {}

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<List<int>>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    seen.add(options.headers['Authorization'] as String?);

    // Без токена сервер отвечает 401, как отвечал живьём; с токеном отдаёт файл.
    return options.headers['Authorization'] == null
        ? ResponseBody.fromBytes(const [], 401)
        : ResponseBody.fromBytes(const [1, 2, 3], 200);
  }
}

/// Докачка, которая НЕ ОТВЕЧАЕТ: состояние «файл ещё едет», ради которого кадр DL·08 и нарисован.
class _HangingAdapter implements HttpClientAdapter {
  @override
  void close({bool force = false}) {}

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<List<int>>? requestStream,
    Future<void>? cancelFuture,
  ) => Completer<ResponseBody>().future;
}
