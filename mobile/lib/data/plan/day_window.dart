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

import 'package:flutter/foundation.dart';

import 'conversation/conversation_models.dart' show TalkTarget;
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

/// ОДНА КНОПКА ОКНА — «Начать» или «Продолжить» (наряд FIX-3 §5: дневного `again` больше нет, «ещё раз» живёт у ряда
/// этапа, `stages[].again`). Нет действия — нет кнопки.
enum WindowAction {
  start,
  resume;

  static WindowAction? fromWire(Object? s) => switch (s) {
    null => null,
    'start' => start,
    'continue' => resume,
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

/// Ряд этапа. [doneCount], [total] и [minutesLeft] не null ТОЛЬКО у текущего — цифра «N / M» в рядах не рисуется
/// вовсе (кадры 23-0a…23-0c, серия 38): ряд говорит словами — «впереди», «идёт · ≈ 8 мин», «ещё раз».
class WindowStage {
  const WindowStage({
    required this.stage,
    required this.state,
    required this.share,
    this.doneCount,
    this.total,
    this.minutesLeft,
    this.minutes,
    this.talkTitleNative,
    this.scenesCount,
    this.targets = const [],
    this.again = false,
    this.summary,
  });

  final PlanStage stage;
  final WindowStageState state;
  final int? doneCount;
  final int? total;
  final int? minutesLeft;

  /// THE ROW'S PLANNED MINUTES (`minutes`, наряд BACK-TAILS-2; кадры 23-0a, 37-1, 37-2) — every row's own estimate,
  /// where [minutesLeft] is the current row's remainder. Null — the server did not send it, and no row prints minutes
  /// it was not given.
  final int? minutes;

  /// The talk's row only (CONV-2, п. 12): «Поговори с врачом», the entry's title (кадр 37-5) inflected by the server.
  final String? talkTitleNative;

  /// The talk's row only (CONV-2, п. 12): how many scenes the talk walks — «Разговор целиком · 3 сцены» before it starts.
  final int? scenesCount;

  /// The talk's row only (наряд CLIENT-CONV-1c, архитектор 22.09 — `targets`, BACK-TAILS-2): the phrases the talk is
  /// for, «Скажи в разговоре» on the entry 37-5; `said` false before the day's first talk, the last talk's after it.
  /// Empty — the server sent none, and the entry has no such block.
  final List<TalkTarget> targets;

  /// «ЕЩЁ РАЗ» У РЯДА (`stages[].again`, наряд FIX-3 §8; кадры 23-0c, 30-1): этап карточек — всегда, и у пройденного
  /// дня; разговор — когда этап пройден и повторы дня не исчерпаны. Нет поля — false, и ряд ничего не предлагает.
  final bool again;

  /// ИТОГ ЭТАПА 30-6 (`stages[].summary`, наряд FIX-3 §10) — считает сервер: объём, «с первого раза», возвраты.
  /// Null у ряда разговора и у дня, розданного до наряда.
  final StageSummary? summary;

  /// Полоса ряда 0…1.
  final double share;
}

/// ИТОГ ЭТАПА (кадр 30-6, наряд FIX-3 §10) — числа сервера, клиент ничего не пересчитывает.
class StageSummary {
  const StageSummary({required this.done, required this.total, required this.firstTry, required this.returns});

  final int done;
  final int total;

  /// «С первого раза» — единицы, все карточки которых прошли с первой попытки.
  final int firstTry;

  /// Единицы, которые вернутся завтра.
  final int returns;
}

/// A SCENE A REVIEW OR THE REHEARSAL IS MADE OF (`window.sources[]`, наряд BACK-TAILS-2; кадры 37-1 «Из каких сцен»,
/// 37-2 «Из каких дней») — the server's list, in its order. [dayNumber] — the day of the route the scene stands on.
class WindowSourceRef {
  const WindowSourceRef({required this.sceneId, required this.titleNative, this.dayNumber});

  final String sceneId;
  final String titleNative;
  final int? dayNumber;
}

/// Счётчики брови вкладки: всего, пройдено и сколько единиц ВЕРНУЛОСЬ из прошлых дней (`summary.returns`, наряд
/// FIX-3 §9 — не «вернутся завтра»: то считается по состояниям единиц, [WindowUnitState.returnsTomorrow]).
class WindowSummary {
  const WindowSummary({required this.total, required this.done, required this.returns});

  final int total;
  final int done;
  final int returns;
}

/// ОТКУДА ЕДИНИЦА ПРОГРАММЫ (`items[].source`, наряд FIX-3 §9): своя сцена дня или возврат из прошлого дня.
enum WindowUnitSource {
  own,
  returned;

  static WindowUnitSource fromWire(Object? s) => switch (s) {
    null => own,
    'own' => own,
    'returned' => returned,
    _ => throw PlanContractError('window unit source «$s»'),
  };
}

/// СЦЕНА ЕДИНИЦЫ (`items[].scene`, наряд FIX-3 §9) — сцена плана, откуда она: своя у `own`, прошлая у `returned`.
/// Из неё полоса группы «Вернулось из дня N» (кадр 23-0d · вернулось).
class WindowUnitScene {
  const WindowUnitScene({required this.id, required this.titleNative, this.dayNumber});

  final String id;
  final String titleNative;
  final int? dayNumber;

  static WindowUnitScene? fromWire(Object? v) {
    if (v is! Map<String, dynamic>) return null;
    final id = v['id'];
    final title = v['title_native'];
    if (id is! String || title is! String) return null;

    return WindowUnitScene(id: id, titleNative: title, dayNumber: (v['day_number'] as num?)?.toInt());
  }
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
    this.source = WindowUnitSource.own,
    this.scene,
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

  /// Своя единица дня или возврат из прошлого дня (наряд FIX-3 §9) и сцена, откуда она.
  final WindowUnitSource source;
  final WindowUnitScene? scene;

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
    this.source = WindowUnitSource.own,
    this.scene,
  });

  final String ref;
  final String text;
  final String translation;
  final WindowUnitState state;
  final String? pronunciation;
  final String? audioUrl;

  /// Своя единица дня или возврат из прошлого дня (наряд FIX-3 §9).
  final WindowUnitSource source;
  final WindowUnitScene? scene;
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
  const WindowPair({
    required this.step,
    this.kind,
    this.partner,
    this.learner,
    this.source = WindowUnitSource.own,
    this.scene,
  });

  final int step;
  final WindowExchangeKind? kind;
  final WindowLine? partner;
  final WindowLine? learner;

  /// Своя реплика дня или возврат из прошлого дня (наряд FIX-3 §9).
  final WindowUnitSource source;
  final WindowUnitScene? scene;

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
    this.highlights = const [],
    this.action,
    this.sources = const [],
  });

  final WindowDay day;

  /// Ряды этапов дня, в порядке хода — СКОЛЬКО ИХ, РЕШАЕТ СЕРВЕР (наряд CONV-1): шесть у дня с
  /// разговором, пять у дня, розданного до него, два у репетиции.
  final List<WindowStage> stages;

  /// Полоса компактной шапки — доля пройденных этапов.
  final double dayProgress;
  final WindowProgram program;

  /// «ЧТО БЫЛО ХОРОШО» (кадр 30-7) — две-три ГОТОВЫЕ строки пройденного дня: сервер их и считает, и
  /// склоняет, клиент печатает по порядку. Пусто, пока день не пройден, и без строк разговора у дня,
  /// которого он не нёс.
  final List<String> highlights;
  final WindowAction? action;

  /// «Из каких сцен» / «Из каких дней» of the rehearsal and a review (кадры 37-1, 37-2) — the server's list
  /// (`window.sources[]`, BACK-TAILS-2). Empty — no field or nothing in it, and the list is not drawn: the client does
  /// not work out where a day comes from.
  final List<WindowSourceRef> sources;

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
        for (final s in _list(j['stages'], 'window.stages')) ?_stage(_map(s, 'stage')),
      ],
      // ADDITIVE, unlike the rest of the block: a window without «Что было хорошо» is a window with
      // nothing to praise yet, not a window that failed to load. There is no state here to guess.
      highlights: [
        for (final h in (j['highlights'] as List?) ?? const [])
          if (h is String && h.trim().isNotEmpty) h,
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
      // BACK-TAILS-2's fields are ADDITIVE, like the highlights: a window without them is a window from before them.
      sources: [
        for (final s in (j['sources'] as List?) ?? const [])
          if (s is Map<String, dynamic> && s['scene_id'] is String && s['title_native'] is String)
            WindowSourceRef(
              sceneId: s['scene_id'] as String,
              titleNative: s['title_native'] as String,
              dayNumber: (s['day_number'] as num?)?.toInt(),
            ),
      ],
    );
  }

  /// A stage this build has never heard of is SKIPPED (наряд CLIENT-CONV-1a): the day's composition
  /// is the server's, and a window that refuses to load over one unknown row would hide the five
  /// rows it does understand — the row is not drawn, and the debug log names it (наряд CLIENT-CONV-1c).
  /// Everything else about a row stays closed.
  static WindowStage? _stage(Map<String, dynamic> s) {
    final stage = PlanStage.fromWire(s['stage'] as String?);
    if (stage == PlanStage.unknown) {
      debugPrint('[day-window] stage «${s['stage']}» is unknown to this build — its row is not drawn');
      return null;
    }

    return WindowStage(
      stage: stage,
      state: WindowStageState.fromWire(s['state']),
      doneCount: (s['done_count'] as num?)?.toInt(),
      total: (s['total'] as num?)?.toInt(),
      minutesLeft: (s['minutes_left'] as num?)?.toInt(),
      minutes: (s['minutes'] as num?)?.toInt(),
      talkTitleNative: _text(s['talk_title_native']),
      scenesCount: (s['scenes_count'] as num?)?.toInt(),
      targets: TalkTarget.listOf(s['targets']),
      again: s['again'] == true,
      summary: _stageSummary(s['summary']),
      share: _share(s['share'], 'stage.share'),
    );
  }

  /// Итог этапа 30-6 — только целиком: ряд без него (разговор, день до наряда) числа не рисует.
  static StageSummary? _stageSummary(Object? v) {
    if (v is! Map<String, dynamic>) return null;
    final done = v['done'];
    final total = v['total'];
    final firstTry = v['first_try'];
    final returns = v['returns'];
    if (done is! num || total is! num || firstTry is! num || returns is! num) return null;

    return StageSummary(
      done: done.toInt(),
      total: total.toInt(),
      firstTry: firstTry.toInt(),
      returns: returns.toInt(),
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
      source: WindowUnitSource.fromWire(w['source']),
      scene: WindowUnitScene.fromWire(w['scene']),
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
    source: WindowUnitSource.fromWire(p['source']),
    scene: WindowUnitScene.fromWire(p['scene']),
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
      source: WindowUnitSource.fromWire(d['source']),
      scene: WindowUnitScene.fromWire(d['scene']),
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
