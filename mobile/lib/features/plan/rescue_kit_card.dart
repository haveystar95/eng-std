import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import '../../data/plan/plan_models.dart';
import '../../data/providers.dart';
import 'day/day_voice.dart';
import 'plan_tab_parts.dart' show PlanSectionLabel;

/// THE RESCUE KIT (`Plan.rescue_kit`, наряд LANG-1b §2; work order CLIENT-START §6) — back where the kit stood (21-2b,
/// under the route of the plan tab): the plan's lines for when a talk goes wrong, in the TARGET language, each with its
/// translation into the learner's own and a circle that says it — the server's file in the learner's voice
/// (`audio_url`), else the phone in the target language. No reading: the sound says it. Folded — the first line; «все
/// N →» unfolds the rest in place.
class PlanRescueKitCard extends ConsumerStatefulWidget {
  const PlanRescueKitCard({super.key, required this.plan});

  final Plan plan;

  @override
  ConsumerState<PlanRescueKitCard> createState() => _PlanRescueKitCardState();
}

class _PlanRescueKitCardState extends ConsumerState<PlanRescueKitCard> {
  bool _open = false;
  DayVoice? _voice;
  String? _preparedFor;

  DayVoice get _voiceNow => _voice ??= DayVoice(lines: ref.read(lineAudioCacheProvider), targetLang: widget.plan.targetLang);

  @override
  void dispose() {
    final voice = _voice;
    if (voice != null) unawaited(voice.stop());
    super.dispose();
  }

  /// The kit's files go to disk the first time the card is drawn for this plan, so a tap plays at once.
  void _prepare() {
    if (_preparedFor == widget.plan.id) return;
    _preparedFor = widget.plan.id;
    final files = [
      for (final p in widget.plan.rescueKit)
        if (p.audioUrl != null) (text: p.textTarget, url: p.audioUrl!),
    ];
    if (files.isNotEmpty) unawaited(_voiceNow.preload(files));
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final phrases = widget.plan.rescueKit.where((p) => p.textTarget.trim().isNotEmpty).toList();
    if (phrases.isEmpty) return const SizedBox.shrink();
    _prepare();
    final shown = _open ? phrases : phrases.take(1).toList();

    return PaperCard(
      key: const ValueKey('plan-rescue-kit'),
      radius: 22,
      padding: const EdgeInsets.fromLTRB(17, 15, 17, 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(child: PlanSectionLabel(l.planKitLabel)),
              if (phrases.length > 1)
                Semantics(
                  button: true,
                  child: GestureDetector(
                    key: const ValueKey('plan-rescue-kit-toggle'),
                    behavior: HitTestBehavior.opaque,
                    onTap: () {
                      AppHaptics.light();
                      setState(() => _open = !_open);
                    },
                    child: Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 2),
                      child: Text(_open ? l.planKitCollapse : l.planKitAll(phrases.length), style: AppTextStart.kitToggle),
                    ),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 4),
          Text(l.planKitSub(phrases.length), style: AppTextStart.kitToggle),
          for (final p in shown) ...[
            const SizedBox(height: 12),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(p.textTarget, style: AppTextStart.kitLine),
                      if (p.textNative.trim().isNotEmpty) ...[
                        const SizedBox(height: 3),
                        Text(context.nativeText(p.textNative), style: AppTextStart.kitNative),
                      ],
                    ],
                  ),
                ),
                const SizedBox(width: 12),
                PlayCircle(
                  key: ValueKey('plan-rescue-play-${phrases.indexOf(p)}'),
                  size: 32,
                  label: p.textTarget,
                  onTap: () => unawaited(_voiceNow.speak(p.textTarget)),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}
