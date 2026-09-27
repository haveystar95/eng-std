import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:path/path.dart' as p;

import 'package:eng_std/data/start/account_device_store.dart';

/// WHAT THE PHONE REMEMBERS ABOUT AN ACCOUNT, AND WHAT A REINSTALL FORGETS (work order CLIENT-START §§2, 3, 5 and the
/// delivery line «удали приложение и поставь заново — первый запуск с нуля»).
void main() {
  group('AccountDeviceStore', () {
    // ЛОВИТ: флаг «листы показаны» на телефон вместо аккаунта.
    test('листы — по id аккаунта: один видел, другой нет', () async {
      final store = AccountDeviceStore(MemoryKeyValue());
      await store.markIntroSeen('A');

      expect(await store.introSeen('A'), isTrue);
      expect(await store.introSeen('B'), isFalse);
    });

    // ЛОВИТ: «Вход через …» чужой двери, и строка двери у аккаунта, про которого телефон ничего не знает.
    test('дверь — своя у каждого аккаунта; неизвестная — null', () async {
      final store = AccountDeviceStore(MemoryKeyValue());
      await store.setDoor('A', SignInDoor.apple);
      await store.setDoor('B', SignInDoor.google);

      expect(await store.door('A'), SignInDoor.apple);
      expect(await store.door('B'), SignInDoor.google);
      expect(await store.door('C'), isNull);
    });

    // ЛОВИТ: имя из пробелов, которое затёрло бы имя аккаунта пустотой.
    test('имя — без краевых пробелов; пустое — как не заданное', () async {
      final kv = MemoryKeyValue();
      final store = AccountDeviceStore(kv);
      await store.setDisplayName('A', '  Ден  ');
      expect(await store.displayName('A'), 'Ден');

      kv.values['display_name:B'] = '   ';
      expect(await store.displayName('B'), isNull);
    });
  });

  group('InstallMarker — новая установка начинается без входа', () {
    late Directory dir;
    setUp(() => dir = Directory.systemTemp.createTempSync('install-marker'));
    tearDown(() => dir.deleteSync(recursive: true));

    // ЛОВИТ: переустановку, которая возвращается «вошедшей» по токену из связки ключей и пропускает заставку, вход и
    // листы.
    test('ни метки, ни базы — новая установка: сессия сброшена, метка записана', () async {
      var dropped = 0;
      final fresh = await InstallMarker(directory: () async => dir).check(dropSession: () async => dropped++);

      expect(fresh, isTrue);
      expect(dropped, 1);
      expect(File(p.join(dir.path, 'install.marker')).existsSync(), isTrue);
    });

    // ЛОВИТ: обновление поверх сборки без метки, которое выкинуло бы Дена из аккаунта.
    test('метки нет, база есть — обновление: сессия остаётся, метка записана', () async {
      File(p.join(dir.path, 'wordtrainer.sqlite')).writeAsStringSync('');
      var dropped = 0;
      final fresh = await InstallMarker(directory: () async => dir).check(dropSession: () async => dropped++);

      expect(fresh, isFalse);
      expect(dropped, 0);
      expect(File(p.join(dir.path, 'install.marker')).existsSync(), isTrue);
    });

    test('метка есть — обычный запуск: ничего не трогаем', () async {
      File(p.join(dir.path, 'install.marker')).writeAsStringSync('x');
      var dropped = 0;
      final fresh = await InstallMarker(directory: () async => dir).check(dropSession: () async => dropped++);

      expect(fresh, isFalse);
      expect(dropped, 0);
    });

    // ЛОВИТ: запуск, который встал бы из-за недоступной папки приложения.
    test('папки нет (тест, не iOS) — решать нечего, запуск не блокируется', () async {
      var dropped = 0;
      final fresh = await InstallMarker(directory: () async => throw StateError('no container')).check(
        dropSession: () async => dropped++,
      );

      expect(fresh, isFalse);
      expect(dropped, 0);
    });
  });
}
