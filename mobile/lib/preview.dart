import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'core/theme.dart';
import 'data/models.dart';
import 'data/providers.dart';
import 'features/home/home_screen.dart';

/// Design preview harness — renders the real screens with sample data so the
/// UI can be reviewed without a backend / login. Not used by the shipped app.
void main() {
  Word word(String id, String term, String tr, String ipa, String ex, String type) => Word(
        termId: id, term: term, translation: tr, transcription: ipa, example: ex, type: type,
      );
  final cards = [
    ReviewCard(word: word('01AAAAAAAAAAAAAAAAAAAAAAA1', 'boarding pass', 'посадочный талон', 'ˈbɔːdɪŋ pɑːs', 'Please have your boarding pass ready.', 'phrase')),
    ReviewCard(word: word('01AAAAAAAAAAAAAAAAAAAAAAA2', 'in advance', 'заранее', 'ɪn ədˈvɑːns', 'Please book your seat in advance.', 'phrase')),
  ];
  WordCollection collection(String id, String title, String emoji, String source, int count) => WordCollection(
        id: id, title: title, emoji: emoji, source: source, type: 'custom', wordsCount: count, sourceLang: 'ru', targetLang: 'en',
      );
  final collections = [
    collection('01BBBBBBBBBBBBBBBBBBBBBBBB1', 'Airport & Travel', '✈️', 'ai', 12),
    collection('01BBBBBBBBBBBBBBBBBBBBBBBB2', 'Everyday Essentials', '📚', 'user', 6),
    collection('01BBBBBBBBBBBBBBBBBBBBBBBB3', 'Business Meetings', '💼', 'ai', 15),
  ];
  final stats = Stats(
    totalWords: 33, learned: 14, mastered: 5, dueToday: 8, reviewsTotal: 61, streakDays: 4,
  );
  final progress = {
    for (final (i, c) in collections.indexed)
      c.id: CollectionProgress(
        collectionId: c.id,
        total: c.wordsCount,
        learned: [8, 2, 15][i],
        mastered: [3, 0, 5][i],
        due: [4, 0, 0][i],
      ),
  };

  runApp(ProviderScope(
    overrides: [
      statsProvider.overrideWith((ref) async => stats),
      dueCardsProvider.overrideWith((ref) async => cards),
      collectionsProvider.overrideWith((ref) async => collections),
      collectionsProgressProvider.overrideWith((ref) async => progress),
      sessionCardsProvider.overrideWith((ref, args) async => cards),
      authControllerProvider.overrideWith(_PreviewAuth.new),
    ],
    child: const _PreviewApp(),
  ));
}

class _PreviewAuth extends AuthController {
  @override
  Future<AppUser?> build() async => AppUser(
        id: '01CCCCCCCCCCCCCCCCCCCCCCCC1',
        name: 'Denis',
        email: 'you@example.com',
        profile: Profile(nativeLanguage: 'ru', targetLanguage: 'en', cefrLevel: 'B1', dailyGoal: 20),
      );
}

class _PreviewApp extends StatelessWidget {
  const _PreviewApp();

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      debugShowCheckedModeBanner: false,
      theme: buildTheme(),
      home: const HomeScreen(),
    );
  }
}
