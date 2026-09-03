import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../data/api_client.dart';
import '../../../data/config.dart';
import '../../../data/plan_models.dart';
import '../../../data/providers.dart';
import '../plan_preview_screen.dart';
import 'entry_ui.dart';

/// СБОРКА ПЛАНА — кадры V4·05, 05б, 05в, 05г.
///
/// Waiting built as READING, not as a spinner: the goal is set large in the serif the plan uses for
/// its own titles, and three steps light up one after another. There is no percentage anywhere,
/// because there is nothing honest to say about how far along a model is.
///
/// ## The steps move on the SERVER'S answer, never on a timer
///
///   «Разбираю цель»      the draft — `POST /plans`, instant and free
///   «Подбираю реплики»   the skeleton — `POST /plans/{id}/outline`, ten to thirty seconds, paid
///   «Собираю слова»      the scheduler's answer, which arrives inside the same response
///
/// The third step is honest about being short: it is the server laying the scenes into days, and it
/// is done by the time the outline call returns. What it must never be is a fake stage that runs on
/// a timer while nothing happens — the frames' own note says «шаги переключаются по факту ответа
/// сервера, не по таймеру».
///
/// ## Under 900 ms this screen does not exist
///
/// «Если сервер отвечает быстрее 900 мс, экран не показываем вовсе» (записка). It paints nothing at
/// all until then, so a fast answer replaces it before a person can see it flash.
///
/// ## Two failures, two different screens
///
/// The FIRST failure changes one line and nothing else: same kicker, same steps, «пробую ещё», and
/// no buttons — the retry is the app's job and the learner is not asked to press anything. The
/// SECOND one hands over control: «Не собралось. Твои ответы сохранены», and two ways out.
///
/// A lost connection is neither: it is its own frame, because the answer is «подождём сеть» rather
/// than «попробуем ещё раз», and the difference matters to somebody on a train.
class PlanAssemblyScreen extends ConsumerStatefulWidget {
  const PlanAssemblyScreen({
    super.key,
    required this.goalText,
    required this.targetLang,
    required this.level,
    required this.eventDate,
    required this.minutesPerDay,
    required this.listened,
  });

  final String goalText, targetLang;
  final PlanLevel level;

  /// `Y-m-d`, or NULL — «Без даты».
  final String? eventDate;
  final int minutesPerDay;
  final List<ListenAnswer> listened;

  @override
  ConsumerState<PlanAssemblyScreen> createState() => _PlanAssemblyScreenState();
}

enum _Phase { goal, lines, words, offline, retrying, failed }

class _PlanAssemblyScreenState extends ConsumerState<PlanAssemblyScreen> {
  _Phase _phase = _Phase.goal;

  /// The draft, once it exists. Kept so a retry buys ONE more skeleton and not a second plan.
  String? _planId;

  /// How many times the skeleton has been asked for. The first failure retries itself; the second
  /// stops. Two, and not «until it works»: each attempt is a paid call.
  int _attempts = 0;

  /// Nothing is painted until this is true — see the class docblock.
  bool _visible = false;
  Timer? _revealTimer;

  @override
  void initState() {
    super.initState();
    _revealTimer = Timer(const Duration(milliseconds: 900), () {
      if (mounted) setState(() => _visible = true);
    });
    unawaited(_run());
  }

  @override
  void dispose() {
    _revealTimer?.cancel();
    super.dispose();
  }

  Future<void> _run() async {
    final api = ref.read(apiClientProvider);

    try {
      // The draft first, and only once: it is free, and a second one would leave an orphan.
      if (_planId == null) {
        final draft = await api.createPlan(
          goalText: widget.goalText,
          targetLang: widget.targetLang,
          level: widget.level.wire,
          eventDate: widget.eventDate,
          minutesPerDay: widget.minutesPerDay,
          listening: widget.listened,
        );
        if (!mounted) return;
        _planId = draft.id;
        setState(() => _phase = _phase == _Phase.retrying ? _Phase.retrying : _Phase.lines);
      }

      _attempts++;
      final outlined = await api.buildPlanOutline(_planId!);
      if (!mounted) return;

      // Everything is done — including the third step, which the same answer carries.
      setState(() => _phase = _Phase.words);
      await Navigator.of(context).pushReplacement(
        MaterialPageRoute(builder: (_) => PlanPreviewScreen(plan: outlined)),
      );
    } catch (e) {
      if (!mounted) return;

      if (isOffline(e)) {
        setState(() => _phase = _Phase.offline);

        return;
      }

      if (_attempts < 2) {
        // The first failure retries ITSELF. The screen says so in one line and grows no buttons:
        // a person watching a plan being written should not be handed the machine's job.
        setState(() => _phase = _Phase.retrying);
        unawaited(_run());

        return;
      }

      setState(() => _phase = _Phase.failed);
    }
  }

  void _retry() {
    AppHaptics.light();
    setState(() => _phase = _planId == null ? _Phase.goal : _Phase.lines);
    unawaited(_run());
  }

  /// «Написать нам» — a mailto and nothing more (see [AppConfig.supportEmail]).
  Future<void> _writeUs() async {
    AppHaptics.light();
    final l = AppLocalizations.of(context);
    final uri = Uri(
      scheme: 'mailto',
      path: AppConfig.supportEmail,
      queryParameters: {'subject': l.planBuildMailSubject},
    );
    await launchUrl(uri, mode: LaunchMode.externalApplication);
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark,
      // The system back gesture is not offered: the plan is being written, and half of it is
      // already paid for. The way out of a failure is on the failure's own screen.
      child: PopScope(
        canPop: _phase == _Phase.offline || _phase == _Phase.failed,
        child: Scaffold(
          backgroundColor: AppColors.paper,
          body: SafeArea(
            bottom: false,
            child: !_visible
                ? const SizedBox.shrink()
                : Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 24),
                    child: ListView(
                      padding: const EdgeInsets.only(top: 64, bottom: 44),
                      children: _content(l),
                    ),
                  ),
          ),
        ),
      ),
    );
  }

  List<Widget> _content(AppLocalizations l) {
    final failed = _phase == _Phase.failed;
    final offline = _phase == _Phase.offline;
    final broken = failed || offline;

    return [
      EntryOverline(
        switch (_phase) {
          _Phase.offline => l.planBuildOfflineKicker,
          _Phase.failed => l.planBuildFailedKicker,
          _Phase.retrying => l.planBuildRetryKicker,
          _ => l.planBuildKicker,
        },
        // The kicker stays BRASS on the first failure and goes grey on the second: the mark of the
        // plan is still there while the plan is still being written.
        color: broken ? AppColors.tertiary : AppColors.brassInk,
      ),
      const SizedBox(height: 16),
      Text(
        switch (_phase) {
          _Phase.offline => l.planBuildOfflineTitle,
          _Phase.failed => l.planBuildFailedTitle,
          _ => widget.goalText,
        },
        style: AppText.collectionNameScreen.copyWith(fontSize: 30, height: 1.2),
      ),
      if (_phase == _Phase.retrying || broken) ...[
        const SizedBox(height: 16),
        Text(
          switch (_phase) {
            _Phase.offline => l.planBuildOfflineBody,
            _Phase.failed => l.planBuildFailedBody,
            _ => l.planBuildRetryBody,
          },
          style: AppText.translation.copyWith(
            fontSize: 15,
            height: 1.6,
            color: AppColors.inkBody,
          ),
        ),
      ],
      SizedBox(height: _phase == _Phase.retrying || broken ? 44 : 52),
      // THE SAME LIST OF STEPS IN EVERY STATE — «сбой сохраняет тот же список шагов: видно, где
      // именно остановились».
      _step(l.planBuildStep1, index: 0),
      const SizedBox(height: 26),
      _step(l.planBuildStep2, index: 1),
      const SizedBox(height: 26),
      _step(l.planBuildStep3, index: 2),
      if (broken) ...[
        const SizedBox(height: 48),
        if (offline) ...[
          EntryCta(label: l.planBuildRetryButton, onPressed: _retry),
          const SizedBox(height: 10),
          // A BUTTON THAT CANNOT WORK, said out loud. There is no «tell me when it's ready» pipeline
          // in this build, and a button that pretended to arm one would be a promise nobody keeps.
          EntrySecondary(
            label: l.planBuildNotifyButton,
            outlined: false,
            enabled: false,
            footnote: l.planBuildNotifyUnavailable,
          ),
        ] else ...[
          EntryCta(
            label: l.planBuildBackToAnswers,
            // The answers are on the screen behind this one, untouched — the entry was never
            // disposed, so «вернуться к ответам» is literally a pop.
            onPressed: () => Navigator.of(context).maybePop(),
          ),
          const SizedBox(height: 10),
          EntrySecondary(label: l.planBuildWriteUs, outlined: false, onPressed: _writeUs),
        ],
      ] else ...[
        const SizedBox(height: 56),
        Container(
          padding: const EdgeInsets.only(top: 18),
          decoration: BoxDecoration(
            border: Border(top: BorderSide(color: AppColors.brassInk.withValues(alpha: 0.3))),
          ),
          child: Text(
            _phase == _Phase.retrying ? l.planBuildRetryFootnote : l.planBuildFootnote,
            style: AppText.translation.copyWith(
              fontSize: 13.5,
              height: 1.55,
              color: AppColors.secondary,
            ),
          ),
        ),
      ],
      const SizedBox(height: 60),
    ];
  }

  /// One step: done (ink disc with a brass tick), running (ring with a terracotta core, two points
  /// larger), stopped (a terracotta dash — this is where it stopped), or not started yet.
  Widget _step(String label, {required int index}) {
    final current = switch (_phase) {
      _Phase.goal => 0,
      _Phase.lines || _Phase.retrying || _Phase.offline || _Phase.failed => 1,
      _Phase.words => 3,
    };
    final done = index < current;
    final active = index == current;
    final stopped = active && (_phase == _Phase.offline || _phase == _Phase.failed);

    return Row(
      children: [
        SizedBox(
          width: 26,
          height: 26,
          child: done
              ? Container(
                  decoration: const BoxDecoration(shape: BoxShape.circle, color: AppColors.ink),
                  child: const Icon(Icons.check, size: 13, color: AppColors.brass),
                )
              : Container(
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    border: Border.all(
                      color: stopped
                          ? AppColors.destructiveText.withValues(alpha: 0.6)
                          : (active ? AppColors.ink : AppColors.ink.withValues(alpha: 0.18)),
                      width: active ? 1.5 : 1,
                    ),
                  ),
                  child: active
                      ? Center(
                          child: stopped
                              ? Container(
                                  width: 9,
                                  height: 1.5,
                                  color: AppColors.destructiveText,
                                )
                              : Container(
                                  width: 8,
                                  height: 8,
                                  decoration: const BoxDecoration(
                                    shape: BoxShape.circle,
                                    color: AppColors.destructiveText,
                                  ),
                                ),
                        )
                      : null,
                ),
        ),
        const SizedBox(width: 16),
        Expanded(
          // «Активный шаг увеличивается с 21 до 23 pt за 200 мс» — the size IS the signal, so there
          // is no spinner anywhere on this screen.
          child: AnimatedDefaultTextStyle(
            duration: const Duration(milliseconds: 200),
            curve: Curves.easeOut,
            style: AppText.collectionNameCard.copyWith(
              fontSize: active && !stopped ? 23 : 21,
              height: 1.25,
              color: done
                  ? AppColors.secondary
                  : (active ? AppColors.ink : AppColors.planInactive),
            ),
            child: Text(label),
          ),
        ),
      ],
    );
  }
}
