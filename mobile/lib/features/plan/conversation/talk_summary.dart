import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../data/plan/conversation/conversation_models.dart';
import '../../../data/plan/plan_models.dart';
import '../plan_stage_text.dart' show PlanDot;
import '../session/parts/session_bits.dart';
import '../session/parts/session_chrome.dart';
import 'talk_constructions.dart';
import 'talk_screen.dart' show talkStripScene;

/// THE TALK'S SUMMARY (кадры 37-12, 37-12b; наряды FIX-3 §3, CLIENT-FIX-4 §4) — what was said, what was understood, and
/// THE CONSTRUCTIONS OF THE TALK as it left them.
///
/// EVERY NUMBER AND EVERY INFLECTION IS THE SERVER'S. The client chooses which sentence to print and prints it:
/// «Сказал сам N реплик», «Понял все вопросы» / «Понял вопросы, кроме одного», «переспросил N раз».
///
/// ONE LIST, IN THE SERVER'S ORDER: under «Конструкции в разговоре» stand the cards of `summary.phrases[]` — said ones
/// filled, with «ты сказал: …» under them, the rest in an outline with «вернётся завтра» or, on the rehearsal and on a
/// replay over a walked stage (CONV-2 п. 2), «повтори перед событием». Over them, when the talk ran out of its time
/// (`summary.ended_by_limit`), one grey line says so. Under them, «Ещё вспомнил» — the scenes' constructions said beyond
/// the targets (`summary.extra_said`), filled like the said ones; no group when there is none.
class TalkSummaryView extends StatelessWidget {
  const TalkSummaryView({
    super.key,
    required this.talk,
    required this.scene,
    required this.onNext,
    required this.onClose,
    this.busy = false,
    this.sceneById,
  });

  final PlanConversation talk;

  /// The day's scene — the strip's photo when the talk's own scene is not found in the plan.
  final PlanScene? scene;

  /// The plan's scene by id — the photo of the scene the talk ended in.
  final PlanScene? Function(String sceneId)? sceneById;
  final VoidCallback? onNext;
  final VoidCallback onClose;
  final bool busy;

  TalkSummary get _summary => talk.summary!;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final s = _summary;
    final rehearsal = talk.type == TalkType.rehearsal;
    final understood = s.understoodAll ? l.planTalkUnderstoodAll : l.planTalkUnderstoodExcept(s.notUnderstood);
    final understoodLine = s.rescues > 0 ? l.planDot(understood, l.planTalkRescues(s.rescues)) : understood;
    final ended = talk.sceneOf(talk.currentSceneId);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Expanded(
          child: SingleChildScrollView(
            padding: const EdgeInsets.only(bottom: 16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Padding(
                  padding: const EdgeInsets.fromLTRB(kSessionGutter, 4, kSessionGutter, 0),
                  child: Align(alignment: Alignment.centerLeft, child: SessionCloseButton(onTap: onClose, label: l.planSessionClose)),
                ),
                SessionSceneStrip(
                  scene: talkStripScene(talk, sceneId: talk.currentSceneId, sceneById: sceneById) ?? scene,
                  title: ended?.titleNative ?? talk.partner.sceneNative,
                  role: ended?.roleNative ?? talk.partner.roleNative,
                ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(kSessionGutter, 24, kSessionGutter, 0),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Text(
                        rehearsal ? l.planTalkReady : l.planTalkSaidLines(s.saidCount),
                        key: const ValueKey('talk-summary-title'),
                        style: AppTextSession.stageTitle,
                      ),
                      const SizedBox(height: 14),
                      // The line of understanding carries its check (37-12, 37-12b) and reads in ink.
                      Row(
                        children: [
                          SvgPicture.asset(
                            'assets/icons/talk-check.svg',
                            key: const ValueKey('talk-summary-understood-check'),
                            width: 20,
                            height: 20,
                            colorFilter: const ColorFilter.mode(AppColors.secondary, BlendMode.srcIn),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Text(understoodLine, key: const ValueKey('talk-summary-understood'), style: AppTextSession.text15),
                          ),
                        ],
                      ),
                      ..._constructions(l, s),
                      ..._extra(l, s),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
        SessionDock(
          child: SessionDockButton(key: const ValueKey('talk-next'), label: l.planSessionNext, busy: busy, onTap: onNext),
        ),
      ],
    );
  }

  /// THE CONSTRUCTIONS OF THE TALK (кадры 37-12, 37-12b) — one list in the server's order, 8 apart. The rehearsal has
  /// no tomorrow before the event, and a replay returns nothing: both read `summary.returns_tomorrow` false and their
  /// unsaid cards say «повтори перед событием». A talk the time ran out on says so over the cards — and promises a
  /// return only where the server gives one.
  List<Widget> _constructions(AppLocalizations l, TalkSummary s) {
    if (s.phrases.isEmpty) return const [];
    final notSaid = s.returnsTomorrow ? l.planWindowSheetReturnsTomorrow : l.planTalkRepeatBefore;
    return [
      const SizedBox(height: 32),
      SessionEyebrow(l.planTalkConstructions),
      if (s.endedByLimit) ...[
        const SizedBox(height: 8),
        Text(
          s.returnsTomorrow ? l.planTalkEndedByTime : l.planTalkEndedByTimeOnly,
          key: const ValueKey('talk-summary-by-time'),
          style: AppTextSession.meta,
        ),
      ],
      const SizedBox(height: 14),
      for (final (i, t) in s.phrases.indexed) ...[
        if (i > 0) const SizedBox(height: 8),
        TalkConstructionCard(target: t, note: t.said ? l.planTalkYouSaid(t.saidWith(t.valueTarget)) : notSaid),
      ],
    ];
  }

  /// «ЕЩЁ ВСПОМНИЛ» (37-12, 37-12b) — the plates of said targets, sage with their check and «ты сказал: …», for the
  /// constructions the learner said beyond the targets; nothing to show — no group.
  List<Widget> _extra(AppLocalizations l, TalkSummary s) {
    if (s.extraSaid.isEmpty) return const [];
    return [
      const SizedBox(height: 32),
      SessionEyebrow(l.planTalkExtraSaid, key: const ValueKey('talk-summary-extra')),
      const SizedBox(height: 14),
      for (final (i, t) in s.extraSaid.indexed) ...[
        if (i > 0) const SizedBox(height: 8),
        TalkConstructionCard(target: t, note: l.planTalkYouSaid(t.saidWith(t.valueTarget)), checkSize: 16),
      ],
    ];
  }
}
