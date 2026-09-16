/// «ОКНО ДНЯ» — `PlanDayRoom.window` (наряды DAY-UI-2, DAY-UI-3, кадры 23-0a…0e; схема `PlanDayWindow`).
///
/// Всё, что окно рисует, СЧИТАЕТ СЕРВЕР: слово состояния дня, ряды этапов с цифрой только у
/// текущего, минуты, полосу компактной шапки, состояния единиц программы и счётчики бровей, одно
/// действие. Клиент складывает из этого слова и не выводит ни одного числа сам.
///
/// Разбор ЗАКРЫТЫЙ, в отличие от остального плана: запертый день, неизвестное слово состояния или
/// пропавший счётчик — [PlanContractError], и окно говорит, что не смогло загрузиться, вместо того
/// чтобы нарисовать выдуманное состояние.
library;

import 'plan_models.dart';

/// Одно слово для дня. `locked` на проводе есть, в окне — нет: такой ответ — ошибка контракта.
enum WindowDayStatus {
  notStarted,
  inProgress,
  passed;

  static WindowDayStatus fromWire(Object? s) => switch (s) {
    'not_started' => notStarted,
    'in_progress' => inProgress,
    'passed' => passed,
    _ => throw PlanContractError('window day status «$s»'),
  };
}

/// Ряд этапа: пройден, идёт, впереди.
enum WindowStageState {
  done,
  current,
  locked;

  static WindowStageState fromWire(Object? s) => switch (s) {
    'done' => done,
    'current' => current,
    'locked' => locked,
    _ => throw PlanContractError('window stage state «$s»'),
  };
}

/// Где стоит единица программы: не пройдена, пройдена, вернётся завтра.
enum WindowUnitState {
  pending,
  done,
  returnsTomorrow;

  static WindowUnitState fromWire(Object? s) => switch (s) {
    'pending' => pending,
    'done' => done,
    'returns_tomorrow' => returnsTomorrow,
    _ => throw PlanContractError('window unit state «$s»'),
  };
}

/// Одна кнопка окна. `again` — «Говорю сам» ещё раз по карточкам дня, без записи ответов.
enum WindowAction {
  start,
  resume,
  again;

  static WindowAction? fromWire(Object? s) => switch (s) {
    null => null,
    'start' => start,
    'continue' => resume,
    'again' => again,
    _ => throw PlanContractError('window action «$s»'),
  };
}

/// «Научишься …» — `passed` у всех целей только у пройденного дня.
class WindowGoal {
  const WindowGoal({required this.text, required this.passed});

  final String text;
  final bool passed;
}

class WindowDay {
  const WindowDay({
    required this.index,
    required this.type,
    required this.imageTone,
    required this.status,
    required this.goals,
    this.titleNative,
    this.titleTarget,
    this.image,
    this.minutesEstimate,
    this.minutesSpent,
  });

  final int index;
  final PlanDayType type;
  final String? titleNative;
  final String? titleTarget;
  final PlanImage? image;

  /// `#RRGGBB` — им залита плита и кружок 32, пока фото в пути или когда фото нет.
  final String imageTone;
  final WindowDayStatus status;

  /// «≈ 20 минут» — сколько дню ещё нужно; null у пройденного и у дня без карточек.
  final int? minutesEstimate;

  /// «19 минут» — только у пройденного.
  final int? minutesSpent;
  final List<WindowGoal> goals;
}

/// Ряд этапа. [doneCount], [total] и [minutesLeft] не null ТОЛЬКО у текущего — цифра рисуется там и
/// больше нигде.
class WindowStage {
  const WindowStage({
    required this.stage,
    required this.state,
    required this.share,
    this.doneCount,
    this.total,
    this.minutesLeft,
  });

  final PlanStage stage;
  final WindowStageState state;
  final int? doneCount;
  final int? total;
  final int? minutesLeft;

  /// Полоса ряда 0…1.
  final double share;
}

/// Счётчики брови вкладки: всего, пройдено, вернётся завтра.
class WindowSummary {
  const WindowSummary({required this.total, required this.done, required this.returns});

  final int total;
  final int done;
  final int returns;
}

/// Слово или связка — карточка сетки и её шит 23-0e. Всё, что шит пишет, пришло с сервера: как слово
/// читается, что значит, его голос, реплика дня, где оно звучит, и день, когда оно вернётся.
class WindowWord {
  const WindowWord({
    required this.ref,
    required this.term,
    required this.translation,
    required this.imageTone,
    required this.state,
    this.image,
    this.pronunciation,
    this.definition,
    this.audioUrl,
    this.usage,
    this.returnsDay,
  });

  final String ref;
  final String term;
  final String translation;
  final PlanImage? image;
  final String imageTone;
  final WindowUnitState state;

  /// Чтение кириллицей — «эпо́йнтмэнт».
  final String? pronunciation;

  /// Определение на изучаемом языке.
  final String? definition;

  /// Голос слова (голос ученика сцены); null — ещё не озвучено, читает телефон.
  final String? audioUrl;

  /// «В разговоре» — реплика дня со словом; null — слова в диалоге нет, блока нет.
  final WindowUsage? usage;

  /// «вернётся в день N» — только у `returnsTomorrow`.
  final int? returnsDay;
}

/// Реплика дня, где звучит слово: где слово стоит в ней — [offset]/[length] в символах (кодовых
/// точках) [text] — и голос самой реплики.
class WindowUsage {
  const WindowUsage({
    required this.text,
    required this.translation,
    required this.offset,
    required this.length,
    this.audioUrl,
  });

  final String text;
  final String translation;
  final int offset;
  final int length;
  final String? audioUrl;
}

/// Фраза ученика: чтение и голос ученика сцены (DAY-UI-3: озвучено всё).
class WindowPhrase {
  const WindowPhrase({
    required this.ref,
    required this.text,
    required this.translation,
    required this.state,
    this.pronunciation,
    this.audioUrl,
  });

  final String ref;
  final String text;
  final String translation;
  final WindowUnitState state;
  final String? pronunciation;
  final String? audioUrl;
}

/// Пузырь реплики. Голос — у обеих, каждой голосом своего говорящего; состояние — у реплики ученика.
class WindowLine {
  const WindowLine({required this.text, required this.translation, this.audioUrl, this.state});

  final String text;
  final String translation;
  final String? audioUrl;
  final WindowUnitState? state;
}

/// The kind of a dialogue exchange: who speaks first in it.
enum WindowExchangeKind {
  answer,
  ask,
  rescue;

  /// `null` on the wire means the scene's lesson was not at hand — the exchange keeps no kind.
  static WindowExchangeKind? fromWire(Object? s) => switch (s) {
    null => null,
    'answer' => answer,
    'ask' => ask,
    'rescue' => rescue,
    _ => throw PlanContractError('window exchange kind «$s»'),
  };
}

class WindowPair {
  const WindowPair({required this.step, this.kind, this.partner, this.learner});

  final int step;
  final WindowExchangeKind? kind;
  final WindowLine? partner;
  final WindowLine? learner;

  /// In an `ask` and a `rescue` the learner opens the exchange; in an `answer` (and an exchange without a
  /// kind) the partner speaks first.
  bool get learnerFirst => kind == WindowExchangeKind.ask || kind == WindowExchangeKind.rescue;
}

class WindowProgram {
  const WindowProgram({
    required this.words,
    required this.wordsSummary,
    required this.phrases,
    required this.phrasesSummary,
    required this.dialogue,
    required this.dialogueSummary,
  });

  final List<WindowWord> words;
  final WindowSummary wordsSummary;
  final List<WindowPhrase> phrases;
  final WindowSummary phrasesSummary;
  final List<WindowPair> dialogue;
  final WindowSummary dialogueSummary;
}

class DayWindow {
  const DayWindow({
    required this.day,
    required this.stages,
    required this.dayProgress,
    required this.program,
    this.action,
  });

  final WindowDay day;
  final List<WindowStage> stages;

  /// Полоса компактной шапки — доля пройденных этапов.
  final double dayProgress;
  final WindowProgram program;
  final WindowAction? action;

  /// Разбор блока `window`. Нет блока, нет поля, чужое слово — [PlanContractError].
  factory DayWindow.fromJson(Object? json) {
    final j = _map(json, 'window');
    final day = _map(j['day'], 'window.day');
    final program = _map(j['program'], 'window.program');

    return DayWindow(
      day: WindowDay(
        index: _int(day['index'], 'day.index'),
        type: PlanDayType.fromWire(day['type'] as String?),
        titleNative: day['title_native'] as String?,
        titleTarget: day['title_target'] as String?,
        image: PlanImage.fromJson(day['image'] as Map<String, dynamic>?),
        imageTone: _string(day['image_tone'], 'day.image_tone'),
        status: WindowDayStatus.fromWire(day['status']),
        minutesEstimate: (day['minutes_estimate'] as num?)?.toInt(),
        minutesSpent: (day['minutes_spent'] as num?)?.toInt(),
        goals: [
          for (final g in _list(day['goals'], 'day.goals'))
            WindowGoal(text: _string(_map(g, 'goal')['text'], 'goal.text'), passed: _map(g, 'goal')['passed'] == true),
        ],
      ),
      stages: [
        for (final s in _list(j['stages'], 'window.stages')) _stage(_map(s, 'stage')),
      ],
      dayProgress: _share(j['day_progress'], 'window.day_progress'),
      program: WindowProgram(
        words: [for (final w in _items(program, 'words')) _word(_map(w, 'word'))],
        wordsSummary: _summary(program, 'words'),
        phrases: [for (final p in _items(program, 'phrases')) _phrase(_map(p, 'phrase'))],
        phrasesSummary: _summary(program, 'phrases'),
        dialogue: [for (final d in _items(program, 'dialogue')) _pair(_map(d, 'pair'))],
        dialogueSummary: _summary(program, 'dialogue'),
      ),
      action: WindowAction.fromWire(j['allowed_action']),
    );
  }

  static WindowStage _stage(Map<String, dynamic> s) {
    final stage = PlanStage.fromWire(s['stage'] as String?);
    if (stage == PlanStage.unknown) throw PlanContractError('window stage «${s['stage']}»');

    return WindowStage(
      stage: stage,
      state: WindowStageState.fromWire(s['state']),
      doneCount: (s['done_count'] as num?)?.toInt(),
      total: (s['total'] as num?)?.toInt(),
      minutesLeft: (s['minutes_left'] as num?)?.toInt(),
      share: _share(s['share'], 'stage.share'),
    );
  }

  static WindowWord _word(Map<String, dynamic> w) {
    final usage = w['usage'];

    return WindowWord(
      ref: _string(w['ref'], 'word.ref'),
      term: _string(w['term'], 'word.term'),
      translation: _string(w['translation'], 'word.translation'),
      image: PlanImage.fromJson(w['image'] as Map<String, dynamic>?),
      imageTone: _string(w['image_tone'], 'word.image_tone'),
      state: WindowUnitState.fromWire(w['state']),
      pronunciation: _text(w['pronunciation']),
      definition: _text(w['definition']),
      audioUrl: _text(w['audio_url']),
      usage: usage == null ? null : _usage(_map(usage, 'word.usage')),
      returnsDay: (w['returns_day'] as num?)?.toInt(),
    );
  }

  static WindowUsage _usage(Map<String, dynamic> u) => WindowUsage(
    text: _string(u['text'], 'usage.text'),
    translation: _string(u['translation'], 'usage.translation'),
    offset: _int(u['offset'], 'usage.offset'),
    length: _int(u['length'], 'usage.length'),
    audioUrl: _text(u['audio_url']),
  );

  static WindowPhrase _phrase(Map<String, dynamic> p) => WindowPhrase(
    ref: _string(p['ref'], 'phrase.ref'),
    text: _string(p['text'], 'phrase.text'),
    translation: _string(p['translation'], 'phrase.translation'),
    state: WindowUnitState.fromWire(p['state']),
    pronunciation: _text(p['pronunciation']),
    audioUrl: _text(p['audio_url']),
  );

  static WindowPair _pair(Map<String, dynamic> d) {
    final partner = d['partner'];
    final learner = d['learner'];

    return WindowPair(
      step: _int(d['step'], 'pair.step'),
      kind: WindowExchangeKind.fromWire(d['kind']),
      partner: partner == null
          ? null
          : WindowLine(
              text: _string(_map(partner, 'partner')['text'], 'partner.text'),
              translation: _string(_map(partner, 'partner')['translation'], 'partner.translation'),
              audioUrl: _map(partner, 'partner')['audio_url'] as String?,
            ),
      learner: learner == null
          ? null
          : WindowLine(
              text: _string(_map(learner, 'learner')['text'], 'learner.text'),
              translation: _string(_map(learner, 'learner')['translation'], 'learner.translation'),
              audioUrl: _map(learner, 'learner')['audio_url'] as String?,
              state: WindowUnitState.fromWire(_map(learner, 'learner')['state']),
            ),
    );
  }

  static WindowSummary _summary(Map<String, dynamic> program, String tab) {
    final s = _map(_map(program[tab], 'program.$tab')['summary'], '$tab.summary');

    return WindowSummary(
      total: _int(s['total'], '$tab.summary.total'),
      done: _int(s['done'], '$tab.summary.done'),
      returns: _int(s['returns'], '$tab.summary.returns'),
    );
  }

  static List<Object?> _items(Map<String, dynamic> program, String tab) =>
      _list(_map(program[tab], 'program.$tab')['items'], '$tab.items');
}

Map<String, dynamic> _map(Object? v, String what) =>
    v is Map<String, dynamic> ? v : throw PlanContractError('$what is missing');

List<Object?> _list(Object? v, String what) => v is List ? v : throw PlanContractError('$what is missing');

String _string(Object? v, String what) => v is String ? v : throw PlanContractError('$what is missing');

/// Необязательный текст: пустая строка — то же, что его нет.
String? _text(Object? v) => v is String && v.trim().isNotEmpty ? v : null;

int _int(Object? v, String what) => v is num ? v.toInt() : throw PlanContractError('$what is missing');

double _share(Object? v, String what) =>
    v is num ? v.toDouble().clamp(0.0, 1.0) : throw PlanContractError('$what is missing');
