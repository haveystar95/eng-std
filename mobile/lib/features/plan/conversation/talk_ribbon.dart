import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/foundation.dart' show kDebugMode, visibleForTesting;
import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../data/local/cached_image_provider.dart';
import '../../../data/plan/conversation/conversation_models.dart';
import '../../../data/plan/plan_models.dart' show PlanImage;
import '../plan_stage_text.dart' show PlanDot;
import '../session/parts/session_bits.dart';
import '../session/parts/session_bubbles.dart';
import '../session/parts/session_mic_panel.dart' show SessionDebugHeardField, SessionTextExit;
import '../session/session_mic.dart';
import 'talk_constructions.dart' show TalkConstructionRow;

/// THE TALK RIBBON — the component of кадры 37-6…37-8, and the one «Ответь своими словами» stands on
/// (35-2, «Лента: пузыри и микрофон — компонент „Лента разговора" 37-6…37-8»).
///
/// The partner's text is OPEN from the moment the line sounds, in the talk as in the trainer (правка прохода
/// 21.09, наряд CLIENT-CONV-1b; SESSION-DES-4) — only «Без подсказок» keeps the talk's lines closed, each until the
/// learner taps its «текст» (наряд CLIENT-CONV-1c §5, приёмка 22.09). What differs between the two screens is the
/// caller's to state: the trainer has «Пропустить», the talk «Не понял».

/// The talk's plates on the ribbon — the canvas shadow `0 2px 8px`, ink at 6 % (37-6…37-9, 35-2).
const List<BoxShadow> kTalkPlateShadow = [BoxShadow(color: AppColors.faintInk, blurRadius: 8, offset: Offset(0, 2))];

/// THE WAVE ALONG THE PLATE'S BOTTOM EDGE while the line sounds (37-6 «врач говорит», SESSION-DES-4): eighteen brass
/// bars 3 wide, 3 apart, standing on one line, 14 at the tallest — 10 under the words.
const List<double> _edgeBars = [4, 8, 12, 14, 10, 6, 12, 8, 4, 10, 14, 8, 4, 10, 6, 12, 8, 4];

/// THE PARTNER'S LINE (37-6…37-11, 35-2) — a light plate on the left, and everything that belongs to the line
/// stands INSIDE it (the architect's revision of 21.09, «по кадру»). Its states:
///
/// - open — the words and their translation, «прослушать» 28 in the top right corner, and nothing else;
/// - under «Без подсказок» the talk's lines stay closed until the learner opens one: the pair «прослушать» 28 and the
///   «текст» chip in one row (наряд CLIENT-CONV-1c §5: «пара „прослушать 28 + чип текст" как в (18)»); a tap on the
///   chip opens THIS line, which then stands open like any other;
/// - either way, WHILE THE LINE SOUNDS a brass wave runs along the plate's bottom edge — from the moment the answer's
///   plate rises with the role's voice ([speaking]) and on every replay from «прослушать» ([playing]); when the line
///   ends the wave goes and «прослушать» stays (SESSION-DES-4, «Волна»);
/// - «прервано» stands over the words when the learner cut the line off (37-9).
///
/// Every circle and chip is 28 on the screen and 44 under the finger: the plate trims its padding by the 8 the touch box
/// adds, so it keeps the frame's size (12 + 28 + 12) and the circle stands where the frame puts it.
class TalkPartnerBubble extends StatelessWidget {
  const TalkPartnerBubble({
    super.key,
    required this.text,
    required this.translation,
    required this.open,
    required this.playing,
    required this.onListen,
    this.onOpenText,
    this.interrupted = false,
    this.speaking = false,
  });

  final String text;
  final String? translation;

  /// The words are shown.
  final bool open;

  /// The line's sound is playing — the role's first saying or a replay from «прослушать».
  final bool playing;
  final VoidCallback? onListen;

  /// «текст» on a closed line — opens this line's words; null — the line has no chip (it is open, or may not be opened).
  final VoidCallback? onOpenText;

  /// The learner cut this line off (кадр 37-9) — it stays in the ribbon with «прервано».
  final bool interrupted;

  /// The role is saying this line now, its first time — the plate rose with the answer, and the wave runs from that
  /// moment, not from the first byte of a file still on its way (наряд CLIENT-CONV-1c §5).
  final bool speaking;

  /// The line sounds — the wave runs along the plate's bottom edge.
  bool get _sounding => speaking || playing;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    // The circle stays «прослушать» while the line sounds — the wave along the plate's edge says it sounds (37-6).
    final listen = SessionListenButton(
      key: const ValueKey('talk-listen'),
      size: 28,
      brass: true,
      label: l.planWindowListen,
      onTap: onListen,
    );
    final cut = interrupted ? Text(l.planTalkInterrupted, key: const ValueKey('talk-interrupted'), style: AppTextSession.meta) : null;
    return SessionPartnerRow(bubble: open ? _openPlate(listen, cut) : _closedPlate(l, listen, cut));
  }

  /// The wave along the bottom edge, 10 under what stands above it.
  Widget get _edgeWave => Padding(
    padding: const EdgeInsets.only(top: 10),
    child: SessionWave(key: const ValueKey('talk-line-wave'), heights: _edgeBars, playing: _sounding, bottomAligned: true),
  );

  /// 37-6…37-11, 35-2: the words, and the circle in the top right corner — its touch box stands 8 into the padding;
  /// while the line sounds, the wave under the words.
  Widget _openPlate(Widget listen, Widget? cut) => SessionBubble(
    own: false,
    padding: EdgeInsets.zero,
    shadow: kTalkPlateShadow,
    child: Stack(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(14, 12, 14 + 28 + 10, 12),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              ?cut,
              Text(text, style: SessionBubble.lineStyle(own: false)),
              if (translation case final t? when t.trim().isNotEmpty) ...[
                const SizedBox(height: 2),
                Text(t, style: SessionBubble.translationStyle(own: false)),
              ],
              if (_sounding) _edgeWave,
            ],
          ),
        ),
        Positioned(top: 4, right: 6, child: listen),
      ],
    ),
  );

  /// «Без подсказок» (37-7 «Примечание»; наряд CLIENT-CONV-1c §5): «прослушать» 28 and the «текст» chip in one row;
  /// «прервано» over them; the wave under them while the line sounds.
  Widget _closedPlate(AppLocalizations l, Widget listen, Widget? cut) {
    final openText = onOpenText;
    final row = Row(
      key: const ValueKey('talk-line-actions'),
      mainAxisSize: MainAxisSize.min,
      children: [
        listen,
        if (openText != null) ...[
          const SizedBox(width: 2),
          TalkTextChip(key: const ValueKey('talk-open-text'), label: l.planTalkOpenText, onTap: openText),
        ],
      ],
    );
    final sounding = _sounding;
    return SessionBubble(
      own: false,
      padding: EdgeInsets.fromLTRB(6, cut == null ? 4 : 12, openText == null ? 6 : 14, sounding ? 12 : 4),
      shadow: kTalkPlateShadow,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (cut != null) ...[
            Padding(padding: const EdgeInsets.only(left: 8), child: cut),
            // The frame sets the row right under «прервано»: the 44 touch box reaches 8 up over its line, and the
            // plate is measured by the 36 below it.
            Align(alignment: Alignment.bottomLeft, widthFactor: 1, heightFactor: 36 / 44, child: row),
          ] else
            row,
          if (sounding) Padding(padding: const EdgeInsets.only(left: 8, right: 8), child: _edgeWave),
        ],
      ),
    );
  }
}

/// THE «ТЕКСТ» CHIP (37-6, 37-8, 37-9; returned by наряд CLIENT-CONV-1c §5, приёмка 22.09) — an ink outline 1,5 at
/// 22 %, 28 high with corners 14, 13 in the secondary ink; a 44 touch target around it.
class TalkTextChip extends StatelessWidget {
  const TalkTextChip({super.key, required this.label, required this.onTap});

  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Semantics(
    button: true,
    child: GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: onTap,
      child: SizedBox(
        height: 44,
        child: Center(
          child: Container(
            height: 28,
            padding: const EdgeInsets.symmetric(horizontal: 12),
            alignment: Alignment.center,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: AppColors.markerOutline, width: 1.5),
            ),
            child: Text(label, style: AppTextSession.meta.copyWith(color: AppColors.secondary)),
          ),
        ),
      ),
    ),
  );
}

/// A DOCK PLATE 44 (37-7, 35-2) — «Не понял» / «Пропустить» in an ink outline at 22 %, «Подсказать» in brass:
/// corners 22, 15 on 14 at the sides. A plate, not a word: it is one of the three things the thumb reaches for.
class TalkPill extends StatelessWidget {
  const TalkPill({super.key, required this.label, required this.onTap, this.brass = false});

  final String label;
  final VoidCallback onTap;
  final bool brass;

  @override
  Widget build(BuildContext context) => Semantics(
    button: true,
    child: GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: onTap,
      child: Container(
        height: 44,
        padding: const EdgeInsets.symmetric(horizontal: 14),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(22),
          border: Border.all(color: brass ? AppColors.brassInk : AppColors.markerOutline, width: 1.5),
        ),
        // The plate hugs its word; a longer word in another language shrinks inside it rather than running into the
        // microphone.
        child: Center(
          widthFactor: 1,
          child: FittedBox(
            fit: BoxFit.scaleDown,
            child: Text(
              label,
              maxLines: 1,
              style: AppTextSession.text15.copyWith(
                color: brass ? AppColors.brassInk : AppColors.ink,
                fontWeight: brass ? FontWeight.w500 : FontWeight.w400,
              ),
            ),
          ),
        ),
      ),
    ),
  );
}

/// THE LEARNER'S LINE (37-8) — an ink bubble on the right holding ONLY what was recognised: no
/// translation under it and no verdict on it. The phrases of the day the SERVER heard in it are
/// underlined in sage ([marks], `[start, end)` in characters of [text]).
class TalkOwnBubble extends StatelessWidget {
  const TalkOwnBubble({super.key, this.text, this.marks = const [], this.child});

  final String? text;

  /// Ranges of [text] the server matched to phrases of the plan.
  final List<({int start, int end})> marks;

  /// Replaces the line — the live line while a recording is on.
  final Widget? child;

  @override
  Widget build(BuildContext context) => SessionOwnRow(
    bubble: SessionBubble(
      own: true,
      child: child ?? TalkSageUnderline(text: text ?? '', marks: marks, style: SessionBubble.lineStyle(own: true)),
    ),
  );
}

/// A LINE WITH PHRASES OF THE DAY UNDER A THIN SAGE RULE (37-8, «фразы дня видны тонкой линией
/// шалфея»). Ranges outside the text, or crossing each other, are ignored — a mark the client cannot
/// place is not drawn somewhere else.
class TalkSageUnderline extends StatelessWidget {
  const TalkSageUnderline({super.key, required this.text, required this.marks, required this.style});

  final String text;
  final List<({int start, int end})> marks;
  final TextStyle style;

  @override
  Widget build(BuildContext context) {
    final valid = [
      for (final m in marks)
        if (m.start >= 0 && m.end <= text.length && m.start < m.end) m,
    ]..sort((a, b) => a.start.compareTo(b.start));
    final underlined = style.copyWith(
      decoration: TextDecoration.underline,
      decorationColor: AppColors.sessionSageOnInk,
      decorationThickness: 1.5,
    );
    final spans = <InlineSpan>[];
    var at = 0;
    for (final m in valid) {
      if (m.start < at) continue;
      if (m.start > at) spans.add(TextSpan(text: text.substring(at, m.start), style: style));
      spans.add(TextSpan(
        text: text.substring(m.start, m.end),
        style: underlined,
      ));
      at = m.end;
    }
    if (at < text.length) spans.add(TextSpan(text: text.substring(at), style: style));

    return Text.rich(TextSpan(children: spans), key: const ValueKey('talk-own-line'));
  }
}

/// «ВРАЧ ДУМАЕТ» (37-8) — three dots where the answer will stand. The talk has no «thinking» state on
/// the wire and needs none: the dots run while the request is in flight.
class TalkThinking extends StatefulWidget {
  const TalkThinking({super.key});

  @override
  State<TalkThinking> createState() => _TalkThinkingState();
}

class _TalkThinkingState extends State<TalkThinking> with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(vsync: this, duration: const Duration(milliseconds: 1200));

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (MediaQuery.maybeDisableAnimationsOf(context) ?? false) {
      _c.stop();
    } else if (!_c.isAnimating) {
      unawaited(_c.repeat());
    }
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => SessionPartnerRow(
    bubble: SessionBubble(
      own: false,
      shadow: kTalkPlateShadow,
      child: AnimatedBuilder(
        key: const ValueKey('talk-thinking'),
        animation: _c,
        builder: (_, _) => SizedBox(
          height: 8,
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              for (var i = 0; i < 3; i++) ...[
                if (i > 0) const SizedBox(width: 6),
                Opacity(
                  opacity: _c.isAnimating ? 0.35 + 0.65 * (0.5 - 0.5 * math.cos(2 * math.pi * ((_c.value + i * 0.18) % 1))) : 0.6,
                  child: const SizedBox(
                    width: 8,
                    height: 8,
                    child: DecoratedBox(decoration: BoxDecoration(shape: BoxShape.circle, color: AppColors.tertiary)),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    ),
  );
}

/// THE HINT PLATE OF THE TALK (кадры 37-7, 37-7e, 37-8e, 39-1b; наряд CLIENT-FIX-4 §3) — a LIGHT plate under the role's
/// last line, on the left where the role's words stand: the lesson's whole sentence in the learner's language, 15/20 in
/// ink — «У меня есть боль в плече.», no «Скажи, что …» around it. [line] — the target's line in the target language,
/// Literata 17/23 in ink, 4 under the sentence when [open]: after an «almost» it is open at once (37-8e), otherwise a
/// tap opens it and a second tap folds it (37-7e). Paper, corners 16, 10 / 14 inside, the ribbon's shadow — the same
/// plate as the card's hint of 35-2 ([TalkHintChip]), never an ink bubble: ink on the right is only what the learner
/// said.
class TalkHintPlate extends StatelessWidget {
  const TalkHintPlate({super.key, required this.sentence, this.line, this.open = false, this.onTap});

  final String sentence;
  final String? line;
  final bool open;

  /// Opens or folds [line]; null — the plate has no second line to give.
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final second = open ? line : null;
    final plate = Container(
      key: const ValueKey('talk-hint-plate'),
      constraints: const BoxConstraints(maxWidth: kSessionBubbleMax),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      decoration: BoxDecoration(color: AppColors.paper, borderRadius: BorderRadius.circular(16), boxShadow: kTalkPlateShadow),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(sentence, key: const ValueKey('talk-hint-sentence'), style: AppTextSession.text15),
          if (second != null) ...[
            const SizedBox(height: 4),
            Text(second, key: const ValueKey('talk-hint-line'), style: AppTextSession.phrase17.copyWith(color: AppColors.ink)),
          ],
        ],
      ),
    );
    return SessionAppear(
      child: Align(
        alignment: Alignment.centerLeft,
        child: onTap == null
            ? plate
            : Semantics(button: true, child: GestureDetector(behavior: HitTestBehavior.opaque, onTap: onTap, child: plate)),
      ),
    );
  }
}

/// THE JUDGE'S LINE AFTER AN «ALMOST» (кадр 37-8e) — «Почти — скажи целиком», 15/20 in ink at 500, under the learner's
/// own line: the move was one word off a construction, which still waits. Only a line of the server's judge, never the
/// phone's own reading of what was said.
class TalkJudgeLine extends StatelessWidget {
  const TalkJudgeLine({super.key});

  @override
  Widget build(BuildContext context) => Text(
    AppLocalizations.of(context).planTalkAlmostJudge,
    key: const ValueKey('talk-judge-almost'),
    style: AppTextSession.text15.copyWith(fontWeight: FontWeight.w500),
  );
}

/// «Сцена 2 · Приём у врача · врач» — a scene of a talk that walks several (the divider 39-1b, the groups of the entry
/// 37-5b): its number, its name and its role in the nominative with the first letter lowered, as the strip writes it.
/// A part the server did not name is left out, never printed empty.
String talkSceneLabel(AppLocalizations l, {int? number, required String title, required String role}) {
  final parts = [if (number != null) l.planTalkSceneNumber(number), if (title.isNotEmpty) title, if (role.isNotEmpty) role];
  if (parts.isEmpty) return '';
  return parts.skip(1).fold(parts.first, l.planDotPlain);
}

/// THE BOUNDARY OF TWO SCENES IN THE RIBBON (кадр 39-1b) — «СЦЕНА 2 · ПРИЁМ У ВРАЧА · ВРАЧ» in the eyebrow's grey caps
/// (the group heading of 23-0d) and a 1 px line after it to the edge: the transition card folds into it on
/// «Продолжить», and every earlier boundary of a talk read again stands as one.
class TalkSceneDivider extends StatelessWidget {
  const TalkSceneDivider({super.key, required this.label});

  final String label;

  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (_, box) => Row(
      children: [
        ConstrainedBox(
          // A long scene name wraps rather than pushing the line out: 40 is always left for the rule.
          constraints: BoxConstraints(maxWidth: (box.maxWidth - 52).clamp(0, double.infinity)),
          child: Text(label.toUpperCase(), style: AppTextSession.eyebrow.copyWith(height: 14 / 11)),
        ),
        const SizedBox(width: 12),
        const Expanded(child: SizedBox(height: 1, child: ColoredBox(color: AppColors.markerOutline))),
      ],
    ),
  );
}

/// THE TRANSITION CARD (кадр 39-1; наряд CLIENT-FIX-4 §1) — the next scene, in the ribbon under the goodbye of the one
/// just closed: the scene's photo 104 high under a scrim with «СЦЕНА 2 ИЗ 2», its name in Literata 26 and its role 13,
/// all in paper; under it the scene's constructions as the rows of 37-5 (the frame with its window, the lesson's example
/// grey) and the one dark button, «Продолжить». The ribbon's whole width, corners 22, the ribbon's two shadows. It rides
/// in from below — a 24 shift and a fade, 220 ms.
class TalkSceneCard extends StatelessWidget {
  const TalkSceneCard({
    super.key,
    required this.number,
    required this.total,
    required this.title,
    required this.role,
    required this.targets,
    required this.onContinue,
    this.photo,
  });

  /// «Сцена N из M».
  final int number;
  final int total;
  final String title;

  /// The next role in the nominative, as the strip writes it («врач»); empty — no line.
  final String role;
  final List<TalkTarget> targets;
  final VoidCallback onContinue;
  final PlanImage? photo;

  static const List<BoxShadow> _shadow = [
    BoxShadow(color: AppColors.faintInk, blurRadius: 8, offset: Offset(0, 2)),
    BoxShadow(color: AppColors.sessionSheetShadow, blurRadius: 24, offset: Offset(0, 10)),
  ];

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final dpr = MediaQuery.maybeDevicePixelRatioOf(context) ?? 2;
    final image = photo;
    const onPhoto = AppColors.paper;
    final card = Container(
      key: const ValueKey('talk-scene-card'),
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(color: AppColors.paper, borderRadius: BorderRadius.circular(22), boxShadow: _shadow),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SizedBox(
            height: 104,
            child: Stack(
              fit: StackFit.expand,
              children: [
                DecoratedBox(
                  decoration: BoxDecoration(
                    color: image == null ? AppColors.photoPlaceholder : (AppColors.wireTone(image.tone) ?? AppColors.photoPlaceholder),
                    image: image == null ? null : DecorationImage(image: CachedNetworkImage(image.urlFor(342, dpr)), fit: BoxFit.cover),
                  ),
                ),
                const DecoratedBox(
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.topCenter,
                      end: Alignment.bottomCenter,
                      colors: [AppColors.talkSceneScrimTop, AppColors.talkSceneScrimBottom],
                    ),
                  ),
                ),
                Positioned(
                  left: 20,
                  right: 20,
                  bottom: 16,
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        l.planTalkSceneOf(number, total).toUpperCase(),
                        key: const ValueKey('talk-scene-card-number'),
                        style: AppTextSession.eyebrow.copyWith(color: onPhoto, height: 14 / 11),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        title,
                        key: const ValueKey('talk-scene-card-title'),
                        maxLines: 2,
                        style: AppTextSession.stageTitle.copyWith(color: onPhoto, letterSpacing: 0),
                      ),
                      if (role.isNotEmpty) ...[
                        const SizedBox(height: 4),
                        Text(role, key: const ValueKey('talk-scene-card-role'), style: AppTextSession.sceneLine.copyWith(color: onPhoto)),
                      ],
                    ],
                  ),
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                for (final (i, t) in targets.indexed) ...[
                  if (i > 0) const SizedBox(height: 8),
                  TalkConstructionRow(target: t),
                ],
                if (targets.isNotEmpty) const SizedBox(height: 14),
                DockButton(key: const ValueKey('talk-scene-continue'), label: l.planTalkSceneContinue, onTap: onContinue),
              ],
            ),
          ),
        ],
      ),
    );
    if (MediaQuery.maybeDisableAnimationsOf(context) ?? false) return card;
    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0, end: 1),
      duration: AppMotion.talkSceneCardIn,
      curve: AppMotion.sessionEaseOut,
      builder: (_, t, c) => Opacity(
        opacity: t,
        child: Transform.translate(offset: Offset(0, AppMotion.talkSceneCardShift * (1 - t)), child: c),
      ),
      child: card,
    );
  }
}

/// THE END OF THE TALK (кадр 37-11) — a sheet over the dock once the role's goodbye has been said: «Разговор окончен» in
/// Literata 26, the talk's minutes 15 grey under it, and 24 below the one dark button, «Итог». The ground's colour,
/// corners 22 on top, padding 24 and a soft shadow upwards; the ribbon stays visible above it.
class TalkEndSheet extends StatelessWidget {
  const TalkEndSheet({super.key, required this.minutes, required this.onSummary});

  /// The talk's minutes as the server counted them; null — no line.
  final int? minutes;
  final VoidCallback onSummary;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final bottom = MediaQuery.paddingOf(context).bottom;
    return Container(
      key: const ValueKey('talk-end-sheet'),
      padding: EdgeInsets.fromLTRB(24, 24, 24, 24 + bottom),
      decoration: const BoxDecoration(
        color: AppColors.ground,
        borderRadius: BorderRadius.vertical(top: Radius.circular(22)),
        boxShadow: [BoxShadow(color: AppColors.talkEndSheetShadow, blurRadius: 40, offset: Offset(0, -12))],
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(l.planTalkEnded, key: const ValueKey('talk-end-title'), style: AppTextSession.stageTitle.copyWith(letterSpacing: 0)),
          if (minutes case final m?) ...[
            const SizedBox(height: 4),
            Text(l.planMinutesCount(m), key: const ValueKey('talk-end-minutes'), style: AppTextSession.body),
          ],
          const SizedBox(height: 24),
          DockButton(key: const ValueKey('talk-summary-action'), label: l.planTalkSummaryAction, onTap: onSummary),
        ],
      ),
    );
  }
}

/// THE HINT CHIP OF A CARD (35-2) — a LIGHT plate with the intention on the learner's language. Never an
/// ink bubble: an ink bubble on the right is only ever what the learner actually said (37-7,
/// «Тёмный пузырь»). By the frame: paper, corners 16, 10 / 14 inside, 15/20 in ink, and the ribbon's own shadow
/// `0 2px 8px` at 6 % — not the sheet's (отчёт client-conv-1a §1.7, наряд CLIENT-CONV-1b). The talk's own hint stands
/// under the role's line ([TalkHintPlate]).
class TalkHintChip extends StatelessWidget {
  const TalkHintChip({super.key, required this.text, this.alignEnd = false, this.stretch = false});

  final String text;

  /// «Спроси сам» (35-2) stands the plate on the right, where the learner's own line will go.
  final bool alignEnd;

  /// The plate runs the ribbon's width — under the judge's refusal (35-2d), where it is read as the line under it.
  final bool stretch;

  @override
  Widget build(BuildContext context) {
    final plate = Container(
      key: const ValueKey('talk-hint-chip'),
      constraints: stretch ? null : const BoxConstraints(maxWidth: kSessionBubbleMax),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      decoration: BoxDecoration(
        color: AppColors.paper,
        borderRadius: BorderRadius.circular(16),
        boxShadow: kTalkPlateShadow,
      ),
      child: Text(text, style: AppTextSession.text15),
    );
    return SessionAppear(
      child: stretch ? plate : Align(alignment: alignEnd ? Alignment.centerRight : Alignment.center, child: plate),
    );
  }
}

/// What the talk's microphone is doing — the screen's own reading, drawn by [TalkMicButton].
enum TalkMicLook {
  /// The role is speaking: the button is dimmed and a tap on it CUTS THE LINE OFF (37-6, 37-9).
  dimmed,

  /// The learner's move — «твоя очередь»: a 2 px brass ring around the 88 box pulses 1.6 s (37-7,
  /// and 37-10 after «не расслышал»).
  waiting,

  /// Recording — «слушаю»: a sage band 8 at 30 % around the 88 box pulses 1.2 s; a second tap stops the
  /// recording (37-7 «слушаю», 37-9).
  listening,

  /// Heard and taken — sage with a check (35-2 «сказал · зачтено»). The talk itself never stands
  /// here: its move goes to the server the moment the recording closes.
  heard,

  /// The move is with the server — nothing to tap (37-8).
  busy,
}

/// THE TALK'S MICROPHONE — the frame's 88 box with the button 72 inside (37-6…37-10). ONE rule: it opens
/// only on a tap — no state of the talk starts a recording by itself — and a second tap stops it.
///
/// The button always shows the microphone (37-9: «Вычтено: … квадрат „стоп"»); what tells the states apart
/// is the ring around the 88 box: brass 2 while the move is the learner's, a sage band 8 while it listens.
class TalkMicButton extends StatefulWidget {
  const TalkMicButton({super.key, required this.look, required this.onTap, required this.label});

  final TalkMicLook look;
  final VoidCallback? onTap;
  final String label;

  @override
  State<TalkMicButton> createState() => _TalkMicButtonState();
}

class _TalkMicButtonState extends State<TalkMicButton> with TickerProviderStateMixin {
  late final AnimationController _brass = AnimationController(vsync: this, duration: AppMotion.sessionRolePulse);
  late final AnimationController _sage = AnimationController(vsync: this, duration: AppMotion.sessionListenPulse);

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _sync();
  }

  @override
  void didUpdateWidget(TalkMicButton old) {
    super.didUpdateWidget(old);
    _sync();
  }

  void _sync() {
    final reduce = MediaQuery.maybeDisableAnimationsOf(context) ?? false;
    void run(AnimationController c, bool on) {
      if (on && !reduce && !c.isAnimating) {
        unawaited(c.repeat());
      } else if ((!on || reduce) && c.isAnimating) {
        c.stop();
        c.value = 0;
      }
    }

    run(_brass, widget.look == TalkMicLook.waiting);
    run(_sage, widget.look == TalkMicLook.listening);
  }

  @override
  void dispose() {
    _brass.dispose();
    _sage.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final look = widget.look;
    final ring = switch (look) {
      // om-ring: the 2 px brass line grows to 8 and fades; at rest — and in a frame — it is the line itself.
      TalkMicLook.waiting => AnimatedBuilder(
        animation: _brass,
        builder: (_, _) {
          final t = _brass.isAnimating ? Curves.easeOut.transform(_brass.value) : 0.0;
          return TalkMicRing(color: AppColors.brassInk.withValues(alpha: 1 - t), width: 2 + 6 * t);
        },
      ),
      // om-pulse: the sage band 8 → 12 → 8 at .30 → .14 → .30.
      TalkMicLook.listening => AnimatedBuilder(
        animation: _sage,
        builder: (_, _) {
          final t = _sage.isAnimating ? (0.5 - 0.5 * math.cos(_sage.value * 2 * math.pi)) : 0.0;
          return TalkMicRing(
            color: Color.lerp(AppColors.sessionListenRing, AppColors.sessionListenRing.withValues(alpha: .14), t)!,
            width: 8 + 4 * t,
          );
        },
      ),
      _ => null,
    };

    return Semantics(
      button: true,
      label: widget.label,
      child: GestureDetector(
        key: const ValueKey('talk-mic'),
        behavior: HitTestBehavior.opaque,
        onTap: widget.onTap == null
            ? null
            : () {
                AppHaptics.light();
                widget.onTap!();
              },
        child: SizedBox(
          width: 88,
          height: 88,
          child: Stack(
            alignment: Alignment.center,
            clipBehavior: Clip.none,
            children: [
              ?ring,
              Opacity(
                opacity: look == TalkMicLook.dimmed || look == TalkMicLook.busy ? 0.35 : 1,
                child: Container(
                  width: 72,
                  height: 72,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: look == TalkMicLook.heard ? AppColors.verdictKnown : AppColors.windowInk,
                    boxShadow: const [BoxShadow(color: AppColors.sessionMicShadow, blurRadius: 24, offset: Offset(0, 8))],
                  ),
                  child: Icon(
                    look == TalkMicLook.heard ? LucideIcons.check : LucideIcons.mic,
                    key: const ValueKey('talk-mic-glyph'),
                    size: 30,
                    color: AppColors.paper,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// THE RING AROUND THE MICROPHONE'S 88 BOX — the frame's `box-shadow: 0 0 0 [width]`, drawn OUTSIDE the box:
/// brass 2 for «твоя очередь», a sage band 8 at 30 % for «слушаю».
class TalkMicRing extends StatelessWidget {
  const TalkMicRing({super.key = const ValueKey('talk-mic-ring'), required this.color, required this.width});

  final Color color;
  final double width;

  @override
  Widget build(BuildContext context) => CustomPaint(size: const Size.square(88), painter: _RingPainter(color: color, width: width));
}

class _RingPainter extends CustomPainter {
  _RingPainter({required this.color, required this.width});

  final Color color;
  final double width;

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = color
      ..style = PaintingStyle.stroke
      ..strokeWidth = width;
    canvas.drawCircle(size.center(Offset.zero), size.shortestSide / 2 + width / 2, paint);
  }

  @override
  bool shouldRepaint(_RingPainter old) => old.color != color || old.width != width;
}

/// THE TALK'S DOCK (37-6…37-10): the constructions on top, the chip, the live line, the caption, and the row of
/// three — the left exit, the microphone 72 in the middle, the right exit.
///
/// The two exits are the caller's: in the talk «Не понял» stands on the left in EVERY state of the
/// learner's move, and «Подсказать» on the right only under «Без подсказок», until the plate is up (37-7c); in 35-2
/// they are «Пропустить» and «Подсказать».
class TalkDock extends StatelessWidget {
  const TalkDock({
    super.key,
    required this.mic,
    this.debugMic,
    this.constructions,
    this.chip,
    this.caption,
    this.subCaption,
    this.liveLine,
    this.left,
    this.right,
    this.notice,
  });

  final Widget mic;

  /// The microphone behind the button — the debug build puts its «what was heard» field under the
  /// dock, and the simulator has no other way to say anything (наряд SESSION-1b).
  final SessionMic? debugMic;

  /// THE CONSTRUCTIONS OF THE TALK (наряды FIX-3 §3, CLIENT-FIX-4 §2) — the plates still to say, part of the dock while
  /// the ribbon runs; the row brings the 14 under itself and folds to nothing once its last plate has left (37-11b), so
  /// the ribbon gets that room back.
  final Widget? constructions;

  /// The hint chip of a card (35-2), over the caption; the talk's own hint stands in the ribbon ([TalkHintPlate]).
  final Widget? chip;

  /// «тап — говорить» / «слушай» / «говори, я слушаю».
  final String? caption;

  /// Under the microphone: «тишина — конец» while it listens, «не расслышал — скажи ещё раз» after an empty
  /// recording (37-7, 37-10).
  final String? subCaption;

  /// The live line of the recording, over the caption.
  final Widget? liveLine;

  final Widget? left;
  final Widget? right;

  /// A failure plate over the dock (37-10) — it replaces nothing, it stands above.
  final Widget? notice;

  /// The debug build's «что услышал» field under the dock. A frame shot for the architect turns it off: the phone's
  /// release build has none, and the frame must show the dock the learner sees (наряд CLIENT-FIX-4, снимки).
  @visibleForTesting
  static bool debugHeardField = true;

  @override
  Widget build(BuildContext context) => Column(
    mainAxisSize: MainAxisSize.min,
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      if (notice case final n?) ...[n, const SizedBox(height: 14)],
      ?constructions,
      if (chip case final c?) ...[c, const SizedBox(height: 14)],
      if (liveLine case final line?) ...[line, const SizedBox(height: 14)],
      // The frame's order (37-7 «слушаю», 37-10): the caption over the button, the button's 88 box, and what
      // closes or failed the move — «тишина — конец», «не расслышал» — under it; 14 between them, so the ring the
      // button wears outside its box (up to 8) never reaches the words.
      if (caption case final text?) ...[
        Text(text, key: const ValueKey('talk-caption'), textAlign: TextAlign.center, style: AppTextSession.meta),
        const SizedBox(height: 14),
      ],
      SizedBox(
        height: 88,
        // THE MICROPHONE IS LAST, SO IT IS HIT FIRST. The exits sit at the sides and their tap areas
        // (44 high, opaque) reach towards the middle; on a 375 phone «Подсказать» already overlaps
        // the button's centre, and a tap meant for the microphone was swallowed by the exit behind
        // it. The row paints in this order and hit-tests in reverse: the button always wins.
        child: LayoutBuilder(
          builder: (_, box) {
            // Each side gets what the microphone's 88 box and its brass ring leave it, with a little air.
            final side = math.max(0.0, (box.maxWidth - 96) / 2);
            return Stack(
              alignment: Alignment.center,
              children: [
                if (left case final w?)
                  Align(alignment: Alignment.centerLeft, child: ConstrainedBox(constraints: BoxConstraints(maxWidth: side), child: w)),
                if (right case final w?)
                  Align(alignment: Alignment.centerRight, child: ConstrainedBox(constraints: BoxConstraints(maxWidth: side), child: w)),
                Center(child: mic),
              ],
            );
          },
        ),
      ),
      if (subCaption case final text?) ...[
        const SizedBox(height: 14),
        Text(text, key: const ValueKey('talk-sub-caption'), textAlign: TextAlign.center, style: AppTextSession.meta),
      ],
      if (kDebugMode && debugHeardField && debugMic != null) ...[
        const SizedBox(height: 10),
        SessionDebugHeardField(mic: debugMic!),
      ],
    ],
  );
}

/// A FAILURE PLATE (37-10) — one sentence and, when there is something to repeat, «Повторить». It
/// says what happened in what the learner can see: no «error», no «server», no «API».
class TalkNotice extends StatelessWidget {
  const TalkNotice({super.key, required this.text, this.onAction, this.actionLabel, this.actionKey = const ValueKey('talk-retry')});

  final String text;
  final VoidCallback? onAction;

  /// The action's words — «Повторить» unless the notice asks for something else («Разрешить»).
  final String? actionLabel;
  final Key actionKey;

  @override
  Widget build(BuildContext context) => Container(
    key: const ValueKey('talk-notice'),
    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
    decoration: BoxDecoration(color: AppColors.paper, borderRadius: BorderRadius.circular(12), boxShadow: kSessionSheetShadow),
    child: Row(
      children: [
        Expanded(child: Text(text, style: AppTextSession.meta)),
        if (onAction != null) ...[
          const SizedBox(width: 12),
          SessionTextExit(
            key: actionKey,
            label: actionLabel ?? AppLocalizations.of(context).planTabRetry,
            brass: true,
            onTap: onAction,
          ),
        ],
      ],
    ),
  );
}
