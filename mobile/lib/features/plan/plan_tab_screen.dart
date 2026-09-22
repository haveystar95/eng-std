import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import '../../data/image_loader.dart';
import '../../data/plan/plan_models.dart';
import '../collections/collection_detail_screen.dart';
import '../profile/profile_avatar.dart';
import 'entry/plan_entry_screen.dart';
import 'plan_day_plate_view.dart';
import 'day/open_day.dart';
import 'plan_notifications_host.dart';
import 'plan_providers.dart';
import 'route/plan_route.dart';
import 'route/route_examples.dart';
import 'plan_rules.dart';
import 'plan_sheets.dart';
import 'plan_stage_text.dart';
import 'plan_tab_parts.dart';

/// ТАБ «ПЛАН» — кадры 21-1 … 21-14 и 22-5a/b/c (наряд PLAN-UI).
///
/// One screen, every state of it named by the SERVER's answer and drawn from it: no plan (21-1),
/// the plate of the day (21-2, 21-3), the closed day (21-4), the plan run to its end (21-7), the
/// event that passed (21-14), day one still being written (22-5a) or failed (22-5c). The route
/// under the plate, the rescue kit, the finished plans — the same blocks in every state that has
/// them. Ground #EFEBE3, fields 20, plate → route 28, between sections 32 (записка).
///
/// The tab reads the network on every entry and keeps the last answer for the offline read (§6);
/// nothing here computes days, slots or the countdown — see `PlanTabController`.
class PlanTabScreen extends ConsumerStatefulWidget {
  const PlanTabScreen({super.key});

  @override
  ConsumerState<PlanTabScreen> createState() => _PlanTabScreenState();
}

class _PlanTabScreenState extends ConsumerState<PlanTabScreen> {
  @override
  void initState() {
    super.initState();
    // The provider loads itself on first watch; a later visit re-reads silently.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) unawaited(ref.read(planTabProvider.notifier).refresh());
    });
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(planTabProvider);
    final bottomInset =
        AppTabBarMetrics.height +
        AppTabBarMetrics.bottomInset +
        MediaQuery.viewPaddingOf(context).bottom +
        AppSpacing.s16;

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark,
      child: ColoredBox(
        color: AppColors.ground,
        child: SafeArea(
          bottom: false,
          child: state.when(
            loading: () => _Page(bottomInset: bottomInset, children: const []),
            error: (e, _) => _Page(
              bottomInset: bottomInset,
              children: [
                PlanLoadFailedCard(
                  onRetry: () => ref.read(planTabProvider.notifier).refresh(silent: false),
                ),
              ],
            ),
            data: (s) => PlanTabBody(state: s, bottomInset: bottomInset),
          ),
        ),
      ),
    );
  }
}

/// The tab's page: whatever stands at the top, then the blocks, under the tab bar's inset.
///
/// The top is NOT always the same row any more. With no plan (кадр 21-1) the tab wears its own
/// name — «План» 28/800 with the avatar. With a plan the name is gone and [PlanHeader] stands
/// there instead: the canvas subtracted the word «План» from the header, because the brow already
/// says it («План · день 2 из 7») and the big type belongs to the plan's own name.
class _Page extends StatelessWidget {
  const _Page({required this.children, required this.bottomInset, this.top, this.leading});

  final List<Widget> children;
  final double bottomInset;

  /// The plan's header. Null — the tab's own name row (no plan, or nothing loaded yet).
  final Widget? top;

  /// A back chevron in place of nothing — the reading mode of a finished plan is pushed.
  final Widget? leading;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);

    return ListView(
      padding: EdgeInsets.fromLTRB(20, AppSpacing.s8, 20, bottomInset),
      children: [
        top ??
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 2),
              child: Row(
                children: [
                  ?leading,
                  Expanded(child: Text(l.planTitle, style: AppText.screenTitle)),
                  const ProfileAvatarButton(),
                ],
              ),
            ),
        ...children,
      ],
    );
  }
}

/// THE BODY, by state. Public so the reading mode of a finished plan can draw the same thing.
class PlanTabBody extends ConsumerStatefulWidget {
  const PlanTabBody({
    super.key,
    required this.state,
    required this.bottomInset,
    this.readOnly = false,
    this.leading,
  });

  final PlanTabState state;
  final double bottomInset;

  /// A finished plan opened from the list (кадр 21-7, режим чтения): no menu, no hints, no kit,
  /// «Открыть коллекцию» instead of «Собрать новый план».
  final bool readOnly;
  final Widget? leading;

  @override
  ConsumerState<PlanTabBody> createState() => _PlanTabBodyState();
}

class _PlanTabBodyState extends ConsumerState<PlanTabBody> {
  /// The first-time hints stay until the FIRST ACTION on the tab — a tap on the plate, the menu,
  /// the kit — and then go for good.
  bool _acted = false;

  PlanTabState get s => widget.state;
  Plan? get plan => s.plan;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final p = plan;
    const gap = SizedBox(height: 32);

    if (p == null) {
      return _Page(
        bottomInset: widget.bottomInset,
        leading: widget.leading,
        children: [
          if (s.offline) ...[const SizedBox(height: 6), _OfflineLine(l.planTabOffline)],
          const SizedBox(height: 32),
          _EmptyState(onStart: _openEntry),
          gap,
          const PlanExamplesStrip(),
          gap,
          PlanFinishedList(rows: s.finished, onOpen: _openFinished),
        ],
      );
    }

    _prefetchPhotos(p);
    final hints = ref.watch(planHintsProvider).value;
    final focus = s.focusDay;
    final showsDone = p.allDaysClosed && p.status.isLive || (widget.readOnly && p.status == PlanStatus.finished);
    final overdue = p.status == PlanStatus.overdue;
    final firstPlan = !widget.readOnly && hints != null && !hints.tabShown && !_acted;
    final showTabHints = firstPlan && focus != null && !focus.isClosed && !focus.lessonBuilding && !overdue && !showsDone;
    final showCloseHint = !widget.readOnly && hints != null && !hints.closeShown && !_acted && s.showsClosedDay;
    final dayLabel = focus?.number ?? p.daysTotal;
    // «Начни с этапа …» names the plate's own first row — «Слова» on a scene day, «Повторение» on a review, «Вспомнить» on
    // the rehearsal (приёмка CLIENT-CONV-1c 22.09); a day with no rows yet has no hint.
    final firstStage = _firstStage(s.room, focus);

    return _Page(
      bottomInset: widget.bottomInset,
      leading: widget.leading,
      top: PlanHeader(
        brow: l.planHeaderBrow(dayLabel, p.daysTotal),
        shortTitle: p.shortTitle,
        goal: p.goalText,
        total: p.daysTotal,
        closed: p.closedDays,
        onMenu: widget.readOnly ? (_) {} : _openMenu,
        trailing: widget.readOnly ? null : const ProfileAvatarButton(),
        leading: widget.leading,
      ),
      children: [
        if (s.offline) ...[const SizedBox(height: 8), _OfflineLine(l.planTabOffline)],
        // Шапка → плита: 32, как между всеми зонами экрана (кадр 21-2).
        const SizedBox(height: 32),
        // ПЛАШКА ПЕРЕСБОРКИ (21-13) — состояние плана, а не сообщение: кнопки у неё нет, и она
        // остаётся на экране. Сколько дней пропущено и какие дни слиты, контракт не отдаёт —
        // плашка говорит то, что сервер сказал (`days_shortened_from`), и не больше.
        if (p.daysShortenedFrom != null && p.daysShortenedFrom! > p.daysTotal) ...[
          PlanNoticeBanner(text: l.planRebuiltTitle(p.daysShortenedFrom!, p.daysTotal)),
          const SizedBox(height: 14),
        ],
        // THE PLATE — or the card that stands in its place.
        if (showsDone)
          PlanDoneCard(
            plan: p,
            readOnly: widget.readOnly,
            onNewPlan: _newPlanAfterDone,
            onOpenCollection: p.collectionId == null ? null : _openCollection,
          )
        else if (overdue)
          PlanOverdueCard(plan: p, onFinish: _finish, onContinue: _continueAfterEvent)
        else if (focus != null)
          // 22-5a/22-5c живут НА ПЛИТЕ, а не отдельной карточкой: канва не уводит человека из
          // таба ни пока день пишется, ни когда он не собрался — шапка, плита и маршрут стоят на
          // местах, меняется только середина плиты.
          PlanDayPlateView(
            plan: p,
            day: focus,
            room: s.room,
            onOpen: () => _openDay(focus),
            onRetryLesson: () => _retryLesson(focus),
          ),
        if (showTabHints && firstStage != null) ...[
          const SizedBox(height: 14),
          PlanHintLine(text: l.planHintFirstStart(planStageName(l, firstStage)), visible: true),
        ],
        if (showCloseHint) ...[
          const SizedBox(height: 14),
          PlanHintLine(text: l.planHintFirstReturn, visible: true),
        ],
        gap,
        // THE ROUTE, under its countdown when the plan has a date.
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 2),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              if (p.untilPhrase != null && p.untilPhrase!.isNotEmpty) PlanRouteHeader(phrase: p.untilPhrase!),
              if (showTabHints)
                Padding(
                  padding: const EdgeInsets.fromLTRB(0, 6, 0, 4),
                  child: PlanHintLine(text: l.planHintFirstRoute(dayLabel), visible: true),
                ),
              // Тап по узлу — кабинет ТОГО ЖЕ дня (21-2b); в режиме чтения узлы не нажимаются.
              PlanRoute(
                plan: p,
                onOpenDay: widget.readOnly ? null : _openDay,
                explainDay: ref.watch(planExplainDayProvider),
                focus: ref.watch(planFocusDayProvider),
              ),
            ],
          ),
        ),
        if (!widget.readOnly) ...[
          gap,
          PlanFinishedList(rows: s.finished, onOpen: _openFinished),
        ],
      ],
    );
  }

  String? _prefetched;

  /// The first row the day's plate draws — the room's stages as the plate reads them (absent and unknown ones skipped),
  /// else the route's while the room is on its way; null — the day has no rows yet.
  static PlanStage? _firstStage(PlanDayRoom? room, PlanDayRoute? day) {
    for (final s in room?.stages ?? const <PlanStageProgress>[]) {
      if (s.state != PlanStageState.absent && s.stage != PlanStage.unknown) return s.stage;
    }
    final route = day?.stages ?? const <PlanRouteStage>[];
    return route.isEmpty ? null : route.first.stage;
  }

  /// Фото дней, до которых маршрут дошёл, и трёх следующих — заранее, одним заходом общего
  /// загрузчика (§3 наряда PLAN-UI-3). Второй раз тот же набор не просится.
  void _prefetchPhotos(Plan p) {
    final dpr = MediaQuery.maybeDevicePixelRatioOf(context) ?? 2;
    final urls = planRoutePrefetch(p, dpr);
    final key = urls.join('|');
    if (key == _prefetched || urls.isEmpty) return;
    _prefetched = key;
    unawaited(ImageLoader.instance.prefetch(urls));
  }

  // ── actions ─────────────────────────────────────────────────────────────────────────────────

  /// The first action on the tab retires the first-time hints (кадр 21-2c: «гаснет после первого
  /// действия»), and the first closing's hint after the first action on a closed day (21-4c).
  void _act() {
    if (_acted) return;
    setState(() => _acted = true);
    final hints = ref.read(planHintsProvider).value;
    if (hints == null) return;
    if (!hints.tabShown) unawaited(ref.read(planHintsProvider.notifier).markTabShown());
    if (!hints.closeShown && s.showsClosedDay) {
      unawaited(ref.read(planHintsProvider.notifier).markCloseShown());
    }
  }

  /// ПЛИТА ОТКРЫВАЕТ КАБИНЕТ ТОГО ЖЕ ДНЯ (кадр 21-2 → 23-0a, наряд DAY-UI). Дверь одна на все
  /// входы — тап по плите, уведомление и ссылка `engstd://plan/day/{id}` зовут [openDayRoom],
  /// и он же перечитывает состояние таба, когда из дня возвращаются.
  void _openDay(PlanDayRoute day) {
    _act();
    final p = plan;
    if (p == null) return;
    unawaited(openDayRoom(context, ref, plan: p, number: day.number));
  }

  Future<void> _openEntry() async {
    AppHaptics.light();
    final started = await openPlanEntry(context);
    if (!mounted || started == null) return;
    // «Начать» → таб: план принимает сам вход, а лист «Как устроен план» приходит следом — один
    // раз, за первым планом (правило целиком — в [showPlanHowSheetOnce]).
    if (!mounted) return;
    await showPlanHowSheetOnce(context, ref);
    // РАЗРЕШЕНИЕ НА УВЕДОМЛЕНИЯ — один раз, после «Начать» на превью и листа «Как устроен план»,
    // не при старте приложения (наряд PLAN-UI-3 §4); следом регистрация push-токена.
    if (mounted) await askPlanNotificationsOnce(ref);
  }

  void _openMenu(BuildContext anchor) {
    final l = AppLocalizations.of(context);
    final p = plan;
    if (p == null) return;
    _act();
    unawaited(
      showFloatingContextMenu(
        context: context,
        anchorContext: anchor,
        barrierLabel: l.commonCloseMenu,
        // ЧЕТЫРЕ ДЕЙСТВИЯ ТЕКСТОМ, без значков (кадр 21-9): разрушающее — терракотой снизу.
        actions: [
          ContextMenuAction(label: l.planMenuDate, onSelected: _changeDate),
          ContextMenuAction(label: l.planMenuNew, onSelected: _newPlan),
          if (p.collectionId != null)
            ContextMenuAction(label: l.planMenuCollection, onSelected: _openCollection),
          ContextMenuAction(label: l.planMenuDelete, destructive: true, onSelected: _delete),
        ],
      ),
    );
  }

  Future<void> _changeDate() async {
    final p = plan;
    if (p == null) return;
    final choice = await showPlanDateSheet(context, p);
    if (choice == null || !mounted) return;
    final controller = ref.read(planTabProvider.notifier);
    try {
      switch (choice) {
        case PlanDateKeep():
          return;
        case PlanDateClear():
          await controller.reschedule(p.id, changeDate: true);
        case PlanDateSet(:final date):
          await controller.reschedule(
            p.id,
            changeDate: true,
            eventDate: '${date.year.toString().padLeft(4, '0')}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}',
          );
      }
    } catch (_) {
      // A refused move (409 `plan_too_short`, no network) leaves the plan as it was — the sheet
      // closed on «Применить» and the tab still shows the truth. No toast (§6).
      AppHaptics.warning();
    }
  }

  Future<void> _newPlan() async {
    final p = plan;
    if (p == null) return;
    final go = await showPlanNewSheet(context, p);
    if (!go || !mounted) return;
    await _finishThenEntry(p);
  }

  /// «Собрать новый план» on the finished card (21-7): the plan is done — close it and go.
  Future<void> _newPlanAfterDone() async {
    final p = plan;
    if (p == null) return;
    await _finishThenEntry(p);
  }

  Future<void> _finishThenEntry(Plan p) async {
    try {
      await ref.read(planTabProvider.notifier).finish(p.id);
    } catch (_) {
      AppHaptics.warning();

      return;
    }
    if (mounted) await _openEntry();
  }

  /// «Дозаниматься» (21-14) — открыть текущий день: план не закрыт, и дни впереди есть.
  void _continueAfterEvent() {
    final day = s.focusDay ?? plan?.currentDay;
    if (day != null) _openDay(day);
  }

  Future<void> _finish() async {
    final p = plan;
    if (p == null) return;
    try {
      await ref.read(planTabProvider.notifier).finish(p.id);
    } catch (_) {
      AppHaptics.warning();
    }
  }

  Future<void> _delete() async {
    final p = plan;
    if (p == null) return;
    final ok = await showPlanDeleteAlert(context, p);
    if (!ok || !mounted) return;
    try {
      await ref.read(planTabProvider.notifier).delete(p.id);
    } catch (_) {
      AppHaptics.warning();
    }
  }

  Future<void> _retryLesson(PlanDayRoute day) async {
    final p = plan;
    final sceneId = day.sceneId;
    if (p == null || sceneId == null) return;
    try {
      await ref.read(planTabProvider.notifier).retryLesson(p.id, sceneId);
    } catch (_) {
      AppHaptics.warning();
    }
  }

  void _openCollection() {
    final p = plan;
    final id = p?.collectionId;
    if (p == null || id == null) return;
    Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => CollectionDetailScreen(collectionId: id, title: p.displayTitle)),
    );
  }

  void _openFinished(PlanRow row) {
    AppHaptics.light();
    Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => PlanFinishedScreen(planId: row.id)),
    );
  }
}

/// ВИТРИНА — таб без плана (кадр 21-1). Латуни нет: метить нечего.
///
/// Что канва вычла: ВОПРОС «К чему готовишься?» — он первый вопрос входа (22-1), а таб обещает
/// РЕЗУЛЬТАТ, а не спрашивает. Ушли и примеры-истории (они живут только на входе), и число «семь
/// дней» (план бывает и на один день), и блок «С чем приходят», и фрагмент дня — день показывает
/// кабинет, а не витрина.
///
/// Над сгибом: заголовок, подпись в одну строку, три правила плана — те же, что в листе 21-8, —
/// и одно угольное действие. Ниже сгиба лента из трёх примеров тем же маршрутом, что в табе
/// ([PlanExamplesStrip]), и «Завершённые планы».
class _EmptyState extends StatelessWidget {
  const _EmptyState({required this.onStart});

  final VoidCallback onStart;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 2),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            l.planEmptyTitle,
            style: const TextStyle(
              fontFamily: AppFonts.literata,
              fontSize: 30,
              fontWeight: FontWeight.w500,
              letterSpacing: -0.6,
              height: 1.15,
              color: AppColors.ink,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            l.planEmptySub,
            style: const TextStyle(
              fontFamily: AppFonts.inter,
              fontSize: 15,
              height: 1.45,
              color: AppColors.secondary,
            ),
          ),
          const SizedBox(height: 28),
          const PlanRules(),
          const SizedBox(height: 28),
          PlanInkButton(label: l.planEmptyCta, onTap: onStart),
        ],
      ),
    );
  }
}

/// «нет сети» — the quiet line over a cached state (§6).
class _OfflineLine extends StatelessWidget {
  const _OfflineLine(this.text);

  final String text;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(horizontal: 2),
    child: Text(
      text,
      style: const TextStyle(fontFamily: AppFonts.inter, fontSize: 14, color: AppColors.tertiary),
    ),
  );
}

/// A finished plan from the list — the same body in its reading mode (кадр 21-7, «Открыть
/// коллекцию»), pushed over the tab with a back chevron.
class PlanFinishedScreen extends ConsumerWidget {
  const PlanFinishedScreen({super.key, required this.planId});

  final String planId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final plan = ref.watch(finishedPlanProvider(planId));
    final bottomInset = MediaQuery.viewPaddingOf(context).bottom + AppSpacing.s26;
    final back = Semantics(
      button: true,
      label: l.commonBack,
      child: InkResponse(
        radius: 22,
        onTap: () => Navigator.of(context).maybePop(),
        child: const SizedBox(
          width: AppSpacing.minTap,
          height: AppSpacing.minTap,
          child: Icon(LucideIcons.chevronLeft, size: 22, color: AppColors.secondary),
        ),
      ),
    );

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark,
      child: Scaffold(
        backgroundColor: AppColors.ground,
        body: SafeArea(
          bottom: false,
          child: plan.when(
            loading: () => _Page(bottomInset: bottomInset, leading: back, children: const []),
            error: (_, _) => _Page(
              bottomInset: bottomInset,
              leading: back,
              children: [
                PlanLoadFailedCard(onRetry: () => ref.invalidate(finishedPlanProvider(planId))),
              ],
            ),
            data: (p) => PlanTabBody(
              state: PlanTabState(plan: p, finished: const []),
              bottomInset: bottomInset,
              readOnly: true,
              leading: back,
            ),
          ),
        ),
      ),
    );
  }
}
