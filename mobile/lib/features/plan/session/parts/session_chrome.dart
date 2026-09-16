import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../../data/local/cached_image_provider.dart';
import '../../../../data/plan/plan_models.dart';
import '../../../../data/plan/session/session_queue.dart';
import '../../../../data/providers.dart';
import '../../../../ui/scene_circle.dart';
import 'session_bits.dart';

/// ШАПКА СЕССИИ (кадр 30-2): крестик, имя этапа, полоса прогресса, справа словами «ещё N слов», под ней
/// бусины по единицам — пройденные шалфеем, текущая латунью, впереди контуром. Минут в шапке нет.
class SessionHeader extends StatelessWidget {
  const SessionHeader({
    super.key,
    required this.stageName,
    required this.progress,
    required this.left,
    required this.beads,
    required this.onClose,
  });

  final String stageName;

  /// Доля отвеченных карточек этапа.
  final double progress;

  /// «ещё 4 слова».
  final String left;
  final List<SessionBead> beads;
  final VoidCallback onClose;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final reduce = MediaQuery.maybeDisableAnimationsOf(context) ?? false;
    return Padding(
      padding: const EdgeInsets.fromLTRB(kSessionGutter - kSessionCloseInset, 4, kSessionGutter, 0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            height: 24,
            child: Row(
              children: [
                SessionCloseButton(onTap: onClose, label: l.planSessionClose),
                const SizedBox(width: 12 - kSessionCloseInset),
                Text(stageName, style: AppTextSession.headerStage),
                const SizedBox(width: 12),
                Expanded(
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(2),
                    child: SizedBox(
                      height: 4,
                      child: LayoutBuilder(
                        builder: (_, c) => Stack(
                          children: [
                            const Positioned.fill(child: ColoredBox(color: AppColors.markerOutline)),
                            TweenAnimationBuilder<double>(
                              tween: Tween(end: progress.clamp(0.0, 1.0)),
                              duration: reduce ? Duration.zero : AppMotion.sessionBarFill + AppMotion.sessionBarFillDelay,
                              curve: reduce
                                  ? Curves.linear
                                  : const Interval(120 / 380, 1, curve: Curves.easeInOut),
                              builder: (_, v, _) => Container(
                                width: c.maxWidth * v,
                                height: 4,
                                decoration: BoxDecoration(
                                  color: AppColors.verdictKnown,
                                  borderRadius: BorderRadius.circular(2),
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Text(left, style: AppTextSession.meta.copyWith(height: 1)),
              ],
            ),
          ),
          const SizedBox(height: 8),
          Padding(
            padding: const EdgeInsets.only(left: kSessionCloseInset),
            child: SizedBox(
              height: 8,
              child: Wrap(
                spacing: 4,
                crossAxisAlignment: WrapCrossAlignment.center,
                children: [for (final b in beads) _Bead(b)],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// На сколько поле касания крестика (44) выходит за его значок (24) влево.
const double kSessionCloseInset = 10;

class _Bead extends StatelessWidget {
  const _Bead(this.state);

  final SessionBead state;

  @override
  Widget build(BuildContext context) {
    final current = state == SessionBead.current;
    return AnimatedContainer(
      duration: AppMotion.sessionBeadFill,
      width: current ? 8 : 6,
      height: current ? 8 : 6,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: switch (state) {
          SessionBead.done => AppColors.verdictKnown,
          SessionBead.current => AppColors.brassInk,
          SessionBead.ahead => Colors.transparent,
        },
        border: state == SessionBead.ahead ? Border.all(color: AppColors.markerOutline, width: 1.5) : null,
      ),
    );
  }
}

/// Крестик / стрелка назад 24 — поле касания 44.
class SessionCloseButton extends StatelessWidget {
  const SessionCloseButton({super.key, required this.onTap, required this.label, this.back = false});

  final VoidCallback onTap;
  final String label;

  /// Стрелка «назад» (вход в этап 30-1) вместо крестика.
  final bool back;

  /// Поле касания 44 в ширину (значок 24 стоит на кромке поля экрана, поле касания выходит влево на
  /// [kSessionCloseInset]) и 24 в высоту строки шапки. Вызывающий ставит кнопку с отступом
  /// `kSessionGutter - kSessionCloseInset`.
  @override
  Widget build(BuildContext context) => Semantics(
    button: true,
    label: label,
    child: GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: onTap,
      child: SizedBox(
        width: 24 + 2 * kSessionCloseInset,
        height: 24,
        child: Center(child: Icon(back ? LucideIcons.arrowLeft : LucideIcons.x, size: 24, color: AppColors.ink)),
      ),
    ),
  );
}

/// ПОЛОСА СЦЕНЫ (кадр 30-2b): фото сцены 32, «Запись к врачу · Регистратор» (роль в именительном, как отдал
/// сервер), кружок ученика 32 справа. Не нажимается — это напоминание.
class SessionSceneStrip extends ConsumerWidget {
  const SessionSceneStrip({super.key, required this.scene});

  final PlanScene? scene;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final s = scene;
    final role = s?.partnerRoleNative?.trim() ?? '';
    final title = s?.titleNative.trim() ?? '';
    final line = role.isEmpty ? title : (title.isEmpty ? role : l.planSessionSceneLine(title, role));
    final dpr = MediaQuery.maybeDevicePixelRatioOf(context) ?? 2;
    final photo = s?.image;
    final user = ref.watch(authControllerProvider).value;
    final name = user?.name.trim() ?? '';
    return Padding(
      padding: const EdgeInsets.fromLTRB(kSessionGutter, 14, kSessionGutter, 0),
      child: SizedBox(
        height: 48,
        child: Row(
          children: [
            SceneCircle(
              image: photo == null ? null : CachedNetworkImage(photo.urlFor(32, dpr)),
              tone: AppColors.wireTone(photo?.tone),
              size: 32,
            ),
            const SizedBox(width: 12),
            Expanded(child: Text(line, maxLines: 2, style: AppTextSession.sceneLine)),
            const SizedBox(width: 12),
            Container(
              width: 32,
              height: 32,
              clipBehavior: Clip.antiAlias,
              alignment: Alignment.center,
              decoration: const BoxDecoration(shape: BoxShape.circle, color: AppColors.photoPlaceholder),
              child: user?.avatar != null
                  ? Image(image: CachedNetworkImage(user!.avatar!), fit: BoxFit.cover, width: 32, height: 32)
                  : Text(
                      name.isEmpty ? '' : name.characters.first.toUpperCase(),
                      style: AppTextSession.headerStage.copyWith(fontSize: 14, color: AppColors.inkBody),
                    ),
            ),
          ],
        ),
      ),
    );
  }
}

/// БАННЕР «НЕТ СВЯЗИ» — ответ ждёт отправки; следующая карточка не откроется, пока он не ушёл.
class SessionOfflineBanner extends StatelessWidget {
  const SessionOfflineBanner({super.key});

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(kSessionGutter, 10, kSessionGutter, 0),
    child: Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      decoration: BoxDecoration(color: AppColors.paper, borderRadius: BorderRadius.circular(12), boxShadow: kSessionSheetShadow),
      child: Row(
        children: [
          const Icon(LucideIcons.wifiOff, size: 16, color: AppColors.secondary),
          const SizedBox(width: 10),
          Expanded(child: Text(AppLocalizations.of(context).planSessionOffline, style: AppTextSession.meta)),
        ],
      ),
    ),
  );
}
