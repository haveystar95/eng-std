import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/l10n/app_localizations.dart';

import '../../data/local/app_database.dart';
import '../../data/models.dart';
import '../../data/providers.dart';
import 'collection_cover.dart';
import 'collection_detail_screen.dart';

/// The pending rows the list should actually draw: every one whose collection is NOT yet visible
/// among [collections].
///
/// A finished generation is mirrored by the sync it triggers, and from that moment the same
/// collection had two rows on the Collections tab — the ready card and the real collection —
/// until an app restart, where reconciliation dropped the row (QA-3). Yielding here rather than
/// deleting the row on success keeps the offline case intact: with the sync still owed the card
/// stays and reads «почти готово», so the user is never left looking at nothing. The row itself is
/// still dropped by the launch reconcile, so nothing accumulates.
List<PendingGeneration> visiblePendingGenerations(
  List<PendingGeneration> pending,
  List<WordCollection> collections,
) {
  final mirrored = collections.map((c) => c.id).toSet();

  return pending.where((p) => p.collectionId == null || !mirrored.contains(p.collectionId)).toList();
}

/// One in-flight / finished generation as a flat list row (кадр 2.5 / 7a), driven entirely by its
/// drift row (survives an app kill AND an offline start; reconciled on launch). Three faces:
/// generating (shimmer in the cover slot + «обычно 20–30 секунд»), failed («Генерация не потрачена»
/// + Повторить/Скрыть), and ready (the synced cover/title, a контурный недобор-бейдж «13 из 15»
/// when under-delivered, tap to open with a «Разобрать» nudge).
class PendingGenerationCard extends ConsumerWidget {
  const PendingGenerationCard({super.key, required this.row, this.showDivider = true});

  final PendingGeneration row;
  final bool showDivider;

  static const _cover = 96.0;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final body = switch (row.status) {
      'failed' => _failed(context, ref),
      'succeeded' => _ready(context, ref),
      _ => _generating(context),
    };
    return DecoratedBox(
      decoration: BoxDecoration(
        border: showDivider ? const Border(bottom: BorderSide(color: AppColors.hairline)) : null,
      ),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: AppSpacing.s16),
        child: body,
      ),
    );
  }

  Widget _generating(BuildContext context) {
    final l = AppLocalizations.of(context);
    final levels = row.levelsCsv.split(',').where((s) => s.isNotEmpty).join('–');
    final meta = l.generationGeneratingMeta(row.topic, levels, l.approxWords(row.size));
    final note = row.sent ? l.generationGeneratingNote : l.generationQueuedNote;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _ShimmerBox(size: _cover),
            const SizedBox(width: 13),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(l.generationGeneratingTitle,
                      style: AppText.collectionNameCard.copyWith(color: AppColors.secondary)),
                  const SizedBox(height: 5),
                  Text(meta, maxLines: 1, overflow: TextOverflow.ellipsis,
                      style: AppText.transcription.copyWith(fontSize: 12.5, color: AppColors.tertiary)),
                  const SizedBox(height: 11),
                  // A thin indeterminate progress line — the shimmer already carries the "working" feel.
                  const SizedBox(
                    height: 2,
                    child: LinearProgressIndicator(
                      minHeight: 2,
                      backgroundColor: AppColors.track,
                      valueColor: AlwaysStoppedAnimation(AppColors.ink),
                    ),
                  ),
                  const SizedBox(height: 7),
                  Text(note, style: AppText.transcription.copyWith(fontSize: 11.5, color: AppColors.tertiary)),
                ],
              ),
            ),
          ],
        ),
      ],
    );
  }

  Widget _failed(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final ctrl = ref.read(generationControllerProvider);
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Outline square in the cover slot (no photo for a failed one).
        SizedBox(
          width: _cover,
          height: _cover,
          child: DecoratedBox(
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(AppRadii.thumb),
              border: Border.all(color: AppColors.hairline),
            ),
            child: const Icon(LucideIcons.imageOff, size: 26, color: AppColors.tertiary),
          ),
        ),
        const SizedBox(width: 13),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(l.generationFailedTitle, style: AppText.sheetButton.copyWith(fontSize: 16.5)),
              const SizedBox(height: 5),
              Text(l.generationFailedBody(row.topic),
                  style: AppText.translation.copyWith(fontSize: 13, height: 1.4, color: AppColors.secondary)),
              const SizedBox(height: 12),
              Row(
                children: [
                  _InkPill(
                    label: l.generationRetry,
                    onTap: () {
                      AppHaptics.light();
                      ctrl.retry(row);
                    },
                  ),
                  const SizedBox(width: 14),
                  InkWell(
                    onTap: () => ctrl.dismiss(row.id),
                    borderRadius: BorderRadius.circular(AppRadii.small),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 8),
                      child: Text(l.generationHide,
                          style: AppTextExercise.answerAuxButton.copyWith(color: AppColors.tertiary)),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _ready(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final cols = ref.watch(collectionsProvider).value ?? const <WordCollection>[];
    final col = _findCollection(cols, row.collectionId);

    // Collection not yet in the local mirror (sync still in flight) — a brief "почти готово".
    if (col == null) {
      return Row(
        children: [
          _ShimmerBox(size: _cover),
          const SizedBox(width: 13),
          Expanded(
            child: Text(l.generationReadyLoading(row.topic),
                maxLines: 2, overflow: TextOverflow.ellipsis, style: AppText.sheetButton),
          ),
        ],
      );
    }

    final under = row.delivered != null && row.requested != null && row.delivered! < row.requested!;
    return InkWell(
      onTap: () {
        AppHaptics.success();
        ref.read(generationControllerProvider).dismiss(row.id);
        Navigator.of(context).push(MaterialPageRoute(
          builder: (_) => CollectionDetailScreen(collectionId: col.id, title: col.title, offerTriage: true),
        ));
      },
      child: Row(
        children: [
          CollectionCover(collection: col, size: _cover),
          const SizedBox(width: 13),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    const Icon(LucideIcons.sparkles, size: 13, color: AppColors.secondary),
                    const SizedBox(width: 5),
                    Text(l.generationReadyLabel, style: AppText.sectionLabel.copyWith(fontSize: 10.5)),
                  ],
                ),
                const SizedBox(height: 4),
                Text(col.title, maxLines: 1, overflow: TextOverflow.ellipsis, style: AppText.collectionNameCard),
                const SizedBox(height: 5),
                if (under)
                  Row(
                    children: [
                      Expanded(
                        child: Text(l.generationReadyUnder,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: AppText.translation.copyWith(fontSize: 12.5)),
                      ),
                      const SizedBox(width: 8),
                      _CountBadge(label: l.generationUnderBadge(row.delivered!, row.requested!)),
                    ],
                  )
                else
                  Text(l.collectionWordsCount(col.wordsCount),
                      style: AppText.translation.copyWith(fontSize: 12.5)),
              ],
            ),
          ),
          const SizedBox(width: AppSpacing.s8),
          const Icon(LucideIcons.chevronRight, size: 18, color: AppColors.tertiary),
        ],
      ),
    );
  }

  static WordCollection? _findCollection(List<WordCollection> cols, String? id) {
    if (id == null) return null;
    for (final c in cols) {
      if (c.id == id) return c;
    }
    return null;
  }
}

/// The warm paper shimmer that holds the cover's place while a generation runs (кадр 2.5) — a paper
/// band sweeping left→right over the track box in 1.2s. A Ticker-backed controller (disposed with
/// the widget), not an ambient timer.
class _ShimmerBox extends StatefulWidget {
  const _ShimmerBox({required this.size});
  final double size;

  @override
  State<_ShimmerBox> createState() => _ShimmerBoxState();
}

class _ShimmerBoxState extends State<_ShimmerBox> with SingleTickerProviderStateMixin {
  late final AnimationController _c =
      AnimationController(vsync: this, duration: const Duration(milliseconds: 1200))..repeat();

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final s = widget.size;
    return SizedBox(
      width: s,
      height: s,
      child: ClipRRect(
        borderRadius: BorderRadius.circular(AppRadii.thumb),
        child: Stack(
          fit: StackFit.expand,
          children: [
            const ColoredBox(color: AppColors.track),
            AnimatedBuilder(
              animation: _c,
              builder: (context, _) {
                final dx = (-1.0 + 2.0 * _c.value) * s;
                return Transform.translate(
                  offset: Offset(dx, 0),
                  child: DecoratedBox(
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        colors: [
                          AppColors.paper.withValues(alpha: 0),
                          AppColors.paper.withValues(alpha: 0.55),
                          AppColors.paper.withValues(alpha: 0),
                        ],
                        stops: const [0.35, 0.5, 0.65],
                      ),
                    ),
                  ),
                );
              },
            ),
          ],
        ),
      ),
    );
  }
}

/// Small ink pill button («Повторить» in the error row) — 38h, radius 14 (кадр 2.5).
class _InkPill extends StatelessWidget {
  const _InkPill({required this.label, required this.onTap});
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.ink,
      borderRadius: BorderRadius.circular(AppRadii.small),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Container(
          height: 38,
          padding: const EdgeInsets.symmetric(horizontal: 18),
          alignment: Alignment.center,
          child: Text(label,
              style: AppTextExercise.answerAuxButton.copyWith(color: AppColors.paper, fontWeight: FontWeight.w700)),
        ),
      ),
    );
  }
}

/// Outline count badge, secondary grey (кадр 2.5 «13 из 15»).
class _CountBadge extends StatelessWidget {
  const _CountBadge({required this.label});
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 2),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(5),
        border: Border.all(color: AppColors.track),
      ),
      child: Text(label,
          style: AppText.badge.copyWith(letterSpacing: 0.3, fontFeatures: const [])),
    );
  }
}
