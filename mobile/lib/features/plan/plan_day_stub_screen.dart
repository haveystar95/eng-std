import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../data/plan/plan_models.dart';

/// ЗАГЛУШКА КАБИНЕТА ДНЯ — стоит до наряда DAY-UI и удаляется им (наряд PLAN-UI, §3).
///
/// Тап по плите и её кнопке ведут сюда, чтобы у «Начать» было куда вести уже сейчас. Экран
/// печатает ровно то, что честно знает: какой день открыт и какой контракт отвечает сервер
/// (`Plan.versions`). Кадра у него нет и не будет.
class PlanDayStubScreen extends StatelessWidget {
  const PlanDayStubScreen({super.key, required this.plan, required this.day});

  final Plan plan;
  final PlanDayRoute day;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final title = day.titleNative ?? plan.sceneOf(day)?.titleNative ?? plan.displayTitle;

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark,
      child: Scaffold(
        backgroundColor: AppColors.paper,
        body: SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(AppSpacing.screenH, AppSpacing.s8, AppSpacing.screenH, AppSpacing.s26),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Semantics(
                      button: true,
                      label: l.commonBack,
                      child: InkResponse(
                        radius: 22,
                        onTap: () => Navigator.of(context).maybePop(),
                        child: const SizedBox(
                          width: AppSpacing.minTap,
                          height: AppSpacing.minTap,
                          child: Icon(LucideIcons.chevronLeft, size: 22, color: AppColors.secondary),
                        ),
                      ),
                    ),
                    Expanded(child: Text(l.planDayStubTitle, style: AppText.screenTitle)),
                  ],
                ),
                const SizedBox(height: AppSpacing.s26),
                Text(l.planDayStubDay(day.number, title), style: AppText.termInList.copyWith(fontFamily: AppFonts.inter)),
                const SizedBox(height: AppSpacing.s12),
                Text(
                  l.planDayStubBody(plan.versions.line),
                  style: AppText.translation.copyWith(height: 1.5),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
