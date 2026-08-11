/// The home primary action is state-dependent (кадр 2.1). All inputs come from
/// the local DB, so it resolves offline.
enum HomeCtaKind {
  /// Есть due-повторения → «Повторить N».
  review,

  /// Due нет, но есть отриаженные «не знаю» новые слова, ещё не введённые в сессию → «Учить N»
  /// (non-practice сессия вводит их под дневную квоту). Без этой ветки они недостижимы (F8).
  learn,

  /// Due нет, есть новые к изучению, но дневная квота новых исчерпана (F13) → неактивное
  /// состояние «Лимит новых на сегодня» + подсказка про свободную тренировку в коллекциях. Не
  /// открывает заблокированную сессию.
  limitReached,

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

/// Decide the home CTA from local counts. Priority: **due → learn/limit → triage → none**
/// (device-batch F8: «Учить N» sits above triage so triaged-«не знаю» words get introduced).
///
/// Reviews are ALWAYS available when due, and never gated by the new-term quota — they don't spend
/// it (F13 rule 1). New words are offered as «Учить M» only up to [remainingNewQuota]
/// (M = min(learnable, remaining)); once the quota is spent but learnable words remain, the CTA is
/// the inactive [HomeCtaKind.limitReached] instead of opening a session the server would return
/// empty (F13 rule 3). Practice is deliberately NOT a home CTA — free training is entered only from
/// the collection screen. For [HomeCtaKind.triage] the target is the collection with the most
/// untriaged terms (ties broken by collection id, for a stable choice).
HomeCta computeHomeCta({
  required int due,
  required Map<String, int> learnableByCollection,
  required Map<String, int> untriagedByCollection,
  required int remainingNewQuota,
}) {
  if (due > 0) return HomeCta(HomeCtaKind.review, count: due);

  final totalLearnable =
      learnableByCollection.values.fold<int>(0, (s, v) => s + (v > 0 ? v : 0));
  if (totalLearnable > 0) {
    // Quota spent but new words remain → inactive "limit reached", not a blocked session.
    if (remainingNewQuota <= 0) return const HomeCta(HomeCtaKind.limitReached);
    // Offer only what the next session would actually introduce.
    final m = totalLearnable < remainingNewQuota ? totalLearnable : remainingNewQuota;
    return HomeCta(HomeCtaKind.learn, count: m);
  }

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
