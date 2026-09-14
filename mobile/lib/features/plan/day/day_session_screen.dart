import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import '../../../data/api_client.dart';
import '../../../data/app_settings.dart';
import '../../../data/languages.dart' show sttLocaleFor;
import '../../../data/local/cached_image_provider.dart';
import '../../../data/plan/day_rules.dart';
import '../../../data/plan/day_session.dart';
import '../../../data/plan/plan_models.dart';
import '../../../data/plan/day_contract.dart';
import '../../../data/providers.dart';
import 'cards/card_context.dart';
import 'cards/dialogue_read_card.dart';
import 'cards/listen_cards.dart';
import 'cards/phrase_cards.dart';
import 'cards/speak_card.dart';
import 'cards/word_cards.dart';
import 'day_card_frame.dart';
import 'day_texts.dart';
import 'day_voice.dart';
import 'speech_attempt.dart';

/// Чем кончилась сессия дня для кабинета.
enum DaySessionExit {
  /// Крестик — «Продолжить позже».
  left,

  /// Последний этап закрыт — кабинет в закрытом состоянии с анимацией 23-0b → 23-0c.
  dayClosed,
}

/// СЕССИЯ ДНЯ (кадры 23-1 … 23-13) — один каркас на все карточки: шапка «Слова · 12 из 32» с
/// пятью сегментами-этапами, вход в этап 23-2a/b, карточки, итог этапа 23-9, выход 23-11.
///
/// Открывает день (`POST …/open` — сервер раздаёт карточки на первый вызов), держит [DaySession]
/// и [DayVoice] на всю посадку; уходит в кабинет по крестику или по закрытию последнего этапа.
class DaySessionScreen extends ConsumerStatefulWidget {
  const DaySessionScreen({super.key, required this.plan, required this.room, this.rehearsal = false});

  final Plan plan;
  final PlanDayRoom room;

  /// «Ещё раз» пройденного дня: «Говорю сам» по уже розданным карточкам (`GET …/cards`), без записи
  /// ответов и без закрытий ([DaySession.rehearsal]).
  final bool rehearsal;

  @override
  ConsumerState<DaySessionScreen> createState() => _DaySessionScreenState();
}

class _DaySessionScreenState extends ConsumerState<DaySessionScreen> {
  DaySession? _session;
  DayVoice? _voice;
  Object? _openError;
  String? _lessonStatus;
  bool _retrying = false;
  Timer? _poll;

  /// Ключ карточки для [AnimatedSwitcher] — новая карточка = новый слайд. Считается по id, а не
  /// по каждому уведомлению сессии: иначе ответ на карточку пересоздавал бы её и терял вердикт.
  int _slide = 0;
  String? _slideCardId;

  @override
  void initState() {
    super.initState();
    unawaited(_open());
  }

  @override
  void dispose() {
    _poll?.cancel();
    _session?.removeListener(_onSession);
    _session?.dispose();
    unawaited(_voice?.release());
    super.dispose();
  }

  Future<void> _open() async {
    final api = ref.read(apiClientProvider);
    try {
      final rehearsal = widget.rehearsal;
      final dealt = rehearsal
          ? await api.dayCards(widget.plan.id, widget.room.day.number)
          : await api.openDay(widget.plan.id, widget.room.day.number);
      final cards = rehearsal ? DaySession.rehearsalOf(dealt.cards) : dealt.cards;
      if (!mounted) return;
      final voice = DayVoice(lines: ref.read(lineAudioCacheProvider), targetLang: widget.plan.targetLang);
      unawaited(voice.warmUp());
      unawaited(voice.prepare(cards));
      final session = DaySession(
        api: api,
        planId: widget.plan.id,
        number: widget.room.day.number,
        cards: cards,
        returnDay: widget.room.day.number + 1,
        rehearsal: rehearsal,
      )..addListener(_onSession);
      setState(() {
        _voice = voice;
        _session = session;
        _openError = null;
        _lessonStatus = null;
      });
    } catch (e) {
      if (!mounted) return;
      final code = problemCodeOf(e);
      setState(() {
        _openError = e;
        _lessonStatus = code == 'plan_lesson_not_ready' ? (problemMetaOf(e)['lesson_status'] as String? ?? 'building') : null;
      });
      // Урок ещё собирается — ждём и пробуем снова, без кнопки.
      if (_lessonStatus != null && _lessonStatus != 'failed') {
        _poll = Timer(const Duration(seconds: 4), _open);
      }
    }
  }

  Future<void> _retryLesson() async {
    final sceneId = widget.room.day.sceneId;
    if (sceneId == null || _retrying) return;
    setState(() => _retrying = true);
    try {
      await ref.read(apiClientProvider).retryPlanLesson(widget.plan.id, sceneId);
    } catch (_) {
      // Сервер скажет своё при следующем открытии.
    }
    if (!mounted) return;
    setState(() {
      _retrying = false;
      _lessonStatus = 'building';
    });
    _poll = Timer(const Duration(seconds: 4), _open);
  }

  void _onSession() {
    if (!mounted) return;
    final s = _session!;
    final id = s.phase == DayPhase.card ? s.current?.id : null;
    if (id != null && id != _slideCardId) {
      _slide++;
      _slideCardId = id;
    }
    setState(() {});
  }

  Future<void> _close() async {
    final l = AppLocalizations.of(context);
    final s = _session;
    // «Ещё раз» ничего не записывает (DAY-UI-2): спрашивать «Продолжить позже? … Прогресс
    // сохранится» было бы обещанием, которого повтор не выполняет, — из него выходят сразу.
    if (s == null || s.stage == null || s.phase == DayPhase.dayDone || s.rehearsal) {
      await _voice?.stop();
      if (mounted) Navigator.of(context).pop(DaySessionExit.left);
      return;
    }
    final stage = s.stage!;
    AppHaptics.light();
    final leave = await showCenterAlert(
      context: context,
      title: l.dayExitTitle,
      message: l.dayExitBody(DayTexts.stage(l, stage), s.doneIn(stage), s.totalIn(stage)),
      confirmLabel: l.dayExitLeave,
      cancelLabel: l.dayExitStay,
    );
    if (leave == true && mounted) {
      await _voice?.stop();
      if (mounted) Navigator.of(context).pop(DaySessionExit.left);
    }
  }

  Future<void> _closeStage() async {
    final s = _session!;
    final wasLast = s.nextStage == null;
    await s.closeStage();
    if (!mounted) return;
    if (s.error != null && s.phase == DayPhase.stageDone) return;
    if (wasLast || s.phase == DayPhase.dayDone) {
      try {
        await s.closeDay();
      } catch (_) {
        // Окно перечитает день; закрытие на сервере идемпотентно.
      }
      if (mounted) Navigator.of(context).pop(s.rehearsal ? DaySessionExit.left : DaySessionExit.dayClosed);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final s = _session;

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark,
      child: PopScope(
        canPop: false,
        onPopInvokedWithResult: (didPop, _) {
          if (!didPop) unawaited(_close());
        },
        child: Scaffold(
          backgroundColor: AppColors.paper,
          body: SafeArea(
            bottom: false,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                _Header(
                  session: s,
                  stages: widget.room.stages,
                  onClose: _close,
                  closeLabel: l.dayExitTitle,
                ),
                Expanded(child: s == null ? _opening(l) : _body(l, s)),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _opening(AppLocalizations l) {
    final status = _lessonStatus;
    if (status == 'failed') {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.screenHWide),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(l.dayLessonFailed, style: AppTextDay.entryTitle, textAlign: TextAlign.center),
              const SizedBox(height: 18),
              PrimaryButton(label: l.dayLessonRetry, minHeight: 52, onPressed: _retrying ? null : _retryLesson),
            ],
          ),
        ),
      );
    }
    if (status != null) {
      return Center(child: Text(l.dayLessonBuilding, style: AppTextDay.facts));
    }
    if (_openError != null) {
      final code = problemCodeOf(_openError!);
      final meta = problemMetaOf(_openError!);
      final text = switch (code) {
        'plan_day_locked' when meta['blocked_by_day'] != null => l.dayLockedByDay((meta['blocked_by_day'] as num).toInt()),
        'plan_day_locked' when meta['opens_on'] != null => l.dayLockedUntil(meta['opens_on'] as String),
        _ => l.planTabLoadFailedTitle,
      };
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.screenHWide),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(text, style: AppTextDay.facts, textAlign: TextAlign.center),
              const SizedBox(height: 18),
              PrimaryButton(label: l.planTabRetry, minHeight: 52, onPressed: _open),
            ],
          ),
        ),
      );
    }
    return const Center(child: CircularProgressIndicator(color: AppColors.ink));
  }

  Widget _body(AppLocalizations l, DaySession s) {
    final reduce = MediaQuery.of(context).disableAnimations;
    final child = switch (s.phase) {
      DayPhase.stageEntry => _StageEntry(
        key: ValueKey('entry-${s.stage}'),
        session: s,
        room: widget.room,
        returnedFromDay: _returnedFromDay,
        onStart: s.startStage,
      ),
      DayPhase.card => KeyedSubtree(key: ValueKey('card-$_slide'), child: _card(s.current!)),
      DayPhase.stageDone => _StageDone(
        key: ValueKey('done-${s.stage}'),
        session: s,
        room: widget.room,
        busy: s.busy,
        onNext: _closeStage,
      ),
      DayPhase.dayDone => const SizedBox.shrink(),
    };

    // Переход карточек: уходящая −24 / 180 мс, входящая +24 / 220 мс; под «уменьшением
    // движения» — только непрозрачность.
    return AnimatedSwitcher(
      duration: reduce ? Duration.zero : AppMotion.nextTaskEnter,
      reverseDuration: reduce ? Duration.zero : AppMotion.nextTaskLeave,
      switchInCurve: AppMotion.easeOut,
      switchOutCurve: AppMotion.easeIn,
      transitionBuilder: (child, anim) => FadeTransition(
        opacity: anim,
        child: reduce
            ? child
            : SlideTransition(
                position: Tween(begin: const Offset(0.06, 0), end: Offset.zero).animate(anim),
                child: child,
              ),
      ),
      layoutBuilder: (current, previous) => Stack(
        alignment: Alignment.topCenter,
        children: [...previous, ?current],
      ),
      child: child,
    );
  }

  /// Номер дня, из которого вернулась карточка, — по id дня в маршруте плана.
  int _returnedFromDay(String? dayId) {
    for (final d in widget.plan.days) {
      if (d.id == dayId) return d.number;
    }
    return (widget.room.day.number - 1).clamp(1, widget.room.day.number);
  }

  Widget _card(DayCard card) {
    final s = _session!;
    final settings = ref.watch(appSettingsProvider).value;
    final plan = widget.plan;
    final scene = widget.room.scene ?? (card.sceneId.isEmpty ? null : plan.sceneById(card.sceneId));
    final localeId = sttLocaleFor(plan.targetLang);
    final dayWords = [
      for (final c in s.cardsOf(PlanStage.words))
        if (c.kind == DayCardKind.wordIntro) c.textTarget,
    ];
    final ctx = DayCardContext(
      session: s,
      voice: _voice!,
      level: plan.level,
      targetLang: plan.targetLang,
      localeId: localeId,
      partnerRole: scene?.partnerRoleNative ?? '',
      returnDay: s.returnDay,
      dayWords: dayWords,
      autoPronounce: settings?.autoPronounce ?? true,
      speech: (c) => speechAttemptFor(ref, c, localeId: localeId, dayWords: dayWords),
      returnedFromDay: _returnedFromDay,
    );
    void next() => s.next();

    return switch (card.kind) {
      DayCardKind.wordIntro => WordIntroCard(card: card, context: ctx, onNext: next),
      DayCardKind.wordSay => WordSayCard(card: card, context: ctx, onNext: next),
      DayCardKind.wordChoose => WordChooseCard(card: card, context: ctx, onNext: next),
      DayCardKind.wordCloze => WordClozeCard(card: card, context: ctx, onNext: next),
      DayCardKind.phraseIntro => PhraseIntroCard(card: card, context: ctx, onNext: next),
      DayCardKind.phraseRepeat => PhraseRepeatCard(card: card, context: ctx, onNext: next),
      DayCardKind.phraseAssemble => PhraseAssembleCard(card: card, context: ctx, onNext: next),
      DayCardKind.dialogueRead => DialogueReadCard(card: card, context: ctx, onNext: next),
      DayCardKind.listenQuestion => ListenQuestionCard(card: card, context: ctx, onNext: next),
      DayCardKind.listenAssemble => ListenAssembleCard(card: card, context: ctx, onNext: next),
      DayCardKind.answerChoose => AnswerChooseCard(card: card, context: ctx, onNext: next),
      DayCardKind.answerAssemble => AnswerAssembleCard(card: card, context: ctx, onNext: next),
      DayCardKind.speak => SpeakCard(card: card, context: ctx, onNext: next),
    };
  }
}

/// ШАПКА СЕССИИ: крестик, «Слова · 12 из 32», пять сегментов 3 px = пять этапов, текущий
/// заливается по карточкам (160 мс). Тап по центру ничего не открывает — листа структуры нет.
class _Header extends StatelessWidget {
  const _Header({required this.session, required this.stages, required this.onClose, required this.closeLabel});

  final DaySession? session;
  final List<PlanStageProgress> stages;
  final VoidCallback onClose;
  final String closeLabel;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final s = session;
    final stage = s?.stage;
    final title = s == null || stage == null
        ? ''
        : l.dayShellCounter(DayTexts.stage(l, stage), s.doneIn(stage), s.totalIn(stage));
    final reduce = MediaQuery.of(context).disableAnimations;

    return Padding(
      padding: const EdgeInsets.fromLTRB(AppSpacing.screenH, 14, AppSpacing.screenH, 0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              DayCloseButton(onTap: onClose, label: closeLabel),
              Expanded(child: Text(title, textAlign: TextAlign.center, style: AppTextDay.shellHeader)),
              const SizedBox(width: AppSpacing.minTap),
            ],
          ),
          const SizedBox(height: 6),
          Row(
            children: [
              for (final st in PlanStage.known) ...[
                if (st != PlanStage.known.first) const SizedBox(width: 4),
                Expanded(
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(1.5),
                    child: SizedBox(
                      height: 3,
                      child: LayoutBuilder(
                        builder: (context, c) => Stack(
                          children: [
                            const Positioned.fill(child: ColoredBox(color: AppColors.barTrack)),
                            AnimatedContainer(
                              duration: reduce ? Duration.zero : AppMotion.stageSegmentFill,
                              curve: AppMotion.linear,
                              width: c.maxWidth * _fill(st),
                              height: 3,
                              color: AppColors.ink,
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                ),
              ],
            ],
          ),
        ],
      ),
    );
  }

  /// Доля заливки сегмента: закрытые этапы — 1, текущий — по карточкам, остальные — 0.
  double _fill(PlanStage st) {
    final s = session;
    if (s == null) {
      final p = stages.where((x) => x.stage == st).firstOrNull;
      if (p == null || p.total == 0) return 0;
      return p.done / p.total;
    }
    final total = s.totalIn(st);
    if (total == 0) return 0;
    return (s.doneIn(st) / total).clamp(0.0, 1.0);
  }
}

/// ВХОД В ЭТАП (23-2a) и «ПРОДОЛЖАЕМ» (23-2b): лейбл «Этап N из 5», заголовок Literata 30, факты,
/// шаги, «8 новых · 2 вернулись из дня 1»; ниже — содержимое этапа списком (слова с мини-фото 32,
/// фразы, обмены парами) с галками у пройденного; «Начать» / «Продолжить» на доке.
class _StageEntry extends StatelessWidget {
  const _StageEntry({super.key, required this.session, required this.room, required this.returnedFromDay, required this.onStart});

  final DaySession session;
  final PlanDayRoom room;
  final int Function(String? dayId) returnedFromDay;
  final VoidCallback onStart;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final s = session;
    final stage = s.stage!;
    final cards = s.cardsOf(stage);
    final units = s.unitsOf(stage);
    final kind = cards.isEmpty ? PlanUnitKind.word : cards.first.unitKind;
    final resuming = s.resumingIn(stage);
    final remaining = s.remainingIn(stage);
    final minutes = DayRules.estimateMinutes(resuming ? remaining : cards.length);
    final returned = cards.where((c) => c.isReturned && !c.isAnswered).map((c) => c.unitRef).toSet();
    final fresh = units.length - returned.length;
    final fromDay = returnedFromDay(cards.where((c) => c.isReturned).firstOrNull?.sourceDayId);

    return Column(
      children: [
        Expanded(
          child: ListView(
            padding: const EdgeInsets.fromLTRB(AppSpacing.screenH, 22, AppSpacing.screenH, 100),
            children: [
              Text(l.dayEntryLabel(stage.ordinal).toUpperCase(), style: AppTextDay.sectionLabel),
              const SizedBox(height: 12),
              Text(DayTexts.stage(l, stage), style: AppTextDay.entryTitle),
              const SizedBox(height: 10),
              Text(
                resuming
                    ? l.dayEntryResume(remaining, cards.length, l.dayApproxMinutes(minutes))
                    : l.dayEntryLine(DayTexts.unitsCount(l, kind, units.length), l.dayCards(cards.length), l.dayApproxMinutes(minutes)),
                style: AppTextDay.facts,
              ),
              if (!resuming) ...[
                const SizedBox(height: 16),
                Text(DayTexts.stageSteps(l, stage), style: AppTextDay.steps),
                if (returned.isNotEmpty) ...[
                  const SizedBox(height: 10),
                  Text('${l.dayEntryNew(fresh)} · ${l.dayEntryReturned(returned.length, fromDay)}', style: AppTextDay.facts.copyWith(color: AppColors.tertiary)),
                ],
              ],
              const SizedBox(height: 18),
              for (final ref in units) _unitRow(l, stage, ref),
            ],
          ),
        ),
        DayDock(label: resuming ? l.dayEntryResumeCta : l.dayEntryCta, onTap: onStart),
      ],
    );
  }

  Widget _unitRow(AppLocalizations l, PlanStage stage, String ref) {
    final s = session;
    final first = s.firstCardOf(stage, ref);
    if (first == null) return const SizedBox.shrink();
    final done = s.unitDoneIn(stage, ref);
    final marker = done
        ? Padding(
            padding: const EdgeInsets.only(left: 12),
            child: VerdictMarker(state: _unitMarker(stage, ref)),
          )
        : null;

    switch (first.unitKind) {
      case PlanUnitKind.word:
        final image = first.image?.url;
        return Padding(
          padding: const EdgeInsets.symmetric(vertical: 8),
          child: Row(
            children: [
              ClipRRect(
                borderRadius: BorderRadius.circular(8),
                child: SizedBox(
                  width: 32,
                  height: 32,
                  child: ColoredBox(
                    color: AppColors.photoSlot,
                    child: image == null ? null : Image(image: CachedNetworkImage(image), fit: BoxFit.cover, errorBuilder: (_, _, _) => const SizedBox.shrink()),
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(first.textTarget, style: AppTextDay.listWord),
                    const SizedBox(height: 2),
                    Text.rich(
                      TextSpan(
                        text: first.textNative,
                        children: [
                          if (first.isReturned) TextSpan(text: '  ${l.dayFromDay(returnedFromDay(first.sourceDayId))}', style: AppTextDay.brassNote.copyWith(fontWeight: FontWeight.w600)),
                        ],
                      ),
                      style: AppTextDay.rowTranslation.copyWith(fontSize: 14),
                    ),
                  ],
                ),
              ),
              ?marker,
            ],
          ),
        );
      case PlanUnitKind.phrase:
        return Padding(
          padding: const EdgeInsets.symmetric(vertical: 10),
          child: Row(
            children: [
              Expanded(child: Text(first.textTarget, style: AppTextDay.listWord)),
              ?marker,
            ],
          ),
        );
      case PlanUnitKind.exchange:
      case PlanUnitKind.unknown:
        final partner = first.partner?.textTarget ?? first.exchanges.firstOrNull?.messages.where((m) => m.isPartner).firstOrNull?.textTarget ?? '';
        final own = first.expected.isNotEmpty ? first.expected : (first.answer.isNotEmpty ? first.answer : first.options.where((o) => o.correct).firstOrNull?.text ?? '');
        return Padding(
          padding: const EdgeInsets.symmetric(vertical: 9),
          child: Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (partner.isNotEmpty) Text(partner, maxLines: 1, overflow: TextOverflow.ellipsis, style: AppTextDay.exchangePartner),
                    if (own.isNotEmpty) ...[
                      const SizedBox(height: 2),
                      Text(own, maxLines: 1, overflow: TextOverflow.ellipsis, style: AppTextDay.exchangeOwn),
                    ],
                  ],
                ),
              ),
              ?marker,
            ],
          ),
        );
    }
  }

  MarkerState _unitMarker(PlanStage stage, String ref) {
    final results = session.cardsOf(stage).where((c) => c.unitRef == ref).map((c) => c.result);
    if (results.any((r) => r == DayCardResult.failed || r == DayCardResult.skipped)) return MarkerState.failed;
    if (results.any((r) => r == DayCardResult.hinted)) return MarkerState.hinted;
    return MarkerState.passed;
  }
}

/// ИТОГ ЭТАПА (23-9): карточка с галкой 30 «Слова закрыты», «32 карточки · 7 минут», полоска в
/// трёх цветах, факты «8 слов в работе · 2 с подсказкой · 1 вернётся»; ниже «Дальше: Фразы · 18
/// карточек · ≈ 5 минут» и список следующего этапа. Сегмент шапки долит сам каркас; звук «этап
/// закрыт» — здесь, один раз при появлении.
class _StageDone extends StatefulWidget {
  const _StageDone({super.key, required this.session, required this.room, required this.busy, required this.onNext});

  final DaySession session;
  final PlanDayRoom room;
  final bool busy;
  final VoidCallback onNext;

  @override
  State<_StageDone> createState() => _StageDoneState();
}

class _StageDoneState extends State<_StageDone> {
  @override
  void initState() {
    super.initState();
    AppFeedback.stageClosed();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final s = widget.session;
    final stage = s.stage!;
    final cards = s.cardsOf(stage);
    final tally = s.tallyOf(stage);
    final total = cards.isEmpty ? 1 : cards.length;
    final kind = cards.isEmpty ? PlanUnitKind.word : cards.first.unitKind;
    final units = s.unitsOf(stage).length;
    final hinted = s.hintedIn(stage);
    final returning = s.returningIn(stage);
    final next = s.nextStage;
    final nextCards = next == null ? const <DayCard>[] : s.cardsOf(next);

    return Column(
      children: [
        Expanded(
          child: ListView(
            padding: const EdgeInsets.fromLTRB(AppSpacing.screenH, 18, AppSpacing.screenH, 100),
            children: [
              PaperCard(
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Row(
                      children: [
                        Container(
                          width: 30,
                          height: 30,
                          decoration: const BoxDecoration(shape: BoxShape.circle, color: AppColors.ink),
                          child: const Icon(LucideIcons.check, size: 16, color: AppColors.paper, weight: 700),
                        ),
                        const SizedBox(width: 11),
                        Expanded(child: Text(DayTexts.stageDone(l, stage), style: AppTextDay.stageDoneTitle)),
                      ],
                    ),
                    const SizedBox(height: 9),
                    Text(l.dayStageDoneMeta(l.dayCards(cards.length), l.dayMinutes(s.minutesOf(stage))), style: AppTextDay.facts),
                    const SizedBox(height: 14),
                    StageBar(passed: tally.passed / total, hinted: tally.hinted / total, failed: tally.failed / total),
                    const SizedBox(height: 14),
                    Text.rich(
                      TextSpan(
                        text: DayTexts.unitsInWork(l, kind, units),
                        children: [
                          if (hinted > 0) ...[
                            const TextSpan(text: ' · '),
                            TextSpan(text: l.dayStageFactsHinted(hinted), style: const TextStyle(color: AppColors.verdictUnsure)),
                          ],
                          if (returning > 0) ...[
                            const TextSpan(text: ' · '),
                            TextSpan(text: l.dayStageFactsReturn(returning), style: const TextStyle(color: AppColors.destructiveText)),
                          ],
                        ],
                      ),
                      style: AppTextDay.stageFacts,
                    ),
                  ],
                ),
              ),
              if (next != null) ...[
                const SizedBox(height: 22),
                Text(
                  l.dayStageNext(DayTexts.stage(l, next), l.dayCards(nextCards.length), l.dayApproxMinutes(DayRules.estimateMinutes(nextCards.length))).toUpperCase(),
                  style: AppTextDay.sectionLabel,
                ),
                const SizedBox(height: 4),
                for (final ref in s.unitsOf(next))
                  if (s.firstCardOf(next, ref) case final c?)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 10),
                      child: Text(
                        c.unitKind == PlanUnitKind.exchange ? (c.partner?.textTarget ?? c.expected) : c.textTarget,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: AppTextDay.listWord,
                      ),
                    ),
              ],
            ],
          ),
        ),
        DayDock(label: l.dayNext, onTap: widget.busy ? null : widget.onNext),
      ],
    );
  }
}
