import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';
import 'package:eng_std/l10n/app_localizations.dart';

import '../../data/api_client.dart';
import '../../data/models.dart' show Word;
import '../../data/languages.dart' show sttLocaleFor;
import '../../data/plan_models.dart';
import '../../data/providers.dart';
import '../../data/pronouncer.dart';
import '../training/triage_swipe.dart' show SessionSegments;
import 'plan_ui.dart';

/// «Быстрая репетиция перед событием» — кадр 1c · 15.
///
/// THREE MINUTES, and the design of the screen is entirely about that budget. One phrase at a time,
/// said out loud, and nothing to answer: no options, no typing, no verdict. The microphone is
/// offered and skipping is equally legitimate — «скажи фразу или пролистай дальше, если она уже
/// звучит сама» — because on the morning of the event the point is to hear yourself, not to be
/// tested.
///
/// It moves NOTHING. No review is written, no stage closes, no schedule changes. That is what lets
/// it be opened twice on the way to the door without costing the learner anything.
class PlanRehearsalScreen extends ConsumerStatefulWidget {
  const PlanRehearsalScreen({super.key, required this.planId, required this.targetLang});

  final String planId;

  /// The language the phrases are IN — for the voice that reads them and for the recogniser that
  /// listens. Read from the plan rather than from the profile: the account can be studying
  /// something else by the time the appointment comes round.
  final String targetLang;

  @override
  ConsumerState<PlanRehearsalScreen> createState() => _PlanRehearsalScreenState();
}

class _PlanRehearsalScreenState extends ConsumerState<PlanRehearsalScreen> {
  /// The shared line cache (наряд TTS-1): a rehearsal line the plan has a server recording for is
  /// played from it, so the morning of the event sounds like the days that led up to it.
  late final Pronouncer _pronouncer = Pronouncer(null, ref.read(lineAudioCacheProvider));
  late Future<PlanRehearsal> _rehearsal;
  int _pos = 0;

  /// Which lines the learner has already been through — the tail list at the bottom of the frame.
  final List<RehearsalLine> _behind = [];

  @override
  void initState() {
    super.initState();
    _rehearsal = ref.read(apiClientProvider).planRehearsal(widget.planId);
    unawaited(_pronouncer.warmUp(targetLang: widget.targetLang));
  }

  @override
  void dispose() {
    _pronouncer.release();
    super.dispose();
  }

  bool _listening = false;

  /// Say the sentence out loud through the same pronouncer every card in the app uses.
  ///
  /// It wants a [Word], so the line is wrapped in one. That is not ceremony: the pronouncer reads
  /// `ttsHint` and `audioUrl` off it, and a second speak path that did not would be the place a
  /// future audio override quietly fails to reach.
  Future<void> _speak(RehearsalLine line) => _pronouncer.speak(
    Word(termId: line.termId, term: line.text, translation: line.translation ?? '', type: 'phrase'),
    targetLang: widget.targetLang,
  );

  /// Listen once, and move on whatever comes back.
  ///
  /// NOTHING is graded and nothing is uploaded: the recogniser is here so the learner hears
  /// themselves say the sentence out loud before they walk in. A refused permission is not an error
  /// either — the line is simply skipped forward, exactly as the «пролистай дальше» affordance
  /// beside the button says it may be.
  Future<void> _listen(List<RehearsalLine> lines) async {
    if (_listening) {
      await ref.read(speechRecognizerProvider).stop();
      return;
    }
    AppHaptics.light();
    unawaited(_pronouncer.stop());

    final recognizer = ref.read(speechRecognizerProvider);
    if (!await recognizer.prepare()) {
      if (mounted) _next(lines);
      return;
    }

    setState(() => _listening = true);
    try {
      await recognizer.listenOnce(
        expected: [lines[_pos].text],
        localeId: sttLocaleFor(widget.targetLang),
        contextualStrings: [lines[_pos].text],
      );
    } finally {
      if (mounted) setState(() => _listening = false);
    }
    if (mounted) _next(lines);
  }

  void _next(List<RehearsalLine> lines) {
    AppHaptics.light();
    unawaited(_pronouncer.stop());
    if (_pos >= lines.length - 1) {
      Navigator.of(context).pop();
      return;
    }
    setState(() {
      _behind.add(lines[_pos]);
      _pos++;
    });
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark,
      child: Scaffold(
        backgroundColor: AppColors.paper,
        body: SafeArea(
          bottom: false,
          child: FutureBuilder<PlanRehearsal>(
            future: _rehearsal,
            builder: (context, snapshot) {
              if (snapshot.hasError) {
                return PlanNotice(
                  text: isOffline(snapshot.error)
                      ? l.planErrorOffline
                      : l.planErrorLoadFailed,
                  actionLabel: l.generationRetry,
                  onAction: () => setState(
                    () => _rehearsal = ref.read(apiClientProvider).planRehearsal(widget.planId),
                  ),
                );
              }
              final data = snapshot.data;
              if (data == null) {
                return const Center(child: CircularProgressIndicator(color: AppColors.ink));
              }
              if (data.lines.isEmpty) {
                return PlanNotice(text: l.planRehearsalEmpty);
              }

              return _Body(
                lines: data.lines,
                position: _pos,
                behind: _behind,
                listening: _listening,
                onListen: () => _listen(data.lines),
                onSpeak: _speak,
                onNext: () => _next(data.lines),
              );
            },
          ),
        ),
      ),
    );
  }
}

class _Body extends StatelessWidget {
  const _Body({
    required this.lines,
    required this.position,
    required this.behind,
    required this.listening,
    required this.onListen,
    required this.onSpeak,
    required this.onNext,
  });

  final List<RehearsalLine> lines;
  final int position;
  final List<RehearsalLine> behind;
  final bool listening;
  final VoidCallback onListen;
  final void Function(RehearsalLine line) onSpeak;
  final VoidCallback onNext;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final line = lines[position];

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(AppSpacing.screenH, 0, AppSpacing.screenH, 0),
          child: Row(
            children: [
              InkResponse(
                onTap: () => Navigator.of(context).maybePop(),
                radius: 22,
                child: const SizedBox(
                  width: AppSpacing.minTap,
                  height: AppSpacing.minTap,
                  child: Icon(LucideIcons.x, size: 20, color: AppColors.secondary),
                ),
              ),
              Expanded(child: Center(child: PlanPill(l.planRehearsalBadge))),
              SizedBox(
                width: AppSpacing.minTap,
                child: Text(
                  l.triageCounter(position + 1, lines.length),
                  textAlign: TextAlign.right,
                  style: AppTextExercise.sessionHeader,
                ),
              ),
            ],
          ),
        ),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: AppSpacing.screenH),
          child: SessionSegments(done: position, total: lines.length),
        ),
        Expanded(
          child: ListView(
            padding: const EdgeInsets.fromLTRB(
              AppSpacing.screenH,
              AppSpacing.s26,
              AppSpacing.screenH,
              AppSpacing.s16,
            ),
            children: [
              PlanLabel(l.planRehearsalSayIt, color: AppColors.tertiary, fontSize: 11),
              const SizedBox(height: AppSpacing.s16),
              // Tapping the sentence speaks it. No auto-play: the learner may already be in the
              // waiting room, and a phone that starts talking by itself is a phone in a pocket.
              GestureDetector(
                onTap: () => onSpeak(line),
                child: Text(
                  line.text,
                  style: AppText.displayTerm.copyWith(fontSize: 27, height: 1.32),
                ),
              ),
              if (line.translation != null && line.translation!.isNotEmpty) ...[
                const SizedBox(height: 10),
                Text(
                  line.translation!,
                  style: AppText.translation.copyWith(
                    fontSize: 14.5,
                    color: AppColors.secondary,
                  ),
                ),
              ],
              if (line.cue != null && line.cue!.isNotEmpty) ...[
                const SizedBox(height: 20),
                Text(
                  l.planRehearsalCue(line.role ?? l.planConversationDefaultRole, line.cue!),
                  style: AppText.translation.copyWith(
                    fontSize: 13,
                    height: 1.5,
                    color: AppColors.tertiary,
                  ),
                ),
              ],
              const SizedBox(height: 34),
              Row(
                children: [
                  // THE MICROPHONE, and it grades nothing. It listens once so the learner hears
                  // themselves say the sentence, then moves on whatever came back — «за три минуты
                  // нужно услышать себя, а не проверить память». A rehearsal that marked an answer
                  // wrong on the morning of the appointment would be the cruellest screen in the
                  // product, and it would also be wrong: this writes no review at all.
                  _MicButton(
                    listening: listening,
                    onTap: onListen,
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Text(
                      listening ? l.planRehearsalListening : l.planRehearsalHint,
                      style: AppText.translation.copyWith(
                        fontSize: 14,
                        height: 1.5,
                        color: AppColors.secondary,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 14),
              // …and the sentence can always be HEARD first. «Прочитай пример» is the other half of
              // the rehearsal, and on a phone in a waiting room it must be a deliberate tap rather
              // than something the screen starts doing by itself.
              Align(
                alignment: Alignment.centerLeft,
                child: QuietButton(
                  label: l.planRehearsalPlay,
                  icon: LucideIcons.volume2,
                  onPressed: () => onSpeak(line),
                ),
              ),
              if (behind.isNotEmpty) ...[
                const SizedBox(height: AppSpacing.s26),
                Container(
                  padding: const EdgeInsets.only(top: 14),
                  decoration: const BoxDecoration(
                    border: Border(top: BorderSide(color: AppColors.dividerFaint)),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      for (final done in behind)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 9),
                          child: Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const SizedBox(
                                width: 18,
                                child: Text(
                                  '✓',
                                  style: TextStyle(
                                    fontFamily: AppFonts.inter,
                                    fontSize: 13,
                                    color: AppColors.brassInk,
                                  ),
                                ),
                              ),
                              Expanded(
                                child: Text(
                                  done.text,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: AppText.translation.copyWith(
                                    fontSize: 14,
                                    color: AppColors.tertiary,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                    ],
                  ),
                ),
              ],
            ],
          ),
        ),
        Padding(
          padding: EdgeInsets.fromLTRB(
            AppSpacing.screenH,
            0,
            AppSpacing.screenH,
            12 + MediaQuery.of(context).viewPadding.bottom,
          ),
          child: PrimaryButton(
            label: position >= lines.length - 1 ? l.planRehearsalDone : l.sessionNext,
            minHeight: 52,
            onPressed: onNext,
          ),
        ),
      ],
    );
  }
}

class _MicButton extends StatelessWidget {
  const _MicButton({required this.listening, required this.onTap});
  final bool listening;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Material(
    color: listening ? AppColors.ink : AppColors.destructiveText,
    shape: const CircleBorder(),
    clipBehavior: Clip.antiAlias,
    child: InkWell(
      onTap: onTap,
      child: SizedBox(
        width: 64,
        height: 64,
        child: Icon(
          listening ? LucideIcons.square : LucideIcons.mic,
          size: 22,
          color: AppColors.paper,
        ),
      ),
    ),
  );
}
