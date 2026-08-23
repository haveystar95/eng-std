import '../../data/local/day_key.dart';

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

/// The daily goal when neither the server nor the profile has said otherwise — the same number the
/// onboarding step offers first.
const int kDefaultDailyGoal = 20;

/// THE DAILY GOAL — one definition, for every screen that shows it (QA-BUG-2).
///
/// The goal is a number of NEW WORDS TAKEN INTO STUDY today, and «taken into study» is a deliberate
/// act with four doors: a «не знаю» swipe, a «не уверен» swipe, «Учить это слово» on the word card,
/// and saving a word from search. «Знаю» is not one of them — it means the opposite. All four write
/// exactly one thing, `term_progress.enrolled_at`, and they write it once (the column keeps the
/// FIRST moment), so a word counts once however many trainers it then passes today.
///
/// It is deliberately NOT a count of answers. The session summary used to print today's ANSWERS
/// under the label «Дневная цель» while the home screen printed the new words — «8 / 20» against
/// «3 / 20» on the same day, from the same phone (QA-BUG-2). Answers are a real fact and the
/// summary still states it, as its own «повторено» stat, with no goal attached to it.
///
/// [now] and the stored instants are compared on the LOCAL calendar day: a goal rolls over at the
/// learner's midnight, not at UTC's.
int newWordsToday(Iterable<DateTime> enrolments, DateTime now) {
  final today = localDayKey(now);
  var count = 0;
  for (final at in enrolments) {
    if (localDayKey(at.toLocal()) == today) count++;
  }
  return count;
}

/// The goal's TARGET (its right-hand number): the server's daily new-term quota when the first
/// `/stats` has arrived, else the profile's own goal — the value the learner picked, which is what
/// the server derives its quota from anyway. Never zero, so nothing divides by it.
int dailyGoalTarget({required int newGoal, required int profileGoal}) {
  if (newGoal > 0) return newGoal;
  return profileGoal > 0 ? profileGoal : kDefaultDailyGoal;
}
