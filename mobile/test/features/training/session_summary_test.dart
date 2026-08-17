import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/models.dart';
import 'package:eng_std/features/training/session/session_grading.dart';

/// The two numbers the summary got wrong on the device.
///
/// «НОВЫХ 12» after six words: the count was over CARDS in the intro phase, and a freshly met word
/// is dealt an intro plus its recognition cards — every one of them in that phase. And the word list
/// read «cold / cold», headline and caption both resolving to the prompt, because on a rung-1 card
/// the prompt IS the term.
void main() {
  const termId = '01M00WHZFYJSYW76Z4B4BBASXC';

  SessionCard intro(String id) => SessionCard(
        termId: id,
        mode: ExerciseMode.intro,
        type: 'word',
        prompt: 'простуда',
        answer: 'cold',
      );

  SessionCard forward(String id) => SessionCard(
        termId: id,
        mode: ExerciseMode.multipleChoice,
        type: 'word',
        prompt: 'cold',
        answer: id,
        options: const ['простуда', 'жара', 'счёт'],
        optionIds: [id, 'T2', 'T3'],
      );

  SessionCard reverse(String id) => SessionCard(
        termId: id,
        mode: ExerciseMode.multipleChoice,
        type: 'word',
        prompt: 'простуда',
        answer: 'cold',
        options: const ['cold', 'heat', 'bill'],
      );

  group('newWordCount — «НОВЫХ» counts words, not cards', () {
    test('six introduced words, each with two recognition cards, is six', () {
      final cards = <SessionCard>[];
      for (var i = 0; i < 6; i++) {
        final id = 'T$i';
        cards.addAll([intro(id), forward(id), reverse(id)]);
      }
      expect(cards, hasLength(18));
      expect(newWordCount(cards), 6);
    });

    test('a word introduced twice in one run (a replayed slot) still counts once', () {
      expect(newWordCount([intro('T1'), intro('T1'), forward('T1')]), 1);
    });

    test('a session that introduces nothing has no new words', () {
      expect(newWordCount([forward('T1'), reverse('T2')]), 0);
    });
  });

  group('SessionCard.translationText — the caption under the reviewed word', () {
    test('an identity-graded card takes it from the correct OPTION', () {
      final card = forward(termId);
      expect(card.isIdentityGraded, isTrue);
      // The prompt is the term itself — printing it as the caption is what read «cold / cold».
      expect(card.prompt, 'cold');
      expect(card.answerText, 'cold');
      expect(card.translationText, 'простуда');
    });

    test('every other card keeps the prompt — that is the translation there', () {
      expect(reverse(termId).translationText, 'простуда');
      expect(intro(termId).translationText, 'простуда');
    });

    test('a broken identity card degrades to empty, never to the id', () {
      final card = SessionCard(
        termId: termId,
        mode: ExerciseMode.multipleChoice,
        type: 'word',
        prompt: 'cold',
        answer: 'SOMETHING-ELSE',
        options: const ['простуда', 'жара'],
        optionIds: const ['T2', 'T3'],
      );
      expect(card.translationText, isEmpty);
    });
  });
}
