import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/api_client.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/plan_models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/data/review_sync.dart';
import 'package:eng_std/data/session_completion_sync.dart';
import 'package:eng_std/data/token_store.dart';
import 'package:eng_std/features/plan/entry/plan_entry_screen.dart';
import 'package:eng_std/features/plan/plan_day_screen.dart';
import 'package:eng_std/features/plan/plan_day_summary.dart';
import 'package:eng_std/features/plan/plan_preview_screen.dart';
import 'package:eng_std/features/plan/plan_rehearsal_done.dart';
import 'package:eng_std/features/plan/plan_screen.dart';
import 'package:eng_std/features/plan/plan_tab_screen.dart';
import 'package:eng_std/features/training/session/session_grading.dart' show LocalCheck;
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/features/plan/entry/entry_ui.dart';

/// The plan screens, on data rather than on a live server.
///
/// What is asserted here is the handful of things the design is EXPLICIT about and that a later
/// refactor could quietly lose: the empty tab explains the difference between a collection and a
/// plan, a day separates phrases from words and marks every word with its stage, and the day's
/// summary says «в работе» rather than «выучено».
MaterialApp _app(Widget home) => MaterialApp(
  locale: const Locale('ru'),
  localizationsDelegates: AppLocalizations.localizationsDelegates,
  supportedLocales: const [Locale('ru')],
  home: Scaffold(body: home),
);

LearningPlan _plan({int focus = 1}) => LearningPlan.fromJson({
  ..._planJson(),
  'focus_day_index': focus,
  'next_day_index': focus + 1,
});

/// The same plan with day 1 CLOSED — what the server answers after a sitting that actually passed
/// the day. The summary reads this rather than assuming «the sitting was strict, so the day is
/// done»: a miss does not close its rung, and a day with one wrong card stays `ready`.
LearningPlan _planWithDayOneDone() => LearningPlan.fromJson({
  ..._planJson(),
  'focus_day_index': 2,
  'next_day_index': 2,
  'days': [
    {'id': 'd1', 'index': 1, 'kind': 'intro', 'title': 'Начать приём', 'status': 'done'},
    {'id': 'd2', 'index': 2, 'kind': 'intro', 'title': 'Уточнить симптомы', 'status': 'ready'},
  ],
});

/// The plan's payload minus the two fields a test usually wants to move. Spread rather than copied,
/// so a day-list test can replace `days` without restating everything above it.
Map<String, dynamic> _planJson() => {
  // The census the plan card is built on: fourteen cards written, twelve past stage A.
  'stage_census': {'total': 14, 'stage_a_closed': 12},
  'id': '01PLAN',
  'status': 'active',
  'title': 'К врачу из-за боли',
  'goal_text': 'Иду к врачу, болит спина',
  'support_lang': 'ru',
  'target_lang': 'en',
  'level': 'basic',
  'event_date': '2026-09-02',
  'minutes_per_day': 20,
  'readiness': 0.5,
  'days_to_event': 2,
  'deadline_tight': false,
  'can_already': [
    {'text': 'Сказать, зачем пришёл', 'day_index': 1, 'hit': false},
  ],
  'days': [
    {'id': 'd1', 'index': 1, 'kind': 'intro', 'title': 'Начать приём', 'status': 'ready'},
    {'id': 'd2', 'index': 2, 'kind': 'intro', 'title': 'Уточнить симптомы', 'status': 'ready'},
  ],
};

PlanDayDetail _day() => PlanDayDetail.fromJson(_dayJson());

/// The day's payload, spread rather than copied so a test can add one field to it — the same shape
/// [_planJson] has, and for the same reason.
Map<String, dynamic> _dayJson() => {
  'id': 'd2',
  'index': 2,
  'kind': 'intro',
  'title': 'Уточнить симптомы и помощь',
  'status': 'ready',
  'outcome': ['Описать, какая это боль'],
  'plan_id': '01PLAN',
  'plan_title': 'К врачу из-за боли',
  'support_lang': 'ru',
  'target_lang': 'en',
  'terms': [
    {
      'id': 't1',
      'text': "It's a sharp pain.",
      'translation': 'Это острая боль.',
      'type': 'phrase',
      'kind': 'line',
      'shelf': 'say',
      'stage': 'a',
      'from_day_index': 2,
    },
    {
      'id': 't0',
      'text': 'Where does it hurt?',
      'translation': 'Где болит?',
      'type': 'phrase',
      'kind': 'line',
      'shelf': 'hear',
      'speaker': 'role',
      'tier': 'understand',
      'stage': 'a',
      'from_day_index': 2,
    },
    {
      'id': 't2',
      'text': 'sharp',
      'translation': 'острый',
      'type': 'word',
      'kind': 'word',
      'shelf': 'words',
      'stage': 'a',
      'from_day_index': 2,
    },
    {
      'id': 't3',
      'text': 'back',
      'translation': 'спина',
      'type': 'word',
      'kind': 'word',
      'shelf': 'words',
      'stage': 'b',
      'from_day_index': 1,
    },
  ],
};

SessionCard _card(String id, String type) => SessionCard.fromJson({
  'term_id': id,
  'exercise_mode': 'multiple_choice',
  'type': type,
  'answer': id,
});

/// A plan session envelope with no carried words — enough for the summary's arithmetic.
class _Envelope implements PlanSessionEnvelope {
  const _Envelope({this.dayCards = 1 << 30, this.kinds = const []});

  /// How many of the cards are the DAY's — everything before this index. The default is «all of
  /// them», which is what a session with nothing else due looks like.
  final int dayCards;

  /// What each card IS in its day — `word` / `chunk` / `line`, positionally. Empty means «the
  /// server did not say», which is the old payload and falls back to «фраза».
  final List<String?> kinds;

  @override
  String get planId => '01PLAN';
  @override
  int get dayIndex => 1;
  @override
  bool get strict => true;
  @override
  String? stageLetterAt(int i) => 'A';
  @override
  ({int ordinal, int of})? stepAt(int i) => (ordinal: 1, of: 3);
  @override
  int? carriedFromAt(int i) => null;
  @override
  bool isDayTaskAt(int i) => i < dayCards;
  @override
  int get dayTaskCount => dayCards;
  @override
  ({String kind, String title})? originAt(int i) => null;
  @override
  String? kindAt(int i) => i >= 0 && i < kinds.length ? kinds[i] : null;
  @override
  // The summary does not read it; the SESSION card does, and that side is covered on the model
  // itself (`plan_contract_v02_test.dart`) rather than by driving the whole session screen.
  String? speakerAt(int i) => null;
  // Nor these three — the shelves and the warm-up are the SESSION's seams, pinned in
  // `plan_session_seam_test.dart` on the caption function itself.
  @override
  String? shelfAt(int i) => null;
  @override
  bool isWarmupAt(int i) => false;
  @override
  bool isRecognitionOnlyAt(int i) => false;

  @override
  List<int> get sittings => const [];

  @override
  PlanSituation? situationAt(int i) => null;

  @override
  bool speaksAfterChoiceAt(int i) => false;

  // Nor the part of the sitting and its conversation: this envelope exists for the SUMMARY, which
  // counts cards, and both are the session frame's business (`plan_session_seam_test.dart`).
  @override
  String? sectionCodeAt(int i) => null;
  @override
  List<PlanDialogue> get dialogues => const [];
}

/// Counts the «this run ended» calls without touching the queue or the network.
///
/// A subclass rather than a mock: [SessionCompletionSync.record] is the whole surface under test,
/// and its real body writes to drift and fires a request. `null` for both collaborators is safe
/// exactly because nothing below the override is ever reached.
class _CompletionSpy implements SessionCompletionSync {
  final List<String> recorded = [];

  @override
  Future<void> record({required String sessionId, DateTime? endedAt}) async {
    recorded.add(sessionId);
  }

  @override
  Future<void> flush() async {}

  @override
  Future<int> pendingCount() async => 0;

  @override
  noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

/// Never sends anything: the summary flushes reviews on the way in, and this test is not about that.
class _ReviewSyncStub implements ReviewSync {
  @override
  noSuchMethod(Invocation invocation) => Future<void>.value();
}

/// [PlanDaySummary] under everything it reaches for on the way in: the plan it names, and the two
/// queues it closes the run through. Without the last two the screen would write to drift and fire
/// a request from a widget test.
ProviderScope _summaryScope(_CompletionSpy spy, Widget child, {LearningPlan? plan}) => ProviderScope(
  overrides: [
    planProvider('01PLAN').overrideWith((ref) async => plan ?? _planWithDayOneDone()),
    sessionCompletionSyncProvider.overrideWithValue(spy),
    reviewSyncProvider.overrideWithValue(_ReviewSyncStub()),
  ],
  child: child,
);

/// A phone tall enough for a whole entry step, so a test can look at the button without scrolling
/// a lazily-built list first. The default 800×600 surface cuts the CTA off the bottom of кадр V4·01.
/// Сервер, который отвечает «Дописать за тебя» ровно тем, что ему сказали.
///
/// Блок продолжений перестал быть статикой (решение владельца 03.09): заготовки жили на чужой теме
/// и выглядели поломкой. Поэтому и тест теперь про ПУТЬ — пауза набора, ответ, тап, — а не про две
/// строки, вшитые в экран.
/// Closes the plan without a network — the ending screen sends `POST /plans/{id}/complete` on open.
class _ClosingApi extends ApiClient {
  _ClosingApi(this.closed) : super(TokenStore());

  final LearningPlan closed;

  @override
  Future<LearningPlan> completePlan(String planId) async => closed;
}

class _ContinuationsApi extends ApiClient {
  _ContinuationsApi(this.continuations) : super(TokenStore());

  final List<String> continuations;
  int calls = 0;

  /// Языка на шаге цели нет, и это часть проверки: пустой `targetLang` = «только продолжения».
  String? lastTargetLang;

  @override
  Future<ListenWarmup> listenWarmup({
    required String goalText,
    String targetLang = '',
    required String level,
  }) async {
    calls++;
    lastTargetLang = targetLang;

    return ListenWarmup(continuations: continuations);
  }
}

/// Довести экран до момента, когда пауза набора отработала и ответ пришёл.
Future<void> _settleContinuations(WidgetTester tester) async {
  await tester.pump(const Duration(milliseconds: 1000));
  await tester.pumpAndSettle();
}

void _tallPhone(WidgetTester tester) {
  tester.view.physicalSize = const Size(1170, 3000);
  tester.view.devicePixelRatio = 3;
  addTearDown(tester.view.reset);
}

void main() {
  testWidgets('every example goal is one the plan would actually accept', (tester) async {
    // The live run of направление Б found this the fastest way there is: «Врач» is four characters,
    // the server refuses a `goal_text` under five, and tapping an example the screen itself
    // suggests produced «Не получилось собрать план». The entry now holds a HIGHER bar than the
    // server's — a five-character goal is legal and useless — so every example is held to the bar
    // that actually opens «Дальше».
    await tester.pumpWidget(ProviderScope(child: _app(const PlanEntryScreen())));
    await tester.pumpAndSettle();

    final examples = tester
        .widgetList<Text>(find.descendant(of: find.byType(InkWell), matching: find.byType(Text)))
        .map((t) => (t.data ?? '').trim())
        .where((s) => s.startsWith('Иду') || s.startsWith('Онлайн') || s.startsWith('Летим'));

    expect(examples, isNotEmpty);
    for (final example in examples) {
      expect(
        example.length,
        greaterThanOrEqualTo(24),
        reason: '«$example» would not open «Дальше»',
      );
    }
  });

  testWidgets('a two-word goal says what is missing instead of blocking with red', (tester) async {
    _tallPhone(tester);
    final api = _ContinuationsApi(const [
      'и понять, что скажет врач про лечение',
      'и записать ребёнка на приём',
    ]);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [apiClientProvider.overrideWithValue(api)],
        child: _app(const PlanEntryScreen(initialGoal: 'К врачу')),
      ),
    );
    await tester.pumpAndSettle();

    // Подсказка кадра V4·01в — не сообщение об ошибке и не красное.
    expect(find.textContaining('Пары слов мало'), findsOneWidget);
    // …и «Дальше» не предлагает потратить запрос на цель, которую модель не сможет использовать.
    final next = tester.widget<EntryCta>(find.byType(EntryCta));
    expect(next.enabled && next.onPressed != null, isFalse);

    // Блок продолжений появляется ТОЛЬКО когда они пришли — не раньше.
    expect(find.text('ДОПИСАТЬ ЗА ТЕБЯ'), findsNothing);
    await _settleContinuations(tester);

    expect(find.text('ДОПИСАТЬ ЗА ТЕБЯ'), findsOneWidget);
    expect(find.text('и понять, что скажет врач про лечение'), findsOneWidget);
    // Языка на этом шаге ещё нет — сервер просят об одних продолжениях.
    expect(api.lastTargetLang, '');
  });

  testWidgets('блок продолжений молча не показывается, если их не написали', (tester) async {
    // Тот же тихий контракт, что у шага слуха: необязательное, что не собралось, не извиняется —
    // его просто нет.
    _tallPhone(tester);
    await tester.pumpWidget(
      ProviderScope(
        overrides: [apiClientProvider.overrideWithValue(_ContinuationsApi(const []))],
        child: _app(const PlanEntryScreen(initialGoal: 'К врачу')),
      ),
    );
    await tester.pumpAndSettle();
    await _settleContinuations(tester);

    expect(find.text('ДОПИСАТЬ ЗА ТЕБЯ'), findsNothing);
    // Подсказка о том, чего не хватает, при этом остаётся: она не про сервер.
    expect(find.textContaining('Пары слов мало'), findsOneWidget);
  });

  testWidgets('одна и та же цель не покупается дважды', (tester) async {
    _tallPhone(tester);
    final api = _ContinuationsApi(const ['и понять назначение']);

    // Цель СРАЗУ достаточной длины: иначе «Дальше» приглушено и уйти с шага нечем.
    await tester.pumpWidget(
      ProviderScope(
        overrides: [apiClientProvider.overrideWithValue(api)],
        child: _app(
          const PlanEntryScreen(initialGoal: 'Иду к врачу с ребёнком, надо объяснить симптомы'),
        ),
      ),
    );
    await tester.pumpAndSettle();
    await _settleContinuations(tester);
    expect(api.calls, 1);

    // Ушли на шаг языка — это ВТОРОЙ, осознанный вызов: разогрев на слух с языком.
    await tester.tap(find.text('Дальше'));
    await tester.pumpAndSettle();
    expect(api.calls, 2);

    // …и вернулись «Изм.» на ту же самую цель. Третьего вызова нет: текст тот же, ответ в кэше.
    await tester.tap(find.text('Изм.').first);
    await tester.pumpAndSettle();
    await _settleContinuations(tester);

    expect(api.calls, 2);
    expect(find.text('и понять назначение'), findsOneWidget);
  });

  testWidgets('тап по продолжению ДОПИСЫВАЕТ к набранному и открывает «Дальше»', (tester) async {
    _tallPhone(tester);
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          apiClientProvider.overrideWithValue(
            _ContinuationsApi(const ['и понять, что скажет врач про лечение']),
          ),
        ],
        child: _app(const PlanEntryScreen(initialGoal: 'К врачу')),
      ),
    );
    await tester.pumpAndSettle();
    await _settleContinuations(tester);

    await tester.tap(find.text('и понять, что скажет врач про лечение'));
    await tester.pumpAndSettle();

    // ДОПИСАНО, а не заменено: то, что человек набрал, никуда не делось.
    expect(
      find.text('К врачу и понять, что скажет врач про лечение'),
      findsOneWidget,
    );
    final next = tester.widget<EntryCta>(find.byType(EntryCta));
    expect(next.enabled && next.onPressed != null, isTrue);
    expect(find.text('хватит для плана'), findsOneWidget);
  });

  testWidgets('продолжение, пересказавшее цель, ЗАМЕНЯЕТ её, а не дописывается', (tester) async {
    // Промпт просит «never restate what is already written», и живой прогон 03.09 поймал, как это
    // правило нарушается на половине целей. Дописать такой ответ значило бы показать человеку его
    // собственную фразу дважды.
    _tallPhone(tester);
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          apiClientProvider.overrideWithValue(
            _ContinuationsApi(const [
              'Иду к врачу, болит спина, и я хочу понять, что мне делать дальше',
            ]),
          ),
        ],
        child: _app(const PlanEntryScreen(initialGoal: 'Иду к врачу, болит спина')),
      ),
    );
    await tester.pumpAndSettle();
    await _settleContinuations(tester);

    await tester.tap(find.textContaining('и я хочу понять'));
    await tester.pumpAndSettle();

    // Читаем САМО ПОЛЕ, а не экран: та же строка стоит и в карточке подсказки.
    final field = tester.widget<EditableText>(find.byType(EditableText)).controller.text;
    expect(field, 'Иду к врачу, болит спина, и я хочу понять, что мне делать дальше');
    // Никакого «Иду к врачу, болит спина Иду к врачу, болит спина, …».
    expect(field, isNot(contains('спина Иду к врачу')));
  });

  testWidgets('превью говорит ТЕМАМИ, а не пересказом цели', (tester) async {
    // Найдено живым прогоном 03.09: подзаголовок брал `goal_summary` от P1 — пересказ цели, — а
    // заголовок экрана и есть цель, и превью говорило одно и то же дважды. Теперь подзаголовок
    // называет число сцен и первые три названия с маленькой буквы; `goal_summary` остаётся в
    // данных (он нужен модели и админке) и на экран не едет.
    _tallPhone(tester);
    final plan = LearningPlan.fromJson({
      ..._planJson(),
      'goal_text': 'Иду к врачу с ребёнком, надо объяснить симптомы и понять назначение',
      'goal_restated':
          'Сходить с ребёнком в частную клинику: понять вопросы на стойке и разобраться в назначении',
      'days': [
        {'id': 'd1', 'index': 1, 'kind': 'intro', 'title': 'На стойке регистрации'},
        {'id': 'd2', 'index': 2, 'kind': 'intro', 'title': 'В кабинете врача'},
        {'id': 'd3', 'index': 3, 'kind': 'intro', 'title': 'После осмотра'},
        {'id': 'd4', 'index': 4, 'kind': 'final', 'title': 'Прогон перед событием'},
      ],
    });

    await tester.pumpWidget(
      ProviderScope(child: _app(PlanPreviewScreen(plan: plan))),
    );
    await tester.pumpAndSettle();

    final subtitle = tester
        .widgetList<Text>(find.textContaining('По твоим словам', skipOffstage: false))
        .map((t) => t.data ?? '')
        .single;

    // Темы — названия сцен, с маленькой буквы, первые три.
    expect(subtitle, contains('3 сцены'));
    expect(subtitle, contains('на стойке регистрации'));
    expect(subtitle, contains('в кабинете врача'));
    expect(subtitle, contains('после осмотра'));
    // Прогон — не сцена и в темы не попадает.
    expect(subtitle, isNot(contains('прогон')));

    // ГЛАВНОЕ: цели в подзаголовке нет ни в одной из двух её форм.
    expect(subtitle, isNot(contains(plan.goalText)));
    expect(subtitle, isNot(contains(plan.goalRestated!)));
    // …а заголовком она остаётся, и это не дубль, а единственное место, где она стоит.
    expect(find.text(plan.title), findsOneWidget);
  });

  testWidgets('the empty План tab explains the difference from a collection', (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          activePlanProvider.overrideWith((ref) async => null),
          planArchiveProvider.overrideWith((ref) async => <PlanSummary>[]),
        ],
        child: _app(const PlanTabScreen()),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Подготовиться к чему-то конкретному'), findsOneWidget);
    expect(find.text('Говоришь цель и дату'), findsOneWidget);
    expect(find.text('Составить план'), findsOneWidget);
  });

  testWidgets('a day lays the scene out by SHELF, in the order the sitting deals it (D·01)', (
    tester,
  ) async {
    // «Фразы дня» и «Слова в этих фразах» were two buckets that could not tell «Тебе скажут» from
    // «Ты ответишь» — both are lines, and drawing them identically is Д-8 on the day screen. The
    // shelves are the scene's own parts, in канон §11's order.
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          planDayProvider((planId: '01PLAN', dayIndex: 2)).overrideWith((ref) async => _day()),
        ],
        child: _app(PlanDayScreen(plan: _plan(focus: 2), dayIndex: 2)),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('СЛОВА И СВЯЗКИ'), findsOneWidget);
    expect(find.text('ТЕБЕ СКАЖУТ'), findsOneWidget);
    expect(find.text('ТЫ ОТВЕТИШЬ'), findsOneWidget);
    expect(find.text("It's a sharp pain."), findsOneWidget);
    expect(find.text('sharp'), findsOneWidget);
    // The interlocutor's shelf says what it is for, and its line is marked «он» in the margin.
    expect(find.textContaining('только понимать'), findsOneWidget);
    expect(find.text('он'), findsOneWidget);
    // A card of an EARLIER day is another scene's and is not laid out here.
    expect(find.text('back'), findsNothing);

    // The composition, counted off the material: three cards of this scene, three parts.
    await tester.scrollUntilVisible(find.textContaining('карточки'), 200);
    await tester.pumpAndSettle();
    expect(find.textContaining('3 карточки · 3 секции'), findsOneWidget);
    // The locked «Разговор» plate is gone: the conversation exists and is played in the sitting.
    expect(find.text('РАЗГОВОР'), findsNothing);
  });

  testWidgets('the day opens with the scene’s вводка, above everything it is made of', (
    tester,
  ) async {
    // «Кто перед тобой, что сейчас произойдёт, что считается успехом» (канон §2), written by the
    // server in the learner's own language. Plain text on purpose — the day screen is DAY-2's to
    // design, and this наряд only has to stop the вводка being thrown away on the wire.
    final withIntro = PlanDayDetail.fromJson({
      ..._dayJson(),
      'intro': 'Ты у стойки регистрации. Тебя спросят фамилию и дату приёма. '
          'Успех — если ты понял вопрос и назвал их, не переспрашивая дважды.',
    });

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          planDayProvider((planId: '01PLAN', dayIndex: 2)).overrideWith((ref) async => withIntro),
        ],
        child: _app(PlanDayScreen(plan: _plan(focus: 2), dayIndex: 2)),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.textContaining('Ты у стойки регистрации'), findsOneWidget);
    // …and it stands above the register rather than inside it.
    expect(
      tester.getTopLeft(find.textContaining('Ты у стойки регистрации')).dy,
      lessThan(tester.getTopLeft(find.text('СЛОВА И СВЯЗКИ')).dy),
    );
  });

  testWidgets('a day written before scenes existed draws no empty вводка', (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          planDayProvider((planId: '01PLAN', dayIndex: 2)).overrideWith((ref) async => _day()),
        ],
        child: _app(PlanDayScreen(plan: _plan(focus: 2), dayIndex: 2)),
      ),
    );
    await tester.pumpAndSettle();

    // The title is followed straight by the day's own blocks — no blank paragraph, no placeholder.
    expect(find.text(''), findsNothing);
    expect(find.text('Уточнить симптомы и помощь'), findsOneWidget);
    expect(find.text('СЛОВА И СВЯЗКИ'), findsOneWidget);
  });

  testWidgets('a running plan can always be given up — there is no other way out', (tester) async {
    // The server allows ONE running plan per learner. Without this link the only exit from a plan is
    // its own event, which makes a plan that went wrong a trap. No frame of «Фаза 4» draws it; the
    // product needs it, so the guard names it rather than leaving it to be «tidied away» later.
    await tester.pumpWidget(
      ProviderScope(
        overrides: [planProvider('01PLAN').overrideWith((ref) async => _plan())],
        child: _app(const PlanScreen(planId: '01PLAN')),
      ),
    );
    await tester.pumpAndSettle();

    await tester.scrollUntilVisible(find.text('Отказаться от плана'), 300);
    expect(find.text('Отказаться от плана'), findsOneWidget);
  });

  testWidgets('the day list tells queued, being written and burned apart (Д-20)', (tester) async {
    // The live plan's list: day 2 burned, days 3 and 4 untouched with zero attempts — and all of
    // them wearing «Собирается», with «Продолжить день 2» underneath. The learner found out day 2
    // was dead by pressing it.
    final plan = LearningPlan.fromJson({
      ..._planJson(),
      'focus_day_index': 2,
      'next_day_index': 3,
      'days': [
        {'id': 'd1', 'index': 1, 'kind': 'intro', 'title': 'Открыть приём', 'status': 'done'},
        {
          'id': 'd2',
          'index': 2,
          'kind': 'intro',
          'title': 'Ответить на вопросы врача',
          'status': 'failed',
          'generation_attempts': 2,
          'fail_code': 'card.example_is_a_term',
        },
        {'id': 'd3', 'index': 3, 'kind': 'intro', 'title': 'Понять назначение', 'status': 'pending'},
        {
          'id': 'd4',
          'index': 4,
          'kind': 'intro',
          'title': 'Забрать лекарство',
          'status': 'generating',
        },
      ],
    });

    await tester.pumpWidget(
      ProviderScope(
        overrides: [planProvider('01PLAN').overrideWith((ref) async => plan)],
        child: _app(const PlanScreen(planId: '01PLAN')),
      ),
    );
    await tester.pumpAndSettle();

    // Three states, three sentences — and «Собирается» belongs to the ONE day a worker holds.
    expect(find.text('Не собрался'), findsOneWidget);
    expect(find.text('В очереди'), findsOneWidget);
    expect(find.text('Собирается'), findsOneWidget);

    // …and the burned focus day is not offered as something to continue.
    expect(find.text('Продолжить день 2'), findsNothing);
    expect(find.text('Открыть день 2'), findsOneWidget);
  });

  testWidgets('a day out of attempts does not offer a button that cannot work', (tester) async {
    // The server claims a day at most twice; after that `PlanDay::claim()` returns false forever,
    // so «Собрать день» there is a button no number of presses can make work. Found on the owner's
    // phone: a day failed the validator twice and the screen kept offering to build it.
    final dead = PlanDayDetail.fromJson({
      'id': 'd1',
      'index': 1,
      'kind': 'intro',
      'title': 'Представиться и начать разговор',
      'status': 'failed',
      'generation_attempts': 2,
      'fail_reason': 'day.key_not_support_language',
      'plan_id': '01PLAN',
      'terms': [],
    });

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          planDayProvider((planId: '01PLAN', dayIndex: 1)).overrideWith((ref) async => dead),
        ],
        child: _app(PlanDayScreen(plan: _plan(focus: 1), dayIndex: 1)),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Собрать день'), findsNothing);
    expect(find.text('Собрать план заново'), findsOneWidget);
    expect(find.textContaining('больше не будет пытаться'), findsOneWidget);
  });

  testWidgets('a burned day names the CAUSE the server reported, not a stock one (Д-19)', (
    tester,
  ) async {
    // The live day died on the example gate and the screen said the model had answered in the
    // wrong language — one hard-coded sentence used for every failure there is. The code is
    // `card.` now (v0.4: one card, one address), the sentence it earns is the same one.
    final dead = PlanDayDetail.fromJson({
      'id': 'd1',
      'index': 1,
      'kind': 'intro',
      'title': 'Ответить на вопросы врача',
      'status': 'failed',
      'generation_attempts': 2,
      'fail_code': 'card.example_is_a_term',
      'plan_id': '01PLAN',
      'terms': [],
    });

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          planDayProvider((planId: '01PLAN', dayIndex: 1)).overrideWith((ref) async => dead),
        ],
        child: _app(PlanDayScreen(plan: _plan(focus: 1), dayIndex: 1)),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.textContaining('пример к карточке повторял другую карточку'), findsOneWidget);
    // …and the sentence that used to be said about everything is gone from this day.
    expect(find.textContaining('не на том языке'), findsNothing);
  });

  testWidgets('an UNKNOWN fail code gets the neutral sentence, never an invented one', (
    tester,
  ) async {
    final dead = PlanDayDetail.fromJson({
      'id': 'd1',
      'index': 1,
      'kind': 'intro',
      'title': 'Ответить на вопросы врача',
      'status': 'failed',
      'generation_attempts': 2,
      // A code this build of the app has never heard of — a server ahead of the client.
      'fail_code': 'day.something_new_entirely',
      'plan_id': '01PLAN',
      'terms': [],
    });

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          planDayProvider((planId: '01PLAN', dayIndex: 1)).overrideWith((ref) async => dead),
        ],
        child: _app(PlanDayScreen(plan: _plan(focus: 1), dayIndex: 1)),
      ),
    );
    await tester.pumpAndSettle();

    // A plausible wrong reason costs more than an honest missing one.
    expect(find.textContaining('не удалось собрать день'), findsOneWidget);
  });

  testWidgets('the plan card shows the stage count, not a percentage nobody can move', (
    tester,
  ) async {
    // «0% готовность к событию» over fifty-six walked cards, on the owner's phone (02.09). The
    // number is honest — readiness counts the cards that reached their LAST stage, and a day that
    // closes stage A moves its cards ONTO stage B — and it will read zero for the first days of
    // every plan. Until the canonical formula (P2-v0.4/SIT-1) the card shows the count a sitting
    // actually moves. The formula is untouched: `readiness` is still computed and still on the wire.
    await tester.pumpWidget(
      ProviderScope(
        overrides: [planProvider('01PLAN').overrideWith((ref) async => _plan())],
        child: _app(const PlanScreen(planId: '01PLAN')),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('12'), findsOneWidget);
    expect(find.text('14 карточек · 12 закрыли ступень A · 2 осталось'), findsOneWidget);

    // The percentage and its caption are gone from the plate — `readiness` is 0.5 in this fixture,
    // so «50» would be found if the plate still drew it.
    expect(find.text('%'), findsNothing);
    expect(find.text('50'), findsNothing);

    // …and «ТЫ УЖЕ МОЖЕШЬ · 0 из 6» is not drawn while every checkpoint is false by construction:
    // a checkpoint is confirmed by being SAID in a conversation, and there is none until CONV-1.
    expect(find.textContaining('ТЫ УЖЕ МОЖЕШЬ'), findsNothing);
    expect(find.text('Сказать, зачем пришёл'), findsNothing);
    // The days are still there — the block's «Потренировать» only ever opened the day that teaches
    // the checkpoint, and the day list is right below it.
    expect(find.text('Начать приём'), findsOneWidget);
  });

  testWidgets('the FINAL day offers the run-through, never «Собрать день» (Д-27)', (tester) async {
    // The dead end. The final day introduces nothing and owns no collection, so the server refuses
    // to build it (404) — and the screen drew «Собрать день» anyway, took the refusal and printed
    // «материал не прошёл проверку». The plan could not be finished from the app at all; the live
    // run closed it from tinker.
    final finalDay = PlanDayDetail.fromJson({
      'id': 'd5',
      'index': 5,
      'kind': 'final',
      'title': 'Прогон перед событием',
      'status': 'pending',
      'plan_id': '01PLAN',
      'terms': <dynamic>[],
    });

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          planDayProvider((planId: '01PLAN', dayIndex: 5)).overrideWith((ref) async => finalDay),
        ],
        child: _app(PlanDayScreen(plan: _plan(focus: 5), dayIndex: 5)),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Пройти прогон'), findsOneWidget);
    expect(find.text('Собрать день'), findsNothing);
    expect(find.text('Собрать план заново'), findsNothing);
  });

  testWidgets('the run-through lists the scenes with the status it knows, and no percentage (D·11)', (
    tester,
  ) async {
    // «Прогон не притворяется днём»: no warm-up, no sections, only the scenes and what is true of
    // each. The frame prints «готов 60%» per scene; the server computes readiness for the PLAN and
    // not per scene, so the rows say the day's STATUS instead of a number nobody produced.
    final finalDay = PlanDayDetail.fromJson({
      'id': 'd5',
      'index': 5,
      'kind': 'final',
      'title': 'Прогон перед событием',
      'status': 'pending',
      'plan_id': '01PLAN',
      'terms': <dynamic>[],
    });
    final plan = LearningPlan.fromJson({
      ..._planJson(),
      // The focus is on scene 2, so scene 1 is behind it — «пройдена» by the same rule the day list
      // uses, and the two screens cannot disagree about one day.
      'focus_day_index': 2,
      'days': [
        {'id': 'd1', 'index': 1, 'kind': 'intro', 'title': 'Начать приём', 'status': 'done'},
        {'id': 'd2', 'index': 2, 'kind': 'intro', 'title': 'Уточнить симптомы', 'status': 'ready'},
        {'id': 'd3', 'index': 3, 'kind': 'intro', 'title': 'Аптека', 'status': 'pending'},
        {'id': 'd5', 'index': 5, 'kind': 'final', 'title': 'Прогон перед событием', 'status': 'pending'},
      ],
    });

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          planDayProvider((planId: '01PLAN', dayIndex: 5)).overrideWith((ref) async => finalDay),
          planProvider('01PLAN').overrideWith((ref) async => plan),
          apiClientProvider.overrideWithValue(_ClosingApi(plan)),
        ],
        child: _app(PlanDayScreen(plan: plan, dayIndex: 5)),
      ),
    );
    await tester.pumpAndSettle();

    // Every teaching scene, by name — and the run-through itself is not one of them.
    expect(find.text('Начать приём'), findsOneWidget);
    expect(find.text('Уточнить симптомы'), findsOneWidget);
    expect(find.text('Аптека'), findsOneWidget);
    expect(find.text('СЦЕНА 1'), findsOneWidget);

    expect(find.text('пройдена'), findsOneWidget);
    expect(find.text('в работе'), findsOneWidget);
    // An untrained scene admits it out loud and does not block the run-through.
    expect(find.text('не тренировали'), findsOneWidget);
    expect(find.textContaining('Войдёт в прогон как есть'), findsOneWidget);
    // No percentage anywhere, and the screen says why rather than leaving a hole.
    expect(find.textContaining('%'), findsNothing);
    expect(find.textContaining('Готовность по сценам появится'), findsOneWidget);

    // «Шпаргалка под рукой» is a promise the screen keeps: the run-through has no prompts and no
    // options, so the sheet is the only thing there is to reach for. Two of them on screen — the
    // header's and the button's — and the header's is the one every plan screen carries.
    expect(find.textContaining('Вслух, без остановок'), findsOneWidget);
    await tester.drag(find.byType(ListView).last, const Offset(0, -400));
    await tester.pumpAndSettle();
    expect(find.text('Шпаргалка'), findsNWidgets(2));

    // «Завершить план» pushes the ending, and the ending is a BODY: [PlanRehearsalDone] is what the
    // run-through returns INSIDE the session's Scaffold. Pushed bare it has no Material over it and
    // Flutter draws the whole screen in the debug face — yellow double underline on black, which is
    // exactly how it came out on the simulator.
    await tester.tap(find.text('Завершить план'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Завершить'));
    await tester.pumpAndSettle();

    expect(
      find.ancestor(of: find.byType(PlanRehearsalDone), matching: find.byType(Material)),
      findsWidgets,
    );
  });

  testWidgets('the interlocutor’s line is marked in the day’s register (Д-8)', (tester) async {
    final day = PlanDayDetail.fromJson({
      'id': 'd1',
      'index': 1,
      'kind': 'intro',
      'title': 'Открыть приём для ребёнка',
      'status': 'ready',
      'plan_id': '01PLAN',
      'terms': [
        {
          'id': 't1',
          'text': 'Hello. What seems to be the problem with your child?',
          'translation': 'Здравствуйте. Что с вашим ребёнком?',
          'type': 'phrase',
          'kind': 'line',
          'speaker': 'role',
          'stage': 'a',
          'from_day_index': 1,
        },
        {
          'id': 't2',
          'text': 'I came with my son.',
          'translation': 'Я пришёл с сыном.',
          'type': 'phrase',
          'kind': 'line',
          'speaker': 'learner',
          'stage': 'a',
          'from_day_index': 1,
        },
      ],
    });

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          planDayProvider((planId: '01PLAN', dayIndex: 1)).overrideWith((ref) async => day),
        ],
        child: _app(PlanDayScreen(plan: _plan(focus: 1), dayIndex: 1)),
      ),
    );
    await tester.pumpAndSettle();

    // Both lines are shown; exactly one of them says whose it is. Without the mark the doctor's
    // question stood among the learner's own phrases and read as one to learn to say.
    //
    // The mark is «он» in the margin now, beside the italic (записка «Пометка роли»): the type
    // answers «чья это речь» before the caption does, and the caption is a word rather than a
    // colon-terminated label over a line the learner will never say.
    expect(find.text('Hello. What seems to be the problem with your child?'), findsOneWidget);
    expect(find.text('I came with my son.'), findsOneWidget);
    expect(find.text('он'), findsOneWidget);
  });

  testWidgets('a day that failed ONCE is still offered a retry', (tester) async {
    final retryable = PlanDayDetail.fromJson({
      'id': 'd1',
      'index': 1,
      'kind': 'intro',
      'title': 'Представиться и начать разговор',
      'status': 'failed',
      'generation_attempts': 1,
      'plan_id': '01PLAN',
      'terms': [],
    });

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          planDayProvider((planId: '01PLAN', dayIndex: 1)).overrideWith((ref) async => retryable),
        ],
        child: _app(PlanDayScreen(plan: _plan(focus: 1), dayIndex: 1)),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Собрать день'), findsOneWidget);
    expect(find.text('Собрать план заново'), findsNothing);
  });

  testWidgets('a day opened out of turn says which day it is — and no longer that it is soft', (
    tester,
  ) async {
    // The line is said BEFORE the button either way: looking ahead is a legitimate thing to want,
    // and the learner should know which day they are about to walk. What it SAYS changed with
    // E2E-SIM-2 (С-1): «тренировка пройдёт мягко» was true of the мягкий прогон, and that progon is
    // what dealt dictations of sentences nobody had been shown. An early day is now dealt its own
    // stage A, strictly, and its answers count — so the warning would be a lie.
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          planDayProvider((planId: '01PLAN', dayIndex: 2)).overrideWith((ref) async => _day()),
        ],
        // Focus is day 1, so day 2 is ahead of it.
        child: _app(PlanDayScreen(plan: _plan(focus: 1), dayIndex: 2)),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.textContaining('пройдёт мягко'), findsNothing);
    expect(find.textContaining('впереди текущего'), findsOneWidget);
    expect(find.textContaining('ответы засчитываются'), findsOneWidget);
  });

  testWidgets('the day summary counts TERMS and says «в работе», never «выучено»', (tester) async {
    await tester.pumpWidget(
      _summaryScope(
        _CompletionSpy(),
        _app(
          PlanDaySummary(
            envelope: const _Envelope(
              kinds: ['line', 'line', 'word', 'word', 'word'],
            ),
            // Five cards, three distinct terms: one word arrives as several cards inside a stage
            // and the summary must not count the questions.
            cards: [
              _card('t1', 'phrase'),
              _card('t1', 'phrase'),
              _card('t2', 'word'),
              _card('t2', 'word'),
              _card('t3', 'word'),
            ],
            onDone: () {},
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    // The verdict is the над-title now, in caps like every надзаголовок of the series; the headline
    // under it is the SCENE (наряд DAY-2, Ч.2.3).
    expect(find.text('ДЕНЬ 1 ПРОЙДЕН'), findsOneWidget);
    expect(find.text('3 фразы и слова в работе'), findsOneWidget);
    expect(find.textContaining('выучен'), findsNothing);

    // Nothing else was due, so there is no revision row to draw.
    expect(find.text('Повторение'), findsNothing);
  });

  testWidgets('a strict sitting that did NOT close the day says so, not «пройден»', (tester) async {
    // The owner's phone, 02.09. All fourteen cards of day 1 were played, two of them wrong — a miss
    // does not close its rung, so the day stayed `ready` and «Продолжить» was still the right button
    // on the home screen. The summary said «День 1 пройден» over it, because it read «the sitting
    // was strict» and never asked the server whether the DAY had passed.
    await tester.pumpWidget(
      _summaryScope(
        _CompletionSpy(),
        _app(
          PlanDaySummary(
            envelope: const _Envelope(kinds: ['word']),
            cards: [_card('t1', 'word')],
            onDone: () {},
          ),
        ),
        // The verdict the server actually gives after such a sitting: day 1 still `ready`.
        plan: _plan(),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('ДЕНЬ 1 · ПОЧТИ'), findsOneWidget);
    expect(find.text('ДЕНЬ 1 ПРОЙДЕН'), findsNothing);
    // …and the row that claims a stage CLOSED is not drawn over a stage that did not.
    expect(find.text('Ступень A пройдена'), findsNothing);
    expect(find.textContaining('Открой день ещё раз'), findsOneWidget);
  });

  testWidgets('the summary counts the day apart from what the queue added to the sitting', (
    tester,
  ) async {
    // The live complaint, in one screen: a day of THREE terms topped up with two the ordinary
    // queue had due. Counting them together announced a day that was twice the size it was — and on
    // the account it happened to, two of the extras were French inside an English plan.
    await tester.pumpWidget(
      _summaryScope(
        _CompletionSpy(),
        _app(
          PlanDaySummary(
            envelope: const _Envelope(
              dayCards: 4,
              kinds: ['line', 'line', 'word', 'word', 'line', 'word'],
            ),
            cards: [
              _card('t1', 'phrase'),
              _card('t1', 'phrase'),
              _card('t2', 'word'),
              _card('t3', 'word'),
              // Past the seam: the top-up.
              _card('r1', 'phrase'),
              _card('r2', 'word'),
            ],
            onDone: () {},
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    // THREE, not five: the headline is the day.
    expect(find.text('3 фразы и слова в работе'), findsOneWidget);

    // …and the revision is said, on its own row, in its own words.
    expect(find.text('Повторение'), findsOneWidget);
    expect(find.text('2 слова'), findsOneWidget);
  });

  testWidgets('the milestone screen does NOT close the run — the session does (Д-1, Д-28)', (
    tester,
  ) async {
    // The blocker, and the shape of its real fix. `record` used to live in the summary WIDGETS, one
    // copy each, and this was the copy that was missing: the live run answered 103 cards over three
    // plan days and sent zero completions. Copying the call in here would have fixed the symptom
    // and left the design that produced it — so the call moved to the SESSION, which is the thing
    // that actually ends, and this screen went back to being only a screen.
    //
    // The behaviour that replaced it is pinned end to end in plan_session_closes_test.dart, on the
    // real session screen: the last card answered closes the run, whichever summary is drawn.
    final spy = _CompletionSpy();

    await tester.pumpWidget(
      _summaryScope(
        spy,
        _app(
          PlanDaySummary(
            envelope: const _Envelope(kinds: ['word']),
            cards: [_card('t1', 'word')],
            onDone: () {},
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(spy.recorded, isEmpty);
  });

  testWidgets('the итог answers «справлюсь ли я в этой сцене», not «сколько слов» (D·06)', (
    tester,
  ) async {
    // The receipt is GONE, not moved (наряд DAY-2, Ч.2.3). «4 слова · 2 связки · 8 фраз» was an
    // honest count of the wrong thing: канон §13 asks the итог to be about the SCENE, and a
    // breakdown by kind is a list of what was handled rather than of what can now be done.
    const kinds = [
      'word', 'word', 'word', 'word',
      'chunk', 'chunk',
      'line', 'line', 'line', 'line', 'line', 'line', 'line', 'line',
    ];

    await tester.pumpWidget(
      _summaryScope(
        _CompletionSpy(),
        _app(
          PlanDaySummary(
            envelope: const _Envelope(kinds: kinds),
            cards: [for (var i = 0; i < kinds.length; i++) _card('t$i', 'word')],
            onDone: () {},
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('4 слова · 2 связки · 8 фраз'), findsNothing);
    // The scene is the headline, and the ladder is said in the words a person uses.
    expect(find.textContaining('Сцена:'), findsOneWidget);
    expect(find.text('познакомился'), findsOneWidget);
    expect(find.text('применяешь'), findsOneWidget);
    expect(find.text('говоришь сам'), findsOneWidget);
    // No percentage anywhere: «пока процент не считается, его нет вообще» (кадр D·06в).
    expect(find.textContaining('%'), findsNothing);
  });

  testWidgets('«почти» names the remainder and offers the choice (D·06б)', (tester) async {
    // A sitting that ended without closing the day. The screen used to say «День 1 ещё не закрыт»
    // and leave it there; the frame asks for the remainder BY NAME and for a choice, because
    // whether to finish now or meet them in tomorrow's warm-up is the learner's call.
    var trained = 0;

    await tester.pumpWidget(
      _summaryScope(
        _CompletionSpy(),
        _app(
          PlanDaySummary(
            envelope: const _Envelope(kinds: ['line', 'line']),
            cards: [_card('t1', 'phrase'), _card('t2', 'phrase')],
            results: [
              (card: _card('t1', 'phrase'), verdict: LocalCheck.wrong),
              (card: _card('t2', 'phrase'), verdict: LocalCheck.correct),
            ],
            onTrainMore: () => trained++,
            onDone: () {},
          ),
        ),
        plan: _plan(),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.textContaining('ДЕНЬ 1 · ПОЧТИ'), findsOneWidget);
    expect(find.textContaining('Осталось дотренировать'), findsOneWidget);
    expect(find.text('Не далось'.toUpperCase()), findsOneWidget);

    // The choice sits at the foot of the итог — a lazy list has not built it yet.
    await tester.scrollUntilVisible(find.text('Оставить на завтра'), 200);
    await tester.pumpAndSettle();
    expect(find.text('Оставить на завтра'), findsOneWidget);

    await tester.tap(find.textContaining('Дотренировать'));
    await tester.pumpAndSettle();
    expect(trained, 1);
  });
}
