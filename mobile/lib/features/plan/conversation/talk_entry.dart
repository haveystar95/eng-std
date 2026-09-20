import 'package:flutter/material.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../data/plan/plan_models.dart';
import '../session/parts/session_bits.dart';
import '../session/parts/session_chrome.dart';

/// THE WAY INTO THE TALK (кадр 37-5) — the sixth stage's own entry, in place of 30-1: the scene strip
/// with the role, the eyebrow, «Поговори с собеседником», the minutes, THREE lines of rules on the
/// learner's language, «Без подсказок» and one button.
///
/// Nothing here is counted on the phone. The minutes are the server's row of the stage
/// (`stages[].minutes_left`) and are simply absent when it did not send them; the role and the scene
/// come from the day. «Без подсказок» is sent once, with the start, and is fixed for that talk.
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
    this.starting = false,
    this.failure,
  });

  final PlanScene? scene;

  /// «около N минут»; null — the server did not send the stage's minutes and the line is not drawn.
  final int? minutes;

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
    final eyebrow = rehearsal
        ? l.planTalkEntryWhole
        : l.planWindowJoin(l.planPlateStageTalk, scene?.titleNative.trim() ?? '');
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
                  padding: const EdgeInsets.fromLTRB(kSessionGutter, 24, kSessionGutter, 0),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      SessionEyebrow(eyebrow),
                      const SizedBox(height: 10),
                      Text(l.planTalkEntryTitle, key: const ValueKey('talk-entry-title'), style: AppTextSession.stageTitle),
                      if (minutes != null) ...[
                        const SizedBox(height: 14),
                        Text(
                          l.planTalkEntryMinutes(l.planMinutesCount(minutes!)),
                          key: const ValueKey('talk-entry-minutes'),
                          style: AppTextSession.meta,
                        ),
                      ],
                      const SizedBox(height: 32),
                      for (final line in [l.planTalkEntryRuleStart, l.planTalkEntryRuleRescue, l.planTalkEntryRuleCounts]) ...[
                        if (line != l.planTalkEntryRuleStart) const SizedBox(height: 12),
                        Text(line, style: AppTextSession.body),
                      ],
                      const SizedBox(height: 32),
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
