import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/rendering.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../../data/plan/day_window.dart';
import '../../../../data/plan/plan_models.dart';
import 'window_bits.dart';
import 'window_compact_header.dart';
import 'window_dialogue.dart';
import 'window_phrases.dart';
import 'window_plate.dart';
import 'window_tabs.dart';
import 'window_texts.dart';
import 'window_words.dart';

/// ЛЕНТА ОКНА ДНЯ — одна: плита, под ней вкладки и их содержимое (наряд DAY-UI-2, «Прокрутка»).
///
/// Плита и вкладки — одна прижатая шапка ленты. По прокрутке плита уезжает вверх, а когда от неё
/// остаётся полоса шапки, на её место за 240 мс ease-out встаёт компактная строка 56; вкладки
/// доезжают под неё и примагничиваются тенью за 160 мс. Отпущенная на полпути лента доводится до
/// одного из двух положений тем же движением 240 мс — в сторону, куда её тянули, — поэтому тяга
/// вниз с верха вкладки возвращает плиту целиком. У каждой вкладки своя прокрутка, и она
/// помнится при смене вкладки тапом или свайпом.
class WindowScroll extends StatefulWidget {
  const WindowScroll({
    super.key,
    required this.window,
    required this.onListen,
    required this.bottomCover,
    this.onBack,
    this.poppedStages = const {},
  });

  final DayWindow window;
  final WindowListen onListen;

  /// Сколько снизу закрывает кнопка — лента оставляет столько пустым под последней строкой.
  final double bottomCover;
  final VoidCallback? onBack;
  final Set<PlanStage> poppedStages;

  @override
  State<WindowScroll> createState() => _WindowScrollState();
}

class _WindowScrollState extends State<WindowScroll> with TickerProviderStateMixin {
  final _outer = ScrollController();
  late final TabController _tabs = TabController(
    length: WindowTab.values.length,
    vsync: this,
    animationDuration: AppMotion.windowTabSwitch,
  );
  late final AnimationController _collapse = AnimationController(vsync: this, duration: AppMotion.windowPlateCollapse);
  late final Animation<double> _collapseCurve = CurvedAnimation(parent: _collapse, curve: AppMotion.easeOut);

  double? _plateHeight;
  ScrollDirection _direction = ScrollDirection.idle;
  bool _snapping = false;

  /// Бумага между плитой и вкладками: отступ 24 минус заход плиты 6.
  static const _gap = 24.0 - WindowPlate.overlap;

  /// Компактная строка встаёт, когда лента прошла эту долю пути плиты, и уходит ниже второй.
  static const _collapseAt = .85;
  static const _expandAt = .6;

  @override
  void initState() {
    super.initState();
    _outer.addListener(_onScroll);
  }

  @override
  void dispose() {
    _outer.dispose();
    _tabs.dispose();
    _collapse.dispose();
    super.dispose();
  }

  double get _compactExtent => MediaQuery.paddingOf(context).top + WindowCompactHeader.height;

  double _range(double plate) => math.max(0, plate + _gap - _compactExtent);

  bool get _reduce => MediaQuery.of(context).disableAnimations;

  void _onScroll() {
    final plate = _plateHeight;
    if (plate == null || !_outer.hasClients) return;
    final range = _range(plate);
    final t = range == 0 ? 1.0 : (_outer.offset / range).clamp(0.0, 1.0);
    final collapsing = _collapse.status == AnimationStatus.forward || _collapse.status == AnimationStatus.completed;
    if (t >= _collapseAt && !collapsing) {
      _reduce ? _collapse.value = 1 : _collapse.forward();
    } else if (t <= _expandAt && collapsing) {
      _reduce ? _collapse.value = 0 : _collapse.reverse();
    }
  }

  bool _onNotification(ScrollNotification n) {
    // Свайп страниц вкладок — горизонтальная лента; доводку плиты решает только вертикаль.
    if (n.metrics.axis != Axis.vertical) return false;
    if (n is UserScrollNotification && n.direction != ScrollDirection.idle) _direction = n.direction;
    // Доводка — после кадра, в котором закончили движение и внешняя лента, и лента вкладки (у каждой
    // свой конец движения), и кадр заказывается явно: после медленно отпущенной ленты других кадров
    // нет, и доводка ждала бы случайного (живой прогон 14.09 — плита торчала из-под шапки).
    if (n is ScrollEndNotification && !_snapping) {
      WidgetsBinding.instance
        ..addPostFrameCallback((_) => _snap())
        ..scheduleFrame();
    }

    return false;
  }

  /// Лента не останавливается между плитой и шапкой: её доводит туда, куда тянули.
  void _snap() {
    final plate = _plateHeight;
    if (!mounted || plate == null || !_outer.hasClients) return;
    final range = _range(plate);
    final offset = _outer.offset;
    if (offset <= 0 || offset >= range) return;
    final collapse = switch (_direction) {
      ScrollDirection.reverse => true,
      ScrollDirection.forward => false,
      ScrollDirection.idle => offset >= range / 2,
    };
    final target = collapse ? range : 0.0;
    if (_reduce) {
      _outer.jumpTo(target);
    } else {
      // Конец самой доводки — тоже конец движения; второй доводки он не заказывает.
      _snapping = true;
      unawaited(
        _outer
            .animateTo(target, duration: AppMotion.windowPlateCollapse, curve: AppMotion.easeOut)
            .whenComplete(() => _snapping = false),
      );
    }
  }

  void _measured(double height) {
    if (!mounted || height == _plateHeight) return;
    setState(() => _plateHeight = height);
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final plateHeight = _plateHeight;
    final plate = WindowPlate(window: widget.window, onBack: widget.onBack, poppedStages: widget.poppedStages);

    return AnimatedBuilder(
      animation: _collapseCurve,
      builder: (context, child) => AnnotatedRegion<SystemUiOverlayStyle>(
        value: _collapseCurve.value < .5 ? SystemUiOverlayStyle.light : SystemUiOverlayStyle.dark,
        child: child!,
      ),
      child: Stack(
        children: [
          // Высота плиты зависит от названия, целей и этапов — её меряет невидимая копия.
          Positioned(
            left: 0,
            right: 0,
            top: 0,
            child: Offstage(child: _Measure(onHeight: _measured, child: plate)),
          ),
          if (plateHeight != null)
            NotificationListener<ScrollNotification>(
              onNotification: _onNotification,
              child: NestedScrollView(
                controller: _outer,
                headerSliverBuilder: (context, _) => [
                  SliverOverlapAbsorber(
                    handle: NestedScrollView.sliverOverlapAbsorberHandleFor(context),
                    sliver: SliverPersistentHeader(
                      pinned: true,
                      delegate: _Header(
                        max: plateHeight + _gap + WindowTabBar.height,
                        min: _compactExtent + WindowTabBar.height,
                        plate: plate,
                        compact: WindowCompactHeader(window: widget.window),
                        collapse: _collapseCurve,
                        tabs: _tabs,
                      ),
                    ),
                  ),
                ],
                body: TabBarView(
                  controller: _tabs,
                  children: [
                    for (final tab in WindowTab.values)
                      _Page(
                        tab: tab,
                        brow: WindowTexts.brow(l, tab, _summaryOf(tab)),
                        bottom: widget.bottomCover,
                        child: switch (tab) {
                          WindowTab.words => WindowWords(words: widget.window.program.words),
                          WindowTab.phrases => WindowPhrases(phrases: widget.window.program.phrases, onListen: widget.onListen),
                          WindowTab.dialogue => WindowDialogue(pairs: widget.window.program.dialogue, onListen: widget.onListen),
                        },
                      ),
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }

  WindowSummary _summaryOf(WindowTab tab) => switch (tab) {
    WindowTab.words => widget.window.program.wordsSummary,
    WindowTab.phrases => widget.window.program.phrasesSummary,
    WindowTab.dialogue => widget.window.program.dialogueSummary,
  };
}

/// Прижатая шапка ленты: плита, уезжающая вверх; компактная строка, встающая на её место; вкладки
/// у нижнего края.
class _Header extends SliverPersistentHeaderDelegate {
  _Header({
    required this.max,
    required this.min,
    required this.plate,
    required this.compact,
    required this.collapse,
    required this.tabs,
  });

  final double max;
  final double min;
  final Widget plate;
  final Widget compact;
  final Animation<double> collapse;
  final TabController tabs;

  @override
  double get maxExtent => max;

  @override
  double get minExtent => math.min(min, max);

  @override
  Widget build(BuildContext context, double shrinkOffset, bool overlapsContent) => Stack(
    children: [
      const Positioned.fill(child: ColoredBox(color: AppColors.ground)),
      Positioned(top: -shrinkOffset, left: 0, right: 0, child: plate),
      Positioned(
        top: 0,
        left: 0,
        right: 0,
        child: IgnorePointer(child: FadeTransition(opacity: collapse, child: compact)),
      ),
      Positioned(
        left: 0,
        right: 0,
        bottom: 0,
        child: WindowTabBar(controller: tabs, pinned: shrinkOffset >= maxExtent - minExtent - .5),
      ),
    ],
  );

  @override
  bool shouldRebuild(covariant _Header old) =>
      old.max != max || old.min != min || old.plate != plate || old.compact != compact || old.tabs != tabs;
}

/// Страница вкладки: своя прокрутка (её помнит `PageStorageKey`), бровь и содержимое, которое
/// появляется `om-cab-in`.
class _Page extends StatelessWidget {
  const _Page({required this.tab, required this.brow, required this.bottom, required this.child});

  final WindowTab tab;
  final String brow;
  final double bottom;
  final Widget child;

  @override
  Widget build(BuildContext context) => Builder(
    builder: (context) => CustomScrollView(
      key: PageStorageKey<WindowTab>(tab),
      slivers: [
        SliverOverlapInjector(handle: NestedScrollView.sliverOverlapAbsorberHandleFor(context)),
        SliverPadding(
          padding: EdgeInsets.fromLTRB(24, 14, 24, bottom + 24),
          sliver: SliverToBoxAdapter(
            child: CabIn(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(brow.toUpperCase(), style: AppTextWindow.tabBrow),
                  const SizedBox(height: 14),
                  child,
                ],
              ),
            ),
          ),
        ),
      ],
    ),
  );
}

/// Сообщает высоту ребёнка после раскладки — один раз на каждое изменение.
class _Measure extends SingleChildRenderObjectWidget {
  const _Measure({required this.onHeight, required super.child});

  final ValueChanged<double> onHeight;

  @override
  RenderObject createRenderObject(BuildContext context) => _RenderMeasure(onHeight);

  @override
  void updateRenderObject(BuildContext context, _RenderMeasure renderObject) => renderObject.onHeight = onHeight;
}

class _RenderMeasure extends RenderProxyBox {
  _RenderMeasure(this.onHeight);

  ValueChanged<double> onHeight;
  double? _last;

  @override
  void performLayout() {
    super.performLayout();
    final height = size.height;
    if (height == _last) return;
    _last = height;
    WidgetsBinding.instance.addPostFrameCallback((_) => onHeight(height));
  }
}
