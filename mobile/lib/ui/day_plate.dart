import 'dart:ui' show ImageFilter;

import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';

import 'plan_marks.dart';
import 'verdict_marker.dart';

/// ПЛИТА ДНЯ — компонент 4н токен-листа, в свёрнутом размере (карточка на табе «План»,
/// кадры 21-2 … 21-4, 22-5a/b).
///
/// Карточка с четырьмя углами radius 28 и без фото (материал 4и: градиент #332A23 → #292219,
/// лейбл «ДЕНЬ N» латунью, обложка кружком 52 с латунным кантом). Второй размер компонента —
/// шапка кабинета — стоит ниже в этом же файле ([DayRoomPlate], наряд DAY-UI): почему отдельным
/// классом, сказано там.
///
/// Строки этапов — без иконок (записка: «индикатор 14 + имя + счётчик»): контур rgba(paper,.30)
/// у запертого, контур paper у текущего, шалфей с галкой у закрытого; вторая строка 14 alpha .60
/// только у текущего. Подвал — по состоянию: бумажная кнопка «Начать» / «Продолжить», шиммер
/// строк у дня, который ещё пишется (22-5a), и — у закрытого дня (21-4) — та же карточка на
/// СВЕТЛОЙ бумаге с галкой в круге 30 и терракотовой строкой «Вернутся в день N».
///
/// `lib/ui/` knows no languages: every string here is the caller's.
class DayPlate extends StatelessWidget {
  const DayPlate({
    super.key,
    required this.label,
    required this.title,
    required this.stages,
    this.meta,
    this.cover,
    this.footer = const DayPlateFooter.none(),
    this.closed = false,
    this.returnLine,
    this.nextDayLine,
    this.notice,
    this.onTap,
  });

  /// «ДЕНЬ 2» — латунью, caps.
  final String label;

  /// «Приём у врача» — сервер; одна строка, обрезается многоточием.
  final String title;

  /// «75 карточек · ≈ 20 минут» — под названием, alpha .55. Null — строки нет.
  final String? meta;

  /// Обложка плана — кружок 52 с латунным кантом. Null — пустая подложка #E3DCCF.
  final ImageProvider? cover;

  final List<DayPlateStage> stages;
  final DayPlateFooter footer;

  /// Закрытый день (кадр 21-4): светлая бумага, галка в круге 30, Literata 23, счётчики 600.
  final bool closed;

  /// «3 карточки вернутся в день 3 →» — терракотой, только у закрытого дня. Null — строки нет.
  final String? returnLine;

  /// «День 3 откроется завтра, 12 сентября» — первая строка подвала закрытого дня (21-4): экран
  /// НЕ даёт кнопки «дальше», следующий день открывается со своего узла в маршруте, и подвал
  /// говорит только когда.
  final String? nextDayLine;

  /// СТРОКА ВМЕСТО ЭТАПОВ (22-5a, 22-5c): день ещё пишется или не собрался. Пока она стоит,
  /// строк этапов на плите нет — их ещё нечем заполнить.
  final DayPlateNotice? notice;

  /// Тап по карточке — в кабинет дня.
  final VoidCallback? onTap;

  static const double _radius = 28;

  /// Закрытый день — светлая бумага чуть меньшего радиуса (21-4: 26 против 28 у тёмной плиты).
  static const double _closedRadius = 26;

  @override
  Widget build(BuildContext context) {
    final br = BorderRadius.circular(closed ? _closedRadius : _radius);
    final ink = closed ? AppColors.ink : AppColors.paper;
    final body = Padding(
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (closed)
            _ClosedHead(title: title, meta: meta)
          else
            _Head(label: label, title: title, cover: cover),
          // На тёмной плите счёт дня — своя строка под шапкой; у закрытого он уже внутри шапки.
          if (meta != null && !closed) ...[
            const SizedBox(height: 10),
            Text(
              meta!,
              style: TextStyle(
                fontFamily: AppFonts.inter,
                fontSize: 14,
                color: AppColors.paper.withValues(alpha: .55),
                fontFeatures: const [FontFeature.tabularFigures()],
              ),
            ),
          ],
          const SizedBox(height: 12),
          if (notice != null)
            _NoticeRow(notice: notice!)
          else
            for (var i = 0; i < stages.length; i++)
              _StageRow(stage: stages[i], last: i == stages.length - 1, ink: ink, closed: closed),
          // ПОДВАЛ ЗАКРЫТОГО ДНЯ (21-4) — две строки текстом под волосяной линией: когда откроется
          // следующий день и какие карточки в него вернутся. Значков и кнопок здесь нет: действия
          // на этом экране тоже нет.
          if (nextDayLine != null || returnLine != null) ...[
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.only(top: 12),
              decoration: BoxDecoration(
                border: Border(top: BorderSide(color: AppColors.ink.withValues(alpha: .10))),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (nextDayLine != null)
                    Text(
                      nextDayLine!,
                      style: const TextStyle(
                        fontFamily: AppFonts.inter,
                        fontSize: 14,
                        height: 1.4,
                        color: AppColors.tertiary,
                      ),
                    ),
                  if (returnLine != null) ...[
                    if (nextDayLine != null) const SizedBox(height: 4),
                    Text(
                      returnLine!,
                      style: const TextStyle(
                        fontFamily: AppFonts.inter,
                        fontSize: 14,
                        fontWeight: FontWeight.w600,
                        height: 1.4,
                        color: AppColors.destructiveText,
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ],
          ..._footer(),
        ],
      ),
    );

    return Semantics(
      button: onTap != null,
      child: DecoratedBox(
        decoration: BoxDecoration(
          borderRadius: br,
          gradient: closed
              ? null
              : const LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [AppColors.plateTop, AppColors.plateBottom],
                ),
          color: closed ? AppColors.paper : null,
          boxShadow: closed ? AppShadows.card : AppShadows.plate,
        ),
        child: Material(
          type: MaterialType.transparency,
          borderRadius: br,
          clipBehavior: Clip.antiAlias,
          child: InkWell(
            onTap: onTap,
            splashColor: ink.withValues(alpha: .06),
            highlightColor: ink.withValues(alpha: .04),
            child: body,
          ),
        ),
      ),
    );
  }

  List<Widget> _footer() => switch (footer) {
    DayPlateFooterNone() => const [],
    DayPlateFooterButton(:final label, :final onTap, :final enabled) => [
      const SizedBox(height: 14),
      _PaperButton(label: label, onTap: onTap, enabled: enabled),
    ],
  };
}

/// Where the plate's footer stands: nothing (a closed day), or the one bumаga button.
sealed class DayPlateFooter {
  const DayPlateFooter();

  const factory DayPlateFooter.none() = DayPlateFooterNone;
  const factory DayPlateFooter.button({
    required String label,
    VoidCallback? onTap,
    bool enabled,
  }) = DayPlateFooterButton;
}

class DayPlateFooterNone extends DayPlateFooter {
  const DayPlateFooterNone();
}

class DayPlateFooterButton extends DayPlateFooter {
  const DayPlateFooterButton({required this.label, this.onTap, this.enabled = true});

  final String label;
  final VoidCallback? onTap;
  final bool enabled;
}

/// One stage row: name, «done / total», its state, and the second line the current one carries.
class DayPlateStage {
  const DayPlateStage({
    required this.kind,
    required this.name,
    required this.count,
    required this.state,
    this.note,
    this.noteColor,
  });

  /// КАКОЙ ЭТО ЭТАП — значок канвы стоит слева в строке вместо кружка-маркера, и состояние он
  /// несёт тонировкой (`assets/stages/`, правило — в [PlanStageMark]).
  final PlanStageMarkKind kind;

  final String name;

  /// «0 / 32» — the caller's string (plan.plate.stage.count); `lib/ui/` composes no copy.
  final String count;
  final DayPlateStageState state;

  /// «начни отсюда · 8 новых слов» / «не закончен · 10 карточек» / «1 с подсказкой».
  final String? note;

  /// Охра у «с подсказкой»; null — paper .60 (dark) / secondary (paper).
  final Color? noteColor;
}

enum DayPlateStageState { locked, current, done }

class _Head extends StatelessWidget {
  const _Head({required this.label, required this.title, this.cover});

  final String label;
  final String title;
  final ImageProvider? cover;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      Container(
        width: 52,
        height: 52,
        clipBehavior: Clip.antiAlias,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          color: AppColors.photoPlate,
          border: Border.all(color: AppColors.brassHairline),
        ),
        child: cover == null ? null : Image(image: cover!, fit: BoxFit.cover),
      ),
      const SizedBox(width: 14),
      Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              label.toUpperCase(),
              style: const TextStyle(
                fontFamily: AppFonts.inter,
                fontSize: 11,
                fontWeight: FontWeight.w700,
                letterSpacing: 1.54,
                color: AppColors.brass,
              ),
            ),
            const SizedBox(height: 5),
            // НАЗВАНИЕ ДНЯ ПЕРЕНОСИТСЯ НА ДВЕ СТРОКИ, обрезки нет (канва 21-2): длинное название
            // сцены («Ресторан с ребёнком», «Повторный визит к врачу») в одну строку с троеточием
            // теряло ровно то слово, которым день и отличается от соседнего.
            Text(
              title,
              maxLines: 2,
              overflow: TextOverflow.clip,
              style: TextStyle(
                fontFamily: AppFonts.inter,
                fontSize: 22,
                fontWeight: FontWeight.w700,
                letterSpacing: -0.33,
                height: 1.15,
                color: AppColors.paper,
              ),
            ),
          ],
        ),
      ),
    ],
  );
}

/// ШАПКА ЗАКРЫТОГО ДНЯ (кадр 21-4): галка в круге ink 34, «День 2 закрыт» Literata 22 и под ней
/// счёт дня — обе строки в одной колонке, потому что это один итог, а не заголовок с подписью.
class _ClosedHead extends StatelessWidget {
  const _ClosedHead({required this.title, this.meta});

  final String title;
  final String? meta;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      Container(
        width: 34,
        height: 34,
        decoration: const BoxDecoration(shape: BoxShape.circle, color: AppColors.ink),
        child: const Icon(LucideIcons.check, size: 18, color: AppColors.paper),
      ),
      const SizedBox(width: 12),
      Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              title,
              style: const TextStyle(
                fontFamily: AppFonts.literata,
                fontSize: 22,
                fontWeight: FontWeight.w500,
                letterSpacing: -0.33,
                height: 1.15,
                color: AppColors.ink,
              ),
            ),
            if (meta != null) ...[
              const SizedBox(height: 3),
              Text(
                meta!,
                style: const TextStyle(
                  fontFamily: AppFonts.inter,
                  fontSize: 14,
                  height: 1.35,
                  color: AppColors.secondary,
                ),
              ),
            ],
          ],
        ),
      ),
    ],
  );
}

class _StageRow extends StatelessWidget {
  const _StageRow({
    required this.stage,
    required this.last,
    required this.ink,
    required this.closed,
  });

  final DayPlateStage stage;
  final bool last;
  final Color ink;
  final bool closed;

  @override
  Widget build(BuildContext context) {
    final line = BorderSide(color: ink.withValues(alpha: closed ? .10 : .14));
    final current = stage.state == DayPlateStageState.current;
    final locked = stage.state == DayPlateStageState.locked;

    // ДВА НАБОРА СТИЛЕЙ, а не один с поправками: тёмная плита идущего дня (21-2, 21-3) и светлая
    // бумага закрытого (21-4) — это разные строки в канве, вплоть до кегля имени и цвета счёта.
    //
    // тёмная:  имя 14, текущий w600/1, пройденный w400/.72, запертый w400/.5; счёт 14.5 w600,
    //          alpha 1 у пройденного и текущего, .5 у запертого; вторая строка paper .60.
    // светлая: имя 15 w600 ink; счёт 14 w600 ШАЛФЕЕМ (день сдан, и счёт об этом говорит);
    //          вторая строка 13 tertiary.
    final nameStyle = closed
        ? const TextStyle(
            fontFamily: AppFonts.inter,
            fontSize: 15,
            fontWeight: FontWeight.w600,
            height: 1.3,
            color: AppColors.ink,
          )
        : TextStyle(
            fontFamily: AppFonts.inter,
            fontSize: 14,
            fontWeight: current ? FontWeight.w600 : FontWeight.w400,
            color: ink.withValues(alpha: current ? 1 : (locked ? .5 : .72)),
          );
    final countStyle = closed
        ? const TextStyle(
            fontFamily: AppFonts.inter,
            fontSize: 14,
            fontWeight: FontWeight.w600,
            color: AppColors.verdictKnown,
            fontFeatures: [FontFeature.tabularFigures()],
          )
        : TextStyle(
            fontFamily: AppFonts.inter,
            fontSize: 14.5,
            fontWeight: FontWeight.w600,
            color: ink.withValues(alpha: locked ? .5 : 1),
            fontFeatures: const [FontFeature.tabularFigures()],
          );
    final noteStyle = TextStyle(
      fontFamily: AppFonts.inter,
      fontSize: closed ? 13 : 14,
      height: closed ? 1.35 : 1.3,
      color: stage.noteColor ?? (closed ? AppColors.tertiary : ink.withValues(alpha: .6)),
    );

    final name = Text(stage.name, style: nameStyle);
    final count = Text(stage.count, style: countStyle);
    final note = stage.note == null ? null : Text(stage.note!, style: noteStyle);

    return Container(
      // 9 на тёмной плите, 11 на светлой бумаге закрытого дня — числа канвы.
      padding: EdgeInsets.symmetric(vertical: closed ? 11 : 9),
      decoration: BoxDecoration(border: Border(top: line, bottom: last ? line : BorderSide.none)),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Значок этапа — 20, и он же маркер состояния; кружков в строках больше нет.
          PlanStageMark(
            kind: stage.kind,
            state: switch (stage.state) {
              DayPlateStageState.done => PlanStageMarkState.done,
              DayPlateStageState.current => PlanStageMarkState.current,
              DayPlateStageState.locked => PlanStageMarkState.locked,
            },
            onDark: !closed,
          ),
          const SizedBox(width: 11),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [Expanded(child: name), const SizedBox(width: 10), count],
                ),
                if (note != null) ...[const SizedBox(height: 3), note],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// СТРОКА ВМЕСТО ЭТАПОВ (кадры 22-5a, 22-5c) — то, что стоит на плите, когда этапов ещё или уже
/// нет: «Собираем день 1 · около минуты · можно закрыть приложение» и «День не собрался».
///
/// Не шиммер-скелет: канва заменила пять фальшивых строк ОДНОЙ честной — срок назван словами и
/// уходить разрешено, а пять серых полосок обещали содержимое, которого пока нет.
class DayPlateNotice {
  const DayPlateNotice({required this.title, required this.sub, this.spinner = false});

  /// «Собираем день 1» / «День не собрался» — 15/600 paper.
  final String title;

  /// «около минуты · можно закрыть приложение» — 14 paper .62.
  final String sub;

  /// Кольцо 34 с оборотом 1.1 с (22-5a) вместо предупреждающего значка 30 (22-5c).
  final bool spinner;
}

/// СТРОКА-ИЗВЕЩЕНИЕ НА ПЛИТЕ (22-5a, 22-5c) — под волосяной линией на месте этапов.
class _NoticeRow extends StatelessWidget {
  const _NoticeRow({required this.notice});

  final DayPlateNotice notice;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.only(top: 14),
    decoration: BoxDecoration(
      border: Border(top: BorderSide(color: AppColors.paper.withValues(alpha: .14))),
    ),
    child: Row(
      crossAxisAlignment: notice.spinner ? CrossAxisAlignment.center : CrossAxisAlignment.start,
      children: [
        if (notice.spinner)
          const _PlateSpinner()
        else
          const SizedBox(
            width: 30,
            height: 30,
            child: Icon(LucideIcons.cloudOff, size: 30, color: AppColors.destructiveOnPlate),
          ),
        const SizedBox(width: 14),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                notice.title,
                style: const TextStyle(
                  fontFamily: AppFonts.inter,
                  fontSize: 15,
                  fontWeight: FontWeight.w600,
                  color: AppColors.paper,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                notice.sub,
                style: TextStyle(
                  fontFamily: AppFonts.inter,
                  fontSize: 14,
                  height: 1.4,
                  color: AppColors.paper.withValues(alpha: .62),
                ),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

/// Кольцо-спиннер 34 плиты: контур paper .20 и один светлый сектор, оборот 1.1 с.
class _PlateSpinner extends StatefulWidget {
  const _PlateSpinner();

  @override
  State<_PlateSpinner> createState() => _PlateSpinnerState();
}

class _PlateSpinnerState extends State<_PlateSpinner> with SingleTickerProviderStateMixin {
  late final AnimationController _turn = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1100),
  );

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    // Снимок не должен зависеть от кадра, на котором его сняли.
    if (MediaQuery.of(context).disableAnimations) {
      _turn.stop();
    } else if (!_turn.isAnimating) {
      _turn.repeat();
    }
  }

  @override
  void dispose() {
    _turn.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => SizedBox(
    width: 34,
    height: 34,
    child: RotationTransition(
      turns: _turn,
      child: CircularProgressIndicator(
        strokeWidth: 3,
        value: MediaQuery.of(context).disableAnimations ? .25 : null,
        backgroundColor: AppColors.paper.withValues(alpha: .20),
        valueColor: const AlwaysStoppedAnimation(AppColors.paper),
      ),
    ),
  );
}

/// Бумажная кнопка плиты — 52 / radius 16 / 17 / 700 (4и).
class _PaperButton extends StatelessWidget {
  const _PaperButton({required this.label, this.onTap, required this.enabled});

  final String label;
  final VoidCallback? onTap;
  final bool enabled;

  @override
  Widget build(BuildContext context) {
    final on = enabled && onTap != null;

    return Semantics(
      button: true,
      enabled: on,
      label: label,
      child: Material(
        color: on ? AppColors.paper : AppColors.paper.withValues(alpha: .10),
        borderRadius: BorderRadius.circular(16),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: on ? onTap : null,
          child: Container(
            height: 52,
            alignment: Alignment.center,
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  label,
                  style: TextStyle(
                    fontFamily: AppFonts.inter,
                    fontSize: 17,
                    fontWeight: FontWeight.w700,
                    color: on ? AppColors.ink : AppColors.paper.withValues(alpha: .45),
                  ),
                ),
                if (on) ...[
                  const SizedBox(width: 9),
                  const Icon(LucideIcons.arrowRight, size: 17, color: AppColors.ink),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }
}

// ── Второй размер компонента 4н: ШАПКА КАБИНЕТА ДНЯ (наряд DAY-UI, кадры 23-0a … 23-0e) ─────────
//
// ДВА КЛАССА, А НЕ `DayPlateSize.header`, как обещал докблок выше, — и вот почему. У свёрнутой
// плиты (21-2) и у шапки (23-0a) общий только материал: тёмный градиент, латунный лейбл «ДЕНЬ N»,
// антиква заголовка. Всё остальное в КАДРАХ разное: на табе строка этапа — индикатор 14 с именем и
// счётчиком, обложка кружком 52, подвала три состояния кнопки; в кабинете — фото дня во всю шапку
// под материалом (blur 24, saturate .8, скрим .88 → .95), блок «научишься» маркерами, полоска 6 px
// в трёх цветах вердиктов с колонкой счётчика 64 и подвал с тремя числами Literata 56. Один класс
// с флагом размера был бы `if (header)` в каждом методе и два набора полей, из которых половина
// всегда null, — это не «один компонент», а два, сшитых через ключ.
//
// Что действительно общее — вынесено: [StageBar] рисует полоску этапа и на плите, и в итоге этапа
// (23-9), и в шите (23-14).

/// ОДИН ЭТАП НА ПЛИТЕ (4н «Строки этапов»): имя, счётчик в колонке 64, полоска 6 px в трёх цветах
/// вердиктов; у текущего — вторая строка под именем.
class DayRoomStage {
  const DayRoomStage({
    required this.name,
    required this.done,
    required this.total,
    required this.passed,
    required this.hinted,
    required this.failed,
    this.current = false,
    this.locked = false,
    this.sub,
  });

  final String name;
  final int done;
  final int total;

  /// Как разложились пройденные карточки — доли полоски.
  final int passed;
  final int hinted;
  final int failed;
  final bool current;
  final bool locked;

  /// «начни отсюда · 8 новых слов» / «не закончен · 10 карточек · ≈ 4 мин» / «2 с подсказкой».
  final String? sub;

  /// Охра во второй строке — «N с подсказкой».
  bool get subOchre => false;
}

/// ОДНО ИЗ ТРЁХ ЧИСЕЛ ПЛИТЫ — «12 · КАРТОЧЕК». [unit] — знак после числа («%»), в меньшем кегле.
class DayRoomNumber {
  const DayRoomNumber({required this.value, required this.label, this.unit});

  final int value;
  final String label;
  final String? unit;
}

/// ПОДВАЛ ПЛИТЫ — три состояния (4н).
sealed class DayRoomFooter {
  const DayRoomFooter();
}

/// Не начат — бумажная кнопка «Начать».
class DayRoomStart extends DayRoomFooter {
  const DayRoomStart({required this.button});
  final String button;
}

/// Идёт — три числа Literata 26 с латунными лейблами и «Продолжить · осталось N».
class DayRoomProgress extends DayRoomFooter {
  const DayRoomProgress({required this.numbers, required this.button});
  final List<DayRoomNumber> numbers;
  final String button;
}

/// Закрыт — галка 30, «День N закрыт», три числа Literata 56; кнопки нет.
class DayRoomClosed extends DayRoomFooter {
  const DayRoomClosed({required this.title, required this.numbers, this.animate = false});
  final String title;
  final List<DayRoomNumber> numbers;

  /// Проиграть закрытие (23-0b → 23-0c): числа набираются 420, галка 220 с задержкой 120,
  /// маркеры «научишься» разом 220 с задержкой 260.
  final bool animate;
}

/// ПЛИТА ДНЯ (токен-лист 4н) — один компонент, два размера.
///
/// [DayRoomPlate.card] — карточка на табе: четыре угла radius 28, воздух вокруг, без фото, материал
/// 4и. [DayRoomPlate.header] — шапка кабинета: от верха экрана под статус-баром, от края до края,
/// скруглена только снизу 28; фото дня под материалом на всю шапку — blur 24, saturate .8, скрим
/// `rgba(51,42,35,.88)` → `rgba(41,34,25,.95)`; без снимка — сплошной материал.
class DayRoomPlate extends StatelessWidget {
  const DayRoomPlate.header({
    super.key,
    required this.label,
    required this.title,
    required this.meta,
    required this.goals,
    required this.stages,
    required this.footer,
    this.goalsDone = false,
    this.photo,
    this.onBack,
    this.backLabel,
    this.onTap,
  });

  final String label;
  final String title;
  final String meta;
  final List<String> goals;

  /// В закрытом дне маркеры «научишься» залиты шалфеем разом.
  final bool goalsDone;
  final List<DayRoomStage> stages;
  final DayRoomFooter footer;
  final ImageProvider? photo;
  final VoidCallback? onBack;
  final String? backLabel;
  final VoidCallback? onTap;

  static const _radius = 28.0;

  @override
  Widget build(BuildContext context) {
    final top = MediaQuery.paddingOf(context).top;
    const radius = BorderRadius.vertical(bottom: Radius.circular(_radius));

    final content = Padding(
      padding: EdgeInsets.fromLTRB(22, top + 6, 22, 20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        mainAxisSize: MainAxisSize.min,
        children: [
          if (onBack != null)
            Align(
              alignment: Alignment.centerLeft,
              child: Semantics(
                button: true,
                label: backLabel,
                child: InkResponse(
                  onTap: onBack,
                  radius: 22,
                  child: const SizedBox(
                    width: AppSpacing.minTap,
                    height: AppSpacing.minTap,
                    child: Icon(LucideIcons.arrowLeft, size: 22, color: AppColors.paper),
                  ),
                ),
              ),
            ),
          const SizedBox(height: 4),
          Text(label.toUpperCase(), style: AppTextDay.plateLabel),
          const SizedBox(height: 6),
          Text(title, style: AppTextDay.plateTitle),
          const SizedBox(height: 8),
          Text(meta, style: AppTextDay.plateMeta),
          if (goals.isNotEmpty) ...[
            const SizedBox(height: 16),
            _Goals(goals: goals, done: goalsDone, animateDelay: footer is DayRoomClosed && (footer as DayRoomClosed).animate),
          ],
          const SizedBox(height: 14),
          for (final stage in stages) _RoomStageRow(stage: stage),
          _Footer(footer: footer),
        ],
      ),
    );

    final plate = DecoratedBox(
      decoration: BoxDecoration(borderRadius: radius, boxShadow: AppShadows.plate),
      child: ClipRRect(
        borderRadius: radius,
        child: Stack(
          fit: StackFit.passthrough,
          children: [
            Positioned.fill(
              child: photo == null
                  ? const DecoratedBox(
                      decoration: BoxDecoration(
                        gradient: LinearGradient(
                          begin: Alignment.topCenter,
                          end: Alignment.bottomCenter,
                          colors: [AppColors.plateTop, AppColors.plateBottom],
                        ),
                      ),
                    )
                  : _BlurredPhoto(photo: photo!),
            ),
            if (photo != null)
              const Positioned.fill(
                child: DecoratedBox(
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.topCenter,
                      end: Alignment.bottomCenter,
                      colors: [AppColors.scrimTop, AppColors.scrimBottom],
                    ),
                  ),
                ),
              ),
            content,
          ],
        ),
      ),
    );

    if (onTap == null) return plate;

    return Material(
      color: Colors.transparent,
      child: InkWell(onTap: onTap, borderRadius: radius, child: plate),
    );
  }
}

/// Снимок под материалом: blur 24, saturate .8. Снимок даёт тон, не картинку — чуть шире плиты,
/// чтобы размытие не показывало края.
class _BlurredPhoto extends StatelessWidget {
  const _BlurredPhoto({required this.photo});
  final ImageProvider photo;

  // saturate(.8) — матрица насыщенности по Rec. 709.
  static const _saturate = ColorFilter.matrix(<double>[
    0.8298, 0.1428, 0.0274, 0, 0,
    0.0426, 0.9300, 0.0274, 0, 0,
    0.0426, 0.1428, 0.8146, 0, 0,
    0, 0, 0, 1, 0,
  ]);

  @override
  Widget build(BuildContext context) => ColoredBox(
    color: AppColors.plateUnderPhoto,
    child: ImageFiltered(
      imageFilter: ImageFilter.blur(sigmaX: 24, sigmaY: 24),
      child: ColorFiltered(
        colorFilter: _saturate,
        child: Transform.scale(
          scale: 1.15,
          child: Image(image: photo, fit: BoxFit.cover, errorBuilder: (_, _, _) => const SizedBox.shrink()),
        ),
      ),
    ),
  );
}

class _Goals extends StatefulWidget {
  const _Goals({required this.goals, required this.done, required this.animateDelay});
  final List<String> goals;
  final bool done;
  final bool animateDelay;

  @override
  State<_Goals> createState() => _GoalsState();
}

class _GoalsState extends State<_Goals> {
  late bool _filled = widget.done && !widget.animateDelay;

  @override
  void initState() {
    super.initState();
    if (widget.done && widget.animateDelay) {
      Future<void>.delayed(AppMotion.plateMarkersDelay, () {
        if (mounted) setState(() => _filled = true);
      });
    }
  }

  @override
  void didUpdateWidget(_Goals old) {
    super.didUpdateWidget(old);
    if (widget.done != old.done) {
      if (widget.done && widget.animateDelay) {
        Future<void>.delayed(AppMotion.plateMarkersDelay, () {
          if (mounted) setState(() => _filled = true);
        });
      } else {
        _filled = widget.done;
      }
    }
  }

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      for (var i = 0; i < widget.goals.length; i++) ...[
        if (i > 0) const SizedBox(height: 8),
        Row(
          children: [
            VerdictMarker(state: _filled ? MarkerState.passed : MarkerState.empty, size: 18, onPlate: true),
            const SizedBox(width: 10),
            Expanded(child: Text(widget.goals[i], style: AppTextDay.plateGoal)),
          ],
        ),
      ],
    ],
  );
}

class _RoomStageRow extends StatelessWidget {
  const _RoomStageRow({required this.stage});
  final DayRoomStage stage;

  @override
  Widget build(BuildContext context) {
    final opacity = stage.locked ? 0.5 : 1.0;
    final total = stage.total <= 0 ? 1 : stage.total;

    return Padding(
      padding: EdgeInsets.symmetric(vertical: stage.current ? 9 : 7),
      child: Opacity(
        opacity: opacity,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(stage.name, style: stage.current ? AppTextDay.plateStageCurrent : AppTextDay.plateStage),
                      if (stage.sub case final sub?) ...[
                        const SizedBox(height: 3),
                        Text(
                          sub,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: AppTextDay.plateStageSub,
                        ),
                      ],
                    ],
                  ),
                ),
                const SizedBox(width: 12),
                SizedBox(
                  width: 64,
                  child: Text(
                    // Пока сервер не раздал карточки (день не открыт), счётчика нет — «0 / 0» врал бы.
                    stage.total == 0 ? '' : '${stage.done} / ${stage.total}',
                    textAlign: TextAlign.right,
                    maxLines: 1,
                    softWrap: false,
                    style: AppTextDay.plateCount,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 6),
            StageBar(
              passed: stage.passed / total,
              hinted: stage.hinted / total,
              failed: stage.failed / total,
              height: 6,
              track: AppColors.paperTrack,
            ),
          ],
        ),
      ),
    );
  }
}

/// ПОЛОСКА ЭТАПА В ТРЁХ ЦВЕТАХ — шалфей · охра · терракота слева направо, остальное — подложка.
/// Одна и та же на плите (6 px), в итоге этапа (6 px) и в шите (8 px).
class StageBar extends StatelessWidget {
  const StageBar({
    super.key,
    required this.passed,
    required this.hinted,
    required this.failed,
    this.height = 6,
    this.track = AppColors.barTrack,
  });

  final double passed, hinted, failed;
  final double height;
  final Color track;

  @override
  Widget build(BuildContext context) {
    final reduce = MediaQuery.of(context).disableAnimations;
    final segments = [
      (passed, AppColors.verdictKnown),
      (hinted, AppColors.verdictUnsure),
      (failed, AppColors.destructiveText),
    ];

    return ClipRRect(
      borderRadius: BorderRadius.circular(height / 2),
      child: SizedBox(
        height: height,
        child: LayoutBuilder(
          builder: (context, c) => Stack(
            children: [
              Positioned.fill(child: ColoredBox(color: track)),
              Positioned.fill(
                child: Row(
                  children: [
                    for (final (share, color) in segments)
                      AnimatedContainer(
                        duration: reduce ? Duration.zero : AppMotion.goalBar,
                        curve: AppMotion.easeOut,
                        width: c.maxWidth * share.clamp(0.0, 1.0),
                        color: color,
                      ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Footer extends StatelessWidget {
  const _Footer({required this.footer});
  final DayRoomFooter footer;

  @override
  Widget build(BuildContext context) => switch (footer) {
    DayRoomStart(:final button) => Padding(
      padding: const EdgeInsets.only(top: 16),
      child: _RoomPaperButton(label: button),
    ),
    DayRoomProgress(:final numbers, :final button) => Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Container(
          margin: const EdgeInsets.only(top: 16),
          padding: const EdgeInsets.only(top: 14),
          decoration: const BoxDecoration(border: Border(top: BorderSide(color: AppColors.paperTrack))),
          child: Row(
            children: [
              for (final n in numbers)
                Expanded(child: _Number(number: n, big: false, animate: false)),
            ],
          ),
        ),
        const SizedBox(height: 16),
        _RoomPaperButton(label: button),
      ],
    ),
    DayRoomClosed(:final title, :final numbers, :final animate) => Container(
      margin: const EdgeInsets.only(top: 18),
      padding: const EdgeInsets.only(top: 16),
      decoration: const BoxDecoration(border: Border(top: BorderSide(color: AppColors.paperTrack))),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              _ClosedCheck(animate: animate),
              const SizedBox(width: 12),
              Expanded(child: Text(title, style: AppTextDay.plateClosedTitle)),
            ],
          ),
          const SizedBox(height: 20),
          Row(
            children: [
              for (final n in numbers) Expanded(child: _Number(number: n, big: true, animate: animate)),
            ],
          ),
        ],
      ),
    ),
  };
}

/// Бумажная кнопка на плите — 52, radius 16, 17/700 ink. Тап ловит вся плита ([DayRoomPlate.onTap])
/// или экран; кнопка — визуальный ответ на вопрос «что дальше».
class _RoomPaperButton extends StatelessWidget {
  const _RoomPaperButton({required this.label});
  final String label;

  @override
  Widget build(BuildContext context) => Container(
    height: 52,
    alignment: Alignment.center,
    decoration: BoxDecoration(color: AppColors.paper, borderRadius: BorderRadius.circular(16)),
    child: Text(label, style: AppTextDay.plateButton, maxLines: 1, overflow: TextOverflow.ellipsis),
  );
}

class _ClosedCheck extends StatelessWidget {
  const _ClosedCheck({required this.animate});
  final bool animate;

  @override
  Widget build(BuildContext context) {
    final reduce = MediaQuery.of(context).disableAnimations;
    final check = Container(
      width: 30,
      height: 30,
      decoration: const BoxDecoration(shape: BoxShape.circle, color: AppColors.paper),
      child: const Icon(LucideIcons.check, size: 16, color: AppColors.plateBottom, weight: 700),
    );
    if (!animate || reduce) return check;

    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0, end: 1),
      duration: AppMotion.plateCheck + AppMotion.plateCheckDelay,
      curve: const Interval(0.35, 1, curve: Curves.easeOut),
      builder: (_, t, child) => Transform.scale(scale: t, child: Opacity(opacity: t, child: child)),
      child: check,
    );
  }
}

/// Число с латунным лейблом. Большое (56) набирается 420 мс при закрытии дня.
class _Number extends StatelessWidget {
  const _Number({required this.number, required this.big, required this.animate});
  final DayRoomNumber number;
  final bool big;
  final bool animate;

  @override
  Widget build(BuildContext context) {
    final reduce = MediaQuery.of(context).disableAnimations;
    Widget value(int v) => Text.rich(
      TextSpan(
        style: big ? AppTextDay.plateNumberBig : AppTextDay.plateNumber,
        children: [
          TextSpan(text: '$v'),
          if (number.unit case final unit?)
            TextSpan(text: unit, style: big ? AppTextDay.plateNumberBigUnit : AppTextDay.plateNumberUnit),
        ],
      ),
      maxLines: 1,
      softWrap: false,
    );

    final numberWidget = animate && !reduce
        ? TweenAnimationBuilder<double>(
            tween: Tween(begin: 0, end: number.value.toDouble()),
            duration: AppMotion.plateNumbersCount,
            curve: AppMotion.easeOut,
            builder: (_, v, _) => value(v.round()),
          )
        : value(number.value);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        FittedBox(fit: BoxFit.scaleDown, alignment: Alignment.centerLeft, child: numberWidget),
        SizedBox(height: big ? 10 : 6),
        Text(number.label.toUpperCase(), style: AppTextDay.plateNumberLabel, maxLines: 1, softWrap: false),
      ],
    );
  }
}
