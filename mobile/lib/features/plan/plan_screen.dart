import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';
import 'package:eng_std/l10n/app_localizations.dart';

import '../../data/api_client.dart';
import '../../data/plan_models.dart';
import '../../data/providers.dart';
import 'plan_day_screen.dart';
import 'plan_feedback_screen.dart';
import 'plan_rehearsal_screen.dart';
import 'plan_tab_screen.dart' show abandonPlan;
import 'plan_ui.dart';

/// THE ACTIVE PLAN — кадр 1c · 01, plus the readiness block the наряд asks for.
///
/// The headline number is READINESS TO THE EVENT and not «слов пройдено», and the difference is the
/// whole product: a learner walking into an appointment does not care how many cards they answered,
/// they care whether they can say the six things they came to say. The word count («14 из 18») is
/// demoted to a service line under the bar for exactly that reason.
///
/// Readiness is the server's, verbatim: `0.6 × чек-пойнты, сказанные вслух + 0.4 × слова на ступени
/// C`. Its first half is a literal zero until CONV-1 writes the first conversation, so a fresh plan
/// reads a small number and «Ты уже можешь» reads 0 из 6. That is honest — nothing has been said out
/// loud to anybody yet — and the number can only ever grow, never be revised down.
class PlanScreen extends ConsumerWidget {
  const PlanScreen({super.key, required this.planId, this.embedded = false});

  final String planId;

  /// Drawn INSIDE the План tab rather than pushed on top of it: no back chevron, and the bottom
  /// padding leaves room for the floating tab pill.
  final bool embedded;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final plan = ref.watch(planProvider(planId));

    final body = plan.when(
      loading: () => const Center(child: CircularProgressIndicator(color: AppColors.ink)),
      error: (e, _) => PlanNotice(
        text: isOffline(e) ? l.planErrorOffline : l.planErrorLoadFailed,
        actionLabel: l.generationRetry,
        onAction: () => ref.invalidate(planProvider(planId)),
      ),
      data: (p) => _PlanBody(plan: p, embedded: embedded),
    );

    if (embedded) return body;

    return Scaffold(
      backgroundColor: AppColors.paper,
      body: SafeArea(bottom: false, child: body),
    );
  }
}

class _PlanBody extends ConsumerWidget {
  const _PlanBody({required this.plan, required this.embedded});

  final LearningPlan plan;
  final bool embedded;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final bottomInset = embedded
        ? AppTabBarMetrics.height +
              AppTabBarMetrics.bottomInset +
              MediaQuery.viewPaddingOf(context).bottom +
              AppSpacing.s8
        : AppSpacing.s26;

    return RefreshIndicator(
      color: AppColors.ink,
      backgroundColor: AppColors.surfaceRaised,
      onRefresh: () async {
        ref.invalidate(planProvider(plan.id));
        ref.invalidate(activePlanProvider);
        await ref.read(planProvider(plan.id).future);
      },
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: EdgeInsets.fromLTRB(
          AppSpacing.screenH,
          AppSpacing.s8,
          AppSpacing.screenH,
          bottomInset,
        ),
        children: [
          // ШАПКА: название экрана — кадр D·07. «Шпаргалки» здесь больше нет (наряд DAY-FIX-2,
          // Ч.4.4): её содержимое и есть экран дня, который открывается тапом по строке.
          PlanLabel(l.planListTitle),
          const SizedBox(height: AppSpacing.s16),
          _GoalCard(plan: plan),
          const SizedBox(height: AppSpacing.s22),
          // ДНИ СТРОКАМИ, каждый со своим статусом СЛОВОМ и своим действием в той же строке.
          for (final day in plan.days)
            _DayRow(
              plan: plan,
              day: day,
              onOpen: () => _openDay(context, plan, day.index),
              onRebuild: () => _openDay(context, plan, day.index),
            ),
          const SizedBox(height: AppSpacing.s22),
          // THE EVENT HAS HAPPENED and the plan is still running: the one thing left to do is say
          // how it went. It is the same screen the evening notification opens, offered here for the
          // learner who never tapped it — otherwise a plan whose appointment is over goes on holding
          // its words out of the ordinary day, indefinitely. An undated plan has no event to have
          // happened, so it is never offered «как прошло».
          if ((plan.daysToEvent ?? 1) <= 0) ...[
            PrimaryButton(
              label: l.planFeedbackTitle,
              minHeight: 52,
              onPressed: () => _openFeedback(context, ref, plan),
            ),
            const SizedBox(height: AppSpacing.s12),
          ],
          // THE WAY OUT, and the only one there is. The server allows one running plan per learner,
          // so without this the only exit is the plan's own event. Quiet terracotta text, the app's
          // established shape for a destructive act (rule 20: no fill) — «Фаза 4» draws no such
          // control, and a product that can be entered and not left is worse than a frame with one
          // more link on it.
          Center(
            child: MinTapHeight(
              onTap: () => abandonPlan(context, ref, plan.id),
              child: Text(
                l.planAbandonLink,
                style: AppText.translation.copyWith(
                  fontSize: 14,
                  color: AppColors.destructiveText,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  void _openDay(BuildContext context, LearningPlan plan, int dayIndex) {
    AppHaptics.light();
    Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => PlanDayScreen(plan: plan, dayIndex: dayIndex)),
    );
  }

  Future<void> _openFeedback(BuildContext context, WidgetRef ref, LearningPlan plan) async {
    AppHaptics.light();
    await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => PlanFeedbackScreen(plan: plan)),
    );
    // The plan may be finished now, in which case this screen is about to be replaced by the
    // finished-plan tab — so both reads it hangs off are dropped rather than one.
    ref.invalidate(activePlanProvider);
    ref.invalidate(planProvider(plan.id));
  }
}

/// КАРТОЧКА ЦЕЛИ, компактно — кадр D·07.
///
/// Название цели, длина подготовки, дата события и обратный отсчёт. Четыре факта в три строки, и ни
/// одного процента: готовность к плану сервер считает, но кадр её здесь не показывает, а готовности
/// к СЦЕНЕ он не считает вовсе.
///
/// Что здесь стояло раньше: тёмная плита с числом 21 кеглем 52 и подписью «ступень A · знакомство с
/// материалом». Число было правдой, подпись — служебной лестницей, о которой человек не просил и
/// которой в продукте нет нигде больше.
class _GoalCard extends StatelessWidget {
  const _GoalCard({required this.plan});

  final LearningPlan plan;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final days = plan.daysToEvent;

    return PaperCard(
      radius: 18,
      padding: const EdgeInsets.fromLTRB(18, 16, 18, 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            plan.title,
            style: AppText.collectionNameCard.copyWith(fontSize: 21, height: 1.25),
          ),
          const SizedBox(height: 8),
          Text(
            [
              // ДНИ ПОДГОТОВКИ, а не строки списка: прогон — не день подготовки, он идёт
              // «накануне» и своей строкой (кадр D·07 говорит «4 дня» над четырьмя сценами).
              l.planDaysCount(plan.introDays.length),
              if ((plan.eventDate ?? '').isNotEmpty)
                l.planEventAt(planDateLabel(context, plan.eventDate!)),
            ].join(' · '),
            style: AppText.translation.copyWith(fontSize: 13, color: AppColors.tertiary),
          ),
          const SizedBox(height: 4),
          Text(
            switch (days) {
              null => l.planEntryHintNoDate,
              final int d when d > 0 => l.planDaysLeft(d),
              0 => l.planEventToday,
              _ => l.planEventPassed,
            },
            style: AppText.translation.copyWith(fontSize: 13, color: AppColors.brassInk),
          ),
        ],
      ),
    );
  }
}

/// ОДИН ДЕНЬ ПЛАНА СТРОКОЙ — кадр D·07.
///
/// У дня с материалом ОДНО слово состояния, и его считает сервер (наряд DAY-FIX-2, Ч.3): «не
/// начат» · «идёт · около N минут» · «пройден». Живой прогон 05.09 показал три экрана с тремя
/// счётами одного дня; теперь вкладка, экран дня и шапка присеста читают одно поле. Дню без
/// материала — слово о сборке: «собирается» / «не собрался» / «ждёт очереди», это другой факт.
///
/// Иконка только у пройденного (галочка), потому что слово читается, а кружок с номером — нет
/// (урок Д-23). Дни различают номер сцены сверху и подстрока вводки под названием.
///
/// Действие стоит В СТРОКЕ и только у сегодняшнего дня, и оно говорит ТО ЖЕ, что слово состояния:
/// «Начать день» у не начатого, «Продолжить» у идущего. «Собрать заново» — у несобравшегося.
class _DayRow extends StatelessWidget {
  const _DayRow({
    required this.plan,
    required this.day,
    required this.onOpen,
    required this.onRebuild,
  });

  final LearningPlan plan;
  final PlanDay day;
  final VoidCallback onOpen, onRebuild;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final state = _PlanRowState.of(plan, day);
    final today = state == _PlanRowState.today;
    final rehearsal = day.kind == PlanDayKind.finalRun;

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
      decoration: BoxDecoration(
        // Акцент один на экран, и в списке дней он занят сегодняшним днём (записка к серии).
        color: today ? AppColors.planSelected : null,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: switch (state) {
            _PlanRowState.today => AppColors.brassFrame,
            _PlanRowState.notBuilt => AppColors.destructiveText.withValues(alpha: .3),
            _ => AppColors.dividerFaint,
          },
        ),
      ),
      child: InkWell(
        onTap: state.opensWith(day) ? onOpen : null,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Text(
                    rehearsal
                        ? l.planRowRehearsalWhen
                        : l.planRowDayScene(day.index, day.index),
                    style: AppText.blockLabel.copyWith(
                      color: AppColors.tertiary,
                      letterSpacing: .6,
                    ),
                  ),
                ),
                const SizedBox(width: AppSpacing.s8),
                if (state == _PlanRowState.passed)
                  const Padding(
                    padding: EdgeInsets.only(right: 5),
                    child: Icon(LucideIcons.check, size: 12, color: AppColors.brassInk),
                  ),
                Text(
                  state.word(l, day),
                  style: AppText.blockLabel.copyWith(color: state.color, letterSpacing: .6),
                ),
              ],
            ),
            const SizedBox(height: 6),
            Text(
              day.title,
              style: AppText.collectionNameCard.copyWith(
                fontSize: today ? 19 : 17,
                fontWeight: today ? FontWeight.w600 : FontWeight.w500,
                color: state == _PlanRowState.waiting ? AppColors.tertiary : AppColors.ink,
              ),
            ),
            if (_subtitle(l) case final subtitle? when subtitle.isNotEmpty) ...[
              const SizedBox(height: 3),
              Text(
                subtitle,
                style: AppText.translation.copyWith(
                  fontSize: 13,
                  height: 1.45,
                  color: AppColors.tertiary,
                ),
              ),
            ],
            // СТРОКИ ЗРЕЛОСТИ И ПРОГОНА ЗДЕСЬ БОЛЬШЕ НЕТ (наряд DAY-FIX-2, Ч.3): у дня ОДНО слово
            // состояния, и оно уже стоит справа от номера. «Прошёл сам 5 из 5 · сразу 0» было
            // счётчиком, а счётчиков на экранах плана не бывает.
            if (today) ...[
              const SizedBox(height: AppSpacing.s12),
              // КНОПКА ГОВОРИТ ТО ЖЕ, ЧТО СЛОВО: «Начать день» / «Продолжить» / «Пройти ещё раз».
              PrimaryButton(
                label: rehearsal ? l.planRehearsalOpen : planDayAction(l, day.dayState),
                minHeight: 46,
                onPressed: rehearsal ? () => _openRehearsalFrom(context) : onOpen,
              ),
            ],
            if (state == _PlanRowState.notBuilt) ...[
              const SizedBox(height: AppSpacing.s12),
              QuietButton(label: l.planDayRebuildDay, onPressed: onRebuild),
              const SizedBox(height: 6),
              // Честная подпись под кнопкой: что именно сорвалось и сколько это займёт. Экран
              // ошибки сборка не показывает — статус приезжает сюда, и объясняться надо здесь.
              Text(
                l.planRowNotBuiltWhy,
                style: AppText.translation.copyWith(fontSize: 12.5, color: AppColors.tertiary),
              ),
            ],
          ],
        ),
      ),
    );
  }

  void _openRehearsalFrom(BuildContext context) {
    AppHaptics.light();
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => PlanRehearsalScreen(planId: plan.id, targetLang: plan.targetLang),
      ),
    );
  }

  /// Подстрока под названием сцены — умение дня, а если его нет, первое предложение вводки.
  ///
  /// Она и различает два дня, начинающихся одинаково (урок Д-23). Пусто — законный ответ: день,
  /// который ещё не написан, ни умений, ни вводки не имеет, и выдумывать их нечем.
  String? _subtitle(AppLocalizations l) {
    if (day.kind == PlanDayKind.finalRun) return l.planRowRehearsalLead;
    if (day.outcomes.isNotEmpty) return day.outcomes.first;
    final intro = day.intro.trim();
    if (intro.isEmpty) return null;
    final stop = intro.indexOf('.');

    return stop > 0 ? intro.substring(0, stop) : intro;
  }
}

/// СТАТУС СТРОКИ — кадр D·07, и это ровно те состояния, в которых день бывает.
///
/// Отдельным типом, а не цепочкой тернарников в вёрстке, потому что слово, цвет и «открывается ли
/// строка» — три ответа на один вопрос, и три отдельных выражения над одним днём расходятся: живой
/// прогон уже ловил день, подписанный «собирается» и открывающийся в пустоту (Д-20).
///
/// СЛОВО У ДНЯ С МАТЕРИАЛОМ — серверное `day_state` (наряд DAY-FIX-2, Ч.3): «не начат» / «идёт ·
/// около N минут» / «пройден». Вкладка его не выводит из фокуса и статуса сборки, а читает.
enum _PlanRowState {
  passed,
  today,
  building,
  notBuilt,
  waiting;

  static _PlanRowState of(LearningPlan plan, PlanDay day) {
    if (day.status == PlanDayStatus.failed) return _PlanRowState.notBuilt;
    if (day.dayState == PlanDayState.done || day.index < plan.focusDayIndex) return _PlanRowState.passed;
    if (day.index == plan.focusDayIndex && day.status.hasMaterial) return _PlanRowState.today;
    if (day.status.isGenerating) return _PlanRowState.building;

    return _PlanRowState.waiting;
  }

  String word(AppLocalizations l, PlanDay day) => switch (this) {
    _PlanRowState.passed => l.planStateDone,
    // ОДНО СЛОВО, серверное: «не начат» или «идёт · около N минут». Прогон накануне — тоже день
    // с материалом, и слово у него то же.
    _PlanRowState.today => planDayStateWord(
      l,
      day.dayState,
      day.minutesLeft,
      conversationMinutes: day.conversationMinutes,
    ),
    _PlanRowState.building => l.planRowBuilding,
    _PlanRowState.notBuilt => l.planRowNotBuilt,
    _PlanRowState.waiting => day.status.hasMaterial
        ? planDayStateWord(
            l,
            day.dayState,
            day.minutesLeft,
            conversationMinutes: day.conversationMinutes,
          )
        : l.planRowWaiting,
  };

  Color get color => switch (this) {
    _PlanRowState.notBuilt => AppColors.destructiveText,
    _PlanRowState.waiting => AppColors.tertiary,
    _ => AppColors.brassInk,
  };

  /// Открывается ли строка тапом. «Собирается» и «не собрался» — нет: у первого нечего
  /// открывать, у второго своя кнопка в строке.
  ///
  /// «Ждёт очереди» открывается, если материал уже написан, и это сознательное расхождение с
  /// кадром: слово «ждёт очереди» про РАСПИСАНИЕ, а не про замок, и отнимать у человека
  /// возможность заглянуть вперёд — это правка механики, о которой наряд не просил.
  bool opensWith(PlanDay day) =>
      this != _PlanRowState.notBuilt && this != _PlanRowState.building && day.status.hasMaterial;
}

