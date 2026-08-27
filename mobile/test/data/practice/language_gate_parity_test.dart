import 'dart:io';
import 'dart:math';

import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/practice/language_mode_support.dart';
import 'package:eng_std/data/practice/learning_ladder.dart';
import 'package:eng_std/data/practice/local_session_builder.dart';
import 'package:eng_std/data/practice/practice_mode_selector.dart';
import 'package:flutter_test/flutter_test.dart';

/// PARITY of the LANGUAGE GATE (BUGFIX-2 Ч.2б D3/D4).
///
/// The server has refused a trainer a language cannot carry since A-2 — `pick_correct` outside
/// English, everything in `zh`/`ja`, `speaking`/`dictation` without a network in `pl`/`ro`. The
/// device deals free practice ITSELF (it has to work in airplane mode from start to summary) and
/// applied none of it, so an offline card could be a trainer the server would never have dealt.
///
/// The port is a PORT: which language loses which trainer is settled on the server, and this side
/// changes nothing about it. So the table is not transcribed here — it is READ OUT OF THE PHP FILE
/// and compared row for row. A transcription would agree on the day it was written and drift
/// silently afterwards, which is the exact failure mode this whole pair of runtimes has.
void main() {
  final php = File('../backend2/app/Modules/Shared/Domain/Service/LanguageModeSupport.php');

  group('the table, read out of the server', () {
    late final String source;

    setUpAll(() {
      expect(
        php.existsSync(),
        isTrue,
        reason: 'the server file moved — this parity test is now pinning nothing: ${php.path}',
      );
      source = php.readAsStringSync();
    });

    /// The `ALL_MODES` list, in the server's own order.
    List<String> serverAllModes() {
      final block = RegExp(
        r'const ALL_MODES = \[(.*?)\];',
        dotAll: true,
      ).firstMatch(source);
      expect(block, isNotNull, reason: 'ALL_MODES not found — the PHP shape changed');

      return _stringsIn(block!.group(1)!);
    }

    /// language => (closed, online_only), parsed out of the `SUPPORT` map.
    Map<String, ({List<String> closed, List<String> onlineOnly})> serverSupport() {
      final block = RegExp(r'const SUPPORT = \[(.*?)\n    \];', dotAll: true).firstMatch(source);
      expect(block, isNotNull, reason: 'SUPPORT not found — the PHP shape changed');

      final rows = RegExp(
        r"'(\w+)' => \['closed' => (\[[^\]]*\]|self::ALL_MODES), 'online_only' => (\[[^\]]*\])\]",
      ).allMatches(block!.group(1)!);

      return {
        for (final row in rows)
          row.group(1)!: (
            closed: row.group(2)! == 'self::ALL_MODES'
                ? serverAllModes()
                : _stringsIn(row.group(2)!),
            onlineOnly: _stringsIn(row.group(3)!),
          ),
      };
    }

    test('every trainer is on both registries, in the same order', () {
      expect(
        LanguageModeSupport.allModes.map((m) => m.wire).toList(),
        serverAllModes(),
        reason: 'a trainer the server knows and this build does not is a card nobody can play',
      );
    });

    test('every language carries exactly the trainers the server says it carries', () {
      final support = serverSupport();
      expect(support, isNotEmpty, reason: 'the PHP table parsed to nothing');
      expect(LanguageModeSupport.languages.toSet(), support.keys.toSet());

      for (final entry in support.entries) {
        final expected = [
          for (final mode in serverAllModes())
            if (!entry.value.closed.contains(mode)) mode,
        ];
        expect(
          LanguageModeSupport.modesFor(entry.key).map((m) => m.wire).toList(),
          expected,
          reason: 'language gate drift for «${entry.key}»',
        );
      }
    });

    test('every online-only trainer is online-only on both sides, and nowhere else', () {
      final support = serverSupport();

      for (final entry in support.entries) {
        for (final mode in LanguageModeSupport.allModes) {
          expect(
            LanguageModeSupport.isOnlineOnly(entry.key, mode),
            entry.value.onlineOnly.contains(mode.wire) &&
                !entry.value.closed.contains(mode.wire),
            reason: '«${entry.key}» / ${mode.wire}: «closed» and «online-only» are different answers',
          );
        }
      }
    });

    test('a language nobody teaches carries nothing at all', () {
      // Not an oversight and not a slight: v1 teaches seven languages, and a term outside that list
      // has no strictness rules, no normalisation and no grader written for it. Silence would be the
      // dangerous answer.
      expect(LanguageModeSupport.modesFor('sv'), isEmpty);
      expect(LanguageModeSupport.supports('sv', ExerciseMode.multipleChoice), isFalse);
    });
  });

  group('the gate as free practice applies it', () {
    Term term(String id, String text, String translation) => Term(
      id: id,
      termText: text,
      type: 'word',
      transcription: null,
      translation: translation,
      // An example that holds the term, so the content gates are open and what closes a trainer
      // below is the LANGUAGE and nothing else.
      example: 'Mam $text na dzisiejszy wieczór proszę.',
      exampleTranslation: 'У меня это на сегодняшний вечер, пожалуйста.',
      imageUrl: null,
      imageAuthor: null,
      imageAuthorUrl: null,
      updatedAt: DateTime.utc(2026, 8, 27),
    );

    final deck = [
      term('01M1GA0000000000000000000A', 'rezerwacja', 'бронь'),
      term('01M1GA0000000000000000000B', 'stolik', 'столик'),
      term('01M1GA0000000000000000000C', 'kelner', 'официант'),
    ];

    const everyMode = PracticeModes([
      ExerciseMode.multipleChoice,
      ExerciseMode.wordBank,
      ExerciseMode.typing,
      ExerciseMode.listening,
      ExerciseMode.cloze,
      ExerciseMode.scramble,
      ExerciseMode.dictation,
      ExerciseMode.pickCorrect,
      ExerciseMode.speaking,
      ExerciseMode.descriptionMatch,
    ]);

    const studied = LadderPosition(
      acquisition: Acquisition.graduated,
      successfulReviews: LearningLadder.dictationMinSuccesses,
      enrolled: true,
    );

    Set<ExerciseMode> fanFor(String lang, {bool isOnline = true}) => LocalPracticeSessionBuilder
        .build(
          terms: deck,
          limit: 20,
          random: Random(3),
          sessionId: 'S',
          onlyTermId: deck.first.id,
          enabled: everyMode,
          ladder: {for (final t in deck) t.id: studied},
          pairs: {for (final t in deck) t.id: (learned: lang, support: 'ru')},
          isOnline: isOnline,
        )
        .cards
        .map((c) => c.mode)
        .toSet();

    test('a Polish word is never dealt pick_correct — there is no judge for it', () {
      expect(fanFor('pl'), isNot(contains(ExerciseMode.pickCorrect)));
      // …and it keeps everything else its material allows.
      expect(fanFor('pl'), contains(ExerciseMode.multipleChoice));
      expect(fanFor('pl'), contains(ExerciseMode.cloze));
    });

    test('OFFLINE, Polish loses the two trainers that listen — and only offline', () {
      final online = fanFor('pl');
      final offline = fanFor('pl', isOnline: false);

      expect(online, contains(ExerciseMode.speaking));
      expect(offline, isNot(contains(ExerciseMode.speaking)));
      expect(offline, isNot(contains(ExerciseMode.dictation)));
      // Available, not absent: everything that does not need the microphone is untouched.
      expect(offline, contains(ExerciseMode.multipleChoice));
      expect(offline, contains(ExerciseMode.typing));
    });

    test('English keeps speaking and dictation offline — it has on-device recognition', () {
      final offline = fanFor('en', isOnline: false);

      expect(offline, contains(ExerciseMode.speaking));
    });

    test('a reference-only language yields NO CARD, not a lesser one', () {
      // `zh`/`ja` are collections in v1: neither puts spaces between words, so the assembly trainers
      // deal one chip for a whole sentence and typing goes through an IME. There is no honest
      // trainer to fall back to, so the session is empty rather than floored.
      expect(fanFor('zh'), isEmpty);
      expect(fanFor('ja'), isEmpty);
    });
  });
}

/// The single-quoted strings inside a PHP array literal, in order.
List<String> _stringsIn(String phpArray) => [
  for (final m in RegExp(r"'([a-z_]+)'").allMatches(phpArray)) m.group(1)!,
];
