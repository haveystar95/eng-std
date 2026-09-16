import 'dart:async';

import 'package:flutter/material.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../../data/plan/session/session_models.dart';
import '../../../../data/plan/session/session_outcomes.dart';
import '../../../../data/plan/session/session_rules.dart';
import '../parts/session_bits.dart';
import '../parts/session_choice.dart';
import '../parts/session_tiles.dart';
import '../session_texts.dart';
import 'card_kit.dart';

/// СЛОВА — серия 31 канвы: по виджету на вид.

/// Звук образца «Повтори» — 0.85× (серверный голос всегда обычного темпа, DECISIONS п. 318).
const double kRepeatRate = 0.85;

/// Пауза перед автозвуком — после смены карточки, чтобы канал звука не стоил кадра перехода.
const Duration kAutoplayDelay = Duration(milliseconds: 280);

void _autoplay(State state, CardEnv env, CardAudio? audio, String fallback, Object key, {double rate = 1.0}) {
  Timer(kAutoplayDelay, () {
    if (state.mounted) unawaited(env.voice.play(audio, fallback: fallback, rate: rate, key: key));
  });
}

// ── 31-1 ──────────────────────────────────────────────────────────────────────────────────────────

/// ЗНАКОМСТВО СО СЛОВОМ (31-1): фото, слово, чтение, перевод, определение; «В разговоре» — реплика дня со
/// словом, подчёркнутым латунью, и «прослушать». Слово звучит само при появлении. «Понятно» → `passed`.
class WordIntroCard extends StatefulWidget {
  const WordIntroCard({super.key, required this.env, required this.payload});

  final CardEnv env;
  final WordIntroPayload payload;

  @override
  State<WordIntroCard> createState() => _WordIntroCardState();
}

class _WordIntroCardState extends State<WordIntroCard> {
  static const _termKey = 'intro-term';
  static const _lineKey = 'intro-line';

  @override
  void initState() {
    super.initState();
    final p = widget.payload;
    _autoplay(this, widget.env, p.termAudio, p.term.textTarget, _termKey);
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final env = widget.env;
    final p = widget.payload;
    final term = p.term;
    final usedIn = p.usedIn;
    return CardLayout(
      taskGap: 20,
      bodyGap: 16,
      fadeStop: 0.34,
      task: SessionTask(l.planSessionTaskRememberWord),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SessionSheet(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (term.image != null) ...[SessionPhoto(image: term.image, height: 150), const SizedBox(height: 20)],
                Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(term.textTarget, style: AppTextSession.term),
                          if (term.pronunciationNative != null) ...[
                            const SizedBox(height: 4),
                            Text(term.pronunciationNative!, style: AppTextSession.meta),
                          ],
                          const SizedBox(height: 4),
                          Text(term.textNative, style: AppTextSession.body),
                        ],
                      ),
                    ),
                    const SizedBox(width: 12),
                    CardListen(env: env, audio: p.termAudio, fallback: term.textTarget, playKey: _termKey),
                  ],
                ),
                if (term.definitionTarget != null) ...[
                  const SizedBox(height: 14),
                  Text(term.definitionTarget!, style: AppTextSession.text15),
                ],
              ],
            ),
          ),
          if (usedIn != null) ...[
            const SizedBox(height: 16),
            SessionEyebrow(l.planWindowSheetTalk),
            const SizedBox(height: 14),
            SessionSheet(
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        _UnderlinedLine(text: usedIn.textTarget, span: usedIn.termSpan),
                        const SizedBox(height: 4),
                        Text(usedIn.textNative, style: AppTextSession.body),
                      ],
                    ),
                  ),
                  const SizedBox(width: 12),
                  CardListen(env: env, audio: p.lineAudio, fallback: usedIn.textTarget, playKey: _lineKey),
                ],
              ),
            ),
          ],
        ],
      ),
      bottom: SessionDockButton(
        label: l.planSessionUnderstood,
        busy: env.advancing,
        onTap: () {
          env.submit(const SessionAnswer(result: SessionResult.passed, attempts: 1));
          unawaited(env.next());
        },
      ),
    );
  }
}

/// Реплика «В разговоре» — Literata 22, слово подчёркнуто латунью 2 px по `term_span`.
class _UnderlinedLine extends StatelessWidget {
  const _UnderlinedLine({required this.text, required this.span});

  final String text;
  final CardSpan? span;

  @override
  Widget build(BuildContext context) {
    final s = span;
    const style = AppTextSession.target22;
    if (s == null || s.start < 0 || s.end > text.length || s.start >= s.end) return Text(text, style: style);
    return Text.rich(
      TextSpan(
        children: [
          TextSpan(text: text.substring(0, s.start), style: style),
          TextSpan(
            text: text.substring(s.start, s.end),
            style: style.copyWith(
              decoration: TextDecoration.underline,
              decorationColor: AppColors.brassInk,
              decorationThickness: 2,
            ),
          ),
          TextSpan(text: text.substring(s.end), style: style),
        ],
      ),
    );
  }
}

// ── 31-2 ──────────────────────────────────────────────────────────────────────────────────────────

/// ПОВТОРИ СЛОВО (31-2): образец звучит при открытии на 0.85×, повтор — «прослушать»; микрофон; зачёт —
/// покрытие `expected_text` по `coverage_min`; две попытки без зачёта — `skipped`. Услышано — эхо под листом.
class WordRepeatCard extends StatefulWidget {
  const WordRepeatCard({super.key, required this.env, required this.payload});

  final CardEnv env;
  final WordRepeatPayload payload;

  @override
  State<WordRepeatCard> createState() => _WordRepeatCardState();
}

class _WordRepeatCardState extends State<WordRepeatCard> with VoiceCardState<WordRepeatCard> {
  static const _key = 'repeat-term';

  @override
  CardEnv get env => widget.env;

  @override
  String get expectedSpeech => widget.payload.expectedText;

  @override
  bool accepts(String heard) => SessionRules.voiceAccepted(widget.payload, heard, env.articles);

  @override
  void initState() {
    super.initState();
    initVoice();
    final p = widget.payload;
    _autoplay(this, env, p.termAudio, p.term.textTarget, _key, rate: kRepeatRate);
  }

  @override
  void dispose() {
    disposeVoice();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final noMic = noMicBody((s) => SessionTexts.stage(AppLocalizations.of(context), s));
    if (noMic != null) return noMic;
    final l = AppLocalizations.of(context);
    final p = widget.payload;
    final term = p.term;
    final heardOk = done && !skippedAfterMisses;
    return CardLayout(
      taskGap: 20,
      centerBody: true,
      fadeStop: 0.30,
      task: SessionTask(
        l.planSessionTaskSayWord,
        companion: env.role.isEmpty ? null : l.planSessionCompanionSayWord(SessionTexts.roleInline(env.role)),
      ),
      body: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SessionSheet(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (term.image != null) ...[SessionPhoto(image: term.image, height: 170), const SizedBox(height: 20)],
                Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(term.textTarget, style: AppTextSession.term),
                          if (term.pronunciationNative != null) ...[
                            const SizedBox(height: 4),
                            Text(term.pronunciationNative!, style: AppTextSession.meta),
                          ],
                          const SizedBox(height: 4),
                          Text(term.textNative, style: AppTextSession.body),
                        ],
                      ),
                    ),
                    const SizedBox(width: 12),
                    CardListen(env: env, audio: p.termAudio, fallback: term.textTarget, playKey: _key, rate: kRepeatRate),
                  ],
                ),
              ],
            ),
          ),
          if (heardOk) ...[
            const SizedBox(height: 20),
            SessionSheet(
              key: const ValueKey('word-repeat-echo'),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      const SessionCheckBadge(),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Text(term.textTarget, style: AppTextSession.target22.copyWith(color: AppColors.verdictKnown)),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Text(l.planSessionEcho, style: AppTextSession.meta),
                ],
              ),
            ),
          ],
        ],
      ),
      bottom: voiceDock(context, showHeardLine: false),
    );
  }
}

// ── 31-3 / 31-4 ───────────────────────────────────────────────────────────────────────────────────

/// ВЫБОР ИЗ ЧЕТЫРЁХ (шаблон 30-9): `term_to_native` (31-3) — фото, слово и звук у вопроса, варианты на
/// родном; `native_to_term` (31-4) — фото и перевод без звука, варианты — слова цели со своим звуком.
class WordChooseCard extends StatefulWidget {
  const WordChooseCard({super.key, required this.env, required this.payload});

  final CardEnv env;
  final WordChoosePayload payload;

  @override
  State<WordChooseCard> createState() => _WordChooseCardState();
}

class _WordChooseCardState extends State<WordChooseCard> with ChoiceCardState<WordChooseCard> {
  @override
  CardEnv get env => widget.env;

  @override
  ChoicePayload get choice => widget.payload;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final p = widget.payload;
    final forward = p.termToNative;
    final text = (forward ? p.promptTextTarget : p.promptTextNative) ?? '';
    return CardLayout(
      bodyGap: 4,
      taskInBody: p.promptImage == null,
      task: SessionTask(forward ? l.planSessionTaskChooseTranslation : l.planSessionTaskChooseWord),
      body: SessionQuestionSheet(
        media: p.promptImage == null ? null : SessionPhoto(image: p.promptImage, radius: 0),
        mediaHeight: answeredWrong ? 160 : 208,
        eyebrow: forward ? l.planSessionBrowWord : l.planSessionBrowTranslation,
        eyebrowTrailing: eyebrowTrailing(l),
        text: Text(text, style: AppTextSession.question),
        listen: forward ? CardListen(env: env, audio: p.promptAudio, fallback: text, playKey: 'choose-prompt') : null,
      ),
      bottom: optionsDock(
        context,
        target: !forward,
        listen: forward
            ? null
            : (o) => CardListen(env: env, audio: o.audio, fallback: o.text, playKey: 'option-${o.id}', size: 28),
      ),
    );
  }
}

// ── 31-5 ──────────────────────────────────────────────────────────────────────────────────────────

/// НА СЛУХ (31-5): волна вместо фото, звук только у вопроса (играет при открытии), четыре слова цели молчат.
class WordListenCard extends StatefulWidget {
  const WordListenCard({super.key, required this.env, required this.payload});

  final CardEnv env;
  final WordListenPayload payload;

  @override
  State<WordListenCard> createState() => _WordListenCardState();
}

class _WordListenCardState extends State<WordListenCard> with ChoiceCardState<WordListenCard> {
  static const _key = 'listen-audio';

  @override
  CardEnv get env => widget.env;

  @override
  ChoicePayload get choice => widget.payload;

  /// Без файла телефон читает верное слово — другого текста у звука нет.
  String get _fallback => widget.payload.correctOption?.text ?? '';

  @override
  void initState() {
    super.initState();
    _autoplay(this, env, widget.payload.audio, _fallback, _key);
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final p = widget.payload;
    return CardLayout(
      bodyGap: 4,
      task: SessionTask(l.planSessionTaskChooseHeard),
      body: SessionQuestionSheet(
        media: ValueListenableBuilder<Object?>(
          valueListenable: env.voice.playing,
          builder: (_, playing, _) => SessionWavePlate(
            playing: playing == _key,
            label: l.planWindowListen,
            onTap: () => unawaited(env.voice.play(p.audio, fallback: _fallback, key: _key)),
          ),
        ),
        mediaHeight: answeredWrong ? 160 : 208,
        eyebrow: l.planSessionBrowByEar,
        eyebrowTrailing: eyebrowTrailing(l),
        text: Text(l.planSessionWhatHeard, style: AppTextSession.question),
        listen: CardListen(env: env, audio: p.audio, fallback: _fallback, playKey: _key),
      ),
      bottom: optionsDock(context, target: true),
    );
  }
}

// ── 31-6 ──────────────────────────────────────────────────────────────────────────────────────────

/// СОБЕРИ ИЗ ПЛИТОК (31-6): фото и перевод держат смысл, плитки 30-5; «Проверить»; зачёт — собранное =
/// `expected` по порядку. Неверно — покачивание, контур у места ошибки, верная плитка подчёркнута латунью.
class WordAssembleCard extends StatefulWidget {
  const WordAssembleCard({super.key, required this.env, required this.payload});

  final CardEnv env;
  final WordAssemblePayload payload;

  @override
  State<WordAssembleCard> createState() => _WordAssembleCardState();
}

class _WordAssembleCardState extends State<WordAssembleCard> {
  /// Места плиток лотка в порядке, в котором их положили.
  final List<int> _placed = [];
  bool? _correct;
  int _shake = 0;

  WordAssemblePayload get p => widget.payload;

  List<String> get _placedText => [for (final i in _placed) p.tiles[i]];

  int get _mismatch {
    final placed = _placedText;
    for (var i = 0; i < placed.length; i++) {
      if (i >= p.expected.length || placed[i] != p.expected[i]) return i;
    }
    return placed.length < p.expected.length ? placed.length : -1;
  }

  void _check() {
    final ok = SessionRules.wordAssembled(_placedText, p.expected);
    setState(() {
      _correct = ok;
      if (!ok) _shake++;
    });
    if (ok) {
      AppHaptics.success();
    } else {
      AppHaptics.warning();
    }
    widget.env.submit(SessionAnswer(
      result: ok ? SessionResult.passed : SessionResult.failed,
      attempts: 1,
      response: const SessionResponse(mode: 'tiles'),
    ));
    if (ok) {
      unawaited(Future<void>.delayed(AppMotion.sessionAutoAdvance, () {
        if (mounted) unawaited(widget.env.next());
      }));
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final env = widget.env;
    final answered = _correct != null;
    final wrongAt = _correct == false ? _mismatch : -1;
    // Верная плитка на месте ошибки — первая неиспользованная с этим текстом.
    int? hintTray;
    if (wrongAt >= 0 && wrongAt < p.expected.length) {
      final want = p.expected[wrongAt];
      for (var i = 0; i < p.tiles.length; i++) {
        if (p.tiles[i] == want && !_placed.contains(i)) {
          hintTray = i;
          break;
        }
      }
    }
    return CardLayout(
      taskGap: 20,
      bodyGap: 16,
      fadeStop: 0.34,
      task: SessionTask(l.planSessionTaskAssembleParts),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SessionSheet(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (p.term.image != null) ...[SessionPhoto(image: p.term.image, height: 226), const SizedBox(height: 16)],
                Row(
                  crossAxisAlignment: CrossAxisAlignment.baseline,
                  textBaseline: TextBaseline.alphabetic,
                  children: [
                    Expanded(child: Text(p.term.textNative, style: AppTextSession.text15)),
                    const SizedBox(width: 8),
                    Text(l.planSessionByParts, style: AppTextSession.meta),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(height: 24),
          SessionAssembly(
            trayGap: 20,
            row: [
              for (var k = 0; k < _placed.length; k++) RowPiece.word(p.tiles[_placed[k]], wrong: k == wrongAt),
            ],
            tray: [
              for (var i = 0; i < p.tiles.length; i++) TrayPiece(p.tiles[i], used: _placed.contains(i), hint: i == hintTray),
            ],
            caret: !answered,
            sage: _correct == true,
            shake: _shake,
            onTray: answered ? null : (i) => setState(() => _placed.add(i)),
            onRow: answered ? null : (k) => setState(() => _placed.removeAt(k)),
          ),
        ],
      ),
      bottom: _correct == false
          ? SessionDockButton(label: l.planSessionNext, busy: env.advancing, onTap: () => unawaited(env.next()))
          : SessionDockButton(label: l.planSessionCheck, enabled: _placed.isNotEmpty && !answered, onTap: _check),
    );
  }
}

// ── 31-7 ──────────────────────────────────────────────────────────────────────────────────────────

/// СЛОВО В ОКНЕ (31-7): реплика с окном латунью на месте слова, полный перевод `text_native` со словом,
/// четыре термина со своим звуком. У карточки нет фото (payload его не несёт) — верх листа текстом.
class WordInLineCard extends StatefulWidget {
  const WordInLineCard({super.key, required this.env, required this.payload});

  final CardEnv env;
  final WordInLinePayload payload;

  @override
  State<WordInLineCard> createState() => _WordInLineCardState();
}

class _WordInLineCardState extends State<WordInLineCard> with ChoiceCardState<WordInLineCard> {
  @override
  CardEnv get env => widget.env;

  @override
  ChoicePayload get choice => widget.payload;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final p = widget.payload;
    final parts = splitAtSlot(p.line.textTarget);
    final correct = p.correctOption?.text;
    return CardLayout(
      bodyGap: 4,
      taskInBody: true,
      task: SessionTask(l.planSessionTaskInsertWord),
      body: SessionQuestionSheet(
        eyebrow: l.planSessionBrowSlot,
        eyebrowTrailing: eyebrowTrailing(l),
        text: SessionFrameText(
          before: parts.before,
          after: parts.after,
          style: AppTextSession.question,
          window: p.line.textTarget.contains(kSlotMark),
          slot: answered ? correct : null,
          look: answered ? SlotLook.sage : SlotLook.empty,
          emptyWindow: const Size(56, 28),
        ),
        listen: CardListen(
          env: env,
          audio: p.line.audio,
          fallback: correct == null ? p.line.textTarget : p.line.textTarget.replaceFirst(kSlotMark, correct),
          playKey: 'in-line',
        ),
        // Полный перевод со словом, а не `text_native_gapped`: окно реплики принимает несколько слов дня, и
        // только перевод делает ответ единственным (уточнение владельца к 31-7; канва здесь ошибается).
        translation: p.line.textNative,
      ),
      bottom: optionsDock(
        context,
        target: true,
        listen: (o) => CardListen(env: env, audio: o.audio, fallback: o.text, playKey: 'option-${o.id}', size: 28),
      ),
    );
  }
}
