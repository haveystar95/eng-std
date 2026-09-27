import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'package:eng_std/l10n/app_localizations.dart';

import '../../data/config.dart';
import '../../data/providers.dart';

/// SHA СБОРКИ СЕРВЕРА — читается один раз на запуск (наряд DAY-GATE-1, Ч.0.4).
///
/// Сборка сервера за время сессии не меняется, а вот сеть отвалиться может, поэтому провайдер
/// автоматически ничего не перечитывает: строка версии — не индикатор связи, и мигать ей нечем.
final backendCommitProvider = FutureProvider<String>(
  (ref) => ref.read(apiClientProvider).health(),
);

/// THE CLIENT'S HALF OF THE LINE — what `--dart-define` baked in at build time (`scripts/build_ios.sh`).
///
/// A provider rather than the constant read directly: a test that shows the line needs STAMPED values, and a
/// compile-time constant is always empty under test. Overridden only in tests; in the app it is exactly `AppConfig`.
final clientBuildProvider = Provider<({String sha, String at})>(
  (ref) => (sha: AppConfig.buildSha, at: AppConfig.buildAt),
);

/// СТРОКА ВЕРСИИ — «клиент abc1234 · 07.09 21:55 · сервер def5678» — the full build line of the profile's dev door
/// (DEV_MENU builds). The profile itself shows the store version, «1.0.0 (22)» (frame 42-1).
///
/// Both halves are honest on their own: the client's is baked in at compile time and cannot go stale, the server's
/// arrives live. Not arrived — it says so; there is never an invented SHA here. A function, not a widget, so a test
/// can ask for the text without a screen.
String buildStampText(
  AppLocalizations l,
  AsyncValue<String> backend, {
  ({String sha, String at}) client = const (sha: AppConfig.buildSha, at: AppConfig.buildAt),
}) {
  final clientText = client.sha.isEmpty
      ? l.buildStampUnstamped
      : '${client.sha}${client.at.isEmpty ? '' : ' · ${client.at}'}';
  final server = backend.when(
    data: (commit) => commit.isEmpty ? l.buildStampUnstamped : commit,
    // Until it answers — say nothing about the server rather than «…»: the line is not about the connection.
    loading: () => l.buildStampWaiting,
    error: (_, _) => l.buildStampNoServer,
  );

  return l.buildStamp(clientText, server);
}
