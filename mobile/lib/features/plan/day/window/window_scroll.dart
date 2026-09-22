import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../../data/plan/day_window.dart';
import '../../../../data/plan/plan_models.dart';
import 'window_bits.dart';
import 'window_compact_header.dart';
import 'window_dialogue.dart';
import 'window_phrases.dart';
import 'window_pill.dart';
import 'window_plate.dart';
import 'window_texts.dart';
import 'window_words.dart';

/// ЛЕНТА ОКНА ДНЯ — одна (DAY-UI-3, «Тайминг · серия 23»).
///
/// Плита, пилюля и компактная шапка — прижатая шапка ленты, и всё, что в ней движется, — ФУНКЦИЯ
/// ПОЗИЦИИ ПРОКРУТКИ, а не анимация: плита едет вверх вместе с лентой, пилюля стоит на её шве и едет с
/// ней, пока не встанет под строкой 56, а сама строка проявляется на последних 160 px пути плиты
/// ([WindowHeaderMath.progress]). Отпущенная на полпути лента доводится ОДНИМ `animateTo` — 260 мс
/// ease-out-cubic к тому краю, куда её тянули, — и шапка просто следует за позицией. Ни контроллера
/// сжатия, ни порогов: в DAY-UI-2 сжатие и доводка были двумя анимациями одной вещи, и они спорили.
///
/// У каждой вкладки своя прокрутка, и она помнится при смене вкладки тапом или свайпом.
class WindowScroll extends StatefulWidget {
  const WindowScroll({
    super.key,
    required this.window,
    required this.onListen,
    required this.onOpenWord,
    required this.bottomCover,
    this.onBack,
    this.poppedStages = const {},
    this.onStageAgain,
    this.sceneImageOf,
  });

  final DayWindow window;
  final WindowListen onListen;
  final ValueChanged<WindowWord> onOpenWord;

  /// Сколько снизу закрывает кнопка — лента оставляет столько пустым под последней строкой.
  final double bottomCover;
  final VoidCallback? onBack;
  final Set<PlanStage> poppedStages;

  /// «Ещё раз» пройденного ряда (наряд FIX-3 §5) — вниз, в плиту.
  final void Function(PlanStage stage)? onStageAgain;

  /// Фото сцены плана по её id — для полосы группы «Вернулось из дня N» (наряд FIX-3 §4).
  final PlanImage? Function(String sceneId)? sceneImageOf;

  @override
  State<WindowScroll> createState() => _WindowScrollState();
}

/// ГЕОМЕТРИЯ ШАПКИ — чистые функции позиции, их проверяет канон («плита — функция прокрутки»).
abstract final class WindowHeaderMath {
  /// Насколько плита перешла в компактную шапку, 0…1: линейно по позиции на последних
  /// `windowPlateToHeaderSpan` px пути. [shrink] — сколько шапка уже уехала, [range] — весь её путь.
  static double progress(double shrink, double range) {
    if (range <= 0) return 1;
    final span = math.min(AppMotion.windowPlateToHeaderSpan, range);

    return ((shrink - (range - span)) / span).clamp(0.0, 1.0);
  }

  /// Куда доводится лента, отпущенная между плитой и шапкой: туда, куда тянули; без направления — к
  /// ближнему краю. Null — лента уже на краю, доводить нечего.
  static double? snapTarget(double offset, double range, ScrollDirection direction) {
    if (offset <= 0 || offset >= range) return null;

    return switch (direction) {
      ScrollDirection.reverse => range,
      ScrollDirection.forward => 0,
      ScrollDirection.idle => offset >= range / 2 ? range : 0,
    };
  }
}

class _WindowScrollState extends State<WindowScroll> with SingleTickerProviderStateMixin {
  final _outer = ScrollController();
  late final TabController _tabs = TabController(
    length: WindowTab.values.length,
    vsync: this,
    animationDuration: AppMotion.windowTabContent,
  );

  double? _plateHeight;
  ScrollDirection _direction = ScrollDirection.idle;
  bool _snapping = false;

  @override
  void dispose() {
    _outer.dispose();
    _tabs.dispose();
    super.dispose();
  }

  /// Прижатая шапка: статус-бар, строка 56 и под ней пилюля.
  double get _minExtent => MediaQuery.paddingOf(context).top + WindowCompactHeader.height + WindowPill.height;

  /// Развёрнутая шапка: плита и нижняя половина пилюли под её швом.
  double _maxExtent(double plate) => math.max(plate + WindowPlate.pillOverlap, _minExtent);

  double _range(double plate) => _maxExtent(plate) - _minExtent;

  bool get _reduce => MediaQuery.of(context).disableAnimations;

  bool _onNotification(ScrollNotification n) {
    // Свайп страниц вкладок — горизонтальная лента; доводку плиты решает только вертикаль.
    if (n.metrics.axis != Axis.vertical) return false;
    if (n is UserScrollNotification && n.direction != ScrollDirection.idle) _direction = n.direction;
    // Доводка — после кадра, в котором закончили движение и внешняя лента, и лента вкладки (у каждой
    // свой конец движения), и кадр заказывается явно: после медленно отпущенной ленты других кадров
    // нет, и доводка ждала бы случайного (живой прогон DAY-UI-2 — плита торчала из-под шапки).
    if (n is ScrollEndNotification && !_snapping) {
      WidgetsBinding.instance
        ..addPostFrameCallback((_) => _snap())
        ..scheduleFrame();
    }

    return false;
  }

  void _snap() {
    final plate = _plateHeight;
    if (!mounted || plate == null || !_outer.hasClients) return;
    final target = WindowHeaderMath.snapTarget(_outer.offset, _range(plate), _direction);
    if (target == null) return;
    if (_reduce) {
      _outer.jumpTo(target);

      return;
    }
    // Конец самой доводки — тоже конец движения; второй доводки он не заказывает.
    _snapping = true;
    unawaited(
      _outer
          .animateTo(target, duration: AppMotion.windowSnap, curve: AppMotion.windowEaseOutCubic)
          .whenComplete(() => _snapping = false),
    );
  }

  void _measured(double height) {
    if (!mounted || height == _plateHeight) return;
    setState(() => _plateHeight = height);
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final plateHeight = _plateHeight;
    final plate = WindowPlate(
      window: widget.window,
      onBack: widget.onBack,
      poppedStages: widget.poppedStages,
      onStageAgain: widget.onStageAgain,
    );

    return Stack(
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
                      max: _maxExtent(plateHeight),
                      min: _minExtent,
                      plate: plate,
                      compact: WindowCompactHeader(window: widget.window),
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
                      brow: WindowTexts.brow(l, tab, _summaryOf(tab), returnsTomorrow: _returnsTomorrowOf(tab)),
                      bottom: widget.bottomCover,
                      child: switch (tab) {
                        WindowTab.words => WindowWords(
                          words: widget.window.program.words,
                          onListen: widget.onListen,
                          onOpen: widget.onOpenWord,
                          imageOf: widget.sceneImageOf,
                        ),
                        WindowTab.phrases => WindowPhrases(
                          phrases: widget.window.program.phrases,
                          onListen: widget.onListen,
                          imageOf: widget.sceneImageOf,
                        ),
                        WindowTab.dialogue => WindowDialogue(
                          pairs: widget.window.program.dialogue,
                          onListen: widget.onListen,
                          imageOf: widget.sceneImageOf,
                        ),
                      },
                    ),
                ],
              ),
            ),
          ),
      ],
    );
  }

  /// «N вернутся завтра» в брови — по СОСТОЯНИЯМ единиц вкладки (`items[].state`), а не по `summary.returns`: тот с
  /// наряда FIX-3 §9 считает, сколько ВЕРНУЛОСЬ из прошлых дней.
  int _returnsTomorrowOf(WindowTab tab) {
    final p = widget.window.program;
    bool back(WindowUnitState? s) => s == WindowUnitState.returnsTomorrow;

    return switch (tab) {
      WindowTab.words => p.words.where((w) => back(w.state)).length,
      WindowTab.phrases => p.phrases.where((f) => back(f.state)).length,
      WindowTab.dialogue => p.dialogue.where((d) => back(d.learner?.state)).length,
    };
  }

  WindowSummary _summaryOf(WindowTab tab) => switch (tab) {
    WindowTab.words => widget.window.program.wordsSummary,
    WindowTab.phrases => widget.window.program.phrasesSummary,
    WindowTab.dialogue => widget.window.program.dialogueSummary,
  };
}

/// Прижатая шапка ленты. Всё в ней — функция [shrinkOffset]: плита сдвинута на него вверх, компактная
/// строка проявлена на [WindowHeaderMath.progress], пилюля стоит у нижнего края шапки (на шве плиты,
/// пока плита видна, и под строкой 56, когда шапка прижата), статус-бар светлый над тёмным и тёмный над
/// бумагой.
class _Header extends SliverPersistentHeaderDelegate {
  _Header({required this.max, required this.min, required this.plate, required this.compact, required this.tabs});

  final double max;
  final double min;
  final Widget plate;
  final Widget compact;
  final TabController tabs;

  @override
  double get maxExtent => max;

  @override
  double get minExtent => math.min(min, max);

  @override
  Widget build(BuildContext context, double shrinkOffset, bool overlapsContent) {
    final t = WindowHeaderMath.progress(shrinkOffset, maxExtent - minExtent);

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: t < .5 ? SystemUiOverlayStyle.light : SystemUiOverlayStyle.dark,
      child: Stack(
        children: [
          const Positioned.fill(child: ColoredBox(color: AppColors.ground)),
          Positioned(top: -shrinkOffset, left: 0, right: 0, child: plate),
          Positioned(
            top: 0,
            left: 0,
            right: 0,
            height: minExtent,
            child: IgnorePointer(
              child: Opacity(
                key: const ValueKey('window-compact'),
                opacity: t,
                child: ColoredBox(color: AppColors.ground, child: Align(alignment: Alignment.topCenter, child: compact)),
              ),
            ),
          ),
          Positioned(
            left: WindowPill.inset,
            right: WindowPill.inset,
            bottom: 0,
            height: WindowPill.height,
            child: WindowPill(controller: tabs),
          ),
        ],
      ),
    );
  }

  @override
  bool shouldRebuild(covariant _Header old) =>
      old.max != max || old.min != min || old.plate != plate || old.compact != compact || old.tabs != tabs;
}

/// Страница вкладки: своя прокрутка (её помнит `PageStorageKey`), 24 от пилюли до брови, бровь и
/// через 14 содержимое.
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
          padding: EdgeInsets.fromLTRB(24, 24, 24, bottom + 24),
          sliver: SliverToBoxAdapter(
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
