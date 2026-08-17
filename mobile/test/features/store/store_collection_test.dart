import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/models.dart';

/// StoreCollection JSON parsing, incl. the CEFR level field added after the frozen contract. The
/// client reads `cefr`, falling back to `level`, so it renders whichever key the store feed ships.
void main() {
  Map<String, dynamic> base() => {
        'id': 'c1',
        'title': 'Собеседование',
        'description': 'Вопросы рекрутера',
        'topic': 'Работа и карьера',
        'source_lang': 'ru',
        'target_lang': 'en',
        'is_premium': true,
        'is_subscribed': false,
        'items_count': 22,
        'image_url': 'https://x/i.jpg',
      };

  test('parses the core fields + topic', () {
    final c = StoreCollection.fromJson(base());
    expect(c.id, 'c1');
    expect(c.title, 'Собеседование');
    expect(c.topic, 'Работа и карьера');
    expect(c.isPremium, isTrue);
    expect(c.isSubscribed, isFalse);
    expect(c.itemsCount, 22);
    expect(c.imageUrl, 'https://x/i.jpg');
  });

  test('reads the level from `cefr`', () {
    final c = StoreCollection.fromJson({...base(), 'cefr': 'B1–B2'});
    expect(c.cefr, 'B1–B2');
  });

  test('falls back to `level` when `cefr` is absent', () {
    final c = StoreCollection.fromJson({...base(), 'level': 'A2'});
    expect(c.cefr, 'A2');
  });

  test('cefr is null when neither key is present', () {
    expect(StoreCollection.fromJson(base()).cefr, isNull);
  });

  test('missing optional fields degrade to safe defaults', () {
    final c = StoreCollection.fromJson({'id': 'c2', 'title': 'X', 'source_lang': 'ru', 'target_lang': 'en'});
    expect(c.itemsCount, 0);
    expect(c.isPremium, isFalse);
    expect(c.topic, isNull);
    expect(c.cefr, isNull);
  });
}
