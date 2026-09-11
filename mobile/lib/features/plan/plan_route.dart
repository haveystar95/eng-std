import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../data/local/cached_image_provider.dart';
import '../../data/plan/plan_models.dart';
import 'plan_format.dart';

/// МАРШРУТ — узлы 22 с цифрой, строки 64, фото 48 у дней-ситуаций, типографика у повторения и
/// репетиции, правый слот — строка с сервера, пункт назначения двумя строками (кадры 21-2, 21-5,
/// 21-6, 22-4b; записка: «линия 1.5 px через центры, ink до последнего пройденного»).
///
/// One widget for the tab and the preview: the preview draws every node as a future one and lets
/// a scene day be swiped away; the tab draws the ring on «сегодня» and fills the line up to it.
class PlanRoute extends StatelessWidget {
  const PlanRoute({
    super.key,
    required this.plan,
    this.preview = false,
    this.onRemoveScene,
  });

  final Plan plan;

  /// Кадр 22-4b: узлы контурные, слота «сегодня» нет, мишень — контур, свайп убирает сцену.
  final bool preview;

  /// Swipe-to-remove in the preview; null — no swipe.
  final ValueChanged<PlanScene>? onRemoveScene;

  @override
  Widget build(BuildContext context) {
    final days = plan.days;
    final ground = preview ? AppColors.paper : AppColors.ground;
    // ink up to and including the current day's node — «ink до узла «сегодня»».
    var lastInk = -1;
    for (var i = 0; i < days.length; i++) {
      if (days[i].isClosed || days[i].slot.code == PlanSlotCode.today) lastInk = i;
    }
    // Every day closed: the line runs all the way to the target (кадр 21-7).
    final allClosed = days.isNotEmpty && days.every((d) => d.isClosed);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (var i = 0; i < days.length; i++)
          _RouteRow(
            plan: plan,
            day: days[i],
            first: i == 0,
            lineAbove: !preview && i > 0 && i <= lastInk,
            lineBelow: !preview && i < lastInk,
            ground: ground,
            preview: preview,
            onRemove: onRemoveScene,
          ),
        _TargetRow(
          plan: plan,
          filled: !preview && allClosed,
          lineAbove: !preview && allClosed,
          ground: ground,
        ),
      ],
    );
  }
}

class _RouteRow extends StatelessWidget {
  const _RouteRow({
    required this.plan,
    required this.day,
    required this.first,
    required this.lineAbove,
    required this.lineBelow,
    required this.ground,
    required this.preview,
    this.onRemove,
  });

  final Plan plan;
  final PlanDayRoute day;
  final bool first;
  final bool lineAbove, lineBelow;
  final Color ground;
  final bool preview;
  final ValueChanged<PlanScene>? onRemove;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final scene = plan.sceneOf(day);
    final isToday = !preview && day.slot.code == PlanSlotCode.today;
    final closed = !preview && day.isClosed;
    final isScene = day.type == PlanDayType.scene;
    final future = !closed && !isToday;

    final (title, teaches) = switch (day.type) {
      PlanDayType.scene => (day.titleNative ?? scene?.titleNative ?? '', day.teachesNative ?? scene?.teachesNative ?? ''),
      PlanDayType.review => (l.planRouteDayRepeat, _repeatSub(l)),
      PlanDayType.rehearsal => (l.planRouteDayRehearsal, l.planRouteDayRehearsalSub),
      PlanDayType.unknown => (day.titleNative ?? '', day.teachesNative ?? ''),
    };

    final row = SizedBox(
      height: 64,
      child: Row(
        children: [
          _NodeColumn(
            number: day.number,
            ink: closed,
            ring: isToday,
            lineAbove: lineAbove,
            lineBelow: lineBelow,
            first: first,
            ground: ground,
            check: closed,
          ),
          const SizedBox(width: 12),
          if (isScene)
            _Photo(url: scene?.image?.url, dim: future)
          else
            const SizedBox(width: 48),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Flexible(
                      child: Text(
                        title,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          fontFamily: AppFonts.inter,
                          fontSize: 17,
                          fontWeight: FontWeight.w600,
                          height: 1.2,
                          // «название 17/600 — единственное тёмное в строке»; повторение и
                          // репетиция — tertiary.
                          color: isScene ? AppColors.ink : AppColors.tertiary,
                        ),
                      ),
                    ),
                    if (!preview) ..._slot(l),
                  ],
                ),
                if (teaches.isNotEmpty) ...[
                  const SizedBox(height: 3),
                  Text(
                    teaches,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontFamily: AppFonts.inter,
                      fontSize: 14,
                      height: 1.3,
                      color: AppColors.secondary,
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );

    if (!preview || onRemove == null || scene == null) return row;

    // Кадр 22-4b: «убери день свайпом». The core scene cannot go (409 `plan_core_scene`) — the
    // server refuses and the row springs back; nothing is drawn for a refusal the frames do not draw.
    return Dismissible(
      key: ValueKey('route-${day.id}'),
      direction: DismissDirection.endToStart,
      confirmDismiss: (_) async {
        onRemove!(scene);

        return false;
      },
      background: Container(
        alignment: Alignment.centerRight,
        padding: const EdgeInsets.only(right: AppSpacing.s16),
        child: Text(
          l.planEntryPreviewRemove,
          style: const TextStyle(
            fontFamily: AppFonts.inter,
            fontSize: 14,
            fontWeight: FontWeight.w600,
            color: AppColors.destructiveText,
          ),
        ),
      ),
      child: row,
    );
  }

  /// «слова и фразы дней 1–2» — the scene days this review day follows, counted off the route.
  String _repeatSub(AppLocalizations l) {
    var a = 0, b = 0;
    for (final d in plan.days) {
      if (d.number >= day.number) break;
      if (d.type == PlanDayType.scene) {
        if (a == 0) a = d.number;
        b = d.number;
      } else if (d.type == PlanDayType.review) {
        a = 0;
        b = 0;
      }
    }
    if (a == 0) return '';

    return a == b ? l.planRouteDayRepeatSubOne(a) : l.planRouteDayRepeatSub(a, b);
  }

  /// The right slot: a check on a closed day, «сегодня» / «завтра» from the server, nothing else.
  List<Widget> _slot(AppLocalizations l) {
    if (day.isClosed) {
      return const [
        SizedBox(width: 8),
        Icon(LucideIcons.check, size: 15, color: AppColors.secondary),
      ];
    }
    final label = day.slot.labelNative;
    if (label == null || label.isEmpty) return const [];
    if (day.slot.code != PlanSlotCode.today && day.slot.code != PlanSlotCode.tomorrow) return const [];

    return [
      const SizedBox(width: 8),
      Text(
        label,
        style: const TextStyle(
          fontFamily: AppFonts.inter,
          fontSize: 14,
          fontWeight: FontWeight.w700,
          height: 1,
          color: AppColors.brassInk,
        ),
      ),
    ];
  }
}

/// The node and the line through its centre: ink up to «сегодня», the ring on it, .30 after.
class _NodeColumn extends StatelessWidget {
  const _NodeColumn({
    required this.number,
    required this.ink,
    required this.ring,
    required this.lineAbove,
    required this.lineBelow,
    required this.first,
    required this.ground,
    required this.check,
  });

  final int number;
  final bool ink, ring, lineAbove, lineBelow, first, check;
  final Color ground;

  @override
  Widget build(BuildContext context) {
    final faint = AppColors.ink.withValues(alpha: .30);

    // The FULL row height, or the Stack sizes itself to the 22 px node and the line through its
    // centre is 22 px long — hidden behind the node, which is how the route came out with no line.
    return SizedBox(
      width: 22,
      height: double.infinity,
      child: Stack(
        alignment: Alignment.center,
        children: [
          Positioned.fill(
            child: Column(
              children: [
                Expanded(child: Container(width: 1.5, color: first ? Colors.transparent : (lineAbove ? AppColors.ink : faint))),
                Expanded(child: Container(width: 1.5, color: lineBelow ? AppColors.ink : faint)),
              ],
            ),
          ),
          Container(
            width: 22,
            height: 22,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: ink ? AppColors.ink : ground,
              border: ink
                  ? null
                  : Border.all(color: ring ? AppColors.brassInk : faint, width: 1.5),
            ),
            child: Text(
              '$number',
              style: TextStyle(
                fontFamily: AppFonts.inter,
                fontSize: 11,
                fontWeight: FontWeight.w700,
                height: 1,
                color: ink ? AppColors.paper : (ring ? AppColors.brassInk : AppColors.tertiary),
                fontFeatures: const [FontFeature.tabularFigures()],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _Photo extends StatelessWidget {
  const _Photo({required this.url, required this.dim});

  final String? url;
  final bool dim;

  @override
  Widget build(BuildContext context) {
    final box = Container(
      width: 48,
      height: 48,
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        color: AppColors.photoPlaceholder,
        borderRadius: BorderRadius.circular(12),
      ),
      child: url == null ? null : Image(image: CachedNetworkImage(url!), fit: BoxFit.cover),
    );

    // «будущие .72» — the photo of a day not yet reached.
    return dim ? Opacity(opacity: .72, child: box) : box;
  }
}

/// «Приём · 15 сентября» / «вторник» — the destination; a ring target while the plan runs, ink
/// once it is finished (кадр 21-7). Without a date — the event alone (entry.preview.event).
class _TargetRow extends StatelessWidget {
  const _TargetRow({
    required this.plan,
    required this.filled,
    required this.lineAbove,
    required this.ground,
  });

  final Plan plan;
  final bool filled, lineAbove;
  final Color ground;

  @override
  Widget build(BuildContext context) {
    final locale = Localizations.localeOf(context).languageCode;
    final event = (plan.eventNative ?? '').trim();
    final date = PlanFormat.parseWireDate(plan.eventDate);
    if (event.isEmpty && date == null) return const SizedBox.shrink();
    final title = date == null ? event : l10nEventTitle(context, event, date, locale);
    final faint = AppColors.ink.withValues(alpha: .30);
    final targetLine = AppColors.ink.withValues(alpha: .45);

    return SizedBox(
      height: 64,
      child: Row(
        children: [
          SizedBox(
            width: 22,
            height: double.infinity,
            child: Stack(
              alignment: Alignment.center,
              children: [
                Positioned.fill(
                  child: Column(
                    children: [
                      Expanded(child: Container(width: 1.5, color: lineAbove ? AppColors.ink : faint)),
                      const Expanded(child: SizedBox()),
                    ],
                  ),
                ),
                Container(
                  width: 22,
                  height: 22,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: filled ? AppColors.ink : ground,
                    border: filled ? null : Border.all(color: targetLine, width: 1.5),
                  ),
                  child: Container(
                    width: 8,
                    height: 8,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: filled ? AppColors.paper : null,
                      border: filled ? null : Border.all(color: targetLine, width: 1.5),
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 12),
          const SizedBox(width: 48),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontFamily: AppFonts.inter,
                    fontSize: 17,
                    fontWeight: FontWeight.w700,
                    height: 1.2,
                    color: AppColors.ink,
                  ),
                ),
                if (date != null) ...[
                  const SizedBox(height: 3),
                  Text(
                    PlanFormat.weekday(date, locale),
                    style: const TextStyle(
                      fontFamily: AppFonts.inter,
                      fontSize: 14,
                      height: 1.3,
                      color: AppColors.secondary,
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }

  static String l10nEventTitle(BuildContext context, String event, DateTime date, String locale) {
    final l = AppLocalizations.of(context);
    final formatted = PlanFormat.date(date, locale);

    return event.isEmpty ? formatted : l.planRouteEventTitle(event, formatted);
  }
}
