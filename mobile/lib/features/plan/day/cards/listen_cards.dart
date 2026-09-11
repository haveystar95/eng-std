import 'dart:async';

import 'package:flutter/material.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import '../../../../data/plan/day_rules.dart';
import '../../../../data/plan/day_contract.dart';
import '../day_card_frame.dart';
import '../day_texts.dart';
import '../day_voice.dart';
import 'card_context.dart';
import 'word_cards.dart' show returnNoteText, returnNoteStyle;

/// «СЛУШАЮ И ОТВЕЧАЮ · ЧТО ОН СПРОСИЛ» (кадры 23-7a–c): карточка «ВРАЧ ГОВОРИТ» с волной — реплика
/// звучит сама, текста нет; внизу одной группой блок задания «ЧТО ОН СПРОСИЛ» + вопрос и варианты
/// 4л (Beginner — по-русски, Intermediate — по-английски). Верно — карточка врача раскрывает текст
/// и перевод, звук «верно» ждёт конца реплики; неверно — объяснение в карточке врача, «Вернётся в
/// конце этапа» / терракотой «Вернётся в день N».
class ListenQuestionCard extends DayCardWidget {
  const ListenQuestionCard({super.key, required super.card, required super.context, required super.onNext});

  @override
  State<ListenQuestionCard> createState() => _ListenQuestionCardState();
}

class _ListenQuestionCardState extends State<ListenQuestionCard> {
  int? _picked;
  bool _sent = false;
  final _verdict = _DeferredVerdict();

  bool get _answered => _picked != null;
  bool get _correct => _picked != null && widget.card.options[_picked!].correct;

  @override
  void dispose() {
    _verdict.dispose();
    super.dispose();
  }

  Future<void> _pick(int i) async {
    if (_answered) return;
    setState(() => _picked = i);
    final ok = widget.card.options[i].correct;
    // ЗВУК ВЕРДИКТА ЖДЁТ КОНЦА РЕПЛИКИ (4к-3) — никогда поверх озвучки.
    _verdict.queue(ok);
    final attempt = DayRules.graded(correct: ok, isRetry: widget.card.retryOf != null);
    _sent = true;
    await widget.context.session.answer(widget.card, DayRules.resultOf(attempt), attempts: 1);
    if (mounted) setState(() {});
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final card = widget.card;
    final partner = card.partner;
    final target = card.language == 'target';

    return DayCardFrame(
      topCentered: false,
      top: partner == null
          ? const SizedBox.shrink()
          : PartnerLineCard(
              label: l.daySpeakerSays(widget.context.roleOf(partner)),
              text: partner.textTarget,
              translation: partner.textNative,
              explanation: _answered && !_correct ? card.explanationNative : null,
              voice: widget.context.voice,
              revealed: _answered,
              onPlayed: _verdict.lineDone,
            ),
      bottom: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          TaskBlock(label: l.dayTaskAsked, text: card.question ?? ''),
          const SizedBox(height: 16),
          for (var i = 0; i < card.options.length; i++) ...[
            if (i > 0) const SizedBox(height: 10),
            AnswerOption(
              text: card.options[i].text,
              target: target,
              verdict: optionVerdict(card, _picked, i),
              onTap: () => unawaited(_pick(i)),
            ),
          ],
        ],
      ),
      dock: _answered
          ? DayDock(
              label: l.dayNext,
              onTap: _sent ? widget.onNext : null,
              note: _correct ? null : returnNoteText(l, card, widget.context.returnDay),
              noteStyle: returnNoteStyle(card),
            )
          : null,
    );
  }
}

AnswerOptionVerdict optionVerdict(DayCard card, int? picked, int i) {
  if (picked == null) return AnswerOptionVerdict.none;
  final correct = card.options[i].correct;
  if (i == picked) return correct ? AnswerOptionVerdict.correct : AnswerOptionVerdict.wrong;
  if (correct) return AnswerOptionVerdict.correctQuiet;
  return AnswerOptionVerdict.dimmed;
}

/// УСЛЫШАЛ → СОБЕРИ (кадры 23-7f–h; только Intermediate, только утверждения ≤ 10 слов — вид
/// карточки отдаёт сервер, клиент лишь рендерит): «ВРАЧ ГОВОРИТ» с волной, текста нет; лейбл
/// «СОБЕРИ, ЧТО ОН СКАЗАЛ»; сборка по стандарту — серая подложка, плитки на бумаге. Верно —
/// подложка шалфеем, маркер слева, перевод под ней; ошибка — терракотой, лишняя плитка вздрагивает,
/// верная реплика раскрывается текстом с переводом.
class ListenAssembleCard extends DayCardWidget {
  const ListenAssembleCard({super.key, required super.card, required super.context, required super.onNext});

  @override
  State<ListenAssembleCard> createState() => _ListenAssembleCardState();
}

class _ListenAssembleCardState extends State<ListenAssembleCard> {
  final List<int> _placed = [];
  bool _answered = false, _correct = false, _sent = false;
  final _verdict = _DeferredVerdict();

  List<String> get _placedTexts => [for (final i in _placed) widget.card.tiles[i]];

  @override
  void dispose() {
    _verdict.dispose();
    super.dispose();
  }

  Future<void> _place(int i) async {
    if (_answered) return;
    AppHaptics.light();
    setState(() => _placed.add(i));
    if (_placed.length >= DayRules.tokens(widget.card.answer).length) await _check();
  }

  Future<void> _check() async {
    final ok = DayRules.assembledMatches(placed: _placedTexts, answer: widget.card.answer);
    setState(() {
      _answered = true;
      _correct = ok;
    });
    _verdict.queue(ok);
    final attempt = DayRules.graded(correct: ok, isRetry: widget.card.retryOf != null);
    _sent = true;
    await widget.context.session.answer(widget.card, DayRules.resultOf(attempt), attempts: 1);
    if (mounted) setState(() {});
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final card = widget.card;
    final partner = card.partner;
    final verdict = !_answered
        ? AnswerOptionVerdict.none
        : _correct
        ? AnswerOptionVerdict.correct
        : AnswerOptionVerdict.wrong;

    return DayCardFrame(
      topCentered: false,
      top: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (partner != null)
            PartnerLineCard(
              label: l.daySpeakerSays(widget.context.roleOf(partner)),
              text: partner.textTarget,
              translation: partner.textNative,
              voice: widget.context.voice,
              revealed: false,
              onPlayed: _verdict.lineDone,
            ),
          const SizedBox(height: 18),
          TaskBlock(label: l.dayTaskAssemble),
          const SizedBox(height: 14),
          AssemblyBoard(
            placed: _placedTexts,
            verdict: verdict,
            minHeight: 84,
            onTapPlaced: _answered ? null : (idx) => setState(() => _placed.removeAt(idx)),
            shakeIndex: _answered && !_correct ? DayRules.extraTileIndex(placed: _placedTexts, answer: card.answer) : null,
          ),
          if (_answered && _correct && (card.textNative.isNotEmpty || partner?.textNative.isNotEmpty == true)) ...[
            const SizedBox(height: 12),
            Text(card.textNative.isNotEmpty ? card.textNative : partner!.textNative, style: AppTextDay.partnerTranslation),
          ],
          const SizedBox(height: 12),
          TileTray(tiles: card.tiles, used: _placed.toSet(), onTap: _answered ? null : (i) => unawaited(_place(i))),
          if (_answered && !_correct) ...[
            const SizedBox(height: 14),
            PaperCard(
              radius: AppRadii.field,
              padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(card.answer, style: AppTextDay.listWord),
                  const SizedBox(height: 6),
                  Text(card.textNative.isNotEmpty ? card.textNative : (partner?.textNative ?? ''), style: AppTextDay.partnerTranslation),
                ],
              ),
            ),
          ],
        ],
      ),
      bottom: const SizedBox.shrink(),
      dock: _answered
          ? DayDock(
              label: l.dayNext,
              onTap: _sent ? widget.onNext : null,
              note: _correct ? null : returnNoteText(l, card, widget.context.returnDay),
              noteStyle: returnNoteStyle(card),
            )
          : null,
    );
  }
}

/// «ЧТО ОТВЕТИШЬ» (кадр 23-7d): задание — перевод реплики ученика в кавычках, три варианта 4л
/// (Literata 18); группа прибита к низу, верх — воздух.
class AnswerChooseCard extends DayCardWidget {
  const AnswerChooseCard({super.key, required super.card, required super.context, required super.onNext});

  @override
  State<AnswerChooseCard> createState() => _AnswerChooseCardState();
}

class _AnswerChooseCardState extends State<AnswerChooseCard> {
  int? _picked;
  bool _sent = false;

  bool get _correct => _picked != null && widget.card.options[_picked!].correct;

  Future<void> _pick(int i) async {
    if (_picked != null) return;
    setState(() => _picked = i);
    final ok = widget.card.options[i].correct;
    if (ok) {
      AppFeedback.correct();
    } else {
      AppFeedback.wrong();
    }
    final attempt = DayRules.graded(correct: ok, isRetry: widget.card.retryOf != null);
    _sent = true;
    await widget.context.session.answer(widget.card, DayRules.resultOf(attempt), attempts: 1);
    if (mounted) setState(() {});
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final card = widget.card;
    final own = card.options.where((o) => o.correct).firstOrNull;
    final task = own?.textNative ?? card.taskNative ?? '';

    return DayCardFrame(
      top: null,
      bottom: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          TaskBlock(label: l.dayTaskAnswer(DayTexts.adverb(l, widget.context.targetLang)), text: '«$task»'),
          const SizedBox(height: 16),
          for (var i = 0; i < card.options.length; i++) ...[
            if (i > 0) const SizedBox(height: 10),
            AnswerOption(
              text: card.options[i].text,
              target: true,
              verdict: optionVerdict(card, _picked, i),
              onTap: () => unawaited(_pick(i)),
            ),
          ],
        ],
      ),
      dock: _picked != null
          ? DayDock(
              label: l.dayNext,
              onTap: _sent ? widget.onNext : null,
              note: _correct ? null : returnNoteText(l, card, widget.context.returnDay),
              noteStyle: returnNoteStyle(card),
            )
          : null,
    );
  }
}

/// «ТЫ НАЧИНАЕШЬ» (кадр 23-7e, стандарт): задание в кавычках, сборка на серой подложке; после
/// сборки — карточка «ВРАЧ ОТВЕЧАЕТ» с латунным лейблом, текстом, переводом и волной. Выбора нет.
class AnswerAssembleCard extends DayCardWidget {
  const AnswerAssembleCard({super.key, required super.card, required super.context, required super.onNext});

  @override
  State<AnswerAssembleCard> createState() => _AnswerAssembleCardState();
}

class _AnswerAssembleCardState extends State<AnswerAssembleCard> {
  final List<int> _placed = [];
  bool _answered = false, _correct = false, _sent = false;

  List<String> get _placedTexts => [for (final i in _placed) widget.card.tiles[i]];

  Future<void> _place(int i) async {
    if (_answered) return;
    AppHaptics.light();
    setState(() => _placed.add(i));
    if (_placed.length >= DayRules.tokens(widget.card.answer).length) await _check();
  }

  Future<void> _check() async {
    final ok = DayRules.assembledMatches(placed: _placedTexts, answer: widget.card.answer);
    setState(() {
      _answered = true;
      _correct = ok;
    });
    if (ok) {
      AppFeedback.correct();
    } else {
      AppFeedback.wrong();
    }
    final attempt = DayRules.graded(correct: ok, isRetry: widget.card.retryOf != null);
    _sent = true;
    await widget.context.session.answer(widget.card, DayRules.resultOf(attempt), attempts: 1);
    if (mounted) setState(() {});
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final card = widget.card;
    final partner = card.partner;
    final verdict = !_answered
        ? AnswerOptionVerdict.none
        : _correct
        ? AnswerOptionVerdict.correct
        : AnswerOptionVerdict.wrong;

    return DayCardFrame(
      topCentered: false,
      top: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          TaskBlock(
            label: l.dayTaskSay(DayTexts.adverb(l, widget.context.targetLang)),
            text: '«${card.promptNative ?? card.taskNative ?? ''}»',
          ),
          const SizedBox(height: 16),
          AssemblyBoard(
            placed: _placedTexts,
            verdict: verdict,
            onTapPlaced: _answered ? null : (idx) => setState(() => _placed.removeAt(idx)),
            shakeIndex: _answered && !_correct ? DayRules.extraTileIndex(placed: _placedTexts, answer: card.answer) : null,
          ),
          const SizedBox(height: 12),
          TileTray(tiles: card.tiles, used: _placed.toSet(), onTap: _answered ? null : (i) => unawaited(_place(i))),
          if (_answered && !_correct) ...[
            const SizedBox(height: 14),
            PaperCard(
              radius: AppRadii.field,
              padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
              child: Text(card.answer, style: AppTextDay.listWord),
            ),
          ],
          if (_answered && partner != null) ...[
            const SizedBox(height: 18),
            PartnerLineCard(
              label: l.daySpeakerAnswers(widget.context.roleOf(partner)),
              text: partner.textTarget,
              translation: partner.textNative,
              voice: widget.context.voice,
              revealed: true,
            ),
          ],
        ],
      ),
      bottom: const SizedBox.shrink(),
      dock: _answered
          ? DayDock(
              label: l.dayNext,
              onTap: _sent ? widget.onNext : null,
              note: _correct ? null : returnNoteText(l, card, widget.context.returnDay),
              noteStyle: returnNoteStyle(card),
            )
          : null,
    );
  }
}

/// ЗВУК ВЕРДИКТА, ОТЛОЖЕННЫЙ ДО КОНЦА РЕПЛИКИ (4к-3): если реплика собеседника ещё звучит, звук
/// ждёт [lineDone]; сторож 15 с — на случай, если озвучка так и не доиграет.
class _DeferredVerdict {
  bool _lineDone = false;
  bool? _pending;
  Timer? _guard;

  void lineDone() {
    _lineDone = true;
    _flush();
  }

  void queue(bool ok) {
    _pending = ok;
    if (_lineDone) {
      _flush();
    } else {
      _guard?.cancel();
      _guard = Timer(const Duration(seconds: 15), _flush);
    }
  }

  void _flush() {
    _guard?.cancel();
    _guard = null;
    final ok = _pending;
    _pending = null;
    if (ok == null) return;
    if (ok) {
      AppFeedback.correct();
    } else {
      AppFeedback.wrong();
    }
  }

  void dispose() {
    _guard?.cancel();
  }
}
