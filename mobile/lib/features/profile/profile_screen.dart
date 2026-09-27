import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import '../../data/app_settings.dart';
import '../../data/config.dart';
import '../../data/feature_flags.dart';
import '../../data/languages.dart' show sttLocaleFor;
import '../../data/locale_controller.dart';
import '../../data/models.dart';
import '../../data/plan/notify_permission.dart';
import '../../data/providers.dart';
import '../../data/start/account_device_store.dart';
import '../plan/entry/voice_gender_sheet.dart';
import '../plan/notify_prompt.dart';
import '../plan/plan_providers.dart';
import 'account_providers.dart';
import 'build_stamp.dart';
import 'perf_log_screen.dart';
import 'profile_sheets.dart';
import 'qa_speech_view.dart';
import 'voice_bakeoff_screen.dart';

/// Whether the profile shows its «Разработка» door — [AppConfig.devMenuEnabled]; a snapshot turns it off to show the
/// profile as the canvas draws it.
final devMenuProvider = Provider<bool>((ref) => AppConfig.devMenuEnabled);

/// ПРОФИЛЬ (frames 42-1a free, 42-1b Premium, 42-1 en) — pushed from the avatar in a tab's header.
///
/// The avatar's letter and the name (a tap on them — the name sheet 42-2), the door the account came in through, then
/// the groups: ПОДПИСКА (what `access` of `/auth/me` says — the paywall itself is PAY-1's), ОБУЧЕНИЕ (the learner's
/// voice, the session's sounds, the interface language, the native language), НАПОМИНАНИЯ (the switch and the time,
/// 42-4), ПРИЛОЖЕНИЕ (the two documents, a letter to support, the rating), then «Выйти», «Удалить аккаунт» (42-3) and
/// the version. There is no «кто ты» field: the plan asks what it needs when it is made.
class ProfileScreen extends ConsumerStatefulWidget {
  const ProfileScreen({super.key, this.pushed = false, this.focusSubscription = false});

  final bool pushed;

  /// Opened from a day locked by the subscription (23-0a «Подписка»): the subscription group is brought into view —
  /// until PAY-1 brings the paywall, that group is where the button leads.
  final bool focusSubscription;

  @override
  ConsumerState<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends ConsumerState<ProfileScreen> {
  final _subscriptionKey = GlobalKey();
  bool _restoring = false;

  @override
  void initState() {
    super.initState();
    if (widget.focusSubscription) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        final target = _subscriptionKey.currentContext;
        if (target != null) Scrollable.ensureVisible(target, duration: AppMotion.windowSnap, alignment: .1);
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final auth = ref.watch(authControllerProvider);
    final user = auth.value;

    // SIGNED OUT, AND THE PROFILE IS STILL ON TOP (FIX-3 §8): nothing to draw without the account, and an empty
    // opaque route over the sign-in is the «black screen». The screen leaves by itself and holds the paper meanwhile.
    if (user == null) {
      if (widget.pushed && auth.hasValue) {
        WidgetsBinding.instance.addPostFrameCallback((_) {
          if (mounted) Navigator.of(context).maybePop();
        });
      }
      return const ColoredBox(color: AppColors.ground, child: SizedBox.expand());
    }

    final settings = ref.watch(appSettingsProvider).value ?? AppSettings.defaults;
    final permission = ref.watch(notifyPermissionProvider).value ?? NotifyPermission.unknown;
    final remindersOn = settings.remindersOn(systemAllows: permission == NotifyPermission.granted);
    final name = ref.watch(accountNameProvider).value ?? user.name;
    final door = ref.watch(signInDoorProvider).value;
    final uiLang = ref.watch(localeControllerProvider).value ?? UiLanguageOption.system;
    final locale = Localizations.localeOf(context);
    final links = ref.read(accountLinksProvider);
    final version = ref.watch(profileVersionProvider).value ?? '';
    final time = reminderTimeOf(settings, heldPlan(ref)?.reminderHour);

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark,
      child: Scaffold(
        backgroundColor: AppColors.ground,
        body: SafeArea(
          bottom: false,
          child: ListView(
            key: const ValueKey('profile-list'),
            padding: EdgeInsets.fromLTRB(24, 4, 24, 24 + MediaQuery.paddingOf(context).bottom),
            children: [
              if (widget.pushed)
                Align(
                  alignment: Alignment.centerLeft,
                  child: Semantics(
                    button: true,
                    label: l.commonBack,
                    child: GestureDetector(
                      key: const ValueKey('profile-back'),
                      behavior: HitTestBehavior.opaque,
                      onTap: () => Navigator.of(context).maybePop(),
                      child: const SizedBox(height: 24, width: 44, child: Align(alignment: Alignment.centerLeft, child: Icon(LucideIcons.arrowLeft, size: 24, color: AppColors.ink))),
                    ),
                  ),
                )
              else
                const SizedBox(height: 24),
              const SizedBox(height: 14),
              _Header(name: name, onTap: () => showNameSheet(context, ref, current: name)),
              if (door != null) ...[
                const SizedBox(height: 14),
                Row(
                  key: const ValueKey('profile-door'),
                  children: [
                    const Icon(LucideIcons.check, size: 20, color: AppColors.verdictKnown),
                    const SizedBox(width: 12),
                    Text(door == SignInDoor.apple ? l.accountSignedInApple : l.accountSignedInGoogle, style: AppTextStart.door),
                  ],
                ),
              ],
              const SizedBox(height: 32),
              KeyedSubtree(
                key: _subscriptionKey,
                child: SettingsGroup(
                  label: l.accountGroupSubscription,
                  rows: _subscriptionRows(l, user.access, locale),
                ),
              ),
              const SizedBox(height: 32),
              SettingsGroup(
                label: l.accountGroupLearning,
                rows: [
                  SettingsRow(
                    key: const ValueKey('profile-voice'),
                    first: true,
                    label: l.accountVoice,
                    value: user.profile?.gender == kVoiceFemale ? l.accountVoiceFemale : l.accountVoiceMale,
                    onTap: () => _editVoice(user.profile?.gender),
                  ),
                  SettingsRow(
                    key: const ValueKey('profile-sounds'),
                    label: l.accountSessionSounds,
                    end: SettingsRowEnd.toggle,
                    on: settings.sessionSoundsEnabled,
                    onTap: () => ref.read(appSettingsProvider.notifier).setSessionSoundsEnabled(!settings.sessionSoundsEnabled),
                  ),
                  SettingsRow(
                    key: const ValueKey('profile-ui-language'),
                    label: l.accountUiLanguage,
                    value: _uiLanguageName(l, uiLang, locale),
                    onTap: () => showUiLanguageSheet(context, ref, current: uiLang, locale: locale),
                  ),
                  SettingsRow(
                    key: const ValueKey('profile-native'),
                    label: l.accountNativeLanguage,
                    value: nativeLanguageName(user.profile?.nativeLanguage ?? 'ru'),
                    onTap: () => showNativeLanguageSheet(context, ref, current: user.profile?.nativeLanguage ?? 'ru'),
                  ),
                ],
              ),
              const SizedBox(height: 32),
              SettingsGroup(
                label: l.accountGroupReminders,
                rows: [
                  SettingsRow(
                    key: const ValueKey('profile-reminders'),
                    first: true,
                    label: l.accountRemind,
                    end: SettingsRowEnd.toggle,
                    on: remindersOn,
                    onTap: () async {
                      // iOS said no: the switch cannot turn it on — the sheet says where it can (42-4).
                      if (!remindersOn && permission == NotifyPermission.denied) {
                        await showRemindersSheet(context, ref, on: false, time: time);
                        return;
                      }
                      await setRemindersFromProfile(ref, on: !remindersOn);
                    },
                  ),
                  SettingsRow(
                    key: const ValueKey('profile-time'),
                    label: l.accountTime,
                    value: formatReminderTime(time, locale),
                    onTap: () => showRemindersSheet(context, ref, on: remindersOn, time: time),
                  ),
                ],
              ),
              const SizedBox(height: 32),
              SettingsGroup(
                label: l.accountGroupApp,
                rows: [
                  SettingsRow(
                    key: const ValueKey('profile-terms'),
                    first: true,
                    label: l.accountTerms,
                    onTap: () => links.open(AppConfig.termsUrl),
                  ),
                  SettingsRow(
                    key: const ValueKey('profile-privacy'),
                    label: l.accountPrivacy,
                    onTap: () => links.open(AppConfig.privacyUrl),
                  ),
                  SettingsRow(
                    key: const ValueKey('profile-support'),
                    label: l.accountSupport,
                    value: l.accountSupportValue,
                    onTap: () => links.writeSupport(version: version),
                  ),
                  SettingsRow(key: const ValueKey('profile-rate'), label: l.accountRate, onTap: links.rate),
                ],
              ),
              if (ref.watch(devMenuProvider)) ...[
                const SizedBox(height: 32),
                SettingsGroup(label: l.profileSectionDev, rows: const [_DevRows()]),
              ],
              const SizedBox(height: 32),
              Align(
                alignment: Alignment.centerLeft,
                child: GestureDetector(
                  key: const ValueKey('profile-sign-out'),
                  behavior: HitTestBehavior.opaque,
                  onTap: () {
                    AppHaptics.light();
                    unawaited(ref.read(authControllerProvider.notifier).signOut());
                  },
                  child: Text(l.accountSignOut, style: AppTextStart.signOut),
                ),
              ),
              const SizedBox(height: 14),
              Align(
                alignment: Alignment.centerLeft,
                child: GestureDetector(
                  key: const ValueKey('profile-delete'),
                  behavior: HitTestBehavior.opaque,
                  onTap: () => showDeleteAccountSheet(context, ref),
                  child: Text(l.accountDelete, style: AppTextStart.deleteAccount),
                ),
              ),
              const SizedBox(height: 14),
              Text(version, key: const ValueKey('profile-version'), style: AppTextStart.version),
            ],
          ),
        ),
      ),
    );
  }

  List<Widget> _subscriptionRows(AppLocalizations l, AccountAccess? access, Locale locale) {
    if (access == null || !access.premium) {
      return [
        SettingsRow(
          key: const ValueKey('profile-plan'),
          first: true,
          label: l.accountFree,
          value: l.accountFreeValue,
          end: SettingsRowEnd.none,
        ),
      ];
    }
    final until = access.expiresAt;
    return [
      SettingsRow(
        key: const ValueKey('profile-plan'),
        first: true,
        label: l.accountPremium,
        value: until == null ? l.accountPremiumForever : l.accountPremiumUntil(DateFormat('d MMMM', locale.toString()).format(until)),
        end: SettingsRowEnd.none,
      ),
      SettingsRow(
        key: const ValueKey('profile-manage'),
        label: l.accountManageSubscription,
        end: SettingsRowEnd.external,
        onTap: () => ref.read(accountLinksProvider).open(kAppStoreSubscriptions),
      ),
      SettingsRow(
        key: const ValueKey('profile-restore'),
        label: l.accountRestorePurchases,
        end: SettingsRowEnd.none,
        busy: _restoring,
        onTap: _restore,
      ),
    ];
  }

  /// «Восстановить покупки» — «запрос без перехода» (42-1b). There are no purchases in the API until PAY-1: the
  /// server's rights are read again, and the group redraws from the fresh `access`.
  Future<void> _restore() async {
    setState(() => _restoring = true);
    try {
      await ref.read(authControllerProvider.notifier).refreshAccount();
    } finally {
      if (mounted) setState(() => _restoring = false);
    }
  }

  Future<void> _editVoice(String? current) async {
    final chosen = await showVoiceGenderSheet(context, current: current ?? kVoiceMale);
    if (chosen == null || chosen == current || !mounted) return;
    try {
      await ref.read(authControllerProvider.notifier).updateProfile({'gender': chosen});
    } catch (e) {
      debugPrint('[profile] voice: $e');
    }
  }

  static String _uiLanguageName(AppLocalizations l, UiLanguageOption option, Locale locale) => switch (option) {
    UiLanguageOption.russian => l.accountUiRussian,
    UiLanguageOption.english => l.accountUiEnglish,
    // «Системный» is not a choice of 42-1: the row names the language the app speaks now.
    UiLanguageOption.system => locale.languageCode == 'en' ? l.accountUiEnglish : l.accountUiRussian,
  };
}

/// The paper circle with the name's first letter, and the name — a tap on either opens 42-2.
class _Header extends StatelessWidget {
  const _Header({required this.name, required this.onTap});

  final String name;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => GestureDetector(
    key: const ValueKey('profile-header'),
    behavior: HitTestBehavior.opaque,
    onTap: () {
      AppHaptics.light();
      onTap();
    },
    child: Row(
      children: [
        Container(
          width: 56,
          height: 56,
          alignment: Alignment.center,
          decoration: const BoxDecoration(
            shape: BoxShape.circle,
            color: AppColors.avatarPlate,
            boxShadow: [BoxShadow(color: AppColors.sessionSheetShadow, blurRadius: 16, offset: Offset(0, 4))],
          ),
          child: Text(avatarLetter(name), style: AppTextStart.avatarLetter),
        ),
        const SizedBox(width: 14),
        Expanded(child: Text(name, maxLines: 1, overflow: TextOverflow.ellipsis, style: AppTextStart.name)),
      ],
    ),
  );
}

// ── «Разработка» — the dev door (DEV_MENU builds only; not in the canvas) ─────────────────────────────────────

/// The dev toggles and doors — the store / paywall flags, the stall monitor, the line voices, the microphone's
/// insides for a QA account, and the full build line with the server's hash.
class _DevRows extends ConsumerWidget {
  const _DevRows();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final flags = ref.watch(featureFlagsProvider);
    final notifier = ref.read(featureFlagsProvider.notifier);
    final qa = ref.watch(authControllerProvider).value?.qaTools ?? false;
    final lang = ref.watch(authControllerProvider).value?.profile?.targetLanguage ?? 'en';
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        SettingsRow(
          first: true,
          label: l.devFlagStore,
          end: SettingsRowEnd.toggle,
          on: flags.storeEnabled,
          onTap: () => notifier.setStoreEnabled(!flags.storeEnabled),
        ),
        SettingsRow(
          label: l.devFlagPaywall,
          end: SettingsRowEnd.toggle,
          on: flags.paywallEnabled,
          onTap: () => notifier.setPaywallEnabled(!flags.paywallEnabled),
        ),
        SettingsRow(
          label: l.devFlagPremium,
          end: SettingsRowEnd.toggle,
          on: flags.devPremium,
          onTap: () => notifier.setDevPremium(!flags.devPremium),
        ),
        SettingsRow(
          label: l.perfMonitorTitle,
          onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const PerfLogScreen())),
        ),
        SettingsRow(
          label: l.devVoicesTitle,
          onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const VoiceBakeoffScreen())),
        ),
        if (qa)
          Container(
            decoration: const BoxDecoration(border: Border(top: BorderSide(color: AppColors.markerOutline))),
            padding: const EdgeInsets.symmetric(vertical: 12),
            child: QaSpeechView(diagnostics: ref.watch(speechDiagnosticsProvider), localeId: sttLocaleFor(lang)),
          ),
        Container(
          decoration: const BoxDecoration(border: Border(top: BorderSide(color: AppColors.markerOutline))),
          padding: const EdgeInsets.symmetric(vertical: 12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                buildStampText(l, ref.watch(backendCommitProvider), client: ref.watch(clientBuildProvider)),
                style: AppText.transcription.copyWith(fontSize: 12, color: AppColors.secondary),
              ),
              const SizedBox(height: 3),
              Text(AppConfig.apiBaseUrl, style: AppText.transcription.copyWith(fontSize: 11, color: AppColors.tertiary)),
            ],
          ),
        ),
      ],
    );
  }
}

/// The profile's switch (42-1) and the reminders sheet (42-4): a decision. Turning it on asks iOS first when it has not
/// been asked (and registers the push address, as the 43-1 sheet does); refused — it stays off.
Future<void> setRemindersFromProfile(WidgetRef ref, {required bool on}) async {
  final settings = ref.read(appSettingsProvider.notifier);
  if (!on) {
    await settings.setReminders(false);
    return;
  }
  final permission = await ref.read(notifyPermissionProbeProvider).status();
  var allowed = permission == NotifyPermission.granted || permission == NotifyPermission.unknown;
  if (permission == NotifyPermission.notDetermined) allowed = await askNotificationsAndRegister(ref);
  ref.invalidate(notifyPermissionProvider);
  await settings.setReminders(allowed);
}
