import '../../data/local/day_key.dart';

/// The COLLECTION screen's primary action, state-dependent. All inputs come from the local DB, so
/// it resolves offline.
///
/// It was the HOME screen's too until кадры 17a–17d: the main screen now asks the server what the
/// day holds (`GET /home-plan`) and draws the composition rather than choosing one verb for it, so
/// `computeHomeCta` is gone and only [computeCollectionCta] builds one of these.
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

  /// Свободная тренировка. Только для экрана коллекции (кнопка «Тренировка»).
  practice,

  /// Слов нет вовсе (новый пользователь) → кнопки нет, генерация — первый блок.
  none,
}

/// A resolved CTA. [count] is the relevant number; [collectionId] is the triage target (the
/// collection with the most eligible terms) for [triage].
class HomeCta {
  const HomeCta(this.kind, {this.count = 0, this.collectionId});

  final HomeCtaKind kind;
  final int count;
  final String? collectionId;
}

/// The one learn-vs-limit decision (F13/F13b), so the quota gate lives in a single place: offer «Учить M» for M = min(learnable, remaining), or the inactive
/// [HomeCtaKind.limitReached] once the day's new-term quota is spent. The caller applies it only when there
/// are learnable words and nothing due.
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
