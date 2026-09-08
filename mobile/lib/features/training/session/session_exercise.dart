import 'dart:async';

import 'package:flutter/foundation.dart' show ValueListenable;
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';
import 'package:eng_std/l10n/app_localizations.dart';

import '../../../data/languages.dart'
    show keyboardLocaleFor, languageAdjectiveFor, languageAdverbFor, looksLikeWrongKeyboard;
import '../../../data/local/app_database.dart';
import '../../../data/local/cached_image_provider.dart';
import '../../../data/models.dart';
import '../../../data/perf_log.dart';
import '../../../data/practice/practice_mode_selector.dart' show TermPlayability;
import '../../../data/providers.dart';
import '../../../data/speech/speech_diagnostics.dart';
import '../../profile/qa_speech_view.dart';
import '../../../data/speech/speech_recognizer.dart';
import '../../../data/speech/speech_grading_config.dart';
import '../../../data/speech/speech_turn.dart';
import 'session_grading.dart';
import 'spoken_line.dart';

/// The wrong-keyboard hint, by name — «похоже, раскладка не та».
///
/// A key rather than a text match because what the test is pinning is that NOTHING WAS GRADED: the
/// line's presence and the untouched attempt are one fact, and a finder that went looking for the
/// copy would start failing the day the copy was reworded.
const Key sessionWrongKeyboardKey = Key('session-wrong-keyboard');

/// Where an `error_span` really sits in its sentence: the first occurrence that is not buried inside
/// a longer word, falling back to a plain search when no standalone one exists.
///
/// A plain `indexOf` is wrong for exactly the spans that matter most — the short function words.
/// «on» is inside «resp**on**sible», «in» is inside «**in**tegrate»: underlining the first raw match
/// draws the wavy line through the middle of an unrelated word and tells the learner the mistake is
/// there. Mirrors the server's EnrichmentValidator.spanPosition(), which uses the same rule to decide
/// whether a distractor's own repair reproduces the example — the two must agree about which
/// occurrence the span means, or the card underlines one place and was validated about another.
///
/// Also used by the intro card, which bolds the TERM inside its example: same question («where does
/// this fragment really sit»), same answer required, so the same function rather than a second one
/// that would drift.
int spanPositionIn(String sentence, String span) {
  final haystack = sentence.toLowerCase();
  final needle = span.toLowerCase();
  final isWordChar = RegExp(r'[\p{L}\p{N}]', unicode: true);

  var from = 0;
  while (true) {
    final at = haystack.indexOf(needle, from);
    if (at < 0) break;
    final before = at > 0 ? haystack[at - 1] : ' ';
    final afterAt = at + needle.length;
    final after = afterAt < haystack.length ? haystack[afterAt] : ' ';
    if (!isWordChar.hasMatch(before) && !isWordChar.hasMatch(after)) return at;
    from = at + 1;
  }

  return haystack.indexOf(needle);
}

/// The term as it is SEARCHED FOR inside its own example: trailing sentence punctuation dropped.
///
/// A sentence-like term carries its own final mark — «I have a fever.» — and the example that
/// teaches it embeds the sentence in a bigger one: «I have a fever and feel very weak.» The term
/// with its full stop occurs nowhere in that string, so the search failed and the intro card fell
/// back to plain text. Single-word terms never showed it, because a word has no trailing mark;
/// that is why the bolding looked like it worked.
///
/// Only the TAIL is normalised, and only for the search: the term is still drawn on the card
/// exactly as it is stored. The server's regenerate-example validation asks the same question with
/// the same normalisation, so «does the example contain the term» has ONE answer on both sides.
String termSearchForm(String term) {
  final trimmed = term.trim();
  final stripped = trimmed.replaceFirst(RegExp(r'[.?!,…]+$'), '').trimRight();

  // A term that is nothing but punctuation would normalise to nothing findable — keep it as it is
  // rather than searching for the empty string, which matches at position 0 and bolds nothing.
  return stripped.isEmpty ? trimmed : stripped;
}

/// One committed answer, handed up to the shell so it can record the RAW review (the server
/// grades it) and tally the summary. [verdict] is the client's instant read — feedback only.
class SessionAnswer {
  const SessionAnswer({
    required this.response,
    required this.verdict,
    required this.usedHint,
    required this.latencyMs,
    this.listenedMs,
  });

  final String response;
  final LocalCheck verdict;
  final bool usedHint;
  final int? latencyMs;

  /// СКОЛЬКО ПРОШЛО ОТ НАЧАЛА ПРОСЛУШИВАНИЯ ДО ОТВЕТА, или null вне говорения.
  ///
  /// Не [latencyMs], и это разные вопросы: латентность меряется от появления карточки и включает
  /// чтение подсказки, а «сразу» канона §4 — про то, как быстро человек начал ГОВОРИТЬ. Ход,
  /// который слушали пятнадцать секунд и на четырнадцатой услышали, — успех, но не быстрый.
  final int? listenedMs;
}

/// A card the learner LEFT rather than answered: the speaking trainer's channel skip.
///
/// It is a separate callback from [SessionAnswer] and not a verdict inside it, because the two are
/// different KINDS of event and the difference is the whole point of the trainer. An answer is
/// evidence about memory; a skip is a statement about a microphone, and it must never become a
/// verdict — no `_results` row, no tick, no cross.
///
/// What the SHELL does with it depends on where the card is standing, and that decision belongs
/// there rather than here: outside a plan the skip reaches nothing at all, and inside one it closes
/// the checklist step as a lapse, because a step nothing closes is a day that never passes
/// ({@see _SessionShellState._skipCard}).
typedef SessionSkipped = void Function();

/// Drop a term's decoded photo once its card is well behind, so a session's photos don't pile up.
/// A GLOBAL cache cap is the wrong tool here — it evicts by LRU, which is free to throw away the
/// card we just warmed up, and a second practice pass in the same app launch runs straight into the
/// ceiling (measured: ~84 MB by the end of one pass, 100 MB limit). Evicting explicitly from behind
/// bounds the footprint to the cards actually in play and can never touch the look-ahead (F20-r).
Future<void> evictSessionImage(BuildContext context, String? url) async {
  if (url == null || url.isEmpty) return;
  await ResizeImage(CachedNetworkImage(url), width: promptPhotoCacheWidth(context)).evict();
}

/// The pixel width the prompt photo is decoded to — the on-screen banner width (F20). Shared by the
/// live banner and the shell's precache so both hit the SAME image-cache entry.
int promptPhotoCacheWidth(BuildContext context) {
  final mq = MediaQuery.of(context);
  return (mq.size.width * mq.devicePixelRatio).round();
}

/// Warm-decode a term's prompt photo at the display width so an upcoming card transition meets an
/// already-decoded image instead of a cold ~4000px decode + GPU upload mid-slide (F20). Fire-and-
/// forget; a null/empty url or a decode error is ignored.
Future<void> precacheSessionImage(BuildContext context, String? url) async {
  if (url == null || url.isEmpty) return;
  final provider = ResizeImage(CachedNetworkImage(url), width: promptPhotoCacheWidth(context));
  try {
    await precacheImage(provider, context);
  } catch (_) {
    // a broken image must never take down the session
  }
}

/// The sliding content of one session card: the prompt, the mode-specific interaction, and — once
/// answered — the feedback that expands in the bottom of the same card (§4е). Owns its own
/// answering→feedback state; the shell owns the header, progression and recording.
class SessionExerciseCard extends ConsumerStatefulWidget {
  const SessionExerciseCard({
    super.key,
    required this.card,
    required this.autoPronounce,
    required this.onAnswered,
    required this.onSpeak,
    this.onSkipped,
    required this.speechLocaleId,
    required this.answerLang,
    this.isCurrent = _alwaysCurrent,
    this.photoUrl,
    this.photoResolved = false,
    this.showDue = true,
    this.situation,
    this.sayIntent,
    this.inDialogue = false,
    this.sceneRun,
    this.speech = SpeechGradingConfig.empty,
    this.roleSpeaking,
    this.roleLineText,
  });

  static bool _alwaysCurrent() => true;

  /// ДИНАМИК ЕЩЁ ГОВОРИТ — реплика собеседника играет (наряд DAY-FIX-3, Ч.1.1).
  ///
  /// Пока `true`, микрофон прогона не открывается: открытый поверх голоса собеседника, он писал
  /// начало его реплики в транскрипт человека (диагностика 06.09, п. 1). Ставит и снимает флаг
  /// СЕССИЯ — она владеет голосом; карточка только ждёт. Null — звука нет, микрофон открывается
  /// сразу после слайда.
  final ValueListenable<bool>? roleSpeaking;

  /// ТЕКСТ РЕПЛИКИ РОЛИ, которая только что прозвучала, — для эхо-замка (Ч.1.5). Склейка, в
  /// которой узнана эта реплика и не узнан ключ, выбрасывается, а не пишется ошибкой. Null вне
  /// разговора.
  final String? roleLineText;

  final SessionCard card;
  final bool autoPronounce;

  /// The term's photo, already looked up by the shell (which needs it for the warm-up anyway).
  /// [photoResolved] true means the lookup FINISHED — the banner then knows its own height from
  /// the first frame instead of reserving 150 px and collapsing a moment later (F20-r: that
  /// collapse was a 164 px layout jump right after the slide, which read as a stutter).
  final String? photoUrl;
  final bool photoResolved;

  /// Whether the feedback may show «увидишь снова через N дней». Free practice NEVER schedules, so
  /// the line would report the term's OLD due date as if this answer had set it — the summary
  /// already hides it for the same reason (Training Loop v2 / F17).
  final bool showDue;

  /// True while this card is still the on-screen one. Deferred side-effects (autopronounce, listening
  /// autoplay, keyboard focus) check it so a fast «Дальше-Дальше» that leaves the card before the
  /// post-transition callback fires cancels the effect instead of firing it on the next card (F20).
  final bool Function() isCurrent;

  /// ЭТО ХОД ПРОГОНА СЦЕНЫ — ступень C, и её секунды (наряд SCENE-RUN, Ч.2).
  ///
  /// Null у всего остального, включая обычную карточку говорения: прогон отличается от неё не
  /// тренажёром, а тем, что с экрана убрали. На карточке остаётся подсказка на языке поддержки и
  /// микрофон; ни ключа реплики, ни фотографии, ни самой реплики — иначе ступень C была бы чтением
  /// вслух под другим именем.
  ///
  /// Секунды приходят с сервера ({@see SceneRunKnobs}), а не лежат константами здесь: сколько
  /// человек думает — продуктовое суждение, и оно обязано двигаться без выката приложения.
  final SceneRunKnobs? sceneRun;

  /// ЧЕМ СУДИТЬ РЕЧЬ — пороги и таблица аббревиатур, приехавшие с сервера (наряд SPEECH-2,
  /// Ч.3.3 / Ч.4.2). Своих чисел у карточки нет и быть не может: экран и сервер судят одной
  /// функцией по одним порогам, иначе телефон печатает «Не то» над ответом, который журнал в ту же
  /// секунду засчитывает верным.
  final SpeechGradingConfig speech;

  /// Called exactly once, when the user commits their answer. The shell then reveals the pinned
  /// «Дальше» bar (advancing lives on the shell, not in the card — device-batch F9).
  final ValueChanged<SessionAnswer> onAnswered;

  /// Called instead of [onAnswered] when the learner gives up on the MICROPHONE — the speaking
  /// trainer's channel skip. The shell moves to the next card and records nothing anywhere.
  final SessionSkipped? onSkipped;

  /// The recognition locale for the speaking card — the language being LEARNED, not the interface
  /// language: the learner is speaking Italian on an Italian card, whatever the app is written in
  /// and whatever the profile says. Required, with no default: a constant here would listen for one
  /// language while the card asks another (MIX-1b).
  final String speechLocaleId;

  /// The two-letter code of the language the ANSWER is written in — this card's studied side, the
  /// same value [speechLocaleId] is built from and the same one the pronouncer speaks in.
  ///
  /// It does two things on a typed card: it asks the keyboard to open in that language, and it is
  /// what [looksLikeWrongKeyboard] judges the answer against. Required, with no default, for the
  /// reason [speechLocaleId] is: a session mixes pairs by design (DECISIONS п. 128), so a constant
  /// here would judge an Italian answer by English's alphabet.
  final String answerLang;

  /// THE POSITION a situational card puts the learner in — «Хозяин спросил про залог».
  ///
  /// It rides on the PLAN's envelope rather than on the card, because it is a fact about the day and
  /// the card is the app's ordinary card ({@see PlanSessionEnvelope.situationAt}). Null on every
  /// other trainer, and on a situational card met outside a plan — where the options alone are drawn
  /// and the card degrades to the ordinary choice it is underneath.
  final PlanSituation? situation;

  /// ЧТО ИМЕННО НАДО СКАЗАТЬ, на языке поддержки — только на карточке СБОРКИ (наряд DAY-GATE-1,
  /// Ч.2.4), и `null` везде ещё, включая карточку выбора: там перевод реплики назвал бы правильный
  /// вариант, то есть карточка ответила бы на собственный вопрос.
  ///
  /// Строка серверная (`task.intent`), префикс «Скажи:» клиентский. Она отвечает на вопрос, который
  /// сборка задаёт молча: блоки лежат на изучаемом языке, и без неё человек собирает фразу, не зная,
  /// что он собирает.
  final String? sayIntent;

  /// ЭТА КАРТОЧКА ИГРАЕТСЯ РАЗГОВОРОМ — она стоит внутри оболочки диалога (серия «Диалог v1»).
  ///
  /// Меняется от этого ровно одно: карточка перестаёт подписывать САМА СЕБЯ. Такт над ней уже задал
  /// крупный русский вопрос — «Что тебе сейчас сказали?», «Что ты ответишь?» — а реплику проигрывает
  /// и повторяет пузырь. Своя серая строка «фраза · выбери, что ответишь» и своя кнопка
  /// воспроизведения были бы вторым ответом на тот же вопрос, тише и мельче первого; живьём владелец
  /// читал именно её и не понимал, какой из двух тактов перед ним (наряд DAY-2-FIX, Ч.1.1).
  ///
  /// Механика не меняется НИЧЕМ: те же варианты, та же оценка, та же лестница.
  final bool inDialogue;

  /// Pronounce a target-language string via the shell's TTS (respects the auto-pronounce toggle
  /// at call sites; here it's an explicit speak). [slow] backs the listening «замедленно» replay.
  final Future<void> Function(String text, {bool slow}) onSpeak;

  @override
  ConsumerState<SessionExerciseCard> createState() => _SessionExerciseCardState();
}

class _SessionExerciseCardState extends ConsumerState<SessionExerciseCard> {
  final _shownAt = DateTime.now(); // ~paint time, for latency
  final _input = TextEditingController();
  final _focus = FocusNode();

  bool _answered = false;
  LocalCheck? _verdict;

  /// ВЕРДИКТ ПРО СКАЗАННОЕ, целиком — со списком «не хватило», покрытием и применённым порогом
  /// (наряд SPEECH-2, Ч.3.5). Null на всём, что не судится речью: там [_verdict] — весь ответ.
  SpokenVerdict? _spoken;

  /// В КАКОМ СОСТОЯНИИ КНОПКА МИКРОФОНА (наряд SPEECH-2, Ч.1.1). Начальное — «твоя очередь»:
  /// карточка говорения открывается готовой слушать, но НЕ пишущей. В разговоре, где перед ходом
  /// звучит реплика роли, оно на время становится [MicState.waiting].
  MicState _micState = MicState.yourTurn;

  /// What the learner actually answered, kept so a WRONG verdict can show it back to them. Cloze
  /// used to replace it with the correct form, which erased the one thing there was to compare
  /// against (QA-16).
  String? _response;
  bool _usedHint = false;
  String? _picked; // multiple_choice / listening-recognition
  late final List<String> _chips = List.of(widget.card.chips ?? const []);
  final List<int> _placed = []; // indices into _chips, in assembled order

  SessionCard get _card => widget.card;
  ExerciseMode get _mode => _card.mode;

  bool get _isListening => _mode == ExerciseMode.listening;
  bool get _isCloze => _mode == ExerciseMode.cloze;
  bool get _isScramble => _mode == ExerciseMode.scramble;
  bool get _isDictation => _mode == ExerciseMode.dictation;
  bool get _isSpeaking => _mode == ExerciseMode.speaking;

  /// «Тебе скажут» on the situational trainer: the line is PLAYED and the options are meanings.
  bool get _isSituationalHear => _mode == ExerciseMode.situationalHear;

  /// The two speak shelves — a position, replies to tap, and the chosen one said out loud.
  bool get _isSituationalSpeak =>
      _mode == ExerciseMode.situationalSay || _mode == ExerciseMode.situationalAsk;

  /// ХОД ПРОГОНА СЦЕНЫ — ступень C: подсказка на языке поддержки и микрофон, больше ничего.
  bool get _isSceneRun => widget.sceneRun != null && _isSpeaking;

  /// Когда открылся микрофон ЭТОЙ попытки — от него меряется «сразу» (канон §4, ≤ 3 с). С Ч.1.1
  /// микрофон открывается ПОСЛЕ реплики собеседника, поэтому «сразу» больше не включает её.
  DateTime? _listenStartedAt;

  /// B+ · СБОРКА — тот же ход, но реплики целиком на экране больше нет (наряд SCENE-RUN, Ч.1).
  ///
  /// Читается по СОДЕРЖИМОМУ карточки, а не по новому режиму: сервер кладёт блоки вместо вариантов,
  /// и это единственная разница. Уровень едет и отдельным полем (`turn_level`), но решает здесь
  /// именно карточка — экран рисует то, что ему прислали, а не то, что он вывел из подписи; иначе
  /// «уровень сборка» и «блоков нет» дали бы пустой экран вместо задания.
  ///
  /// Клавиатуры не появляется ни при каком уровне: канон §9 — ни один обязательный шаг не требует
  /// системной клавиатуры изучаемого языка.
  bool get _isAssembleTurn => _isSituationalSpeak && _chips.isNotEmpty;

  // ── speaking ───────────────────────────────────────────────────────────────
  // The channel state, kept apart from the answering state above on purpose: `_attempts` counts
  // failures of the MICROPHONE, never wrong answers. A recognised answer is a verdict on the first
  // try like every other trainer; only silence is retried.

  bool _listeningNow = false;
  String _partial = '';
  int _attempts = 0;

  /// The last channel failure, shown as a quiet line rather than as a verdict. Null once something
  /// is heard, so a successful retry clears the apology.
  SpeechOutcome? _channelFailure;

  /// ОБРЫВ НА ПОЛУСЛОВЕ (наряд DAY-FIX-3, Ч.1.4): канал упал после того, как человек начал
  /// говорить. Не ошибка и не «не помню» — «Не расслышали до конца — скажи ещё раз», журнал не
  /// пишется, попытка вторая. Снимается следующим прослушиванием.
  bool _cutOff = false;

  /// Resolved once, in [initState], and never through `ref` again — `dispose` has to close the
  /// microphone, and reading a provider from a widget that is already coming down is not allowed.
  SpeechRecognizer? _recognizer;

  /// Журнал и живое состояние канала (наряд DAY-GATE-1, Ч.0.1). Резолвится там же и по той же
  /// причине, что и [_recognizer].
  SpeechDiagnostics? _diagnostics;

  /// Последний ответ ОС про разрешения и распознаватель языка карточки. Null, пока канал не падал:
  /// спрашивать до отказа незачем, а спрашивать после — единственный способ отличить «нет
  /// разрешения» от «нет движка».
  SpeechProbe? _probe;

  /// ОДИН ДВИЖОК СЛУШАНИЯ НА ВСЕ КАРТОЧКИ ГОВОРЕНИЯ ({@see SpeechTurn}, Ч.1): склейка,
  /// закрытие по тишине после речи или по потолку речи, сторож от открытия, эхо-замок. Карточка
  /// не разбирает результаты плагина сама — она получает ОДИН исход хода.
  SpeechTurn? _turn;

  /// Таймер отложенной подстановки транскрипта (дев-дверь QA): «сказал, но не сразу» ждёт столько,
  /// сколько ждал бы человек. Больше ни для чего не нужен — микрофон сам не переоткрывается.
  Timer? _reopenTimer;

  /// Текст, который дев-дверь QA просила подставить, пока микрофон был закрыт. Кладётся в открытый
  /// ход самим [_listenOnce] — см. {@see _substituteTranscript}.
  String? _pendingInjection;

  /// May the learner set this card aside?
  ///
  /// Offered once the microphone has actually let them down — an escape hatch that appears before
  /// it is needed reads as «this probably won't work» — but from the FIRST failure, not the third
  /// (QA-OBS-7). The card already says «микрофон недоступен, карточку можно пропустить» on that
  /// first failure, and it said it with no button on screen: between the promise and the button sat
  /// two more useless taps whose only other exit was «Не помню» — a LAPSE, i.e. the scheduler
  /// punishing the learner for hardware they were never given. The message and the button are the
  /// same fact and now appear together.
  ///
  /// [_channelFailure] is cleared while a retry is listening, so the attempt count is kept as the
  /// second half: once the budget is spent the escape hatch stays put instead of blinking away
  /// under the finger.
  bool get _canSkip =>
      // В ПРОГОНЕ «ПРОПУСТИТЬ» ДОСТУПНО ВСЕГДА (наряд DAY-FIX-3, Ч.1.7): ход проходится голосом,
      // спасателем или пропуском, и выход не должен появляться по таймеру — молчание это законный
      // ход человека, который не вспомнил, а не поломка железа (SCENE-RUN, Ч.2.4).
      _isSceneRun ||
      (widget.onSkipped != null &&
          (_channelFailure != null || _attempts >= SpokenAnswer.maxChannelAttempts));

  /// The words the recogniser is listening for, and what the answer is graded against.
  ///
  /// У реплики плана — ключ и его упрощённые формы (`speaking_keys`, Ч.1.6): любая из них
  /// засчитывается, и распознавателю подсказываются все.
  List<String> get _spokenTargets => _card.spokenTargets.isNotEmpty
      ? _card.spokenTargets
      : (_card.asksForExample ? [_card.answer] : [_card.answer, ..._card.acceptedVariants]);

  /// ЧИСЛА ЭТОГО ХОДА. Одни на все карточки говорения (Ч.1.3); прогон сцены приносит свой сторож
  /// с сервера (`listen_seconds`), потому что сколько человек думает над репликой — суждение
  /// продукта, которое двигают без выката.
  SpeechTurnConfig get _turnConfig {
    final knobs = widget.sceneRun;
    const base = SpeechTurnConfig();

    // `listen_seconds` ТЕПЕРЬ ОГРАНИЧИВАЕТ ВСЮ ЗАПИСЬ (наряд SPEECH-2, Ч.2.1), а не ожидание
    // первого слова: ждать больше нечего — запись начинается по нажатию. Пол в 15 секунд стоит в
    // самом движке ({@see SpeechTurnConfig.minMaxRecording}).
    return knobs == null
        ? base
        : base.copyWith(maxRecording: Duration(seconds: knobs.listenSeconds));
  }

  /// «В СКЛЕЙКЕ УЗНАНА РЕПЛИКА РОЛИ» — эхо динамика (Ч.1.5): покрытие её слов ≥ порога конфига.
  bool _isEcho(String transcript) {
    final line = widget.roleLineText?.trim() ?? '';
    if (line.isEmpty) return false;

    return SessionGrader.coverageOf(transcript, line, ignoreArticles: true) >= _turnConfig.echoCoverage;
  }

  /// Is this card's spoken answer judged by coverage rather than by equality (QA-22)? The one
  /// «длинность» rule, from the one place that defines it.
  bool get _gradesByCoverage =>
      SpokenAnswer.gradesByCoverage(asksForExample: _card.asksForExample, term: _card.answerText);

  /// The term's local mirror row, needed ONLY to get its plain headword (`termText`) for
  /// [_contextualStrings] on the example form — [SessionCard] carries the example sentence but not
  /// the bare term separately from it once [SessionCard.asksForExample] folds them together. Same
  /// lazy, once-per-card, DB-not-network pattern as `_PromptPhotoState._term` below.
  late final Future<Term?> _term = ref.read(appDatabaseProvider).termById(_card.termId);

  /// The vocabulary hint sent as `contextualStrings` (see [SpeechRecognizer.listenOnce]'s doc) —
  /// what the recogniser should expect to hear, distinct from [_spokenTargets] which also drives
  /// grading and the taskHint. Word-form: the term whole, plus its individual words (a multi-word
  /// term needs both — the recogniser sometimes matches best on a fragment). Example-form: the
  /// UNIQUE words of the reference example, plus the term itself — the sentence alone does not
  /// always spell the term the way its dictionary form would (inflection, capitalisation).
  /// Deduplicated and capped at 50 entries; never empty strings.
  Future<List<String>> _contextualStrings() async {
    final strings = <String>{};
    void add(String? text) {
      final trimmed = (text ?? '').trim();
      if (trimmed.isNotEmpty) strings.add(trimmed);
    }

    if (_card.asksForExample) {
      for (final word in _card.answer.trim().split(RegExp(r'\s+'))) {
        add(word);
      }
      add((await _term)?.termText);
    } else {
      add(_card.answer);
      for (final word in _card.answer.trim().split(RegExp(r'\s+'))) {
        add(word);
      }
    }

    return strings.take(50).toList();
  }

  /// Is this word_bank card dealt LETTER chips rather than word ones?
  ///
  /// The trainer has two shapes and always has: a phrase is assembled from its words, a single word
  /// from its letters — the branch that was unreachable until the gate stopped asking for two WORDS
  /// (BUGFIX-2 Ч.2б D2). Read off the ANSWER rather than off the chips, because a phrasal verb's
  /// word chips come with decoy particles and counting chips would call it letters.
  bool get _assemblesLetters =>
      _mode == ExerciseMode.wordBank && TermPlayability.wordsIn(_card.answerText) < 2;

  /// A listening card with options is recognition (12g); without, production/typing (12h). The
  /// backend currently sends no options for listening, so this is the typed path — but it stays
  /// forward-compatible if options ever arrive.
  bool get _isRecognitionListening => _isListening && (_card.options?.isNotEmpty ?? false);

  Timer? _deferTimer;
  Timer? _speakTimer;
  Timer? _settleTimer;

  /// True once the slide-in has finished. A photo that is NOT already decoded waits for this before
  /// fading in: a picture that materialises mid-transition is exactly what reads as a lag, even
  /// though every frame is delivered on time (F20-r — the janky-looking cards had zero late frames).
  /// A cached photo ignores this and shows instantly, which is the original F20 win.
  bool _settled = false;

  @override
  void initState() {
    super.initState();
    if (_isSpeaking) {
      _recognizer = ref.read(speechRecognizerProvider);
      _diagnostics = ref.read(speechDiagnosticsProvider);
    }
    _settleTimer = Timer(AppMotion.nextTaskEnter + const Duration(milliseconds: 30), () {
      if (mounted) setState(() => _settled = true);
    });
    // F20: side-effects that used to fire DURING the card's slide-in (and stalled it) now run AFTER
    // the transition settles, and are cancelled if the card is no longer current (fast «Дальше»).
    if (_mode.isHeard) {
      // A heard card plays on appearance — the text is never shown, only spoken. The audio IS the
      // card's content, so it must NOT wait out the whole slide: a full 250 ms of silence on a
      // listen-and-type card reads as the app hanging (F20-r — the user called it a lag on a
      // transition where every frame was on time). The shell pre-warms the audio session, so
      // speak() is now a single channel call and one frame of headroom is enough to keep it off
      // the transition's first, heaviest frame. Dictation joins listening here unchanged: the only
      // difference is the length of what is spoken.
      _afterTransition(
        () => widget.onSpeak(_card.answerText),
        delay: const Duration(milliseconds: 100),
      );
    } else if (_mode == ExerciseMode.typing || _isCloze) {
      // Raise the keyboard after the slide, not during it (the keyboard-attach channel call was a
      // per-transition stall). Field autofocus is off; we request focus here instead.
      _afterTransition(() => _focus.requestFocus());
    }
    // Cloze types straight INTO the blank (кадр 12j): the hidden field captures the keyboard, and
    // the sentence's blank shows the letters as they're typed. Rebuild the sentence on each change.
    if (_isCloze) {
      _input.addListener(_onClozeInput);
    }
    // МИКРОФОН НЕ ОТКРЫВАЕТСЯ САМ (наряд SPEECH-2, Ч.1.1). Карточка только ЗОВЁТ: кнопка переходит
    // в «твоя очередь», а запись начинается по нажатию.
    //
    // Что здесь было и почему ушло. Прогон слушал сам, по концу реплики собеседника — «в жизни
    // собеседник договаривает, и ты отвечаешь, а не жмёшь запись». Замысел правильный, а на
    // устройстве 08.09 он выглядел так: человек ещё не собрался, а его уже пишут, сторож тикает, и
    // первое, что слышит микрофон, — вдох. В тренажёре человек имеет право подумать, и отнимать
    // это право ради реализма — плохая сделка.
    if (_isSpeaking) {
      // Начальное состояние ставится ПОЛЕМ, а не `setState`: до первой сборки его ещё некому
      // перерисовать. Дальше состояние двигает только [_setMicState].
      final speaking = widget.roleSpeaking;
      _micState = speaking != null && speaking.value ? MicState.waiting : MicState.yourTurn;
      _waitForRoleThenInvite();
    }
  }

  /// ПОЗВАТЬ, КОГДА ДИНАМИК ЗАМОЛЧИТ (Ч.1.1) — сразу, если он молчит уже.
  ///
  /// Кнопка не зовёт поверх чужой реплики: тап посреди неё записал бы динамик. Со страховкой —
  /// голос, который не сказал, что кончил ([_roleWaitCap]), не имеет права держать ход запертым.
  void _waitForRoleThenInvite() {
    final speaking = widget.roleSpeaking;
    if (speaking == null || !speaking.value) {
      _invite();

      return;
    }
    _setMicState(MicState.waiting, phase: SpeechPhase.waitingForRole);
    void onQuiet() {
      if (speaking.value) return;
      speaking.removeListener(onQuiet);
      _quietListener = null;
      _roleWaitTimer?.cancel();
      if (mounted && widget.isCurrent() && !_answered) _invite();
    }

    speaking.addListener(onQuiet);
    _quietListener = onQuiet;
    _roleWaitTimer?.cancel();
    _roleWaitTimer = Timer(_roleWaitCap, () {
      if (_quietListener == null) return;
      speaking.removeListener(onQuiet);
      _quietListener = null;
      if (mounted && widget.isCurrent() && !_answered) _invite();
    });
  }

  /// «ТВОЯ ОЧЕРЕДЬ» — состояние БЕЗ ТАЙМАУТА (Ч.1.2). Ничего не заводится: ни сторож, ни микрофон.
  void _invite() => _setMicState(MicState.yourTurn, phase: SpeechPhase.yourTurn);

  void _setMicState(MicState state, {SpeechPhase? phase}) {
    if (phase != null) _diagnostics?.phaseIs(phase);
    if (_micState == state) return;
    _micState = state;
    if (mounted) setState(() {});
  }

  VoidCallback? _quietListener;
  Timer? _roleWaitTimer;

  /// Дольше этого реплика собеседника не звучит — потолок ожидания динамика.
  static const _roleWaitCap = Duration(seconds: 15);

  /// ПОДСТАВИТЬ ТРАНСКРИПТ ВМЕСТО ГОЛОСА — дев-дверь QA (наряд SCENE-RUN, Ч.2.9).
  ///
  /// На симуляторе микрофона нет, и без этой двери ступень C нечем пройти живьём: прогон сцены
  /// невозможно ни снять, ни принять. Дверь та же, что у входа без пароля, и решает её СЕРВЕР
  /// ({@see AppUser.qaTools}): аккаунт помечен `is_qa` И среда не production. В релизной сборке у
  /// боевого аккаунта поле всегда `false`, поэтому кнопок здесь не бывает.
  ///
  /// Что она делает: кладёт готовый текст в тот же путь, которым уходит услышанное, и подделывает
  /// ОДНУ вещь сверх текста — момент начала прослушивания, потому что «сразу» это про время, а
  /// подстановка мгновенна по построению. Без этого любой подставленный ход был бы «сразу», и
  /// проверить «сказал, но медленно» стало бы нечем.
  ///
  /// Дверь стоит на ЛЮБОЙ карточке говорения, а не только в прогоне (наряд DAY-FIX-2, Ч.7):
  /// спасатель, дошедший до ступени «скажи вслух», встаёт в разогрев дня, и без микрофона его не
  /// пройти иначе как «Не помню» — а это ошибка в append-only журнале и возврат карточки. Вне
  /// прогона секунд «сразу» нет, и подделывать нечего: подставляется только текст.
  void _substituteTranscript(String text, {required bool fast}) {
    if (_answered) return;
    final knobs = widget.sceneRun;

    // ЧЕРЕЗ ДВИЖОК, А НЕ МИМО НЕГО (наряд DAY-GATE-1, доработка Ч.3). Текст въезжает в ход тем же
    // путём, каким въехал бы частичный результат плагина: склейка, первое слово, сторож,
    // эхо-замок, журнал. Подстановка, обходившая движок, проверяла КАРТОЧКУ и не проверяла ничего
    // из починенного в Ч.0 — а на симуляторе микрофона нет, и другого способа увидеть эти
    // состояния живьём не существует.
    //
    // …И ЗАКРЫВАЕТ ЗАПИСЬ ТАК ЖЕ, КАК ЧЕЛОВЕК (наряд SPEECH-2): подставил — и «Готово». Раньше
    // подстановку закрывал УЗНАННЫЙ КЛЮЧ, и этой дороги больше нет; ждать вместо неё две секунды
    // тишины на каждой карточке значит превратить дев-дверь в секундомер.
    if (_turn case final turn? when _listeningNow) {
      final delay = fast || knobs == null
          ? Duration.zero
          : Duration(seconds: knobs.fastSeconds + 1);
      _reopenTimer?.cancel();
      _reopenTimer = Timer(delay, () {
        if (!mounted || _answered || !_listeningNow) return;
        turn.injectTranscript(text);
        unawaited(turn.stop());
      });

      return;
    }

    // МИКРОФОН ЗАКРЫТ — теперь это ОБЫЧНОЕ состояние, а не редкость: он не открывается сам нигде
    // (Ч.1.1). Открываем его тем же вызовом, что и нажатие, и кладём текст в свежий ход.
    //
    // ЖДЁМ НЕ ТАЙМЕРОМ, А САМ ХОД. Канал, которого нет (симулятор), умирает через доли секунды, и
    // подстановка «через N миллисекунд» гонялась с этим наперегонки: `_listenOnce` успевает
    // прочитать подсказки распознавателю из зеркала терминов, и к моменту таймера хода ещё нет.
    // Поэтому текст кладётся в очередь, а вставляет его САМ `_listenOnce`, когда ход уже открыт.
    _pendingInjection = text;
    unawaited(_listenOnce());
  }

  /// Run [fn] once the slide-in animation has settled, unless the card was left in the meantime.
  /// [delay] overrides the wait for effects that must not sit out the whole transition (listening
  /// audio); raising the keyboard keeps the full wait, because that one really does stall the slide.
  void _afterTransition(VoidCallback fn, {Duration? delay}) {
    _deferTimer?.cancel();
    _deferTimer = Timer(delay ?? AppMotion.nextTaskEnter + const Duration(milliseconds: 30), () {
      if (mounted && widget.isCurrent()) fn();
    });
  }

  void _onClozeInput() {
    if (mounted) setState(() {});
  }

  @override
  void dispose() {
    _deferTimer?.cancel();
    _speakTimer?.cancel();
    _settleTimer?.cancel();
    _reopenTimer?.cancel();
    _roleWaitTimer?.cancel();
    if (_quietListener case final listener?) widget.roleSpeaking?.removeListener(listener);
    if (_isCloze) _input.removeListener(_onClozeInput);
    // A card left mid-utterance must not leave the microphone open behind it — and must not have
    // its transcript arrive over the next card either. Cancel keeps nothing, which is right: an
    // abandoned attempt was never an answer.
    final turn = _turn;
    if (turn != null) {
      unawaited(turn.cancel());
    } else if (_recognizer case final recognizer?) {
      unawaited(recognizer.cancel());
    }
    _input.dispose();
    _focus.dispose();
    super.dispose();
  }

  /// Which of the learner's own [words] to mark, once a WRONG verdict is in. Empty while the answer
  /// is still open and whenever it was accepted — a mark on an accepted answer would be a correction
  /// where there was no mistake, and `typo` is accepted.
  Set<int> _mistakes(List<String> words) {
    if (!_answered || (_verdict?.isAccepted ?? true)) return const {};
    // Nothing to mark on the LETTER form: the chips are one word being spelled, so «which of these
    // is not in the answer» has no honest answer — every letter IS in it, and what went wrong is the
    // order. Marking all seven of them said the opposite. The wrong-verdict underline plus the
    // correct form below already say everything this card can say.
    if (_assemblesLetters) return const {};

    return SessionGrader.misplacedWords(words, _card.answer);
  }

  int? _latency() {
    final ms = DateTime.now().difference(_shownAt).inMilliseconds;
    return ms > 0 ? ms : null;
  }

  /// СУДИТСЯ ЛИ ОТВЕТ ЭТОЙ КАРТОЧКИ РЕЧЬЮ — то есть {@see SpokenLine}, а не равенством.
  ///
  /// Три случая, зеркало трёх серверных политик ({@see MatchPolicy} там): реплика с ключом, чтение
  /// напечатанного примера, длинный термин. КОРОТКОЕ слово голосом сюда НЕ попадает и не должно:
  /// покрытие над однословной целью засчитало бы это слово, произнесённое в любой фразе вообще, —
  /// сервер для него держит равенство, и телефон обязан держать то же.
  bool get _judgedAsSpeech =>
      _isSpeaking && (_card.spokenTargets.isNotEmpty || _card.asksForExample || _gradesByCoverage);

  /// СТОИТ ЛИ ЦЕЛЬ ПЕРЕД ГЛАЗАМИ — единственный вопрос, которым {@see SpokenLine} выбирает правило
  /// (наряд SPEECH-2, Ч.3.1 против Ч.3.2).
  ///
  /// Форма примера печатает предложение на карточке — задача «прочитай», и порог высокий. Форма
  /// слова показывает перевод и фотографию, прогон не показывает ничего: там «вспомни».
  bool get _targetPrinted => _card.asksForExample;

  /// ПЕРВЫЕ ДВЕ ТРЕТИ РЕПЛИКИ — дев-дверь QA, вердикт «почти» (наряд SPEECH-2, Ч.6).
  ///
  /// Две трети, а не половина: порог чтения с экрана 0.9, ключа с остальным — 0.6, а пол «почти» —
  /// 0.5. Две трети попадают между ними на любой из трёх дорог, и именно это делает кнопку
  /// проверкой «почти», а не лотереей на длину конкретной реплики.
  String get _halfLine {
    final words = _card.answerText.trim().split(RegExp(r'\s+'));
    if (words.length < 3) return _card.answerText;

    return words.take((words.length * 2 / 3).ceil()).join(' ');
  }

  /// ВЕРДИКТ ПРО СКАЗАННОЕ — со списком «не хватило» и покрытием, а не одно «верно/нет».
  SpokenVerdict _spokenVerdictFor(String response) => SpokenLine.judge(
    transcript: response,
    line: _card.answer,
    keys: _card.spokenTargets,
    printed: _targetPrinted,
    config: widget.speech,
  );

  /// THE INSTANT VERDICT for [response] — the same rule [_commit] writes, so the card and the log
  /// cannot disagree about what was said.
  LocalCheck _verdictFor(String response) {
    // Two grading paths, exactly as the server has: an identity card's key is a term id, so the
    // check is id equality — running a ULID through the text grader's normalisation and typo
    // tolerance would be meaningless (and, before this, marked every correct tap wrong). Every
    // other card grades its text against the accepted set.
    if (_card.isIdentityGraded) {
      return response == _card.answer ? LocalCheck.correct : LocalCheck.wrong;
    }
    // ВСЁ, ЧТО СКАЗАНО ГОЛОСОМ И ДЛИННЕЕ СЛОВА, судит {@see SpokenLine} — одна функция с сервером
    // (наряд SPEECH-2, Ч.3.4). До неё здесь стояли две ветки, и обе были про «достаточно ли»:
    // ключ покрыт — верно; предложение покрыто на 0.7 — верно. Первая засчитывала слово вместо
    // фразы, вторая — две трети текста, который человек читал с экрана.
    if (_judgedAsSpeech) {
      return _spokenVerdictFor(response).isAccepted ? LocalCheck.correct : LocalCheck.wrong;
    }

    return SessionGrader.check(
      response,
      _card.answer,
      variants: _card.acceptedVariants,
      forgiveTypos: _mode.forgivesTypos,
      spokenSuffixTolerance: _isSpeaking,
      // Speaking ONLY (QA-21) — see SessionGrader.check. Typing and dictation practise the
      // article deliberately and keep failing a dropped one.
      ignoreArticles: _isSpeaking,
    );
  }

  /// [listenedMs] — сколько прошло от открытия микрофона до НАЧАЛА речи (канон §4, «сразу»), или
  /// null вне говорения. Считает движок ({@see SpeechTurnResult.speechStartedAt}); QA-подстановка
  /// подделывает его сама.
  void _commit(String response, {bool usedHint = false, int? listenedMs}) {
    if (_answered) return;
    // ВЕРДИКТ ПРО РЕЧЬ СЧИТАЕТСЯ ОДИН РАЗ И ЦЕЛИКОМ (наряд SPEECH-2, Ч.3.5): «почти» нужен список
    // пропущенных слов, служебной строке — покрытие и применённый порог, а планировщику — только
    // «зачёт или нет». Два вызова судьи вместо одного — это два места, где они могут разойтись.
    final spoken = _judgedAsSpeech ? _spokenVerdictFor(response) : null;
    if (spoken != null) {
      _diagnostics?.verdictIs(
        normalized: spoken.normalized,
        coverage: spoken.coverage,
        threshold: spoken.threshold,
        credit: spoken.credit.name,
      );
    }
    final verdict = spoken != null
        ? (spoken.isAccepted ? LocalCheck.correct : LocalCheck.wrong)
        : _verdictFor(response);
    // Sound + haptic together, for every mode — the verdict is shared, so its feedback is too.
    switch (verdict) {
      case LocalCheck.correct:
      case LocalCheck.typo:
        AppFeedback.correct();
      case LocalCheck.wrong:
        AppFeedback.wrong();
    }
    setState(() {
      _answered = true;
      _verdict = verdict;
      _spoken = spoken;
      _response = response;
      _usedHint = usedHint;
    });
    _focus.unfocus();
    // Auto-pronounce the correct form (§4е). The TTS platform-channel call stalls the main thread
    // ~40 ms, so fire it only AFTER the feedback has settled — on a static screen that stall is
    // invisible, and a fast «Дальше» cancels it (isCurrent) so it never lands on the slide (F20).
    if (widget.autoPronounce) {
      _speakTimer?.cancel();
      _speakTimer = Timer(const Duration(milliseconds: 420), () {
        if (mounted && widget.isCurrent()) widget.onSpeak(_card.answerText);
      });
    }
    widget.onAnswered(
      SessionAnswer(
        response: response,
        verdict: verdict,
        usedHint: usedHint,
        latencyMs: _latency(),
        listenedMs: listenedMs ??
            (_listenStartedAt == null
                ? null
                : DateTime.now().difference(_listenStartedAt!).inMilliseconds),
      ),
    );
  }

  // ── interactions ──────────────────────────────────────────────────────────

  /// [index] is the option's position, which is how an identity card's key is found: `option_ids`
  /// is aligned with `options`, so the tapped option's TERM ID is what gets graded and uploaded.
  /// The text is kept only for the UI ([_picked] marks which row the learner touched).
  void _pick(String option, int index) {
    PerfLog.instance.tapHandled('option');
    if (_answered) return;
    setState(() => _picked = option);
    _commit(_card.optionIdAt(index) ?? option);
  }

  void _placeChip(int i) {
    PerfLog.instance.tapHandled('chip');
    if (_answered || _placed.contains(i)) return;
    AppHaptics.light();
    setState(() => _placed.add(i));
  }

  void _unplaceChip(int i) {
    if (_answered) return;
    setState(() => _placed.remove(i));
  }

  /// What the assembly line COMMITS — and the separator is not cosmetic.
  ///
  /// Word chips are a phrase and join with spaces; LETTER chips are one word and join with nothing.
  /// The letter branch became reachable in BUGFIX-2 Ч.2б D2 and inherited the space, so a perfectly
  /// assembled `dolphin` was uploaded as «d o l p h i n» and graded wrong — on the owner's phone,
  /// first run. The answer key is the term, and the term has no spaces in it.
  String get _assembled => _placed.map((i) => _chips[i]).join(_assemblesLetters ? '' : ' ');

  void _submitAssembled() {
    if (_placed.isEmpty) return;
    _commit(_assembled);
  }

  /// The answer was typed on the wrong keyboard — «ФЕЬ» at an English card. Cleared by the next
  /// keystroke, so the hint lives exactly as long as the text that caused it.
  bool _wrongKeyboard = false;

  /// May the layout guard speak on THIS card at all?
  ///
  /// Only when the card's own answer is written in the language's script. A term can legitimately
  /// be foreign to the language that holds it — «Wi-Fi» and «IT» are Russian vocabulary written in
  /// Latin — and on such a card the correct answer is exactly the shape the guard was built to
  /// stop. Blocking it would make the card unanswerable, which is a client refusing what the server
  /// would accept: the one direction the local check is forbidden to take.
  ///
  /// Asked of the ANSWER rather than of the term, because on cloze and dictation the answer is the
  /// example sentence — the text the learner is actually typing is the text to judge against.
  bool get _admitsLayoutGuard => !looksLikeWrongKeyboard(widget.answerLang, _card.answerText);

  void _submitTyped() {
    final text = _input.text.trim();
    if (text.isEmpty) return;
    // THE LAYOUT GUARD, before anything is graded. An answer with not one letter of the card's own
    // alphabet in it is a keyboard left on, not a wrong answer — the learner knew the word and the
    // device did not tell them which language it wanted. Charging a mistake for that teaches the
    // scheduler something untrue about the word, so the attempt is not spent: the field keeps the
    // text, the card says what happened, and the next Enter goes through whatever it holds.
    //
    // It cannot swallow an honest miss: a wrong English answer contains English letters and fails
    // this test outright (see [looksLikeWrongKeyboard], which is all-or-nothing on purpose).
    if (_admitsLayoutGuard && looksLikeWrongKeyboard(widget.answerLang, text)) {
      if (!_wrongKeyboard) {
        AppHaptics.warning();
        setState(() => _wrongKeyboard = true);
      }
      _focus.requestFocus();

      return;
    }
    _commit(text);
  }

  void _useFirstLetter() {
    if (_answered || _usedHint) return;
    final first = _card.answerText.characters.isEmpty ? '' : _card.answerText.characters.first;
    setState(() {
      _usedHint = true;
      if (_input.text.isEmpty) {
        _input.text = first;
        _input.selection = TextSelection.collapsed(offset: _input.text.length);
      }
    });
    _focus.requestFocus();
  }

  void _giveUp() => _commit('', usedHint: _usedHint); // honest fail — shows the answer

  // ── speaking ───────────────────────────────────────────────────────────────

  /// ОДИН ХОД ГОЛОСОМ — движок ({@see SpeechTurn}) отдаёт ОДИН исход, и карточка отвечает на него
  /// (наряд DAY-FIX-3, Ч.1.4):
  ///
  ///   heard        полная запись — ВЕСЬ транскрипт уходит ответом и судится как любой другой
  ///                (наряд SPEECH-2, Ч.2.3): мимо ответа — честная ошибка (`speaking/again`);
  ///   incomplete   обрыв на полуслове — «Не расслышали до конца — скажи ещё раз», журнал не
  ///                пишется, попытка вторая;
  ///   silent       сторож записи закрыл её пустой — в прогоне ход делает сторож пустым ответом
  ///                (`again`, SCENE-RUN Ч.2.4), на обычной карточке — сообщение и «Пропустить»;
  ///   unavailable  канал не поднялся — как и раньше, состояние тренажёра, не ответ.
  ///
  /// ВЫЗЫВАЕТСЯ ТОЛЬКО ПО НАЖАТИЮ (Ч.1.1) — и по очереди подстановки дев-двери, которая нажатие
  /// подделывает. Ни один таймер сюда больше не ведёт.
  Future<void> _listenOnce() async {
    if (_answered || _listeningNow) return;
    final recognizer = _recognizer;
    if (recognizer == null) return;
    AppHaptics.light();
    setState(() {
      _listeningNow = true;
      _micState = MicState.recording;
      _partial = '';
      _channelFailure = null;
      _cutOff = false;
    });
    // ОТСЧЁТ «СРАЗУ» начинается здесь — от момента, когда микрофон открылся, а не от появления
    // карточки: между ними лежит реплика собеседника и чтение подсказки, и они не про скорость речи.
    final openedAt = DateTime.now();
    _listenStartedAt = openedAt;

    // ЛЮБОЙ СРЫВ ЗДЕСЬ — ЭТО «Слушаю…» НАВСЕГДА (наряд DAY-GATE-1, Ч.0.2, находка F4). Метод
    // вызывается через `unawaited`, поэтому исключение из подсказок распознавателю (чтение зеркала
    // терминов), из движка или из плагина не всплывает никуда: оно просто оставляет карточку в
    // состоянии «слушаю», в котором нет ни микрофона, ни выхода. `finally` — единственное, что
    // делает это состояние невозможным по построению, а не по бдительности.
    final SpeechTurnResult result;
    try {
      final contextualStrings = await _contextualStrings();
      if (!mounted || !_listeningNow) return;

      final turn = SpeechTurn(recognizer, config: _turnConfig, diagnostics: _diagnostics);
      _turn = turn;
      // ОЧЕРЕДЬ ПОДСТАНОВКИ (дев-дверь QA): ход открыт, класть текст можно. Один кадр форы —
      // движок ещё не успел дойти до плагина, а `injectTranscript` требует живого хода.
      if (_pendingInjection case final pending?) {
        _pendingInjection = null;
        // СРАЗУ, БЕЗ ТАЙМЕРА: на мёртвом канале ход живёт доли секунды (три мгновенные пустоты
        // подряд — и он закрыт), и любая отложенная вставка гонялась бы с этим наперегонки.
        // …и тут же «Готово», как это сделал бы человек, — см. [_substituteTranscript].
        if (turn.injectTranscript(pending)) unawaited(turn.stop());
      }
      result = await turn.listen(
        expected: _spokenTargets,
        localeId: widget.speechLocaleId,
        contextualStrings: contextualStrings,
        echoOf: widget.roleLineText == null ? null : _isEcho,
        onPartial: (text) {
          if (mounted && _listeningNow) setState(() => _partial = text);
        },
      );
      if (_turn == turn) _turn = null;
    } catch (e) {
      _diagnostics?.phaseIs(SpeechPhase.failed, code: 'listen_threw: $e');
      if (mounted && _listeningNow) {
        setState(() {
          _listeningNow = false;
          _micState = MicState.yourTurn;
          _attempts++;
          _partial = '';
          _channelFailure = SpeechOutcome.unavailable;
        });
        unawaited(_refreshProbe());
      }

      return;
    }
    if (!mounted) return;
    // THE CARD WAS LET OUT FIRST — QA substitution, a skip, a give-up. A result that arrives after
    // that is a result for an attempt nobody is waiting for.
    if (!_listeningNow || _answered) return;
    // ЗАПИСЬ КОНЧИЛАСЬ — кнопка снова зовёт. Что бы ни вышло: вторая попытка начинается тем же
    // нажатием, что и первая (Ч.1.3), и «Слушаю…», из которого нет выхода, стало невозможным.
    setState(() {
      _listeningNow = false;
      _micState = MicState.yourTurn;
    });

    switch (result.outcome) {
      case SpeechTurnOutcome.heard:
        _commit(
          result.transcript,
          listenedMs: (result.speechStartedAt ?? DateTime.now()).difference(openedAt).inMilliseconds,
        );

      case SpeechTurnOutcome.incomplete:
        // НЕ ОТВЕТ И НЕ ОШИБКА. Deliberately NOT `_commit('')` — an empty answer is «не помню»,
        // a claim about the learner's memory, and a cut-off channel is not entitled to make it.
        setState(() {
          _partial = '';
          _cutOff = true;
        });
        AppHaptics.warning();
        // МИКРОФОН НЕ ПЕРЕОТКРЫВАЕТСЯ САМ (наряд SPEECH-2, Ч.1.1) — даже после обрыва. Кнопка
        // снова зовёт, и вторую попытку начинает человек: запись, начавшаяся сама после «не
        // расслышали», ловит ровно ту же неготовность, из-за которой обрыв и вышел.

      case SpeechTurnOutcome.silent:
        if (_isSceneRun) {
          // ХОД ДЕЛАЕТ СТОРОЖ, и делает его тем же, чем сделал бы человек: пустым ответом, который
          // сервер оценивает как `again` (SCENE-RUN, Ч.2.4). Разговор идёт дальше, никто не
          // застревает. Сторож при этом теперь считает ЗАПИСЬ, которую человек начал сам, — то
          // есть ход за него делается только после того, как он его начал.
          _giveUp();

          return;
        }
        setState(() {
          _attempts++;
          _partial = '';
          _channelFailure = SpeechOutcome.silent;
        });
        AppHaptics.warning();

      case SpeechTurnOutcome.unavailable:
        setState(() {
          _attempts++;
          _partial = '';
          _channelFailure = SpeechOutcome.unavailable;
        });
        AppHaptics.warning();
        // ПОЧЕМУ канал не поднялся, спрашиваем У ОС, а не гадаем: «нет разрешения» чинится
        // человеком за три касания, «нет движка» — ничем, и одна строка на оба случая помогает
        // только во втором (Ч.0.2).
        unawaited(_refreshProbe());
    }
  }

  /// Спросить ОС про разрешения и распознаватель этого языка — {@see SpeechDiagnostics.refresh}.
  /// Ничего не запрашивает у человека: только читает статусы.
  Future<void> _refreshProbe() async {
    final diagnostics = _diagnostics;
    if (diagnostics == null) return;
    final probe = await diagnostics.refresh(widget.speechLocaleId);
    if (mounted) setState(() => _probe = probe);
  }

  Future<void> _stopListening() async {
    if (!_listeningNow) return;
    // A deliberate «Готово» — whatever was heard is the learner's own final answer, graded as-is
    // even if short, never retried behind their back.
    await _turn?.stop();
  }

  /// Set the card aside: the microphone lost, and nothing about this word is recorded anywhere.
  ///
  /// В ПРОГОНЕ СЦЕНЫ ЭТО ЗНАЧИТ ДРУГОЕ (наряд SCENE-RUN, Ч.2.4). Там «Пропустить» — не отказ
  /// железа, а законный ход человека, который не вспомнил: он ПИШЕТ `speaking/again` для пары, та
  /// же семантика, что у говорения фраз, и разговор идёт дальше. Молчаливый пропуск оставил бы ход
  /// вне прогона вовсе — итог «Прошёл сам 4 из 4» на сцене из пяти ходов, что живой прогон и
  /// показал.
  void _skipCard() {
    if (_answered) return;
    if (_isSceneRun) {
      _reopenTimer?.cancel();
      unawaited(_turn?.cancel());
      _listeningNow = false;
      _giveUp();

      return;
    }
    _answered = true; // no second exit from this card
    widget.onSkipped?.call();
  }

  // ── build ──────────────────────────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _promptCard(l),
        // Every mode whose answer is TAPPED from given options. pick_correct belongs here even
        // though its options are whole sentences — that only changes how they read, not how they
        // are answered. Leaving it out was the device-batch bug: prompt and photo rendered, and
        // there was nothing on screen to tap.
        if (!_isAssembleTurn &&
            (_mode == ExerciseMode.multipleChoice ||
                _mode == ExerciseMode.descriptionMatch ||
                _mode.isSituational ||
                _mode.isSentenceChoice ||
                _isRecognitionListening)) ...[
          const SizedBox(height: AppSpacing.s12),
          _options(l),
        ],
        // СТРОКА СБОРКИ У ХОДА — своим блоком, а не внутри карточки-вопроса.
        //
        // У word_bank и scramble она живёт в карточке-вопросе, потому что там эта карточка есть. В
        // разговоре её нет вовсе: реплику подаёт пузырь, вопрос такта стоит над карточкой, и
        // положения на экране не рисуется (наряд DAY-2-FIX, Ч.1.1). Без этой строки человек тапал
        // бы блоки и не видел, что собрал.
        if (_isAssembleTurn) ...[
          // «СКАЖИ: …» — ЧТО ИМЕННО СОБИРАЮТ (наряд DAY-GATE-1, Ч.2.4). Блоки лежат на изучаемом
          // языке, и без этой строки сборка — это складывание чужих слов наугад: живой прогон
          // показал человека, который собрал грамматически верную фразу не о том.
          if (widget.sayIntent case final intent? when intent.isNotEmpty) ...[
            const SizedBox(height: AppSpacing.s12),
            Text(
              l.planSayIntent(intent),
              style: AppText.stepTitle.copyWith(fontSize: 16, height: 1.4),
            ),
          ],
          const SizedBox(height: AppSpacing.s12),
          PaperCard(
            child: _AssemblyLine(
              words: _placed.map((i) => _chips[i]).toList(),
              answered: _answered,
              correct: _verdict?.isAccepted ?? false,
              mistakes: _mistakes(_placed.map((i) => _chips[i]).toList()),
              onTapWord: (idx) => _unplaceChip(_placed[idx]),
            ),
          ),
        ],
        if (_mode.isAssembled || _isAssembleTurn) ...[
          const SizedBox(height: AppSpacing.s16),
          _chipTray(l),
        ],
        if (!_answered && _isSpeaking) ...[
          const SizedBox(height: AppSpacing.s16),
          _speakingControls(l),
        ],
        if (!_answered && (_mode.isTyped && !_isRecognitionListening)) ...[
          const SizedBox(height: AppSpacing.s12),
          _auxButtons(l),
        ],
        // Giving up stays reachable on BOTH assembly modes, through the same channel as the typed
        // modes' «Не помню» — one `_giveUp`, which commits an empty answer and lets the SERVER
        // grade it as the lapse it is. `scramble` had it and `word_bank` did not, which made
        // «я не помню это слово» sayable on a sentence and unsayable on a word: the only way out of
        // a word_bank card was to assemble something wrong on purpose, and a wrong answer and a
        // blank one are not the same statement about what the learner knows.
        if (!_answered && (_mode.isAssembled || _isAssembleTurn)) ...[
          const SizedBox(height: AppSpacing.s12),
          QuietButton(label: l.sessionDontRemember, onPressed: _giveUp),
        ],
        if (!_answered && (_mode.isAssembled || _isAssembleTurn) && _placed.isNotEmpty) ...[
          const SizedBox(height: AppSpacing.s12),
          // «Проверить», not «Дальше»: this submits the assembled phrase (grades it) — the
          // feedback block then shows the real «Дальше» that advances. Two distinct steps, two
          // distinct labels, so the first tap doesn't read as a no-op (device-batch F12).
          PrimaryButton(label: l.sessionCheck, onPressed: _submitAssembled),
        ],
        // «ПОКАЗАТЬ ТЕКСТ», and only after the answer (наряд Ч-2). Before it the card is the sound:
        // printing the line would answer its own question, and «Ещё раз» / «Медленнее» are the
        // escape a learner who did not catch it actually needs.
        if (_answered && _isSituationalHear) ...[
          const SizedBox(height: AppSpacing.s12),
          _RevealedLine(text: _card.answerText, onSpeak: widget.onSpeak),
        ],
        if (_answered) ...[
          const SizedBox(height: AppSpacing.s12),
          _FeedbackBlock(
            card: _card,
            verdict: _verdict!,
            spoken: _spoken,
            onSpeak: widget.onSpeak,
            photoUrl: widget.photoUrl,
            photoResolved: widget.photoResolved,
            showDue: widget.showDue,
            recognizedText: _isSpeaking ? _response : null,
          ),
        ],
      ],
    );
  }

  // The prompt / question region — differs per mode.
  Widget _promptCard(AppLocalizations l) {
    // Dictation is the same card as typed listening — a play circle, a slow replay and a field.
    // What differs is the length of what is spoken, which is not a layout concern.
    if (_isDictation) return _listeningPrompt(l, typed: true);
    if (_isListening && !_isRecognitionListening) return _listeningPrompt(l, typed: true);
    if (_isRecognitionListening) return _listeningPrompt(l, typed: false);
    if (_isCloze) return _clozePrompt(l);
    if (_isSpeaking) return _speakingPrompt(l);
    if (_isScramble) return _scramblePrompt(l);
    if (_mode.isSentenceChoice) return _pickCorrectPrompt(l);
    if (_isSituationalHear) return _situationalHearPrompt(l);
    if (_isSituationalSpeak) return _situationalSpeakPrompt(l);
    if (_mode == ExerciseMode.descriptionMatch) return _descriptionPrompt(l);
    if (_mode == ExerciseMode.wordBank) return _wordBankPrompt(l);
    if (_mode == ExerciseMode.typing) return _typingPrompt(l);
    return _choicePrompt(l); // multiple_choice
  }

  /// The language THIS CARD is studied in, in the form the instruction's sentence wants.
  ///
  /// Read off [SessionExerciseCard.answerLang] — the card's own studied side — and not off the
  /// profile: the pool mixes pairs by design (DECISIONS п. 128), so an Italian card in an
  /// English-heavy session is an ordinary Tuesday. The instructions used to say «английский»
  /// outright, which told the learner to do something other than what the card was asking.
  String _adjective(AppLocalizations l) => languageAdjectiveFor(widget.answerLang, l.localeName);

  String _adverb(AppLocalizations l) => languageAdverbFor(widget.answerLang, l.localeName);

  String _instructionFor(AppLocalizations l) => switch (_mode) {
    // Unreachable: an intro is not an exercise and never reaches this widget (the shell renders
    // SessionIntroCard for it). Named rather than defaulted so the next mode added still has to
    // answer this question explicitly.
    ExerciseMode.intro => '',
    // The instruction has to know which WAY the card asks. Rung 1 shows the English term and
    // offers translations (it is graded by identity, which is what makes it recognisable here);
    // rung 2 is the reverse. «Выбери английский эквивалент» printed under an English prompt with
    // Russian options told the learner to do the opposite of what the card wanted.
    ExerciseMode.multipleChoice =>
      _card.isIdentityGraded ? l.sessionInstrRecogniseTranslation : l.sessionInstrChoose(_adjective(l)),
    // Two shapes of the same trainer: a phrase is dealt WORD chips, a single word LETTER chips
    // (BUGFIX-2 Ч.2б D2). The instruction has to name what is actually lying on the screen —
    // «собери из слов» over a row of single letters describes a card the learner is not looking at.
    ExerciseMode.wordBank => _assemblesLetters ? l.sessionInstrAssembleLetters : l.sessionInstrAssemble,
    ExerciseMode.typing => l.sessionInstrType(_adverb(l)),
    ExerciseMode.cloze => l.sessionInstrType(_adverb(l)),
    ExerciseMode.scramble => l.sessionInstrAssembleSentence,
    ExerciseMode.dictation => l.sessionInstrDictation,
    ExerciseMode.pickCorrect => l.sessionInstrPickCorrect,
    // The prompt above is a DESCRIPTION, not a translation, so the instruction has to say so —
    // «выбери английский эквивалент» under an English sentence would describe the wrong task.
    ExerciseMode.descriptionMatch => l.sessionInstrDescriptionMatch,
    // The mode's two forms read as two different tasks, because they ARE two different tasks —
    // recall the word, or read the sentence you can see.
    ExerciseMode.speaking =>
      _card.asksForExample ? l.sessionInstrSpeakExample : l.sessionInstrSpeakWord,
    ExerciseMode.listening =>
      _isRecognitionListening ? l.sessionInstrListenChoose : l.sessionInstrListenType(_adverb(l)),
    // The three situational cards each name what is being chosen, because the options are three
    // different things: a MEANING on «Тебе скажут», a reply on «Ты ответишь», a question on «Ты
    // спросишь». One instruction for all three would describe none of them.
    ExerciseMode.situationalHear => l.sessionInstrSituationalHear,
    // …и на двух говорящих полках инструкция зависит от того, ЧТО лежит на экране: варианты или
    // блоки (наряд SCENE-RUN, Ч.1). «Выбери, что ответишь» над рядом плиток описывает карточку, на
    // которую человек не смотрит.
    ExerciseMode.situationalSay =>
      _isAssembleTurn ? l.sessionInstrAssembleTurn : l.sessionInstrSituationalSay,
    ExerciseMode.situationalAsk =>
      _isAssembleTurn ? l.sessionInstrAssembleTurn : l.sessionInstrSituationalAsk,
  };

  String _typeLabel(AppLocalizations l) => switch (_card.type) {
    'word' => l.triageTermTypeWord,
    'phrase' => l.triageTermTypePhrase,
    'idiom' => l.triageTermTypeIdiom,
    'phrasal_verb' => l.triageTermTypePhrasalVerb,
    _ => l.triageTermTypePhrase,
  };

  Widget _instructionLine(AppLocalizations l, {bool withType = true}) {
    final text = withType ? '${_typeLabel(l)} · ${_instructionFor(l)}' : _instructionFor(l);
    return Text(text, style: AppTextExercise.taskInstruction);
  }

  // multiple_choice — photo (when the term has one) + prompt + instruction.
  Widget _choicePrompt(AppLocalizations l) {
    return PaperCard(
      clipContent: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Height known from the shell's lookup; a cold photo waits for [_settled] so it can't
          // materialise mid-slide (F20-r).
          _PromptPhoto(
            termId: _card.termId,
            url: widget.photoUrl,
            resolved: widget.photoResolved,
            reveal: _settled,
          ),
          Text(_card.prompt ?? '', style: AppTextExercise.taskPromptRu),
          const SizedBox(height: AppSpacing.s4),
          _instructionLine(l),
        ],
      ),
    );
  }

  // pick_correct — the translated sentence + instruction. NO photo, deliberately: the answer area
  // already holds three whole sentences to read and compare, and a banner above them pushes the
  // third option off the first screen. The photo is a memory aid for a WORD; this card is a
  // reading task.
  Widget _pickCorrectPrompt(AppLocalizations l) {
    return PaperCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(_card.prompt ?? '', style: AppTextExercise.taskPromptRu),
          const SizedBox(height: AppSpacing.s4),
          _instructionLine(l, withType: false),
        ],
      ),
    );
  }

  // description_match — the DESCRIPTION is the question, and it is in the language being learned.
  //
  // No photo and no translation, both deliberately. The photo is the strongest hint the app has and
  // would answer the card outright; the translation would turn «read this and recognise the word»
  // into the ordinary translation card that already exists. This is the one screen in the session
  // that shows no Russian at all, and that is the whole point of the trainer.
  //
  // Literata rather than the Inter prompt style: this is a sentence to READ, in the target language,
  // like the example and the pick_correct options — not a Russian cue to glance at.
  Widget _descriptionPrompt(AppLocalizations l) {
    return PaperCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(_card.prompt ?? '', style: AppTextExercise.answerOption),
          const SizedBox(height: AppSpacing.s8),
          _instructionLine(l, withType: false),
        ],
      ),
    );
  }

  // typing — prompt + inline input field (12c). No photo in the question.
  Widget _typingPrompt(AppLocalizations l) {
    return PaperCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(_card.prompt ?? '', style: AppTextExercise.taskPromptRu),
          const SizedBox(height: AppSpacing.s4),
          _instructionLine(l, withType: false),
          const SizedBox(height: AppSpacing.s16),
          _inputField(),
        ],
      ),
    );
  }

  // word_bank — prompt + the assembly line inside the card (12b).
  Widget _wordBankPrompt(AppLocalizations l) {
    return PaperCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(_card.prompt ?? '', style: AppTextExercise.taskPromptRu),
          const SizedBox(height: AppSpacing.s4),
          _instructionLine(l),
          const SizedBox(height: AppSpacing.s16),
          _AssemblyLine(
            words: _placed.map((i) => _chips[i]).toList(),
            answered: _answered,
            correct: _verdict?.isAccepted ?? false,
            mistakes: _mistakes(_placed.map((i) => _chips[i]).toList()),
            onTapWord: (idx) => _unplaceChip(_placed[idx]),
            letters: _assemblesLetters,
          ),
        ],
      ),
    );
  }

  // scramble — the sentence's translation is the task, the assembly line collects the chips.
  // No photo and no term text: showing either would give away words of the sentence being built.
  Widget _scramblePrompt(AppLocalizations l) {
    return PaperCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // The prompt is the EXAMPLE's translation (the server swaps it in for this mode), so this
          // reads as «собери это по-английски» rather than as the term's own translation.
          Text(_card.prompt ?? '', style: AppTextExercise.taskPromptRu),
          const SizedBox(height: AppSpacing.s4),
          _instructionLine(l, withType: false),
          const SizedBox(height: AppSpacing.s16),
          _AssemblyLine(
            words: _placed.map((i) => _chips[i]).toList(),
            answered: _answered,
            correct: _verdict?.isAccepted ?? false,
            mistakes: _mistakes(_placed.map((i) => _chips[i]).toList()),
            onTapWord: (idx) => _unplaceChip(_placed[idx]),
          ),
        ],
      ),
    );
  }

  // speaking — the prompt by form, then the record button and the live transcript.
  //
  // WORD form: the translation and the photo, exactly the material multiple_choice shows, and
  // deliberately not the term — printing the word being recalled would turn free recall into
  // reading aloud, which is the OTHER form of this card.
  //
  // EXAMPLE form: the sentence itself, large, because reading it IS the task. The translation sits
  // under it as it does everywhere else.
  Widget _speakingPrompt(AppLocalizations l) {
    final asksExample = _card.asksForExample;
    return PaperCard(
      clipContent: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // ФОТОГРАФИИ В ПРОГОНЕ НЕТ: она подсказка к слову, а здесь вспоминают реплику, и
          // картинка чужого слова рядом с ней — шум.
          if (!asksExample && !_isSceneRun)
            _PromptPhoto(
              termId: _card.termId,
              url: widget.photoUrl,
              resolved: widget.photoResolved,
              reveal: _settled,
            ),
          if (asksExample) ...[
            Text(_card.answer, style: AppTextExercise.introExample),
            if ((_card.exampleTranslation ?? '').isNotEmpty) ...[
              const SizedBox(height: AppSpacing.s8),
              Text(_card.exampleTranslation!, style: AppText.translation.copyWith(height: 1.4)),
            ],
          ] else
            Text(_card.prompt ?? '', style: AppTextExercise.taskPromptRu),
          const SizedBox(height: AppSpacing.s4),
          // В ПРОГОНЕ СЛУЖЕБНОЙ СТРОКИ НЕТ (наряд DAY-GATE-1, Ч.2.7). «фраза · скажи слово вслух»
          // говорила там неправду дважды: реплику она называла словом, а тип материала — служебным
          // словом, которое на экраны плана не выходит. Человеческая строка стоит ниже
          // ([_speakHint] → «скажи свою реплику — текста не будет») и говорит то же самое один раз.
          if (!_isSceneRun) ...[
            _instructionLine(l, withType: !asksExample),
            const SizedBox(height: AppSpacing.s4),
          ],
          // The frame, on the card and not only in a spec: this is recall, not pronunciation. It is
          // what makes a learner willing to speak at all.
          //
          // A LINE SAYS WHICH PART OF IT IS BEING ASKED. The card grades the key and nothing else,
          // so the instruction names it — «главное — <ключ>» — and a line the day left no key for
          // says the other true thing, that the whole line is the ask. A spoken WORD keeps the
          // original sentence: the term is already the whole of what is wanted.
          Text(_speakHint(l), style: AppTextExercise.taskInstruction),
        ],
      ),
    );
  }

  /// What this spoken card is actually asking for, in one line. See [_speakingPrompt].
  String _speakHint(AppLocalizations l) {
    // В ПРОГОНЕ КЛЮЧ НЕ ПОКАЗЫВАЮТ. Ключ написан на изучаемом языке, а прогон — это «скажи сам, без
    // текста»: строка «главное — a fever» отдала бы половину реплики и превратила ступень C в
    // чтение вслух. Что делать, говорит подсказка на языке поддержки над микрофоном.
    if (_isSceneRun) return l.planSceneRunHint;

    final key = _card.spokenTarget;
    if (key != null) return l.sessionSpeakHintKey(key);

    // «Целиком» belongs to a card whose answer is a SENTENCE — the same «длинность» rule the
    // window and the grading use, so a card recorded like a sentence is described like one.
    return _gradesByCoverage ? l.sessionSpeakHintWhole : l.sessionSpeakHint;
  }

  /// The record button, the live transcript, and — only once the microphone has actually failed —
  /// the way out.
  Widget _speakingControls(AppLocalizations l) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        // ОДНА КНОПКА НА ВСЕ ТРИ КАРТОЧКИ ГОВОРЕНИЯ (наряд SPEECH-2, Ч.1.3) — и три её состояния
        // видны без чтения: ждём собеседника, твоя очередь, пишу.
        Center(
          child: MicButton(
            state: _micState,
            onTap: () => unawaited(_micState == MicState.recording ? _stopListening() : _listenOnce()),
            caption: switch (_micState) {
              MicState.waiting => l.sessionSpeakWaitForRole,
              MicState.yourTurn => l.sessionSpeakYourTurn,
              MicState.recording => l.sessionSpeakRecording,
            },
          ),
        ),
        // What the recogniser has so far, live. Seeing the words appear is what tells the learner
        // the phone is hearing them at all — the difference between «it is thinking» and «it is
        // broken», on a card with no keyboard to prove otherwise.
        if (_partial.trim().isNotEmpty) ...[
          const SizedBox(height: AppSpacing.s12),
          Text(_partial, textAlign: TextAlign.center, style: AppTextExercise.typingInput),
        ],
        // РАЗРЕШЕНИЕ ОТОЗВАНО — это не «микрофон недоступен», а единственный отказ канала, который
        // человек может починить сам (наряд DAY-GATE-1, Ч.0.2). Строка про Настройки ЗАМЕНЯЕТ
        // общую: две подряд («недоступен» и «разреши») читаются как два разных сбоя.
        if (_probe case final probe? when _channelFailure != null && probe.blockedInSettings) ...[
          const SizedBox(height: AppSpacing.s12),
          SpeechPermissionNotice(
            micDenied: probe.microphone != SpeechPermission.granted,
            recognitionDenied: probe.recognition != SpeechPermission.granted,
          ),
        ] else if (_channelFailure != null || _cutOff) ...[
          const SizedBox(height: AppSpacing.s12),
          Text(
            // ОБРЫВ — своя строка (Ч.1.4): не «микрофон недоступен» и не «не расслышал», а
            // «не расслышали до конца» — человек говорил, канал не дослушал.
            _cutOff
                ? l.sessionSpeakCutOff
                : _channelFailure == SpeechOutcome.unavailable
                ? l.sessionSpeakNoMic
                : l.sessionSpeakNotHeard,
            textAlign: TextAlign.center,
            // The colour of an ordinary note, NOT of a verdict: nothing has gone wrong with the
            // learner's memory, and the card must not look as if it has.
            style: AppTextExercise.taskInstruction,
          ),
        ],
        // ДЕВ-ДВЕРЬ QA: подстановка транскрипта вместо голоса. Ни в релизе, ни у боевого аккаунта
        // её нет — право приезжает с сервера одним полем ({@see _substituteTranscript}). На
        // каждой карточке говорения, не только в прогоне: разогрев дня тоже просит сказать вслух.
        if (!_answered && _isSpeaking && (ref.watch(authControllerProvider).value?.qaTools ?? false)) ...[
          const SizedBox(height: AppSpacing.s12),
          // СЛУЖЕБНАЯ СТРОКА ПРЯМО У МИКРОФОНА (наряд DAY-GATE-1, доработка Ч.3): стадию хода надо
          // видеть В МОМЕНТ хода, а не на другом экране после него — `listening` живёт секунды.
          if (_diagnostics case final diagnostics?) ...[
            QaSpeechView(diagnostics: diagnostics, localeId: widget.speechLocaleId),
            const SizedBox(height: AppSpacing.s8),
          ],
          _QaTranscriptRow(
            // ПОДСТАВЛЯЕТСЯ ВСЯ РЕПЛИКА, а не ключ (наряд SPEECH-2, Ч.3.2): ключ сам по себе
            // больше не ответ, и дверь, кладущая один ключ, показывала бы «Не то» на каждой
            // карточке и выглядела бы поломкой.
            onSaid: (fast) => _substituteTranscript(_card.answerText, fast: fast),
            // «Мимо» — не пустой ответ, а ЧУЖОЙ текст: пустой это «не помню», а карточка проверяет,
            // что человек СКАЗАЛ, и мимо цели сказанное — тоже сказанное.
            onMissed: () => _substituteTranscript('nothing like the line', fast: false),
          ),
          const SizedBox(height: AppSpacing.s8),
          // «ПОЧТИ»: половина реплики — единственный вердикт, который иначе не увидеть ни на
          // симуляторе, ни на устройстве без специально плохой дикции.
          _QaHalfLineRow(onSaid: () => _substituteTranscript(_halfLine, fast: true)),
        ],
        if (_canSkip) ...[
          const SizedBox(height: AppSpacing.s16),
          QuietButton(label: l.sessionSpeakSkip, onPressed: _skipCard),
          const SizedBox(height: AppSpacing.s4),
          Text(
            l.sessionSpeakSkipHint,
            textAlign: TextAlign.center,
            style: AppTextExercise.taskInstruction,
          ),
        ],
        const SizedBox(height: AppSpacing.s12),
        // «Не помню» is the OTHER exit, and the only one that writes anything: an honest lapse,
        // the same code path every typed card uses. It is always available — a learner who knows
        // they have forgotten should not have to fail three microphone attempts to say so.
        QuietButton(label: l.sessionDontRemember, onPressed: _listeningNow ? null : _giveUp),
      ],
    );
  }

  // cloze — the example with a blank at the answer's position (12i/12j).
  Widget _clozePrompt(AppLocalizations l) {
    return PaperCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(l.sessionClozeInsert.toUpperCase(), style: AppText.sectionLabel),
          const SizedBox(height: AppSpacing.s12),
          _ClozeSentence(
            example: _card.example ?? _card.answerText,
            answer: _card.answerText,
            // The blank always holds the LEARNER'S OWN word — while typing, and after the verdict.
            // It used to be swapped for the correct form on answering, which read as though they had
            // typed the right thing and left nothing to compare the correction below against
            // (QA-16). The correct form still appears, as its own card underneath.
            filled: _answered
                ? ((_response ?? '').trim().isEmpty ? null : _response!.trim())
                : (_input.text.trim().isEmpty ? null : _input.text),
            answered: _answered,
            correct: _verdict?.isAccepted ?? false,
            mistakes: _mistakes([(_response ?? '').trim()]),
          ),
          if (_card.exampleTranslation != null) ...[
            const SizedBox(height: AppSpacing.s12),
            Text(_card.exampleTranslation!, style: AppText.translation.copyWith(height: 1.4)),
          ],
          if (!_answered)
            // The visible answer is the blank in the sentence above; this field is invisible and
            // only captures the keyboard (transparent text, no underline).
            _inputField(hideText: true, borderless: true),
        ],
      ),
    );
  }

  // listening — a big play circle (tap = replay) + a «замедленно» slow-replay control; typed answer
  // below when production (12h), options handled outside. The term text is never shown — only heard.
  Widget _listeningPrompt(AppLocalizations l, {required bool typed}) {
    return PaperCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Center(
            child: _PlayCircle(
              onTap: () => widget.onSpeak(_card.answerText),
              label: l.sessionListenReplay,
            ),
          ),
          const SizedBox(height: AppSpacing.s12),
          Center(
            child: QuietButton(
              label: l.sessionListenReplaySlow,
              icon: LucideIcons.gauge,
              onPressed: () => widget.onSpeak(_card.answerText, slow: true),
            ),
          ),
          const SizedBox(height: AppSpacing.s16),
          Text(
            _instructionFor(l),
            textAlign: TextAlign.center,
            style: AppTextExercise.taskInstruction,
          ),
          if (typed && !_answered) ...[const SizedBox(height: AppSpacing.s16), _inputField()],
        ],
      ),
    );
  }

  /// «ТЕБЕ СКАЖУТ» ON THE SITUATIONAL TRAINER (кадр D · 03) — the sound, and no text at all.
  ///
  /// The whole card is what is NOT on it: the line is played and never printed, because the question
  /// is «что он спросил» and the answer is written on the card the moment the sentence is. What the
  /// learner gets instead is the two things a person actually asks for when they miss something —
  /// «Ещё раз» and «Медленнее» — and, once they have answered, the text
  /// ([_RevealedLine]).
  ///
  /// Above it stands the SCENE, not the вводка: «сейчас услышите · У стойки регистратуры». The
  /// вводка says what will happen, which on this card is the answer.
  Widget _situationalHearPrompt(AppLocalizations l) {
    final scene = widget.situation?.context?.trim() ?? '';

    // В ЛЕНТЕ РАЗГОВОРА ПРОМПТА У ЭТОЙ КАРТОЧКИ НЕТ: пузырь над ней и есть промпт (кадр DL·02) —
    // реплика звучит сама при появлении и повторяется той же кнопкой «Ещё раз», что и на всех
    // остальных пузырях роли, а «Показать текст» раскрывает её внутри пузыря. Своя кнопка
    // воспроизведения кеглем 112 рядом с пузырём была бы вторым плеером на одну реплику.
    //
    // Отличается это от ХВОСТА сцены (карточки вне цепочки) ровно положением: в ленте сервер шлёт
    // `situation: null`, потому что момент подаёт пузырь; у хвоста положение есть, пузыря нет, и
    // играть реплику нечем, кроме собственной кнопки.
    if (widget.inDialogue && widget.situation == null) return const SizedBox.shrink();

    return PaperCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (scene.isNotEmpty) ...[
            Text(l.sessionSituationHearLabel.toUpperCase(), style: AppText.sectionLabel),
            const SizedBox(height: AppSpacing.s8),
            Text(scene, style: AppText.stepTitle.copyWith(fontSize: 19, height: 1.3)),
            const SizedBox(height: AppSpacing.s22),
          ],
          Center(
            child: _PlayCircle(
              onTap: () => widget.onSpeak(_card.answerText),
              label: l.sessionListenReplay,
            ),
          ),
          const SizedBox(height: AppSpacing.s12),
          Center(
            child: QuietButton(
              label: l.sessionListenReplaySlow,
              icon: LucideIcons.gauge,
              onPressed: () => widget.onSpeak(_card.answerText, slow: true),
            ),
          ),
          const SizedBox(height: AppSpacing.s16),
          Text(
            _instructionFor(l),
            textAlign: TextAlign.center,
            style: AppTextExercise.taskInstruction,
          ),
        ],
      ),
    );
  }

  /// «ТЫ ОТВЕТИШЬ» / «ТЫ СПРОСИШЬ» (кадр D · 04) — the position, then the replies.
  ///
  /// The situation is always ABOVE the options and always on the learner's own language: the choice
  /// is made from the moment, not from a gloss of the right answer (канон §13). Its second line is
  /// either what was just SAID to them — the day's own role line, on the language being learned,
  /// speakable — or, when this day has no role line for the ability this card serves, the ability
  /// itself. Both are the server's; neither is ever a translation of the answer.
  Widget _situationalSpeakPrompt(AppLocalizations l) {
    final situation = widget.situation;
    final context = situation?.context?.trim() ?? '';
    final roleLine = situation?.roleLine?.trim() ?? '';
    final task = situation?.task?.trim() ?? '';

    // NO POSITION, NO HEADING, NO INSTRUCTION. Inside a conversation the shell owns the moment —
    // the line sounds from the bubble above, the вводка stood on the dialogue's opening screen, and
    // the такт asks its question in full size over the options (наряд DAY-2-FIX, Ч.1.1). What stood
    // here was «выбери, что ответишь» in grey 12 pt: the only sentence on the screen telling the
    // learner what to do, set smaller than everything around it and in the wrong language.
    if (context.isEmpty && roleLine.isEmpty && task.isEmpty) {
      // В разговоре — ничего: вопрос такта уже стоит над карточкой крупно, а у хвостовой карточки
      // над ней стоит вводка «Ещё раз ответ этой сцены». Вне разговора — своя инструкция, потому
      // что спросить больше некому и карточка деградирует до обычного выбора, а не до молчания.
      if (widget.inDialogue) return const SizedBox.shrink();

      return PaperCard(
        child: Align(
          alignment: Alignment.centerLeft,
          child: Text(_instructionFor(l), style: AppTextExercise.taskInstruction),
        ),
      );
    }

    return PaperCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(l.sessionSituationLabel.toUpperCase(), style: AppText.sectionLabel),
          if (context.isNotEmpty) ...[
            const SizedBox(height: AppSpacing.s8),
            Text(context, style: AppText.stepTitle.copyWith(fontSize: 17, height: 1.45)),
          ],
          if (roleLine.isNotEmpty) ...[
            const SizedBox(height: AppSpacing.s12),
            // WHAT WAS JUST SAID TO THEM, in the language being learned — so the reply is an answer
            // to something heard, not to a description of it. Tapping speaks it, like every other
            // target-language line on a card.
            InkWell(
              onTap: () => widget.onSpeak(roleLine),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Padding(
                    padding: EdgeInsets.only(top: 3, right: AppSpacing.s8),
                    child: Icon(LucideIcons.volume2, size: 15, color: AppColors.brassInk),
                  ),
                  Expanded(
                    child: Text(
                      roleLine,
                      style: AppText.stepTitle.copyWith(
                        fontSize: 16,
                        height: 1.4,
                        fontStyle: FontStyle.italic,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
          if (roleLine.isEmpty && task.isNotEmpty) ...[
            const SizedBox(height: AppSpacing.s12),
            Text(
              l.sessionSituationTask(task),
              style: AppText.translation.copyWith(fontSize: 15, height: 1.45),
            ),
          ],
          // Служебной строки в разговоре нет и у карточки С ПОЛОЖЕНИЕМ: над хвостом стоит вводка
          // «Ещё раз вопрос этой сцены», и «выбери, что спросишь» под ней — то же самое, тише и
          // мельче (наряд DAY-2-FIX, Ч.1.6 — поймано живым прогоном).
          if (!widget.inDialogue) ...[
            const SizedBox(height: AppSpacing.s16),
            Text(_instructionFor(l), style: AppTextExercise.taskInstruction),
          ],
        ],
      ),
    );
  }

  Widget _inputField({bool hideText = false, bool borderless = false}) {
    // The answer renders in antiqua as it's typed — the word becomes dictionary-like (12c note).
    // [borderless] + [hideText] together make an invisible keyboard-capture field (cloze types into
    // the sentence blank, so the field itself must not show a stray underline).
    const noBorder = UnderlineInputBorder(borderSide: BorderSide(color: Colors.transparent));

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      mainAxisSize: MainAxisSize.min,
      children: [
        _field(hideText: hideText, borderless: borderless, noBorder: noBorder),
        // The layout hint. A quiet line, not a verdict: nothing was graded and nothing was spent,
        // and dressing it as an error would say the opposite of what happened.
        if (_wrongKeyboard) ...[
          const SizedBox(height: AppSpacing.s8),
          Text(
            AppLocalizations.of(context).sessionWrongKeyboard,
            key: sessionWrongKeyboardKey,
            textAlign: TextAlign.center,
            style: AppTextExercise.answerAuxButton.copyWith(color: AppColors.secondary),
          ),
        ],
      ],
    );
  }

  Widget _field({
    required bool hideText,
    required bool borderless,
    required UnderlineInputBorder noBorder,
  }) {
    return TextField(
      controller: _input,
      focusNode: _focus,
      // Focus is requested AFTER the slide (see initState) so the keyboard-raise doesn't stall the
      // transition (F20); listening never auto-focuses (let the user hear first).
      autofocus: false,
      // The keyboard is told which language this card is answered in — the card's own studied side,
      // not the app's language and not the profile's, exactly as the voice is (MIX-1b). On iOS this
      // is a statement with no listener (Flutter honours `hintLocales` on Android only, and no iOS
      // app may switch the keyboard anyway); it is still the right thing to say, and the layout
      // guard in [_submitTyped] is what actually catches the case here.
      hintLocales: [keyboardLocaleFor(widget.answerLang)],
      style: hideText
          ? const TextStyle(color: Colors.transparent, height: 0.01)
          : AppTextExercise.typingInput,
      cursorColor: borderless ? Colors.transparent : AppColors.ink,
      textInputAction: TextInputAction.done,
      autocorrect: false,
      enableSuggestions: false,
      textCapitalization: TextCapitalization.none,
      // The hint lives exactly as long as the text that caused it: the first keystroke after it
      // takes it away, so a learner who switches the keyboard and starts retyping is not left
      // reading an accusation about characters that are gone.
      onChanged: _wrongKeyboard ? (_) => setState(() => _wrongKeyboard = false) : null,
      onSubmitted: (_) => _submitTyped(),
      decoration: InputDecoration(
        isDense: true,
        contentPadding: const EdgeInsets.only(bottom: AppSpacing.s8),
        enabledBorder: borderless
            ? noBorder
            : const UnderlineInputBorder(
                borderSide: BorderSide(color: AppColors.track, width: 1.5),
              ),
        focusedBorder: borderless
            ? noBorder
            : const UnderlineInputBorder(borderSide: BorderSide(color: AppColors.ink, width: 1.5)),
      ),
    );
  }

  Widget _options(AppLocalizations l) {
    final opts = _card.options ?? const <String>[];
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (var i = 0; i < opts.length; i++) ...[
          _SessionOption(
            text: opts[i],
            answered: _answered,
            // Marked by the SAME key _commit grades against, or the check would disagree with the
            // verdict on the very card it explains: an identity card matches the option's term id,
            // everything else the accepted set under the same typo policy. Getting that policy wrong
            // is what once put a green check on "Could you takes a photo…" — one character off.
            isAnswer: _card.isIdentityGraded
                ? _card.optionIdAt(i) == _card.answer
                : SessionGrader.check(
                    opts[i],
                    _card.answer,
                    variants: _card.acceptedVariants,
                    forgiveTypos: _mode.forgivesTypos,
                  ).isAccepted,
            isPicked: _picked == opts[i],
            onTap: () => _pick(opts[i], i),
            errorSpan: _card.feedbackFor(opts[i])?.errorSpan,
          ),
          if (i != opts.length - 1) const SizedBox(height: AppSpacing.s12),
        ],
        if (_wrongPickCorrection(l) case final line?) ...[
          const SizedBox(height: AppSpacing.s12),
          Text(line, style: AppTextExercise.answerOption.copyWith(color: AppColors.inkBody)),
        ],
      ],
    );
  }

  /// «должно быть: …» under a wrong pick_correct pick. Shown only after answering and only for a
  /// wrong pick — a correct pick has nothing to explain.
  String? _wrongPickCorrection(AppLocalizations l) {
    if (!_answered || _picked == null) return null;
    final feedback = _card.feedbackFor(_picked!);
    if (feedback == null || feedback.correction.isEmpty) return null;

    return l.sessionPickCorrectShouldBe(feedback.correction);
  }

  Widget _chipTray(AppLocalizations l) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Wrap(
          spacing: 10,
          runSpacing: 10,
          children: [
            for (var i = 0; i < _chips.length; i++)
              _WordChip(
                text: _chips[i],
                used: _placed.contains(i),
                onTap: _answered ? null : () => _placeChip(i),
              ),
          ],
        ),
        const SizedBox(height: AppSpacing.s16),
        Text(l.sessionChipReturnHint, style: AppTextExercise.taskInstruction),
      ],
    );
  }

  Widget _auxButtons(AppLocalizations l) {
    // «Подсказка: первая буква» takes two lines at half a screen; IntrinsicHeight + stretch keeps
    // the pair one row of equals instead of a tall button beside a short one (QA-OBS-29).
    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Expanded(
            child: QuietButton(
              label: l.sessionHintFirstLetter,
              onPressed: _usedHint ? null : _useFirstLetter,
            ),
          ),
          const SizedBox(width: AppSpacing.s12),
          Expanded(
            child: QuietButton(label: l.sessionDontRemember, onPressed: _giveUp),
          ),
        ],
      ),
    );
  }
}

// ── option (multiple choice / listening recognition) ──────────────────────────

class _SessionOption extends StatelessWidget {
  const _SessionOption({
    required this.text,
    required this.answered,
    required this.isAnswer,
    required this.isPicked,
    required this.onTap,
    this.errorSpan,
  });

  final String text;
  final bool answered;
  final bool isAnswer;
  final bool isPicked;
  final VoidCallback onTap;

  /// pick_correct: the broken fragment of THIS option, underlined once the answer is committed. Null
  /// for every other mode and for the correct option.
  final String? errorSpan;

  @override
  Widget build(BuildContext context) {
    // Post-answer marking: the correct option draws a sage underline + check; a wrong pick a
    // terracotta underline + cross. Untouched options stay plain.
    final showCorrect = answered && isAnswer;
    final showWrong = answered && isPicked && !isAnswer;
    final markColor = showCorrect
        ? AppColors.verdictKnown
        : (showWrong ? AppColors.destructiveText : null);
    final icon = showCorrect ? LucideIcons.check : (showWrong ? LucideIcons.x : null);

    return PaperCard(
      onTap: answered ? null : onTap,
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(child: _label(showWrong)),
              if (icon != null) Icon(icon, size: 17, color: markColor),
            ],
          ),
          if (markColor != null) ...[
            const SizedBox(height: 9),
            _DrawnUnderline(color: markColor, draw: showCorrect),
          ],
        ],
      ),
    );
  }

  /// The option's text, with the broken fragment marked once a wrong pick is committed. This is the
  /// point of pick_correct over multiple_choice: the learner sees WHERE the sentence went wrong
  /// instead of just that it did. Falls back to plain text whenever the span cannot be located —
  /// the server validates that it occurs in the sentence, so that is a belt-and-braces path.
  Widget _label(bool showWrong) {
    final span = errorSpan;
    if (!showWrong || span == null || span.isEmpty) {
      return Text(text, style: AppTextExercise.answerOption);
    }

    final at = spanPositionIn(text, span);
    if (at < 0) return Text(text, style: AppTextExercise.answerOption);

    return Text.rich(
      TextSpan(
        style: AppTextExercise.answerOption,
        children: [
          TextSpan(text: text.substring(0, at)),
          TextSpan(
            // Marked, not recoloured away: the wrong words stay readable, which is what makes the
            // correction below make sense.
            text: text.substring(at, at + span.length),
            style: const TextStyle(
              color: AppColors.destructiveText,
              decoration: TextDecoration.underline,
              decorationStyle: TextDecorationStyle.wavy,
            ),
          ),
          TextSpan(text: text.substring(at + span.length)),
        ],
      ),
    );
  }
}

/// A 2-px verdict underline. The correct one draws left→right (220 ms ease-out, §4е); a wrong
/// mark shows immediately full-width (it's not a reward). Reduce-motion → instant either way.
class _DrawnUnderline extends StatelessWidget {
  const _DrawnUnderline({required this.color, required this.draw});
  final Color color;
  final bool draw;

  @override
  Widget build(BuildContext context) {
    final line = SizedBox(height: 2, child: ColoredBox(color: color));
    if (!draw || MediaQuery.of(context).disableAnimations) {
      return line;
    }
    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0, end: 1),
      duration: AppMotion.answerCorrect,
      curve: AppMotion.easeOut,
      builder: (_, t, _) => Align(
        alignment: Alignment.centerLeft,
        child: FractionallySizedBox(widthFactor: t, child: line),
      ),
    );
  }
}

// ── word-bank pieces ──────────────────────────────────────────────────────────

class _WordChip extends StatelessWidget {
  const _WordChip({required this.text, required this.used, required this.onTap});
  final String text;
  final bool used;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    // A placed chip leaves a faded copy behind (§4е); an available chip is raised paper.
    if (used) {
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 15, vertical: 9),
        decoration: BoxDecoration(
          color: AppColors.faintInk,
          borderRadius: BorderRadius.circular(AppRadii.field),
        ),
        child: Text(
          text,
          style: AppTextExercise.dictionaryChip.copyWith(color: AppColors.tertiary),
        ),
      );
    }
    return Material(
      color: AppColors.surfaceRaised,
      borderRadius: BorderRadius.circular(AppRadii.field),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadii.field),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 15, vertical: 9),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(AppRadii.field),
            boxShadow: AppShadows.card,
          ),
          child: Text(text, style: AppTextExercise.dictionaryChip),
        ),
      ),
    );
  }
}

class _AssemblyLine extends StatelessWidget {
  const _AssemblyLine({
    required this.words,
    required this.answered,
    required this.correct,
    required this.onTapWord,
    this.mistakes = const {},
    this.letters = false,
  });

  final List<String> words;
  final bool answered;
  final bool correct;
  final ValueChanged<int> onTapWord;

  /// Are the chips LETTERS rather than words? word_bank deals letters for a single word
  /// (BUGFIX-2 Ч.2б D2), and the empty line's hint has to name what is actually on screen.
  final bool letters;

  /// Indices of [words] that do not belong in the answer — marked once a WRONG verdict is in. The
  /// line already kept the learner's own sentence; what it did not do was say WHERE it went wrong,
  /// so a wrong answer was a terracotta rule under a sentence that looked fine (QA-16).
  final Set<int> mistakes;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final underline = answered
        ? (correct ? AppColors.verdictKnown : AppColors.destructiveText)
        : AppColors.track;
    return Container(
      constraints: const BoxConstraints(minHeight: 42),
      decoration: BoxDecoration(
        border: Border(bottom: BorderSide(color: underline, width: 1.5)),
      ),
      padding: const EdgeInsets.only(bottom: 7),
      child: words.isEmpty
          // Empty, the line is the whole affordance — so it has to be VISIBLE. A bare
          // SizedBox(height:) is zero-wide, and the parent Column is start-aligned, so the
          // container collapsed to nothing and the underline never drew: the card read as a blank
          // box with no hint of where the words go. Stretch it across the card and name the
          // gesture. The hint is dropped once answered — «Не помню» leaves the line empty, and a
          // verdict-coloured line captioned "tap the words" would be instructions after the fact.
          ? SizedBox(
              width: double.infinity,
              height: 30,
              child: answered
                  ? null
                  : Align(
                      alignment: Alignment.centerLeft,
                      child: Text(
                        letters ? l.sessionAssemblyEmptyHintLetters : l.sessionAssemblyEmptyHint,
                        style: AppTextExercise.taskInstruction,
                      ),
                    ),
            )
          : Wrap(
              // Letters are ONE WORD being built, so they stand shoulder to shoulder; words are a
              // phrase and keep the gap that separates them. At the word spacing a letter line read
              // as «d o l p h i n» — seven tokens rather than the word it is.
              spacing: letters ? 1 : 9,
              runSpacing: 4,
              crossAxisAlignment: WrapCrossAlignment.end,
              children: [
                for (var i = 0; i < words.length; i++)
                  GestureDetector(
                    onTap: answered ? null : () => onTapWord(i),
                    child: Text(
                      words[i],
                      style: mistakes.contains(i)
                          // Marked, not recoloured away — the same wavy terracotta pick_correct
                          // draws under a broken fragment, and for the same reason: the wrong word
                          // stays readable, which is what makes the correction below mean anything.
                          ? AppTextExercise.assemblyLine.copyWith(
                              color: AppColors.destructiveText,
                              decoration: TextDecoration.underline,
                              decorationStyle: TextDecorationStyle.wavy,
                              decorationColor: AppColors.destructiveText,
                            )
                          : AppTextExercise.assemblyLine,
                    ),
                  ),
              ],
            ),
    );
  }
}

// ── cloze ────────────────────────────────────────────────────────────────────

class _ClozeSentence extends StatelessWidget {
  const _ClozeSentence({
    required this.example,
    required this.answer,
    required this.filled,
    required this.answered,
    required this.correct,
    this.mistakes = const {},
  });

  final String example;
  final String answer;

  /// The word to show in the blank — the learner's OWN text throughout, live while they type and
  /// still theirs after the verdict; null when they typed nothing («Не помню»). It used to become
  /// the correct form on answering, which showed them an answer they had not given (QA-16).
  final String? filled;
  final bool answered;
  final bool correct;

  /// Indices of [filled]'s words that do not belong — marked when the verdict is wrong.
  final Set<int> mistakes;

  @override
  Widget build(BuildContext context) {
    // Split the example around the answer (case-insensitive), keeping the sentence in italic
    // antiqua. If the answer isn't found, put the blank at the end so the card still plays.
    final idx = example.toLowerCase().indexOf(answer.toLowerCase());
    final before = idx >= 0 ? example.substring(0, idx) : '$example ';
    final after = idx >= 0 ? example.substring(idx + answer.length) : '';

    final InlineSpan blank;
    if (filled == null) {
      // Empty blank ≈ the word's width, with a caret so it reads as "type here" (12i).
      blank = WidgetSpan(
        alignment: PlaceholderAlignment.baseline,
        baseline: TextBaseline.alphabetic,
        child: Container(
          width: 100,
          height: 22,
          alignment: Alignment.centerLeft,
          decoration: const BoxDecoration(
            border: Border(bottom: BorderSide(color: AppColors.tertiary, width: 1.5)),
          ),
          child: const SizedBox(width: 1.5, height: 20, child: ColoredBox(color: AppColors.ink)),
        ),
      );
    } else {
      // Answered → verdict-coloured underline; while typing → a plain ink underline (no verdict yet).
      // A marked word carries the wavy terracotta instead, the same mark pick_correct puts under a
      // broken fragment: the word stays readable and is plainly named as the thing that was wrong.
      final marked = mistakes.isNotEmpty;
      blank = TextSpan(
        text: filled,
        style: AppTextExercise.clozeExample.copyWith(
          fontStyle: FontStyle.normal,
          fontWeight: FontWeight.w500,
          color: marked ? AppColors.destructiveText : AppColors.ink,
          decoration: TextDecoration.underline,
          decorationStyle: marked ? TextDecorationStyle.wavy : TextDecorationStyle.solid,
          decorationColor: answered
              ? (correct ? AppColors.verdictKnown : AppColors.destructiveText)
              : AppColors.tertiary,
          decorationThickness: answered && !marked ? 2 : 1.5,
        ),
      );
    }

    return Text.rich(
      TextSpan(
        style: AppTextExercise.clozeExample,
        children: [
          TextSpan(text: before),
          blank,
          TextSpan(text: after),
        ],
      ),
    );
  }
}

// ── listening play circle ─────────────────────────────────────────────────────

/// «ПОКАЗАТЬ ТЕКСТ» — what was said, revealed once the meaning has been chosen.
///
/// After the answer and never before it: the hear card's question IS the sound, so printing the
/// sentence early answers it. Speakable, because a learner who missed it wants to hear it again
/// while reading it.
class _RevealedLine extends StatefulWidget {
  const _RevealedLine({required this.text, required this.onSpeak});

  final String text;
  final Future<void> Function(String text, {bool slow}) onSpeak;

  @override
  State<_RevealedLine> createState() => _RevealedLineState();
}

class _RevealedLineState extends State<_RevealedLine> {
  bool _shown = false;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    if (!_shown) {
      return Center(
        child: QuietButton(
          label: l.sessionSituationRevealText,
          icon: LucideIcons.eye,
          onPressed: () => setState(() => _shown = true),
        ),
      );
    }

    return PaperCard(
      child: InkWell(
        onTap: () => widget.onSpeak(widget.text),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Padding(
              padding: EdgeInsets.only(top: 3, right: AppSpacing.s8),
              child: Icon(LucideIcons.volume2, size: 15, color: AppColors.brassInk),
            ),
            Expanded(
              child: Text(
                widget.text,
                style: AppText.stepTitle.copyWith(fontSize: 17, height: 1.4),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// ДЕВ-РЯД QA: подставить транскрипт вместо голоса — наряд SCENE-RUN, Ч.2.9.
///
/// Существует ровно ради одного: на симуляторе микрофона нет, и без подстановки ступень C нечем
/// пройти живьём — прогон сцены невозможно ни снять, ни принять. Дверь стережёт сервер
/// ({@see AppUser.qaTools}), поэтому здесь нет ни одной собственной проверки: второе правило про то
/// же самое однажды разошлось бы с первым, и разошлось бы в сторону открытой двери.
///
/// Три кнопки, потому что исходов у хода три и они не выводятся один из другого: «сразу» и «сказал»
/// различаются ВРЕМЕНЕМ, а не текстом, и без отдельной кнопки подставленный ход всегда был бы
/// быстрым.
///
/// Подписи латиницей и намеренно: это не интерфейс продукта, а инструмент, и человеку, который
/// учит язык, его не показывают.
class _QaTranscriptRow extends StatelessWidget {
  const _QaTranscriptRow({required this.onSaid, required this.onMissed});

  final void Function(bool fast) onSaid;
  final VoidCallback onMissed;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(child: QuietButton(label: 'QA · fast', onPressed: () => onSaid(true))),
        const SizedBox(width: 8),
        Expanded(child: QuietButton(label: 'QA · said', onPressed: () => onSaid(false))),
        const SizedBox(width: 8),
        Expanded(child: QuietButton(label: 'QA · miss', onPressed: onMissed)),
      ],
    );
  }
}

/// ДЕВ-ДВЕРЬ: «сказал две трети реплики» — вердикт «почти» (наряд SPEECH-2, Ч.6).
///
/// Отдельной строкой, а не четвёртой кнопкой в ряду выше: тот ряд про то, ЧТО сказано (реплика,
/// мимо) и КОГДА (сразу, не сразу), а это про то, СКОЛЬКО. На симуляторе микрофона нет, и «почти»
/// иначе не увидеть ничем — а именно этот вердикт наряд и завёл.
class _QaHalfLineRow extends StatelessWidget {
  const _QaHalfLineRow({required this.onSaid});

  final VoidCallback onSaid;

  @override
  Widget build(BuildContext context) =>
      QuietButton(label: 'QA · part', onPressed: onSaid);
}

class _PlayCircle extends StatefulWidget {
  const _PlayCircle({required this.onTap, required this.label});
  final VoidCallback onTap;
  final String label;

  @override
  State<_PlayCircle> createState() => _PlayCircleState();
}

class _PlayCircleState extends State<_PlayCircle> with SingleTickerProviderStateMixin {
  late final AnimationController _pulse = AnimationController(
    vsync: this,
    duration: AppMotion.listenPulse,
    lowerBound: 1.0,
    upperBound: 1.04,
  );

  @override
  void dispose() {
    _pulse.dispose();
    super.dispose();
  }

  void _tap() {
    AppHaptics.light();
    if (!MediaQuery.of(context).disableAnimations) {
      _pulse.forward(from: 1.0).then((_) => _pulse.reverse());
    }
    widget.onTap();
  }

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      label: widget.label,
      child: GestureDetector(
        onTap: _tap,
        child: ScaleTransition(
          scale: _pulse,
          child: Container(
            width: 112,
            height: 112,
            decoration: const BoxDecoration(
              color: AppColors.ink,
              shape: BoxShape.circle,
              boxShadow: AppShadows.anchor,
            ),
            child: const Icon(LucideIcons.volume2, color: AppColors.paper, size: 44),
          ),
        ),
      ),
    );
  }
}

// ── speaking record button ────────────────────────────────────────────────────

/// The speaking card's one affordance: a big circle that starts listening, and while listening
/// breathes so the learner can see the phone is awake.
///
/// Deliberately the same size and weight as the listening card's play circle — the two are a pair
/// («here is the word», «now say it»), and giving them different shapes would suggest they are
/// different kinds of task.
class _RecordCircle extends StatefulWidget {
  const _RecordCircle({required this.listening, required this.onTap, required this.label});

  final bool listening;
  final VoidCallback onTap;
  final String label;

  @override
  State<_RecordCircle> createState() => _RecordCircleState();
}

class _RecordCircleState extends State<_RecordCircle> with SingleTickerProviderStateMixin {
  late final AnimationController _pulse = AnimationController(
    vsync: this,
    duration: AppMotion.listenPulse,
    lowerBound: 1.0,
    upperBound: 1.06,
  );

  @override
  void didUpdateWidget(_RecordCircle old) {
    super.didUpdateWidget(old);
    if (widget.listening == old.listening) return;
    if (widget.listening && !MediaQuery.of(context).disableAnimations) {
      _pulse.repeat(reverse: true);
    } else {
      _pulse.stop();
      _pulse.value = 1.0;
    }
  }

  @override
  void dispose() {
    _pulse.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    // Listening inverts the circle — outlined while idle, filled while it hears you. One glance
    // answers the only question this card has ("is it recording?"), without a word of copy.
    final filled = widget.listening;
    return Semantics(
      button: true,
      label: widget.label,
      child: GestureDetector(
        onTap: widget.onTap,
        child: ScaleTransition(
          scale: _pulse,
          child: Container(
            width: 112,
            height: 112,
            decoration: BoxDecoration(
              color: filled ? AppColors.ink : AppColors.surfaceRaised,
              shape: BoxShape.circle,
              border: filled
                  ? null
                  : const Border.fromBorderSide(BorderSide(color: AppColors.ink, width: 1.5)),
              boxShadow: filled ? AppShadows.anchor : AppShadows.card,
            ),
            child: Icon(LucideIcons.mic, color: filled ? AppColors.paper : AppColors.ink, size: 44),
          ),
        ),
      ),
    );
  }
}

// ── prompt photo (from the local term mirror) ─────────────────────────────────

class _PromptPhoto extends ConsumerStatefulWidget {
  const _PromptPhoto({required this.termId, this.url, this.resolved = false, this.reveal = true});

  final String termId;

  /// The photo url the shell already resolved, and whether that lookup has FINISHED. When resolved,
  /// no query runs here at all and the banner takes its final height on the very first frame.
  final String? url;
  final bool resolved;

  /// May an image that is not already decoded appear now? False during the slide-in.
  final bool reveal;

  @override
  ConsumerState<_PromptPhoto> createState() => _PromptPhotoState();
}

class _PromptPhotoState extends ConsumerState<_PromptPhoto> {
  /// Whether this photo's bytes are already on disk — asked once, while building, because it
  /// changes how the image may appear (no fade). A synchronous map lookup, not I/O.
  bool get _fromDisk => CachedNetworkImage.isCached(widget.url);

  // Fallback only — used when the shell hasn't resolved this term (e.g. the feedback photo of a
  // card whose warm-up hasn't run). `late final` means it never executes if never read.
  late final Future<Term?> _term = ref.read(appDatabaseProvider).termById(widget.termId);

  @override
  Widget build(BuildContext context) {
    if (widget.resolved) return _banner(context, widget.url);
    return FutureBuilder<Term?>(
      future: _term,
      builder: (context, snap) {
        if (snap.connectionState != ConnectionState.done) {
          // Height unknown yet: reserve the banner so resolving it doesn't jump the layout.
          return _plate();
        }
        return _banner(context, snap.data?.imageUrl);
      },
    );
  }

  /// The empty banner slot — a grey plate at the final height.
  Widget _plate() => const Padding(
    padding: EdgeInsets.only(bottom: 14),
    child: SizedBox(
      height: 150,
      width: double.infinity,
      child: ColoredBox(color: AppColors.track),
    ),
  );

  Widget _banner(BuildContext context, String? url) {
    if (url == null || url.isEmpty) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(AppRadii.field),
        child: SizedBox(
          height: 150,
          width: double.infinity,
          // The plate sits UNDER the image, so a not-yet-decoded photo shows grey rather than a
          // hole of paper colour. Before, the slot went grey → blank → picture, which is three
          // visible steps for one card (F20-r).
          child: Stack(
            fit: StackFit.expand,
            children: [
              const ColoredBox(color: AppColors.track),
              Image(
                // Bytes come from the disk cache when we have them (F22), so a photo seen once
                // renders in airplane mode and after a restart. Everything around it is unchanged:
                // same ResizeImage key, same decode width, same entry the warm-up filled.
                image: ResizeImage(CachedNetworkImage(url), width: promptPhotoCacheWidth(context)),
                fit: BoxFit.cover,
                frameBuilder: (context, child, frame, wasSync) {
                  // Already decoded (warm cache) → straight in, no fade. That is F20's win and it
                  // must survive.
                  if (wasSync) return child;
                  // On disk → the bytes are local, so the decode lands in a frame or two. Fading
                  // that in would invent a delay the user does not have; the banner's height is
                  // already reserved, so appearing at once moves nothing (F20-r's rule was about
                  // pictures materialising mid-slide, which the reveal gate still prevents).
                  if (_fromDisk) {
                    return Opacity(opacity: frame != null && widget.reveal ? 1 : 0, child: child);
                  }
                  // Cold: hold the plate until the slide settles, then fade.
                  return AnimatedOpacity(
                    opacity: frame != null && widget.reveal ? 1 : 0,
                    duration: const Duration(milliseconds: 180),
                    child: child,
                  );
                },
                errorBuilder: (_, _, _) => const SizedBox.shrink(), // the plate stays
              ),
            ],
          ),
        ),
      ),
    );
  }
}

// ── feedback (12d, in the bottom of the same card) ────────────────────────────

class _FeedbackBlock extends ConsumerWidget {
  const _FeedbackBlock({
    required this.card,
    required this.verdict,
    required this.onSpeak,
    this.photoUrl,
    this.photoResolved = false,
    this.showDue = true,
    this.recognizedText,
    this.spoken,
  });

  final SessionCard card;
  final LocalCheck verdict;

  /// ВЕРДИКТ ПРО СКАЗАННОЕ, целиком (наряд SPEECH-2, Ч.3.5). Null на всём, что не судится речью —
  /// тогда строку вердикта пишет [verdict], как писал всегда.
  final SpokenVerdict? spoken;

  final Future<void> Function(String) onSpeak;

  /// Same resolved photo the prompt uses — the feedback of a wrong answer shows it too.
  /// Unused on a speaking card: see [_isSpeaking] below.
  final String? photoUrl;
  final bool photoResolved;

  /// See [SessionExerciseCard.showDue].
  final bool showDue;

  /// What the recogniser heard, for a speaking card only (null on every other mode). Shown on
  /// BOTH outcomes (QA-20) — a wrong verdict with nothing to compare against left the learner
  /// unable to tell a genuine miss from a channel problem the recogniser mangled.
  final String? recognizedText;

  bool get _isSpeaking => card.mode == ExerciseMode.speaking;

  /// Is this card's spoken answer judged by coverage (QA-22)? The same one rule the card itself
  /// grades by — the highlight has to follow the verdict, or it would mark words on a card that
  /// was never compared word by word.
  bool get _gradesByCoverage =>
      _isSpeaking &&
      SpokenAnswer.gradesByCoverage(asksForExample: card.asksForExample, term: card.answerText);

  /// Indices into the target's own displayed words with no pair in what was recognised — empty
  /// wherever grading is binary, where there is neither a multi-word target worth marking nor a
  /// word-by-word comparison behind the verdict to justify marking it.
  Set<int> get _uncoveredWords {
    final heard = recognizedText;
    if (heard == null || heard.trim().isEmpty) return const {};

    // A KEYED LINE MARKS ITS KEY AND NOTHING ELSE. The verdict was computed over the key alone, so
    // the marks have to be too — underlining the frame says the learner got wrong something the
    // card never asked for, which is what seven of fifteen underlined words said on 01.09.
    final key = card.spokenTarget;
    if (key != null) {
      final span = SessionGrader.keyWordIndices(card.answerText, key);
      if (span.isEmpty) return const {};

      // Indices INTO THE KEY, mapped back onto the sentence through the span the scan found.
      final missing = SessionGrader.uncoveredWords(heard, key, ignoreArticles: true);
      return {
        for (final i in missing)
          if (i < span.length) span[i],
      };
    }

    if (!_gradesByCoverage) return const {};

    // Matches the verdict's own comparison (QA-21) — marking an article the verdict forgave would
    // point at a "mistake" that was not counted as one.
    return SessionGrader.uncoveredWords(heard, card.answerText, ignoreArticles: true);
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final wrong = verdict == LocalCheck.wrong;
    final heard = recognizedText?.trim() ?? '';

    final verdictRow = _verdictRow(l);
    final content = <Widget>[
      verdictRow,
      if (_isSpeaking && heard.isNotEmpty) ...[
        const SizedBox(height: AppSpacing.s4),
        Text(l.sessionSpeakHeard(heard), style: AppTextExercise.feedbackTranscription),
      ],
      if (wrong) ...[
        const SizedBox(height: AppSpacing.s16),
        // The feedback lands on a STATIC screen, so revealing straight away is fine here — except
        // on a speaking card, which stays compact and skips the photo (QA-20): term, transcription,
        // what was heard (above) and pronunciation are the whole point of that verdict, not a
        // second copy of the prompt's image.
        if (!_isSpeaking) _PromptPhoto(termId: card.termId, url: photoUrl, resolved: photoResolved),
        Row(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            // [answerText], never [answer]: on the identity-graded card the key is a term id, and
            // printing it here is what put a raw ULID where «over the counter» belonged.
            //
            // The speaking example form marks its own unmatched words instead of the plain
            // typewriter reveal (QA-20) — a wrong sentence-form reading is exactly the case where
            // WHICH part failed to register is worth showing, and the write-on animation has
            // nothing to say about that.
            Expanded(
              child: _gradesByCoverage || card.spokenTarget != null
                  ? _SpokenSentence(sentence: card.answerText, uncovered: _uncoveredWords)
                  : _WritesItself(text: card.answerText, style: AppTextExercise.feedbackTerm),
            ),
            _SpeakDot(onTap: () => onSpeak(card.answerText)),
          ],
        ),
        // The transcription belongs to the TERM; under a whole sentence it reads as nonsense.
        if (!card.asksForExample &&
            card.transcription != null &&
            card.transcription!.isNotEmpty) ...[
          const SizedBox(height: AppSpacing.s4),
          Text('/${card.transcription}/', style: AppTextExercise.feedbackTranscription),
        ],
      ],
      // On a sentence card the example IS the answer, already shown above — printing it again as
      // "the example" would just be the same line twice.
      if (!card.asksForExample && card.example != null && card.example!.isNotEmpty) ...[
        const SizedBox(height: AppSpacing.s12),
        Text(card.example!, style: AppText.usageExample),
      ],
      if (showDue) _NextDue(termId: card.termId),
    ];

    return AnimatedSize(
      duration: AppMotion.feedbackReveal,
      curve: AppMotion.easeOut,
      alignment: Alignment.topCenter,
      child: PaperCard(
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: content),
      ),
    );
  }

  Widget _verdictRow(AppLocalizations l) {
    // РЕЧЬ ГОВОРИТ ТРЕМЯ СЛОВАМИ, А НЕ ДВУМЯ (наряд SPEECH-2, Ч.3.5).
    //
    // «Почти» стоит между «верно» и «не то» потому, что между ними есть что сказать: реплика
    // узнана, но часть слов не прозвучала — и человек, которому вместо этого печатают «Не то»,
    // не знает, промахнулся он мыслью или дикцией. Список пропущенных слов отвечает на это
    // ровно тем, чего не хватило.
    //
    // Цвет «почти» — НЕ зелёный: в журнал уходит тот же промах, что и у «не то», и зелёная
    // галочка над строкой, которую сервер оценил `again`, — это ровно то расхождение экрана и
    // журнала, которое чинил DAY-GATE-1.
    if (spoken case final s?) {
      final (color, icon, text) = switch (s.credit) {
        SpokenCredit.correct => (
          AppColors.verdictKnown,
          LucideIcons.check,
          l.sessionSpeakVerdictCorrect,
        ),
        SpokenCredit.almost => (
          AppColors.verdictUnsure,
          LucideIcons.circleDashed,
          l.sessionSpeakVerdictAlmost(s.missing.join(', ')),
        ),
        SpokenCredit.wrong => (
          AppColors.destructiveText,
          LucideIcons.x,
          l.sessionSpeakVerdictWrong,
        ),
      };

      return Row(
        children: [
          Icon(icon, size: 17, color: color),
          const SizedBox(width: 9),
          Flexible(
            child: Text(text, style: AppTextExercise.feedbackVerdict.copyWith(color: color)),
          ),
        ],
      );
    }

    final (color, icon, text) = switch (verdict) {
      LocalCheck.correct => (AppColors.verdictKnown, LucideIcons.check, l.sessionFeedbackCorrect),
      LocalCheck.typo => (AppColors.verdictKnown, LucideIcons.check, l.sessionFeedbackAlmost),
      // Where the correct answer actually IS decides which sentence this is. A tapped card marks it
      // in the option list above; a typed or assembled one has it right below, in this very block.
      LocalCheck.wrong => (
        AppColors.destructiveText,
        LucideIcons.x,
        card.answeredByTapping ? l.sessionFeedbackWrongAbove : l.sessionFeedbackWrong,
      ),
    };
    return Row(
      children: [
        Icon(icon, size: 17, color: color),
        const SizedBox(width: 9),
        Flexible(
          child: Text.rich(
            TextSpan(
              style: AppTextExercise.feedbackVerdict.copyWith(color: color),
              children: [
                TextSpan(text: text),
                // A typo shows the corrected form right after «Почти:».
                if (verdict == LocalCheck.typo)
                  TextSpan(text: ' ${card.answerText}', style: AppTextExercise.feedbackCorrectForm),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

/// The real next-due, read reactively from the local progress mirror — it lands after the
/// answer's upload + sync. The client never computes an interval; if the schedule isn't known
/// yet (offline / not synced), the line is simply absent.
class _NextDue extends ConsumerWidget {
  const _NextDue({required this.termId});
  final String termId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final prog = ref.watch(termProgressForProvider(termId));
    final due = prog.value?.dueAt;
    if (due == null) return const SizedBox.shrink();
    final days = daysUntil(due.toLocal(), DateTime.now());
    final when = days == 0
        ? l.sessionDueToday
        : days == 1
        ? l.sessionDueTomorrow
        : l.sessionDueInDays(days);
    return Padding(
      padding: const EdgeInsets.only(top: AppSpacing.s16),
      child: Text(l.sessionSeeAgain(when), style: AppTextExercise.feedbackNextDue),
    );
  }
}

/// The target sentence of a wrong speaking example-form verdict, with the words that had no pair
/// in what was recognised marked — the same wavy terracotta [_WordBankLine]/[_ClozeSentence] put
/// under a broken fragment, here naming the part of the CORRECT sentence that seems to have been
/// skipped or cut off, rather than a mistake in the learner's own words (QA-20).
class _SpokenSentence extends StatelessWidget {
  const _SpokenSentence({required this.sentence, required this.uncovered});

  final String sentence;
  final Set<int> uncovered;

  @override
  Widget build(BuildContext context) {
    final words = sentence.trim().isEmpty
        ? const <String>[]
        : sentence.trim().split(RegExp(r'\s+'));

    return Text.rich(
      TextSpan(
        style: AppTextExercise.feedbackTerm,
        children: [
          for (var i = 0; i < words.length; i++) ...[
            if (i > 0) const TextSpan(text: ' '),
            TextSpan(
              text: words[i],
              style: uncovered.contains(i)
                  ? const TextStyle(
                      color: AppColors.destructiveText,
                      decoration: TextDecoration.underline,
                      decorationStyle: TextDecorationStyle.wavy,
                      decorationColor: AppColors.destructiveText,
                    )
                  : null,
            ),
          ],
        ],
      ),
    );
  }
}

/// The correct form «writes itself»: characters reveal in sequence, 24 ms each, capped ≤ 350 ms
/// (§4е). Reduce-motion → the whole word at once.
class _WritesItself extends StatelessWidget {
  const _WritesItself({required this.text, required this.style});
  final String text;
  final TextStyle style;

  @override
  Widget build(BuildContext context) {
    if (MediaQuery.of(context).disableAnimations || text.isEmpty) {
      return Text(text, style: style);
    }
    final total = Duration(
      milliseconds: (AppMotion.writePerChar.inMilliseconds * text.characters.length).clamp(
        0,
        AppMotion.writeTotalCap.inMilliseconds,
      ),
    );
    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0, end: 1),
      duration: total,
      curve: AppMotion.easeOut,
      builder: (_, t, _) {
        final n = (t * text.characters.length).round().clamp(0, text.characters.length);
        return Text(text.characters.take(n).toString(), style: style);
      },
    );
  }
}

class _SpeakDot extends StatelessWidget {
  const _SpeakDot({required this.onTap});
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      child: InkResponse(
        onTap: onTap,
        radius: 24,
        child: Container(
          width: 38,
          height: 38,
          decoration: const BoxDecoration(
            shape: BoxShape.circle,
            border: Border.fromBorderSide(BorderSide(color: AppColors.hairline)),
          ),
          child: const Icon(LucideIcons.volume2, size: 18, color: AppColors.ink),
        ),
      ),
    );
  }
}
