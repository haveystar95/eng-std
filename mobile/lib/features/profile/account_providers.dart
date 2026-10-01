import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../data/app_version.dart';
import '../../data/config.dart';
import '../../data/providers.dart';
import '../../data/start/account_device_store.dart';

/// THE NAME THE APP CALLS THE LEARNER (42-1, 42-2): the one typed in the name sheet on this phone, else the account's.
/// `PATCH /profile` takes no name — the typed one lives on the phone (see [AccountDeviceStore]).
class AccountName extends AsyncNotifier<String> {
  @override
  Future<String> build() async {
    final user = ref.watch(authControllerProvider).value;
    if (user == null) return '';
    return await ref.read(accountDeviceStoreProvider).displayName(user.id) ?? user.name;
  }

  Future<void> rename(String name) async {
    final user = ref.read(authControllerProvider).value;
    final trimmed = name.trim();
    if (user == null || trimmed.isEmpty) return;
    await ref.read(accountDeviceStoreProvider).setDisplayName(user.id, trimmed);
    state = AsyncData(trimmed);
  }
}

final accountNameProvider = AsyncNotifierProvider<AccountName, String>(AccountName.new);

/// The door the account came in through on this phone (42-1 «Вход через …»); null — not known, the row is not drawn.
final signInDoorProvider = FutureProvider<SignInDoor?>((ref) async {
  final user = ref.watch(authControllerProvider).value;
  if (user == null) return null;
  return ref.read(accountDeviceStoreProvider).door(user.id);
});

/// The first letter of the name for the paper circle — «Д» for «Ден».
String avatarLetter(String name) {
  final trimmed = name.trim();
  if (trimmed.isEmpty) return '?';
  return String.fromCharCode(trimmed.runes.first).toUpperCase();
}

/// WHERE THE PROFILE'S ROWS LEAD OUTSIDE THE APP (42-1) — one seam, so a test can see each row go where it should.
abstract interface class AccountLinks {
  /// «Правила», «Конфиденциальность» — a page; «Управлять подпиской» — the App Store's subscriptions.
  Future<void> open(String url);

  /// «Поддержка» — a letter to [AppConfig.supportEmail].
  Future<void> writeSupport({required String version});

  /// «Оценить Ritora» — the system's rating sheet (`SKStoreReviewController`).
  Future<void> rate();
}

class SystemAccountLinks implements AccountLinks {
  const SystemAccountLinks();

  static const _appInfo = MethodChannel('com.denis.engstd/app_info');

  @override
  Future<void> open(String url) async {
    if (url.isEmpty) return;
    await launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
  }

  @override
  Future<void> writeSupport({required String version}) async {
    final uri = Uri(scheme: 'mailto', path: AppConfig.supportEmail, query: 'subject=${Uri.encodeComponent('Ritora $version')}');
    await launchUrl(uri);
  }

  @override
  Future<void> rate() async {
    try {
      await _appInfo.invokeMethod<void>('requestReview');
    } on MissingPluginException {
      // Not iOS — nothing to rate.
    }
  }
}

final accountLinksProvider = Provider<AccountLinks>((ref) => const SystemAccountLinks());

/// The App Store's own page of the learner's subscriptions («Управлять подпиской»).
const kAppStoreSubscriptions = 'https://apps.apple.com/account/subscriptions';

/// «1.0.0 (24)» — the version line at the foot of the profile, as the installed bundle says it ([appVersionProvider]);
/// empty until iOS has answered.
final profileVersionProvider = FutureProvider<String>((ref) async {
  final version = await ref.watch(appVersionProvider.future);
  return version ?? '';
});
