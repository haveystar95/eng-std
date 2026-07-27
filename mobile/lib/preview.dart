import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'core/theme.dart';
import 'data/models.dart';
import 'data/providers.dart';
import 'features/home/home_screen.dart';

/// Design preview harness — renders the real screens with sample data so the
/// UI can be reviewed without a backend / login. Not used by the shipped app.
void main() {
  final cards = [
    ReviewCard(word: Word(id: 1, collectionId: 1, term: 'boarding pass', translation: 'посадочный талон', transcription: 'ˈbɔːdɪŋ pɑːs', example: 'Please have your boarding pass ready.', cefrLevel: 'A2')),
    ReviewCard(word: Word(id: 2, collectionId: 1, term: 'in advance', translation: 'заранее', transcription: 'ɪn ədˈvɑːns', example: 'Please book your seat in advance.', cefrLevel: 'B1')),
  ];
  final collections = [
    WordCollection(id: 1, title: 'Airport & Travel', emoji: '✈️', topic: 'travel', source: 'ai', wordsCount: 12),
    WordCollection(id: 2, title: 'Everyday Essentials', emoji: '📚', topic: 'general', source: 'manual', wordsCount: 6),
    WordCollection(id: 3, title: 'Business Meetings', emoji: '💼', topic: 'business', source: 'ai', wordsCount: 15),
  ];
  final stats = Stats(
    totalWords: 33, learned: 14, mastered: 5, dueToday: 8, reviewsTotal: 61,
    collections: [
      CollectionStat(id: 1, title: 'Airport & Travel', source: 'ai', total: 12, learned: 7, due: 4),
      CollectionStat(id: 2, title: 'Everyday Essentials', source: 'manual', total: 6, learned: 6, due: 0),
      CollectionStat(id: 3, title: 'Business Meetings', source: 'ai', total: 15, learned: 1, due: 4),
    ],
  );

  runApp(ProviderScope(
    overrides: [
      statsProvider.overrideWith((ref) async => stats),
      dueCardsProvider.overrideWith((ref) async => cards),
      collectionsProvider.overrideWith((ref) async => collections),
      sessionCardsProvider.overrideWith((ref, args) async => cards),
      authControllerProvider.overrideWith(_PreviewAuth.new),
    ],
    child: const _PreviewApp(),
  ));
}

class _PreviewAuth extends AuthController {
  @override
  Future<AppUser?> build() async => AppUser(
        id: 1,
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
