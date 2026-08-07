import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/features/practice_dialog/dialog_models.dart';
import 'package:eng_std/features/practice_dialog/dialog_providers.dart';
import 'package:eng_std/features/practice_dialog/dialog_repository.dart';
import 'package:eng_std/features/practice_dialog/dialog_screen.dart';
import 'package:eng_std/features/practice_dialog/realtime_channel.dart';
import 'package:eng_std/l10n/app_localizations.dart';

/// Widget test: the conversation screen renders the coverage bar + transcript feed from the scripted
/// channel, then reaches the finale with the right word count. Device-batched, so this pins layout.
///
/// Uses a synchronous in-test repository (no drift): the screen is driven by the fake channel under
/// fake time, which a real DB read wouldn't resolve. The DB-backed repository is covered separately
/// in dialog_controller_test.dart (real time).
void main() {
  testWidgets('renders coverage + feed, then the finale', (tester) async {
    await tester.pumpWidget(ProviderScope(
      overrides: [
        dialogRepositoryProvider.overrideWithValue(_StubRepo(const ['withdraw', 'account', 'balance'])),
        // dispose() invalidates this — keep it off the network in the test.
        lastDialogProvider('c1').overrideWith((ref) async => null),
        realtimeChannelFactoryProvider.overrideWithValue(
          // Slow enough that the active conversation is observable for a frame, fast enough that a
          // couple of seconds of pumped time reaches the finale.
          () => FakeRealtimeChannel(
            connectDelay: const Duration(milliseconds: 40),
            botBeat: const Duration(milliseconds: 150),
            listenBeat: const Duration(milliseconds: 150),
            thinkGap: const Duration(milliseconds: 40),
          ),
        ),
      ],
      child: const MaterialApp(
        locale: Locale('ru'),
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: [Locale('ru')],
        home: PracticeDialogScreen(collectionId: 'c1', title: 'At the Bank', targetLang: 'en'),
      ),
    ));

    // start() resolves, the conversation goes active and the bot greets (~40ms).
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 80));

    // Coverage bar shows one chip per collection word.
    expect(find.text('withdraw'), findsWidgets);
    expect(find.text('account'), findsWidgets);
    expect(find.text('balance'), findsWidgets);
    // Coverage counter is present ("0 / 3", "1 / 3", …).
    expect(find.textContaining('/ 3'), findsWidgets);
    // The bot's opening line is in the feed.
    expect(find.textContaining('quick chat'), findsWidgets);

    // Drive the scripted conversation to the bot's wrap-up (channel closes → finale).
    await tester.pump(const Duration(seconds: 2));
    await tester.pump();

    expect(find.text('Разговор окончен'), findsOneWidget);
    expect(find.text('Слов прозвучало: 3 из 3'), findsOneWidget);
    expect(tester.takeException(), isNull);

    // Unmount to cancel the countdown/animation timers.
    await tester.pumpWidget(const SizedBox());
  });
}

/// Synchronous stand-in for [DialogRepository] — fixed words, substring coverage, no DB.
class _StubRepo implements DialogRepository {
  _StubRepo(List<String> words)
      : _words = [
          for (var i = 0; i < words.length; i++) TargetWord(termId: 't${i + 1}', text: words[i]),
        ];

  List<TargetWord> _words;
  final StringBuffer _spoken = StringBuffer();

  @override
  Future<DialogStart> start({required String collectionId, required String clientId}) async =>
      DialogStart(
        dialogId: 'stub',
        realtimeToken: 'stub',
        expiresAt: DateTime(2026, 8, 7, 12),
        model: 'stub',
        targetWords: _words,
        durationSeconds: 200,
      );

  @override
  Future<List<TargetWord>> sendTranscripts(String dialogId, List<TranscriptEvent> events) async {
    for (final e in events) {
      if (e.role == DialogRole.user) _spoken.write(' ${e.text.toLowerCase()}');
    }
    final s = _spoken.toString();
    _words = [for (final w in _words) w.copyWith(used: w.used || s.contains(w.text.toLowerCase()))];
    return _words;
  }

  @override
  Future<DialogSummary> finish(String dialogId) async {
    final used = _words.where((w) => w.used).length;
    return DialogSummary(summary: 'Done.', wordsUsed: used, wordsTotal: _words.length);
  }
}
