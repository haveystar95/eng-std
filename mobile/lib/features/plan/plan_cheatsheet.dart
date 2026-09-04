/// ШПАРГАЛКА СЦЕНЫ — кадры D·09 и D·10 серии «День v1».
///
/// «Подглядеть перед дверью врача»: крупный текст, кнопки озвучки 56 pt, никакого декора. It is the
/// ONE screen of the product where a translation stands under every line — everywhere else a
/// translation under the target language is the answer key, and here nobody is being tested. The
/// learner is standing outside a room with two minutes.
///
/// ## Лист, а не экран
///
/// It opens over whatever the learner was doing and returns them to exactly that: the plan screen,
/// the day, the middle of a sitting. That is why it is a sheet and not a route — a screen would put
/// something in the back stack and make «закрыть» a navigation decision instead of a swipe down.
///
/// ## Спасатели закреплены
///
/// The five phrases sit above the scroll and do not move with it. They are the plan's, not the
/// scene's (канон §5), and they are the ones needed exactly when the learner has lost the thread —
/// which is the moment they would otherwise have to scroll for them.
library;

import 'dart:async';

import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../data/api_client.dart';
import '../../data/line_audio.dart';
import '../../data/plan_models.dart';
import '../../data/providers.dart';
import '../../data/pronouncer.dart';
import 'plan_ui.dart';

/// Open the cheat sheet over the current screen and return to it on close.
///
/// The backdrop is the ink ground the записка names (#2E2620) rather than the usual scrim: the
/// sheet is a sheet of paper held up in front of the app, and a translucent grey behind it would
/// leave the screen underneath legible and competing with it.
Future<void> showPlanCheatSheet(
  BuildContext context, {
  required String planId,
  required int dayIndex,
  required String targetLang,
}) {
  AppHaptics.light();

  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    backgroundColor: Colors.transparent,
    barrierColor: AppColors.ink.withValues(alpha: .92),
    builder: (_) => PlanCheatSheet(
      planId: planId,
      dayIndex: dayIndex,
      targetLang: targetLang,
    ),
  );
}

class PlanCheatSheet extends ConsumerStatefulWidget {
  const PlanCheatSheet({
    super.key,
    required this.planId,
    required this.dayIndex,
    required this.targetLang,
  });

  final String planId;
  final int dayIndex;
  final String targetLang;

  @override
  ConsumerState<PlanCheatSheet> createState() => _PlanCheatSheetState();
}

class _PlanCheatSheetState extends ConsumerState<PlanCheatSheet> {
  /// Built with the shared line cache (наряд TTS-1): шпаргалка играет ТОТ ЖЕ файл, что и разговор.
  /// Два голоса на одну реплику — это две разные реплики для уха.
  late final LineAudioCache _lineAudio = ref.read(lineAudioCacheProvider);
  late final Pronouncer _pronouncer = Pronouncer(null, _lineAudio);

  /// Which line is sounding right now — one at a time, and the others do not grey out (записка
  /// «Озвучка в шпаргалке»). Null when nothing is playing.
  String? _speaking;

  @override
  void initState() {
    super.initState();
    unawaited(_pronouncer.warmUp(targetLang: widget.targetLang));
  }

  @override
  void dispose() {
    _pronouncer.release();
    super.dispose();
  }

  /// Докачать озвучку реплик этого дня, если её ещё нет в общем кэше.
  Future<void> _preload(List<PlanTermRow> terms) async {
    final lines = [
      for (final term in terms)
        if ((term.audioUrl ?? '').isNotEmpty) (text: term.text, url: term.audioUrl!),
    ];
    if (lines.isEmpty) return;

    await _lineAudio.preload(lines);
    if (mounted) setState(() {});
  }

  /// Tap to play, tap again to stop. A second line taps over the first: одновременно играет одна.
  Future<void> _say(PlanTermRow term) async {
    AppHaptics.light();
    if (_speaking == term.termId) {
      await _pronouncer.stop();
      if (mounted) setState(() => _speaking = null);

      return;
    }

    setState(() => _speaking = term.termId);
    try {
      await _pronouncer.speakText(term.text, targetLang: widget.targetLang);
    } catch (_) {
      // A voice this device does not have is not something the learner can act on, and the line is
      // on the screen in front of them either way.
    }
    if (mounted && _speaking == term.termId) setState(() => _speaking = null);
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final day = ref.watch(planDayProvider((planId: widget.planId, dayIndex: widget.dayIndex)));

    return Padding(
      padding: EdgeInsets.only(top: MediaQuery.viewPaddingOf(context).top + 24),
      child: Container(
        decoration: const BoxDecoration(
          color: AppColors.paper,
          borderRadius: BorderRadius.vertical(top: Radius.circular(26)),
        ),
        clipBehavior: Clip.antiAlias,
        child: SafeArea(
          top: false,
          child: day.when(
            loading: () => const SizedBox(
              height: 220,
              child: Center(child: CircularProgressIndicator(color: AppColors.ink)),
            ),
            error: (e, _) => Padding(
              padding: const EdgeInsets.all(20),
              child: PlanNotice(text: isOffline(e) ? l.planErrorOffline : l.planErrorLoadFailed),
            ),
            data: (detail) {
              // Шпаргалка тоже качает: её открывают отдельно от посадки (канон §13 — «с любого
              // экрана плана»), и реплика, у которой файл ещё не приехал, иначе прозвучала бы
              // системным голосом на экране, где рядом играет серверный.
              unawaited(_preload(detail.terms));

              return _Sheet(
                detail: detail,
                speaking: _speaking,
                onSay: _say,
                voiceTrouble: PlanVoiceTrouble(cache: _lineAudio),
              );
            },
          ),
        ),
      ),
    );
  }
}

class _Sheet extends StatelessWidget {
  const _Sheet({
    required this.detail,
    required this.speaking,
    required this.onSay,
    required this.voiceTrouble,
  });

  final PlanDayDetail detail;
  final String? speaking;
  final void Function(PlanTermRow) onSay;

  /// Дев-бейдж сломанной озвучки — тот же виджет, что в диалоге. Дефект общий для экранов, потому
  /// что путь «пейлоад → докачка → произноситель» один.
  final Widget voiceTrouble;

  /// The five phrases of the plan, and the scene's own shelves in the order the sitting deals them.
  ///
  /// Numbers are deliberately absent and the sheet says so: `PlanProgress` keeps the `numbers` shelf
  /// out of a day's term list because no session deals one (канон §6, NUM-1), so the client has
  /// nothing to draw. Кадр D·10 has a «Числа на слух» block; it needs a server change, and inventing
  /// the numbers here would be worse than naming the gap.
  static const _order = [
    PlanTermRow.shelfHear,
    PlanTermRow.shelfSay,
    PlanTermRow.shelfAsk,
    PlanTermRow.shelfWords,
    PlanTermRow.shelfChunks,
  ];

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);

    final rescue = <PlanTermRow>[];
    final byShelf = <String, List<PlanTermRow>>{};
    for (final term in detail.terms) {
      if (term.shelf == PlanTermRow.shelfRescue) {
        rescue.add(term);

        continue;
      }
      if (term.fromDayIndex != detail.day.index) continue;
      byShelf.putIfAbsent(term.shelf ?? '', () => []).add(term);
    }

    final blocks = <({String label, String? note, List<PlanTermRow> terms})>[];
    for (final shelf in _order) {
      final terms = byShelf.remove(shelf);
      if (terms == null || terms.isEmpty) continue;
      blocks.add((
        label: switch (shelf) {
          PlanTermRow.shelfHear => l.planShelfHear,
          PlanTermRow.shelfSay => l.planShelfSay,
          PlanTermRow.shelfAsk => l.planShelfAsk,
          _ => l.planShelfWords,
        },
        note: shelf == PlanTermRow.shelfHear ? l.planDayRoleOnlyUnderstand : null,
        terms: terms,
      ));
    }
    // A day written before the shelves existed: one block, under the caption it has always had.
    final rest = byShelf.values.expand((t) => t).toList(growable: false);
    if (rest.isNotEmpty) blocks.add((label: l.planDayPhrases, note: null, terms: rest));

    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        const SizedBox(height: 10),
        // The handle — the sheet says it can be swiped away before anyone tries.
        Container(
          width: 38,
          height: 4,
          decoration: BoxDecoration(
            color: AppColors.ink.withValues(alpha: .2),
            borderRadius: BorderRadius.circular(2),
          ),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 10, 20, 0),
          child: voiceTrouble,
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 4, 8, 0),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    PlanLabel(l.planCheatSheetTitle(detail.day.index)),
                    const SizedBox(height: 6),
                    Text(
                      detail.day.title,
                      style: AppText.collectionNameScreen.copyWith(fontSize: 25, height: 1.2),
                    ),
                  ],
                ),
              ),
              InkResponse(
                onTap: () => Navigator.of(context).maybePop(),
                radius: 22,
                child: const SizedBox(
                  width: AppSpacing.minTap,
                  height: AppSpacing.minTap,
                  child: Icon(LucideIcons.x, size: 20, color: AppColors.secondary),
                ),
              ),
            ],
          ),
        ),
        // СПАСАТЕЛИ ЗАКРЕПЛЕНЫ — outside the scroll, so they are where the learner reaches for them
        // at the moment they need them (кадр D·09).
        if (rescue.isNotEmpty)
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 14, 20, 0),
            child: _RescuePinned(terms: rescue, speaking: speaking, onSay: onSay),
          ),
        Flexible(
          child: blocks.isEmpty && rescue.isEmpty
              ? Padding(
                  padding: const EdgeInsets.fromLTRB(20, 24, 20, 24),
                  child: Text(
                    l.planCheatSheetEmpty,
                    style: AppText.translation.copyWith(
                      fontSize: 14.5,
                      height: 1.55,
                      color: AppColors.secondary,
                    ),
                  ),
                )
              : ListView(
                  padding: const EdgeInsets.fromLTRB(20, 18, 20, 26),
                  children: [
                    for (final block in blocks) ...[
                      Row(
                        children: [
                          Expanded(
                            child: PlanLabel(
                              block.label,
                              color: AppColors.tertiary,
                              fontSize: 11.5,
                            ),
                          ),
                          if (block.note != null)
                            Text(
                              block.note!,
                              style: AppText.translation.copyWith(
                                fontSize: 11.5,
                                color: AppColors.brassInk,
                              ),
                            ),
                        ],
                      ),
                      const SizedBox(height: 8),
                      for (final term in block.terms)
                        _CheatLine(
                          term: term,
                          speaking: speaking == term.termId,
                          onSay: () => onSay(term),
                        ),
                      const SizedBox(height: 22),
                    ],
                    // The gap named out loud — see [_order].
                    Text(
                      l.planCheatSheetNoNumbers,
                      style: AppText.translation.copyWith(
                        fontSize: 12.5,
                        height: 1.5,
                        color: AppColors.tertiary,
                      ),
                    ),
                  ],
                ),
        ),
      ],
    );
  }
}

/// The plan's five phrases, pinned above the scroll.
class _RescuePinned extends StatelessWidget {
  const _RescuePinned({required this.terms, required this.speaking, required this.onSay});

  final List<PlanTermRow> terms;
  final String? speaking;
  final void Function(PlanTermRow) onSay;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);

    return Container(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 6),
      decoration: BoxDecoration(
        color: AppColors.surfaceRaised,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.brassInk.withValues(alpha: .3)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(child: PlanLabel(l.planCheatSheetRescuePinned)),
              Text(
                '${terms.length}',
                style: AppText.blockLabel.copyWith(color: AppColors.brassInk, fontSize: 13),
              ),
            ],
          ),
          const SizedBox(height: 6),
          for (final term in terms)
            _CheatLine(
              term: term,
              speaking: speaking == term.termId,
              onSay: () => onSay(term),
              compact: true,
            ),
        ],
      ),
    );
  }
}

/// One line of the sheet: the line, its translation, and a speak button big enough to hit blind.
class _CheatLine extends StatelessWidget {
  const _CheatLine({
    required this.term,
    required this.speaking,
    required this.onSay,
    this.compact = false,
  });

  final PlanTermRow term;
  final bool speaking;
  final VoidCallback onSay;

  /// Inside the pinned rescue block, where five lines have to fit above the fold.
  final bool compact;

  @override
  Widget build(BuildContext context) => Padding(
    padding: EdgeInsets.only(bottom: compact ? 8 : 14),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                term.text,
                style: AppText.collectionNameCard.copyWith(
                  fontSize: compact ? 15.5 : 17,
                  height: 1.35,
                  // Курсив — только чужая речь, здесь как и везде.
                  fontStyle: term.isRecognitionOnly ? FontStyle.italic : FontStyle.normal,
                ),
              ),
              if ((term.translation ?? '').isNotEmpty) ...[
                const SizedBox(height: 2),
                // THE ONE SCREEN WITH A TRANSLATION UNDER EVERY LINE. Everywhere else it would be
                // the answer key; here nobody is being tested and the learner has two minutes.
                Text(
                  term.translation!,
                  style: AppText.translation.copyWith(
                    fontSize: 13.5,
                    height: 1.4,
                    color: AppColors.secondary,
                  ),
                ),
              ],
            ],
          ),
        ),
        const SizedBox(width: AppSpacing.s12),
        // 56 pt, and it stays 56 pt in the compact block: this is the control the learner presses
        // without looking, on the way through a door.
        Semantics(
          button: true,
          label: term.text,
          child: InkResponse(
            onTap: onSay,
            radius: 28,
            child: Container(
              width: 56,
              height: 56,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                // Painted while it sounds, and the others do not dim — one line plays at a time and
                // the rest stay pressable (записка «Озвучка в шпаргалке»).
                color: speaking ? AppColors.planSelected : AppColors.surfaceRaised,
                border: Border.all(color: AppColors.brassInk.withValues(alpha: .3)),
              ),
              child: Icon(
                speaking ? LucideIcons.square : LucideIcons.volume2,
                size: 18,
                color: AppColors.brassInk,
              ),
            ),
          ),
        ),
      ],
    ),
  );
}
