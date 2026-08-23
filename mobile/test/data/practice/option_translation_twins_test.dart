import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/practice/practice_distractors.dart';
import 'package:flutter_test/flutter_test.dart';

/// QA-17, the card half: two options that mean the same thing are ONE option.
///
/// A distractor was already dropped when its translation matched the PROMPT's — otherwise the card
/// has two correct answers on it. What was missing is the same rule between the options themselves:
/// «check-in desk» and «front desk» are both «стойка регистрации», so a card carrying both asks the
/// learner to pick one of two answers that are equally right, and half the time marks them wrong.
///
/// Client port of the server's OptionTranslationTwinsTest, and it has to exist twice for the usual
/// reason: the device builds its own practice sessions offline.
void main() {
  Term term(String id, String text, String translation) => Term(
    id: id,
    termText: text,
    type: 'word',
    transcription: null,
    translation: translation,
    example: null,
    exampleTranslation: null,
    imageUrl: null,
    imageAuthor: null,
    imageAuthorUrl: null,
    updatedAt: DateTime.utc(2026, 8, 10),
  );

  final target = term('t0', 'withdraw cash', 'снять наличные');
  final checkIn = term('t1', 'check-in desk', 'стойка регистрации');
  final frontDesk = term('t2', 'front desk', 'стойка регистрации');
  final boarding = term('t3', 'boarding pass', 'посадочный талон');
  final towel = term('t4', 'towel', 'полотенце');

  test('never puts two translation twins on one card', () {
    final options = PracticeDistractors.forTarget(
      target: target,
      pool: [checkIn, frontDesk, boarding],
      count: 3,
    );

    final twins = options.where((o) => o == 'check-in desk' || o == 'front desk');
    expect(twins.length, lessThanOrEqualTo(1), reason: 'one meaning, one option');
  });

  test('still drops an option that means the same as the PROMPT', () {
    // The rule this one extends, kept honest: it was there first and must not be lost.
    final twinOfTarget = term('t5', 'take out cash', 'снять наличные');

    expect(
      PracticeDistractors.forTarget(target: target, pool: [twinOfTarget, boarding], count: 3),
      isNot(contains('take out cash')),
    );
  });

  test('still fills the card when the meanings are all different', () {
    expect(
      PracticeDistractors.forTarget(target: target, pool: [checkIn, boarding, towel], count: 3),
      hasLength(3),
    );
  });

  test('a term with no translation is still usable as a decoy', () {
    // The mirror is thinner than the server's database, and an untranslated row is common in it.
    // Dropping those too would starve the options in a half-synced collection — and an option with
    // no translation cannot be a twin of anything.
    final untranslated = term('t6', 'lobby', '');

    expect(PracticeDistractors.forTarget(target: target, pool: [untranslated], count: 3), [
      'lobby',
    ]);
  });
}
