import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/l10n/app_localizations.dart';

/// Russian plural forms, where the CASE is not the counting one (QA-OBS-5).
///
/// «{done} из {total} слов» reads through the preposition «из», which governs the genitive — so the
/// forms are not the ones a bare count takes. The ARB had the counting paradigm («1 из 1 слово»,
/// «2 из 3 слова») and only the `many` form, the one that happens to coincide, was ever seen on a
/// screen. The neighbouring plural strings were re-read for the same class of error; they are all
/// bare counts («Повторить 3 слова», «через 5 дней») and correct, and a couple of them are pinned
/// here so that stays true.
void main() {
  late AppLocalizations l;

  setUpAll(() async {
    l = await AppLocalizations.delegate.load(const Locale('ru'));
  });

  group('homeCollectionProgress — «из» takes the genitive', () {
    test('one: «1 из 1 слова», never «слово»', () {
      expect(l.homeCollectionProgress(1, 1), '1 из 1 слова');
      expect(l.homeCollectionProgress(0, 21), '0 из 21 слова');
    });

    test('few: «2 из 3 слов», never «слова»', () {
      expect(l.homeCollectionProgress(2, 3), '2 из 3 слов');
      expect(l.homeCollectionProgress(1, 4), '1 из 4 слов');
    });

    test('many: unchanged — this is the form the screen happened to show', () {
      expect(l.homeCollectionProgress(4, 5), '4 из 5 слов');
      expect(l.homeCollectionProgress(18, 24), '18 из 24 слов');
    });

    test('the category is chosen by the TOTAL, not by the done count', () {
      expect(l.homeCollectionProgress(3, 5), '3 из 5 слов');
      expect(l.homeCollectionProgress(5, 1), '5 из 1 слова');
    });
  });

  group('the neighbours: bare counts, counting forms', () {
    test('«Повторить N слово/слова/слов»', () {
      expect(l.homeReviewButton(1), 'Повторить 1 слово');
      expect(l.homeReviewButton(3), 'Повторить 3 слова');
      expect(l.homeReviewButton(5), 'Повторить 5 слов');
    });

    test('«N слово/слова/слов» in a collection', () {
      expect(l.collectionWordsCount(1), '1 слово');
      expect(l.collectionWordsCount(2), '2 слова');
      expect(l.collectionWordsCount(11), '11 слов');
    });

    test('«через N день/дня/дней»', () {
      expect(l.sessionDueInDays(1), 'через 1 день');
      expect(l.sessionDueInDays(2), 'через 2 дня');
      expect(l.sessionDueInDays(7), 'через 7 дней');
    });
  });
}
