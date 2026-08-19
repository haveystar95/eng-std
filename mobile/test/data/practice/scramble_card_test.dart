import 'dart:math';

import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/practice/learning_ladder.dart';
import 'package:eng_std/data/practice/local_session_builder.dart';
import 'package:eng_std/data/practice/practice_mode_selector.dart';
import 'package:eng_std/features/training/session/session_grading.dart';
import 'package:flutter_test/flutter_test.dart';

/// `scramble` on the device: the tokenizer, the gate, and the card the offline builder deals.
///
/// The ladder itself is pinned to the server by the contract fixture; these are the rules AROUND
/// it that the fixture cannot express — what a chip is, and what the assembled card carries.
void main() {
  // ── tokenizer: the four rules, on the edge cases they were chosen for ──────

  test('an in-word apostrophe stays inside its token', () {
    expect(
      SentenceTokenizer.tokenize("I won't give up until I've achieved my goals."),
      ['I', "won't", 'give', 'up', 'until', "I've", 'achieved', 'my', 'goals'],
    );
  });

  test('the final . ! ? is dropped and never becomes a chip', () {
    expect(SentenceTokenizer.tokenize('I have a reservation for tonight.'),
        ['I', 'have', 'a', 'reservation', 'for', 'tonight']);
    expect(SentenceTokenizer.tokenize('Where is the front desk?'),
        ['Where', 'is', 'the', 'front', 'desk']);
    expect(SentenceTokenizer.tokenize('Really?!'), ['Really']);
  });

  test('inner punctuation stays glued to its own word', () {
    expect(SentenceTokenizer.tokenize('Could I have extra sheets, please?'),
        ['Could', 'I', 'have', 'extra', 'sheets,', 'please']);
    // Only the LAST terminal mark is stripped; a mid-sentence one is inner punctuation.
    expect(SentenceTokenizer.tokenize('Wow! That was close.'), ['Wow!', 'That', 'was', 'close']);
  });

  test('case is not folded — the sentence keeps its own capitals', () {
    expect(SentenceTokenizer.tokenize('CHECK IN starts at 3 pm.'),
        ['CHECK', 'IN', 'starts', 'at', '3', 'pm']);
  });

  test('degenerate input yields no empty chips', () {
    expect(SentenceTokenizer.tokenize(''), isEmpty);
    expect(SentenceTokenizer.tokenize('   '), isEmpty);
    expect(SentenceTokenizer.tokenize('!'), isEmpty);
    expect(SentenceTokenizer.tokenize('Hello .'), ['Hello']);
  });

  // ── gate ───────────────────────────────────────────────────────────────────

  bool scrambles(String answer, String? example, [String? translation = 'Перевод.']) =>
      TermPlayability.of(answer: answer, example: example, exampleTranslation: translation)
          .supports(ExerciseMode.scramble);

  test('the 4…12 window is honoured at both edges', () {
    String of(int words) => '${List.filled(words, 'word').join(' ')}.';

    expect(scrambles('word', of(3)), isFalse);
    expect(scrambles('word', of(4)), isTrue);
    expect(scrambles('word', of(12)), isTrue);
    expect(scrambles('word', of(13)), isFalse);
  });

  test('an example with no translation is refused — the translation IS the question', () {
    const sentence = 'I have a reservation for tonight.';
    expect(scrambles('reservation', sentence, null), isFalse);
    expect(scrambles('reservation', sentence, '  '), isFalse);
    expect(scrambles('reservation', sentence), isTrue);
  });

  test('an example that is merely the term is refused — that is word_bank, twice', () {
    expect(scrambles('Nice to meet you', 'Nice to meet you.'), isFalse);
    expect(scrambles('nice to meet you', 'NICE TO MEET YOU!'), isFalse);
    expect(scrambles('Nice to meet you', 'Nice to meet you, I am Denis.'), isTrue);
  });

  test('a term with no example at all is refused', () {
    expect(scrambles('front desk', null), isFalse);
    expect(scrambles('front desk', ''), isFalse);
  });

  // ── the card the offline builder deals ────────────────────────────────────

  Term term(String id, String text, String example, String exampleTranslation) => Term(
        id: id,
        termText: text,
        type: 'word',
        transcription: null,
        translation: 'перевод',
        example: example,
        exampleTranslation: exampleTranslation,
        imageUrl: null,
        imageAuthor: null,
        imageAuthorUrl: null,
        updatedAt: DateTime.utc(2026, 8, 11),
      );

  final scrambleable = [
    term('01KZETAAA50EMHCN6SP80T8DHC', 'reservation', 'I have a reservation for tonight.', 'У меня бронь на сегодня.'),
    term('01KZETAAB4AW6M9ZFRB3X02CVW', 'goals', "I won't give up until I've achieved my goals.", 'Я не сдамся, пока не добьюсь своего.'),
    term('01KZETAAC103WZ24WQ7H087ZJ3', 'sheets', 'Could I have extra sheets, please?', 'Можно мне ещё простыни?'),
    term('01KZETAAD2EWE2H5ZV7WD8JWKT', 'towel', 'I need a clean towel, please.', 'Мне нужно чистое полотенце.'),
    term('01KZETAAE63W6K93C55NCYXKVA', 'check in', 'Check in starts at three pm.', 'Заселение начинается в три.'),
  ];

  /// Every pair at the TOP of the acquisition ladder: this file is about scramble's own gate (a
  /// translated example of the right length), and the ladder is a separate filter with its own
  /// tests. Left out, every term here would be a never-shown one — held at rung 1, where the only
  /// trainer admitted is multiple_choice and no scramble card can be dealt at all.
  List<SessionCard> scrambleCards() => LocalPracticeSessionBuilder.build(
        terms: scrambleable,
        limit: 20,
        random: Random(8),
        sessionId: 'SESSION',
        ladder: {
          for (final t in scrambleable)
            t.id: const LadderPosition(acquisition: Acquisition.graduated, successfulReviews: 12, enrolled: true),
        },
      ).cards.where((c) => c.mode == ExerciseMode.scramble).toList();

  test('offline practice deals scramble cards without a network call', () {
    expect(scrambleCards(), isNotEmpty,
        reason: 'a pool of translated, well-sized examples must reach scramble in the fan');
  });

  test('a scramble card asks for the SENTENCE, not the term', () {
    for (final card in scrambleCards()) {
      final source = scrambleable.firstWhere((t) => t.id == card.termId);

      // The swap the server's StudyCardAssembler makes, mirrored here — otherwise an offline card
      // and an online one would be graded against different things.
      expect(card.answer, source.example);
      expect(card.prompt, source.exampleTranslation);
      expect(card.options, isNull);
    }
  });

  test('its chips are the sentence own words, shuffled, with no full stop and no decoys', () {
    for (final card in scrambleCards()) {
      final tokens = SentenceTokenizer.tokenize(card.answer);

      expect(card.chips, isNotNull);
      // Compare on COPIES: sorting in place would destroy the order the next assertion checks.
      expect([...card.chips!]..sort(), [...tokens]..sort(), reason: 'the same words, no extras');
      expect(card.chips, isNot(tokens), reason: 'never dealt in the sentence own order');
      expect(card.chips, everyElement(isNot(endsWith('.'))));
    }
  });

  test('the assembled string is what grading compares — joined by single spaces', () {
    // What the card commits is `chips joined by ' '`, exactly like word_bank. Assembled back in
    // the right order it must read as correct, and the local check must accept the punctuation and
    // capitalisation the learner never typed.
    for (final card in scrambleCards()) {
      final assembled = SentenceTokenizer.tokenize(card.answer).join(' ');

      expect(SessionGrader.check(assembled, card.answer).isAccepted, isTrue,
          reason: 'the correct assembly of "${card.answer}" must be accepted');
    }
  });

  test('a wrong order is not accepted', () {
    final card = scrambleCards().first;
    final tokens = SentenceTokenizer.tokenize(card.answer);
    final swapped = [tokens[1], tokens[0], ...tokens.skip(2)].join(' ');

    expect(SessionGrader.check(swapped, card.answer).isAccepted, isFalse);
  });
}
