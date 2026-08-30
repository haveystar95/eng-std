import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';
import 'package:eng_std/l10n/app_localizations.dart';

import '../../data/api_client.dart';
import '../../data/plan_models.dart';
import '../../data/providers.dart';
import 'plan_ui.dart';

/// «Как прошло? Отметь, что сказал» — the last thing a plan asks (кадры 1c · 14 → 11).
///
/// Ticked BY HAND, and that is not a shortcut for something the app could work out: the appointment
/// happened in a room the app was not in. Nothing on the device knows whether the learner said «it
/// hurts in my lower back» to an actual doctor, and the honest way to find out is to ask.
///
/// Sending it CLOSES the plan. That pairing is deliberate — a plan whose event is over and which is
/// still running goes on holding its words out of the ordinary day, for an appointment that has
/// already happened.
class PlanFeedbackScreen extends ConsumerStatefulWidget {
  const PlanFeedbackScreen({super.key, required this.plan});

  final LearningPlan plan;

  @override
  ConsumerState<PlanFeedbackScreen> createState() => _PlanFeedbackScreenState();
}

class _PlanFeedbackScreenState extends ConsumerState<PlanFeedbackScreen> {
  late final Set<int> _hit = {...?widget.plan.eventFeedback};
  bool _busy = false;
  String? _error;

  Future<void> _send() async {
    if (_busy) return;
    AppHaptics.light();
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await ref.read(apiClientProvider).submitPlanFeedback(widget.plan.id, _hit.toList()..sort());
      if (!mounted) return;
      // The plan is finished: the tab's two reads both change meaning, and the ordinary day just
      // got its words back.
      ref.invalidate(activePlanProvider);
      ref.invalidate(planArchiveProvider);
      ref.invalidate(planProvider(widget.plan.id));
      ref.read(syncServiceProvider).sync();
      AppHaptics.success();
      Navigator.of(context).pop(true);
    } catch (e) {
      if (!mounted) return;
      final l = AppLocalizations.of(context);
      setState(() {
        _busy = false;
        _error = isOffline(e) ? l.planErrorOffline : l.planErrorBuildFailed;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final checkpoints = widget.plan.canAlready;

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark,
      child: Scaffold(
        backgroundColor: AppColors.paper,
        body: SafeArea(
          bottom: false,
          child: ListView(
            padding: const EdgeInsets.fromLTRB(
              AppSpacing.screenH,
              AppSpacing.s8,
              AppSpacing.screenH,
              AppSpacing.s26,
            ),
            children: [
              SizedBox(
                height: AppSpacing.minTap,
                child: Align(
                  alignment: Alignment.centerLeft,
                  child: InkResponse(
                    onTap: () => Navigator.of(context).maybePop(),
                    radius: 22,
                    child: const SizedBox(
                      width: AppSpacing.minTap,
                      height: AppSpacing.minTap,
                      child: Icon(LucideIcons.x, size: 20, color: AppColors.secondary),
                    ),
                  ),
                ),
              ),
              PlanLabel(widget.plan.title),
              const SizedBox(height: AppSpacing.s12),
              Text(
                l.planFeedbackTitle,
                style: AppText.collectionNameScreen.copyWith(fontSize: 29, height: 1.18),
              ),
              const SizedBox(height: 10),
              Text(
                l.planFeedbackBody(checkpoints.length),
                style: AppText.translation.copyWith(
                  fontSize: 14.5,
                  height: 1.6,
                  color: AppColors.secondary,
                ),
              ),
              const SizedBox(height: AppSpacing.s22),
              for (final (i, checkpoint) in checkpoints.indexed)
                _CheckRow(
                  text: checkpoint.text,
                  checked: _hit.contains(i),
                  onTap: () {
                    AppHaptics.light();
                    setState(() => _hit.contains(i) ? _hit.remove(i) : _hit.add(i));
                  },
                ),
              if (_error != null) ...[
                const SizedBox(height: AppSpacing.s16),
                Text(
                  _error!,
                  style: AppText.translation.copyWith(
                    fontSize: 13.5,
                    height: 1.45,
                    color: AppColors.destructiveText,
                  ),
                ),
              ],
              const SizedBox(height: AppSpacing.s26),
              // Sendable with NOTHING ticked. «Ничего из этого не пригодилось» is a real answer, and
              // a button that refused it would leave the plan running forever after an event that
              // did not go the way it was planned.
              PrimaryButton(
                label: _busy ? l.planBuilderWorking : l.planFeedbackSubmit,
                minHeight: 52,
                enabled: !_busy,
                onPressed: _send,
              ),
              const SizedBox(height: 10),
              Center(
                child: Text(
                  l.planFeedbackClosesPlan,
                  textAlign: TextAlign.center,
                  style: AppText.translation.copyWith(fontSize: 12.5, color: AppColors.tertiary),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _CheckRow extends StatelessWidget {
  const _CheckRow({required this.text, required this.checked, required this.onTap});
  final String text;
  final bool checked;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => InkWell(
    onTap: onTap,
    child: Container(
      constraints: const BoxConstraints(minHeight: 56),
      padding: const EdgeInsets.symmetric(vertical: 12),
      decoration: const BoxDecoration(
        border: Border(bottom: BorderSide(color: AppColors.dividerFaint)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 22,
            height: 22,
            margin: const EdgeInsets.only(top: 1),
            alignment: Alignment.center,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: checked ? AppColors.ink : null,
              border: checked ? null : Border.all(color: AppColors.dashed, width: 1.5),
            ),
            child: checked
                ? const Icon(LucideIcons.check, size: 13, color: AppColors.paper)
                : null,
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Text(
              text,
              style: AppText.translation.copyWith(
                fontSize: 15.5,
                height: 1.45,
                color: checked ? AppColors.ink : AppColors.inkBody,
              ),
            ),
          ),
        ],
      ),
    ),
  );
}
