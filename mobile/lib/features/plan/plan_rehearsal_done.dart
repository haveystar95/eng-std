import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';
import 'package:eng_std/l10n/app_localizations.dart';

import '../../data/providers.dart';
import 'plan_ui.dart';

/// «ПОДГОТОВКА ЗАВЕРШЕНА» — the end of the final day's run-through, and the act that closes the plan.
///
/// The smallest version of itself on purpose: the finished final-day screen is DAY-2, and what was
/// missing before this was not a screen, it was a WAY THROUGH. The final day introduces nothing and
/// owns no collection, so the server refuses to build it (404) — and the client drew «Собрать день»
/// anyway, took the 404, printed «материал не прошёл проверку» and stopped. The plan could not be
/// finished from the app at all; the live run closed it from tinker (Д-27).
///
/// So: the run-through ends here, `POST /plans/{id}/complete` runs the ordinary `EndPlan(Complete)`
/// — the same archive `abandon` performs — and «К плану» lands on the finished plan's own screen,
/// which has said «Подготовка завершена» since PLAN-1c.
class PlanRehearsalDone extends ConsumerStatefulWidget {
  const PlanRehearsalDone({super.key, required this.planId, required this.onDone});

  final String planId;

  final VoidCallback onDone;

  @override
  ConsumerState<PlanRehearsalDone> createState() => _PlanRehearsalDoneState();
}

class _PlanRehearsalDoneState extends ConsumerState<PlanRehearsalDone> {
  /// Null while the request is in flight, true once the plan is closed, false when it could not be.
  bool? _closed;

  @override
  void initState() {
    super.initState();
    unawaited(_close());
  }

  Future<void> _close() async {
    try {
      await ref.read(apiClientProvider).completePlan(widget.planId);
      if (!mounted) return;
      // Every plan surface is read live (see providers.dart) — none of them is mirrored — so the
      // finished state reaches the tab, the home card and this plan's own screen by re-reading.
      ref.invalidate(planProvider(widget.planId));
      ref.invalidate(activePlanProvider);
      ref.invalidate(planArchiveProvider);
      setState(() => _closed = true);
    } catch (_) {
      if (mounted) setState(() => _closed = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);

    return SafeArea(
      bottom: false,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(
          AppSpacing.screenH,
          AppSpacing.s26,
          AppSpacing.screenH,
          AppSpacing.s26,
        ),
        children: [
          const SizedBox(height: 28),
          Center(child: PlanLabel(l.planFinishedBadge)),
          const SizedBox(height: 14),
          Text(
            l.planRehearsalDoneTitle,
            textAlign: TextAlign.center,
            style: AppText.displayTerm.copyWith(fontSize: 34, height: 1.15),
          ),
          const SizedBox(height: 12),
          Text(
            // The honest sentence, and only once the server has agreed. A screen that announced the
            // archive over a request that failed would be the same lie the old «слова ушли в общее
            // повторение» was (Д-30) — one screen down, in the other direction.
            _closed == false ? l.planErrorOffline : l.planRehearsalDoneBody,
            textAlign: TextAlign.center,
            style: AppText.translation.copyWith(
              fontSize: 15,
              height: 1.6,
              color: AppColors.secondary,
            ),
          ),
          const SizedBox(height: AppSpacing.s26),
          if (_closed == null)
            const Center(child: CircularProgressIndicator(color: AppColors.ink))
          else if (_closed == false)
            PrimaryButton(label: l.generationRetry, minHeight: 52, onPressed: () {
              setState(() => _closed = null);
              unawaited(_close());
            })
          else
            PrimaryButton(
              label: l.planRehearsalDoneAction,
              minHeight: 52,
              onPressed: widget.onDone,
            ),
        ],
      ),
    );
  }
}
