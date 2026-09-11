import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';
import 'package:eng_std/l10n/app_localizations.dart';

import '../../../data/app_settings.dart';
import '../../../data/local/cached_image_provider.dart';
import '../../../data/models.dart';
import '../../../data/providers.dart';
import '../../../data/speech/speech_diagnostics.dart';
import '../../../data/speech/speech_grading_config.dart';
import '../../../data/speech/speech_recognizer.dart';
import '../../../data/speech/speech_turn.dart';
import 'session_exercise.dart';
import 'session_grading.dart';
import 'spoken_line.dart';

/// The zeroth rung of the acquisition ladder: the word is SHOWN, not asked (кадр 16b).
///
/// A separate widget from [SessionExerciseCard] because it is not an exercise. It has no options,
/// no input, no verdict and no colour — the single exit is «Понятно →», and the only thing it
/// produces is the fact that the learner saw the word. Threading that through the exercise card's
/// answering→feedback state machine would mean an "answer" with no answer in it, which is exactly
/// the shape the review log must never be handed.
///
/// The order is the mock's: TERM first, so typography meets the reader before anything else; then
/// the translation; then the example with the term set in bold inside it; then the photo, which
/// CONFIRMS the meaning rather than announcing it; then «также:»; and the «новое слово» badge last,
/// just above «Понятно» — it says what kind of card this is, which is a footnote and not an opening
/// line. The badge is an outline, not a fill — nothing here is a verdict, so nothing here is coloured.
class SessionIntroCard extends ConsumerStatefulWidget {
  const SessionIntroCard({
    super.key,
    required this.card,
    required this.onSpeak,
    this.photoUrl,
    this.photoResolved = false,
    this.autoPronounce = true,
    this.showExample = true,
    required this.speechLocaleId,
    this.speech = SpeechGradingConfig.empty,
    this.isCurrent = _alwaysCurrent,
  });

  static bool _alwaysCurrent() => true;

  final SessionCard card;

  /// The shell's TTS. The intro speaks the TERM — the thing being learned — not the example.
  final Future<void> Function(String text, {bool slow}) onSpeak;

  final String? photoUrl;
  final bool photoResolved;
  final bool autoPronounce;

  /// Does this card carry an example sentence at all?
  ///
  /// False for a plan LINE, which has none by contract: the line is the sentence being learned, its
  /// gap is cut out of its own frame, and a sentence written around it teaches nothing. The live day
  /// of 02.09 had one on every line — «I see, without utilities.» was introduced by «When the power
  /// went out, I realized that I see, without utilities, life becomes…» — and the intro is the card
  /// that showed it. True everywhere else, which is every word and connector and every card outside
  /// a plan.
  final bool showExample;

  /// Recognition locale for the echo — the language being learned, off THIS card's pair. Required,
  /// with no default: a constant here would quietly listen for English on an Italian word, which is
  /// the mixed-session bug this card's TTS side had (MIX-1b).
  final String speechLocaleId;

  /// ЧЕМ СУДИТЬ РЕЧЬ — пороги и таблица аббревиатур с сервера (наряд SPEECH-2, Ч.3.3 / Ч.4.2).
  /// Эхо теперь не только слушает, но и ГОВОРИТ, что вышло, — и говорит это тем же судьёй, что и
  /// все остальные карточки говорения.
  final SpeechGradingConfig speech;

  /// Still the on-screen card? A fast «Понятно» must cancel a deferred pronounce rather than fire
  /// it over the next card — the same rule the exercise card follows (F20).
  final bool Function() isCurrent;

  @override
  ConsumerState<SessionIntroCard> createState() => _SessionIntroCardState();
}

/// В КАКОМ СОСТОЯНИИ ЭХО ЗНАКОМСТВА.
///
/// `heard` больше не значит «микрофон сработал»: с наряда SPEECH-2 (Ч.3.1, Ч.6) эхо ГОВОРИТ, что
/// вышло, — «верно», «почти — не хватило: …», «не то». Оно по-прежнему НИЧЕГО НЕ ПИШЕТ: ни ревью,
/// ни показа, ни лестницы; вердикт здесь — ответ на вопрос «я это сказал?», а не оценка.
enum _Echo { idle, listening, heard, again }

class _SessionIntroCardState extends ConsumerState<SessionIntroCard> {
  Timer? _speakTimer;

  /// The echo's state. Starts [idle] and, if the learner never taps, stays there forever: the echo
  /// is entirely optional and the intro's «Понятно →» is reachable without it.
  _Echo _echo = _Echo.idle;

  /// ВЕРДИКТ ПОСЛЕДНЕЙ ПОПЫТКИ — «верно» / «почти — не хватило: …» / «не то» (наряд SPEECH-2,
  /// Ч.3.1, Ч.3.5). Null, пока попытки не было.
  ///
  /// Что здесь ИЗМЕНИЛОСЬ и почему. До наряда эхо говорило «Услышал тебя» и никогда — «неверно»:
  /// «сказать человеку, что его первая попытка нового слова неправильна, — самый быстрый способ
  /// отучить его говорить вслух». Наряд просит вердикт на всех трёх карточках с микрофоном, и он
  /// здесь есть — но остаётся ТРЁХСТУПЕНЧАТЫМ и мягким («почти» с перечнем пропущенных слов), и
  /// по-прежнему НЕ ПИШЕТСЯ никуда. Строгость даёт порог чтения с экрана: фраза стоит перед
  /// глазами, и «прочитал» — вопрос с ответом, а не суждение о памяти.
  SpokenVerdict? _verdict;

  /// What the recogniser transcribed on the last attempt, printed back under the button.
  ///
  /// Showing it is not a step toward grading it (QA-21): a bare «Услышал тебя» left the learner
  /// unable to tell a good attempt from a mangled one, or even whether the microphone had heard
  /// THEM rather than the room. The text answers that and nothing else — there is still no verdict
  /// here, and there is not going to be one. Cleared at the start of every new attempt, so the
  /// screen never shows a previous try's words beside a fresh recording.
  String _heard = '';

  /// Resolved once, so `dispose` can close a microphone left open without reaching for `ref` on a
  /// widget that is already coming down.
  late final SpeechRecognizer _recognizer = ref.read(speechRecognizerProvider);

  /// ТОТ ЖЕ ЖУРНАЛ, ЧТО У ВСЕХ ОСТАЛЬНЫХ КАРТОЧЕК ГОВОРЕНИЯ (наряд DAY-GATE-1, доработка Ч.0.1).
  late final SpeechDiagnostics _diagnostics = ref.read(speechDiagnosticsProvider);

  /// ОДИН ДВИЖОК СЛУШАНИЯ НА ВСЕ КАРТОЧКИ ({@see SpeechTurn}). Эхо ходило в плагин НАПРЯМУЮ — мимо
  /// склейки, мимо сторожа и мимо диагностики, — и поэтому: запинка длиннее паузы плагина обрывала
  /// попытку на полуслове («ба…» вместо «background»), а служебная строка про этот микрофон молчала
  /// вовсе, хотя эхо знакомства — очень часто ПЕРВЫЙ микрофон в запуске и, значит, первое место,
  /// где поломка канала видна.
  ///
  /// Что НЕ изменилось: ход по-прежнему ничего не пишет — ни ревью, ни показа, ни лестницы. Ключа
  /// движку не дают (`isAnswer` не передан), потому что здесь нечего засчитывать.
  SpeechTurn? _turn;

  @override
  void initState() {
    super.initState();
    if (widget.autoPronounce) {
      // After the slide, like every other deferred effect on this screen — a channel call on the
      // transition's first frame is what F20 measured as a stall.
      _speakTimer = Timer(AppMotion.nextTaskEnter + const Duration(milliseconds: 60), () {
        if (mounted && widget.isCurrent()) widget.onSpeak(widget.card.answerText);
      });
    }
    // НИЧЕГО НЕ СПРАШИВАЕМ НА ОТКРЫТИИ (наряд SPEECH-2, Ч.1.4). До наряда карточка тихо ходила в
    // ОС за статусом, чтобы решить, показывать ли кнопку или приглашение вместо неё; теперь кнопка
    // стоит всегда, а разрешение спрашивает первый тап. Один вопрос вместо двух состояний.
  }

  @override
  void dispose() {
    _speakTimer?.cancel();
    // Карточку покинули посреди эха: ход бросается и НИЧЕГО не хранит — он и так ничего не писал.
    final turn = _turn;
    if (turn != null) {
      unawaited(turn.cancel());
    } else if (_echo == _Echo.listening) {
      unawaited(_recognizer.cancel());
    }
    super.dispose();
  }

  /// «Повторить вслух»: listen once, say something kind either way, and write NOTHING.
  ///
  /// No grade, no review, no exposure of its own, no effect on the ladder — the intro card's whole
  /// contract is that it asks for nothing, and an echo that could be failed would quietly turn it
  /// into the app's first exercise. What it is for is the mouth: hearing the word and then making
  /// it is how a word stops being a shape on a page, and doing that once, unwatched, is worth more
  /// here than any score would be.
  /// A second tap WHILE listening settles the attempt on whatever has been heard — the same
  /// «Готово» the speaking card offers. Without it the only way to end a recording was to go quiet
  /// and wait out the pause window, which on a card with no progress of any kind read as a hang
  /// (QA-21). Tapping again AFTER a result simply starts a fresh attempt, replacing the old text.
  Future<void> _echoBack() async {
    if (_echo == _Echo.listening) {
      await _turn?.stop();

      return;
    }
    // РАЗРЕШЕНИЕ СПРАШИВАЕТСЯ ПРИ ПЕРВОМ ТАПЕ (наряд SPEECH-2, Ч.1.4), а не при открытии карточки
    // и не заранее: карточка знакомства по-прежнему НИЧЕГО не требует, но и не прячет кнопку за
    // приглашением, которое человеку приходилось разгадывать.
    AppHaptics.light();
    if (!await _recognizer.prepare()) {
      if (mounted) setState(() => _echo = _Echo.again);

      return;
    }
    if (!mounted) return;
    setState(() {
      _echo = _Echo.listening;
      _heard = '';
      _verdict = null;
    });

    final term = widget.card.answerText;
    // Окно то же, что у говорения слова такой длины: фразоподобный термин требует
    // «предложенческого» (QA-21). Движку оно отдаётся паузой после речи — правилом закрытия
    // попытки владеет он, а не плагин.
    final window = SpokenAnswer.windowFor(asksForExample: false, term: term);
    final turn = SpeechTurn(
      _recognizer,
      config: SpeechTurnConfig(silenceAfterSpeech: window.pauseFor),
      diagnostics: _diagnostics,
    );
    _turn = turn;
    final SpeechTurnResult attempt;
    try {
      attempt = await turn.listen(
        expected: [term],
        localeId: widget.speechLocaleId,
        // The same vocabulary hint the speaking word form sends (QA-20): the term whole, plus its
        // individual words. Nothing here grades against it — it only helps the recogniser print
        // back what was actually said.
        contextualStrings: _contextualStrings(term),
        onPartial: (text) {
          if (mounted && _echo == _Echo.listening) setState(() => _heard = text);
        },
      );
    } finally {
      if (_turn == turn) _turn = null;
    }

    if (!mounted) return;
    // ФРАЗА СТОИТ ПЕРЕД ГЛАЗАМИ — значит, судится порогом чтения (наряд SPEECH-2, Ч.3.1), и
    // судится тем же {@see SpokenLine}, что и все остальные карточки говорения. Ключа у слова нет
    // и не нужно: задача — сказать это.
    final verdict = attempt.isHeard
        ? SpokenLine.judge(
            transcript: attempt.transcript,
            line: term,
            printed: true,
            config: widget.speech,
          )
        : null;
    if (verdict != null) {
      _diagnostics.verdictIs(
        normalized: verdict.normalized,
        coverage: verdict.coverage,
        threshold: verdict.threshold,
        credit: verdict.credit.name,
      );
    }
    setState(() {
      _echo = attempt.isHeard ? _Echo.heard : _Echo.again;
      _heard = attempt.isHeard ? attempt.transcript.trim() : '';
      _verdict = verdict;
    });
  }

  /// The term whole plus its individual words, deduplicated, no empties — the word form's own
  /// contextualStrings shape (see `SessionExerciseCard`).
  List<String> _contextualStrings(String term) {
    final strings = <String>{};
    void add(String text) {
      final trimmed = text.trim();
      if (trimmed.isNotEmpty) strings.add(trimmed);
    }

    add(term);
    for (final word in term.trim().split(RegExp(r'\s+'))) {
      add(word);
    }

    return strings.take(50).toList();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final card = widget.card;
    final example = widget.showExample ? card.example : null;
    final variants = card.acceptedVariants;
    final showReading = ref.watch(transliterationEnabledProvider);
    final photoUrl = widget.photoResolved ? (widget.photoUrl ?? '') : '';

    // ЗНАКОМСТВО ВО ВЕСЬ ЭКРАН (кадр 16a по правке шага 10): фото на всю ширину 220, слово
    // Literata 46 с воспроизведением 44, перевод, чтение, пример курсивом с подчёркнутым словом;
    // «Понятно» — на доке экрана. Карточка в карточке съедала треть экрана и делала фото
    // маленьким. Без фото — 16b: тот же макет без фото-полосы, только типографика.
    InlineSpan? exampleSpan;
    if (example != null && example.isNotEmpty) {
      final needle = termSearchForm(card.answerText);
      final at = spanPositionIn(example, needle);
      exampleSpan = underlinedExample(example, at: at, length: needle.length);
    }
    final reading = [
      if ((card.transcription ?? '').isNotEmpty) '/${card.transcription}/',
      // ЧТЕНИЕ в квадратных скобках, под тем же переключателем «Подсказка произношения»: читатель,
      // выключивший её в словаре, не просил включить её в тренировке.
      if (showReading && (card.transliteration ?? '').isNotEmpty) '[${card.transliteration}]',
    ].join('  ');

    return IntroLayout(
      photo: photoUrl.isEmpty ? null : CachedNetworkImage(photoUrl),
      badge: _IntroBadge(label: l.sessionIntroBadge),
      term: card.answerText,
      translation: card.prompt ?? '',
      reading: reading.isEmpty ? null : reading,
      example: exampleSpan,
      also: variants.isEmpty ? null : '${l.sessionIntroAlso} ${variants.join(' · ')}',
      onSpeak: () => widget.onSpeak(card.answerText),
      // ЭХО — ТА ЖЕ КНОПКА, ЧТО НА ДВУХ ДРУГИХ КАРТОЧКАХ ГОВОРЕНИЯ (наряд SPEECH-2, Ч.1.3).
      // Карточка по-прежнему НИЧЕГО не требует: она зовёт, а не заставляет, и разрешение
      // спрашивается первым тапом (Ч.1.4).
      below: _EchoRow(state: _echo, heard: _heard, verdict: _verdict, onTap: _echoBack),
    );
  }
}

/// «ПОВТОРИ ВСЛУХ» — та же кнопка микрофона, что на двух других карточках говорения (наряд
/// SPEECH-2, Ч.1.3), плюс одна строка реакции.
///
/// Что здесь ИЗМЕНИЛОСЬ. До наряда это был тихий [QuietButton] с серой строкой и без вердикта: «в
/// карточке знакомства нет ничего, что было бы суждением, и зелёная галочка сделала бы её
/// суждением». Наряд просит вердикт на всех трёх карточках с микрофоном (Ч.3.5, Ч.6), и он здесь
/// есть — трёхступенчатый и с перечнем пропущенных слов, а не «неверно». Что НЕ изменилось: ход
/// по-прежнему не пишет ни ревью, ни показа, ни лестницы; «Понятно →» доступно и без него.
class _EchoRow extends StatefulWidget {
  const _EchoRow({
    required this.state,
    required this.heard,
    required this.verdict,
    required this.onTap,
  });

  final _Echo state;

  /// The transcript to print back, or '' when there is none (idle, or nothing was made out).
  final String heard;

  /// ЧТО ВЫШЛО — «верно» / «почти — не хватило: …» / «не то» (наряд SPEECH-2, Ч.3.5). Null, пока
  /// попытки не было или пока микрофон ничего не расслышал.
  final SpokenVerdict? verdict;

  final VoidCallback onTap;

  @override
  State<_EchoRow> createState() => _EchoRowState();
}

class _EchoRowState extends State<_EchoRow> {
  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final heard = widget.heard.trim();

    // Что строка говорит под кнопкой. Пока пишет — живой частичный текст, если он уже есть: увидеть
    // свои слова на экране это самое ясное «да, тебя слышат».
    final note = switch (widget.state) {
      _Echo.idle => null,
      _Echo.listening => heard.isEmpty ? l.sessionSpeakListening : l.sessionSpeakHeard(heard),
      // Печатаем РАСПОЗНАННОЕ: смысл эха во рту, и как оно вышло, человек понимает, читая, что
      // получилось. Вердикт стоит отдельной строкой ниже.
      _Echo.heard => heard.isEmpty ? l.sessionEchoHeard : l.sessionSpeakHeard(heard),
      _Echo.again => l.sessionEchoAgain,
    };

    final verdict = widget.verdict;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Center(
          child: MicButton(
            state: switch (widget.state) {
              _Echo.listening => MicState.recording,
              _ => MicState.yourTurn,
            },
            onTap: widget.onTap,
            caption: switch (widget.state) {
              _Echo.listening => l.sessionSpeakRecording,
              _ => l.sessionEchoTry,
            },
          ),
        ),
        if (note != null) ...[
          const SizedBox(height: AppSpacing.s8),
          Text(note, textAlign: TextAlign.center, style: AppTextExercise.taskInstruction),
        ],
        if (verdict != null) ...[
          const SizedBox(height: AppSpacing.s4),
          Text(
            switch (verdict.credit) {
              SpokenCredit.correct => l.sessionSpeakVerdictCorrect,
              SpokenCredit.almost => l.sessionSpeakVerdictAlmost(verdict.missing.join(', ')),
              SpokenCredit.wrong => l.sessionSpeakVerdictWrong,
            },
            textAlign: TextAlign.center,
            style: AppTextExercise.taskInstruction.copyWith(
              color: switch (verdict.credit) {
                SpokenCredit.correct => AppColors.verdictKnown,
                SpokenCredit.almost => AppColors.verdictUnsure,
                SpokenCredit.wrong => AppColors.destructiveText,
              },
            ),
          ),
        ],
      ],
    );
  }
}

/// The «новое слово» badge — an OUTLINE. Nothing on this card is a verdict, so nothing on it is
/// filled or coloured; the badge marks a kind of card, not an outcome.
class _IntroBadge extends StatelessWidget {
  const _IntroBadge({required this.label});
  final String label;

  @override
  Widget build(BuildContext context) {
    return Align(
      alignment: Alignment.centerLeft,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(AppRadii.chip),
          border: Border.all(color: AppColors.hairline),
        ),
        child: Text(label.toUpperCase(), style: AppText.badge),
      ),
    );
  }
}
