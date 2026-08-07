import '../home/home_cta.dart';

/// The collection screen's primary action (кадр 2.3). Priority mirrors the home CTA
/// (device-batch F8): **due → learn → triage → practice → none**. «Учить N» ([learnable]:
/// triaged-«не знаю» words with no progress row) sits above triage so the words a user
/// swiped as unknown actually get introduced by a non-practice session — otherwise they
/// are neither due nor untriaged and no button reaches them. Practice is last and only
/// shows once every word is in the SRS, so the empty «Free practice» button no longer
/// appears on a fresh, all-new set. All counts are local (offline).
HomeCta computeCollectionCta({
  required int untriaged,
  required int learnable,
  required int due,
  required int total,
}) {
  if (due > 0) return HomeCta(HomeCtaKind.review, count: due);
  if (learnable > 0) return HomeCta(HomeCtaKind.learn, count: learnable);
  if (untriaged > 0) return HomeCta(HomeCtaKind.triage, count: untriaged);
  if (total > 0) return const HomeCta(HomeCtaKind.practice);
  return const HomeCta(HomeCtaKind.none);
}
