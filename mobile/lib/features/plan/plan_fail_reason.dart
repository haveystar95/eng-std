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
/// день» — and nothing else. Guessing from a code's shape («it starts with `card.example`, so
/// probably…») is how the old sentence was wrong in the first place, and a plausible wrong reason
/// costs more than an honest missing one. The list grows when the server grows a code; the map is
/// in `backend2/docs/plan-map.md` §3.
///
/// The codes turned over wholesale when the day stopped being three arrays and became a scene
/// (p2.plan-day v0.4): what used to be a `day.*` verdict about the whole payload is now a `card.*`
/// one about ONE card, because that is the address a repair call is pointed at. The gone codes are
/// gone from here too — a client that keeps answering for a code the server no longer sends is a
/// list nobody can trust to be current — and where the new code says what an old one said, it
/// inherits the old one's sentence rather than a second wording of the same thing.
library;

import 'package:dio/dio.dart' show DioException;

import 'package:eng_std/l10n/app_localizations.dart';

import '../../data/api_client.dart' show problemCode;

/// The sentence for [failCode], or the neutral one when there is no sentence to give.
///
/// [failCode] is null on every day that did not fail, and on a failure with no verdict at all — a
/// vendor error, a write that did not land. Both get the neutral answer, which is true of both.
String planFailReason(AppLocalizations l, String? failCode) => switch (failCode) {
  // ── one card, one address: the day goes back for a repair of that card ───────────────────────
  'card.gap_missing' => l.planFailGapMissing,
  'card.gap_outside_frame' => l.planFailSlotOutsideFrame,
  'card.translation_has_gap' => l.planFailTranslationHasGap,
  'card.translation_missing_key' => l.planFailTranslationMissingKey,
  'card.filler_not_card' => l.planFailFillerNotCard,
  'card.clone' => l.planFailTermRepeated,
  'card.example_is_a_term' => l.planFailExampleIsATerm,
  'card.example_skeleton_clone' => l.planFailExampleDuplicated,
  'card.example_without_translation' => l.planFailExampleWithoutTranslation,
  'card.word_is_basic' => l.planFailWordIsBasic,
  'card.kind_size' => l.planFailKindSize,
  'card.term_is_a_name' => l.planFailTermIsAName,
  'card.translation_is_transliteration' => l.planFailKeyIsTheTerm,
  'card.skill_ref_invalid' => l.planFailSkillRefInvalid,
  'card.number_value_mismatch' => l.planFailNumberValueMismatch,
  // ── about the DAY, with no card to point at, so the whole day goes back ──────────────────────
  'day.shelf_missing' => l.planFailShelfMissing,
  'outline.target_language' => l.planFailNotTargetLanguage,
  _ => l.planFailUnknown,
};

/// ОТКАЗ ПЛАНА СЛОВАМИ — 409 `plan_day_locked` и `plan_sitting_empty` (наряд DAY-GATE-1, Ч.2.3).
///
/// Null для всего остального, включая «нет сети»: эта функция отвечает ровно за два отказа, у
/// которых есть, что сказать человеку, и ни за один сбой связи. Оба локализуются ПО КОДУ — сервер
/// шлёт `code` в RFC 7807, как и всюду, а прозу пишет клиент, потому что языка у приложения два.
///
/// Почему это важнее, чем кажется: без разбора обе ошибки попадали в общую «не удалось загрузить
/// тренировку» с кнопкой «Ещё раз». Запертый день от повтора не откроется, а закрытый этап не
/// наполнится — человек жал кнопку, получал то же самое и оставался без объяснения, что делать.
String? planRefusalText(AppLocalizations l, Object? error) {
  if (error is! DioException) return null;

  return switch (problemCode(error)) {
    // «сначала закончи день N» — номер держателя приезжает в `meta.blocked_by_day`.
    'plan_day_locked' => l.planErrorDayLocked(_blockedBy(error) ?? '—'),
    'plan_sitting_empty' => l.planErrorSittingEmpty,
    _ => null,
  };
}

/// Номер дня-держателя из `meta.blocked_by_day`, или null.
String? _blockedBy(DioException error) {
  final meta = (error.response?.data as Map?)?['meta'];

  return meta is Map && meta['blocked_by_day'] != null ? '${meta['blocked_by_day']}' : null;
}
