import 'package:flutter/widgets.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan_models.dart';
import 'package:eng_std/features/training/session_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';

/// THE SEAMS OF A PLAN SITTING, from the client's side.
///
/// It started as one seam: the day, then the top-up. The client used to fold the two together, so a
/// day of fourteen cards was announced as «День 1 пройден · 21 фраза и слово» — seven of them out
/// of another plan and, on the account the bug was found on, another language.
///
/// A day is a SCENE now, so there are more seams and they are the shelves of it: the warm-up first,
/// then «Тебе скажут» / «Ты ответишь» / «Ты спросишь» / «Слова и связки», then the revision. The
/// server names each card's `section` and `shelf`; the wording is the client's, and so is the rule
/// that a caption appears only where the shelf CHANGES.
///
/// All of it is read off the cards themselves. The running order beyond «разогрев первым» is not
/// fixed yet, and a client that assumed one would draw its captions in the wrong places the day it
/// changes.
void main() {
  /// The wire shape of one task, with only the parts of it this file is about.
  Map<String, dynamic> task({
    required String termId,
    int? fromDayIndex,
    String? section,
    String? shelf,
    String? tier,
    String? speaker,
  }) => {
    'stage': 'a',
    'ordinal': 1,
    'of_steps': 1,
    'from_day_index': fromDayIndex,
    'softened': false,
    'source': fromDayIndex == null ? 'other_review' : 'new',
    'section': ?section,
    'shelf': ?shelf,
    'tier': ?tier,
    'speaker': ?speaker,
    if (section == 'review') 'origin': {'kind': 'plan', 'title': 'Собеседование'},
    'knobs_applied': <String>[],
    'knobs_ignored': <String>[],
    'card': {
      'term_id': termId,
      'exercise_mode': 'intro',
      'type': 'phrase',
      'answer': termId,
    },
  };

  group('plan session task', () {
    test('reads the section the server names', () {
      final day = PlanSessionTask.fromJson(task(termId: 't1', fromDayIndex: 1, section: 'day'));
      final review = PlanSessionTask.fromJson(task(termId: 't2', section: 'review'));

      expect(day.section, PlanSessionTask.sectionDay);
      expect(day.isDay, isTrue);
      expect(review.section, PlanSessionTask.sectionReview);
      expect(review.isDay, isFalse);
    });

    test('falls back to `from_day_index` when the server has not sent `section` yet', () {
      // The rule the client was supposed to apply before the field existed — and the one it got
      // wrong. A task of no day of this plan is the top-up.
      final day = PlanSessionTask.fromJson(task(termId: 't1', fromDayIndex: 2));
      final review = PlanSessionTask.fromJson(task(termId: 't2'));

      expect(day.isDay, isTrue);
      expect(review.isDay, isFalse);
    });
  });

  group('plan session', () {
    /// The live shape, shrunk: three cards of the day, then two the ordinary queue added.
    PlanSession sessionWithTopUp() => PlanSession.fromJson({
      'session_id': '01SESSION',
      'plan_id': '01PLAN',
      'day_index': 1,
      'strict': true,
      'day_task_count': 3,
      'tasks': [
        task(termId: 'd1', fromDayIndex: 1, section: 'day'),
        task(termId: 'd2', fromDayIndex: 1, section: 'day'),
        task(termId: 'd3', fromDayIndex: 1, section: 'day'),
        task(termId: 'fr1', section: 'review'),
        task(termId: 'other1', section: 'review'),
      ],
    });

    test('counts the day out of its own tasks, not out of the whole sitting', () {
      final session = sessionWithTopUp();

      expect(session.tasks.length, 5);
      expect(session.dayTaskCount, 3);
      expect(
        session.dayTasks.map((t) => t.card.termId),
        ['d1', 'd2', 'd3'],
      );
    });

    test('answers per card index, which is what the screens hold', () {
      final session = sessionWithTopUp();

      expect([for (var i = 0; i < 5; i++) session.isDayTaskAt(i)], [
        true,
        true,
        true,
        false,
        false,
      ]);

      // Out of range on both ends, because the screen asks while a session is being rebuilt.
      expect(session.isDayTaskAt(-1), isFalse);
      expect(session.isDayTaskAt(99), isFalse);
    });

    test('a session with nothing else due is all day', () {
      final session = PlanSession.fromJson({
        'session_id': '01SESSION',
        'plan_id': '01PLAN',
        'day_index': 1,
        'strict': true,
        'day_task_count': 2,
        'tasks': [
          task(termId: 'd1', fromDayIndex: 1, section: 'day'),
          task(termId: 'd2', fromDayIndex: 1, section: 'day'),
        ],
      });

      expect(session.dayTaskCount, session.tasks.length);
    });
  });

  group('origin', () {
    test('reads where a review card came from, and leaves the day`s own unlabelled', () {
      final session = PlanSession.fromJson({
        'session_id': '01SESSION',
        'plan_id': '01PLAN',
        'day_index': 1,
        'strict': true,
        'day_task_count': 1,
        'tasks': [
          task(termId: 'd1', fromDayIndex: 1, section: 'day'),
          task(termId: 'r1', section: 'review'),
        ],
      });

      expect(session.originAt(0), isNull);
      expect(session.originAt(1), (kind: 'plan', title: 'Собеседование'));
      expect(session.tasks[1].origin!.isPlan, isTrue);
    });

    test('treats a blank title as no origin at all', () {
      // A label with nothing in it is worse than none: it draws a caption that says nothing.
      expect(PlanTaskOrigin.fromJson(const {'kind': 'plan', 'title': '  '}), isNull);
      expect(PlanTaskOrigin.fromJson(null), isNull);
    });
  });

  group('the warm-up is a third section, not a shade of the day', () {
    test('the rescue kit does not count into «день пройден»', () {
      // The same five phrases on day 1 and on day 9 (канон §5). Counting them into the day would
      // grow «N из N» by five for a day that did not grow — the Д-40 arithmetic, from the new side.
      final session = PlanSession.fromJson({
        'session_id': '01SESSION',
        'plan_id': '01PLAN',
        'day_index': 1,
        'strict': true,
        'tasks': [
          task(termId: 'r1', fromDayIndex: 1, section: 'warmup', shelf: 'rescue'),
          task(termId: 'r2', fromDayIndex: 1, section: 'warmup', shelf: 'rescue'),
          task(termId: 'd1', fromDayIndex: 1, section: 'day', shelf: 'say'),
        ],
      });

      expect(session.tasks.length, 3);
      expect(session.dayTaskCount, 1);
      expect(session.dayTasks.single.card.termId, 'd1');
      expect(session.isWarmupAt(0), isTrue);
      expect(session.isDayTaskAt(0), isFalse);
      expect(session.isWarmupAt(2), isFalse);
    });
  });

  group('recognition-only cards', () {
    test('«understand» and the older «role» both mean the same thing', () {
      final heard = PlanSessionTask.fromJson(
        task(termId: 'h1', fromDayIndex: 1, section: 'day', shelf: 'hear', tier: 'understand'),
      );
      final numbers = PlanSessionTask.fromJson(
        task(termId: 'n1', fromDayIndex: 1, section: 'day', shelf: 'numbers', tier: 'understand'),
      );
      final legacyRole = PlanSessionTask.fromJson(
        task(termId: 'l1', fromDayIndex: 1, section: 'day', speaker: 'role'),
      );
      final said = PlanSessionTask.fromJson(
        task(termId: 's1', fromDayIndex: 1, section: 'day', shelf: 'say', tier: 'speak'),
      );

      expect(heard.isRecognitionOnly, isTrue);
      // A card with no speaker at all: the tier is the only thing saying it is never produced.
      expect(numbers.isRecognitionOnly, isTrue);
      expect(legacyRole.isRecognitionOnly, isTrue);
      expect(said.isRecognitionOnly, isFalse);
    });

    test('a tier this build has never heard of degrades to the older signal', () {
      // A server ahead of the client must not be able to turn a produced card into a recognised
      // one by inventing a third word for the ladder — nor the other way round.
      final unknown = PlanSessionTask.fromJson(
        task(termId: 'u1', fromDayIndex: 1, section: 'day', shelf: 'say', tier: 'perform'),
      );
      final unknownRole = PlanSessionTask.fromJson(
        task(
          termId: 'u2',
          fromDayIndex: 1,
          section: 'day',
          shelf: 'hear',
          tier: 'perform',
          speaker: 'role',
        ),
      );

      expect(unknown.isRecognitionOnly, isFalse);
      expect(unknownRole.isRecognitionOnly, isTrue);
    });
  });

  group('the captions the session draws over the seams', () {
    late AppLocalizations l;

    setUp(() async {
      TestWidgetsFlutterBinding.ensureInitialized();
      l = await AppLocalizations.delegate.load(const Locale('ru'));
    });

    List<String?> captions(PlanSession session) => [
      for (var i = 0; i < session.tasks.length; i++) planSeamCaption(l, session, i),
    ];

    // ПРАВИЛО: наряд DAY-GATE-1 (доработка), п. 3 — набор «на всякий случай» зовётся своим именем;
    // «Из прошлых дней» — это реплики прошлых дней, и больше ничего.
    // ЛОВИТ: подпись, прибитую к КОДУ секции. Код `warmup` держит две разные вещи, и до этой правки
    // ожидание здесь стояло на 'Из прошлых дней' над спасательным набором — то есть закрепляло
    // дефект, который живой прогон 07.09 и назвал: прошлых дней у дня 1 нет.
    test('names the warm-up, every shelf of the scene, and the revision — once each', () {
      final session = PlanSession.fromJson({
        'session_id': '01SESSION',
        'plan_id': '01PLAN',
        'day_index': 2,
        'strict': true,
        'tasks': [
          task(termId: 'k1', fromDayIndex: 2, section: 'warmup', shelf: 'rescue'),
          task(termId: 'k2', fromDayIndex: 2, section: 'warmup', shelf: 'rescue'),
          task(termId: 'h1', fromDayIndex: 2, section: 'day', shelf: 'hear'),
          task(termId: 'h2', fromDayIndex: 2, section: 'day', shelf: 'hear'),
          task(termId: 's1', fromDayIndex: 2, section: 'day', shelf: 'say'),
          task(termId: 'a1', fromDayIndex: 2, section: 'day', shelf: 'ask'),
          task(termId: 'w1', fromDayIndex: 2, section: 'day', shelf: 'words'),
          // Одна подпись на две полки: канон §2 считает слова и связки вместе.
          task(termId: 'c1', fromDayIndex: 2, section: 'day', shelf: 'chunks'),
          task(termId: 'p1', section: 'review'),
          task(termId: 'p2', section: 'review'),
        ],
      });

      expect(captions(session), [
        'На всякий случай',
        null,
        'Тебе скажут',
        null,
        'Ты ответишь',
        'Ты спросишь',
        'Слова и связки',
        null,
        'Повторение · из прошлых дней',
        null,
      ]);
    });

    test('draws the seam where the SHELF changes, not where the order happens to put it', () {
      // The running order past «разогрев первым» is a later наряд. A shelf that comes back after
      // another one is captioned again — the caption answers «что сейчас», not «сколько мы прошли».
      final session = PlanSession.fromJson({
        'session_id': '01SESSION',
        'plan_id': '01PLAN',
        'day_index': 1,
        'strict': true,
        'tasks': [
          task(termId: 's1', fromDayIndex: 1, section: 'day', shelf: 'say'),
          task(termId: 'h1', fromDayIndex: 1, section: 'day', shelf: 'hear'),
          task(termId: 's2', fromDayIndex: 1, section: 'day', shelf: 'say'),
        ],
      });

      expect(captions(session), ['Ты ответишь', 'Тебе скажут', 'Ты ответишь']);
    });

    test('says nothing over a shelf it cannot name', () {
      // `numbers` is stored and not dealt yet, and a shelf a newer server invents is a name this
      // build cannot read. Both get silence: an invented caption would be the plan telling the
      // learner something the plan does not know.
      final session = PlanSession.fromJson({
        'session_id': '01SESSION',
        'plan_id': '01PLAN',
        'day_index': 1,
        'strict': true,
        'tasks': [
          task(termId: 'n1', fromDayIndex: 1, section: 'day', shelf: 'numbers'),
          task(termId: 'x1', fromDayIndex: 1, section: 'day', shelf: 'small_talk'),
        ],
      });

      expect(captions(session), [null, null]);
    });

    test('a payload with no shelves keeps exactly the one seam it always drew', () {
      final session = PlanSession.fromJson({
        'session_id': '01SESSION',
        'plan_id': '01PLAN',
        'day_index': 1,
        'strict': true,
        'tasks': [
          task(termId: 'd1', fromDayIndex: 1),
          task(termId: 'd2', fromDayIndex: 1),
          task(termId: 'r1'),
          task(termId: 'r2'),
        ],
      });

      expect(captions(session), [
        null,
        null,
        'Повторение · из прошлых дней',
        null,
      ]);
    });

    // ПРАВИЛО: наряд DAY-GATE-1 (доработка), п. 3 — «Из прошлых дней» не рендерится, когда в ней
    // ноль карточек; на дне 1 её нет ПО ПОСТРОЕНИЮ.
    // ЛОВИТ: секцию, которую рисует имя, а не карточки. Живой прогон 07.09: на первом дне над
    // спасательным набором стояло «Из прошлых дней» — прошлых дней у дня 1 не бывает.
    test('на дне 1 «Из прошлых дней» не появляется: прошлых дней у него нет', () {
      final session = PlanSession.fromJson({
        'session_id': '01SESSION',
        'plan_id': '01PLAN',
        'day_index': 1,
        'strict': true,
        'tasks': [
          task(termId: 'k1', fromDayIndex: 1, section: 'warmup', shelf: 'rescue'),
          task(termId: 'k2', fromDayIndex: 1, section: 'warmup', shelf: 'rescue'),
          task(termId: 's1', fromDayIndex: 1, section: 'day', shelf: 'say'),
        ],
      });

      expect(captions(session), ['На всякий случай', null, 'Ты ответишь']);
      expect(captions(session), isNot(contains('Из прошлых дней')));
    });

    // ПРАВИЛО: то же — и обратная половина: когда карточки прошлых дней ЕСТЬ, секция есть, со своим
    // швом, а не молча приклеенная к набору.
    // ЛОВИТ: склейку двух частей разогрева в одну. Промах прошлого дня подавался бы под подписью
    // «На всякий случай» — то есть человек не узнал бы, что это его вчерашняя реплика.
    test('реплика прошлого дня даёт «Из прошлых дней» отдельным швом', () {
      final session = PlanSession.fromJson({
        'session_id': '01SESSION',
        'plan_id': '01PLAN',
        'day_index': 2,
        'strict': true,
        'tasks': [
          task(termId: 'k1', fromDayIndex: 2, section: 'warmup', shelf: 'rescue'),
          task(termId: 'm1', fromDayIndex: 1, section: 'warmup', shelf: 'say'),
          task(termId: 'm2', fromDayIndex: 1, section: 'warmup', shelf: 'hear'),
          task(termId: 's1', fromDayIndex: 2, section: 'day', shelf: 'say'),
        ],
      });

      expect(captions(session), ['На всякий случай', 'Из прошлых дней', null, 'Ты ответишь']);
    });

    test('a sitting that opens on the revision is captioned on its very first card', () {
      final session = PlanSession.fromJson({
        'session_id': '01SESSION',
        'plan_id': '01PLAN',
        'day_index': 1,
        'strict': true,
        'tasks': [task(termId: 'r1', section: 'review')],
      });

      expect(captions(session), ['Повторение · из прошлых дней']);
    });
  });
}
