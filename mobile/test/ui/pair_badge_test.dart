import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/mini_flag.dart';
import 'package:eng_std/ui/pair_badge.dart';

/// The pair badge: two flags and an arrow.
///
/// The first cut set it in TYPE — «EN→ES» — on the letter of rule 14 («на карточках слов и
/// коллекций флагов нет»). The owner overruled it on sight: a pair is glanced at, and two
/// two-letter codes are read instead. Rule 14 was amended rather than quietly broken — a pair badge
/// IS a language context (DECISIONS п. 148) — and what these tests hold is the amended shape, plus
/// the two things that did not change with it: the direction never flips, and a phrasebook says a
/// word instead of promising a course.
void main() {
  Widget host(Widget child, {Locale locale = const Locale('ru')}) => MaterialApp(
    locale: locale,
    localizationsDelegates: AppLocalizations.localizationsDelegates,
    supportedLocales: const [Locale('ru'), Locale('en')],
    home: Scaffold(body: Center(child: child)),
  );

  testWidgets('draws the two flags of the pair, learned first', (tester) async {
    await tester.pumpWidget(host(const PairBadge(learned: 'en', support: 'ru')));

    final flags = tester.widgetList<MiniFlag>(find.byType(MiniFlag)).toList();
    expect(flags, hasLength(2));
    expect(flags.first.languageCode, 'en');
    expect(flags.last.languageCode, 'ru');
  });

  testWidgets('does not flip: a collection\'s pair is a fact, not a direction', (tester) async {
    // The search pill flips — it is a question the learner asks. This is what a folder IS.
    await tester.pumpWidget(host(const PairBadge(learned: 'pl', support: 'ru')));

    final flags = tester.widgetList<MiniFlag>(find.byType(MiniFlag)).toList();
    expect(flags.first.languageCode, 'pl');
    expect(flags.last.languageCode, 'ru');
  });

  testWidgets('a code outside the catalogue still draws — the pair is never half-missing', (
    tester,
  ) async {
    // Every CATALOGUE language now has a painter (`mini_flag_test`), so the fallback is reached only
    // by a code that should not exist — a typo, or a language added to one runtime and forgotten in
    // another. It must still render: a half-drawn badge would be worse than a plain one.
    await tester.pumpWidget(host(const PairBadge(learned: 'en', support: 'xx')));

    expect(find.byType(MiniFlag), findsNWidgets(2));
    expect(find.text('XX'), findsOneWidget);
  });

  testWidgets('sits in a quiet chip — the colour must not float on the paper', (tester) async {
    // Bare flags at 15 were tried and lost: two saturated circles at the end of a title line become
    // the brightest thing on a paper/ink screen. The chip is what frames them (DECISIONS п. 148).
    await tester.pumpWidget(host(const PairBadge(learned: 'en', support: 'ru')));

    final chip = tester.widget<Container>(
      find.ancestor(of: find.byType(MiniFlag).first, matching: find.byType(Container)).first,
    );
    expect((chip.decoration! as BoxDecoration).color, AppColors.faintInk);
  });

  testWidgets('a phrasebook says so instead of naming a pair', (tester) async {
    // «ZH→RU» would promise a course. There are no trainers for zh at all (DECISIONS пп. 84, 136).
    await tester.pumpWidget(
      host(const PairBadge(learned: 'zh', support: 'ru', reference: true)),
    );

    expect(find.text('СПРАВОЧНИК'), findsOneWidget);
    expect(find.byType(MiniFlag), findsNothing);
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

  testWidgets('the phrasebook label sits at tertiary ink — a label, never a headline', (
    tester,
  ) async {
    await tester.pumpWidget(
      host(const PairBadge(learned: 'zh', support: 'ru', reference: true)),
    );

    final style = tester.widget<Text>(find.text('СПРАВОЧНИК')).style!;
    expect(style.color, AppColors.tertiary);
    expect(style.fontFamily, AppFonts.inter);
  });
}
