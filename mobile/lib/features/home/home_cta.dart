/// The home primary action is state-dependent (кадр 2.1). All inputs come from
/// the local DB, so it resolves offline.
enum HomeCtaKind {
  /// Есть due-повторения → «Повторить N».
  review,

  /// Due нет, но есть отриаженные «не знаю» новые слова, ещё не введённые в сессию → «Учить N»
  /// (non-practice сессия вводит их под дневную квоту). Без этой ветки они недостижимы (F8).
  learn,

  /// Due нет, новых-к-изучению нет, но есть неразобранные термины → «Разобрать N».
  triage,

  /// Свободная тренировка. **Только для экрана коллекции** (кнопка «Тренировка») — на Home
  /// практики больше нет: вход в неё только из коллекции. `computeHomeCta` этот кейс не возвращает;
  /// значение живёт для `computeCollectionCta`/экрана коллекции.
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

/// Decide the home CTA from local counts. Priority: **due → learn → triage → none**
/// (device-batch F8: «Учить N» sits above triage so triaged-«не знаю» words get introduced).
/// Practice is deliberately NOT a home CTA — free training is entered only from the collection
/// screen — so when nothing is due, learnable or untriaged this returns [HomeCtaKind.none].
/// For [HomeCtaKind.triage] the target is the collection with the most untriaged terms
/// (ties broken by collection id, for a stable choice).
HomeCta computeHomeCta({
  required int due,
  required Map<String, int> learnableByCollection,
  required Map<String, int> untriagedByCollection,
}) {
  if (due > 0) return HomeCta(HomeCtaKind.review, count: due);

  final totalLearnable =
      learnableByCollection.values.fold<int>(0, (s, v) => s + (v > 0 ? v : 0));
  if (totalLearnable > 0) return HomeCta(HomeCtaKind.learn, count: totalLearnable);

  final eligible = untriagedByCollection.entries.where((e) => e.value > 0).toList()
    ..sort((a, b) {
      final byCount = b.value.compareTo(a.value);
      return byCount != 0 ? byCount : a.key.compareTo(b.key);
    });
  if (eligible.isNotEmpty) {
    final total = eligible.fold<int>(0, (s, e) => s + e.value);
    return HomeCta(HomeCtaKind.triage, count: total, collectionId: eligible.first.key);
  }

  return const HomeCta(HomeCtaKind.none);
}
