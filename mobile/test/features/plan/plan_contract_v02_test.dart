import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan_models.dart';

/// The v0.2 contract, from the client's side.
///
/// Two questions, and they are opposite. A field the server ADDED must be ignored without a fuss —
/// the client is not rebuilt in step with the API and never has been. A field the server added
/// because the OLD guess became wrong must actually be read, or the screen keeps drawing the wrong
/// thing quietly.
void main() {
  group('plan day term', () {
    test('reads `kind` and sets a spoken line apart from a substitution', () {
      final line = PlanTermRow.fromJson(const {
        'id': 't1',
        'text': "It hurts in my lower back.",
        'translation': 'Болит в пояснице.',
        'type': 'phrase',
        'kind': 'line',
        'stage': 'a',
        'from_day_index': 1,
      });

      final chunk = PlanTermRow.fromJson(const {
        'id': 't2',
        'text': 'deal with',
        'translation': 'разбираться с',
        'type': 'phrasal_verb',
        'kind': 'chunk',
        'stage': 'a',
        'from_day_index': 1,
      });

      expect(line.kind, 'line');
      expect(line.isPhrase, isTrue);

      // THE case v0.2 introduced: two words, and not a line. Under the old rule («anything that is
      // not one word is a line») this row would have been set in serif as a spoken turn.
      expect(chunk.kind, 'chunk');
      expect(chunk.isPhrase, isFalse);
    });

    test('falls back to the old guess for a term written before `kind` existed', () {
      final legacy = PlanTermRow.fromJson(const {
        'id': 't3',
        'text': "It's a sharp pain.",
        'type': 'phrase',
        'stage': 'a',
        'from_day_index': 1,
      });

      expect(legacy.kind, isNull);
      expect(legacy.isPhrase, isTrue);
    });
  });

  group('plan session task', () {
    test('ignores the fields v0.2 added to the envelope', () {
      final task = PlanSessionTask.fromJson(const {
        'stage': 'b',
        'ordinal': 1,
        'of_steps': 2,
        'from_day_index': 1,
        'softened': false,
        'source': 'new',
        'speaking_form': 'example_from_memory',
        // New in v0.2: where a cloze card cuts its gap — the day's own frame when there is one.
        // The client does not draw it yet and must not trip over it.
        'cloze_source': 'It hurts in my ___.',
        'knobs_applied': <String>[],
        'knobs_ignored': <String>['tts_rate'],
        'card': {
          'term_id': 't1',
          'exercise_mode': 'cloze',
          'type': 'phrase',
          'answer': 'lower back',
        },
      });

      expect(task.stage.letter, 'B');
      expect(task.ordinal, 1);
      expect(task.ofSteps, 2);
      expect(task.card.termId, 't1');
    });

    test('reads the reading v0.2.1 put on the intro card', () {
      // The one field v0.2.1 added to the wire, and the client DOES draw it — so unlike
      // `cloze_source` above, this one has to be read rather than merely survived. It rides on the
      // ordinary card body, which means the plan and an ordinary collection get it by the same
      // path: the plan session renders its cards through the same `SessionCard`.
      final task = PlanSessionTask.fromJson(const {
        'stage': 'a',
        'ordinal': 1,
        'of_steps': 4,
        'from_day_index': 1,
        'softened': false,
        'source': 'new',
        'knobs_applied': <String>[],
        'knobs_ignored': <String>[],
        'card': {
          'term_id': 't1',
          'exercise_mode': 'intro',
          'type': 'phrase',
          'answer': 'It hurts in my lower back.',
          'transcription': 'ɪt hɜːts ɪn maɪ ˈləʊə bæk',
          'transliteration': 'ит хётс ин май лоуэр бэк',
        },
      });

      expect(task.card.transliteration, 'ит хётс ин май лоуэр бэк');
      // Beside the IPA, never instead of it — two different products in two different notations.
      expect(task.card.transcription, 'ɪt hɜːts ɪn maɪ ˈləʊə bæk');
    });

    test('a card that carries no reading reads as null, not as the empty string', () {
      // Every mode except `intro`, and every term that simply has no hint. The card draws nothing;
      // the distinction matters because '' would render as «[]».
      final task = PlanSessionTask.fromJson(const {
        'stage': 'b',
        'ordinal': 1,
        'of_steps': 2,
        'from_day_index': 1,
        'softened': false,
        'source': 'new',
        'knobs_applied': <String>[],
        'knobs_ignored': <String>[],
        'card': {
          'term_id': 't1',
          'exercise_mode': 'cloze',
          'type': 'phrase',
          'answer': 'lower back',
          'transliteration': null,
        },
      });

      expect(task.card.transliteration, isNull);
    });
  });
}
