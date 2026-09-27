import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../data/local/cached_image_provider.dart';
import '../../data/providers.dart';
import 'account_providers.dart';
import 'profile_screen.dart';

/// ПРОФИЛЬ — КРУЖОК-АВАТАР 30 В ШАПКЕ СПРАВА (токен-лист 4к-1). Таба у профиля нет.
///
/// Один виджет для трёх шапок — Сегодня, План, Коллекции — чтобы «где профиль» имело один ответ.
/// Подложка #E3DCCF с инициалом 14/700, фото аккаунта поверх, когда оно есть. Тап толкает экран
/// профиля поверх таба.
class ProfileAvatarButton extends ConsumerWidget {
  const ProfileAvatarButton({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final user = ref.watch(authControllerProvider).value;
    // The name the profile shows — typed on this phone (42-2) or the account's — so the two circles agree.
    final initial = avatarLetter(ref.watch(accountNameProvider).value ?? user?.name ?? '');

    return Semantics(
      button: true,
      label: l.profileTitle,
      child: InkResponse(
        radius: 22,
        onTap: () {
          AppHaptics.light();
          Navigator.of(context).push(
            MaterialPageRoute(builder: (_) => const ProfileScreen(pushed: true)),
          );
        },
        child: SizedBox(
          width: AppSpacing.minTap,
          height: AppSpacing.minTap,
          child: Center(
            child: Container(
              width: 30,
              height: 30,
              clipBehavior: Clip.antiAlias,
              alignment: Alignment.center,
              decoration: const BoxDecoration(shape: BoxShape.circle, color: AppColors.photoPlaceholder),
              child: user?.avatar != null
                  ? Image(image: CachedNetworkImage(user!.avatar!), fit: BoxFit.cover)
                  : Text(
                      initial,
                      style: const TextStyle(
                        fontFamily: AppFonts.inter,
                        fontSize: 14,
                        fontWeight: FontWeight.w700,
                        color: AppColors.inkBody,
                      ),
                    ),
            ),
          ),
        ),
      ),
    );
  }
}
