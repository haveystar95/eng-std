import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/line_audio.dart';
import 'package:eng_std/data/pronouncer.dart';

/// ПОДАЧА И ТЕМП (наряд TTS-1, Ч.2.2 и Ч.2.3).
///
/// Три вещи, и все три решаются в одном месте — [Pronouncer.speakText] — именно потому, что
/// вызывающих шесть, и правило, размазанное по ним, забудут в одном:
///
///   реплика, у которой есть файл, играется ФАЙЛОМ и не трогает синтезатор вообще;
///   реплика без файла читается синтезатором, но СВОИМ темпом — ниже темпа слов (канон §7);
///   слово, которое не реплика, читается как читалось.
void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  const tts = MethodChannel('flutter_tts');
  const player = MethodChannel('com.denis.engstd/line_audio');

  late List<MethodCall> ttsCalls;
  late List<MethodCall> playerCalls;
  late Directory dir;

  setUp(() {
    ttsCalls = [];
    playerCalls = [];
    dir = Directory.systemTemp.createTempSync('line_tempo_test');

    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger.setMockMethodCallHandler(
      tts,
      (call) async {
        ttsCalls.add(call);

        return 1;
      },
    );
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger.setMockMethodCallHandler(
      player,
      (call) async {
        playerCalls.add(call);

        return null;
      },
    );
  });

  tearDown(() {
    final messenger = TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger;
    messenger.setMockMethodCallHandler(tts, null);
    messenger.setMockMethodCallHandler(player, null);
    if (dir.existsSync()) dir.deleteSync(recursive: true);
  });

  double? rateOf(List<MethodCall> calls) {
    for (final call in calls.reversed) {
      if (call.method == 'setSpeechRate') return (call.arguments as num).toDouble();
    }

    return null;
  }

  Dio serving(List<int> bytes) {
    final dio = Dio();
    dio.httpClientAdapter = _Bytes(bytes);

    return dio;
  }

  test('a line with a file is played from it, and the synthesiser is never asked', () async {
    final cache = LineAudioCache(http: serving([1, 2, 3]), directory: dir);
    await cache.preload([(text: 'Thanks for joining today.', url: 'https://x/api/v1/audio/lines/A.mp3')]);

    ttsCalls.clear();
    playerCalls.clear();
    await Pronouncer(null, cache).speakText('Thanks for joining today.', targetLang: 'en');

    expect(playerCalls.map((c) => c.method), contains('play'));
    expect(ttsCalls.map((c) => c.method), isNot(contains('speak')));
  });

  test('a line WITHOUT a file is read by the phone, slower than a word', () async {
    final cache = LineAudioCache(http: _refusing(), directory: dir);
    await cache.preload([(text: 'Could you briefly introduce yourself?', url: 'https://x/api/v1/audio/lines/B.mp3')]);

    ttsCalls.clear();
    await Pronouncer(null, cache).speakText('Could you briefly introduce yourself?', targetLang: 'en');
    final lineRate = rateOf(ttsCalls);

    ttsCalls.clear();
    await Pronouncer(null, cache).speakText('reservation', targetLang: 'en');
    final wordRate = rateOf(ttsCalls);

    // Канон §7 одной строкой: «темп реплик ниже темпа слов». Синтезатор всё-таки говорит — тишины
    // вместо голоса не бывает ни в одном исходе.
    expect(ttsCalls.map((c) => c.method), contains('speak'));
    expect(lineRate, isNotNull);
    expect(wordRate, isNotNull);
    expect(lineRate!, lessThan(wordRate!));
  });

  test('a line the server does NOT voice is still read at the line tempo', () async {
    // Труба выключена — файлов нет вовсе, а вопрос в восемь слов всё равно не должен звучать со
    // скоростью одиночного слова (канон §7). Посадка называет свои реплики отдельно.
    final cache = LineAudioCache(http: _refusing(), directory: dir);
    cache.note(const ['What were your main responsibilities in your last role?']);

    ttsCalls.clear();
    await Pronouncer(null, cache)
        .speakText('What were your main responsibilities in your last role?', targetLang: 'en');
    final lineRate = rateOf(ttsCalls);

    ttsCalls.clear();
    await Pronouncer(null, cache).speakText('responsibilities', targetLang: 'en');

    expect(playerCalls, isEmpty);
    expect(lineRate, isNotNull);
    expect(lineRate!, lessThan(rateOf(ttsCalls)!));
  });

  test('with no cache at all the app speaks exactly as it did before the наряд', () async {
    await Pronouncer().speakText('Thanks for joining today.', targetLang: 'en');

    expect(playerCalls, isEmpty);
    expect(ttsCalls.map((c) => c.method), contains('speak'));
  });


  test('a line that HAD an address and fell back to the phone is counted, not swallowed', () async {
    // ЖИВОЙ ДЕФЕКТ TTS-1: сервер отдавал `http://`, iOS резал запрос по ATS, докачка падала — и все
    // реплики звучали системным голосом, молча, на всех экранах сразу. Тихий фолбэк ПРИ ЖИВОМ
    // адресе — дефект, и в дев-сборке он обязан быть виден. Здесь пинается счётчик под бейджем.
    final cache = LineAudioCache(http: _refusing(), directory: dir);
    await cache.preload([(text: 'Thanks for joining today.', url: 'https://x/api/v1/audio/lines/A.mp3')]);

    expect(cache.trouble.downloads, greaterThan(0));
    expect(cache.trouble.lastReason, isNotNull);

    await Pronouncer(null, cache).speakText('Thanks for joining today.', targetLang: 'en');

    expect(cache.trouble.silentFallbacks, 1);
    expect(ttsCalls.map((c) => c.method), contains('speak'));
  });

  test('a line the server never voiced is NOT counted as trouble', () async {
    // Слово, связка, реплика при выключенной трубе — им нечем было прозвучать файлом, и считать
    // это поломкой значит утопить настоящую поломку в шуме.
    final cache = LineAudioCache(http: _refusing(), directory: dir);
    cache.note(const ['Could you repeat that, please?']);

    await Pronouncer(null, cache).speakText('Could you repeat that, please?', targetLang: 'en');

    expect(cache.trouble.silentFallbacks, 0);
    expect(cache.trouble.downloads, 0);
  });

}

Dio _refusing() {
  final dio = Dio();
  dio.httpClientAdapter = _Bytes(const [], status: 503);

  return dio;
}

class _Bytes implements HttpClientAdapter {
  _Bytes(this.bytes, {this.status = 200});

  final List<int> bytes;
  final int status;

  @override
  void close({bool force = false}) {}

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<List<int>>? requestStream,
    Future<void>? cancelFuture,
  ) async => ResponseBody.fromBytes(bytes, status);
}
