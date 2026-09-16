import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';

import '../../../../data/local/cached_image_provider.dart';
import '../../../../data/plan/plan_models.dart';
import '../../../../data/plan/session/session_models.dart';
import '../../../../ui/plan_marks.dart';

/// ОБЩИЕ КИРПИЧИ СЕССИИ ДНЯ (канва `session-canvas.dc.html`, серии 30–32): лист, бровь, задание, волна,
/// «прослушать», кнопка действия и её док, фото, строка каркаса с окном, реакции «верно / неверно».
/// Один виджет на тип — карточки собираются из них и своих стилей не заводят.

/// Поле экрана по бокам — 24.
const double kSessionGutter = 24;

/// Пустое окно каркаса фразы — 96 × 30 (кадры 32-x).
const Size kSessionEmptyWindow = Size(96, 30);

/// Тень листа материала — `0 4px 16px rgba(46,38,32,.08)`.
const List<BoxShadow> kSessionSheetShadow = [
  BoxShadow(color: AppColors.sessionSheetShadow, blurRadius: 16, offset: Offset(0, 4)),
];

/// ЛИСТ — `#F6F3EC`, скругление 16, тень; поле задаёт вызывающий.
class SessionSheet extends StatelessWidget {
  const SessionSheet({super.key, required this.child, this.padding = const EdgeInsets.all(20), this.color = AppColors.paper});

  final Widget child;
  final EdgeInsetsGeometry padding;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    clipBehavior: Clip.antiAlias,
    decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(16), boxShadow: kSessionSheetShadow),
    padding: padding,
    child: child,
  );
}

/// БРОВЬ ЛИСТА — 11/600 капителью.
class SessionEyebrow extends StatelessWidget {
  const SessionEyebrow(this.text, {super.key, this.trailing});

  final String text;

  /// Справа — «вернётся завтра» (30-9d).
  final String? trailing;

  @override
  Widget build(BuildContext context) => Row(
    crossAxisAlignment: CrossAxisAlignment.baseline,
    textBaseline: TextBaseline.alphabetic,
    children: [
      Expanded(child: Text(text.toUpperCase(), style: AppTextSession.eyebrow)),
      if (trailing != null) Text(trailing!, style: AppTextSession.meta),
    ],
  );
}

/// ЗАДАНИЕ НАД ЛИСТОМ — 17/600 и, если есть, голос спутника 13 под ним.
class SessionTask extends StatelessWidget {
  const SessionTask(this.title, {super.key, this.companion});

  final String title;
  final String? companion;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    mainAxisSize: MainAxisSize.min,
    children: [
      Text(title, style: AppTextSession.task),
      if (companion != null) ...[
        const SizedBox(height: 4),
        Text(companion!, style: AppTextSession.meta),
      ],
    ],
  );
}

/// ВОЛНА ЛАТУНЬЮ — столбики живые только пока [playing], потом статичные (таблица «Тайминг · сессия»).
class SessionWave extends StatefulWidget {
  const SessionWave({super.key, required this.heights, this.barWidth = 3, this.width, this.playing = false, this.color = AppColors.brassInk});

  /// Пять столбиков кнопки «прослушать» и микрофона (10/18/24/14/20).
  static const List<double> five = [10, 18, 24, 14, 20];

  /// Двадцать столбиков плашки «На слух» 80 × 24 (31-5).
  static const List<double> twenty = [10, 16, 22, 12, 24, 18, 10, 20, 14, 22, 16, 11, 24, 18, 12, 20, 15, 10, 22, 16];

  final List<double> heights;
  final double barWidth;

  /// Ширина всей волны (столбики по ширине `space-between`); null — вплотную с зазором 3.
  final double? width;
  final bool playing;
  final Color color;

  @override
  State<SessionWave> createState() => _SessionWaveState();
}

class _SessionWaveState extends State<SessionWave> with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(vsync: this, duration: const Duration(milliseconds: 1200));

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _sync();
  }

  @override
  void didUpdateWidget(SessionWave old) {
    super.didUpdateWidget(old);
    _sync();
  }

  void _sync() {
    final animate = widget.playing && !(MediaQuery.maybeDisableAnimationsOf(context) ?? false);
    if (animate && !_c.isAnimating) {
      unawaited(_c.repeat());
    } else if (!animate && _c.isAnimating) {
      _c.stop();
      _c.value = 0;
    }
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final maxH = widget.heights.fold<double>(0, math.max);
    final reduce = MediaQuery.maybeDisableAnimationsOf(context) ?? false;
    return SizedBox(
      width: widget.width,
      height: maxH,
      child: AnimatedBuilder(
        animation: _c,
        builder: (_, _) {
          final bars = <Widget>[
            for (var i = 0; i < widget.heights.length; i++)
              Container(
                width: widget.barWidth,
                height: widget.heights[i] * (widget.playing && !reduce ? _scale(i) : 1),
                decoration: BoxDecoration(color: widget.color, borderRadius: BorderRadius.circular(2)),
              ),
          ];
          return Row(
            mainAxisSize: widget.width == null ? MainAxisSize.min : MainAxisSize.max,
            mainAxisAlignment: widget.width == null ? MainAxisAlignment.center : MainAxisAlignment.spaceBetween,
            crossAxisAlignment: CrossAxisAlignment.center,
            children: widget.width == null
                ? [for (var i = 0; i < bars.length; i++) ...[if (i > 0) const SizedBox(width: 3), bars[i]]]
                : bars,
          );
        },
      ),
    );
  }

  /// `om-wave`: масштаб столбика .35 ↔ 1 со своим периодом и сдвигом.
  double _scale(int i) {
    final period = 0.42 + 0.11 * (i % 6);
    final t = (_c.value * 1.2 / period + i * 0.07) % 1.0;
    return 0.35 + 0.65 * (0.5 - 0.5 * math.cos(2 * math.pi * t));
  }
}

/// «ПРОСЛУШАТЬ» — круг 44 (у листа) или 28 (у варианта); пока звучит — контур латунью и волна.
class SessionListenButton extends StatelessWidget {
  const SessionListenButton({super.key, required this.onTap, required this.label, this.size = 44, this.playing = false});

  final VoidCallback? onTap;
  final String label;
  final double size;
  final bool playing;

  @override
  Widget build(BuildContext context) {
    final big = size >= 40;
    return Semantics(
      button: true,
      label: label,
      child: GestureDetector(
        behavior: HitTestBehavior.opaque,
        onTap: onTap,
        child: SizedBox(
          width: math.max(size, 44),
          height: math.max(size, 44),
          child: Center(
            child: Container(
              width: size,
              height: size,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: AppColors.paper,
                border: Border.all(color: playing ? AppColors.brassInk : AppColors.markerOutline, width: 1.5),
              ),
              child: playing
                  ? SessionWave(
                      heights: big ? const [8, 14, 18, 12, 16] : const [4, 7, 9, 6, 8],
                      barWidth: big ? 2.5 : 1.5,
                      playing: true,
                    )
                  : Icon(LucideIcons.volume2, size: big ? 20 : 13, color: AppColors.ink),
            ),
          ),
        ),
      ),
    );
  }
}

/// КНОПКА ДЕЙСТВИЯ 56 — `#1B1A18`, скругление 18, 17/600 бумагой; неактивная — подложка 8 %.
class SessionDockButton extends StatelessWidget {
  const SessionDockButton({super.key, required this.label, required this.onTap, this.enabled = true, this.busy = false});

  final String label;
  final VoidCallback? onTap;
  final bool enabled;

  /// Ответ ещё уходит на сервер — кнопка ждёт.
  final bool busy;

  @override
  Widget build(BuildContext context) {
    final on = enabled && onTap != null && !busy;
    return Semantics(
      button: true,
      enabled: on,
      label: label,
      child: GestureDetector(
        onTap: on
            ? () {
                AppHaptics.light();
                onTap!();
              }
            : null,
        child: AnimatedContainer(
          duration: AppMotion.sessionChipSelect,
          height: 56,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: enabled ? AppColors.windowInk : AppColors.sessionSheetShadow,
            borderRadius: BorderRadius.circular(18),
          ),
          child: busy
              ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: AppColors.paper))
              : Text(label, style: enabled ? AppTextSession.dock : AppTextSession.dock.copyWith(color: AppColors.tertiary)),
        ),
      ),
    );
  }
}

/// ДОК ВНИЗУ ЭКРАНА — бумажный градиент поверх ленты и поле 14 / 24 / 24 (+ безопасная зона).
class SessionDock extends StatelessWidget {
  const SessionDock({super.key, required this.child, this.fadeStop = 0.34});

  final Widget child;

  /// Где градиент становится сплошным: 34 % у кнопки, 22 % у вариантов, 30 % у микрофона.
  final double fadeStop;

  /// Верхний отступ дока — прозрачный край, под который заходит поле карточки.
  static const double topInset = 14;

  @override
  Widget build(BuildContext context) => Container(
    decoration: BoxDecoration(
      gradient: LinearGradient(
        begin: Alignment.topCenter,
        end: Alignment.bottomCenter,
        colors: const [AppColors.groundClear, AppColors.ground],
        stops: [0, fadeStop],
      ),
    ),
    padding: EdgeInsets.fromLTRB(kSessionGutter, topInset, kSessionGutter, math.max(24, MediaQuery.paddingOf(context).bottom + 8)),
    child: child,
  );
}

/// ФОТО СЛОВА — тон, пока фото в пути; нет фото — плашка `#E3DCCF`.
class SessionPhoto extends StatelessWidget {
  const SessionPhoto({super.key, required this.image, this.height, this.radius = 12});

  final CardImage? image;
  final double? height;
  final double radius;

  @override
  Widget build(BuildContext context) {
    final url = image?.url;
    final tone = AppColors.wireTone(image?.tone) ?? AppColors.photoPlaceholder;
    return ClipRRect(
      borderRadius: BorderRadius.circular(radius),
      child: SizedBox(
        height: height,
        width: double.infinity,
        child: ColoredBox(
          color: url == null ? AppColors.photoPlaceholder : tone,
          child: url == null
              ? null
              : Image(
                  image: CachedNetworkImage(url),
                  fit: BoxFit.cover,
                  frameBuilder: (_, child, frame, sync) => sync || frame != null ? child : const SizedBox.expand(),
                  errorBuilder: (_, _, _) => const SizedBox.expand(),
                ),
        ),
      ),
    );
  }
}

/// Что стоит в окне каркаса.
enum SlotLook {
  /// Пустое окно — латунный контур, подложка 8 %.
  empty,

  /// Наполнение чернилами в латунном окне.
  filled,

  /// Задание на родном курсивом (32-7).
  task,

  /// Зачтено — шалфей.
  sage,

  /// Ошибка — контур чернил (32-2c).
  wrong,
}

/// СТРОКА КАРКАСА С ОКНОМ — текст до окна, окно ([SlotLook]) и текст после; окно не режется и переносится
/// целиком. [frameColor] — цвет самого каркаса (шалфей, когда каркас зачтён раздельно с окном).
class SessionFrameText extends StatelessWidget {
  const SessionFrameText({
    super.key,
    required this.before,
    required this.after,
    required this.style,
    this.window = true,
    this.slot,
    this.look = SlotLook.empty,
    this.frameColor,
    this.underline,
    this.caret = false,
    this.textAlign = TextAlign.start,
    this.emptyWindow = kSessionEmptyWindow,
  });

  /// Каркас карточки: окно на месте `___`; у каркаса без окна — строка целиком, без окна.
  factory SessionFrameText.frame(
    CardFrame frame, {
    required TextStyle style,
    String? slot,
    SlotLook look = SlotLook.empty,
    Color? frameColor,
    bool caret = false,
  }) {
    final parts = frame.parts;
    return SessionFrameText(
      before: parts.before,
      after: parts.after,
      style: style,
      window: frame.hasSlot,
      slot: slot,
      look: look,
      frameColor: frameColor,
      caret: caret,
    );
  }

  /// Строка без окна — [before] целиком (ключ можно подчеркнуть).
  const SessionFrameText.plain(this.before, {super.key, required this.style, this.underline, this.frameColor, this.textAlign = TextAlign.start})
    : after = '',
      window = false,
      slot = null,
      look = SlotLook.empty,
      caret = false,
      emptyWindow = kSessionEmptyWindow;

  final String before;
  final String after;
  final TextStyle style;

  /// Рисовать ли окно между [before] и [after].
  final bool window;

  /// Текст в окне; null — пустое окно.
  final String? slot;
  final SlotLook look;
  final Color? frameColor;

  /// Подчеркнуть латунью кусок [before] (ключ фразы, 32-6).
  final TextRange? underline;

  /// Курсор в окне — окно ещё заполняется голосом (32-9b).
  final bool caret;
  final TextAlign textAlign;

  /// Размер пустого окна: 96 × 30 у каркасов фраз, 56 × 28 у слова в реплике (31-7).
  final Size emptyWindow;

  @override
  Widget build(BuildContext context) {
    final base = style.copyWith(color: frameColor ?? style.color);
    final spans = <InlineSpan>[..._withUnderline(before, base)];
    var rest = after;
    if (window) {
      // Наполненное окно стоит на строке текста (базовая линия слова в окне — базовая линия фразы),
      // пустое и с курсором — посередине строки (у курсора нет базовой линии, и сухой расчёт высоты
      // абзаца на ней падает).
      final onLine = slot != null && !caret;
      // Знак сразу после окна едет в том же заместителе: иначе строка переносится между окном и точкой.
      final mark = _closingMark.firstMatch(after)?.group(0) ?? '';
      rest = after.substring(mark.length);
      final box = _window(context);
      spans.add(WidgetSpan(
        alignment: onLine ? PlaceholderAlignment.baseline : PlaceholderAlignment.middle,
        baseline: onLine ? TextBaseline.alphabetic : null,
        child: mark.isEmpty
            ? box
            : Row(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: onLine ? CrossAxisAlignment.baseline : CrossAxisAlignment.center,
                textBaseline: onLine ? TextBaseline.alphabetic : null,
                // Окно сжимается и переносит свой текст, знак остаётся рядом.
                children: [Flexible(child: box), Text(mark, style: base)],
              ),
      ));
    }
    if (rest.isNotEmpty) spans.add(TextSpan(text: rest, style: base));
    return Text.rich(TextSpan(children: spans), textAlign: textAlign);
  }

  static final RegExp _closingMark = RegExp(r'^[.,!?;:…)»]+');

  List<InlineSpan> _withUnderline(String text, TextStyle base) {
    final u = underline;
    if (u == null || !u.isValid || u.end > text.length || u.start >= u.end) return [TextSpan(text: text, style: base)];
    return [
      TextSpan(text: u.textBefore(text), style: base),
      TextSpan(
        text: u.textInside(text),
        style: base.copyWith(decoration: TextDecoration.underline, decorationColor: AppColors.brassInk, decorationThickness: 1.5),
      ),
      TextSpan(text: u.textAfter(text), style: base),
    ];
  }

  Widget _window(BuildContext context) {
    final (border, fill, textColor) = switch (look) {
      SlotLook.empty || SlotLook.filled || SlotLook.task => (AppColors.brassInk, AppColors.sessionWindowFill, AppColors.ink),
      SlotLook.sage => (AppColors.verdictKnown, AppColors.sessionSageWash, AppColors.verdictKnown),
      SlotLook.wrong => (AppColors.ink, Colors.transparent, AppColors.ink),
    };
    final value = slot;
    final fontSize = style.fontSize ?? 22;
    // Пустое окно — точного размера: контейнер без ребёнка иначе растягивается на всю строку.
    return AnimatedContainer(
      duration: AppMotion.sessionSlotSage,
      margin: const EdgeInsets.symmetric(horizontal: 2),
      padding: EdgeInsets.symmetric(horizontal: value == null && !caret ? 0 : 10),
      width: value == null ? emptyWindow.width : null,
      height: value == null ? emptyWindow.height : null,
      decoration: BoxDecoration(
        color: fill,
        borderRadius: BorderRadius.circular(6),
        border: Border.all(color: border, width: 1.5),
      ),
      child: value == null
          ? (caret ? const _Caret() : null)
          : Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Flexible(
                  child: Text(
                    value,
                    style: style.copyWith(
                      color: textColor,
                      fontStyle: look == SlotLook.task ? FontStyle.italic : null,
                      height: 34 / fontSize,
                    ),
                  ),
                ),
                if (caret) const _Caret(),
              ],
            ),
    );
  }
}

/// Курсор в окне, которое заполняется голосом.
class _Caret extends StatelessWidget {
  const _Caret();

  @override
  Widget build(BuildContext context) => const Padding(padding: EdgeInsets.only(left: 2, top: 16), child: SessionCaret());
}

/// КУРСОР-ПОДЧЕРК — [width] × 2 чернилами, мигает раз в секунду (`om-caret`, steps(1)); под «уменьшением
/// движения» стоит.
class SessionCaret extends StatefulWidget {
  const SessionCaret({super.key, this.width = 24});

  final double width;

  @override
  State<SessionCaret> createState() => _SessionCaretState();
}

class _SessionCaretState extends State<SessionCaret> {
  Timer? _t;
  bool _on = true;

  @override
  void initState() {
    super.initState();
    _t = Timer.periodic(AppMotion.sessionCaretPeriod ~/ 2, (_) {
      if (!mounted) return;
      if (MediaQuery.maybeDisableAnimationsOf(context) ?? false) return;
      setState(() => _on = !_on);
    });
  }

  @override
  void dispose() {
    _t?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) =>
      Opacity(opacity: _on ? 1 : 0, child: Container(width: widget.width, height: 2, color: AppColors.ink));
}

/// ПОКАЧИВАНИЕ «НЕВЕРНО» — ±4 px, 120 мс × 2, когда [trigger] меняется на новое значение.
class SessionShake extends StatefulWidget {
  const SessionShake({super.key, required this.trigger, required this.child});

  /// Новое значение — покачать один раз (0 — не качать).
  final int trigger;
  final Widget child;

  @override
  State<SessionShake> createState() => _SessionShakeState();
}

class _SessionShakeState extends State<SessionShake> with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(vsync: this, duration: AppMotion.sessionShake * 2);

  @override
  void didUpdateWidget(SessionShake old) {
    super.didUpdateWidget(old);
    if (widget.trigger != old.trigger && widget.trigger != 0 && !(MediaQuery.maybeDisableAnimationsOf(context) ?? false)) {
      unawaited(_c.forward(from: 0));
    }
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AnimatedBuilder(
    animation: _c,
    builder: (_, child) {
      // 0 → −4 → +4 → 0 дважды за два такта по 120 мс.
      final t = _c.value * 2 % 1;
      final dx = _c.isAnimating ? AppMotion.sessionShakeOffset * math.sin(t * 2 * math.pi) * -1 : 0.0;
      return Transform.translate(offset: Offset(dx, 0), child: child);
    },
    child: widget.child,
  );
}

/// ГАЛКА «ВЕРНО» — круг 28 шалфеем с галкой 17 бумагой, появляется масштабом 0→1 (180 мс, ease-out-back).
class SessionCheckBadge extends StatelessWidget {
  const SessionCheckBadge({super.key, this.size = 28});

  final double size;

  @override
  Widget build(BuildContext context) {
    final badge = Container(
      width: size,
      height: size,
      alignment: Alignment.center,
      decoration: const BoxDecoration(shape: BoxShape.circle, color: AppColors.verdictKnown),
      child: Icon(LucideIcons.check, size: size * 0.6, color: AppColors.paper),
    );
    if (MediaQuery.maybeDisableAnimationsOf(context) ?? false) return badge;
    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0, end: 1),
      duration: AppMotion.sessionCheckPop,
      curve: AppMotion.sessionEaseOutBack,
      builder: (_, s, child) => Transform.scale(scale: s, child: child),
      child: badge,
    );
  }
}

/// Точка «вернётся завтра» 8 латунью — вырастает (180 мс, ease-out-back).
class SessionReturnDot extends StatelessWidget {
  const SessionReturnDot({super.key});

  @override
  Widget build(BuildContext context) {
    const dot = SizedBox(
      width: 8,
      height: 8,
      child: DecoratedBox(decoration: BoxDecoration(shape: BoxShape.circle, color: AppColors.brassInk)),
    );
    if (MediaQuery.maybeDisableAnimationsOf(context) ?? false) return dot;
    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0, end: 1),
      duration: AppMotion.sessionReturnDot,
      curve: AppMotion.sessionEaseOutBack,
      builder: (_, s, child) => Transform.scale(scale: s, child: child),
      child: dot,
    );
  }
}

/// Значок этапа 20 одним цветом.
Widget sessionStageGlyph(PlanStage stage, Color color) => PlanStageGlyph(kind: sessionStageMark(stage), color: color);

/// Значок этапа по имени этапа.
PlanStageMarkKind sessionStageMark(PlanStage stage) => switch (stage) {
  PlanStage.words => PlanStageMarkKind.words,
  PlanStage.phrases => PlanStageMarkKind.phrases,
  PlanStage.dialogue => PlanStageMarkKind.dialogue,
  PlanStage.listen => PlanStageMarkKind.listen,
  PlanStage.speak || PlanStage.unknown => PlanStageMarkKind.speak,
};
