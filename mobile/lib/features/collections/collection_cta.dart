import '../home/home_cta.dart';

/// The collection screen's primary action (кадр 2.3). Unlike the home CTA, here
/// **triage is priority #1** («Есть неразобранные слова — приоритет №1»): sort
/// the new words before drilling reviews. Then due reviews, then quiet practice.
/// All counts are local (offline).
HomeCta computeCollectionCta({
  required int untriaged,
  required int due,
  required int total,
}) {
  if (untriaged > 0) return HomeCta(HomeCtaKind.triage, count: untriaged);
  if (due > 0) return HomeCta(HomeCtaKind.review, count: due);
  if (total > 0) return const HomeCta(HomeCtaKind.practice);
  return const HomeCta(HomeCtaKind.none);
}
