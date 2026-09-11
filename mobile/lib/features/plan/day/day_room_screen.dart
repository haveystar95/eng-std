import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import '../../../data/local/cached_image_provider.dart';
import '../../../data/plan/day_rules.dart';
import '../../../data/plan/plan_models.dart';
import '../../../data/plan/day_contract.dart';
import '../../../data/plan/day_providers.dart';
import '../plan_providers.dart';
import '../plan_tab_parts.dart' show PlanLoadFailedCard;
import 'day_card_frame.dart';
import 'day_session_screen.dart';
import 'day_texts.dart';
import 'term_sheet.dart';

/// КАБИНЕТ ДНЯ (кадры 23-0a … 23-0e) — одно окно, три состояния дня: не начат / идёт (брошен) /
/// закрыт. Шапка — плита 4н во весь верх с фото дня под материалом; под ней на бумаге — программа
/// дня: слова сеткой 2 × 4, фразы строками 60, обмены парами 72; состояние везде маркером 4л.
/// В закрытом дне между шапкой и программой — «Далось труднее всего» и «В работе».
///
/// Стрелка назад — на таб «План». Тап по плите — сессия; крестик сессии возвращает сюда;
/// закрытие последнего этапа — сюда в закрытом состоянии с анимацией 23-0b → 23-0c.
class DayRoomScreen extends ConsumerStatefulWidget {
  const DayRoomScreen({super.key, required this.plan, required this.number});

  final Plan plan;
  final int number;

  @override
  ConsumerState<DayRoomScreen> createState() => _DayRoomScreenState();
}

class _DayRoomScreenState extends ConsumerState<DayRoomScreen> {
  /// День только что закрыли в сессии — плита проигрывает 23-0b → 23-0c один раз.
  bool _justClosed = false;

  DayAddress get _address => (planId: widget.plan.id, number: widget.number);

  Future<void> _openSession(PlanDayRoom room) async {
    AppHaptics.light();
    final exit = await Navigator.of(context).push<DaySessionExit>(
      MaterialPageRoute(builder: (_) => DaySessionScreen(plan: widget.plan, room: room)),
    );
    if (!mounted) return;
    _justClosed = exit == DaySessionExit.dayClosed;
    ref.invalidate(dayRoomProvider(_address));
    ref.invalidate(dayCardsProvider(_address));
    ref.invalidate(daySheetProvider(_address));
    unawaited(ref.read(planTabProvider.notifier).refresh());
    if (_justClosed) AppFeedback.dayClosed();
  }

  @override
  Widget build(BuildContext context) {
    final room = ref.watch(dayRoomProvider(_address));

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light,
      child: Scaffold(
        backgroundColor: AppColors.paper,
        body: room.when(
          loading: () => const Center(child: CircularProgressIndicator(color: AppColors.ink)),
          error: (e, _) => SafeArea(
            child: PlanLoadFailedCard(onRetry: () => ref.invalidate(dayRoomProvider(_address))),
          ),
          data: (r) => _Room(
            plan: widget.plan,
            room: r,
            animateClose: _justClosed,
            onOpen: () => _openSession(r),
            onBack: () => Navigator.of(context).maybePop(),
          ),
        ),
      ),
    );
  }
}

class _Room extends ConsumerWidget {
  const _Room({required this.plan, required this.room, required this.animateClose, required this.onOpen, required this.onBack});

  final Plan plan;
  final PlanDayRoom room;
  final bool animateClose;
  final VoidCallback onOpen;
  final VoidCallback onBack;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final address = (planId: plan.id, number: room.day.number);
    final cards = room.day.status == PlanDayStatus.open ? const <DayCard>[] : (ref.watch(dayCardsProvider(address)).value?.cards ?? const <DayCard>[]);
    final sheet = room.sheetAvailable ? ref.watch(daySheetProvider(address)).value : null;
    final status = room.day.status;
    final closed = status == PlanDayStatus.closed;
    final title = room.day.titleNative ?? room.scene?.titleNative ?? plan.titleNative ?? '';
    final total = room.cardsTotal;
    final photo = room.scene?.image?.url;
    final returnDay = room.day.number + 1;

    // ДО ОТКРЫТИЯ ДНЯ сервер ещё не раздал карточки: этапы «absent», программа пуста. Пять строк
    // этапов рисуются без счётчиков, программа — из шита (слова и фразы дня известны заранее;
    // обмены раздаются только с карточками). Отклонение от 23-0a названо в отчёте.
    final known = room.stages.where((p) => p.state != PlanStageState.absent).toList();
    final stageRows = known.isNotEmpty
        ? known
        : [
            for (final s in PlanStage.known)
              PlanStageProgress(stage: s, total: 0, done: 0, state: s == PlanStage.words ? PlanStageState.current : PlanStageState.locked),
          ];
    final stages = [for (final p in stageRows) _stageRow(l, p, cards, status, ref: ref, address: address)];

    final footer = switch (status) {
      PlanDayStatus.closed => DayRoomClosed(
        title: l.dayClosedTitle(room.day.number),
        numbers: _numbers(l, room, cards),
        animate: animateClose,
      ),
      PlanDayStatus.inProgress => DayRoomProgress(numbers: _numbers(l, room, cards), button: l.dayCtaContinue(room.cardsRemaining)),
      _ => DayRoomStart(button: l.dayCtaStart),
    };

    final plate = DayRoomPlate.header(
      label: l.dayLabel(room.day.number),
      title: title,
      meta: total == 0
          ? DayTexts.level(l, plan.level)
          : '${l.dayCards(total)} · ${l.dayApproxMinutes(DayRules.estimateMinutes(total))} · ${DayTexts.level(l, plan.level)}',
      goals: room.goalsNative,
      goalsDone: closed,
      stages: stages,
      footer: footer,
      photo: photo == null ? null : CachedNetworkImage(photo),
      onBack: onBack,
      backLabel: l.dayBack,
    );

    final fromSheet = room.program.isEmpty && sheet != null;
    final words = fromSheet ? [for (final t in sheet.words) _unitOf(t, PlanUnitKind.word)] : room.words.toList();
    final phrases = fromSheet ? [for (final t in sheet.phrases) _unitOf(t, PlanUnitKind.phrase)] : room.phrases.toList();
    final exchanges = room.exchanges.toList();
    final returnedWords = words.where((u) => u.source == PlanUnitSource.returned).toList();
    final returnedFrom = returnedWords.isEmpty ? null : _returnedFromDay(cards, returnedWords.first.unitRef);

    return ListView(
      padding: EdgeInsets.zero,
      children: [
        closed ? plate : Material(color: Colors.transparent, child: InkWell(onTap: onOpen, child: plate)),
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 0, 20, 40),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              if (closed) ...[
                const SizedBox(height: 20),
                ..._closedCards(l, room, cards, words, phrases, exchanges, returnDay),
                const SizedBox(height: 8),
              ] else
                const SizedBox(height: 10),
              if (words.isNotEmpty) ...[
                DaySectionLabel(
                  l.daySectionWords(words.length - returnedWords.length),
                  trailing: returnedWords.isEmpty ? null : l.dayWordsExtra(returnedWords.length, returnedFrom ?? room.day.number - 1),
                ),
                GridView.count(
                  crossAxisCount: 2,
                  mainAxisSpacing: 12,
                  crossAxisSpacing: 12,
                  childAspectRatio: 169 / 186,
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  padding: EdgeInsets.zero,
                  children: [
                    for (final u in words)
                      _WordCard(
                        unit: u,
                        term: sheet?.byRef(u.unitRef),
                        marker: _unitMarker(u, cards, closed),
                        fromDay: u.source == PlanUnitSource.returned ? (_returnedFromDay(cards, u.unitRef) ?? room.day.number - 1) : null,
                        onTap: () => _showSheet(context, u, sheet, cards, returnDay),
                      ),
                  ],
                ),
              ],
              if (phrases.isNotEmpty) ...[
                DaySectionLabel(l.daySectionPhrases(phrases.length)),
                for (final u in phrases)
                  _PhraseRow(
                    unit: u,
                    marker: _unitMarker(u, cards, closed),
                    onTap: () => _showSheet(context, u, sheet, cards, returnDay),
                  ),
              ],
              if (exchanges.isNotEmpty) ...[
                DaySectionLabel(l.daySectionTalk(exchanges.length)),
                for (final u in exchanges)
                  _ExchangeRow(unit: u, partner: _partnerLineOf(u, cards), own: _ownLineOf(u, cards), marker: _unitMarker(u, cards, closed)),
              ],
            ],
          ),
        ),
      ],
    );
  }

  /// Единица программы из термина шита — для дня, который ещё не открыт.
  PlanProgramUnit _unitOf(DayTerm t, PlanUnitKind kind) => PlanProgramUnit(
    unitKind: kind,
    unitRef: t.ref,
    sceneId: t.sceneId,
    textTarget: t.textTarget,
    textNative: t.textNative,
    source: PlanUnitSource.today,
    cardsTotal: 0,
    cardsDone: 0,
    state: PlanUnitState.pending,
  );

  /// Три числа подвала: карточек · минут · с первого раза. В идущем дне процент считает клиент
  /// по карточкам, пока сервер не отдаст свой в закрытии (отклонение, названо в отчёте).
  List<DayRoomNumber> _numbers(AppLocalizations l, PlanDayRoom room, List<DayCard> cards) {
    final m = room.metrics;
    final done = m?.cardsDone ?? room.cardsDone;
    final minutes = m?.minutesSpent ?? room.day.minutesSpent;
    final percent = m?.firstTryPercent ?? DayRules.firstTryPercent(cards) ?? 0;
    return [
      DayRoomNumber(value: done, label: l.dayNumCards(done)),
      DayRoomNumber(value: minutes, label: l.dayNumMinutes(minutes)),
      DayRoomNumber(value: percent, label: l.dayNumFirstTry, unit: '%'),
    ];
  }

  DayRoomStage _stageRow(AppLocalizations l, PlanStageProgress p, List<DayCard> cards, PlanDayStatus status, {required WidgetRef ref, required DayAddress address}) {
    var passed = 0, hinted = 0, failed = 0;
    for (final c in cards) {
      if (c.stage != p.stage) continue;
      switch (c.result) {
        case DayCardResult.passed:
          passed++;
        case DayCardResult.hinted:
          hinted++;
        case DayCardResult.failed:
        case DayCardResult.skipped:
          failed++;
        case null:
          break;
      }
    }
    // Карточек ещё нет (день не открыт) — полоска по серверному `done` шалфеем.
    if (cards.isEmpty) passed = p.done;
    final current = p.state == PlanStageState.current && status != PlanDayStatus.closed;
    String? sub;
    if (current) {
      if (p.done == 0) {
        final units = p.stage == PlanStage.words && room.words.isEmpty ? (ref.read(daySheetProvider(address)).value?.words.length ?? 0) : _unitsIn(p.stage);
        final what = p.stage == PlanStage.words ? l.dayNewWords(units) : l.dayCards(p.total);
        sub = l.dayStageSubStart(what);
      } else {
        sub = l.dayStageSubUnfinished(l.dayCards(p.remaining), l.dayApproxMin(DayRules.estimateMinutes(p.remaining)));
      }
    }
    return DayRoomStage(
      name: DayTexts.stage(l, p.stage),
      done: p.done,
      total: p.total,
      passed: passed,
      hinted: hinted,
      failed: failed,
      current: current,
      locked: p.state == PlanStageState.locked,
      sub: sub,
    );
  }

  int _unitsIn(PlanStage stage) => switch (stage) {
    PlanStage.words => room.words.length,
    PlanStage.phrases => room.phrases.length,
    _ => room.exchanges.length,
  };

  /// Маркер единицы: по карточкам (с подсказкой видно только там), иначе по состоянию с сервера.
  MarkerState _unitMarker(PlanProgramUnit u, List<DayCard> cards, bool closed) {
    final own = cards.where((c) => c.unitRef == u.unitRef).toList();
    if (own.isNotEmpty) {
      final results = own.map((c) => c.result).toList();
      if (results.every((r) => r == null)) return MarkerState.empty;
      if (own.any((c) => c.returns) || results.any((r) => r == DayCardResult.failed || r == DayCardResult.skipped)) return MarkerState.failed;
      if (results.any((r) => r == DayCardResult.hinted)) return MarkerState.hinted;
      if (results.every((r) => r != null)) return MarkerState.passed;
      return MarkerState.empty;
    }
    return switch (u.state) {
      PlanUnitState.pending => MarkerState.empty,
      PlanUnitState.passed => MarkerState.passed,
      PlanUnitState.failed => MarkerState.failed,
      PlanUnitState.unknown => MarkerState.empty,
    };
  }

  int? _returnedFromDay(List<DayCard> cards, String ref) {
    for (final c in cards) {
      if (c.unitRef == ref && c.sourceDayId != null) {
        for (final d in plan.days) {
          if (d.id == c.sourceDayId) return d.number;
        }
      }
    }
    return null;
  }

  /// Реплика собеседника в обмене — у единицы программы текста нет, он в карточках.
  String _partnerLineOf(PlanProgramUnit u, List<DayCard> cards) {
    if (u.textTarget case final t? when t.isNotEmpty) return t;
    for (final c in cards) {
      if (c.unitRef != u.unitRef) continue;
      if (c.partner case final p?) return p.textTarget;
      for (final x in c.exchanges) {
        for (final m in x.messages) {
          if (m.isPartner) return m.textTarget;
        }
      }
    }
    return '';
  }

  /// Своя реплика обмена — из карточек, где её произносит или собирает ученик; «что он спросил»
  /// и «услышал → собери» про реплику собеседника и сюда не идут.
  String _ownLineOf(PlanProgramUnit u, List<DayCard> cards) {
    for (final c in cards) {
      if (c.unitRef != u.unitRef) continue;
      switch (c.kind) {
        case DayCardKind.speak:
          if (c.expected.isNotEmpty) return c.expected;
        case DayCardKind.answerAssemble:
          if (c.answer.isNotEmpty) return c.answer;
        case DayCardKind.answerChoose:
          final own = c.options.where((o) => o.correct).firstOrNull;
          if (own != null) return own.text;
        default:
          break;
      }
    }
    for (final c in cards) {
      if (c.unitRef != u.unitRef || c.kind != DayCardKind.dialogueRead) continue;
      for (final x in c.exchanges) {
        for (final m in x.messages) {
          if (!m.isPartner) return m.textTarget;
        }
      }
    }
    return '';
  }

  List<Widget> _closedCards(AppLocalizations l, PlanDayRoom room, List<DayCard> cards, List<PlanProgramUnit> words, List<PlanProgramUnit> phrases, List<PlanProgramUnit> exchanges, int returnDay) {
    final m = room.metrics;
    final hardestText = m?.hardestUnitText;
    final hardestRef = m?.hardestUnitRef;
    final tries = hardestRef == null ? 0 : cards.where((c) => c.unitRef == hardestRef).fold(0, (n, c) => n + c.attempts);
    final returning = cards.where((c) => c.returns).length;
    final parts = [
      if (words.isNotEmpty) l.dayWordsCount(words.length),
      if (phrases.isNotEmpty) l.dayPhrasesCount(phrases.length),
      if (exchanges.isNotEmpty) l.dayExchangesCount(exchanges.length),
    ];

    return [
      if (hardestText != null && hardestText.isNotEmpty) ...[
        PaperCard(
          radius: 22,
          padding: const EdgeInsets.all(18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(l.dayHardest.toUpperCase(), style: AppTextDay.sectionLabel),
              const SizedBox(height: 10),
              Row(
                crossAxisAlignment: CrossAxisAlignment.baseline,
                textBaseline: TextBaseline.alphabetic,
                children: [
                  Expanded(child: Text(hardestText, style: AppTextDay.hardest)),
                  if (tries > 0) ...[
                    const SizedBox(width: 12),
                    Text(l.dayTries(tries), style: AppTextDay.facts.copyWith(color: AppColors.tertiary)),
                  ],
                ],
              ),
            ],
          ),
        ),
        const SizedBox(height: 12),
      ],
      PaperCard(
        radius: 22,
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(l.dayInWork.toUpperCase(), style: AppTextDay.sectionLabel),
            const SizedBox(height: 10),
            Text(parts.join(' · '), style: AppTextDay.inWork),
            if (returning > 0) ...[
              const SizedBox(height: 10),
              Text(l.dayWillReturn(returnDay, returning), style: AppTextDay.willReturn),
            ],
          ],
        ),
      ),
    ];
  }

  Future<void> _showSheet(BuildContext context, PlanProgramUnit u, DaySheet? sheet, List<DayCard> cards, int returnDay) async {
    AppHaptics.light();
    final own = cards.where((c) => c.unitRef == u.unitRef).toList();
    final term = sheet?.byRef(u.unitRef) ??
        DayTerm(
          id: u.unitRef,
          sceneId: u.sceneId,
          kind: u.unitKind == PlanUnitKind.phrase ? 'phrase' : 'word',
          ref: u.unitRef,
          textTarget: u.textTarget ?? own.firstOrNull?.textTarget ?? '',
          textNative: u.textNative ?? own.firstOrNull?.textNative ?? '',
          simplifiedVariants: const [],
          pronunciationNative: own.firstOrNull?.pronunciationNative,
          exampleTarget: own.firstOrNull?.exampleTarget,
          exampleNative: own.firstOrNull?.exampleNative,
          speakingKey: own.firstOrNull?.speakingKey,
          image: own.firstOrNull?.image,
        );
    final marker = _unitMarker(u, cards, room.day.status == PlanDayStatus.closed);
    final state = switch (marker) {
      MarkerState.empty => SheetTermState.pending,
      MarkerState.passed => SheetTermState.passed,
      MarkerState.hinted => SheetTermState.hinted,
      MarkerState.failed => SheetTermState.returns,
    };
    // «В разговоре» у фразы — реплика собеседника из карточки знакомства (пример фразы) и сама фраза.
    final intro = own.where((c) => c.kind == DayCardKind.phraseIntro).firstOrNull;
    await showTermSheet(
      context,
      term: term,
      state: state,
      dayNumber: room.day.number,
      returnDay: returnDay,
      targetLang: plan.targetLang,
      partnerRole: room.scene?.partnerRoleNative ?? '',
      inTalkPartner: intro?.exampleTarget,
      inTalkOwn: term.textTarget,
      fromDay: u.source == PlanUnitSource.returned ? (_returnedFromDay(cards, u.unitRef) ?? room.day.number - 1) : null,
    );
  }
}

/// Карточка слова в сетке: фото 110 с маркером в углу на бумажной подложке .9 и латунной пилюлей
/// «из дня 1»; слово Literata 18, перевод 14 — по одной строке.
class _WordCard extends StatelessWidget {
  const _WordCard({required this.unit, required this.term, required this.marker, required this.fromDay, required this.onTap});

  final PlanProgramUnit unit;
  final DayTerm? term;
  final MarkerState marker;
  final int? fromDay;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final image = term?.image?.url;
    return PaperCard(
      radius: 18,
      padding: EdgeInsets.zero,
      clipContent: true,
      onTap: onTap,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SizedBox(
            height: 110,
            child: Stack(
              fit: StackFit.expand,
              children: [
                ColoredBox(
                  color: AppColors.photoSlot,
                  child: image == null ? null : Image(image: CachedNetworkImage(image), fit: BoxFit.cover, errorBuilder: (_, _, _) => const SizedBox.shrink()),
                ),
                if (marker != MarkerState.empty)
                  Positioned(
                    top: 8,
                    right: 8,
                    child: Container(
                      width: 28,
                      height: 28,
                      alignment: Alignment.center,
                      decoration: const BoxDecoration(shape: BoxShape.circle, color: AppColors.paperUnderMarker),
                      child: VerdictMarker(state: marker),
                    ),
                  ),
                if (fromDay case final d?) Positioned(top: 10, left: 10, child: BrassPill(l.dayFromDay(d))),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 12, 14, 14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(unit.textTarget ?? term?.textTarget ?? '', maxLines: 1, overflow: TextOverflow.ellipsis, style: AppTextDay.gridWord),
                const SizedBox(height: 3),
                Text(unit.textNative ?? term?.textNative ?? '', maxLines: 1, overflow: TextOverflow.ellipsis, style: AppTextDay.gridTranslation),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Строка фразы 60: фраза Literata 18, перевод 15, маркер справа.
class _PhraseRow extends StatelessWidget {
  const _PhraseRow({required this.unit, required this.marker, required this.onTap});

  final PlanProgramUnit unit;
  final MarkerState marker;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => InkWell(
    onTap: onTap,
    borderRadius: BorderRadius.circular(AppRadii.field),
    child: SizedBox(
      height: 60,
      child: Row(
        children: [
          Expanded(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(unit.textTarget ?? '', maxLines: 1, overflow: TextOverflow.ellipsis, style: AppTextDay.gridWord),
                const SizedBox(height: 3),
                Text(unit.textNative ?? '', maxLines: 1, overflow: TextOverflow.ellipsis, style: AppTextDay.rowTranslation),
              ],
            ),
          ),
          if (marker != MarkerState.empty) ...[
            const SizedBox(width: 12),
            VerdictMarker(state: marker),
          ],
        ],
      ),
    ),
  );
}

/// Пара обмена 72: реплика собеседника secondary, своя — ink; маркер справа.
class _ExchangeRow extends StatelessWidget {
  const _ExchangeRow({required this.unit, required this.partner, required this.own, required this.marker});

  final PlanProgramUnit unit;
  final String partner;
  final String own;
  final MarkerState marker;

  @override
  Widget build(BuildContext context) => SizedBox(
    height: 72,
    child: Row(
      children: [
        Expanded(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(partner, maxLines: 1, overflow: TextOverflow.ellipsis, style: AppTextDay.exchangePartner),
              if (own.isNotEmpty || (unit.textNative ?? '').isNotEmpty) ...[
                const SizedBox(height: 4),
                Text(own.isNotEmpty ? own : unit.textNative!, maxLines: 1, overflow: TextOverflow.ellipsis, style: AppTextDay.exchangeOwn),
              ],
            ],
          ),
        ),
        if (marker != MarkerState.empty) ...[
          const SizedBox(width: 12),
          VerdictMarker(state: marker),
        ],
      ],
    ),
  );
}
