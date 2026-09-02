import 'package:flutter/widgets.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/features/plan/plan_fail_reason.dart';
import 'package:eng_std/l10n/app_localizations.dart';

/// THE FAIL-CODE MAP, and the two ways it can be wrong.
///
/// It can be SHORT — a code the server sends and this list does not know — and then the day says
/// «не удалось собрать день», which is honest and useless. Or it can be STALE, still answering for
/// codes the server stopped sending, and then nobody can tell which half of it is live; that is the
/// half this file is really about, because the v0.4 rewrite retired every `day.*` verdict at once.
///
/// The rule the sentences are held to is Д-19's: never a plausible wrong reason. Two codes get the
/// same sentence only where they mean the same thing, and a code nobody recognises gets the neutral
/// one rather than a guess made from the shape of its name.
void main() {
  late AppLocalizations ru;
  late AppLocalizations en;

  setUp(() async {
    TestWidgetsFlutterBinding.ensureInitialized();
    ru = await AppLocalizations.delegate.load(const Locale('ru'));
    en = await AppLocalizations.delegate.load(const Locale('en'));
  });

  /// Every code the server can put in `fail_code` — the fatal set of p2.plan-day v0.4 plus the one
  /// outline verdict that survived the rewrite.
  const live = [
    'card.gap_missing',
    'card.gap_outside_frame',
    'card.translation_has_gap',
    'card.translation_missing_key',
    'card.filler_not_card',
    'card.clone',
    'card.example_is_a_term',
    'card.example_skeleton_clone',
    'card.word_is_basic',
    'card.kind_size',
    'card.term_is_a_name',
    'card.translation_is_transliteration',
    'card.skill_ref_invalid',
    'card.number_value_mismatch',
    'day.shelf_missing',
    'outline.target_language',
  ];

  test('every live code has a sentence of its own, in both languages', () {
    for (final code in live) {
      expect(
        planFailReason(ru, code),
        isNot(ru.planFailUnknown),
        reason: '$code falls through to the neutral sentence — the map is short',
      );
      expect(
        planFailReason(en, code),
        isNot(en.planFailUnknown),
        reason: '$code has no English wording',
      );
    }
  });

  test('no two live codes are answered with the same sentence', () {
    // A new code INHERITING an old one's wording is the point — `card.gap_outside_frame` says what
    // `day.slot_outside_frame` said, so it reuses that string rather than a second phrasing of it.
    // Two LIVE codes sharing one is a different thing: it means the day cannot say which of two
    // failures happened, which is Д-19 growing back one sentence at a time.
    final byWording = <String, List<String>>{};
    for (final code in live) {
      byWording.putIfAbsent(planFailReason(ru, code), () => []).add(code);
    }

    expect(byWording.values.where((codes) => codes.length > 1), isEmpty);
  });

  test('the retired `day.*` verdicts are gone, not quietly re-answered', () {
    // v0.4 moved every card-level verdict from `day.` to `card.`, and the day-level ones it did not
    // keep simply stopped existing. A client that still answers for them cannot be read as a list
    // of what the server actually sends.
    const retired = [
      'day.example_is_a_term',
      'day.example_duplicated',
      'day.example_missing',
      'day.key_is_the_term',
      'day.key_duplicated',
      'day.key_not_support_language',
      'day.kind_mismatch',
      'day.array_count',
      'day.term_count',
      'day.checkpoint_uncovered',
      'day.checkpoint_on_word',
      'day.checkpoint_out_of_range',
      'day.term_is_a_name',
      'day.slot_outside_frame',
      'day.image_prompt_missing',
      'day.description_gives_away',
      'day.role_line_invented',
      'plan.term_repeated',
    ];

    for (final code in retired) {
      expect(planFailReason(ru, code), ru.planFailUnknown, reason: '$code is still answered for');
    }
  });

  test('an unknown code and no code at all both get the neutral sentence', () {
    expect(planFailReason(ru, 'card.something_v0_5_invented'), ru.planFailUnknown);
    expect(planFailReason(ru, null), ru.planFailUnknown);
    expect(planFailReason(ru, ''), ru.planFailUnknown);
  });
}
