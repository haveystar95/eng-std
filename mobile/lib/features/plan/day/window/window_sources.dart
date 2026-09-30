import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/native_text.dart';

import '../../../../data/local/cached_image_provider.dart';
import '../../../../data/plan/day_window.dart';
import '../../../../data/plan/plan_models.dart';
import 'window_plate.dart';
import 'window_texts.dart';

/// One card under the plate of a review or the rehearsal (кадры 37-1, 37-2): a scene the day is made of — its name, or
/// on a review its day of the route and its name — and its photo.
typedef WindowSource = ({String key, String title, PlanImage? image});

/// WHERE A REVIEW AND THE REHEARSAL COME FROM (кадры 37-1 «Из каких сцен», 37-2 «Из каких дней») — THE SERVER'S LIST,
/// `window.sources[]` (наряд BACK-TAILS-2; CLIENT-CONV-1c §9б), in its order. No field, or nothing in it — no list at
/// all: the client does not work out which scenes or days a day is made of (the reading of cards and the rule of
/// dealing that stood here in CLIENT-CONV-1b are gone with it).
///
/// The rehearsal names each scene (37-1); a review names each by its day — «День 1 · Запись к врачу» (37-2) — and a
/// scene without a day of the route by its name alone. No number stands on the cards: the list carries none, and the
/// client does not count lines or cards.
abstract final class WindowSources {
  static List<WindowSource> of(AppLocalizations l, {required Plan plan, required DayWindow window}) => switch (window.day.type) {
    PlanDayType.rehearsal => [
      for (final s in window.sources) (key: s.sceneId, title: s.titleNative, image: plan.sceneById(s.sceneId)?.image),
    ],
    PlanDayType.review => [
      for (final s in window.sources)
        (
          key: s.sceneId,
          title: s.dayNumber == null ? s.titleNative : l.planRouteDayTitle(s.dayNumber!, s.titleNative),
          image: plan.sceneById(s.sceneId)?.image,
        ),
    ],
    PlanDayType.scene || PlanDayType.unknown => const [],
  };
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
    this.onStageAgain,
  });

  final DayWindow window;
  final WindowSystemDay system;
  final List<WindowSource> sources;
  final double bottomCover;
  final VoidCallback? onBack;
  final Set<PlanStage> poppedStages;

  /// «Ещё раз» пройденного ряда (наряд FIX-3 §5) — вниз, в плиту.
  final void Function(PlanStage stage)? onStageAgain;

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
              onStageAgain: widget.onStageAgain,
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
/// 12 on its tone and the name 15/20 in ink. A long name wraps and the card grows with it — nothing in the plan's
/// windows is cut.
class _SourceCard extends StatelessWidget {
  const _SourceCard({required this.source});

  final WindowSource source;

  @override
  Widget build(BuildContext context) {
    final dpr = MediaQuery.maybeDevicePixelRatioOf(context) ?? 2;
    final photo = source.image;
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
          // The scene's name is the server's, in the learner's language (CLIENT-22-1 §2); «День 1 · …» — ours.
          Expanded(child: Text(context.nativeText(source.title), style: AppTextSession.text15)),
        ],
      ),
    );
  }
}
