import 'dart:async';

import 'package:flutter/foundation.dart';

import '../api_client.dart';
import 'plan_models.dart';
import 'day_contract.dart';

/// ГДЕ СТОИТ СЕССИЯ ДНЯ.
enum DayPhase {
  /// Вход в этап (23-2a) или «продолжаем» (23-2b).
  stageEntry,

  /// Карточка.
  card,

  /// Итог этапа (23-9).
  stageDone,

  /// Все этапы закрыты — день закрывается / закрыт; экран уходит в кабинет.
  dayDone,
}

/// МАШИНА СОСТОЯНИЙ ДНЯ — порядок карточек, этапы, ответы и закрытия. Ничего не рисует.
///
/// Порядок и состав — от сервера (`PlanDayCards`, в порядке хода); клиент ничего не собирает. Что
/// клиент ведёт сам: какая карточка сейчас впереди, сколько попыток на ней было, и повтор в конце
/// этапа, который сервер возвращает `requeued` на первый `failed`. Каждый вердикт уходит на сервер
/// сразу; локальная копия карточки обновляется ответом сервера.
class DaySession extends ChangeNotifier {
  DaySession({
    required this.api,
    required this.planId,
    required this.number,
    required List<DayCard> cards,
    required this.returnDay,
  }) : _cards = List.of(cards) {
    _stages = [
      for (final s in PlanStage.known)
        if (_cards.any((c) => c.stage == s)) s,
    ];
    _openFirstUnfinished();
  }

  final ApiClient api;
  final String planId;
  final int number;

  /// Номер дня, в который вернётся неудавшаяся карточка — «Вернётся в день N».
  final int returnDay;

  final List<DayCard> _cards;
  late List<PlanStage> _stages;
  PlanStage? _stage;
  DayPhase _phase = DayPhase.stageEntry;
  DayCard? _current;
  Object? _error;

  /// Сколько раз человек уже ответил на ЭТОЙ карточке (для микрофона — попытки без зачёта).
  final Map<String, int> _attempts = {};

  /// Что человек сказал на карточках «говорю сам» — для ленты (23-8e).
  final Map<String, String> _spoken = {};

  /// Когда этап начали — «N минут» в итоге этапа.
  DateTime? _stageStartedAt;
  final Map<PlanStage, int> _stageMinutes = {};

  bool _busy = false;

  List<DayCard> get cards => List.unmodifiable(_cards);
  List<PlanStage> get stages => List.unmodifiable(_stages);
  PlanStage? get stage => _stage;
  DayPhase get phase => _phase;
  DayCard? get current => _current;
  Object? get error => _error;
  bool get busy => _busy;
  Map<String, String> get spoken => Map.unmodifiable(_spoken);

  /// Порядковый номер текущего этапа среди пяти — «Этап N из 5».
  int get stageOrdinal => _stage == null ? 1 : _stage!.ordinal;

  /// Карточки этапа в порядке хода (повторы — в конце, как их поставил сервер).
  List<DayCard> cardsOf(PlanStage s) {
    final list = _cards.where((c) => c.stage == s).toList();
    list.sort((a, b) => a.position.compareTo(b.position));
    return list;
  }

  int doneIn(PlanStage s) => cardsOf(s).where((c) => c.isAnswered).length;
  int totalIn(PlanStage s) => cardsOf(s).length;
  int remainingIn(PlanStage s) => totalIn(s) - doneIn(s);

  /// Уже ли в этом этапе что-то отвечено — «продолжаем» вместо «начать».
  bool resumingIn(PlanStage s) => doneIn(s) > 0;

  int attemptsOf(DayCard card) => _attempts[card.id] ?? 0;

  /// Единицы этапа — по `unit_ref`, в порядке первого появления.
  List<String> unitsOf(PlanStage s) {
    final seen = <String>{};
    return [for (final c in cardsOf(s)) if (seen.add(c.unitRef)) c.unitRef];
  }

  /// Первая карточка единицы в этапе — та, чей пейлоад описывает единицу (знакомство и т. п.).
  DayCard? firstCardOf(PlanStage s, String unitRef) {
    for (final c in cardsOf(s)) {
      if (c.unitRef == unitRef) return c;
    }
    return null;
  }

  /// Единица закрыта в этом этапе — все её карточки отвечены.
  bool unitDoneIn(PlanStage s, String unitRef) =>
      cardsOf(s).where((c) => c.unitRef == unitRef).every((c) => c.isAnswered);

  /// Как этап разложился — для полоски в трёх цветах и фактов итога.
  ({int passed, int hinted, int failed}) tallyOf(PlanStage s) {
    var passed = 0, hinted = 0, failed = 0;
    for (final c in cardsOf(s)) {
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
    return (passed: passed, hinted: hinted, failed: failed);
  }

  /// Единицы этапа, которые вернутся в следующий день.
  int returningIn(PlanStage s) {
    final refs = <String>{};
    for (final c in cardsOf(s)) {
      if (c.returns || c.result == DayCardResult.failed) refs.add(c.unitRef);
    }
    return refs.length;
  }

  int hintedIn(PlanStage s) {
    final refs = <String>{};
    for (final c in cardsOf(s)) {
      if (c.result == DayCardResult.hinted) refs.add(c.unitRef);
    }
    return refs.length;
  }

  int minutesOf(PlanStage s) => _stageMinutes[s] ?? 0;

  /// Следующий этап после текущего, или null.
  PlanStage? get nextStage {
    final i = _stage == null ? -1 : _stages.indexOf(_stage!);
    return i + 1 < _stages.length ? _stages[i + 1] : null;
  }

  /// Первая карточка первого этапа с неотвеченными карточками — с неё день продолжается.
  void _openFirstUnfinished() {
    for (final s in _stages) {
      if (remainingIn(s) > 0) {
        _stage = s;
        _phase = DayPhase.stageEntry;
        _current = null;
        return;
      }
    }
    _stage = _stages.isEmpty ? null : _stages.last;
    _phase = DayPhase.dayDone;
  }

  /// «Начать» / «Продолжить» на входе в этап.
  void startStage() {
    _stageStartedAt = DateTime.now();
    _advance();
  }

  void _advance() {
    final s = _stage;
    if (s == null) return;
    DayCard? next;
    for (final c in cardsOf(s)) {
      if (!c.isAnswered) {
        next = c;
        break;
      }
    }
    if (next == null) {
      final started = _stageStartedAt;
      if (started != null) {
        _stageMinutes[s] = (DateTime.now().difference(started).inSeconds / 60).round().clamp(1, 999);
      }
      _phase = DayPhase.stageDone;
      _current = null;
    } else {
      _phase = DayPhase.card;
      _current = next;
    }
    notifyListeners();
  }

  /// «Дальше» после карточки — следующая карточка или итог этапа.
  void next() => _advance();

  /// Знакомство — «Понятно»: `passed`, без попыток.
  Future<void> acknowledge(DayCard card) => answer(card, DayCardResult.passed, attempts: 1);

  /// ОТВЕТ НА КАРТОЧКУ. [attempts] — сколько попыток было, включая эту.
  ///
  /// Уходит на сервер сразу; ответ сервера заменяет локальную карточку, а `requeued` встаёт в
  /// конец этапа. Экран после этого сам зовёт [next] — по «Дальше», по авто-уходу «произнеси» или
  /// сразу на «говорю сам».
  Future<void> answer(DayCard card, DayCardResult result, {required int attempts, String? spokenText}) async {
    if (_busy) return;
    _busy = true;
    _error = null;
    _attempts[card.id] = attempts;
    if (spokenText != null) _spoken[card.id] = spokenText;
    notifyListeners();
    try {
      final outcome = await api.answerDayCard(planId, number, card.id, result: result, attempts: attempts);
      _replace(outcome.card);
      if (outcome.requeued case final again?) {
        if (!_cards.any((c) => c.id == again.id)) _cards.add(again);
      }
    } catch (e) {
      // Уже отвеченная карточка (409 `plan_card_answered`) — сервер её знает; дальше без ошибки.
      if (problemCodeOf(e) == 'plan_card_answered') {
        _replace(card.copyWith(result: result, attempts: attempts));
      } else {
        // Сеть упала: вердикт человека не теряется — карточка закрывается локально и день идёт;
        // при следующем открытии сервер отдаст свою правду.
        _error = e;
        _replace(card.copyWith(result: result, attempts: attempts));
      }
    } finally {
      _busy = false;
      notifyListeners();
    }
  }

  void _replace(DayCard fresh) {
    final i = _cards.indexWhere((c) => c.id == fresh.id);
    if (i >= 0) {
      _cards[i] = fresh;
    } else {
      _cards.add(fresh);
    }
  }

  /// «Дальше» на итоге этапа: закрыть этап на сервере и открыть следующий (или закрыть день).
  Future<PlanDayRoom?> closeStage() async {
    final s = _stage;
    if (s == null || _busy) return null;
    _busy = true;
    _error = null;
    notifyListeners();
    PlanDayRoom? room;
    try {
      room = await api.closeStage(planId, number, s);
    } catch (e) {
      // Этап, который сервер уже считает закрытым, — не ошибка; всё остальное — показать и дать
      // повторить.
      if (problemCodeOf(e) != 'plan_stage_incomplete') {
        _error = e;
      } else {
        _error = e;
        _busy = false;
        notifyListeners();
        return null;
      }
    }
    _busy = false;
    final after = nextStage;
    if (after == null) {
      _phase = DayPhase.dayDone;
      _current = null;
    } else {
      _stage = after;
      _phase = DayPhase.stageEntry;
      _current = null;
    }
    notifyListeners();
    return room;
  }

  /// Закрыть день — кабинет с метриками.
  Future<PlanDayRoom> closeDay() => api.closeDay(planId, number);
}

extension on DayCard {
  DayCard copyWith({DayCardResult? result, int? attempts}) => DayCard(
    id: id,
    stage: stage,
    position: position,
    kind: kind,
    source: source,
    sourceDayId: sourceDayId,
    unitKind: unitKind,
    unitRef: unitRef,
    payload: payload,
    retryOf: retryOf,
    result: result ?? this.result,
    attempts: attempts ?? this.attempts,
    returns: returns,
  );
}
