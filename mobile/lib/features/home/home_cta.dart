import '../../data/models.dart';

/// The home primary action is state-dependent (кадр 2.1). All inputs come from
/// the local DB, so it resolves offline.
enum HomeCtaKind {
  /// Есть due-повторения → «Повторить N».
  review,

  /// Due нет, но в ПУЛЕ есть слова, которые ещё ни разу не показывали → «Учить N» (non-practice
  /// сессия вводит их под дневную квоту). Без этой ветки они недостижимы (F8).
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
/// (device-batch F8: «Учить N» sits above triage so words already taken into study get introduced).
///
/// [learnable] is read off the POOL and is deliberately GLOBAL, not a per-collection sum: a word
/// the learner took into study is theirs to learn even if the collection it came from was deleted
/// or unsubscribed, and the session builder will still deal it. The collection map survives only
/// for [untriagedByCollection], where the number IS about a collection — the triage deck is a pass
/// over one of them.
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
  required int learnable,
  required Map<String, int> untriagedByCollection,
  required int remainingNewQuota,
}) {
  if (due > 0) return HomeCta(HomeCtaKind.review, count: due);

  if (learnable > 0) return learnOrLimitCta(learnable, remainingNewQuota);

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

/// The one learn-vs-limit decision, shared by the home and collection CTAs (F13/F13b) so the quota
/// gate lives in a single place: offer «Учить M» for M = min(learnable, remaining), or the inactive
/// [HomeCtaKind.limitReached] once the day's new-term quota is spent. Callers apply it only when
/// there are learnable words and nothing due.
HomeCta learnOrLimitCta(int learnable, int remainingNewQuota) {
  if (remainingNewQuota <= 0) return const HomeCta(HomeCtaKind.limitReached);
  final m = learnable < remainingNewQuota ? learnable : remainingNewQuota;
  return HomeCta(HomeCtaKind.learn, count: m);
}

/// The home goal ring counts NEW terms introduced today against the daily new-term goal (F13b) —
/// reviews and free practice never count toward a "new words/day" goal. `done = new_today =
/// new_goal − new_remaining`, clamped into `[0, goal]`.
({int done, int goal}) homeGoalRing(Stats stats) {
  final goal = stats.newGoal;
  final done = (stats.newGoal - stats.newRemaining).clamp(0, stats.newGoal);
  return (done: done, goal: goal);
}
