import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../data/plan/conversation/conversation_models.dart';
import '../../../data/plan/plan_models.dart';
import '../session/parts/session_bits.dart';
import '../session/parts/session_chrome.dart';
import '../session/parts/session_mic_panel.dart' show SessionTextExit;
import '../session/session_voice.dart';
import 'talk_screen.dart' show talkStripScene;

/// THE TALK'S SUMMARY (кадры 37-12, 37-12b) — what was said, what was understood, which phrases of the day
/// sounded and what did not.
///
/// EVERY NUMBER AND EVERY INFLECTION IS THE SERVER'S. The client chooses which sentence to print and
/// prints it: «Сказал сам N реплик», «Понял все вопросы» / «Понял вопросы, кроме одного», «переспросил
/// N раз», «3 из 5». Whether what did not sound comes back tomorrow is `returns_tomorrow`, not a guess
/// from the kind of day — and it is also what shapes the list (наряд CLIENT-CONV-1b):
///
/// - it comes back (a scene day, a review) — the phrases that sounded under «Фразы дня в разговоре · 3 из 5», then
///   «Не прозвучало — вернётся завтра» with the rest (37-12);
/// - it does not (the rehearsal: tomorrow is the event) — one list under «Фразы дня в разговоре · 3 из 4», GROUPED BY
///   SCENE in the order the talk walked them, and what did not sound in a scene stands under «<сцена> · повтори перед
///   приёмом» (37-12b).
class TalkSummaryView extends StatelessWidget {
  const TalkSummaryView({
    super.key,
    required this.talk,
    required this.scene,
    required this.voice,
    required this.onAgain,
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
  final SessionVoice voice;

  /// «Ещё раз» — a NEW talk (`again: true`); the server closes the old one as `replayed`.
  final VoidCallback? onAgain;
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
    final understoodLine = s.rescues > 0 ? l.planWindowJoin(understood, l.planTalkRescues(s.rescues)) : understood;

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
                  scene: talkStripScene(talk, sceneById: sceneById) ?? scene,
                  title: talk.partner.sceneNative,
                  role: talk.partner.roleNative,
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
                      ...s.returnsTomorrow ? _returning(context, s) : _byScene(context, s),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
        SessionDock(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Center(child: SessionTextExit(key: const ValueKey('talk-again'), label: l.planSessionTryAgain, brass: true, onTap: onAgain)),
              const SizedBox(height: 14),
              SessionDockButton(key: const ValueKey('talk-next'), label: l.planSessionNext, busy: busy, onTap: onNext),
            ],
          ),
        ),
      ],
    );
  }

  /// 37-12: what sounded, then «Не прозвучало — вернётся завтра». A talk of several scenes (a review) names each
  /// scene over its own phrases inside each part; a talk of one scene has nothing to name — the strip says it.
  List<Widget> _returning(BuildContext context, TalkSummary s) {
    final l = AppLocalizations.of(context);
    return [
      if (s.said.isNotEmpty) ...[
        const SizedBox(height: 32),
        SessionEyebrow(l.planTalkPhrasesOf(s.phrasesUsed, s.phrasesTotal)),
        const SizedBox(height: 14),
        ..._plates(s.said, named: talk.scenes.length > 1),
      ],
      if (s.notSaid.isNotEmpty) ...[
        SizedBox(height: s.said.isEmpty ? 32 : 16),
        SessionEyebrow(l.planTalkNotSaidTomorrow),
        const SizedBox(height: 10),
        ..._plates(s.notSaid, named: talk.scenes.length > 1),
      ],
    ];
  }

  /// 37-12b: one list, scene by scene in the order the talk walked them; in each scene what sounded under its name
  /// and what did not under «<сцена> · повтори перед приёмом» — there is no tomorrow before the event.
  List<Widget> _byScene(BuildContext context, TalkSummary s) {
    final l = AppLocalizations.of(context);
    final order = [for (final scene in talk.scenes) scene.sceneId];
    for (final p in s.phrases) {
      if (!order.contains(p.sceneId)) order.add(p.sceneId);
    }
    final groups = <Widget>[];
    for (final sceneId in order) {
      final title = _sceneTitle(sceneId);
      for (final used in [true, false]) {
        final phrases = [for (final p in s.phrases) if (p.sceneId == sceneId && p.used == used) p];
        if (phrases.isEmpty) continue;
        final label = title == null
            ? (used ? null : l.planTalkNotSaidRehearsal)
            : (used ? title : l.planTalkSceneRepeatBefore(title));
        groups.add(Column(
          key: ValueKey('talk-summary-group-$sceneId-${used ? 'said' : 'not-said'}'),
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (label != null) ...[SessionEyebrow(label), const SizedBox(height: 10)],
            ..._plates(phrases, named: false),
          ],
        ));
      }
    }
    if (groups.isEmpty) return const [];
    return [
      const SizedBox(height: 32),
      SessionEyebrow(l.planTalkPhrasesOf(s.phrasesUsed, s.phrasesTotal)),
      const SizedBox(height: 14),
      for (final (i, group) in groups.indexed) ...[if (i > 0) const SizedBox(height: 16), group],
    ];
  }

  /// The plates of one part, 8 apart; [named] — a scene's name stands over its own plates (a review's two scenes).
  List<Widget> _plates(List<TalkPhrase> phrases, {required bool named}) {
    final rows = <Widget>[];
    String? scene;
    for (final p in phrases) {
      if (named) {
        final title = _sceneTitle(p.sceneId);
        if (title != null && title != scene) {
          scene = title;
          if (rows.isNotEmpty) rows.add(const SizedBox(height: 16));
          rows.add(SessionEyebrow(title));
          rows.add(const SizedBox(height: 10));
        }
      }
      if (rows.isNotEmpty && rows.last is _PhraseRow) rows.add(const SizedBox(height: 8));
      rows.add(_PhraseRow(key: ValueKey('talk-phrase-${p.sceneId}-${p.ref}'), phrase: p, voice: voice));
    }
    return rows;
  }

  String? _sceneTitle(String sceneId) {
    for (final s in talk.scenes) {
      if (s.sceneId == sceneId) return s.titleNative;
    }
    return null;
  }
}

/// One phrase of the summary — A PLATE (кадр 37-12): said, it lies on a sage wash 15 %; not said, it stands in an
/// ink outline. The line, its translation and «прослушать» 28 are inside — the circle in the top right corner, its 44
/// touch box reaching 8 into the plate's padding so the circle sits where the frame puts it (8 from the top, 12 from
/// the edge).
class _PhraseRow extends StatelessWidget {
  const _PhraseRow({super.key, required this.phrase, required this.voice});

  final TalkPhrase phrase;
  final SessionVoice voice;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final key = 'summary-${phrase.sceneId}-${phrase.ref}';
    return ValueListenableBuilder<Object?>(
      valueListenable: voice.playing,
      builder: (_, playing, _) => Container(
        decoration: BoxDecoration(
          color: phrase.used ? AppColors.sessionSageWash : null,
          borderRadius: BorderRadius.circular(16),
          border: phrase.used ? null : Border.all(color: AppColors.markerOutline, width: 1.5),
        ),
        child: Stack(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(12, 8, 12 + 28 + 12, 8),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(phrase.textTarget, style: AppTextSession.target22),
                  Text(phrase.textNative, style: AppTextSession.body),
                ],
              ),
            ),
            Positioned(
              top: 0,
              right: 4,
              child: SessionListenButton(
                size: 28,
                brass: true,
                label: l.planWindowListen,
                playing: playing == key,
                onTap: () => unawaited(voice.play(phrase.audio, fallback: phrase.textTarget, key: key)),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
