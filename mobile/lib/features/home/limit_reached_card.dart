import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';
import 'package:flutter/widgets.dart';

/// F13: the daily new-term quota is spent while new words still wait. An inactive card — never a
/// button into a session the server would return empty — pointing at free practice (which ignores
/// the quota). Shared by the home screen and a collection screen (F13b), so the copy lives once.
/// Reviews, when due, get their own active CTA instead of this.
class LimitReachedCard extends StatelessWidget {
  const LimitReachedCard({super.key});

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return PaperCard(
      radius: AppRadii.chip,
      padding: const EdgeInsets.fromLTRB(AppSpacing.s22, 20, AppSpacing.s22, 20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            l.homeLimitReachedTitle,
            textAlign: TextAlign.center,
            style: AppText.stepTitle.copyWith(fontSize: 22),
          ),
          const SizedBox(height: 8),
          Text(
            l.homeLimitReachedHint,
            textAlign: TextAlign.center,
            style: AppText.translation.copyWith(
              fontSize: 13.5,
              height: 1.45,
              color: AppColors.secondary,
            ),
          ),
        ],
      ),
    );
  }
}
