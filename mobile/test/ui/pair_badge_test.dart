import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/mini_flag.dart';
import 'package:eng_std/ui/pair_badge.dart';

/// The pair badge: two codes and an arrow, in type — never a flag.
///
/// `tokens.html` §4б and rule 14 put mini-flags in LANGUAGE contexts only, and say in as many
/// words: «на карточках слов и коллекций флагов нет». A collection card and a session card are
/// exactly those two places. The rule is easy to break by accident later — a flag is the obvious
/// thing to reach for — so it is pinned here rather than left to a review.
void main() {
  Widget host(Widget child, {Locale locale = const Locale('ru')}) => MaterialApp(
    locale: locale,
    localizationsDelegates: AppLocalizations.localizationsDelegates,
    supportedLocales: const [Locale('ru'), Locale('en')],
    home: Scaffold(body: Center(child: child)),
  );

  testWidgets('reads «изучаемый → язык поддержки», in uppercase codes', (tester) async {
    await tester.pumpWidget(host(const PairBadge(learned: 'en', support: 'ru')));

    expect(find.text('EN→RU'), findsOneWidget);
  });

  testWidgets('does not flip: a collection\'s pair is a fact, not a direction', (tester) async {
    // The search pill flips — it is a question the learner asks. This is what a folder IS.
    await tester.pumpWidget(host(const PairBadge(learned: 'pl', support: 'ru')));

    expect(find.text('PL→RU'), findsOneWidget);
    expect(find.text('RU→PL'), findsNothing);
  });

  testWidgets('draws no flag — rule 14 keeps decorative colour out of these cards', (tester) async {
    await tester.pumpWidget(host(const PairBadge(learned: 'en', support: 'ru')));

    expect(find.byType(MiniFlag), findsNothing);
  });

  testWidgets('a phrasebook says so instead of naming a pair', (tester) async {
    // «ZH→RU» would promise a course. There are no trainers for zh at all (DECISIONS пп. 84, 136).
    await tester.pumpWidget(
      host(const PairBadge(learned: 'zh', support: 'ru', reference: true)),
    );

    expect(find.text('СПРАВОЧНИК'), findsOneWidget);
    expect(find.text('ZH→RU'), findsNothing);
  });

  testWidgets('the phrasebook label is localized, not a hard-coded Russian word', (tester) async {
    await tester.pumpWidget(
      host(
        const PairBadge(learned: 'ja', support: 'en', reference: true),
        locale: const Locale('en'),
      ),
    );

    expect(find.text('REFERENCE'), findsOneWidget);
  });

  testWidgets('sits at tertiary ink — a label, never a headline', (tester) async {
    await tester.pumpWidget(host(const PairBadge(learned: 'en', support: 'ru')));

    final style = tester.widget<Text>(find.text('EN→RU')).style!;
    expect(style.color, AppColors.tertiary);
    expect(style.fontFamily, AppFonts.inter);
  });
}
