import 'dart:async';

import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../../data/plan/session/session_models.dart';
import '../../../../data/plan/session/session_outcomes.dart';
import '../../../../data/plan/session/session_rules.dart';
import '../../../../data/plan/session/speech_coverage.dart';
import '../../../../data/speech/speech_turn.dart';
import '../parts/session_bits.dart';
import '../parts/session_choice.dart';
import '../parts/session_mic_panel.dart';
import '../parts/session_tiles.dart';
import '../session_mic.dart';
import '../session_texts.dart';
import 'card_kit.dart';
import 'word_cards.dart' show kAutoplayDelay, kRepeatRate;

/// ФРАЗЫ — серия 32 канвы: каркас с окном, по виджету на вид.

/// Чтение каркаса с наполнением: `___` в чтении заменено чтением наполнения.
String? _pronunciation(CardFrame frame, CardFiller? filler) {
  final base = frame.framePronunciationNative;
  if (base == null) return null;
  final fp = filler?.pronunciationNative;
  return fp == null ? base : base.replaceFirst(kSlotMark, fp);
}

/// Перевод каркаса с наполнением — фраза на родном (`native_line`), иначе каркас на родном.
String _native(CardFrame frame, CardFiller? filler) {
  if (filler == null) return frame.frameNative;
  return filler.nativeLine ?? frame.frameNative.replaceFirst(kSlotMark, filler.native);
}

void _autoplay(State state, CardEnv env, CardAudio? audio, String fallback, Object key, {double rate = 1.0}) {
  Timer(kAutoplayDelay, () {
    if (state.mounted) unawaited(env.voice.play(audio, fallback: fallback, rate: rate, key: key));
  });
}

/// ПЛАШКА ФРАЗЫ (32-1, 32-3…32-9) — `#EFEBE3` во всё поле листа, фраза Literata 30 по левому краю,
/// «прослушать» 44 в правом нижнем углу.
class _PhrasePlate extends StatelessWidget {
  const _PhrasePlate({required this.child, this.listen, this.height = 208, this.topLeft});

  final Widget child;
  final Widget? listen;
  final double height;
  final Widget? topLeft;

  /// Высота плашки не меньше [height]; длинная фраза её растит — текст не режется.
  @override
  Widget build(BuildContext context) => ColoredBox(
    color: AppColors.ground,
    child: Stack(
      children: [
        Container(
          constraints: BoxConstraints(minHeight: height),
          padding: EdgeInsets.fromLTRB(20, topLeft == null ? 20 : 52, 20, listen == null ? 20 : 64),
          alignment: Alignment.centerLeft,
          child: child,
        ),
        if (topLeft != null) Positioned(left: 20, top: 20, child: topLeft!),
        if (listen != null) Positioned(right: 16, bottom: 16, child: listen!),
      ],
    ),
  );
}

/// Текстовая часть листа фразы — бровь, чтение, перевод.
class _PhraseFooter extends StatelessWidget {
  const _PhraseFooter({required this.eyebrow, this.reading, this.native, this.nativeStyle});

  final String eyebrow;
  final String? reading;
  final String? native;
  final TextStyle? nativeStyle;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.all(20),
    child: Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        SessionEyebrow(eyebrow),
        if (reading != null) ...[const SizedBox(height: 4), Text(reading!, style: AppTextSession.meta)],
        if (native != null) ...[const SizedBox(height: 4), Text(native!, style: nativeStyle ?? AppTextSession.body)],
      ],
    ),
  );
}

/// Лист фразы: плашка сверху и текстовая часть.
class _PhraseSheet extends StatelessWidget {
  const _PhraseSheet({required this.plate, required this.footer});

  final Widget plate;
  final Widget footer;

  @override
  Widget build(BuildContext context) => SessionSheet(
    padding: EdgeInsets.zero,
    child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [plate, footer]),
  );
}

/// Ряд чипов наполнений 40 (выбранный — чернила).
class _FillerChips extends StatelessWidget {
  const _FillerChips({required this.fillers, required this.selected, required this.onTap, this.extra});

  final List<CardFiller> fillers;
  final int? selected;
  final ValueChanged<CardFiller>? onTap;
  final Widget? extra;

  @override
  Widget build(BuildContext context) => Wrap(
    spacing: 8,
    runSpacing: 8,
    children: [
      for (final f in fillers)
        SessionTile(
          key: ValueKey('chip-${f.index}'),
          text: f.target,
          height: 40,
          selected: selected == f.index,
          onTap: onTap == null ? null : () => onTap!(f),
        ),
      ?extra,
    ],
  );
}

// ── 32-1 ──────────────────────────────────────────────────────────────────────────────────────────

/// ЗНАКОМСТВО С КАРКАСОМ (32-1): каркас с окном, три чипа-наполнения, чтение и перевод; тап по чипу
/// подставляет наполнение в окно и играет его звук. «Понятно» → `passed`.
class PhraseIntroCard extends StatefulWidget {
  const PhraseIntroCard({super.key, required this.env, required this.payload});

  final CardEnv env;
  final PhraseIntroPayload payload;

  @override
  State<PhraseIntroCard> createState() => _PhraseIntroCardState();
}

class _PhraseIntroCardState extends State<PhraseIntroCard> {
  CardFiller? _filler;

  void _pick(CardFiller f) {
    final frame = widget.payload.frame;
    setState(() => _filler = f);
    unawaited(widget.env.voice.play(f.audio, fallback: frame.filledWith(f.target), key: 'chip-${f.index}'));
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final env = widget.env;
    final p = widget.payload;
    final frame = p.frame;
    final f = _filler;
    final listenAudio = f?.audio ?? p.said.audio;
    final listenText = f == null ? p.said.textTarget : frame.filledWith(f.target);
    return CardLayout(
      bodyGap: 12,
      fadeStop: 0.34,
      task: SessionTask(l.planSessionTaskRememberPhrase),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _PhraseSheet(
            plate: _PhrasePlate(
              height: 288,
              listen: CardListen(env: env, audio: listenAudio, fallback: listenText, playKey: 'intro-phrase'),
              child: frame.hasSlot
                  ? SessionFrameText.frame(frame, style: AppTextSession.frame, slot: f?.target, look: f == null ? SlotLook.empty : SlotLook.filled)
                  : SessionFrameText.plain(frame.frameTarget, style: AppTextSession.frame),
            ),
            footer: _PhraseFooter(
              eyebrow: l.planSessionBrowFrame,
              reading: _pronunciation(frame, f),
              native: _native(frame, f),
            ),
          ),
          if (frame.hasSlot) ...[
            const SizedBox(height: 24),
            _FillerChips(fillers: frame.fillers, selected: f?.index, onTap: _pick),
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

// ── 32-2 ──────────────────────────────────────────────────────────────────────────────────────────

/// ПЕРЕВОД → СБОРКА (32-2): плитки каркаса и чипы наполнений в одном лотке; окно в строке — пустой чип до
/// выбора наполнения; «Проверить»; зачёт — слова = `expected.words`, окно на `slot_at`, наполнение =
/// `expected.filler_index`. Плитки строчные — первое слово собранной строки клиент пишет с заглавной.
class PhraseAssembleCard extends StatefulWidget {
  const PhraseAssembleCard({super.key, required this.env, required this.payload});

  final CardEnv env;
  final PhraseAssemblePayload payload;

  @override
  State<PhraseAssembleCard> createState() => _PhraseAssembleCardState();
}

class _PhraseAssembleCardState extends State<PhraseAssembleCard> {
  final List<AssemblyPiece> _pieces = [];
  bool? _correct;
  int _shake = 0;

  PhraseAssemblePayload get p => widget.payload;

  SlotPiece? get _slot {
    for (final x in _pieces) {
      if (x is SlotPiece) return x;
    }
    return null;
  }

  void _tapTray(int i) {
    final tiles = p.tiles.length;
    setState(() {
      if (i < tiles) {
        _pieces.add(TilePiece(i, p.tiles[i]));
        return;
      }
      final filler = p.chips[i - tiles];
      final at = _pieces.indexWhere((x) => x is SlotPiece);
      if (at >= 0) {
        _pieces[at] = SlotPiece(filler);
      } else {
        _pieces.add(SlotPiece(filler));
      }
    });
    if (i >= tiles) {
      final f = p.chips[i - tiles];
      unawaited(widget.env.voice.play(f.audio, fallback: p.frame.filledWith(f.target), key: 'chip-${f.index}'));
    }
  }

  void _check() {
    final ok = SessionRules.phraseAssembled(p, _pieces);
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
      response: SessionResponse(mode: 'tiles', fillerIndex: _slot?.filler.index),
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
    final wrongAt = _correct == false ? SessionRules.phraseMismatch(p, _pieces) : -1;
    final expectedAt = wrongAt >= 0 ? SessionRules.phraseExpectedAt(p, wrongAt) : null;
    final full = _pieces.length >= p.expectedWords.length + 1;
    final tail = p.frame.parts.after.trim();
    final row = <RowPiece>[
      for (var k = 0; k < _pieces.length; k++)
        switch (_pieces[k]) {
          TilePiece(:final text) => RowPiece.word(k == 0 ? SessionRules.capitalized(text) : text, wrong: k == wrongAt),
          SlotPiece(:final filler) => RowPiece.slot(filler.target, wrong: k == wrongAt),
        },
      if (full && tail.isNotEmpty && !tail.contains(' ')) RowPiece.tail(tail),
    ];
    final usedTiles = {for (final x in _pieces) if (x is TilePiece) x.index};
    final slotFiller = _slot?.filler.index;
    int? hint;
    if (expectedAt != null) {
      if (expectedAt.word != null) {
        for (var i = 0; i < p.tiles.length; i++) {
          if (p.tiles[i] == expectedAt.word && !usedTiles.contains(i)) {
            hint = i;
            break;
          }
        }
      } else if (expectedAt.fillerIndex != null) {
        final c = p.chips.indexWhere((f) => f.index == expectedAt.fillerIndex);
        if (c >= 0) hint = p.tiles.length + c;
      }
    }
    return CardLayout(
      bodyGap: 12,
      fadeStop: 0.34,
      centerBody: true,
      task: SessionTask(l.planSessionTaskAssemblePhrase),
      body: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SessionQuestionSheet(
            eyebrow: l.planSessionBrowTranslation,
            text: Text(p.targetNative, style: AppTextSession.question),
            translation: null,
          ),
          const SizedBox(height: 24),
          SessionAssembly(
            trayGap: 20,
            row: row,
            tray: [
              for (var i = 0; i < p.tiles.length; i++)
                TrayPiece(p.tiles[i], used: usedTiles.contains(i), hint: hint == i),
              for (var c = 0; c < p.chips.length; c++)
                TrayPiece(p.chips[c].target, used: slotFiller == p.chips[c].index, hint: hint == p.tiles.length + c),
            ],
            emptySlot: _slot == null && p.frame.hasSlot && !answered,
            caret: !answered && !full,
            sage: _correct == true,
            shake: _shake,
            onTray: answered ? null : _tapTray,
            onRow: answered ? null : (k) {
              if (k < _pieces.length) setState(() => _pieces.removeAt(k));
            },
          ),
        ],
      ),
      bottom: _correct == false
          ? SessionDockButton(label: l.planSessionNext, busy: env.advancing, onTap: () => unawaited(env.next()))
          : SessionDockButton(
              label: l.planSessionCheck,
              enabled: !answered && _pieces.isNotEmpty && (_slot != null || !p.frame.hasSlot),
              onTap: _check,
            ),
    );
  }
}

// ── 32-3 ──────────────────────────────────────────────────────────────────────────────────────────

/// ОБРАТНЫЙ ПЕРЕВОД (32-3, шаблон 30-9): фраза на цели со звуком и чтением — четыре варианта на родном.
class PhraseChooseBackCard extends StatefulWidget {
  const PhraseChooseBackCard({super.key, required this.env, required this.payload});

  final CardEnv env;
  final PhraseChooseBackPayload payload;

  @override
  State<PhraseChooseBackCard> createState() => _PhraseChooseBackCardState();
}

class _PhraseChooseBackCardState extends State<PhraseChooseBackCard> with ChoiceCardState<PhraseChooseBackCard> {
  @override
  CardEnv get env => widget.env;

  @override
  ChoicePayload get choice => widget.payload;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final p = widget.payload;
    return CardLayout(
      bodyGap: 12,
      centerBody: true,
      task: SessionTask(l.planSessionTaskChooseTranslation),
      body: _PhraseSheet(
        plate: _PhrasePlate(
          listen: CardListen(env: env, audio: p.audio, fallback: p.textTarget, playKey: 'choose-back'),
          child: SessionFrameText.plain(p.textTarget, style: AppTextSession.frame),
        ),
        footer: _PhraseFooter(eyebrow: l.planSessionBrowPhrase, reading: p.pronunciationNative),
      ),
      bottom: optionsDock(context),
    );
  }
}

// ── 32-4 ──────────────────────────────────────────────────────────────────────────────────────────

/// ОКНО · ВСТАВЬ НАПОЛНЕНИЕ (32-4): каркас с пустым окном и предложение на родном; четыре наполнения
/// Literata 22 со своим «прослушать». Верно — окно шалфеем.
class PhraseSlotCard extends StatefulWidget {
  const PhraseSlotCard({super.key, required this.env, required this.payload});

  final CardEnv env;
  final PhraseSlotPayload payload;

  @override
  State<PhraseSlotCard> createState() => _PhraseSlotCardState();
}

class _PhraseSlotCardState extends State<PhraseSlotCard> with ChoiceCardState<PhraseSlotCard> {
  @override
  CardEnv get env => widget.env;

  @override
  ChoicePayload get choice => widget.payload;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final p = widget.payload;
    return CardLayout(
      bodyGap: 12,
      centerBody: true,
      task: SessionTask(l.planSessionTaskInsert),
      body: _PhraseSheet(
        plate: _PhrasePlate(
          child: SessionFrameText.frame(
            p.frame,
            style: AppTextSession.frame,
            slot: answered ? p.correctOption?.text : null,
            look: answered ? SlotLook.sage : SlotLook.empty,
          ),
        ),
        footer: _PhraseFooter(eyebrow: l.planSessionBrowTranslation, native: p.promptNative),
      ),
      bottom: optionsDock(
        context,
        target: true,
        listen: (o) => CardListen(env: env, audio: o.audio, fallback: p.frame.filledWith(o.text), playKey: 'option-${o.id}', size: 28),
      ),
    );
  }
}

// ── 32-5 ──────────────────────────────────────────────────────────────────────────────────────────

/// ОКНО НА СЛУХ (32-5): волна вместо фразы, звучит одно наполнение (при открытии и по тапу по волне),
/// каркас с пустым окном текстом; варианты молчат.
class PhraseSlotListenCard extends StatefulWidget {
  const PhraseSlotListenCard({super.key, required this.env, required this.payload});

  final CardEnv env;
  final PhraseSlotListenPayload payload;

  @override
  State<PhraseSlotListenCard> createState() => _PhraseSlotListenCardState();
}

class _PhraseSlotListenCardState extends State<PhraseSlotListenCard> with ChoiceCardState<PhraseSlotListenCard> {
  static const _key = 'slot-listen';

  @override
  CardEnv get env => widget.env;

  @override
  ChoicePayload get choice => widget.payload;

  String get _fallback {
    final p = widget.payload;
    final f = p.frame.filler(p.fillerIndex);
    return p.frame.filledWith(f?.target ?? p.correctOption?.text ?? '');
  }

  @override
  void initState() {
    super.initState();
    _autoplay(this, env, widget.payload.audio, _fallback, _key);
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final p = widget.payload;
    final said = p.frame.filler(p.fillerIndex);
    return CardLayout(
      bodyGap: 4,
      task: SessionTask(l.planSessionTaskChooseHeard),
      body: SessionQuestionSheet(
        media: ValueListenableBuilder<Object?>(
          valueListenable: env.voice.playing,
          builder: (_, playing, _) => SessionWavePlate(
            playing: playing == _key,
            heights: SessionWave.five,
            label: l.planWindowListen,
            onTap: () => unawaited(env.voice.play(p.audio, fallback: _fallback, key: _key)),
          ),
        ),
        mediaHeight: answeredWrong ? 160 : 208,
        eyebrow: l.planSessionBrowByEar,
        eyebrowTrailing: eyebrowTrailing(l),
        text: SessionFrameText.frame(
          p.frame,
          style: AppTextSession.question,
          slot: answered ? p.correctOption?.text : null,
          look: answered ? SlotLook.sage : SlotLook.empty,
        ),
        translation: answered ? _native(p.frame, said) : p.frame.frameNative,
      ),
      bottom: optionsDock(context, target: true),
    );
  }
}

// ── 32-6 ──────────────────────────────────────────────────────────────────────────────────────────

/// ПОВТОРИ ВСЛУХ (32-6): образец на 0.85× при открытии, ключ подчёркнут латунью; микрофон; зачёт — покрытие
/// `expected_text` по `coverage_min`; две попытки без зачёта — `skipped`.
class PhraseRepeatCard extends StatefulWidget {
  const PhraseRepeatCard({super.key, required this.env, required this.payload});

  final CardEnv env;
  final PhraseRepeatPayload payload;

  @override
  State<PhraseRepeatCard> createState() => _PhraseRepeatCardState();
}

class _PhraseRepeatCardState extends State<PhraseRepeatCard> with VoiceCardState<PhraseRepeatCard> {
  static const _key = 'phrase-repeat';

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
    _autoplay(this, env, widget.payload.audio, widget.payload.expectedText, _key, rate: kRepeatRate);
  }

  @override
  void dispose() {
    disposeVoice();
    super.dispose();
  }

  /// Ключ в строке — без учёта регистра; не нашёлся — без подчёркивания.
  TextRange? get _keyRange {
    final key = widget.payload.key;
    if (key == null) return null;
    final at = widget.payload.expectedText.toLowerCase().indexOf(key.toLowerCase());
    return at < 0 ? null : TextRange(start: at, end: at + key.length);
  }

  @override
  Widget build(BuildContext context) {
    final noMic = noMicBody((s) => SessionTexts.stage(AppLocalizations.of(context), s));
    if (noMic != null) return noMic;
    final l = AppLocalizations.of(context);
    final p = widget.payload;
    final filler = p.frame.filler(p.fillerIndex);
    return CardLayout(
      bodyGap: 12,
      centerBody: true,
      fadeStop: 0.30,
      task: SessionTask(l.planSessionTaskSayPhrase),
      body: _PhraseSheet(
        plate: _PhrasePlate(
          listen: CardListen(env: env, audio: p.audio, fallback: p.expectedText, playKey: _key, rate: kRepeatRate),
          child: SessionFrameText.plain(p.expectedText, style: AppTextSession.frame, underline: _keyRange),
        ),
        footer: _PhraseFooter(
          eyebrow: l.planSessionBrowPhrase,
          reading: _pronunciation(p.frame, filler),
          native: _native(p.frame, filler),
        ),
      ),
      bottom: voiceDock(context),
    );
  }
}

// ── 32-7 ──────────────────────────────────────────────────────────────────────────────────────────

/// СКАЖИ С ДРУГИМ ОКНОМ (32-7): в окне — задание на родном курсивом, звука у листа нет; зачёт — покрытие
/// каркаса И все слова `slot_expected`; при зачёте каркас и окно подсвечиваются раздельно.
class PhraseOtherSlotCard extends StatefulWidget {
  const PhraseOtherSlotCard({super.key, required this.env, required this.payload});

  final CardEnv env;
  final PhraseOtherSlotPayload payload;

  @override
  State<PhraseOtherSlotCard> createState() => _PhraseOtherSlotCardState();
}

class _PhraseOtherSlotCardState extends State<PhraseOtherSlotCard> with VoiceCardState<PhraseOtherSlotCard> {
  ({bool frame, bool slot})? _parts;

  @override
  CardEnv get env => widget.env;

  @override
  String get expectedSpeech => widget.payload.expectedText;

  @override
  bool accepts(String heard) {
    final parts = SessionRules.otherSlotParts(widget.payload, heard, env.articles);
    setState(() => _parts = parts);
    return parts.frame && parts.slot;
  }

  @override
  void initState() {
    super.initState();
    initVoice();
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
    final asked = p.frame.filler(p.fillerIndex);
    final parts = done && !skippedAfterMisses ? _parts : null;
    return CardLayout(
      bodyGap: 12,
      centerBody: true,
      fadeStop: 0.30,
      task: SessionTask(l.planSessionTaskOtherSlot),
      body: _PhraseSheet(
        plate: _PhrasePlate(
          child: SessionFrameText.frame(
            p.frame,
            style: AppTextSession.frame,
            frameColor: parts?.frame == true ? AppColors.verdictKnown : null,
            slot: parts?.slot == true ? p.slotExpected : p.taskNative,
            look: parts?.slot == true ? SlotLook.sage : SlotLook.task,
          ),
        ),
        footer: _PhraseFooter(eyebrow: l.planSessionBrowSlot, native: _native(p.frame, asked)),
      ),
      bottom: voiceDock(context),
    );
  }
}

// ── 32-8 ──────────────────────────────────────────────────────────────────────────────────────────

/// КОМБИНАЦИЯ (32-8): реплика собеседника звучит, текст открыт; три каркаса — выбор; потом чипы наполнений;
/// «Дальше» неактивна до обоих выборов. Зачёт — каркас = `correct_frame`, наполнение любое. Неверный каркас
/// — реакция шаблона 30-9 (контур у выбранного, шалфей у верного) и «Дальше».
class PhraseCombineCard extends StatefulWidget {
  const PhraseCombineCard({super.key, required this.env, required this.payload});

  final CardEnv env;
  final PhraseCombinePayload payload;

  @override
  State<PhraseCombineCard> createState() => _PhraseCombineCardState();
}

class _PhraseCombineCardState extends State<PhraseCombineCard> {
  static const _partnerKey = 'combine-partner';

  String? _frameRef;
  CardFiller? _filler;
  int _shake = 0;

  PhraseCombinePayload get p => widget.payload;

  @override
  void initState() {
    super.initState();
    final line = p.partnerLine;
    if (line != null) _autoplay(this, widget.env, line.audio, line.textTarget, _partnerKey);
  }

  bool get _frameWrong => _frameRef != null && !SessionRules.combineCorrect(p, _frameRef!);

  CardFrameText? get _correctFrame {
    for (final f in p.frames) {
      if (f.ref == p.correctFrame) return f;
    }
    return null;
  }

  void _pickFrame(CardFrameText frame) {
    if (_frameRef != null) return;
    final ok = SessionRules.combineCorrect(p, frame.ref);
    setState(() {
      _frameRef = frame.ref;
      if (!ok) _shake++;
    });
    if (!ok) {
      AppHaptics.warning();
      widget.env.submit(const SessionAnswer(result: SessionResult.failed, attempts: 1, response: SessionResponse(mode: 'chips')));
    }
  }

  void _pickFiller(CardFiller f) {
    if (_filler != null) return;
    final frame = _correctFrame;
    setState(() => _filler = f);
    AppHaptics.success();
    widget.env.submit(SessionAnswer(
      result: SessionResult.passed,
      attempts: 1,
      response: SessionResponse(mode: 'chips', fillerIndex: f.index),
    ));
    unawaited(widget.env.voice.play(
      f.audio,
      fallback: frame == null ? f.target : frame.frameTarget.replaceFirst(kSlotMark, f.target),
      key: 'combine-filler',
    ));
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final env = widget.env;
    final line = p.partnerLine;
    final frame = _correctFrame;
    final framePicked = _frameRef != null && !_frameWrong && frame != null;

    if (!framePicked) {
      // Шаг 1 — реплика и три каркаса.
      return CardLayout(
        bodyGap: 12,
        centerBody: true,
        task: SessionTask(l.planSessionTaskReply),
        body: line == null
            ? const SizedBox.shrink()
            : _PhraseSheet(
                plate: ValueListenableBuilder<Object?>(
                  valueListenable: env.voice.playing,
                  builder: (_, playing, _) => _PhrasePlate(
                    topLeft: SessionWave(heights: SessionWave.five, width: 80, playing: playing == _partnerKey),
                    listen: CardListen(env: env, audio: line.audio, fallback: line.textTarget, playKey: _partnerKey),
                    child: Text(line.textTarget, style: AppTextSession.frame),
                  ),
                ),
                footer: _PhraseFooter(
                  eyebrow: env.role.isEmpty ? l.planSessionBrowFrame : l.planSessionBrowPartnerAsks(env.role),
                  native: line.textNative,
                ),
              ),
        bottom: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            for (final f in p.frames) ...[
              if (f != p.frames.first) const SizedBox(height: 8),
              SessionOption(
                key: ValueKey('frame-${f.ref}'),
                text: f.frameTarget,
                target: true,
                look: _frameRef == null
                    ? OptionLook.idle
                    : f.ref == p.correctFrame
                    ? (env.returnsTomorrow ? OptionLook.returns : OptionLook.correct)
                    : f.ref == _frameRef
                    ? OptionLook.wrong
                    : OptionLook.settled,
                shake: f.ref == _frameRef ? _shake : 0,
                onTap: _frameRef == null ? () => _pickFrame(f) : null,
              ),
            ],
            if (_frameWrong) ...[
              const SizedBox(height: 8),
              SessionDockButton(label: l.planSessionNext, busy: env.advancing, onTap: () => unawaited(env.next())),
            ],
          ],
        ),
      );
    }

    // Шаг 2 — окно: чипы наполнений верного каркаса.
    final parts = splitAtSlot(frame.frameTarget);
    final filled = _filler;
    return CardLayout(
      bodyGap: 0,
      fadeStop: 0.30,
      task: SessionTask(l.planSessionTaskReply),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (line != null)
            SizedBox(
              height: 48,
              child: Row(
                children: [
                  Expanded(
                    child: Text('${line.textTarget} · ${line.textNative}', maxLines: 2, style: AppTextSession.sceneLine),
                  ),
                  const SizedBox(width: 12),
                  CardListen(env: env, audio: line.audio, fallback: line.textTarget, playKey: _partnerKey, size: 28),
                ],
              ),
            ),
          const SizedBox(height: 48),
          _PhraseSheet(
            plate: _PhrasePlate(
              listen: filled == null
                  ? null
                  : CardListen(
                      env: env,
                      audio: filled.audio,
                      fallback: frame.frameTarget.replaceFirst(kSlotMark, filled.target),
                      playKey: 'combine-filler',
                    ),
              child: SessionFrameText(
                before: parts.before,
                after: parts.after,
                style: AppTextSession.frame,
                frameColor: filled == null ? null : AppColors.verdictKnown,
                slot: filled?.target,
                look: filled == null ? SlotLook.empty : SlotLook.sage,
              ),
            ),
            footer: _PhraseFooter(
              eyebrow: filled == null ? l.planSessionBrowFrame : l.planSessionBrowAssembled,
              native: filled == null ? frame.frameNative : (filled.nativeLine ?? frame.frameNative.replaceFirst(kSlotMark, filled.native)),
            ),
          ),
        ],
      ),
      bottom: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (filled == null)
            _FillerChips(fillers: p.chips, selected: null, onTap: _pickFiller)
          else
            ValueListenableBuilder<Object?>(
              valueListenable: env.voice.playing,
              builder: (_, playing, _) => Column(
                children: [
                  SessionWave(heights: SessionWave.five, width: 80, playing: playing == 'combine-filler'),
                  const SizedBox(height: 14),
                  // «играет» — только пока звучит собранная фраза (32-8 «собрано · играет»); место держится.
                  Opacity(
                    opacity: playing == 'combine-filler' ? 1 : 0,
                    child: Text(l.planSessionPlaying, style: AppTextSession.meta),
                  ),
                ],
              ),
            ),
          const SizedBox(height: 14),
          SessionDockButton(
            label: l.planSessionNext,
            enabled: filled != null,
            busy: env.advancing,
            onTap: filled == null ? null : () => unawaited(env.next()),
          ),
        ],
      ),
    );
  }
}

// ── 32-9 ──────────────────────────────────────────────────────────────────────────────────────────

/// СВОЁ ОКНО (32-9): каркас с пустым окном, чипы известных наполнений и «своё…» рядом с микрофоном. Тап по
/// чипу — судья с каркасом, сказанным этим наполнением; голос — судья с услышанным. Зачтено — окно шалфеем
/// со значением судьи и «по смыслу ✓»; нет — причина под строкой, «Ещё раз» / «Пропустить». `hinted` —
/// всегда false: каркас на экране всегда.
class PhraseOwnSlotCard extends StatefulWidget {
  const PhraseOwnSlotCard({super.key, required this.env, required this.payload});

  final CardEnv env;
  final PhraseOwnSlotPayload payload;

  @override
  State<PhraseOwnSlotCard> createState() => _PhraseOwnSlotCardState();
}

class _PhraseOwnSlotCardState extends State<PhraseOwnSlotCard> {
  late final SessionMic _mic;
  SessionJudgeOutcome? _verdict;
  String? _reason;
  bool _judging = false;
  bool _done = false;
  int _attempts = 0;
  String _heard = '';
  int? _chip;
  bool _ownChip = false;
  bool _noMicReported = false;

  PhraseOwnSlotPayload get p => widget.payload;

  String get _framePart => (p.frame.parts.before + p.frame.parts.after).trim();

  @override
  void initState() {
    super.initState();
    _mic = widget.env.makeMic(_framePart, [
      _framePart,
      for (final f in p.chips) f.target,
      ...p.examples,
    ])..onTurn = _onTurn;
    _mic.addListener(_onMic);
  }

  @override
  void dispose() {
    _mic.removeListener(_onMic);
    _mic.dispose();
    super.dispose();
  }

  void _onMic() {
    final noMic = _mic.state == MicState.unavailable;
    if (noMic != _noMicReported) {
      _noMicReported = noMic;
      widget.env.reportNoMic(noMic);
    }
    if (mounted) setState(() {});
  }

  void _onTurn(MicTurn turn) {
    if (turn.outcome != SpeechTurnOutcome.heard || turn.transcript.trim().isEmpty) {
      _attempts++;
      _mic.settle(accepted: false);
      setState(() => _reason = null);
      return;
    }
    unawaited(_judge(turn.transcript));
  }

  Future<void> _judge(String heard) async {
    if (_judging || _done) return;
    setState(() {
      _judging = true;
      _reason = null;
      _heard = heard;
    });
    try {
      final verdict = await widget.env.judge(heard);
      if (!mounted) return;
      _attempts = verdict.attempts;
      if (verdict.accepted) {
        _mic.settle(accepted: true);
        AppHaptics.success();
        setState(() {
          _verdict = verdict;
          _done = true;
          _judging = false;
        });
        unawaited(Future<void>.delayed(AppMotion.sessionAutoAdvance, () {
          if (mounted) unawaited(widget.env.next());
        }));
      } else {
        _mic.settle(accepted: false);
        AppHaptics.warning();
        setState(() {
          _reason = verdict.reasonNative;
          _judging = false;
        });
      }
    } catch (e) {
      if (!mounted) return;
      _mic.settle(accepted: false);
      setState(() {
        _reason = AppLocalizations.of(context).planSessionOffline;
        _judging = false;
      });
    }
  }

  void _pickChip(CardFiller f) {
    if (_judging || _done) return;
    setState(() {
      _chip = f.index;
      _ownChip = false;
    });
    unawaited(_judge(p.frame.filledWith(f.target)));
  }

  void _tryAgain() {
    _mic.reset();
    setState(() {
      _reason = null;
      _chip = null;
      _ownChip = false;
    });
  }

  void _skip({bool noMic = false}) {
    if (_done) return;
    _done = true;
    widget.env.submit(SessionAnswer(
      result: SessionResult.skipped,
      attempts: _attempts < 1 ? 1 : _attempts,
      response: SessionResponse(heard: _heard.isEmpty ? null : _heard, noMic: noMic ? true : null),
    ));
    widget.env.reportNoMic(false);
    unawaited(widget.env.next());
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final env = widget.env;
    if (_mic.state == MicState.unavailable) {
      return SessionNoMicView(
        stageName: (s) => SessionTexts.stage(l, s),
        onSkip: () => _skip(noMic: true),
        onAllow: () async {
          final ok = await _mic.askAgain();
          if (!ok && _mic.blockedInSettings) await env.openSettings();
        },
      );
    }
    final accepted = _verdict?.accepted == true;
    final listening = _mic.state == MicState.listening;
    final slotValue = accepted ? (_verdict!.slotValue ?? _slotFromChip()) : null;
    // Пока идёт запись «своего», окно заполняется словами сверх каркаса на лету; запись закрыта и ждёт
    // судью — услышанное стоит в окне замершим, а не пропадает до вердикта.
    final liveSlot = listening ? _slotWords(_mic.partial) : null;
    // «своё…» выбран, пока голосом идёт попытка, и остаётся выбранным после зачёта голосом (32-9).
    final ownSelected = _chip == null && (_ownChip || listening || _judging || accepted);
    return CardLayout(
      bodyGap: 12,
      fadeStop: 0.30,
      task: SessionTask(l.planSessionTaskOwnSlot),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _PhraseSheet(
            plate: _PhrasePlate(
              child: SessionFrameText.frame(
                p.frame,
                style: AppTextSession.frame,
                frameColor: accepted || (listening && _framePartHeard) ? AppColors.verdictKnown : null,
                slot: slotValue ?? (liveSlot == null || liveSlot.isEmpty ? null : liveSlot),
                look: accepted ? SlotLook.sage : (liveSlot != null && liveSlot.isNotEmpty ? SlotLook.filled : SlotLook.empty),
                caret: listening && !_mic.closed,
              ),
            ),
            footer: _PhraseFooter(
              eyebrow: l.planSessionBrowOwnSlot,
              reading: p.frame.framePronunciationNative,
              native: accepted ? l.planSessionByMeaning : p.frame.frameNative,
              nativeStyle: accepted ? AppTextSession.body.copyWith(color: AppColors.verdictKnown) : null,
            ),
          ),
          const SizedBox(height: 24),
          _FillerChips(
            fillers: p.chips,
            selected: _chip,
            onTap: _judging || _done || listening ? null : _pickChip,
            extra: SessionTile(
              key: const ValueKey('chip-own'),
              text: l.planSessionOwnChip,
              height: 40,
              selected: ownSelected,
              trailing: Icon(LucideIcons.mic, size: 16, color: ownSelected ? AppColors.paper : AppColors.ink),
              onTap: _judging || _done || listening
                  ? null
                  : () {
                      setState(() {
                        _ownChip = true;
                        _chip = null;
                        _reason = null;
                      });
                      unawaited(_mic.tap());
                    },
            ),
          ),
        ],
      ),
      bottom: _dock(context),
    );
  }

  /// Каркас вне окна уже прозвучал — на лету, только для цвета строки (зачёт — у судьи).
  bool get _framePartHeard =>
      _mic.partial.isNotEmpty && SpeechCoverage.covers(_mic.partial, _framePart, p.coverageMin, widget.env.articles);

  String? _slotFromChip() {
    for (final f in p.chips) {
      if (f.index == _chip) return f.target;
    }
    return null;
  }

  /// Слова услышанного сверх каркаса — чтобы окно заполнялось на лету (только экран, зачёт — у судьи).
  String _slotWords(String heard) {
    final frameWords = SpeechCoverage.words(_framePart).toSet();
    return heard
        .split(RegExp(r'\s+'))
        .where((w) {
          final tokens = SpeechCoverage.words(w);
          return tokens.isNotEmpty && !tokens.every(frameWords.contains);
        })
        .join(' ');
  }

  Widget _dock(BuildContext context) {
    final l = AppLocalizations.of(context);
    if (_reason != null && !_done) {
      return Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(_reason!, textAlign: TextAlign.center, style: AppTextSession.meta),
          const SizedBox(height: 14),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              _TextButton(label: l.planSessionTryAgain, color: AppColors.brassInk, onTap: _tryAgain),
              const SizedBox(width: 24),
              _TextButton(label: l.planSessionSkip, onTap: _skip),
            ],
          ),
        ],
      );
    }
    return SessionMicPanel(
      mic: _mic,
      expected: _framePart,
      onSkip: _done || _judging ? null : () => _skip(),
      heardText: _heardLine(),
    );
  }

  /// Строка «услышал» после зачёта — каркас со значением окна от судьи.
  String? _heardLine() {
    final v = _verdict;
    if (v == null || !v.accepted) return null;
    final value = v.slotValue ?? _slotFromChip();
    return value == null ? _heard : p.frame.filledWith(value).replaceAll(RegExp(r'[.?!…]+$'), '');
  }
}

class _TextButton extends StatelessWidget {
  const _TextButton({required this.label, required this.onTap, this.color});

  final String label;
  final VoidCallback onTap;
  final Color? color;

  @override
  Widget build(BuildContext context) => Semantics(
    button: true,
    child: GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
        child: Text(label, style: AppTextSession.skip.copyWith(color: color)),
      ),
    ),
  );
}
