import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter_svg/flutter_svg.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../data/local/cached_image_provider.dart';
import '../../../data/plan/conversation/conversation_models.dart';
import '../../../data/plan/plan_models.dart';
import '../plan_stage_text.dart' show PlanDot;
import '../session/parts/session_bits.dart';
import '../session/parts/session_chrome.dart';

/// THE WAY INTO THE TALK (кадр 37-5, SESSION-DES-4) — the sixth stage's own entry, in place of 30-1: the scene strip
/// with the role, the scene's photo band, the eyebrow, the title the server wrote («Поговори с врачом»), the minutes,
/// THREE lines of rules on the learner's language, «Скажи в разговоре» — the phrases the talk is for — «Без подсказок»
/// and one button.
///
/// Nothing here is counted or worded on the phone. The minutes are the server's row of the stage
/// (`stages[].minutes_left`), the title and the scenes' count its talk row's (`talk_title_native`, `scenes_count`,
/// CONV-2 п. 12), the phrases its `targets` (архитектор 22.09); each is simply absent when it did not come. «Без
/// подсказок» is sent once, with the start, and is fixed for that talk — «Начать разговор» is the only call here.
class TalkEntryView extends StatelessWidget {
  const TalkEntryView({
    super.key,
    required this.scene,
    required this.minutes,
    required this.rehearsal,
    required this.noHints,
    required this.onNoHints,
    required this.onStart,
    required this.onBack,
    this.title,
    this.scenesCount,
    this.targets = const [],
    this.starting = false,
    this.failure,
  });

  final PlanScene? scene;

  /// «около N минут»; null — the server did not send the stage's minutes and the line is not drawn.
  final int? minutes;

  /// «Поговори с врачом» — the talk row's `talk_title_native`; null — no title line (the client has none of its own).
  final String? title;

  /// «Разговор целиком · 3 сцены» on the rehearsal — the talk row's `scenes_count`; null — the eyebrow without it.
  final int? scenesCount;

  /// «Скажи в разговоре» — the talk row's `targets`, in the server's order; empty — no block.
  final List<TalkTarget> targets;

  /// The rehearsal talks the whole visit through, not one scene.
  final bool rehearsal;
  final bool noHints;
  final ValueChanged<bool> onNoHints;
  final VoidCallback? onStart;
  final VoidCallback onBack;

  /// The start is on its way — it waits on a model and a voice, so the button waits with it.
  final bool starting;

  /// One sentence about why the last start did not go through; null — nothing went wrong.
  final String? failure;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final scenes = scenesCount;
    final eyebrow = rehearsal
        ? (scenes == null || scenes < 1 ? l.planTalkEntryWhole : l.planDot(l.planTalkEntryWhole, l.planTalkEntryScenes(scenes)))
        : l.planDot(l.planPlateStageTalk, scene?.titleNative.trim() ?? '');
    final title = this.title;
    Widget gutter(Widget child) => Padding(padding: const EdgeInsets.symmetric(horizontal: kSessionGutter), child: child);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Expanded(
          child: LayoutBuilder(
            builder: (context, box) {
              final cut = targets.isEmpty
                  ? null
                  : _TargetsCut.measure(targets, box.maxWidth - 2 * kSessionGutter, MediaQuery.textScalerOf(context));
              return SingleChildScrollView(
                child: _EntryFit(
                  viewport: box.maxHeight,
                  window: cut,
                  children: [
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Padding(
                          padding: const EdgeInsets.fromLTRB(kSessionGutter, 4, kSessionGutter, 0),
                          child: Align(
                            alignment: Alignment.centerLeft,
                            child: SessionCloseButton(onTap: onBack, label: l.planWindowBack, back: true),
                          ),
                        ),
                        SessionSceneStrip(scene: scene),
                      ],
                    ),
                    gutter(_ScenePhoto(scene: scene)),
                    gutter(
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          SessionEyebrow(eyebrow),
                          if (title != null) ...[
                            const SizedBox(height: 8),
                            Text(title, key: const ValueKey('talk-entry-title'), style: AppTextSession.stageTitle),
                          ],
                          if (minutes != null) ...[
                            const SizedBox(height: 4),
                            Text(
                              l.planTalkEntryMinutes(minutes!),
                              key: const ValueKey('talk-entry-minutes'),
                              style: AppTextSession.meta,
                            ),
                          ],
                          const SizedBox(height: 24),
                          for (final (i, (icon, line)) in [
                            (_RuleIcon.talk, l.planTalkEntryRuleStart),
                            (_RuleIcon.rescue, l.planTalkEntryRuleRescue),
                            (_RuleIcon.counts, l.planTalkEntryRuleCounts),
                          ].indexed) ...[
                            if (i > 0) const SizedBox(height: 14),
                            _Rule(icon: icon, text: line),
                          ],
                          if (targets.isNotEmpty) ...[
                            const SizedBox(height: 24),
                            SessionEyebrow(l.planTalkEntrySay),
                          ],
                        ],
                      ),
                    ),
                    if (cut != null) gutter(_TargetsWindow(targets: targets, cut: cut.cuts)) else const SizedBox.shrink(),
                    gutter(_NoHintsRow(value: noHints, onChanged: onNoHints)),
                  ],
                ),
              );
            },
          ),
        ),
        SessionDock(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              if (failure case final text?) ...[
                Text(text, key: const ValueKey('talk-entry-failure'), textAlign: TextAlign.center, style: AppTextSession.meta),
                const SizedBox(height: 14),
              ],
              SessionDockButton(
                key: const ValueKey('talk-entry-start'),
                label: l.planTalkStart,
                busy: starting,
                onTap: onStart,
              ),
            ],
          ),
        ),
      ],
    );
  }
}

/// The three rules' marks (кадр 37-5), cut from the canvas: the talk (the dialogue's own mark), the question mark in a
/// circle for «Не понял», the check for what counts.
enum _RuleIcon {
  talk('assets/stages/dialogue.svg'),
  rescue('assets/icons/talk-rule-rescue.svg'),
  counts('assets/icons/talk-check.svg');

  const _RuleIcon(this.asset);

  final String asset;
}

/// A RULE OF 37-5 — its mark 20 in the secondary ink on the line's first row, 12, the sentence 15/20.
class _Rule extends StatelessWidget {
  const _Rule({required this.icon, required this.text});

  final _RuleIcon icon;
  final String text;

  @override
  Widget build(BuildContext context) => Row(
    key: ValueKey('talk-entry-rule-${icon.name}'),
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      SvgPicture.asset(
        icon.asset,
        width: 20,
        height: 20,
        colorFilter: const ColorFilter.mode(AppColors.secondary, BlendMode.srcIn),
      ),
      const SizedBox(width: 12),
      Expanded(child: Text(text, style: AppTextSession.body)),
    ],
  );
}

/// «СКАЖИ В РАЗГОВОРЕ» (кадр 37-5, SESSION-DES-4) — the talk's phrases between the rules and «Без подсказок»: each the
/// phrase in Literata 17/23 in ink and its translation 15/20 in grey under it, 10 between them. A WINDOW that scrolls
/// on its own, as high as the entry's layout gives it ([_EntryFit]): two phrases and the third one cut by the edge
/// («список прокручивается, нижняя фраза подрезана кромкой»), the edge fading into the ground over 28. A list the
/// window holds whole has no fade and nothing to scroll.
class _TargetsWindow extends StatelessWidget {
  const _TargetsWindow({required this.targets, required this.cut});

  final List<TalkTarget> targets;

  /// The list runs past the window's edge — the fade stands over the edge, and the scroll leaves room under the last
  /// phrase to bring it out of the fade.
  final bool cut;

  static const double fade = 28;

  @override
  Widget build(BuildContext context) => SizedBox.expand(
    key: const ValueKey('talk-entry-targets'),
    child: Stack(
      children: [
        Positioned.fill(
          child: SingleChildScrollView(
            padding: EdgeInsets.only(bottom: cut ? fade : 0),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                for (final (i, t) in targets.indexed) ...[
                  if (i > 0) const SizedBox(height: _TargetsCut.gap),
                  Column(
                    key: ValueKey('talk-entry-target-${t.sceneId}-${t.ref}'),
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(t.textTarget, style: AppTextSession.phrase17),
                      const SizedBox(height: _TargetsCut.lineGap),
                      Text(t.textNative, style: AppTextSession.body),
                    ],
                  ),
                ],
              ],
            ),
          ),
        ),
        if (cut)
          const Positioned(
            left: 0,
            right: 0,
            bottom: 0,
            height: fade,
            child: IgnorePointer(
              child: DecoratedBox(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topCenter,
                    end: Alignment.bottomCenter,
                    colors: [AppColors.groundClear, AppColors.ground],
                    stops: [0, 0.9],
                  ),
                ),
              ),
            ),
          ),
      ],
    ),
  );
}

/// WHERE THE PHRASES' WINDOW MAY END — measured on the phrases themselves, so the edge always falls INSIDE a phrase and
/// never in the gap between two (the frame's own 116 left the third phrase 6 of its 45: under the fade, «две фразы и
/// пустота», приёмка снимков 22.09). The edge cuts a phrase [into] its translation: its own line reads whole, the fade
/// starts under the top of its letters and eats the translation.
class _TargetsCut {
  const _TargetsCut({required this.preferred, required this.minimum, required this.cuts});

  /// The phrases stand [gap] apart, the translation [lineGap] under its phrase (кадр 37-5).
  static const double gap = 10;
  static const double lineGap = 2;

  /// How far into the cut phrase's translation the edge falls.
  static const double into = 10;

  /// Two phrases and the third one cut; a list of one or two — whole.
  final double preferred;

  /// One phrase and the second one cut — the least the window takes while the screen still fits.
  final double minimum;

  /// The list runs past the window.
  final bool cuts;

  static _TargetsCut measure(List<TalkTarget> targets, double width, TextScaler scaler) {
    final line = <double>[];
    final whole = <double>[];
    for (final t in targets.take(3)) {
      final target = _laid(t.textTarget, AppTextSession.phrase17, width, scaler);
      final native = _laid(t.textNative, AppTextSession.body, width, scaler);
      final metrics = target.computeLineMetrics();
      line.add(metrics.isEmpty ? target.height : metrics.first.height);
      whole.add(target.height + lineGap + native.height);
      target.dispose();
      native.dispose();
    }
    final into = lineGap + scaler.scale(_TargetsCut.into);
    if (targets.length <= 2) {
      final all = whole.fold(0.0, (sum, h) => sum + h) + gap * (whole.length - 1);
      return _TargetsCut(preferred: all, minimum: all, cuts: false);
    }
    return _TargetsCut(
      preferred: whole[0] + gap + whole[1] + gap + line[2] + into,
      minimum: whole[0] + gap + line[1] + into,
      cuts: true,
    );
  }

  static TextPainter _laid(String text, TextStyle style, double width, TextScaler scaler) => TextPainter(
    text: TextSpan(text: text, style: style),
    textDirection: TextDirection.ltr,
    textScaler: scaler,
  )..layout(maxWidth: width);
}

/// THE ENTRY'S VERTICAL BUDGET (кадр 37-5; приёмка снимков 22.09: «на 844 всё помещается, „Без подсказок" целиком над
/// кнопкой без прокрутки»). The children in order: the header (the back row and the scene strip), the photo band, the
/// body (eyebrow → rules → «Скажи в разговоре»), the phrases' window, the switch — at the frame's own distances: 20
/// under the strip, 20 under the band, 14 under the eyebrow of the phrases, 24 above the switch, 8 of air over the dock.
///
/// The frame's column only fits the frame's own words: a title of two lines («Поговори с регистратором» on a 390 screen)
/// or a third phrase that shows its cut take the room it does not have. What the screen lacks, THE PHOTO BAND GIVES —
/// the element the frame already cut from 170 to 64 «чтобы блок встал без наложения»: it stands whole, [photo] high, or
/// not at all — never a narrowed strip (приёмка CLIENT-CONV-1c 22.09, третий заход). The window keeps its two phrases
/// and the cut third while it can, else cuts the second; a screen that cannot hold even that scrolls.
class _EntryFit extends MultiChildRenderObjectWidget {
  const _EntryFit({required this.viewport, required this.window, required super.children});

  /// The height the entry has above the dock.
  final double viewport;

  /// Where the phrases' window may end; null — no phrases, no window.
  final _TargetsCut? window;

  @override
  _RenderEntryFit createRenderObject(BuildContext context) => _RenderEntryFit(viewport: viewport, window: window);

  @override
  void updateRenderObject(BuildContext context, _RenderEntryFit renderObject) => renderObject
    ..viewport = viewport
    ..window = window;
}

class _EntryFitData extends ContainerBoxParentData<RenderBox> {}

class _RenderEntryFit extends RenderBox
    with ContainerRenderObjectMixin<RenderBox, _EntryFitData>, RenderBoxContainerDefaultsMixin<RenderBox, _EntryFitData> {
  _RenderEntryFit({required this._viewport, required this._window});

  static const double stripGap = 20;
  static const double photoGap = 20;
  static const double sayGap = 14;
  static const double toggleGap = 24;
  static const double air = 8;
  static const double photo = 64;

  double _viewport;
  set viewport(double value) {
    if (value == _viewport) return;
    _viewport = value;
    markNeedsLayout();
  }

  _TargetsCut? _window;
  set window(_TargetsCut? value) {
    if (value?.preferred == _window?.preferred && value?.minimum == _window?.minimum && value?.cuts == _window?.cuts) return;
    _window = value;
    markNeedsLayout();
  }

  @override
  void setupParentData(RenderBox child) {
    if (child.parentData is! _EntryFitData) child.parentData = _EntryFitData();
  }

  @override
  void performLayout() {
    final width = constraints.maxWidth;
    final children = getChildrenAsList();
    assert(children.length == 5, 'the entry lays out five parts');
    final [header, photo, body, window, toggle] = children;
    final free = BoxConstraints.tightFor(width: width);
    header.layout(free, parentUsesSize: true);
    body.layout(free, parentUsesSize: true);
    toggle.layout(free, parentUsesSize: true);

    final cut = _window;
    final fixed =
        header.size.height + stripGap + body.size.height + (cut == null ? 0 : sayGap) + toggleGap + toggle.size.height + air;
    final room = _viewport - fixed;
    var windowHeight = 0.0;
    var photoHeight = 0.0;
    if (cut == null) {
      photoHeight = _band(room);
    } else if (room >= cut.preferred) {
      windowHeight = cut.preferred;
      photoHeight = _band(room - cut.preferred);
    } else {
      // Between the two cuts the edge would fall in a gap: the window takes the smaller cut and the air stays below.
      windowHeight = cut.minimum;
    }

    var y = 0.0;
    void place(RenderBox child, double at) => (child.parentData! as _EntryFitData).offset = Offset(0, at);
    place(header, y);
    y += header.size.height + stripGap;
    photo.layout(BoxConstraints.tightFor(width: width, height: photoHeight));
    place(photo, y);
    if (photoHeight > 0) y += photoHeight + photoGap;
    place(body, y);
    y += body.size.height;
    window.layout(BoxConstraints.tightFor(width: width, height: windowHeight));
    if (cut != null) y += sayGap;
    place(window, y);
    y += windowHeight + toggleGap;
    place(toggle, y);
    y += toggle.size.height + air;
    size = constraints.constrain(Size(width, y));
  }

  /// The photo band on what is left: the frame's 64 whole when it fits with its own 20 under it, else none.
  static double _band(double room) => room - photoGap >= photo ? photo : 0;

  @override
  void paint(PaintingContext context, Offset offset) => defaultPaint(context, offset);

  @override
  bool hitTestChildren(BoxHitTestResult result, {required Offset position}) =>
      defaultHitTestChildren(result, position: position);
}

/// THE SCENE'S PHOTO BAND (кадр 37-5, SESSION-DES-4) — between the strip and the eyebrow, corners 12, 64 high when the
/// entry's budget holds it and absent when not ([_EntryFit]; it was 170 before the phrases came onto the screen), the
/// photo covering the band on the scene's tone while it comes in. A scene without a photo keeps the band's shape as a
/// paper plate.
class _ScenePhoto extends StatelessWidget {
  const _ScenePhoto({required this.scene});

  final PlanScene? scene;

  @override
  Widget build(BuildContext context) {
    final photo = scene?.image;
    final dpr = MediaQuery.maybeDevicePixelRatioOf(context) ?? 2;
    return ClipRRect(
      key: const ValueKey('talk-entry-photo'),
      borderRadius: BorderRadius.circular(12),
      child: Container(
        decoration: BoxDecoration(
          color: photo == null ? AppColors.paper : AppColors.wireTone(photo.tone),
          image: photo == null ? null : DecorationImage(image: CachedNetworkImage(photo.urlFor(342, dpr)), fit: BoxFit.cover),
        ),
      ),
    );
  }
}

/// «БЕЗ ПОДСКАЗОК» on the talk's entry — the same switch as 30-1, without the second line: on this
/// card it is about the talk alone, and the sub of 30-1 names the trainers. The frame's plate: 56 high, 16 at the sides.
class _NoHintsRow extends StatelessWidget {
  const _NoHintsRow({required this.value, required this.onChanged});

  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Semantics(
      toggled: value,
      label: l.planSessionNoHints,
      child: GestureDetector(
        behavior: HitTestBehavior.opaque,
        onTap: () {
          AppHaptics.light();
          onChanged(!value);
        },
        child: SessionSheet(
          padding: const EdgeInsets.symmetric(horizontal: 16),
          child: Row(
            children: [
              Expanded(
                child: ConstrainedBox(
                  constraints: const BoxConstraints(minHeight: 56),
                  child: Align(alignment: Alignment.centerLeft, child: Text(l.planSessionNoHints, style: AppTextSession.text15)),
                ),
              ),
              const SizedBox(width: 12),
              AnimatedContainer(
                key: const ValueKey('talk-entry-no-hints'),
                duration: AppMotion.sessionChipSelect,
                width: 44,
                height: 26,
                padding: const EdgeInsets.all(3),
                alignment: value ? Alignment.centerRight : Alignment.centerLeft,
                decoration: BoxDecoration(
                  color: value ? AppColors.verdictKnown : AppColors.sessionToggleTrack,
                  borderRadius: BorderRadius.circular(13),
                ),
                child: Container(
                  width: 20,
                  height: 20,
                  decoration: const BoxDecoration(
                    shape: BoxShape.circle,
                    color: AppColors.paper,
                    boxShadow: [BoxShadow(color: AppColors.sessionToggleKnobShadow, blurRadius: 3, offset: Offset(0, 1))],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
