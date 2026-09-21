import 'dart:async';

import 'package:flutter/material.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../data/plan/conversation/conversation_models.dart';
import '../../../data/plan/plan_models.dart';
import '../session/parts/session_bits.dart';
import '../session/parts/session_chrome.dart';
import '../session/parts/session_mic_panel.dart' show SessionTextExit;
import '../session/session_voice.dart';

/// THE TALK'S SUMMARY (кадр 37-12) — what was said, what was understood, which phrases of the day
/// sounded and what did not.
///
/// EVERY NUMBER AND EVERY INFLECTION IS THE SERVER'S. The client chooses which sentence to print and
/// prints it: «Сказал сам N реплик», «Понял все вопросы» / «Понял вопросы, кроме одного», «переспросил
/// N раз», «3 из 5». Whether what did not sound comes back tomorrow is `returns_tomorrow`, not a guess
/// from the kind of day.
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
  });

  final PlanConversation talk;
  final PlanScene? scene;
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
                SessionSceneStrip(scene: scene),
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
                      Text(understoodLine, key: const ValueKey('talk-summary-understood'), style: AppTextSession.body),
                      if (s.said.isNotEmpty) ...[
                        const SizedBox(height: 40),
                        SessionEyebrow(l.planTalkPhrasesOf(s.phrasesUsed, s.phrasesTotal)),
                        const SizedBox(height: 14),
                        ..._group(context, s.said),
                      ],
                      if (s.notSaid.isNotEmpty) ...[
                        const SizedBox(height: 32),
                        SessionEyebrow(
                          s.returnsTomorrow ? l.planTalkNotSaidTomorrow : l.planTalkNotSaidRehearsal,
                        ),
                        const SizedBox(height: 14),
                        ..._group(context, s.notSaid),
                      ],
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

  /// The phrases of a group. A talk that walks SEVERAL scenes (the rehearsal) names each scene over
  /// its own phrases, as кадр 37-12 does; a talk of one scene has nothing to name — the strip above
  /// already says which one it is.
  List<Widget> _group(BuildContext context, List<TalkPhrase> phrases) {
    final rows = <Widget>[];
    String? scene;
    for (final p in phrases) {
      if (talk.scenes.length > 1) {
        final title = _sceneTitle(p.sceneId);
        if (title != null && title != scene) {
          scene = title;
          if (rows.isNotEmpty) rows.add(const SizedBox(height: 20));
          rows.add(Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: Text(title, style: AppTextSession.text15),
          ));
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
