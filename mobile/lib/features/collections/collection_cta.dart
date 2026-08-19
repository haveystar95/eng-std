import '../home/home_cta.dart';

/// The collection screen's PRIMARY action (кадр 2.3). Priority (device-batch F8):
/// **due → learn → triage → none**. «Учить N» ([learnable]: triaged-«не знаю» words with no
/// progress row) sits above triage so the words a user swiped as unknown actually get introduced
/// by a non-practice session — otherwise they are neither due nor untriaged and no button reaches
/// them. All counts are local (offline).
///
/// Practice is deliberately NOT a primary CTA any more (Training Loop v2 / F17): «Тренировка» is a
/// separate, always-available secondary button under this one (see [CollectionDetailScreen]) so the
/// user can drill the whole collection at any moment. When nothing is due / learnable / untriaged
/// this returns [HomeCtaKind.none] and only the secondary practice button shows.
/// The daily new-term quota gates «Учить N» here exactly as on home (F13b): [remainingNewQuota] is
/// the user-wide new_remaining, so «Учить M» = min(learnable, remaining) and, once it's spent while
/// learnable words remain, the CTA is the inactive [HomeCtaKind.limitReached] (the collection's
/// always-available «Свободная тренировка» button sits right below it). Reviews are never gated.
HomeCta computeCollectionCta({
  required int untriaged,
  required int learnable,
  required int due,
  required int remainingNewQuota,
}) {
  if (due > 0) return HomeCta(HomeCtaKind.review, count: due);
  if (learnable > 0) return learnOrLimitCta(learnable, remainingNewQuota);
  if (untriaged > 0) return HomeCta(HomeCtaKind.triage, count: untriaged);
  return const HomeCta(HomeCtaKind.none);
}

/// Is the SECONDARY «Разобрать N» entry point shown, beside the primary CTA (QA-25)?
///
/// The priority above answers «what is the one most useful thing here right now», and the answer
/// stops being triage the moment the first swipe produces something to learn or to review. But the
/// swipe pass is a pass over a WHOLE collection: three words triaged out of forty left the other
/// thirty-seven with no way in, because the only button that reached them had been outranked by
/// «Учить 3». Rung and quota are the primary CTA's business; being able to finish sorting the
/// collection is not, so it gets its own quiet button that lives exactly as long as there is
/// something left to sort.
///
/// Suppressed only when the primary CTA IS triage — one button per action, never the same one twice.
bool showsSecondaryTriage(HomeCta cta, int untriaged) =>
    untriaged > 0 && cta.kind != HomeCtaKind.triage;
