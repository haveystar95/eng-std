import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/models.dart';
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

    test('reads the shelf and the tier v0.4 put on the register', () {
      final heard = PlanTermRow.fromJson(const {
        'id': 't4',
        'text': 'What seems to be the problem?',
        'translation': 'Что вас беспокоит?',
        'type': 'phrase',
        'kind': 'line',
        'speaker': 'role',
        'shelf': 'hear',
        'tier': 'understand',
        'stage': 'a',
        'from_day_index': 1,
      });

      final asked = PlanTermRow.fromJson(const {
        'id': 't5',
        'text': 'How long will it take?',
        'translation': 'Сколько это займёт?',
        'type': 'phrase',
        'kind': 'line',
        'speaker': 'learner',
        'shelf': 'ask',
        'tier': 'speak',
        'stage': 'a',
        'from_day_index': 1,
      });

      expect(heard.shelf, PlanTermRow.shelfHear);
      expect(heard.isRecognitionOnly, isTrue);

      // Both are `line`: only the shelf tells «Ты спросишь» from «Тебе скажут».
      expect(asked.kind, heard.kind);
      expect(asked.shelf, PlanTermRow.shelfAsk);
      expect(asked.isRecognitionOnly, isFalse);
    });
  });

  group('a day written before the day was a scene (v0.3 and earlier)', () {
    /// The whole day payload with none of the fields v0.4 added — the answer a server that has not
    /// been deployed yet still gives, and the shape every fallback here has to keep working on.
    PlanDayDetail oldDay() => PlanDayDetail.fromJson(const {
      'id': 'd1',
      'index': 1,
      'kind': 'intro',
      'title': 'Начать приём',
      'status': 'ready',
      'plan_id': '01PLAN',
      'terms': [
        {
          'id': 't1',
          'text': "It's a sharp pain.",
          'translation': 'Это острая боль.',
          'type': 'phrase',
          'stage': 'a',
          'from_day_index': 1,
        },
      ],
    });

    test('has no вводка, and that is an empty string rather than a hole', () {
      // The day screen asks `intro.isNotEmpty` and draws nothing. Null would make every call site
      // ask the question twice; a placeholder would put a sentence on the screen nobody wrote.
      expect(oldDay().day.intro, '');
    });

    test('has no shelves and no tiers, and every older reading still answers', () {
      final term = oldDay().terms.single;

      expect(term.shelf, isNull);
      expect(term.tier, isNull);
      // The pre-v0.2 guess still stands the phrase apart from the words…
      expect(term.isPhrase, isTrue);
      // …and with neither tier nor speaker there is nothing to say it is only ever recognised.
      expect(term.isRecognitionOnly, isFalse);
    });

    test('an вводка of blank space is read as none at all', () {
      final blank = PlanDayDetail.fromJson({
        ...const {
          'id': 'd1',
          'index': 1,
          'kind': 'intro',
          'title': 'Начать приём',
          'status': 'ready',
          'plan_id': '01PLAN',
          'terms': <dynamic>[],
        },
        'intro': '   \n ',
      });

      expect(blank.day.intro, '');
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

      expect(task.stage?.letter, 'B');
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

    test('reads `kind` and `speaker` off the task, so the card can say what it is (Д-5, Д-8)', () {
      final role = PlanSessionTask.fromJson(const {
        'stage': 'a',
        'ordinal': 1,
        'of_steps': 2,
        'from_day_index': 1,
        'softened': false,
        'source': 'new',
        'section': 'day',
        'kind': 'line',
        'speaker': 'role',
        'knobs_applied': <String>[],
        'knobs_ignored': <String>[],
        'card': {
          'term_id': 't1',
          'exercise_mode': 'multiple_choice',
          'type': 'phrase',
          'answer': 'Hello. What seems to be the problem with your child?',
        },
      });

      final chunk = PlanSessionTask.fromJson(const {
        'stage': 'a',
        'ordinal': 1,
        'of_steps': 2,
        'from_day_index': 1,
        'softened': false,
        'source': 'new',
        'section': 'day',
        'kind': 'chunk',
        'speaker': null,
        'knobs_applied': <String>[],
        'knobs_ignored': <String>[],
        'card': {
          'term_id': 't2',
          'exercise_mode': 'multiple_choice',
          'type': 'phrasal_verb',
          'answer': 'sore throat',
        },
      });

      // The interlocutor's line, marked as one. Without this the session dealt it like every other
      // card and the learner rehearsed the doctor's question.
      expect(role.speaker, 'role');
      expect(role.isRoleLine, isTrue);

      // A connector is a connector — the summary counts «связка» off this and no longer off the
      // number of words in the text.
      expect(chunk.kind, 'chunk');
      expect(chunk.isRoleLine, isFalse);
    });

    test('a payload written before either field is read without a fuss', () {
      final old = PlanSessionTask.fromJson(const {
        'stage': 'a',
        'ordinal': 1,
        'of_steps': 2,
        'from_day_index': 1,
        'softened': false,
        'source': 'new',
        'knobs_applied': <String>[],
        'knobs_ignored': <String>[],
        'card': {
          'term_id': 't1',
          'exercise_mode': 'multiple_choice',
          'type': 'phrase',
          'answer': 'x',
        },
      });

      // Null, and never «learner» or «word» by default: a term that is not a plan line has no
      // speaker at all, and that is a third answer rather than the second one.
      expect(old.kind, isNull);
      expect(old.speaker, isNull);
      expect(old.isRoleLine, isFalse);

      // The same for what v0.4 added. A session with no shelves draws the one seam it always drew,
      // and «never produced» is decided by the speaker exactly as it was.
      expect(old.shelf, isNull);
      expect(old.tier, isNull);
      expect(old.isWarmup, isFalse);
      expect(old.isRecognitionOnly, isFalse);
    });
  });

  group('the running order is the CONTRACT and the client does not touch it (PLAN-FIX-4)', () {
    test('cards reach the session screen in the order the server dealt them', () {
      // The day is laid out on the server — pieces, connectors, replies, the interlocutor's line,
      // each with its whole checklist ({@see PlanDayOrder}). A client that re-sorted any of it
      // would deal a card before the one that teaches it, and there would be two answers to «what
      // comes next».
      Map<String, dynamic> task(String termId, String mode) => {
        'stage': 'a',
        'ordinal': 1,
        'of_steps': 4,
        'from_day_index': 1,
        'softened': false,
        'section': 'day',
        'card': {'term_id': termId, 'exercise_mode': mode, 'answer': termId},
      };

      final session = PlanSession.fromJson({
        'session_id': 'S1',
        'plan_id': 'P1',
        'day_index': 1,
        'strict': true,
        'tasks': [
          task('word', 'intro'),
          task('word', 'speaking'),
          task('chunk', 'intro'),
          task('line', 'intro'),
          task('line', 'speaking'),
        ],
      });

      expect(
        session.asStudySession().cards.map((c) => '${c.termId}:${c.mode.wire}').toList(),
        ['word:intro', 'word:speaking', 'chunk:intro', 'line:intro', 'line:speaking'],
      );
    });

    test('reads the spoken line key off the card', () {
      final session = PlanSession.fromJson({
        'session_id': 'S1',
        'plan_id': 'P1',
        'day_index': 1,
        'strict': true,
        'tasks': [
          {
            'stage': 'a',
            'ordinal': 4,
            'of_steps': 4,
            'from_day_index': 1,
            'softened': false,
            'section': 'day',
            'card': {
              'term_id': 'T1',
              'exercise_mode': 'speaking',
              'answer': "Yes, I'm looking for a place to rent.",
              'speaking_key': 'a place to rent',
            },
          },
        ],
      });

      expect(session.tasks.single.card.spokenTarget, 'a place to rent');
    });

    test('a card with no key says so as null, never as an empty string', () {
      final card = SessionCard.fromJson({
        'term_id': 'T2',
        'exercise_mode': 'speaking',
        'answer': 'Sorry, could you repeat that?',
        'speaking_key': null,
      });

      expect(card.spokenTarget, isNull);
    });
  });
}
