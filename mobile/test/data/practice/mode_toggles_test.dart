import 'dart:math';

import 'package:drift/native.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/local/sync_service.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/practice/learning_ladder.dart';
import 'package:eng_std/data/practice/local_session_builder.dart';
import 'package:eng_std/data/practice/practice_mode_selector.dart';
import 'package:flutter_test/flutter_test.dart';

/// The trainer toggles reaching the device.
///
/// The set lives in the database now, per user, and rides `/sync` — so a mode switched off in the
/// admin panel has to stop being dealt OFFLINE too, on the next sync and without a new build. That
/// last part is the whole point: the offline builder is a second implementation of the ladder, and
/// a toggle it ignored would be invisible.
void main() {
  test('an empty or missing value falls back to the pre-sync default', () {
    // A device that has never synced has to assume something, and assuming "nothing" would mean a
    // practice session with no cards.
    expect(PracticeModes.fromWire(null).modes, PracticeModes.serverDefault.modes);
    expect(PracticeModes.fromWire('').modes, PracticeModes.serverDefault.modes);
    expect(PracticeModes.fromWire('   ').modes, PracticeModes.serverDefault.modes);
  });

  test('the stored set is taken as sent, order included', () {
    // The order is the rotation, so it survives the round trip verbatim.
    expect(
      PracticeModes.fromWire('typing,multiple_choice,scramble').modes,
      [ExerciseMode.typing, ExerciseMode.multipleChoice, ExerciseMode.scramble],
    );
  });

  test('a mode this build does not know is dropped, not guessed at', () {
    // A newer server can name a trainer this app cannot draw. Falling back to `typing` (what
    // ExerciseMode.fromWire does for a CARD) would deal a card the user never enabled.
    expect(
      PracticeModes.fromWire('typing,time_travel,cloze').modes,
      [ExerciseMode.typing, ExerciseMode.cloze],
    );
    // …and if NOTHING survives, the default is a better answer than an empty session.
    expect(PracticeModes.fromWire('time_travel').modes, PracticeModes.serverDefault.modes);
  });

  group('offline practice honours the stored toggles', () {
    late AppDatabase db;

    setUp(() => db = AppDatabase.forTesting(NativeDatabase.memory()));
    tearDown(() => db.close());

    // Distinct translations, and that matters: two terms that MEAN the same thing cannot be each
    // other's wrong option, so a deck of synonyms leaves multiple_choice with nothing to offer and
    // the card is refused (QA-15). This group is about the TOGGLES.
    Term term(String id, String text, String example, String translation) => Term(
          id: id,
          termText: text,
          type: 'word',
          transcription: null,
          translation: translation,
          example: example,
          exampleTranslation: 'перевод примера',
          imageUrl: null,
          imageAuthor: null,
          imageAuthorUrl: null,
          updatedAt: DateTime.utc(2026, 8, 11),
        );

    final terms = [
      term('01KZETAAA50EMHCN6SP80T8DHC', 'reservation', 'I have a reservation for tonight.', 'бронь'),
      term('01KZETAAB4AW6M9ZFRB3X02CVW', 'towel', 'I need a clean towel, please.', 'полотенце'),
      term('01KZETAAC103WZ24WQ7H087ZJ3', 'sheets', 'Could I have extra sheets, please?', 'простыни'),
      term('01KZETAAD2EWE2H5ZV7WD8JWKT', 'goals', 'She finally achieved her goals.', 'цели'),
    ];

    /// Every pair at the TOP of the acquisition ladder: this group is about the TOGGLES, and a
    /// never-shown pair would be held at rung 1 by the ladder before a toggle could say anything.
    /// The ladder gate has its own tests (ladder_gate_test.dart).
    Set<ExerciseMode> dealt(PracticeModes enabled) => LocalPracticeSessionBuilder.build(
          terms: terms,
          limit: 20,
          random: Random(3),
          sessionId: 'S',
          enabled: enabled,
          ladder: {
            for (final t in terms)
              t.id: const LadderPosition(acquisition: Acquisition.graduated, successfulReviews: 12),
          },
        ).cards.map((c) => c.mode).toSet();

    test('a narrowed set is the only thing dealt', () async {
      await db.setMeta(SyncKeys.exerciseModes, 'typing,multiple_choice');

      final enabled = PracticeModes.fromWire(await db.getMeta(SyncKeys.exerciseModes));

      expect(dealt(enabled), {ExerciseMode.typing, ExerciseMode.multipleChoice});
    });

    test('a single-mode set deals only that mode', () async {
      await db.setMeta(SyncKeys.exerciseModes, 'scramble');

      expect(dealt(PracticeModes.fromWire(await db.getMeta(SyncKeys.exerciseModes))),
          {ExerciseMode.scramble});
    });

    test('a mode switched off stops being dealt after the next sync', () async {
      await db.setMeta(SyncKeys.exerciseModes, 'multiple_choice,typing,scramble');
      expect(dealt(PracticeModes.fromWire(await db.getMeta(SyncKeys.exerciseModes))),
          contains(ExerciseMode.scramble));

      // …the admin switches scramble off, and the next /sync stores the new set.
      await db.setMeta(SyncKeys.exerciseModes, 'multiple_choice,typing');

      expect(dealt(PracticeModes.fromWire(await db.getMeta(SyncKeys.exerciseModes))),
          isNot(contains(ExerciseMode.scramble)));
    });

    test('the set survives a reopen — it is settings, not session state', () async {
      await db.setMeta(SyncKeys.exerciseModes, 'typing');
      expect(await db.getMeta(SyncKeys.exerciseModes), 'typing');
    });
  });
}
