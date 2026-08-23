import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/models.dart';

WordCollection _collection({
  bool isDefault = false,
  String type = 'custom',
  bool subscribed = false,
}) => WordCollection(
  id: '01KZETAAA50EMHCN6SP80T8DHC',
  title: isDefault ? 'Сохранённые' : 'Работа',
  source: 'user',
  type: type,
  wordsCount: 3,
  sourceLang: 'ru',
  targetLang: 'en',
  isSubscribed: subscribed,
  isDefault: isDefault,
);

void main() {
  group('the default folder', () {
    test('is an ordinary editable collection in every way but deletion', () {
      final saved = _collection(isDefault: true);

      // The flag buys exactly two behaviours and no more: the shelf hides its delete action, and
      // the save confirmation can name it. Everything else — practice, rename, adding words — is
      // the ordinary custom-collection path.
      expect(saved.isDefault, isTrue);
      expect(saved.isOwned, isTrue);
      expect(saved.readOnly, isFalse);
    });

    test('is not confused with a store deck, which is read-only for a different reason', () {
      final store = _collection(type: 'system');

      expect(store.isDefault, isFalse);
      expect(store.readOnly, isTrue);
    });

    test(
      'defaults to false when the server says nothing — an old mirror is not the default folder',
      () {
        final parsed = WordCollection.fromJson({
          'id': '01KZETAAA50EMHCN6SP80T8DHC',
          'title': 'Работа',
          'items_count': 0,
        });

        expect(parsed.isDefault, isFalse);
      },
    );

    test('reads the flag, never the title — the owner may have renamed it', () {
      final renamed = WordCollection.fromJson({
        'id': '01KZETAAA50EMHCN6SP80T8DHC',
        'title': 'Мои находки',
        'items_count': 2,
        'is_default': true,
      });

      expect(renamed.isDefault, isTrue);
      expect(renamed.title, 'Мои находки');
    });
  });

  group('move targets', () {
    // The rule the picker applies, stated where it can be checked without a widget: own, editable
    // folders, minus the one the word is already in. A store deck is a catalogue nobody can put a
    // word into, and offering the current folder would be a move to nowhere.
    List<WordCollection> targets(List<WordCollection> all, String current) =>
        all.where((c) => c.isOwned && !c.isSubscribed && c.id != current).toList();

    test('excludes the current folder, store decks and subscriptions', () {
      final mine = _collection();
      final other = WordCollection(
        id: '01KZETAAB50EMHCN6SP80T8DHC',
        title: 'Банк',
        source: 'user',
        type: 'custom',
        wordsCount: 0,
        sourceLang: 'ru',
        targetLang: 'en',
      );
      final store = WordCollection(
        id: '01KZETAAC50EMHCN6SP80T8DHC',
        title: 'Аэропорт',
        source: 'curated',
        type: 'system',
        wordsCount: 20,
        sourceLang: 'ru',
        targetLang: 'en',
      );

      expect(targets([mine, other, store], mine.id).map((c) => c.id), [other.id]);
    });

    test('the default folder IS a legitimate destination', () {
      final saved = _collection(isDefault: true);
      final other = WordCollection(
        id: '01KZETAAB50EMHCN6SP80T8DHC',
        title: 'Банк',
        source: 'user',
        type: 'custom',
        wordsCount: 0,
        sourceLang: 'ru',
        targetLang: 'en',
      );

      expect(targets([saved, other], other.id).map((c) => c.id), [saved.id]);
    });
  });
}
