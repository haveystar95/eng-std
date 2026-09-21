import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../data/local/cached_image_provider.dart';
import '../../../data/plan/conversation/conversation_models.dart';
import '../../../data/plan/plan_models.dart';
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
        ? (scenes == null || scenes < 1 ? l.planTalkEntryWhole : l.planWindowJoin(l.planTalkEntryWhole, l.planTalkEntryScenes(scenes)))
        : l.planWindowJoin(l.planPlateStageTalk, scene?.titleNative.trim() ?? '');
    final title = this.title;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Expanded(
          child: SingleChildScrollView(
            padding: const EdgeInsets.only(bottom: 16),
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
                Padding(
                  padding: const EdgeInsets.fromLTRB(kSessionGutter, 20, kSessionGutter, 0),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      _ScenePhoto(scene: scene),
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
                        const SizedBox(height: 14),
                        _TargetsWindow(targets: targets),
                      ],
                      const SizedBox(height: 24),
                      _NoHintsRow(value: noHints, onChanged: onNoHints),
                    ],
                  ),
                ),
              ],
            ),
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
/// phrase in Literata 17/23 in ink and its translation 15/20 in grey under it, 10 between them. A WINDOW 116 high that
/// scrolls on its own, its lower edge fading into the ground over 28 — the bottom phrase is cut by the edge, so the
/// screen keeps the switch and the button where the frame puts them.
class _TargetsWindow extends StatelessWidget {
  const _TargetsWindow({required this.targets});

  final List<TalkTarget> targets;

  static const double height = 116;
  static const double fade = 28;

  @override
  Widget build(BuildContext context) => SizedBox(
    key: const ValueKey('talk-entry-targets'),
    height: height,
    child: Stack(
      children: [
        Positioned.fill(
          child: SingleChildScrollView(
            padding: const EdgeInsets.only(bottom: fade),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                for (final (i, t) in targets.indexed) ...[
                  if (i > 0) const SizedBox(height: 10),
                  Column(
                    key: ValueKey('talk-entry-target-${t.sceneId}-${t.ref}'),
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(t.textTarget, style: AppTextSession.phrase17),
                      const SizedBox(height: 2),
                      Text(t.textNative, style: AppTextSession.body),
                    ],
                  ),
                ],
              ],
            ),
          ),
        ),
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

/// THE SCENE'S PHOTO BAND (кадр 37-5, SESSION-DES-4) — between the strip and the eyebrow, 64 high with corners 12 (it was
/// 170 before the phrases came onto the screen), the photo covering the band on the scene's tone while it comes in. A
/// scene without a photo keeps the band's shape as a paper plate.
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
        height: 64,
        decoration: BoxDecoration(
          color: photo == null ? AppColors.paper : AppColors.wireTone(photo.tone),
          image: photo == null ? null : DecorationImage(image: CachedNetworkImage(photo.urlFor(342, dpr)), fit: BoxFit.cover),
        ),
      ),
    );
  }
}

/// «БЕЗ ПОДСКАЗОК» on the talk's entry — the same switch as 30-1, without the second line: on this
/// card it is about the talk alone, and the sub of 30-1 names the trainers.
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
          padding: const EdgeInsets.all(16),
          child: Row(
            children: [
              Expanded(child: Text(l.planSessionNoHints, style: AppTextSession.text15)),
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
