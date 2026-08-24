import 'dart:async';

import 'package:drift/drift.dart' show Value;
import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'package:eng_std/data/api_client.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/search/search_history.dart';
import 'package:eng_std/features/search/search_pair.dart';
import 'package:eng_std/features/search/search_screen.dart';
import 'package:eng_std/features/word_card/word_card_screen.dart';
import 'package:eng_std/features/word_card/word_card_subject.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

/// Design preview for «Слова · фаза 3» — the search screen and the word card, with a fake API.
///
/// Sibling of `tool/preview.dart` and `tool/ladder_preview.dart`: the REAL screens, mock data, no
/// backend and no login. It exists because two of the nine frames cannot be reached on a live
/// build without changing the server — the daily model cap (кадр 08) and a deliberately slow
/// assembly (кадр 05) — and a design that is only ever reviewed on the states the server happens
/// to be in is a design nobody has actually seen.
///
/// The reverse search (RS-1) added three more of those: a query typed in the learner's own
/// language, one too long to be a query, and one the model cannot place. Type «случай», «как дела»,
/// a pasted paragraph or «asdfgh» into the first entry to reach them.
///
/// ```bash
/// flutter run --debug -d <simulator-udid> --target tool/search_preview.dart
/// ```
void main() => runApp(const _PreviewApp());

const _holePhoto =
    'https://images.pexels.com/photos/1108099/pexels-photo-1108099.jpeg?auto=compress&cs=tinysrgb&w=900';
const _formPhoto =
    'https://images.pexels.com/photos/590041/pexels-photo-590041.jpeg?auto=compress&cs=tinysrgb&w=900';

/// The whole preview lives under ONE scope, above the `MaterialApp`.
///
/// It has to: a card opened from search is pushed onto the app's root navigator, which sits ABOVE
/// any scope a screen wraps itself in — so overrides placed further down simply do not reach it,
/// and the card silently talks to the real backend instead. Which entry is being previewed is
/// therefore switched on [_RoutingApi], not by nesting another scope.
class _PreviewApp extends StatefulWidget {
  const _PreviewApp();

  @override
  State<_PreviewApp> createState() => _PreviewAppState();
}

class _PreviewAppState extends State<_PreviewApp> {
  late final AppDatabase _db = AppDatabase.forTesting(NativeDatabase.memory());
  late final Future<void> _seeded = _seed();

  Future<void> _seed() async {
    // The photo a search hit gets is the one the LOCAL mirror already holds — `/search` carries
    // none. Seeding the term is what makes кадры 03 and 06 show a picture here.
    await _db.applyDelta(
      termUpserts: [
        TermsCompanion.insert(
          id: '01HOLE',
          termText: const Value('hole'),
          translation: const Value('дыра'),
          transcription: const Value('hoʊl'),
          description: const Value(
            'This is a space or opening in a solid object or surface. Air, water or light can pass through it.',
          ),
          example: const Value('I found a hole in my shirt after playing outside.'),
          exampleTranslation: const Value('Я нашёл дыру в своей рубашке после игры на улице.'),
          imageUrl: const Value(_holePhoto),
          imageAuthor: const Value('Ann H'),
          updatedAt: DateTime.now(),
        ),
        TermsCompanion.insert(
          id: '01FILL',
          termText: const Value('fill out'),
          translation: const Value('заполнять (форму)'),
          imageUrl: const Value(_formPhoto),
          imageAuthor: const Value('Pixabay'),
          updatedAt: DateTime.now(),
        ),
      ],
    );
    for (final entry in const [
      RecentSearch(word: 'withdraw', translation: 'снимать', cefr: 'B2'),
      RecentSearch(word: 'invoice', translation: 'счёт', cefr: 'B1'),
      RecentSearch(word: 'hollow', translation: 'пустой', cefr: 'B2'),
    ]) {
      await SearchHistory(_db).remember(entry);
    }
  }

  @override
  void dispose() {
    _db.close();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => FutureBuilder<void>(
    future: _seeded,
    builder: (context, snapshot) {
      if (snapshot.connectionState != ConnectionState.done) {
        return const ColoredBox(color: AppColors.paper);
      }

      return ProviderScope(
        overrides: [
          appDatabaseProvider.overrideWithValue(_db),
          apiClientProvider.overrideWithValue(_RoutingApi()),
          collectionsProvider.overrideWith(
            (ref) => Stream.value([
              WordCollection(
                id: 'F',
                title: 'Сохранённые',
                source: 'user',
                type: 'custom',
                wordsCount: 12,
                sourceLang: 'ru',
                targetLang: 'en',
                isDefault: true,
              ),
              WordCollection(
                id: 'F2',
                title: 'Документы',
                source: 'user',
                type: 'custom',
                wordsCount: 4,
                sourceLang: 'ru',
                targetLang: 'en',
              ),
            ]),
          ),
        ],
        child: MaterialApp(
          debugShowCheckedModeBanner: false,
          locale: const Locale('ru'),
          localizationsDelegates: AppLocalizations.localizationsDelegates,
          supportedLocales: const [Locale('ru')],
          theme: buildAppTheme(),
          home: const _Menu(),
        ),
      );
    },
  );
}

class _Menu extends StatelessWidget {
  const _Menu();

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: AppColors.paper,
    body: SafeArea(
      child: ListView(
        padding: const EdgeInsets.all(AppSpacing.s22),
        children: [
          const SizedBox(height: 40),
          Text('Слова · фаза 3', style: AppText.screenTitle),
          const SizedBox(height: AppSpacing.s26),
          _entry(context, '01–07 · поиск и карточка', () => _search(cap: false, slow: false)),
          _entry(context, '05 · медленная сборка', () => _search(cap: false, slow: true)),
          _entry(context, '08 · дневной лимит', () => _search(cap: true, slow: false)),
          _entry(context, '09 · карточка из папки', () => const _FromFolder()),
          _entry(context, '09 · слово из каталога (не в пуле)', () => const _FromCatalogue()),
        ],
      ),
    ),
  );

  Widget _entry(BuildContext context, String label, Widget Function() build) => Padding(
    padding: const EdgeInsets.only(bottom: AppSpacing.s12),
    child: ListTile(
      tileColor: AppColors.surfaceRaised,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(AppRadii.sheet)),
      title: Text(label, style: AppText.translation.copyWith(color: AppColors.ink)),
      onTap: () => Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => build())),
    ),
  );

  Widget _search({required bool cap, required bool slow}) {
    _RoutingApi.delegate = _FakeApi(capReached: cap, slow: slow);

    return const SearchScreen();
  }
}

/// The word card as it opens from one of the learner's own folders (кадр 09).
class _FromFolder extends StatelessWidget {
  const _FromFolder();

  @override
  Widget build(BuildContext context) => WordCardScreen(
    mode: WordCardMode.folder,
    onSpeak: () {},
    onTrain: () {},
    onUnenroll: () {},
    subject: WordCardSubject.fromWord(
      Word(
        termId: '01HOLE',
        term: 'hole',
        translation: 'дыра',
        transcription: 'hoʊl',
        description:
            'This is a space or opening in a solid object or surface. Air, water or light can pass through it.',
        example: 'I found a hole in my shirt after playing outside.',
        exampleTranslation: 'Я нашёл дыру в своей рубашке после игры на улице.',
        type: 'word',
        imageUrl: _holePhoto,
        imageAuthor: 'Ann H',
        ladderStep: 1,
        enrolled: true,
      ),
      folders: const [SavedFolder(id: 'F', title: 'Сохранённые', isDefault: true)],
    ),
  );
}

/// The same card opened from a STORE set — a word in the catalogue the learner has not taken into
/// study. No folder to name (a catalogue is not one of their shelves), no ladder to draw, and the
/// one move on offer is the decision. This is the case that used to fall back to the old sheet.
class _FromCatalogue extends StatelessWidget {
  const _FromCatalogue();

  @override
  Widget build(BuildContext context) => WordCardScreen(
    mode: WordCardMode.folder,
    onSpeak: () {},
    onTrain: () {},
    onEnroll: () {},
    subject: WordCardSubject.fromWord(
      Word(
        termId: '01WEATHER',
        term: 'How is the weather?',
        translation: 'Как погода?',
        transcription: 'haʊ ɪz ðə ˈwɛðər',
        example: 'How is the weather in your city right now?',
        type: 'phrase',
        imageUrl: _formPhoto,
        imageAuthor: 'Pixabay',
        ladderStep: 0,
      ),
    ),
  );
}

/// The one API the scope hands out; which fake it forwards to is switched per preview entry.
///
/// The field is `delegate` and not `target`, which it used to be: `target` is now also the name of
/// a language parameter on three of these methods, and a parameter shadowing the field it delegates
/// to compiles into an infinite silence.
class _RoutingApi implements ApiClient {
  static ApiClient delegate = _FakeApi();

  @override
  Future<List<SearchHit>> search(
    String query, {
    int limit = 20,
    String? source,
    String? target,
    String? taughtSide,
  }) => delegate.search(query, limit: limit, source: source, target: target, taughtSide: taughtSide);

  @override
  Future<SearchLanguages> searchLanguages() => delegate.searchLanguages();

  @override
  Future<InstantHint> instantHint(
    String query, {
    String? source,
    String? target,
    String? taughtSide,
  }) => delegate.instantHint(query, source: source, target: target, taughtSide: taughtSide);

  @override
  Future<LookupOutcome> lookupWord(
    String query, {
    String? source,
    String? target,
    String? taughtSide,
  }) => delegate.lookupWord(query, source: source, target: target, taughtSide: taughtSide);

  @override
  Future<SavedSearchResult> addSearchResult({
    String? lookupId,
    String? termId,
    String? collectionId,
  }) => delegate.addSearchResult(lookupId: lookupId, termId: termId, collectionId: collectionId);

  @override
  noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

class _FakeApi implements ApiClient {
  _FakeApi({this.capReached = false, this.slow = false});

  final bool capReached;
  final bool slow;

  static const _catalogue = [
    SearchHit(
      termId: '01HOLE',
      text: 'hole',
      type: 'word',
      transcription: 'hoʊl',
      translation: 'дыра',
      description:
          'This is a space or opening in a solid object or surface. Air, water or light can pass through it.',
      example: 'I found a hole in my shirt after playing outside.',
      exampleTranslation: 'Я нашёл дыру в своей рубашке после игры на улице.',
      cefr: 'A1',
    ),
    SearchHit(termId: '01HP', text: 'hole punch', type: 'word', translation: 'дырокол', cefr: 'B1'),
    SearchHit(termId: '01PH', text: 'pothole', type: 'word', translation: 'выбоина', cefr: 'B2'),
    SearchHit(termId: '01HW', text: 'hollywood', type: 'word', translation: 'Голливуд', cefr: 'B1'),
    SearchHit(termId: '01HY', text: 'holly', type: 'word', translation: 'омела', cefr: 'C1'),
    SearchHit(termId: '01HL', text: 'hollow', type: 'word', translation: 'пустой', cefr: 'B2'),
    SearchHit(termId: '01HE', text: 'holler', type: 'word', translation: 'кричать', cefr: 'C1'),
    SearchHit(termId: '01FI', text: 'fill in', type: 'phrase', translation: 'вписать', cefr: 'B1'),
    SearchHit(termId: '01FG', text: 'filling', type: 'word', translation: 'начинка', cefr: 'B2'),
  ];

  /// Every answer keyed by what is typed, in BOTH directions.
  ///
  /// The Russian rows are not invented: they are what the live backend returned on the real DeepL
  /// and the real lookup model («случай» → `case`, «как дела» → `how are you`). A preview that made
  /// up prettier answers than the server gives would be reviewing a screen nobody will see.
  static const _hints = {
    'holl': (text: 'холл', reversed: false),
    'hole': (text: 'дыра', reversed: false),
    'fill out': (text: 'заполнить', reversed: false),
    'fill ou': (text: 'заполнить', reversed: false),
    'случай': (text: 'case', reversed: true),
    'как дела': (text: 'how are you', reversed: true),
  };

  /// What the lookup model refuses to place. Typing it reaches кадр 04's «проверьте написание».
  static const _gibberish = 'asdfgh';

  @override
  Future<SearchLanguages> searchLanguages() async => const SearchLanguages(
    targets: ['en', 'ro', 'es', 'de'],
    natives: ['ru', 'en', 'ro', 'es', 'de'],
    defaultTaught: 'en',
    defaultNative: 'ru',
  );

  @override
  Future<List<SearchHit>> search(
    String query, {
    int limit = 20,
    String? source,
    String? target,
      String? taughtSide,
  }) async {
    await Future<void>.delayed(const Duration(milliseconds: 120));
    final q = query.trim().toLowerCase();

    return _catalogue.where((h) => h.text.toLowerCase().startsWith(q)).take(limit).toList();
  }

  @override
  Future<InstantHint> instantHint(
    String query, {
    String? source,
    String? target,
    String? taughtSide,
  }) async {
    await Future<void>.delayed(const Duration(milliseconds: 150));
    // The one line the field puts up without a translation behind it: a paragraph is not a query.
    if (query.trim().length > 120) return InstantHint(query: query, queryTooLong: true);

    final hint = _hints[query.trim().toLowerCase()];

    return InstantHint(query: query, translation: hint?.text, reversed: hint?.reversed ?? false);
  }

  @override
  Future<LookupOutcome> lookupWord(
    String query, {
    String? source,
    String? target,
    String? taughtSide,
  }) async {
    await Future<void>.delayed(Duration(seconds: slow ? 5 : 2));
    if (capReached) return const LookupOutcome(limitReached: true, dailyCap: 5, usedToday: 5);
    if (query.trim().toLowerCase().contains(_gibberish)) {
      return const LookupOutcome(dailyCap: 5, usedToday: 3, notRecognized: true);
    }
    // Asked in Russian, built in English — the card is about the word, never about the question.
    if (query.trim().toLowerCase() == 'случай') {
      return const LookupOutcome(
        dailyCap: 5,
        usedToday: 2,
        card: LookupCard(
          lookupId: '01CASE',
          text: 'case',
          type: 'word',
          transcription: 'keɪs',
          translation: 'случай',
          description:
              'It is a particular situation or example. People often discuss different situations using this word.',
          example: 'In this case, you should listen to the advice of your friends.',
          exampleTranslation: 'В этом случае вам следует послушать советы ваших друзей.',
          cefr: 'A2',
          fresh: true,
        ),
      );
    }

    return const LookupOutcome(
      dailyCap: 5,
      usedToday: 2,
      card: LookupCard(
        lookupId: '01LOOKUP',
        text: 'fill out',
        type: 'phrase',
        transcription: 'fɪl aʊt',
        translation: 'заполнять (форму)',
        description:
            'When you fill out a form, you write your information in the empty spaces. You do this for applications and documents.',
        example: 'Please fill out this application to proceed.',
        exampleTranslation: 'Пожалуйста, заполните эту заявку, чтобы продолжить.',
        cefr: 'B1',
        fresh: true,
      ),
    );
  }

  @override
  Future<SavedSearchResult> addSearchResult({
    String? lookupId,
    String? termId,
    String? collectionId,
  }) async {
    await Future<void>.delayed(const Duration(milliseconds: 250));

    return const SavedSearchResult(
      termId: '01FILL',
      collectionId: 'F',
      collectionTitle: 'Сохранённые',
      collectionIsDefault: true,
      added: true,
      enrolled: true,
    );
  }

  @override
  noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}
