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
    await _db.applyDelta(termUpserts: [
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
    ]);
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
              collectionsProvider.overrideWith((ref) => Stream.value([
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
                  ])),
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
          onTap: () => Navigator.of(context)
              .push(MaterialPageRoute<void>(builder: (_) => build())),
        ),
      );

  Widget _search({required bool cap, required bool slow}) {
    _RoutingApi.target = _FakeApi(capReached: cap, slow: slow);

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

/// The one API the scope hands out; which fake it forwards to is switched per preview entry.
class _RoutingApi implements ApiClient {
  static ApiClient target = _FakeApi();

  @override
  Future<List<SearchHit>> search(String query, {int limit = 20}) =>
      target.search(query, limit: limit);

  @override
  Future<InstantHint> instantHint(String query) => target.instantHint(query);

  @override
  Future<LookupOutcome> lookupWord(String query) => target.lookupWord(query);

  @override
  Future<SavedSearchResult> addSearchResult({
    String? lookupId,
    String? termId,
    String? collectionId,
  }) =>
      target.addSearchResult(lookupId: lookupId, termId: termId, collectionId: collectionId);

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

  static const _hints = {
    'holl': 'холл',
    'hole': 'дыра',
    'fill out': 'заполнить',
    'fill ou': 'заполнить',
  };

  @override
  Future<List<SearchHit>> search(String query, {int limit = 20}) async {
    await Future<void>.delayed(const Duration(milliseconds: 120));
    final q = query.trim().toLowerCase();

    return _catalogue.where((h) => h.text.toLowerCase().startsWith(q)).take(limit).toList();
  }

  @override
  Future<InstantHint> instantHint(String query) async {
    await Future<void>.delayed(const Duration(milliseconds: 150));

    return InstantHint(query: query, translation: _hints[query.trim().toLowerCase()]);
  }

  @override
  Future<LookupOutcome> lookupWord(String query) async {
    await Future<void>.delayed(Duration(seconds: slow ? 5 : 2));
    if (capReached) return const LookupOutcome(limitReached: true, dailyCap: 5, usedToday: 5);

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
