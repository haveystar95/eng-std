import 'package:flutter/material.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import '../../../../data/local/cached_image_provider.dart';
import '../../../../data/plan/day_window.dart';
import '../../../../data/plan/plan_models.dart';

/// ЧТО ВЕРНУЛОСЬ И ОТКУДА (кадр 23-0d · вернулось, наряд FIX-3 §§4, 9).
///
/// Своё дня идёт первым и без заголовка; то, что вернулось из прошлого дня, встаёт ниже группой: заголовок
/// «Вернулось из дня N», полоса сцены-источника и единицы теми же карточками и пузырями, что у своих. Сервер говорит и
/// то и другое — `items[].source` и `items[].scene` (`{id, title_native, day_number}`); клиент ничего не выводит сам.
typedef WindowReturnGroup<T> = ({WindowUnitScene? scene, List<T> items});

/// Свои единицы дня и группы возвратов, в порядке первого появления сцены.
({List<T> own, List<WindowReturnGroup<T>> returns}) windowReturnGroups<T>(
  List<T> items, {
  required WindowUnitSource Function(T item) sourceOf,
  required WindowUnitScene? Function(T item) sceneOf,
}) {
  final own = <T>[];
  final order = <String>[];
  final groups = <String, WindowReturnGroup<T>>{};
  for (final item in items) {
    if (sourceOf(item) != WindowUnitSource.returned) {
      own.add(item);
      continue;
    }
    final scene = sceneOf(item);
    final key = scene?.id ?? '';
    if (!groups.containsKey(key)) {
      order.add(key);
      groups[key] = (scene: scene, items: <T>[]);
    }
    groups[key]!.items.add(item);
  }

  return (own: own, returns: [for (final key in order) groups[key]!]);
}

/// ЗАГОЛОВОК ГРУППЫ ВОЗВРАТА И ПОЛОСА ЕЁ СЦЕНЫ (кадр 23-0d · вернулось): «Вернулось из дня 1», под ним
/// «День 1 · Ресепшен зала» с фотографией сцены. Дня у сцены может не быть — тогда в заголовке просто «Вернулось», а в
/// полосе одно имя сцены.
class WindowReturnHeading extends StatelessWidget {
  const WindowReturnHeading({super.key, required this.scene, this.image});

  final WindowUnitScene? scene;
  final PlanImage? image;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final dpr = MediaQuery.maybeDevicePixelRatioOf(context) ?? 2;
    final day = scene?.dayNumber;
    final title = scene?.titleNative;

    return Padding(
      padding: const EdgeInsets.only(top: 28, bottom: 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            (day == null ? l.planWindowReturnedFrom : l.planWindowReturnedFromDay(day)).toUpperCase(),
            style: AppTextWindow.brow,
          ),
          if (title != null) ...[
            const SizedBox(height: 10),
            Row(
              children: [
                SceneCircle(
                  image: image == null ? null : CachedNetworkImage(image!.urlFor(28, dpr)),
                  tone: AppColors.wireTone(image?.tone),
                  size: 28,
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    day == null ? title : l.planRouteDayTitle(day, title),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: AppTextSession.sceneLine,
                  ),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}
