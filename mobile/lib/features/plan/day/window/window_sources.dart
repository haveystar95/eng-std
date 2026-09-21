import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../../data/local/cached_image_provider.dart';
import '../../../../data/plan/day_window.dart';
import '../../../../data/plan/plan_models.dart';
import 'window_plate.dart';
import 'window_texts.dart';

/// One card under the plate of a review or the rehearsal (кадры 37-1, 37-2): a scene the day is made of, or the day
/// of the route it comes from. [lines] — how many of the learner's own lines the scene holds, when the server has
/// counted them (the rehearsal's recall sheet); null — no number is printed.
typedef WindowSource = ({String key, String title, int? lines, PlanImage? image});

/// WHERE A REVIEW AND THE REHEARSAL COME FROM (кадры 37-1 «Из каких сцен», 37-2 «Из каких дней»; наряд
/// CLIENT-CONV-1b).
///
/// The day window's block carries no such list, so it is read off what the day room does carry, in this order:
///
/// - a DEALT day — its own cards: the rehearsal's `recall_scenes` sheet names every scene with its lines, a review's
///   cards name the scenes they were dealt from (`payload.scene_id`), and those scenes' days of the route are the days
///   it brings back;
/// - a day NOT DEALT yet — the outline has no cards to read, so the list follows the rule the server deals by: the
///   rehearsal walks every ready scene of the plan in the plan's order, a review the two scene days before it
///   (`DayDealer`, `ConversationMaterial::scenesOf`). No count is printed there — nothing has been counted yet.
///
/// The one number on the cards is the rehearsal's «N реплик», the length of the server's own list; «N карточек» of
/// кадр 37-2 is not sent, and the client does not count cards (отчёт client-conv-1b §5).
abstract final class WindowSources {
  static List<WindowSource> of(AppLocalizations l, {required Plan plan, required PlanDayRoom room, required WindowDay day}) =>
      switch (day.type) {
        PlanDayType.rehearsal => _scenes(plan, room),
        PlanDayType.review => _days(l, plan, room, day.index),
        PlanDayType.scene || PlanDayType.unknown => const [],
      };

  static List<WindowSource> _scenes(Plan plan, PlanDayRoom room) {
    if (room.recallScenes.isNotEmpty) {
      return [
        for (final s in room.recallScenes)
          (key: s.sceneId, title: s.titleNative, lines: s.lines, image: plan.sceneById(s.sceneId)?.image),
      ];
    }
    final ready = [for (final s in plan.scenes) if (s.lessonStatus == LessonStatus.ready) s]
      ..sort((a, b) => a.order.compareTo(b.order));
    return [for (final s in ready) (key: s.id, title: s.titleNative, lines: null, image: s.image)];
  }

  static List<WindowSource> _days(AppLocalizations l, Plan plan, PlanDayRoom room, int number) {
    final sceneDays = [for (final d in plan.days) if (d.type == PlanDayType.scene && d.number < number) d]
      ..sort((a, b) => a.number.compareTo(b.number));
    final List<PlanDayRoute> days;
    if (room.cardSceneIds.isNotEmpty) {
      days = [for (final d in sceneDays) if (room.cardSceneIds.contains(d.sceneId)) d];
    } else {
      days = sceneDays.length <= 2 ? sceneDays : sceneDays.sublist(sceneDays.length - 2);
    }
    return [
      for (final d in days)
        (
          key: d.id,
          title: l.planRouteDayTitle(d.number, d.titleNative ?? plan.sceneOf(d)?.titleNative ?? ''),
          lines: null,
          image: plan.sceneOf(d)?.image,
        ),
    ];
  }
}

/// THE WINDOW OF A REVIEW OR THE REHEARSAL (кадры 37-1, 37-2) — «второй компонент окна: это то же окно дня 23-0a,
/// сменились ряды и список под плитой». The plate with its rows, then the list the day is made of; no tabs, so no pill
/// and no compact header — the plate simply scrolls away over a long list, and the status bar turns dark with it.
class WindowSourcesScroll extends StatefulWidget {
  const WindowSourcesScroll({
    super.key,
    required this.window,
    required this.system,
    required this.sources,
    required this.bottomCover,
    this.onBack,
    this.poppedStages = const {},
  });

  final DayWindow window;
  final WindowSystemDay system;
  final List<WindowSource> sources;
  final double bottomCover;
  final VoidCallback? onBack;
  final Set<PlanStage> poppedStages;

  @override
  State<WindowSourcesScroll> createState() => _WindowSourcesScrollState();
}

class _WindowSourcesScrollState extends State<WindowSourcesScroll> {
  final _scroll = ScrollController();
  final _plateKey = GlobalKey();
  bool _overPaper = false;

  @override
  void initState() {
    super.initState();
    _scroll.addListener(_onScroll);
  }

  @override
  void dispose() {
    _scroll.dispose();
    super.dispose();
  }

  /// The status bar is light over the dark plate and dark once the plate has gone up under it.
  void _onScroll() {
    final plate = _plateKey.currentContext?.size?.height;
    if (plate == null) return;
    final over = _scroll.offset > plate - MediaQuery.paddingOf(context).top;
    if (over != _overPaper) setState(() => _overPaper = over);
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final rehearsal = widget.window.day.type == PlanDayType.rehearsal;
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: _overPaper ? SystemUiOverlayStyle.dark : SystemUiOverlayStyle.light,
      child: CustomScrollView(
        controller: _scroll,
        slivers: [
          SliverToBoxAdapter(
            child: WindowPlate(
              key: _plateKey,
              window: widget.window,
              onBack: widget.onBack,
              poppedStages: widget.poppedStages,
              system: widget.system,
            ),
          ),
          if (widget.sources.isNotEmpty)
            SliverPadding(
              padding: EdgeInsets.fromLTRB(24, 24, 24, widget.bottomCover + 24),
              sliver: SliverList.list(
                children: [
                  Text(
                    (rehearsal ? l.planWindowFromScenes : l.planWindowFromDays).toUpperCase(),
                    key: const ValueKey('window-sources-brow'),
                    style: AppTextWindow.tabBrow,
                  ),
                  const SizedBox(height: 14),
                  for (final (i, s) in widget.sources.indexed) ...[
                    if (i > 0) const SizedBox(height: 12),
                    _SourceCard(source: s),
                  ],
                ],
              ),
            )
          else
            SliverToBoxAdapter(child: SizedBox(height: widget.bottomCover)),
        ],
      ),
    );
  }
}

/// A card of the list (37-1, 37-2): 72 high, paper, corners 16, the faint shadow; the scene's photo 48 with corners
/// 12 on its tone, the name 15/20 in ink, and on the right the server's count of lines when there is one. A long
/// name wraps and the card grows with it — nothing in the plan's windows is cut.
class _SourceCard extends StatelessWidget {
  const _SourceCard({required this.source});

  final WindowSource source;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final dpr = MediaQuery.maybeDevicePixelRatioOf(context) ?? 2;
    final photo = source.image;
    final lines = source.lines;
    return Container(
      key: ValueKey('window-source-${source.key}'),
      constraints: const BoxConstraints(minHeight: 72),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
      decoration: BoxDecoration(
        color: AppColors.paper,
        borderRadius: BorderRadius.circular(16),
        boxShadow: const [BoxShadow(color: AppColors.windowSourceShadow, blurRadius: 8, offset: Offset(0, 2))],
      ),
      child: Row(
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(12),
            child: Container(
              width: 48,
              height: 48,
              color: AppColors.wireTone(photo?.tone) ?? AppColors.photoPlaceholder,
              child: photo == null
                  ? null
                  : Image(
                      image: CachedNetworkImage(photo.urlFor(48, dpr)),
                      fit: BoxFit.cover,
                      errorBuilder: (_, _, _) => const SizedBox.expand(),
                    ),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(child: Text(source.title, style: AppTextSession.text15)),
          if (lines != null && lines > 0) ...[
            const SizedBox(width: 12),
            Text(l.planWindowSourceLines(lines), key: ValueKey('window-source-lines-${source.key}'), style: AppTextSession.meta),
          ],
        ],
      ),
    );
  }
}
