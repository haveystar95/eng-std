/// WHY A PLAN DAY BURNED, in the learner's language — [planFailReason].
///
/// The server sends a CODE (`fail_code`) and never the sentence. That split is deliberate on both
/// sides: `PlanViolation`'s prose is Russian, quotes the model's own cards and was written for a
/// log, and this app has two languages. So the code crosses the wire and the wording lives here.
///
/// Before it did, the screen had ONE hard-coded sentence — «модель возвращает материал не на том
/// языке» — and used it for every failure there is. The live run's day 2 had died on
/// `day.example_is_a_term`, and the owner was told the model had answered in the wrong language;
/// there was no way to work out what to actually fix (Д-19).
///
/// A code this list does not know gets [AppLocalizations.planFailUnknown] — «не удалось собрать
/// день» — and nothing else. Guessing from a code's shape («it starts with `day.example`, so
/// probably…») is how the old sentence was wrong in the first place, and a plausible wrong reason
/// costs more than an honest missing one. The list grows when the server grows a code; the map is
/// in `backend2/docs/plan-map.md` §3.
library;

import 'package:eng_std/l10n/app_localizations.dart';

/// The sentence for [failCode], or the neutral one when there is no sentence to give.
///
/// [failCode] is null on every day that did not fail, and on a failure with no verdict at all — a
/// vendor error, a write that did not land. Both get the neutral answer, which is true of both.
String planFailReason(AppLocalizations l, String? failCode) => switch (failCode) {
  'day.example_is_a_term' => l.planFailExampleIsATerm,
  'day.example_duplicated' => l.planFailExampleDuplicated,
  'day.example_missing' => l.planFailExampleMissing,
  'outline.target_language' => l.planFailNotTargetLanguage,
  'day.key_is_the_term' => l.planFailKeyIsTheTerm,
  'day.key_duplicated' => l.planFailKeyDuplicated,
  'day.key_not_support_language' => l.planFailKeyNotSupportLanguage,
  'day.kind_mismatch' => l.planFailKindMismatch,
  'day.array_count' || 'day.term_count' => l.planFailCounts,
  'day.checkpoint_uncovered' ||
  'day.checkpoint_on_word' ||
  'day.checkpoint_out_of_range' => l.planFailCheckpoint,
  'day.term_is_a_name' => l.planFailTermIsAName,
  'day.slot_outside_frame' => l.planFailSlotOutsideFrame,
  'day.image_prompt_missing' => l.planFailImagePromptMissing,
  'day.description_gives_away' => l.planFailDescriptionGivesAway,
  'day.role_line_invented' => l.planFailRoleLineInvented,
  'plan.term_repeated' => l.planFailTermRepeated,
  _ => l.planFailUnknown,
};
