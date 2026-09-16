import 'dart:async';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../data/providers.dart';
import '../../data/qa_report.dart';
import 'build_stamp.dart';

/// «ЖАЛОБА» ОДНИМ ТАПОМ, С ЛЮБОГО ЭКРАНА — наряд DAY-GATE-1, Ч.0.5.
///
/// Живой прогон 07.09 стоил суток именно потому, что от поломки остались одни слова владельца:
/// какая карточка была на экране, какой это был день, что в этот момент делал микрофон — всё это
/// пришлось восстанавливать догадками, и часть догадок была неверной. Кнопка превращает «тут что-то
/// не так» в файл на диске сервера за одно касание, ПОКА ЭКРАН ЕЩЁ ТОТ САМЫЙ.
///
/// Наружу не уходит ничего: ни Notion, ни почты. Файл ложится рядом с API
/// (`backend2/storage/qa-reports/`), и что с ним делать дальше — решение владельца.
///
/// ВИДНА ТОЛЬКО QA-АККАУНТУ, и решает это СЕРВЕР ({@see AppUser.qaTools}: `is_qa` И среда не
/// production) — то же правило, что у входа без пароля, подстановки транскрипта и сдвига часов.
/// Своей проверки клиент не держит: второе правило про ту же дверь однажды разошлось бы с первым.
class QaReportOverlay extends ConsumerStatefulWidget {
  const QaReportOverlay({super.key, required this.child});

  final Widget child;

  @override
  ConsumerState<QaReportOverlay> createState() => _QaReportOverlayState();
}

class _QaReportOverlayState extends ConsumerState<QaReportOverlay> {
  /// Граница перерисовки ВОКРУГ ВСЕГО ПРИЛОЖЕНИЯ — снимок делается с неё.
  ///
  /// Именно вокруг всего, а не вокруг экрана: «жалоба» нажимается там, где что-то не так, и заранее
  /// известного списка таких мест нет. Пустая `RepaintBoundary` в корне не стоит ничего, пока
  /// снимок не запрошен.
  final _boundary = GlobalKey();

  bool _sending = false;

  /// Mounted [QaReportHidden]s — while at least one is mounted, there is no button.
  final Set<Object> _hiders = {};

  void _hide(Object token) => _afterFrame(() => _hiders.add(token));

  void _unhide(Object token) => _afterFrame(() => _hiders.remove(token));

  /// The set changes after the frame: a screen mounts and unmounts in the middle of a build, and an ancestor
  /// must not be rebuilt during a build.
  void _afterFrame(VoidCallback change) {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) setState(change);
    });
  }

  Future<void> _send() async {
    if (_sending) return;
    setState(() => _sending = true);
    final l = AppLocalizations.of(context);
    final messenger = ScaffoldMessenger.maybeOf(context);
    try {
      final shot = await _screenshot();
      final report = buildQaReport(
        context: ref.read(qaContextProvider),
        speech: ref.read(speechDiagnosticsProvider),
        // Уже прочитанный SHA сервера, если он есть; ходить за ним ещё раз ради отчёта незачем.
        backendCommit: ref.read(backendCommitProvider).value ?? '',
        // ПОЧЕМУ СНИМКА НЕТ — в самом отчёте. Молчаливо отсутствующий снимок неотличим от снимка,
        // который не понадобился, и разбирать по такому отчёту нечего.
        note: shot.error,
      );
      final id = await ref.read(apiClientProvider).sendQaReport(
        report: report,
        screenshotPng: shot.png,
      );
      messenger?.showSnackBar(SnackBar(content: Text(l.qaReportSent(id))));
    } catch (_) {
      // Отчёт не ушёл — говорим об этом и не пытаемся повторить сами: повтор в фоне превратил бы
      // одну «жалобу» в несколько файлов про разные моменты.
      messenger?.showSnackBar(SnackBar(content: Text(l.qaReportFailed)));
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  /// PNG всего, что сейчас на экране, или null.
  ///
  /// Снимок необязателен НАМЕРЕННО: он единственная часть отчёта, которая может не получиться
  /// (граница ещё не отрисована, устройство отказало в буфере), и потерять из-за неё описание
  /// состояния было бы обидно ровно в тот момент, ради которого всё заведено.
  Future<({List<int>? png, String? error})> _screenshot() async {
    try {
      // СНАЧАЛА ДАЁМ КАДРУ ДОРИСОВАТЬСЯ. Тап уже перекрасил кнопку и запустил чернильную волну, то
      // есть пометил границу перерисовки грязной, а `toImage` на грязной границе падает
      // (`!debugNeedsPaint`) — что и случилось на первом живом нажатии: отчёт пришёл без снимка и
      // без причины. Одно ожидание конца кадра стоит миллисекунды и снимает весь класс.
      await WidgetsBinding.instance.endOfFrame;
      if (!mounted) return (png: null, error: 'screenshot: gone');
      final object = _boundary.currentContext?.findRenderObject();
      if (object is! RenderRepaintBoundary) {
        return (png: null, error: 'screenshot: no boundary');
      }
      final image = await object.toImage(pixelRatio: 2);
      final bytes = await image.toByteData(format: ui.ImageByteFormat.png);
      image.dispose();

      return (png: bytes?.buffer.asUint8List(), error: bytes == null ? 'screenshot: no bytes' : null);
    } catch (e) {
      return (png: null, error: 'screenshot failed: $e');
    }
  }

  @override
  Widget build(BuildContext context) {
    final qa = ref.watch(authControllerProvider).value?.qaTools ?? false;
    final l = AppLocalizations.of(context);

    return RepaintBoundary(
      key: _boundary,
      child: Stack(
        children: [
          widget.child,
          // Правый край и середина по высоте: снизу тянется плавающая полоса вкладок, сверху —
          // шапки экранов, а середина правого края свободна на всех экранах серии.
          if (qa && _hiders.isEmpty)
            Positioned(
              right: 0,
              top: MediaQuery.sizeOf(context).height * 0.42,
              child: SafeArea(
                child: Semantics(
                  label: l.qaReportButton,
                  button: true,
                  child: Material(
                    color: AppColors.destructiveText.withValues(alpha: _sending ? .35 : .85),
                    borderRadius: const BorderRadius.horizontal(left: Radius.circular(14)),
                    clipBehavior: Clip.antiAlias,
                    child: InkWell(
                      onTap: _sending ? null : () => unawaited(_send()),
                      child: const Padding(
                        padding: EdgeInsets.fromLTRB(9, 10, 7, 10),
                        child: Icon(LucideIcons.flag, size: 16, color: AppColors.paper),
                      ),
                    ),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

/// A SCREEN WITHOUT THE REPORT BUTTON: while this widget is mounted there is no button — neither on the screen
/// nor on its sheets. The day session works this way (work order SESSION-1b, screenshot fixes): the session
/// canvas does not know the button, and its palette has no red. The app's other screens show the button as
/// before.
class QaReportHidden extends StatefulWidget {
  const QaReportHidden({super.key, required this.child});

  final Widget child;

  @override
  State<QaReportHidden> createState() => _QaReportHiddenState();
}

class _QaReportHiddenState extends State<QaReportHidden> {
  /// The report overlay above the navigator; captured up front — ancestors can no longer be looked up in
  /// `dispose`.
  _QaReportOverlayState? _overlay;

  @override
  void initState() {
    super.initState();
    _overlay = context.findAncestorStateOfType<_QaReportOverlayState>();
    _overlay?._hide(this);
  }

  @override
  void dispose() {
    _overlay?._unhide(this);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => widget.child;
}
