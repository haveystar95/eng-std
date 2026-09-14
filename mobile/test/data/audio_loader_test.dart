import 'dart:async';
import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:path/path.dart' as p;

import 'package:eng_std/data/audio_loader.dart';

/// ОБЩИЙ ЗАГРУЗЧИК ЗВУКА (наряд DAY-UI-3, §5) — близнец `ImageLoader`, и обещания у него те же.
///
/// Окно дня при открытии просит весь голос дня разом — десятки файлов. Здесь то, что с этим случится:
/// не больше шести на проводе, один запрос на адрес, диск раньше сети, повтор на обрыв и 5xx (но не на
/// 4xx), и ни одного полуфайла под именем целого.
void main() {
  late Directory dir;

  setUp(() => dir = Directory.systemTemp.createTempSync('audio_loader_test'));
  tearDown(() {
    if (dir.existsSync()) dir.deleteSync(recursive: true);
  });

  AudioLoader loader(HttpClientAdapter adapter) =>
      AudioLoader(http: Dio()..httpClientAdapter = adapter, directory: dir, firstBackoff: Duration.zero);

  String url(int i) => 'https://api.test/api/v1/plans/audio/L$i';

  // ПРАВИЛО: не больше шести докачек на проводе, остальные ждут по очереди.
  // ЛОВИТ: `Future.wait` на весь день без очереди — тридцать запросов разом, и iOS держит шесть, а
  // остальные стоят в HTTP-стеке, где их никто не переставит (и таймауты у них тикают).
  test('the whole day at once — never more than six on the wire', () async {
    final wire = _GatedAdapter();
    final audio = loader(wire);

    final all = Future.wait([for (var i = 0; i < 20; i++) audio.load(url(i))]);
    await pumpEventQueue();
    expect(wire.open, AudioLoader.maxParallel);
    expect(audio.active, AudioLoader.maxParallel);

    var peak = wire.open;
    while (wire.pending.isNotEmpty) {
      wire.releaseOne();
      await pumpEventQueue();
      peak = peak > wire.open ? peak : wire.open;
    }
    final loads = await all;

    expect(peak, AudioLoader.maxParallel);
    expect(wire.asked, hasLength(20));
    expect(loads.every((l) => l.path != null && File(l.path!).existsSync()), isTrue);
    expect(audio.active, 0);
  });

  // ПРАВИЛО: один адрес — одна докачка, сколько бы экранов его ни просили.
  // ЛОВИТ: окно, шит и сессия, качающие одну реплику трижды параллельно — и три записи в один файл.
  test('the same address asked three times — one request', () async {
    final wire = _GatedAdapter();
    final audio = loader(wire);

    final three = Future.wait([audio.load(url(1)), audio.load(url(1)), audio.load(url(1))]);
    await pumpEventQueue();
    wire.releaseAll();
    final loads = await three;

    expect(wire.asked, [url(1)]);
    expect(loads.map((l) => l.path).toSet(), hasLength(1));
  });

  // ПРАВИЛО: файл на диске — сеть не трогается; это и есть тёплый вход в день.
  // ЛОВИТ: загрузчик, который перекачивает скачанное на каждом открытии окна.
  test('a file already on disk — no request, timed as from disk', () async {
    File(p.join(dir.path, 'L7.mp3')).writeAsBytesSync([9, 9, 9]);
    final wire = _GatedAdapter();
    final audio = loader(wire);

    final load = await audio.load(url(7));

    expect(wire.asked, isEmpty);
    expect(load.path, p.join(dir.path, 'L7.mp3'));
    expect(audio.timings.single.fromDisk, isTrue);
  });

  // ПРАВИЛО: обрыв и 5xx — повтор с паузой; 4xx — ответ, а не задержка.
  // ЛОВИТ: один пропавший пакет, после которого строка звучит системным голосом до конца дня, — и
  // обратное: три запроса подряд за файлом, которого на сервере нет.
  test('a 503 is retried and then served; a 404 is not retried', () async {
    final flaky = _ScriptedAdapter([503, 503, 200]);
    final served = await loader(flaky).load(url(2));
    expect(flaky.asked, hasLength(1 + AudioLoader.retries));
    expect(served.path, isNotNull);

    final missing = _ScriptedAdapter([404, 200]);
    final absent = await loader(missing).load(url(3));
    expect(missing.asked, hasLength(1));
    expect(absent.path, isNull);
    expect(absent.status, 404);
  });

  // ПРАВИЛО: файл пишется во временный и переименовывается.
  // ЛОВИТ: половину файла под именем целого — плеер сыграл бы обрывок, а кэш считал бы строку готовой.
  test('no part file is left behind', () async {
    final wire = _GatedAdapter();
    final audio = loader(wire);
    final load = audio.load(url(4));
    await pumpEventQueue();
    wire.releaseAll();
    await load;

    expect(dir.listSync().map((f) => p.basename(f.path)), ['L4.mp3']);
  });
}

/// Сеть, которая держит каждый запрос, пока тест его не отпустит, — видно, сколько их на проводе.
class _GatedAdapter implements HttpClientAdapter {
  final List<String> asked = [];
  final List<Completer<ResponseBody>> pending = [];
  int open = 0;

  void releaseOne() {
    final next = pending.removeAt(0);
    open--;
    next.complete(ResponseBody.fromBytes(const [1, 2, 3], 200));
  }

  void releaseAll() {
    while (pending.isNotEmpty) {
      releaseOne();
    }
  }

  @override
  void close({bool force = false}) {}

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<List<int>>? requestStream, Future<void>? cancelFuture) {
    asked.add(options.uri.toString());
    open++;
    final answer = Completer<ResponseBody>();
    pending.add(answer);

    return answer.future;
  }
}

/// Сеть, отвечающая статусами по порядку (последний повторяется).
class _ScriptedAdapter implements HttpClientAdapter {
  _ScriptedAdapter(this.statuses);

  final List<int> statuses;
  final List<String> asked = [];

  @override
  void close({bool force = false}) {}

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<List<int>>? requestStream, Future<void>? cancelFuture) async {
    asked.add(options.uri.toString());
    final status = statuses[(asked.length - 1).clamp(0, statuses.length - 1)];

    return ResponseBody.fromBytes(status == 200 ? const [1, 2, 3] : const [], status);
  }
}
