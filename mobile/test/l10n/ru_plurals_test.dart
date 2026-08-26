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
  late AppLocalizations en;

  setUpAll(() async {
    l = await AppLocalizations.delegate.load(const Locale('ru'));
    en = await AppLocalizations.delegate.load(const Locale('en'));
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
    // Was «Повторить N слово/слова/слов» — that button died with кадры 17a–17d, where the day's
    // size is stated once on the session card instead of inside a verb. The paradigm it guarded is
    // the same one, so the guard moved to the string that now carries it.
    test('«N слово/слова/слов» on the session card', () {
      expect(l.homeSessionCardWords(1), '1 слово');
      expect(l.homeSessionCardWords(3), '3 слова');
      expect(l.homeSessionCardWords(5), '5 слов');
    });

    test('«В работе — N слово/слова/слов»', () {
      expect(l.homeInWorkTitle(1), 'В работе — 1 слово');
      expect(l.homeInWorkTitle(2), 'В работе — 2 слова');
      expect(l.homeInWorkTitle(41), 'В работе — 41 слово');
    });

    test('«выпадет через N день/дня/дней»', () {
      expect(l.homeEdgeInDays(1), 'через 1 день');
      expect(l.homeEdgeInDays(2), 'через 2 дня');
      expect(l.homeEdgeInDays(5), 'через 5 дней');
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

  /// The session summary prints the number and its label on two lines, so the label has to agree
  /// with the number above it: the screen said «1 НОВЫХ», «1 ОШИБКИ» and «1 Mistakes» (QA-OBS-12).
  ///
  /// All four counters are plural-shaped, including the two whose word never inflects — that way
  /// the call site cannot print one counter's number under another counter's label.
  group('session summary counters agree with their number', () {
    test('«новое / новых»', () {
      expect(l.sessionStatNew(1), 'Новое');
      expect(l.sessionStatNew(2), 'Новых');
      expect(l.sessionStatNew(5), 'Новых');
      expect(l.sessionStatNew(21), 'Новое'); // «21 новое»
    });

    test('«ошибка / ошибки / ошибок»', () {
      expect(l.sessionStatErrors(1), 'Ошибка');
      expect(l.sessionStatErrors(2), 'Ошибки');
      expect(l.sessionStatErrors(5), 'Ошибок');
      expect(l.sessionStatErrors(11), 'Ошибок');
      expect(l.sessionStatErrors(21), 'Ошибка');
    });

    test('the impersonal two do not inflect — and say so at every count', () {
      for (final n in [1, 2, 5, 21]) {
        expect(l.sessionStatReviewed(n), 'Повторено', reason: 'n = $n');
        expect(l.sessionPracticeStatDone(n), 'Пройдено', reason: 'n = $n');
      }
    });

    test('English: only «Mistake» has a singular', () {
      expect(en.sessionStatErrors(1), 'Mistake');
      expect(en.sessionStatErrors(2), 'Mistakes');
      expect(en.sessionStatNew(1), 'New');
      expect(en.sessionStatReviewed(1), 'Reviewed');
      expect(en.sessionPracticeStatDone(1), 'Practiced');
    });
  });

  /// The set size is exact — the pipeline slices an over-generated batch down to the requested
  /// `size`, and an under-delivery carries its own badge. «Примерно» promised a range that no
  /// longer exists (правка 1.7).
  group('approxWords — a count, not an estimate', () {
    test('ru: «1 слово», «3 слова», «11 слов», no «примерно»', () {
      expect(l.approxWords(1), '1 слово');
      expect(l.approxWords(3), '3 слова');
      expect(l.approxWords(11), '11 слов');
    });

    test('en: «1 word», «8 words», no «about»', () {
      expect(en.approxWords(1), '1 word');
      expect(en.approxWords(8), '8 words');
    });
  });
}
