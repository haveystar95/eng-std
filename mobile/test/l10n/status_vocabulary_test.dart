import 'dart:convert';
import 'dart:io';

import 'package:flutter/widgets.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/practice/learning_ladder.dart';
import 'package:eng_std/data/word_status.dart';
import 'package:eng_std/l10n/app_localizations.dart';

/// Ч.4 — ONE STATUS VOCABULARY, kept by a source guard.
///
/// There used to be four vocabularies for one set of facts, and none of them was wrong on its own
/// screen — that is what made the drift invisible. A vocabulary is not something a review catches
/// twice, so the retired words are pinned here: if «Подтверждено» or «Знакомое» ever come back into
/// the deck, this fails and the reason has to be argued rather than merged.
///
/// It guards the DECK and not the code: the copy is the thing that must agree, and every string the
/// learner reads goes through the ARB files (`no_cyrillic_outside_l10n_test.dart` is what keeps that
/// true). A guard over widget sources would have to be taught what a string literal is.
void main() {
  Map<String, dynamic> arb(String locale) =>
      jsonDecode(File('lib/l10n/app_$locale.arb').readAsStringSync()) as Map<String, dynamic>;

  /// Every VALUE the learner can read — the `@`-prefixed entries are notes to ourselves, and the
  /// word «триаж» is allowed to live there: it is a word for the code, and the code may say it.
  Iterable<String> visibleCopy(String locale) => arb(
    locale,
  ).entries.where((e) => !e.key.startsWith('@')).map((e) => e.value.toString());

  group('retired words are gone from the deck', () {
    const retiredRu = ['Подтверждено', 'Знакомое', 'в каталоге'];

    for (final word in retiredRu) {
      test('«$word» does not appear in any Russian string', () {
        final offenders = arb('ru').entries
            .where((e) => !e.key.startsWith('@') && e.value.toString().contains(word))
            .map((e) => e.key)
            .toList();

        expect(
          offenders,
          isEmpty,
          reason:
              '«$word» belongs to a vocabulary Ч.4 retired. The five words are '
              '«Разобрать», «В работе», «Ступень X из 5», «Освоено», «Отложено».',
        );
      });
    }

    test('«триаж» is a word for the code, never for the screen', () {
      // The jargon rule, and the reason this test reads only the values: the `@`-notes explain the
      // machinery to whoever reads the deck next, and they are allowed to name it.
      final offenders = visibleCopy('ru').where((v) => v.toLowerCase().contains('триаж')).toList();

      expect(offenders, isEmpty, reason: 'the learner reads «Разобрать N слов», never «триаж»');
    });

    test('«triage» is jargon in English too', () {
      final offenders = visibleCopy('en').where((v) => v.toLowerCase().contains('triage')).toList();

      expect(offenders, isEmpty);
    });
  });

  group('the five words exist and are the ones being used', () {
    late AppLocalizations ru;

    setUp(() async => ru = await AppLocalizations.delegate.load(const Locale('ru')));

    test('each status has exactly one word, and they are the canonical five', () {
      expect(wordStatusLabel(ru, WordStatus.toSort), 'Разобрать');
      expect(wordStatusLabel(ru, WordStatus.inWork), 'В работе');
      expect(wordStatusLabel(ru, WordStatus.mastered), 'Освоено');
      expect(wordStatusLabel(ru, WordStatus.paused), 'Отложено');
    });

    test('the ladder is stated as «Ступень X из 5», with the rung named', () {
      expect(ladderPositionLabel(ru, LearningLadder.stepIntro), 'Ступень 1 из 5: знакомство');
      // Both recognition steps are ONE rung to a learner — the count is about how far the word has
      // come, and the direction a recognition was asked in is not that.
      expect(
        ladderPositionLabel(ru, LearningLadder.stepRecognitionForward),
        'Ступень 2 из 5: узнавание',
      );
      expect(
        ladderPositionLabel(ru, LearningLadder.stepRecognitionReverse),
        'Ступень 2 из 5: узнавание',
      );
      expect(ladderPositionLabel(ru, LearningLadder.stepAssembly), 'Ступень 3 из 5: сборка');
      expect(ladderPositionLabel(ru, LearningLadder.stepTyping), 'Ступень 4 из 5: написание');
      expect(ladderPositionLabel(ru, LearningLadder.stepDictation), 'Ступень 5 из 5: диктант');
    });

    test('a word off the ladder has a status and no position', () {
      // «Ступень 1 из 5» for a word that never started would claim something that did not happen.
      expect(ladderPositionLabel(ru, null), isNull);
    });

    test('the density legend speaks the same three words', () {
      expect(ru.collectionDensityMastered(3), contains(wordStatusLabel(ru, WordStatus.mastered)));
      expect(ru.collectionDensityInWork(3), contains(wordStatusLabel(ru, WordStatus.inWork)));
      expect(ru.collectionDensityToSort(3), contains(wordStatusLabel(ru, WordStatus.toSort)));
    });

    test('so does the button that reaches the unsorted words', () {
      expect(ru.collectionTriageButton(7), contains(wordStatusLabel(ru, WordStatus.toSort)));
    });
  });

  group('wordStatusOf — the pool decides, the ladder does not', () {
    test('mastered wins', () {
      expect(
        wordStatusOf(enrolled: true, mastered: true),
        WordStatus.mastered,
      );
    });

    test('in the queue → «В работе»', () {
      expect(wordStatusOf(enrolled: true, mastered: false), WordStatus.inWork);
    });

    test('out of the queue after walking → «Отложено», a pause and not a delete', () {
      expect(
        wordStatusOf(enrolled: false, mastered: false, everStudied: true),
        WordStatus.paused,
      );
    });

    test('out of the queue having never started → «Разобрать»', () {
      expect(wordStatusOf(enrolled: false, mastered: false), WordStatus.toSort);
    });
  });
}
