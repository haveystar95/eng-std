import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../data/local/cached_image_provider.dart';
import '../../../data/plan/conversation/conversation_models.dart';
import '../../../data/plan/plan_models.dart';
import '../plan_stage_text.dart' show PlanDot;
import '../session/parts/session_bits.dart';
import '../session/parts/session_chrome.dart';
import '../session/session_texts.dart';
import 'talk_constructions.dart';
import 'talk_ribbon.dart' show talkSceneLabel;

/// THE WAY INTO THE TALK (кадр 37-5 серии 38) — the sixth stage's own entry, in place of 30-1: the scene strip with
/// the role, the scene's photo band, the eyebrow, the title the server wrote («Поговори с врачом»), the minutes, THREE
/// lines of rules on the learner's language, «Скажи в разговоре» — THE CONSTRUCTIONS the talk is for, each with the
/// lesson's own example grey under it — and, pinned to the bottom, «Без подсказок» and one button.
///
/// THE SCREEN SCROLLS AS ONE (наряд FIX-3 §3, правка владельца 23.09): the list has no window and no scroll of its own
/// — it runs on under the dock, and the dock's gradient is where it fades. Seven constructions are ordinary, and a
/// phone that cannot hold them all shows what it can and gives the rest to the thumb.
///
/// Nothing here is counted or worded on the phone. The minutes are the server's row of the stage
/// (`stages[].minutes_left`), the title and the scenes' count its talk row's (`talk_title_native`, `scenes_count`,
/// CONV-2 п. 12), the constructions its `targets` (FIX-3 §6); each is simply absent when it did not come. «Без
/// подсказок» is sent once, with the start, and is fixed for that talk — «Начать разговор» is the only call here.
///
/// A TALK THAT WALKS SEVERAL SCENES (the rehearsal, a review — 37-5b; наряд CLIENT-FIX-4 §5) lists its constructions
/// by scene, in the scenes' order: «СЦЕНА 1 · ЗАПИСЬ К ВРАЧУ · РЕГИСТРАТОР» and its constructions, the grey line «Разговор
/// идёт сцена за сценой», the next scene; the first rule names the first scene's role («Регистратор начнёт первым»), and
/// the strip shows that scene ([scenes]).
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
    this.scenes = const [],
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

  /// The same constructions BY SCENE when the talk walks more than one ([talkEntryScenes]); empty — one block.
  final List<TalkEntryScene> scenes;

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
    final count = scenesCount;
    final eyebrow = rehearsal
        ? (count == null || count < 1 ? l.planTalkEntryWhole : l.planDot(l.planTalkEntryWhole, l.planTalkEntryScenes(count)))
        : l.planDotPlain(l.planPlateStageTalk, scene?.titleNative.trim() ?? '');
    final title = this.title;
    return ClipRect(
      child: CustomMultiChildLayout(
        delegate: _EntryLayout(),
        children: [
          LayoutId(
            id: _EntrySlot.field,
            child: SingleChildScrollView(
              child: Column(
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
                  const SizedBox(height: 20),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: kSessionGutter),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        SizedBox(height: _ScenePhoto.band, child: _ScenePhoto(scene: scene)),
                        const SizedBox(height: 20),
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
                          (_RuleIcon.talk, _firstRule(l)),
                          (_RuleIcon.rescue, l.planTalkEntryRuleRescue),
                          (_RuleIcon.counts, l.planTalkEntryRuleCounts),
                        ].indexed) ...[
                          if (i > 0) const SizedBox(height: 14),
                          _Rule(icon: icon, text: line),
                        ],
                        if (scenes.length > 1)
                          ..._byScene(l)
                        else if (targets.isNotEmpty) ...[
                          const SizedBox(height: 24),
                          SessionEyebrow(l.planTalkEntrySay),
                          const SizedBox(height: 14),
                          for (final (i, t) in targets.indexed) ...[
                            if (i > 0) const SizedBox(height: 10),
                            TalkConstructionRow(target: t),
                          ],
                        ],
                        // The list runs on under the dock; this is the room its last row needs to come out from under it.
                        const SizedBox(height: 16),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
          LayoutId(
            id: _EntrySlot.dock,
            child: SessionDock(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  if (failure case final text?) ...[
                    Text(text, key: const ValueKey('talk-entry-failure'), textAlign: TextAlign.center, style: AppTextSession.meta),
                    const SizedBox(height: 14),
                  ],
                  _NoHintsRow(value: noHints, onChanged: onNoHints),
                  const SizedBox(height: 14),
                  SessionDockButton(
                    key: const ValueKey('talk-entry-start'),
                    label: l.planTalkStart,
                    busy: starting,
                    onTap: onStart,
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

extension on TalkEntryView {
  /// «Собеседник начнёт первым…» — or, on a talk of several scenes, the first scene's role as the plan names it:
  /// «Регистратор начнёт первым…» (37-5b). The day keeps its line (наряд CLIENT-FIX-4 §5: «в дне — как было»).
  String _firstRule(AppLocalizations l) {
    final role = scenes.length > 1 ? scenes.first.role.trim() : '';
    return role.isEmpty ? l.planTalkEntryRuleStart : l.planTalkEntryRuleStartRole(role);
  }

  /// 37-5b: a scene's caps and its constructions, 14 apart, the grey «Разговор идёт сцена за сценой» between two
  /// scenes, 24 around it.
  List<Widget> _byScene(AppLocalizations l) => [
    for (final (i, s) in scenes.indexed) ...[
      if (i > 0) ...[
        const SizedBox(height: 24),
        Text(l.planTalkEntrySceneByScene, key: const ValueKey('talk-entry-scene-by-scene'), style: AppTextSession.meta),
      ],
      const SizedBox(height: 24),
      SessionEyebrow(
        talkSceneLabel(l, number: i + 1, title: s.title.trim(), role: SessionTexts.roleInline(s.role.trim())),
        key: ValueKey('talk-entry-scene-${s.sceneId}'),
      ),
      const SizedBox(height: 14),
      for (final (j, t) in s.targets.indexed) ...[
        if (j > 0) const SizedBox(height: 10),
        TalkConstructionRow(target: t),
      ],
    ],
  ];
}

/// A SCENE OF A TALK THAT WALKS SEVERAL (кадр 37-5b): its id, its name, its role in the nominative as the plan names it,
/// and its constructions in the server's order.
typedef TalkEntryScene = ({String sceneId, String title, String role, List<TalkTarget> targets});

/// THE TALK ROW'S CONSTRUCTIONS BY SCENE (37-5b) — in the order the day names its scenes (`window.sources[]`,
/// [order]); a scene that list does not name goes after them, in the order its constructions came. The name is the
/// source's, else the plan's scene's; the role is the plan's scene's. Fewer than two scenes — no groups: the day's own
/// talk lists its constructions as one block (37-5).
List<TalkEntryScene> talkEntryScenes(
  List<TalkTarget> targets, {
  required List<({String sceneId, String title})> order,
  required PlanScene? Function(String sceneId) sceneById,
}) {
  final byScene = <String, List<TalkTarget>>{};
  for (final t in targets) {
    byScene.putIfAbsent(t.sceneId, () => []).add(t);
  }
  if (byScene.length < 2) return const [];
  final named = {for (final s in order) s.sceneId: s.title};
  final ids = [
    for (final s in order)
      if (byScene.containsKey(s.sceneId)) s.sceneId,
    for (final id in byScene.keys)
      if (!named.containsKey(id)) id,
  ];
  return [
    for (final id in ids)
      (
        sceneId: id,
        title: named[id] ?? sceneById(id)?.titleNative ?? '',
        role: sceneById(id)?.partnerRoleNative ?? '',
        targets: byScene[id]!,
      ),
  ];
}

enum _EntrySlot { field, dock }

/// THE DOCK IS PINNED, THE LIST GOES UNDER IT (кадр 37-5 серии 38) — the same layout the cards use: the dock takes what
/// it needs at the bottom, the field takes the rest plus the dock's own transparent top inset, and what does not fit
/// scrolls under the gradient.
class _EntryLayout extends MultiChildLayoutDelegate {
  @override
  void performLayout(Size size) {
    final dock = layoutChild(_EntrySlot.dock, BoxConstraints(minWidth: size.width, maxWidth: size.width, maxHeight: size.height));
    positionChild(_EntrySlot.dock, Offset(0, size.height - dock.height));
    final field = (size.height - dock.height + SessionDock.topInset).clamp(0.0, size.height);
    layoutChild(_EntrySlot.field, BoxConstraints.tight(Size(size.width, field)));
    positionChild(_EntrySlot.field, Offset.zero);
  }

  @override
  bool shouldRelayout(_EntryLayout oldDelegate) => false;
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

/// THE SCENE'S PHOTO BAND (кадр 37-5) — between the strip and the eyebrow, corners 12, 64 high (it was 170 before the
/// constructions came onto the screen), the photo covering the band on the scene's tone while it comes in. A scene
/// without a photo keeps the band's shape as a paper plate.
class _ScenePhoto extends StatelessWidget {
  const _ScenePhoto({required this.scene});

  /// The band's height on the frame.
  static const double band = 64;

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
