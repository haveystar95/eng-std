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
HomeCta computeCollectionCta({
  required int untriaged,
  required int learnable,
  required int due,
}) {
  if (due > 0) return HomeCta(HomeCtaKind.review, count: due);
  if (learnable > 0) return HomeCta(HomeCtaKind.learn, count: learnable);
  if (untriaged > 0) return HomeCta(HomeCtaKind.triage, count: untriaged);
  return const HomeCta(HomeCtaKind.none);
}
