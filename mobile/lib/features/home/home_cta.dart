/// The home primary action is state-dependent (кадр 2.1). All inputs come from
/// the local DB, so it resolves offline.
enum HomeCtaKind {
  /// Есть due-повторения → «Повторить N».
  review,

  /// Due нет, но есть новые неразобранные термины → «Разобрать N».
  triage,

  /// Всё разобрано и повторять нечего, но слова есть → свободная тренировка.
  practice,

  /// Слов нет вовсе (новый пользователь) → кнопки нет, генерация — первый блок.
  none,
}

/// Resolved home CTA. [count] is the relevant number; [collectionId] is the
/// triage target (the collection with the most eligible terms) for [triage].
class HomeCta {
  const HomeCta(this.kind, {this.count = 0, this.collectionId});

  final HomeCtaKind kind;
  final int count;
  final String? collectionId;
}

/// Decide the CTA from local counts. Priority: due → triage → practice → none.
/// For [HomeCtaKind.triage] the target is the collection with the most eligible
/// terms (ties broken by collection id, for a stable choice).
HomeCta computeHomeCta({
  required int due,
  required Map<String, int> untriagedByCollection,
  required int totalWords,
}) {
  if (due > 0) return HomeCta(HomeCtaKind.review, count: due);

  final eligible = untriagedByCollection.entries.where((e) => e.value > 0).toList()
    ..sort((a, b) {
      final byCount = b.value.compareTo(a.value);
      return byCount != 0 ? byCount : a.key.compareTo(b.key);
    });
  if (eligible.isNotEmpty) {
    final total = eligible.fold<int>(0, (s, e) => s + e.value);
    return HomeCta(HomeCtaKind.triage, count: total, collectionId: eligible.first.key);
  }

  if (totalWords > 0) return const HomeCta(HomeCtaKind.practice);
  return const HomeCta(HomeCtaKind.none);
}
