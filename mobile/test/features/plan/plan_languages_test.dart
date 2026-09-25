import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_riverpod/misc.dart' show Override;
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/api_client.dart';
import 'package:eng_std/data/languages.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/plan/plan_languages.dart';
import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/data/token_store.dart';
import 'package:eng_std/features/plan/entry/plan_entry_screen.dart';
import 'package:eng_std/features/profile/build_stamp.dart';
import 'package:eng_std/features/profile/profile_screen.dart';
import 'package:eng_std/ui/ui.dart';

import '../../support/plan_goldens.dart';

/// THE CANON OF THE PAIR'S LANGUAGES (наряд LANG-1, part C): seven targets and nine natives come from
/// the server (`GET /languages`), are kept in memory for the app run and stood in for by the bundle
/// when there is no network.
///
/// The main session's decisions held here: step 22-2 offers the targets MINUS the profile's native (a
/// language paired with itself is not a pair, and the server refuses it); the native is asked on the
/// first screen and in the profile, and the native lists are the server's minus the chosen target;
/// names and flags come from the client catalogue, the server's only for a code it does not know.
void main() {
  setUpAll(setUpPlanGoldens);

  // ── PARSING THE ANSWER ───────────────────────────────────────────────────────────────────────
  group('разбор GET /languages — защитный', () {
    // RULE: an entry is an object {code, endonym, flag} or a bare code; anything else is dropped.
    // CATCHES: a parse that throws on one stray entry and leaves the screen without a list.
    test('объекты и строки вперемешку; чужие формы и повторы отброшены', () {
      final parsed = PlanLanguages.fromJson({
        'targets': [
          {'code': 'en', 'endonym': 'English', 'flag': '🇬🇧'},
          'de',
          ' PL ',
          42,
          {'endonym': 'no code'},
          {'code': 7},
          'english',
          'de',
          null,
        ],
        'natives': ['ru', {'code': 'be', 'endonym': 'Беларуская', 'flag': '🇧🇾'}],
      });

      expect(parsed.targetCodes, ['en', 'de', 'pl']);
      expect(parsed.nativeCodes, ['ru', 'be']);
      expect(parsed.fromBundle, isFalse);
    });

    // RULE (HYG-1): the name and the flag are the client catalogue's; the server's only for an unknown code.
    // CATCHES: a server typo that renames a language on one screen and not on the next — and an unknown
    // code drawn as the catalogue's first row («Русский»).
    test('справочник клиента сильнее сервера; незнакомый код — с именем и флагом сервера', () {
      final parsed = PlanLanguages.fromJson({
        'targets': [
          {'code': 'ro', 'endonym': 'România', 'flag': '🏳'},
          {'code': 'nl', 'endonym': 'Nederlands', 'flag': '🇳🇱'},
          'sv',
        ],
        'natives': const [],
      });

      expect(parsed.targets[0].endonym, 'Română');
      expect(parsed.targets[0].flag, '🇷🇴');
      expect(parsed.targets[1].endonym, 'Nederlands');
      expect(parsed.targets[1].nameIn('ru'), 'Nederlands');
      expect(parsed.targets[1].flag, '🇳🇱');
      expect(parsed.targets[2].endonym, 'sv');
      expect(parsed.targets[2].flag, isEmpty);
    });

    // CATCHES: an empty half of the answer turned into an empty step.
    test('пустая или битая половина — из бандла, вторая — сервера', () {
      final parsed = PlanLanguages.fromJson({'targets': 'en', 'natives': ['uk']}).orBundled();

      expect(parsed.targetCodes, kPlanTargetCodes);
      expect(parsed.nativeCodes, ['uk']);
      expect(PlanLanguages.fromJson('garbage').orBundled().fromBundle, isTrue);
    });

    test('бандл — те же коды, что сервер отдаёт сегодня, Беларуская среди родных', () {
      expect(PlanLanguages.bundled.targetCodes, ['en', 'pl', 'ro', 'es', 'it', 'de', 'fr']);
      expect(PlanLanguages.bundled.nativeCodes, ['ru', 'uk', 'be', 'pl', 'ro', 'es', 'it', 'de', 'fr']);
      expect(PlanLanguages.bundled.natives.map((l) => l.endonym), contains('Беларуская'));
    });
  });

  // ── THE PROVIDER: CACHED IN MEMORY, THE BUNDLE IN RESERVE ────────────────────────────────────
  group('кэш в памяти, запас в бандле', () {
    // CATCHES: a provider that passes a network error on to the screen (an empty step, a red screen).
    test('API бросает — провайдер отдаёт бандл, а не ошибку', () async {
      final container = ProviderContainer(overrides: [apiClientProvider.overrideWithValue(_ThrowingApi())]);
      addTearDown(container.dispose);

      final languages = await container.read(planLanguagesProvider.future);

      expect(languages.fromBundle, isTrue);
      expect(languages.targetCodes, kPlanTargetCodes);
      expect(languages.nativeCodes, kNativeLanguageCodes);
      expect(container.read(planLanguagesProvider).hasError, isFalse);
    });

    // CATCHES: a list asked again on every entry (an autoDispose provider).
    test('сервер ответил — его списки, и спрошен один раз за запуск', () async {
      final api = _LanguagesApi();
      final container = ProviderContainer(overrides: [apiClientProvider.overrideWithValue(api)]);
      addTearDown(container.dispose);

      final sub = container.listen(planLanguagesProvider, (_, _) {});
      final first = await container.read(planLanguagesProvider.future);
      sub.close();
      await Future<void>.delayed(Duration.zero);
      final again = await container.read(planLanguagesProvider.future);

      expect(first.targetCodes, ['en', 'pl', 'ro', 'es', 'it', 'de', 'fr']);
      expect(first.nativeCodes, hasLength(9));
      expect(identical(first, again), isTrue);
      expect(api.asked, 1);
    });
  });

  // ── THE NATIVE 22-2 SUBTRACTS ────────────────────────────────────────────────────────────────
  group('родной, который вычитается из целей', () {
    final languages = PlanLanguages.fromJson(languagesFixture());

    test('родной профиля из списка — он', () {
      expect(languages.nativeFor(profileNative: 'pl', deviceLanguage: 'en'), 'pl');
    });

    // RULE (item 11): no profile native, or one outside the list — the guess from the device's language.
    test('родного нет или он вне списка — язык устройства, белорусский в том числе', () {
      expect(languages.nativeFor(profileNative: null, deviceLanguage: 'be'), 'be');
      expect(languages.nativeFor(profileNative: 'en', deviceLanguage: 'uk'), 'uk');
      expect(languages.nativeFor(profileNative: 'ja', deviceLanguage: 'sv'), 'ru');
    });

    test('defaultNativeLanguageFor узнаёт белорусское устройство', () {
      expect(defaultNativeLanguageFor('be'), 'be');
      expect(kNativeLanguageCodes, contains('be'));
    });

    test('цели минус родной; родные минус цель', () {
      expect(languages.targetsFor('pl').map((l) => l.code), ['en', 'ro', 'es', 'it', 'de', 'fr']);
      expect(languages.nativesFor(target: 'de').map((l) => l.code), ['ru', 'uk', 'be', 'pl', 'ro', 'es', 'it', 'fr']);
      expect(languages.nativesFor(target: 'en').length, 9);
    });

    // CATCHES: a step with no card at all when the server offered one target — and it is the native.
    test('сервер оставил одну цель, и это родной — шаг берёт бандл', () {
      final narrow = PlanLanguages.fromJson(languagesFixture(targets: const ['pl']));

      expect(narrow.targetsFor('pl').map((l) => l.code), ['en', 'ro', 'es', 'it', 'de', 'fr']);
    });
  });

  // ── STEP 22-2 ───────────────────────────────────────────────────────────────────────────────
  group('шаг 22-2: цели сервера минус родной профиля', () {
    List<Override> overrides(ApiClient api, Profile profile) => [
      authControllerProvider.overrideWith(() => _Auth(profile)),
      apiClientProvider.overrideWithValue(api),
      connectivityProvider.overrideWith((ref) => Stream.value(true)),
    ];

    Widget entry(ApiClient api, Profile profile) =>
        ProviderScope(overrides: overrides(api, profile), child: planGoldenShell(const PlanEntryScreen()));

    Future<void> toLanguageStep(WidgetTester tester, Widget app) async {
      tester.view
        ..devicePixelRatio = 2
        ..physicalSize = const Size(390, 1400) * 2;
      addTearDown(tester.view.reset);
      await tester.pumpWidget(app);
      await tester.pump();
      await tester.enterText(find.byType(TextField), 'иду к врачу с ребёнком в клинику');
      await tester.pump();
      await tester.tap(find.text('Далее'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 200));
    }

    List<String> languageCards(WidgetTester tester) => [
      for (final card in tester.widgetList<ChoiceCard>(find.byType(ChoiceCard)))
        if (card.title != 'Средний' && card.title != 'Начальный') card.title,
    ];

    String? selectedLanguage(WidgetTester tester) => tester
        .widgetList<ChoiceCard>(find.byType(ChoiceCard))
        .where((c) => c.selected && c.title != 'Средний' && c.title != 'Начальный')
        .map((c) => c.title)
        .firstOrNull;

    // RULE (item 180): 22-2 does not ask for the native, it subtracts it — a Pole is not offered «Польский».
    // CATCHES: the pair «Polish × Polish», which the server refuses with a 422 only after «Собрать план».
    testWidgets('родной pl, семь целей — шесть карточек, «Немецкий» уходит в POST /plans как de', (tester) async {
      final api = _EntryApi();
      await toLanguageStep(tester, entry(api, _profile(native: 'pl', target: 'en')));

      expect(languageCards(tester), ['Английский', 'Румынский', 'Испанский', 'Итальянский', 'Немецкий', 'Французский']);
      expect(find.text('Польский'), findsNothing);
      expect(selectedLanguage(tester), 'Английский');

      await tester.tap(find.text('Немецкий'));
      await tester.pump();
      expect(selectedLanguage(tester), 'Немецкий');

      for (final label in ['Далее', 'Далее', 'Собрать план']) {
        await tester.tap(find.text(label));
        await tester.pump();
        await tester.pump(const Duration(milliseconds: 200));
      }

      expect(api.createdWith, ['de']);
    });

    // RULE: a profile target the step does not offer (it is the native itself) does not stay selected
    // silently — the first offered card is.
    testWidgets('цель профиля совпала с родным — выбрана первая предложенная', (tester) async {
      await toLanguageStep(tester, entry(_EntryApi(), _profile(native: 'de', target: 'de')));

      expect(languageCards(tester), isNot(contains('Немецкий')));
      expect(selectedLanguage(tester), 'Английский');
    });

    // RULE: the list may arrive while the goal is still being typed; a target the SERVER's list does not
    // offer is replaced by its first card then, not only when the screen opens.
    // CATCHES: the bundle's «Английский» still chosen, drawn nowhere, and sent to POST /plans.
    testWidgets('сервер не предлагает цель профиля — выбрана его первая карточка, она и уходит в POST /plans', (
      tester,
    ) async {
      final api = _EntryApi(targets: const ['de', 'fr']);
      await toLanguageStep(tester, entry(api, _profile(native: 'ru', target: 'en')));

      expect(languageCards(tester), ['Немецкий', 'Французский']);
      expect(selectedLanguage(tester), 'Немецкий');

      for (final label in ['Далее', 'Далее', 'Собрать план']) {
        await tester.tap(find.text(label));
        await tester.pump();
        await tester.pump(const Duration(milliseconds: 200));
      }

      expect(api.createdWith, ['de']);
    });

    // CATCHES: a step that stays empty, or holds only the account's language, with no network.
    testWidgets('сети нет — те же семь целей из бандла минус родной', (tester) async {
      await toLanguageStep(tester, entry(_EntryApi(offline: true), _profile(native: 'uk', target: 'en')));

      expect(languageCards(tester), hasLength(7));
      expect(languageCards(tester).first, 'Английский');
    });

    // RULE: the run's cache holds the bundle only until someone asks again — an entry that opens after a
    // failed ask asks the server once more.
    // CATCHES: one lost request at start-up pinning the bundle for the whole run.
    testWidgets('прошлый запрос упал — вход спрашивает сервер снова и рисует его список', (tester) async {
      final api = _EntryApi(failFirst: true, targets: const ['en', 'de']);
      final container = ProviderContainer(overrides: overrides(api, _profile(native: 'ru', target: 'en')));
      addTearDown(container.dispose);
      final first = await container.read(planLanguagesProvider.future);
      expect(first.fromBundle, isTrue);

      await toLanguageStep(
        tester,
        UncontrolledProviderScope(container: container, child: planGoldenShell(const PlanEntryScreen())),
      );

      expect(api.asked, 2);
      expect(languageCards(tester), ['Английский', 'Немецкий']);
    });
  });

  // ── THE NATIVE IN THE PROFILE ────────────────────────────────────────────────────────────────
  group('строка профиля «Родной язык»: родные сервера минус цель', () {
    List<Override> overrides(Profile profile, ApiClient api) => [
      appDatabaseProvider.overrideWith((ref) {
        final db = AppDatabase.forTesting(NativeDatabase.memory());
        ref.onDispose(db.close);

        return db;
      }),
      authControllerProvider.overrideWith(() => _Auth(profile)),
      statsProvider.overrideWith(
        (ref) => Stream.value(
          Stats(totalWords: 0, learned: 0, mastered: 0, dueToday: 0, reviewsTotal: 0, streakDays: 0),
        ),
      ),
      backendCommitProvider.overrideWith((ref) async => 'def5678'),
      apiClientProvider.overrideWithValue(api),
    ];

    Future<void> showProfile(WidgetTester tester, Profile profile, {ApiClient? api, ProviderContainer? container}) async {
      tester.view
        ..devicePixelRatio = 2
        ..physicalSize = const Size(390, 2400) * 2;
      addTearDown(tester.view.reset);
      const screen = ProfileScreen(pushed: true);
      await tester.pumpWidget(
        container != null
            ? UncontrolledProviderScope(container: container, child: planGoldenShell(screen))
            : ProviderScope(overrides: overrides(profile, api ?? _LanguagesApi()), child: planGoldenShell(screen)),
      );
      await tester.pumpAndSettle();
    }

    Future<Finder> openNativeSheet(WidgetTester tester) async {
      await tester.tap(find.text('Родной язык'));
      await tester.pumpAndSettle();

      return find.byType(AppBottomSheet);
    }

    List<String> rows(WidgetTester tester, Finder sheet) => [
      for (final flag in tester.widgetList<MiniFlag>(find.descendant(of: sheet, matching: find.byType(MiniFlag))))
        flag.languageCode,
    ];

    // CATCHES: a Belarusian whose native is shown as «Русский» (a catalogue without be) and who is
    // missing from the sheet.
    testWidgets('родной be — строка «Беларуская», в листе девять родных, Беларуская отмечена', (tester) async {
      await showProfile(tester, _profile(native: 'be', target: 'en'));
      expect(find.text('Беларуская'), findsOneWidget, reason: 'the row names the native by its endonym');

      final sheet = await openNativeSheet(tester);

      expect(rows(tester, sheet), ['ru', 'uk', 'be', 'pl', 'ro', 'es', 'it', 'de', 'fr']);
      expect(find.descendant(of: sheet, matching: find.text('Беларуская')), findsOneWidget);
      final checked = find.descendant(of: sheet, matching: find.byIcon(Icons.check));
      expect(checked, findsOneWidget);
      expect(
        find.ancestor(of: checked, matching: find.byWidgetPredicate((w) => w is Row && w.children.any((c) => c is MiniFlag && c.languageCode == 'be'))),
        findsOneWidget,
      );
    });

    // RULE: the native may not be the target — the target is taken out of the sheet.
    testWidgets('цель de — в листе восемь родных, Deutsch нет', (tester) async {
      await showProfile(tester, _profile(native: 'ru', target: 'de'));
      final sheet = await openNativeSheet(tester);

      expect(rows(tester, sheet), ['ru', 'uk', 'be', 'pl', 'ro', 'es', 'it', 'fr']);
      expect(find.descendant(of: sheet, matching: find.text('Deutsch')), findsNothing);
    });

    // RULE: the rows are the SERVER's natives, not the bundled copy.
    // CATCHES: a sheet drawn from the client constant, which a server narrowing (or growing) the list
    // would never reach — both lists hold the same nine today, so only a different answer tells them apart.
    testWidgets('сервер отдал три родных — в листе ровно они', (tester) async {
      await showProfile(tester, _profile(native: 'uk', target: 'en'), api: _LanguagesApi(natives: const ['uk', 'be', 'pl']));
      final sheet = await openNativeSheet(tester);

      expect(rows(tester, sheet), ['uk', 'be', 'pl']);
    });

    // RULE: a tap after a failed ask asks once more before the sheet opens.
    // CATCHES: the bundle cached for the whole run after one lost request.
    testWidgets('прошлый запрос упал — тап по строке спрашивает сервер снова', (tester) async {
      final api = _LanguagesApi(failFirst: true, natives: const ['uk', 'be', 'pl']);
      final container = ProviderContainer(overrides: overrides(_profile(native: 'uk', target: 'en'), api));
      addTearDown(container.dispose);
      expect((await container.read(planLanguagesProvider.future)).fromBundle, isTrue);

      await showProfile(tester, _profile(native: 'uk', target: 'en'), container: container);
      final sheet = await openNativeSheet(tester);

      expect(api.asked, 2);
      expect(rows(tester, sheet), ['uk', 'be', 'pl']);
    });
  });

  // ── THE DEVICE LANGUAGE HEADER ───────────────────────────────────────────────────────────────
  group('Accept-Language — язык устройства на каждом запросе', () {
    // RULE (LANG-1 §7): at the first sign-in the server takes the native from Accept-Language.
    // CATCHES: a client that does not send the header — and a Belarusian gets «Русский» by default.
    test('запрос несёт Accept-Language = тег локали устройства; ответ разобран', () async {
      TestWidgetsFlutterBinding.ensureInitialized();
      final adapter = _RecordingAdapter();
      final api = ApiClient(TokenStore(), adapter: adapter);

      final languages = await api.pairLanguages();

      final expected = ApiClient.deviceLanguageTag();
      expect(expected, isNotNull);
      expect(adapter.sent.single['Accept-Language'], expected);
      expect(adapter.paths.single, endsWith('/languages'));
      expect(languages.targetCodes, ['en', 'pl', 'ro', 'es', 'it', 'de', 'fr']);
      expect(languages.nativeCodes, contains('be'));
    });

    test('тег — BCP-47 устройства; «und» не шлётся', () {
      expect(ApiClient.deviceLanguageTag(const Locale('be', 'BY')), 'be-BY');
      expect(ApiClient.deviceLanguageTag(const Locale('uk')), 'uk');
      expect(ApiClient.deviceLanguageTag(const Locale.fromSubtags()), isNull);
    });
  });
}

Profile _profile({required String native, required String target}) =>
    Profile(nativeLanguage: native, targetLanguage: target, cefrLevel: 'B1', dailyGoal: 20);

class _Auth extends AuthController {
  _Auth(this._profile);

  final Profile _profile;

  @override
  Future<AppUser?> build() async => AppUser(id: 'u1', name: 'Денис', profile: _profile);
}

/// The server of LANG-1: seven targets, nine natives — or [natives] only; with [failFirst] the first
/// ask of the run is lost.
class _LanguagesApi implements ApiClient {
  _LanguagesApi({this.natives, this.failFirst = false});

  final List<String>? natives;
  final bool failFirst;
  int asked = 0;

  @override
  Future<PlanLanguages> pairLanguages() async {
    asked++;
    if (failFirst && asked == 1) {
      throw DioException(requestOptions: RequestOptions(path: '/languages'), type: DioExceptionType.connectionError);
    }

    return PlanLanguages.fromJson(languagesFixture(natives: natives));
  }

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

class _ThrowingApi implements ApiClient {
  @override
  Future<PlanLanguages> pairLanguages() async => throw DioException(
    requestOptions: RequestOptions(path: '/languages'),
    type: DioExceptionType.connectionError,
  );

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

/// The entry's server: the languages ([targets] narrows them; [offline] loses every ask, [failFirst]
/// only the first), and `POST /plans` that records the target and answers `failed` — the test is
/// about what was asked for, not about the build.
class _EntryApi implements ApiClient {
  _EntryApi({this.offline = false, this.failFirst = false, this.targets});

  final bool offline;
  final bool failFirst;
  final List<String>? targets;
  final List<String> createdWith = [];
  int asked = 0;

  @override
  Future<PlanLanguages> pairLanguages() async {
    asked++;
    if (offline || (failFirst && asked == 1)) {
      throw DioException(requestOptions: RequestOptions(path: '/languages'), type: DioExceptionType.connectionError);
    }

    return PlanLanguages.fromJson(languagesFixture(targets: targets));
  }

  @override
  Future<PlanBuild> createPlan({
    required String goalText,
    required String targetLang,
    required PlanLevel level,
    required int daysTotal,
    String? eventDate,
  }) async {
    createdWith.add(targetLang);

    return PlanBuild.fromJson({...planFixture('build_ready'), 'status': 'failed', 'fail_reason': 'model_error'});
  }

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

/// A transport that answers `GET /languages` as backend2 does and keeps what each request carried.
class _RecordingAdapter implements HttpClientAdapter {
  final List<Map<String, dynamic>> sent = [];
  final List<String> paths = [];

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) async {
    sent.add({...options.headers});
    paths.add(options.path);

    return ResponseBody.fromString(jsonEncode({'data': languagesFixture()}), 200, headers: {
      Headers.contentTypeHeader: [Headers.jsonContentType],
    });
  }

  @override
  void close({bool force = false}) {}
}
