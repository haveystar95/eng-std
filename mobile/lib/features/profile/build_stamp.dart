import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../data/config.dart';
import '../../data/providers.dart';

/// SHA СБОРКИ СЕРВЕРА — читается один раз на запуск (наряд DAY-GATE-1, Ч.0.4).
///
/// Сборка сервера за время сессии не меняется, а вот сеть отвалиться может, поэтому провайдер
/// автоматически ничего не перечитывает: строка версии — не индикатор связи, и мигать ей нечем.
final backendCommitProvider = FutureProvider<String>(
  (ref) => ref.read(apiClientProvider).health(),
);

/// СТРОКА ВЕРСИИ — «клиент abc1234 · 07.09 21:55 · сервер def5678».
///
/// Стоит внизу вкладки «План» ВСЕГДА, а не под дев-флагом, и это главное в ней. 07.09 сутки ушли
/// на разбор поломки, которой в текущем коде уже не было: телефон показывал одно, репозиторий —
/// другое, и вопрос «а ту ли сборку мы смотрим» никто не мог закрыть. Строка закрывает его за
/// полсекунды и стоит одну строчку кегля 10.
///
/// Обе половины честны по отдельности: клиентская зашита при компиляции и не может устареть,
/// серверная приезжает живьём. Не пришла — так и написано; выдуманного SHA здесь не бывает.
class BuildStampLine extends ConsumerWidget {
  const BuildStampLine({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);

    return Padding(
      padding: const EdgeInsets.only(top: AppSpacing.s16, bottom: AppSpacing.s8),
      child: Text(
        buildStampText(l, ref.watch(backendCommitProvider)),
        textAlign: TextAlign.center,
        style: AppText.transcription.copyWith(fontSize: 10, color: AppColors.tertiary),
      ),
    );
  }

  /// Текст строки — отдельно от виджета, чтобы дев-дверь печатала ТО ЖЕ САМОЕ, а не свою версию
  /// той же мысли, и чтобы тест мог спросить его без экрана.
  static String buildStampText(AppLocalizations l, AsyncValue<String> backend) {
    final client = AppConfig.buildSha.isEmpty
        ? l.buildStampUnstamped
        : '${AppConfig.buildSha}${AppConfig.buildAt.isEmpty ? '' : ' · ${AppConfig.buildAt}'}';
    final server = backend.when(
      data: (commit) => commit.isEmpty ? l.buildStampUnstamped : commit,
      // Пока не ответил — молчим о сервере, а не показываем «…»: строка не про связь.
      loading: () => l.buildStampWaiting,
      error: (_, _) => l.buildStampNoServer,
    );

    return l.buildStamp(client, server);
  }
}
