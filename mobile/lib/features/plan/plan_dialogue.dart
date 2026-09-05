/// THE DIALOGUE OF A SCENE — серия «Диалог v1», кадры DL·01…DL·10.
///
/// The unit of the trainer is an EXCHANGE and not a card (`docs/plan-dialogue.md` §1): the other
/// person says something, you answer, they react. The server already deals the scene's stage-B
/// cards in the order the scene is spoken (наряд DAY-2, Ч.1), and what lives here is the SHELL
/// around them — the feed above, the live bubble, the rescue button, and the two screens that book-
/// end the conversation.
///
/// ## The exercise cards are not re-implemented, and that is the whole design
///
/// «Механика тренажёров не меняется» is a standing rule, and такт 1 / такт 2 are trainers the app
/// already has: «что тебе сейчас сказали» is the `situational_hear` card and «что ты ответишь» is
/// `situational_say` / `situational_ask`. So the shell wraps the ordinary card widget rather than
/// growing a second copy of it: the answers are ordinary reviews, the ladder is the ladder, and the
/// only thing that changed is that the learner is looking at a conversation instead of a stack.
///
/// ## Курсив — только чужая речь
///
/// The one typographic rule of the series, and it is load-bearing rather than decorative: what the
/// other person says is set in Literata italic, what you say is set in the grotesque. A learner
/// glancing at the feed can tell whose turn a line was without reading it — which is Д-8 answered
/// by the type rather than by a caption.
library;

import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import '../../data/models.dart' show PlanDialogue, PlanDialogueTurn;
import 'plan_ui.dart';

/// ONE SCENE'S CONVERSATION, around one card of it.
///
/// [turnIndex] is where the card at the front stands in [dialogue]; everything before it is the
/// feed. When the current turn is the LEARNER's, the role turn in front of it is drawn as the live
/// bubble — that is кадр DL·06, «зрелый обмен»: the line still sounds, and the screen goes straight
/// to «Что ты ответишь?» because its own card has nothing left to owe.
class PlanDialogueShell extends StatefulWidget {
  const PlanDialogueShell({
    super.key,
    required this.dialogue,
    required this.turnIndex,
    required this.card,
    required this.onSpeak,
    this.taktQuestion,
    this.voiceReady = true,
    this.rescue = const [],
    this.answeredAloud = const {},
    this.dealtTerms = const {},
    this.spokenUntasked = const {},
    this.onSpokeUntasked,
    this.voiceTrouble,
    this.onRescueUsed,
  });

  final PlanDialogue dialogue;

  /// Where the card at the front stands in the chain, or -1 when it is not in it at all — which
  /// happens for a card the ladder owes that the model left out of the conversation
  /// (`plan_day_dialogue_uncovered`). Then the shell draws the whole feed and no live bubble.
  final int turnIndex;

  /// The ordinary exercise card, built by the session exactly as it is everywhere else.
  final Widget card;

  /// Say a line out loud. The SESSION owns the speech engine — it raises the audio route once for
  /// the whole sitting — so the shell asks rather than warming up an engine of its own.
  final void Function(String text) onSpeak;

  /// СПАСАТЕЛЕМ ВОСПОЛЬЗОВАЛИСЬ на текущем ходу (наряд SCENE-RUN, Ч.2.5).
  ///
  /// Панель спасателей законна на любом ходу, включая прогон, и её использование не ошибка — но и
  /// не «сказал сам»: в прогоне это третий исход хода. Экран, который об этом промолчал бы, записал
  /// бы сцену как сказанную голосом там, где голос был чужой.
  final VoidCallback? onRescueUsed;

  /// THE VOICE IS READY — canon §7: «никакая реплика не подаётся на слух, пока озвучка не готова
  /// (без „тишины вместо голоса“)». False draws кадр DL·08 instead of the bubble: the line is not
  /// offered, the options are not asked for, and nothing promises when it will be — the time is not
  /// known, so no countdown is printed.
  ///
  /// Two things have to be true for it, and the session answers both: the speech ENGINE is up, and
  /// THIS line's audio has arrived if the server has one for it (наряд TTS-1, Ч.2.1). A line the
  /// server does not voice is ready the moment the engine is — it was always going to be read by
  /// the phone, and there is nothing to wait for.
  final bool voiceReady;

  /// The plan's five rescue phrases — кадр DL·09. Empty when the sitting has no warm-up in it.
  final List<({String text, String? translation})> rescue;

  /// Term ids the learner has already answered in this conversation — the «сказано вслух» mark.
  final Set<String> answeredAloud;

  /// Дев-бейдж сломанной озвучки, или null. Ставит его СЕССИЯ — она владеет кэшем; оболочка про
  /// докачку ничего не знает и знать не должна.
  final Widget? voiceTrouble;

  /// КРУПНЫЙ РУССКИЙ ВОПРОС ТАКТА — «Что тебе сейчас сказали?» / «Что ты ответишь?» / «Что ты
  /// спросишь?» (наряд DAY-2-FIX, Ч.1.1, кадры DL·02 и DL·03).
  ///
  /// Он стоит НАД карточкой, а не внутри неё, потому что такт — это устройство разговора, а не
  /// упражнения: карточка остаётся тем же тренажёром, что и вне диалога, и рисует только варианты.
  /// Два такта обязаны различаться с одного взгляда, а серая строка «выбери, что ответишь» кеглем
  /// 12 под пузырём этого не делала — живьём владелец не мог сказать, о чём его спрашивают.
  ///
  /// Приходит от СЕССИИ, потому что вопрос задаёт РЕЖИМ карточки, а оболочка режимов не знает.
  final String? taktQuestion;

  /// Ходы, у которых в этой посадке есть карточка — по `term_id`.
  ///
  /// Два разных ответа висят на этом множестве, и оба про честность подписи:
  ///  * «знакомая реплика · разбор не нужен» пишется, только когда у реплики роли карточки СЕГОДНЯ
  ///    нет. Раньше она стояла над каждым живым пузырём, в том числе над той репликой, которую
  ///    человек только что разобрал сам, — подпись врала ровно в том месте, где хвалила;
  ///  * свой ход без карточки не проматывается молча в ленту — см. [spokenUntasked].
  final Set<String> dealtTerms;

  /// Свои ходы без карточки, которые человек уже произнёс вслух в этом разговоре.
  ///
  /// «Участие в каждом своём ходу» (канон §1: единица интерактива — ОБМЕН). Ход `you`, чью карточку
  /// лестница сегодня не выдала, всё равно принадлежит человеку: он проговаривает фразу, и пузырь
  /// встаёт в ленту. Ничего не оценивается и ревью не пишется — это закрепление, а не ответ.
  final Set<String> spokenUntasked;

  /// Человек произнёс свой ход без карточки. Null — оболочка тогда таких ходов не спрашивает
  /// (харнессы и виджет-тесты, которым нужен один кадр).
  final void Function(String termId)? onSpokeUntasked;

  /// WHERE THE LIVE ROLE BUBBLE STANDS in [dialogue], or -1 when there is none.
  ///
  /// ОДИН ПУЗЫРЬ НА ОБА СЛУЧАЯ (наряд DAY-2-FIX, Ч.1.3). The turn itself when the card at the front
  /// IS the role's — `situational_hear` is the такт «Понял?», and кадр DL·02 draws it as a bubble
  /// with «Ещё раз» over three Russian meanings, not as a play button on a card of its own. The
  /// PREVIOUS turn when the card is the learner's, which is кадр DL·03 and DL·06.
  ///
  /// Before this there were two shapes of the same thing: a bubble that sounded by itself and a card
  /// that waited to be tapped. Живьём это и читалось как два разных экрана — «никаких пузырей,
  /// которые молчат до тапа».
  static int liveRoleIndexOf(PlanDialogue dialogue, int turnIndex) {
    if (turnIndex < 0 || turnIndex >= dialogue.turns.length) return -1;
    if (dialogue.turns[turnIndex].isRole) return turnIndex;
    final previous = turnIndex - 1;

    return previous >= 0 && dialogue.turns[previous].isRole ? previous : -1;
  }

  /// THE ROLE LINE THAT SOUNDS AT [turnIndex] — the live bubble, or null.
  ///
  /// Static and public because the SESSION also has to answer it: «озвучка готова» is a fact about
  /// the line that is about to sound, not about the engine (наряд TTS-1), and the screen deciding
  /// that separately from the shell drawing it is two answers to one question.
  static PlanDialogueTurn? liveRoleTurnOf(PlanDialogue dialogue, int turnIndex) {
    final i = liveRoleIndexOf(dialogue, turnIndex);

    return i < 0 ? null : dialogue.turns[i];
  }

  /// СВОИ ХОДЫ БЕЗ КАРТОЧКИ, которые стоят в ленте перед ходом [turnIndex] и ещё не произнесены.
  ///
  /// Their turn is still their turn: the ladder simply owes them nothing today. Канон §1 — единица
  /// интерактива это ОБМЕН, а обмен, в котором человек промолчал, разговором не был. So each of
  /// them is asked out loud once, in order, before the conversation moves on ([PlanDialogueSayAloud]).
  ///
  /// [before] is exclusive; pass `turns.length` to sweep the tail of a finished conversation.
  static List<PlanDialogueTurn> untakenTurnsBefore(
    PlanDialogue dialogue,
    int before, {
    required Set<String> dealtTerms,
    required Set<String> spoken,
  }) => [
    for (var i = 0; i < before && i < dialogue.turns.length; i++)
      if (!dialogue.turns[i].isRole &&
          dialogue.turns[i].text.trim().isNotEmpty &&
          !dealtTerms.contains(dialogue.turns[i].termId) &&
          !spoken.contains(dialogue.turns[i].termId))
        dialogue.turns[i],
  ];

  @override
  State<PlanDialogueShell> createState() => _PlanDialogueShellState();
}

class _PlanDialogueShellState extends State<PlanDialogueShell> {
  /// Whether the live bubble's text is revealed. Reset with the turn: «Показать текст» is a hint
  /// asked for once, not a setting.
  bool _revealed = false;

  int _spokenFor = -1;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _speakLive());
  }

  @override
  void didUpdateWidget(PlanDialogueShell old) {
    super.didUpdateWidget(old);
    if (old.turnIndex != widget.turnIndex) {
      _revealed = false;
      _spokenFor = -1;
    }
    // …и когда свой ход наконец произнесён: реплика собеседника ждала его и теперь звучит сама.
    if (old.turnIndex != widget.turnIndex ||
        (!old.voiceReady && widget.voiceReady) ||
        old.spokenUntasked.length != widget.spokenUntasked.length) {
      WidgetsBinding.instance.addPostFrameCallback((_) => _speakLive());
    }
  }

  /// The live role line, said once per turn — never on every rebuild.
  ///
  /// Не звучит, пока на экране стоит чужой шаг: «Скажи вслух» ([_untaken]) — это ход ЧЕЛОВЕКА, и
  /// реплика собеседника, зазвучавшая поверх него, отвечала бы за него же.
  void _speakLive() {
    final turn = _liveRoleTurn;
    if (!mounted || !widget.voiceReady || turn == null || _untaken != null) return;
    if (_spokenFor == widget.turnIndex) return;
    _spokenFor = widget.turnIndex;
    widget.onSpeak(turn.text);
  }

  int get _liveIndex => PlanDialogueShell.liveRoleIndexOf(widget.dialogue, widget.turnIndex);

  PlanDialogueTurn? get _liveRoleTurn =>
      PlanDialogueShell.liveRoleTurnOf(widget.dialogue, widget.turnIndex);

  /// How many turns of the feed stand behind the current exchange.
  int get _feedEnd => _liveIndex >= 0
      ? _liveIndex
      : (widget.turnIndex < 0 ? widget.dialogue.turns.length : widget.turnIndex);

  /// Свой ход без карточки, который ждёт своей очереди прямо сейчас, или null.
  PlanDialogueTurn? get _untaken {
    if (widget.onSpokeUntasked == null) return null;
    final pending = PlanDialogueShell.untakenTurnsBefore(
      widget.dialogue,
      _feedEnd,
      dealtTerms: widget.dealtTerms,
      spoken: widget.spokenUntasked,
    );

    return pending.isEmpty ? null : pending.first;
  }

  /// «Разбор не нужен» — ПРАВДА ЛИ ЭТО про живой пузырь.
  ///
  /// Правда ровно тогда, когда у реплики роли в этой посадке карточки нет: лестница её не выдала,
  /// значит разбирать нечего, реплика просто звучит. Над репликой, которую человек разбирает прямо
  /// сейчас или только что разобрал, эта подпись врала бы.
  bool get _familiar {
    final live = _liveRoleTurn;

    return live != null && !widget.dealtTerms.contains(live.termId);
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final live = _liveRoleTurn;
    final turns = widget.dialogue.turns;
    final untaken = _untaken;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (widget.voiceTrouble != null) widget.voiceTrouble!,
        _DialogueBar(
          scene: widget.dialogue.dayIndex,
          exchange: _exchangeNumber,
          exchanges: widget.dialogue.exchanges,
          rescue: widget.rescue,
          onSpeak: widget.onSpeak,
          onRescueUsed: widget.onRescueUsed,
        ),
        const SizedBox(height: AppSpacing.s16),
        // THE FEED — everything already spoken, quieter and smaller the further back it is. The
        // conversation goes forward: what is behind reads as context, not as a queue of cards.
        //
        // A turn the learner has not taken yet is NOT in it: [untaken] stops the feed at that line
        // and asks for it out loud instead (Ч.1.4). Без этого свой ход появлялся в ленте сам, будто
        // его кто-то сказал за человека.
        for (var i = 0; i < _feedEnd && i < turns.length; i++) ...[
          if (untaken == null || i < turns.indexOf(untaken)) ...[
            PlanDialogueBubble(
              turn: turns[i],
              past: true,
              aloud: widget.answeredAloud.contains(turns[i].termId) ||
                  widget.spokenUntasked.contains(turns[i].termId),
              onReplay: turns[i].isRole ? () => widget.onSpeak(turns[i].text) : null,
            ),
            const SizedBox(height: 10),
          ],
        ],
        // СВОЙ ХОД НИКОГДА НЕ МОЛЧИТ — кадр DL·04, канон §1. The conversation stops here until the
        // line has been said; nothing is graded and no review is written.
        if (untaken != null)
          PlanDialogueSayAloud(
            turn: untaken,
            onSpeak: widget.onSpeak,
            onDone: () => widget.onSpokeUntasked!(untaken.termId),
          )
        else ...[
          if (live != null) ...[
            if (!widget.voiceReady)
              const _VoicePreparing()
            else
              PlanDialogueBubble(
                turn: live,
                past: false,
                revealed: _revealed,
                onReplay: () => widget.onSpeak(live.text),
                onToggleText: () => setState(() => _revealed = !_revealed),
                // «Знакомая реплика · разбор не нужен» — the line has closed «понимаю», so the
                // screen plays it and goes straight to the answer (кадр DL·06). It is a fact about
                // the card, not a compliment, so it is a caption and not praise — and it is only
                // printed when it is true ([_familiar]).
                note: _familiar ? l.planDialogueFamiliar : null,
              ),
            const SizedBox(height: AppSpacing.s16),
          ],
          // ШАПКА ТАКТА — крупный русский вопрос, кадры DL·02 и DL·03 (наряд DAY-2-FIX, Ч.1.1).
          //
          // Стоит и пока голос едет: «что от меня хотят» — правда про такт, а не про озвучку, и
          // экран, который сначала молчит, а потом задаёт вопрос, читается как два разных экрана.
          if (widget.taktQuestion case final question?) ...[
            Text(
              question,
              style: AppText.collectionNameCard.copyWith(fontSize: 21, height: 1.25),
            ),
            const SizedBox(height: AppSpacing.s12),
          ],
          // Пока голоса нет, реплика не подаётся, и над карточкой стоит «Готовим озвучку» вместо
          // пузыря (кадр DL·08). Варианты при этом ПРИГЛУШЕНЫ, но не заблокированы, и это
          // сознательное расхождение с кадром: у кадра есть выход «Читать сцену без звука», а у
          // экрана его нет, — устройство без голоса для этого языка заперло бы человека в посадке
          // навсегда. Приглушение говорит «рано», блокировка соврала бы «нельзя».
          Opacity(opacity: widget.voiceReady ? 1 : .45, child: widget.card),
        ],
      ],
    );
  }

  /// «обмен 2 из 5» — WHICH EXCHANGE IS BEING PLAYED.
  ///
  /// Counted by the ROLE turns behind us, because an exchange OPENS when the other person speaks:
  /// their line and the answer to it are one exchange, so the number must not tick over between the
  /// two halves of it. Counting the learner's turns instead left «обмен 1 из 5» standing over the
  /// second line the interlocutor said.
  int get _exchangeNumber {
    var n = 0;
    for (var i = 0; i <= widget.turnIndex && i < widget.dialogue.turns.length; i++) {
      if (widget.dialogue.turns[i].isRole) n++;
    }

    return n == 0 ? 1 : n;
  }
}

/// The header of the dialogue: where we are, and the rescue button that never leaves.
class _DialogueBar extends StatelessWidget {
  const _DialogueBar({
    required this.scene,
    required this.exchange,
    required this.exchanges,
    required this.rescue,
    required this.onSpeak,
    this.onRescueUsed,
  });

  final int scene, exchange, exchanges;
  final List<({String text, String? translation})> rescue;
  final void Function(String text) onSpeak;

  /// Спасателем воспользовались на этом ходу — {@see _RescueButton.onUsed}.
  final VoidCallback? onRescueUsed;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);

    return Row(
      children: [
        Expanded(
          child: PlanLabel(
            '${l.planDialogueScene(scene)} · ${l.planDialogueExchangeOf(exchange, exchanges)}',
          ),
        ),
        if (rescue.isNotEmpty) ...[
          const SizedBox(width: AppSpacing.s8),
          _RescueButton(rescue: rescue, onSpeak: onSpeak, onUsed: onRescueUsed),
        ],
      ],
    );
  }
}

/// «Спасатели» — brass, never terracotta: it is a means to hand, not the step being asked for.
class _RescueButton extends StatelessWidget {
  const _RescueButton({required this.rescue, required this.onSpeak, this.onUsed});

  final List<({String text, String? translation})> rescue;
  final void Function(String text) onSpeak;

  /// СПАСАТЕЛЕМ ВОСПОЛЬЗОВАЛИСЬ — не ошибка и не «сам» (канон §8, наряд SCENE-RUN, Ч.2.5).
  ///
  /// В прогоне это третий исход хода: человек его сделал, но сделал не сам. Молчать об этом значило
  /// бы записать сцену как сказанную голосом там, где голос был чужой.
  final VoidCallback? onUsed;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);

    return InkWell(
      borderRadius: BorderRadius.circular(999),
      onTap: () {
        AppHaptics.light();
        showModalBottomSheet<void>(
          context: context,
          backgroundColor: Colors.transparent,
          isScrollControlled: true,
          builder: (_) => PlanRescueSheet(
            rescue: rescue,
            onSpeak: (text) {
              onUsed?.call();
              onSpeak(text);
            },
          ),
        );
      },
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
        decoration: BoxDecoration(
          border: Border.all(color: AppColors.brassInk.withValues(alpha: .38)),
          borderRadius: BorderRadius.circular(999),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(LucideIcons.lifeBuoy, size: 13, color: AppColors.brassInk),
            const SizedBox(width: 6),
            Text(
              l.planDialogueRescue,
              style: AppText.blockLabel.copyWith(color: AppColors.brassInk, letterSpacing: .4),
            ),
          ],
        ),
      ),
    );
  }
}

/// ПАНЕЛЬ СПАСАТЕЛЕЙ — кадр DL·09.
///
/// Brass throughout and no terracotta anywhere on it: the turn is still the learner's, and painting
/// the way out as the thing to do would be the app answering for them. The note says out loud that
/// using one is not a mistake — канон §8, «их использование не ошибка, а нормальный ход».
class PlanRescueSheet extends StatelessWidget {
  const PlanRescueSheet({super.key, required this.rescue, this.onSpeak});

  final List<({String text, String? translation})> rescue;

  /// Say one of them out loud. THE SAME VOICE AS THE CONVERSATION, and that is the point of routing
  /// it through the session rather than through a pronouncer of the sheet's own (наряд TTS-1): a
  /// rescue phrase is trained in the warm-up, shown here, and read again on the cheat sheet, and
  /// three voices for one phrase would be three phrases for the ear.
  final void Function(String text)? onSpeak;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);

    return SafeArea(
      top: false,
      child: Container(
        margin: const EdgeInsets.all(AppSpacing.s12),
        padding: const EdgeInsets.fromLTRB(20, 18, 20, 18),
        decoration: BoxDecoration(
          color: AppColors.surfaceRaised,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: AppColors.brassInk.withValues(alpha: .3)),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Expanded(child: PlanLabel(l.planDialogueRescue)),
                Text(
                  l.planDialogueRescuePhrases(rescue.length),
                  style: AppText.blockLabel.copyWith(color: AppColors.brassInk),
                ),
              ],
            ),
            const SizedBox(height: AppSpacing.s12),
            for (final phrase in rescue) ...[
              InkWell(
                onTap: onSpeak == null
                    ? null
                    : () {
                        AppHaptics.light();
                        onSpeak!(phrase.text);
                      },
                child: Padding(
                  padding: const EdgeInsets.symmetric(vertical: 2),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              phrase.text,
                              style: AppText.collectionNameCard.copyWith(fontSize: 17, height: 1.35),
                            ),
                            if ((phrase.translation ?? '').isNotEmpty)
                              Text(
                                phrase.translation!,
                                style: AppText.translation
                                    .copyWith(fontSize: 13.5, color: AppColors.secondary),
                              ),
                          ],
                        ),
                      ),
                      if (onSpeak != null) ...[
                        const SizedBox(width: AppSpacing.s12),
                        const Icon(LucideIcons.volume2, size: 16, color: AppColors.brassInk),
                      ],
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 12),
            ],
            Text(
              l.planDialogueRescueNote,
              style: AppText.translation.copyWith(
                fontSize: 13,
                height: 1.5,
                color: AppColors.tertiary,
              ),
            ),
            const SizedBox(height: AppSpacing.s16),
            QuietButton(
              label: l.planDialogueRescueBack,
              onPressed: () => Navigator.of(context).maybePop(),
            ),
          ],
        ),
      ),
    );
  }
}

/// ONE TURN IN THE FEED — the other person's on the left in italic serif, the learner's on the
/// right in the grotesque.
class PlanDialogueBubble extends StatelessWidget {
  const PlanDialogueBubble({
    super.key,
    required this.turn,
    required this.past,
    this.revealed = false,
    this.aloud = false,
    this.note,
    this.onReplay,
    this.onToggleText,
  });

  final PlanDialogueTurn turn;

  /// Already behind us: smaller, quieter, and with no controls — the conversation moves forward.
  final bool past;

  /// The live bubble's text is showing. A past bubble always shows its text: it has been heard.
  final bool revealed;

  /// «сказано вслух» under the learner's own turn — a fact, not a grade (кадр DL·05).
  final bool aloud;

  /// One caption under the live bubble, when there is something true to say.
  final String? note;

  final VoidCallback? onReplay, onToggleText;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final showText = past || revealed;

    return Align(
      alignment: turn.isRole ? Alignment.centerLeft : Alignment.centerRight,
      child: ConstrainedBox(
        constraints: BoxConstraints(maxWidth: MediaQuery.sizeOf(context).width * .84),
        child: Container(
          padding: const EdgeInsets.fromLTRB(14, 11, 14, 11),
          decoration: BoxDecoration(
            color: turn.isRole ? AppColors.surfaceRaised : AppColors.photoPlate,
            borderRadius: BorderRadius.circular(15),
            border: Border.all(
              color: past
                  ? AppColors.dividerFaint
                  : AppColors.brassInk.withValues(alpha: turn.isRole ? .3 : .0),
            ),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              if (showText)
                Text(
                  turn.text,
                  style: turn.isRole
                      // Курсив — только чужая речь; своя набирается прямым.
                      ? AppText.collectionNameCard.copyWith(
                          fontSize: past ? 14.5 : 16,
                          height: 1.45,
                          fontStyle: FontStyle.italic,
                          color: past ? AppColors.secondary : AppColors.ink,
                        )
                      : AppText.termInList.copyWith(
                          fontSize: past ? 14.5 : 16,
                          height: 1.45,
                          color: past ? AppColors.secondary : AppColors.ink,
                        ),
                )
              else
                // The bubble stands where its text would stand, so it reads as part of the feed
                // rather than as a separate exercise (кадр DL·02).
                Text(
                  l.planDialogueRoleSpeaks,
                  style: AppText.translation.copyWith(fontSize: 13, color: AppColors.tertiary),
                ),
              if (aloud) ...[
                const SizedBox(height: 4),
                Text(
                  l.planDialogueSaidAloud,
                  style: AppText.blockLabel.copyWith(color: AppColors.tertiary),
                ),
              ],
              if (!past && (onReplay != null || onToggleText != null)) ...[
                const SizedBox(height: 8),
                Row(
                  children: [
                    if (onReplay != null)
                      _BubbleAction(
                        icon: LucideIcons.rotateCcw,
                        label: l.planDialogueReplay,
                        onTap: onReplay!,
                      ),
                    if (onReplay != null && onToggleText != null) const SizedBox(width: 14),
                    if (onToggleText != null)
                      // Quieter than everything else on the card: «Показать текст» is a hint, not a
                      // step, and it is available at every moment of the conversation.
                      _BubbleAction(
                        icon: revealed ? LucideIcons.eyeOff : LucideIcons.eye,
                        label: revealed ? l.planDialogueHideText : l.planDialogueShowText,
                        onTap: onToggleText!,
                        quiet: true,
                      ),
                  ],
                ),
              ],
              if (note != null && !past) ...[
                const SizedBox(height: 6),
                Text(
                  note!,
                  style: AppText.translation.copyWith(fontSize: 12.5, color: AppColors.tertiary),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _BubbleAction extends StatelessWidget {
  const _BubbleAction({
    required this.icon,
    required this.label,
    required this.onTap,
    this.quiet = false,
  });

  final IconData icon;
  final String label;
  final VoidCallback onTap;
  final bool quiet;

  @override
  Widget build(BuildContext context) {
    final color = quiet ? AppColors.tertiary : AppColors.brassInk;

    return InkWell(
      borderRadius: BorderRadius.circular(8),
      onTap: () {
        AppHaptics.light();
        onTap();
      },
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 4, horizontal: 2),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 13, color: color),
            const SizedBox(width: 5),
            Text(label, style: AppText.blockLabel.copyWith(color: color, letterSpacing: .4)),
          ],
        ),
      ),
    );
  }
}

/// «СКАЖИ ВСЛУХ» — кадр DL·04, свой ход, которого лестница сегодня не спрашивает.
///
/// Три-пять обменов в сцене, а карточек на них лестница выдаёт столько, сколько дозрело: ход `you`
/// без карточки — обычное дело, и до этого наряда он просто появлялся в ленте сам. Экран говорил
/// «ты это сказал» человеку, который не открывал рта. Канон §1 держит обратное: единица интерактива
/// — обмен, и в каждом своём ходу человек участвует.
///
/// Что здесь НЕ происходит: ничего не оценивается, ничего не сравнивается, ревью не пишется и
/// лестница не двигается. Подпись говорит это вслух, потому что кнопка с микрофоном обещает разбор
/// произношения, которого тут нет.
class PlanDialogueSayAloud extends StatelessWidget {
  const PlanDialogueSayAloud({
    super.key,
    required this.turn,
    required this.onSpeak,
    required this.onDone,
  });

  final PlanDialogueTurn turn;

  /// «Послушать, как это звучит» — тем же голосом, что и весь разговор.
  final void Function(String text) onSpeak;

  /// Произнёс — пузырь встаёт в ленту, разговор идёт дальше.
  final VoidCallback onDone;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        // «Ваш ответ» / «Ваш вопрос» — по полке хода, потому что «Ты спросишь» это не ответ и
        // подписывать его ответом значит называть ход не тем, что он есть (канон §2).
        PlanLabel(
          turn.shelf == 'ask' ? l.planDialogueYourQuestion : l.planDialogueYourAnswer,
        ),
        const SizedBox(height: AppSpacing.s8),
        PaperCard(
          radius: 16,
          padding: const EdgeInsets.fromLTRB(18, 18, 18, 18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // Своя реплика набирается ПРЯМЫМ — курсив в серии значит чужую речь, и ничего больше.
              Text(
                turn.text,
                style: AppText.termInList.copyWith(fontSize: 22, height: 1.35),
              ),
              const SizedBox(height: AppSpacing.s16),
              Align(
                alignment: Alignment.centerLeft,
                child: QuietButton(
                  label: l.planDialogueListenHow,
                  icon: LucideIcons.volume2,
                  onPressed: () => onSpeak(turn.text),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: AppSpacing.s16),
        Text(
          l.planDialogueSayAloudNote,
          style: AppText.translation.copyWith(
            fontSize: 13.5,
            height: 1.55,
            color: AppColors.secondary,
          ),
        ),
        const SizedBox(height: AppSpacing.s16),
        PrimaryButton(
          label: l.planDialogueSaidIt,
          minHeight: 52,
          onPressed: () {
            AppHaptics.light();
            onDone();
          },
        ),
      ],
    );
  }
}

/// «ГОТОВИМ ОЗВУЧКУ» — кадр DL·08.
///
/// Nothing is blocked and nothing is promised: the line simply has not sounded yet, and the screen
/// says so rather than sitting silent with three options under it. No «осталось 5 секунд» — the
/// time is not known, so it is not printed.
class _VoicePreparing extends StatelessWidget {
  const _VoicePreparing();

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);

    return Container(
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
      decoration: BoxDecoration(
        color: AppColors.surfaceRaised,
        borderRadius: BorderRadius.circular(15),
        border: Border.all(color: AppColors.dividerFaint),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              // A MARK, not a spinner. The wait has no known length (кадр DL·08 refuses to promise
              // «осталось 5 секунд»), and a turning ring is a promise that something is being
              // counted down. It is also the difference between a screen that settles and one that
              // animates for ever, which is what a widget test measures.
              Container(
                width: 7,
                height: 7,
                decoration: const BoxDecoration(
                  shape: BoxShape.circle,
                  color: AppColors.brassInk,
                ),
              ),
              const SizedBox(width: 9),
              Text(
                l.planDialogueVoicePreparing,
                style: AppText.blockLabel.copyWith(color: AppColors.brassInk, letterSpacing: .4),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            l.planDialogueVoicePreparingBody,
            style: AppText.translation.copyWith(
              fontSize: 13,
              height: 1.5,
              color: AppColors.secondary,
            ),
          ),
        ],
      ),
    );
  }
}

/// ВХОД В ДИАЛОГ — кадр DL·01.
///
/// Two honest warnings and one action: the lines SOUND rather than being written, and answering
/// will be a move of the learner's own. «N обменов» is the server's count, not an estimate of
/// length.
class PlanDialogueIntro extends StatelessWidget {
  const PlanDialogueIntro({
    super.key,
    required this.dialogue,
    required this.onStart,
    this.rescueCount = 0,
  });

  final PlanDialogue dialogue;
  final VoidCallback onStart;
  final int rescueCount;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);

    return ListView(
      padding: const EdgeInsets.fromLTRB(
        AppSpacing.screenH,
        AppSpacing.s26,
        AppSpacing.screenH,
        AppSpacing.s26,
      ),
      children: [
        PlanLabel(l.planDialogueScene(dialogue.dayIndex)),
        const SizedBox(height: AppSpacing.s8),
        Text(
          dialogue.sceneTitle ?? '',
          style: AppText.collectionNameScreen.copyWith(fontSize: 27, height: 1.2),
        ),
        if ((dialogue.sceneIntro ?? '').isNotEmpty) ...[
          const SizedBox(height: AppSpacing.s12),
          Text(
            dialogue.sceneIntro!,
            style: AppText.translation.copyWith(
              fontSize: 14.5,
              height: 1.6,
              color: AppColors.inkBody,
            ),
          ),
        ],
        const SizedBox(height: AppSpacing.s22),
        _IntroBlock(
          label: l.planDialogueLabel,
          value: l.planDialogueExchanges(dialogue.exchanges),
          body: l.planDialogueLead,
        ),
        if (rescueCount > 0) ...[
          const SizedBox(height: AppSpacing.s16),
          _IntroBlock(
            label: l.planDialogueRescueAtHand,
            value: l.planDialogueRescuePhrases(rescueCount),
            body: l.planDialogueRescueLead,
          ),
        ],
        const SizedBox(height: AppSpacing.s26),
        PrimaryButton(label: l.planDialogueStart, minHeight: 52, onPressed: onStart),
        const SizedBox(height: 10),
        Center(
          child: Text(
            l.planDialogueSound,
            style: AppText.translation.copyWith(fontSize: 13, color: AppColors.tertiary),
          ),
        ),
      ],
    );
  }
}

class _IntroBlock extends StatelessWidget {
  const _IntroBlock({required this.label, required this.value, required this.body});

  final String label, value, body;

  @override
  Widget build(BuildContext context) => PaperCard(
    radius: 16,
    padding: const EdgeInsets.fromLTRB(18, 15, 18, 15),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            Expanded(child: PlanLabel(label)),
            Text(value, style: AppText.blockLabel.copyWith(color: AppColors.brassInk)),
          ],
        ),
        const SizedBox(height: 8),
        Text(
          body,
          style: AppText.translation.copyWith(
            fontSize: 13.5,
            height: 1.55,
            color: AppColors.secondary,
          ),
        ),
      ],
    ),
  );
}

/// ФИНАЛ ДИАЛОГА — кадр DL·10.
///
/// The whole conversation, and then three facts in the mono face with not one percentage among
/// them. «Просил повторить · 1 раз» stands beside the others without apology: it is part of a
/// conversation, and a screen that hid it would be teaching the learner to avoid the one move that
/// keeps a real one alive.
class PlanDialogueDone extends StatelessWidget {
  const PlanDialogueDone({
    super.key,
    required this.dialogue,
    required this.answeredSelf,
    required this.answerable,
    required this.heardOut,
    required this.hearable,
    required this.rescueUsed,
    required this.onDone,
    this.run,
  });

  final PlanDialogue dialogue;

  /// Turns the learner actually took, out of the turns that were theirs to take.
  final int answeredSelf, answerable;

  /// Role lines whose meaning the learner picked, out of the ones they were asked about.
  final int heardOut, hearable;

  final int rescueUsed;
  final VoidCallback onDone;

  /// ИТОГ ПРОГОНА СЦЕНЫ — «Прошёл сам N из M · сразу K» (кадр DL·10, наряд SCENE-RUN, Ч.2.7).
  ///
  /// Null у обычного финала диалога: там мерили выбор, а не голос, и «прошёл сам» было бы не про
  /// ту ступень. Процента здесь нет и не будет — на экранах плана его нет нигде.
  final ({int total, int said, int fast})? run;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);

    return SafeArea(
      child: ListView(
        padding: const EdgeInsets.fromLTRB(
          AppSpacing.screenH,
          AppSpacing.s26,
          AppSpacing.screenH,
          AppSpacing.s26,
        ),
        children: [
          PlanLabel(
            '${l.planDialogueScene(dialogue.dayIndex)} · ${l.planDialogueDone}',
            color: AppColors.brassInk,
          ),
          const SizedBox(height: AppSpacing.s8),
          Text(
            dialogue.sceneTitle ?? '',
            style: AppText.collectionNameScreen.copyWith(fontSize: 27, height: 1.2),
          ),
          const SizedBox(height: AppSpacing.s12),
          Text(
            l.planDialogueDoneLead,
            style: AppText.translation.copyWith(
              fontSize: 14,
              height: 1.55,
              color: AppColors.secondary,
            ),
          ),
          const SizedBox(height: AppSpacing.s22),
          for (final turn in dialogue.turns) ...[
            PlanDialogueBubble(turn: turn, past: true),
            const SizedBox(height: 8),
          ],
          const SizedBox(height: AppSpacing.s22),
          PlanLabel(l.planDialogueResult),
          const SizedBox(height: 10),
          if (run case final r?) ...[
            // ПРОГОН МЕРИТ ДРУГОЕ: не «сколько ходов ты сделал», а «сколько ты СКАЗАЛ сам» и
            // «сколько из них — сразу». Две строки вместо одной, потому что медленный успех
            // остаётся успехом (канон §4) и прятать его за одним числом было бы неправдой.
            _Fact(label: l.planSceneRunSaidSelf, value: l.planDialogueCountOf(r.said, r.total)),
            _Fact(label: l.planSceneRunSaidFast, value: '${r.fast}'),
          ] else
            _Fact(
              label: l.planDialogueAnsweredSelf,
              value: l.planDialogueCountOf(answeredSelf, answerable),
            ),
          if (run == null && hearable > 0)
            _Fact(label: l.planDialogueHeardOut, value: l.planDialogueCountOf(heardOut, hearable)),
          if (rescueUsed > 0)
            _Fact(label: l.planDialogueAskedRepeat, value: l.planDialogueTimes(rescueUsed)),
          const SizedBox(height: AppSpacing.s16),
          Text(
            l.planDialogueNextAssembly,
            style: AppText.translation.copyWith(
              fontSize: 13.5,
              height: 1.55,
              color: AppColors.tertiary,
            ),
          ),
          const SizedBox(height: AppSpacing.s22),
          PrimaryButton(label: l.planDialogueBackToSession, minHeight: 52, onPressed: onDone),
        ],
      ),
    );
  }
}

class _Fact extends StatelessWidget {
  const _Fact({required this.label, required this.value});

  final String label, value;

  @override
  Widget build(BuildContext context) => Container(
    constraints: const BoxConstraints(minHeight: AppSpacing.minTap),
    decoration: const BoxDecoration(
      border: Border(bottom: BorderSide(color: AppColors.dividerFaint)),
    ),
    child: Row(
      children: [
        Expanded(
          child: Text(
            label,
            style: AppText.translation.copyWith(fontSize: 15, color: AppColors.inkBody),
          ),
        ),
        const SizedBox(width: AppSpacing.s12),
        Text(value, style: AppText.blockLabel.copyWith(fontSize: 14, color: AppColors.brassInk)),
      ],
    ),
  );
}
