import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:url_launcher/url_launcher.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import 'buttons.dart';

/// РАЗРЕШЕНИЕ НА РЕЧЬ НЕ ДАНО — и экран говорит это словами (наряд DAY-GATE-1, Ч.0.2).
///
/// До этого любой отказ канала выглядел одинаково: «микрофон недоступен» и мигающий кружок. Но
/// отказы бывают двух совершенно разных родов, и человек может починить только один из них:
///
///   * микрофона нет / движок не поднялся — делать нечего, ход проходится пропуском;
///   * РАЗРЕШЕНИЕ отозвано или не дано — чинится за три касания в Настройках, и приложение обязано
///     сказать, в каких именно и зачем. iOS спрашивает разрешение ОДИН раз за установку: после
///     отказа `initialize` возвращает false молча и навсегда, поэтому «нажми ещё раз» — не выход,
///     а бесконечный цикл.
///
/// Два разрешения тоже разные (`NSMicrophoneUsageDescription` и
/// `NSSpeechRecognitionUsageDescription`), и отказать можно любому по отдельности, — поэтому
/// [micDenied] и [recognitionDenied] стоят врозь и подпись называет то, чего не хватает.
///
/// ОДНО МЕСТО НА ВСЕ КАРТОЧКИ ГОВОРЕНИЯ: прогон сцены, «Скажи вслух» в разговоре, говорение слов,
/// эхо интро. Копия этой строки в каждой из них — это четыре места, где чинить одну опечатку.
class SpeechPermissionNotice extends StatelessWidget {
  const SpeechPermissionNotice({
    super.key,
    required this.micDenied,
    required this.recognitionDenied,
  });

  final bool micDenied;
  final bool recognitionDenied;

  /// Открыть страницу приложения в Настройках. `app-settings:` — документированная схема iOS,
  /// ведущая ровно на экран этого приложения, где обе строки разрешений и лежат.
  static Future<void> openSettings() async {
    final uri = Uri.parse('app-settings:');
    if (await canLaunchUrl(uri)) await launchUrl(uri);
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    // Когда не хватает обоих — говорим про оба, а не выбираем один: человек, включивший микрофон и
    // снова упёршийся в тишину, второй раз в Настройки не пойдёт.
    final text = micDenied && recognitionDenied
        ? l.speechPermissionBothDenied
        : micDenied
        ? l.speechPermissionMicDenied
        : l.speechPermissionRecognitionDenied;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          text,
          textAlign: TextAlign.center,
          // Тон обычной заметки, не вердикта: с памятью человека ничего не случилось.
          style: AppTextExercise.taskInstruction,
        ),
        const SizedBox(height: AppSpacing.s12),
        Center(
          child: QuietButton(
            label: l.speechPermissionOpenSettings,
            icon: LucideIcons.settings,
            onPressed: () => openSettings(),
          ),
        ),
      ],
    );
  }
}
