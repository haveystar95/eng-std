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
    this.voiceReady = true,
    this.rescue = const [],
    this.answeredAloud = const {},
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

  /// THE VOICE IS READY — canon §7: «никакая реплика не подаётся на слух, пока озвучка не готова
  /// (без „тишины вместо голоса“)». False draws кадр DL·08 instead of the bubble: the line is not
  /// offered, the options are not asked for, and nothing promises when it will be — the time is not
  /// known, so no countdown is printed.
  final bool voiceReady;

  /// The plan's five rescue phrases — кадр DL·09. Empty when the sitting has no warm-up in it.
  final List<({String text, String? translation})> rescue;

  /// Term ids the learner has already answered in this conversation — the «сказано вслух» mark.
  final Set<String> answeredAloud;

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
    if (old.turnIndex != widget.turnIndex || (!old.voiceReady && widget.voiceReady)) {
      WidgetsBinding.instance.addPostFrameCallback((_) => _speakLive());
    }
  }

  /// The live role line, said once per turn — never on every rebuild.
  void _speakLive() {
    final turn = _liveRoleTurn;
    if (!mounted || !widget.voiceReady || turn == null || _spokenFor == widget.turnIndex) return;
    _spokenFor = widget.turnIndex;
    widget.onSpeak(turn.text);
  }

  /// The role line THIS card answers — drawn as the live bubble above it.
  ///
  /// Only when the card at the front is the learner's turn. When the card IS the role turn, the
  /// card itself is the bubble (`situational_hear` plays the line and asks what it meant), and a
  /// second bubble above it would be the same line twice.
  PlanDialogueTurn? get _liveRoleTurn {
    final i = widget.turnIndex;
    if (i <= 0 || i >= widget.dialogue.turns.length) return null;
    if (widget.dialogue.turns[i].isRole) return null;
    final previous = widget.dialogue.turns[i - 1];

    return previous.isRole ? previous : null;
  }

  /// How many turns of the feed stand behind the current exchange.
  int get _feedEnd {
    final live = _liveRoleTurn;
    final i = widget.turnIndex < 0 ? widget.dialogue.turns.length : widget.turnIndex;

    return live == null ? i : i - 1;
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final live = _liveRoleTurn;
    final turns = widget.dialogue.turns;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _DialogueBar(
          scene: widget.dialogue.dayIndex,
          exchange: _exchangeNumber,
          exchanges: widget.dialogue.exchanges,
          rescue: widget.rescue,
        ),
        const SizedBox(height: AppSpacing.s16),
        // THE FEED — everything already spoken, quieter and smaller the further back it is. The
        // conversation goes forward: what is behind reads as context, not as a queue of cards.
        for (var i = 0; i < _feedEnd && i < turns.length; i++) ...[
          PlanDialogueBubble(
            turn: turns[i],
            past: true,
            aloud: widget.answeredAloud.contains(turns[i].termId),
            onReplay: turns[i].isRole ? () => widget.onSpeak(turns[i].text) : null,
          ),
          const SizedBox(height: 10),
        ],
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
              // «Знакомая реплика · разбор не нужен» — the line has closed «понимаю», so the screen
              // plays it and goes straight to the answer (кадр DL·06). It is a fact about the card,
              // not a compliment, so it is set as a caption and not as praise.
              note: l.planDialogueFamiliar,
            ),
          const SizedBox(height: AppSpacing.s16),
        ],
        widget.card,
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
  });

  final int scene, exchange, exchanges;
  final List<({String text, String? translation})> rescue;

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
          _RescueButton(rescue: rescue),
        ],
      ],
    );
  }
}

/// «Спасатели» — brass, never terracotta: it is a means to hand, not the step being asked for.
class _RescueButton extends StatelessWidget {
  const _RescueButton({required this.rescue});

  final List<({String text, String? translation})> rescue;

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
          builder: (_) => PlanRescueSheet(rescue: rescue),
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
  const PlanRescueSheet({super.key, required this.rescue});

  final List<({String text, String? translation})> rescue;

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
              Text(phrase.text, style: AppText.collectionNameCard.copyWith(fontSize: 17, height: 1.35)),
              if ((phrase.translation ?? '').isNotEmpty)
                Text(
                  phrase.translation!,
                  style: AppText.translation.copyWith(fontSize: 13.5, color: AppColors.secondary),
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
  });

  final PlanDialogue dialogue;

  /// Turns the learner actually took, out of the turns that were theirs to take.
  final int answeredSelf, answerable;

  /// Role lines whose meaning the learner picked, out of the ones they were asked about.
  final int heardOut, hearable;

  final int rescueUsed;
  final VoidCallback onDone;

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
          _Fact(label: l.planDialogueAnsweredSelf, value: l.planDialogueCountOf(answeredSelf, answerable)),
          if (hearable > 0)
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
