import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';
import 'package:eng_std/l10n/app_localizations.dart';

import '../../data/local/app_database.dart';
import '../../data/models.dart';
import '../../data/practice/learning_ladder.dart';
import '../../data/pronouncer.dart';
import '../../data/providers.dart';
import '../training/session_screen.dart';
import 'ladder_legend.dart';
import '../word_card/word_card_screen.dart';
import '../word_card/word_card_subject.dart';

/// «Мои слова» — the POOL, which is the list this app was missing.
///
/// A collection answers «что бывает в этой теме». This screen answers «что я учу», and until the
/// library and the queue came apart there was nowhere to ask it: words were scattered across the
/// collections they happened to arrive in, and a word whose collection had been deleted was
/// invisible while still being trained.
///
/// Reads the local mirror like every other screen, so it opens in airplane mode. The filters are
/// applied in Dart rather than in SQL on purpose: the pool is a personal list of hundreds, not a
/// table of millions, and one reactive query that never has to be re-issued is worth more here than
/// a narrower one.
class MyWordsScreen extends ConsumerStatefulWidget {
  const MyWordsScreen({super.key});

  @override
  ConsumerState<MyWordsScreen> createState() => _MyWordsScreenState();
}

/// The phase filter. Three buckets, and they are the acquisition dimension rather than the
/// scheduler's: the question this screen answers is «как далеко я с этим словом», which `state`
/// («когда оно вернётся») cannot answer.
enum _Phase { all, isNew, learning, review }

class _MyWordsScreenState extends ConsumerState<MyWordsScreen> {
  final _search = TextEditingController();
  // Owned here, like the collection screen owns its own: the engine is stateful and releasing it
  // with the screen is what keeps the audio route from being held open by a screen nobody sees.
  final _pronouncer = Pronouncer();
  _Phase _phase = _Phase.all;

  /// Null = «все коллекции». The empty string is the real, separate case «без коллекции»: a pool
  /// word outlives the collection it came from, and those words must stay reachable.
  String? _collectionId;

  @override
  void dispose() {
    _search.dispose();
    _pronouncer.release();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final pool = ref.watch(poolProvider).value ?? const <PoolWordRow>[];
    final collections = ref.watch(collectionsProvider).value ?? const <WordCollection>[];
    final rows = _filter(pool);

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark,
      child: Scaffold(
        backgroundColor: AppColors.paper,
        body: SafeArea(
          bottom: false,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _Header(title: l.myWordsTitle, subtitle: l.myWordsCount(pool.length)),
              if (pool.isNotEmpty) ...[
                Padding(
                  padding: const EdgeInsets.fromLTRB(
                    AppSpacing.screenH,
                    0,
                    AppSpacing.screenH,
                    AppSpacing.s12,
                  ),
                  child: _SearchField(
                    controller: _search,
                    hint: l.myWordsSearchHint,
                    onChanged: (_) => setState(() {}),
                  ),
                ),
                ChipScrollRow(
                  children: [
                    for (final p in _Phase.values)
                      AppChip(
                        label: _phaseLabel(l, p),
                        selected: _phase == p,
                        onTap: () => setState(() => _phase = p),
                      ),
                  ],
                ),
                const SizedBox(height: AppSpacing.s8),
                ChipScrollRow(
                  children: [
                    AppChip(
                      label: l.myWordsSourceAll,
                      selected: _collectionId == null,
                      onTap: () => setState(() => _collectionId = null),
                    ),
                    for (final c in collections)
                      AppChip(
                        label: c.title,
                        selected: _collectionId == c.id,
                        onTap: () => setState(() => _collectionId = c.id),
                      ),
                    // Offered only when there is something behind it — a chip that always finds
                    // nothing is a chip that teaches the learner to distrust the filter row.
                    if (pool.any((r) => r.collectionIds.isEmpty))
                      AppChip(
                        label: l.myWordsSourceNone,
                        selected: _collectionId == '',
                        onTap: () => setState(() => _collectionId = ''),
                      ),
                  ],
                ),
                const SizedBox(height: AppSpacing.s16),
              ],
              Expanded(
                child: pool.isEmpty
                    ? _Empty(title: l.myWordsEmptyTitle, message: l.myWordsEmptyMessage)
                    : rows.isEmpty
                    ? _Empty(title: l.myWordsNothingFound)
                    : ListView.builder(
                        padding: EdgeInsets.only(
                          bottom: MediaQuery.viewPaddingOf(context).bottom + AppSpacing.s26,
                        ),
                        itemCount: rows.length,
                        itemBuilder: (context, i) => _PoolRow(
                          row: rows[i],
                          showDivider: i < rows.length - 1,
                          onTap: () => _openCard(rows[i]),
                        ),
                      ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  /// Search + phase + source, in that order. Search matches the term AND the translation: someone
  /// looking for a word they half-remember is as likely to reach for the Russian side.
  List<PoolWordRow> _filter(List<PoolWordRow> pool) {
    final q = _search.text.trim().toLowerCase();
    return [
      for (final r in pool)
        if (_matchesQuery(r, q) && _matchesPhase(r) && _matchesSource(r)) r,
    ];
  }

  bool _matchesQuery(PoolWordRow r, String q) {
    if (q.isEmpty) return true;
    final term = (r.term.termText ?? '').toLowerCase();
    final translation = (r.term.translation ?? '').toLowerCase();
    return term.contains(q) || translation.contains(q);
  }

  bool _matchesPhase(PoolWordRow r) => switch (_phase) {
    _Phase.all => true,
    _Phase.isNew => r.position.acquisition == Acquisition.isNew,
    _Phase.learning => r.position.acquisition == Acquisition.learning,
    _Phase.review => r.position.acquisition == Acquisition.graduated,
  };

  bool _matchesSource(PoolWordRow r) => switch (_collectionId) {
    null => true,
    '' => r.collectionIds.isEmpty,
    final id => r.collectionIds.contains(id),
  };

  String _phaseLabel(AppLocalizations l, _Phase p) => switch (p) {
    _Phase.all => l.myWordsFilterAll,
    _Phase.isNew => l.myWordsFilterNew,
    _Phase.learning => l.myWordsFilterLearning,
    _Phase.review => l.myWordsFilterReview,
  };

  /// The language THIS word is in, off its own pair — the pool mixes collections by design, so the
  /// screen has no single language to borrow and the profile's is simply a different word's. Asked
  /// through [AppDatabase.pairByTerms], the one place that answers «which pair does this term belong
  /// to» (first collection by id wins, as on the server), so the card and the session agree.
  ///
  /// The fallback is the profile's target language: a word whose collections have left the mirror
  /// still has to be pronounceable.
  String get _fallbackLang => ref.read(authControllerProvider).value?.profile?.targetLanguage ?? 'en';

  /// The same card the collection screen opens — one card for a word, wherever it is met.
  ///
  /// No folder is named here on purpose: the pool mixes collections, and a word may have come from
  /// several or from one that no longer exists. «Добавить в другую папку» still works — it simply
  /// has no «current» shelf to contrast with.
  Future<void> _openCard(PoolWordRow row) async {
    final word = poolWordToWord(row);
    final pairs = await ref.read(appDatabaseProvider).pairByTerms([word.termId]);
    final speakLang = pairs[word.termId]?.learned ?? _fallbackLang;
    if (!mounted) return;
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => WordCardScreen(
          subject: WordCardSubject.fromWord(word),
          mode: WordCardMode.folder,
          onSpeak: () {
            AppHaptics.light();
            _pronouncer.speak(word, targetLang: speakLang);
          },
          onTrain: () => Navigator.of(context).push(
            MaterialPageRoute<void>(
              builder: (_) =>
                  SessionScreen(title: word.term, practice: true, onlyTermId: word.termId),
            ),
          ),
          onUnenroll: () => ref.read(poolSyncProvider).unenroll(word.termId),
        ),
      ),
    );
  }

}

class _Header extends StatelessWidget {
  const _Header({required this.title, required this.subtitle});
  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(
        AppSpacing.s8,
        AppSpacing.s8,
        AppSpacing.screenH,
        AppSpacing.s16,
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          IconButton(
            icon: const Icon(LucideIcons.chevronLeft, color: AppColors.ink),
            onPressed: () => Navigator.of(context).maybePop(),
          ),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SizedBox(height: 6),
                Text(title, style: AppText.collectionNameScreen),
                const SizedBox(height: 4),
                Text(subtitle, style: AppText.translation.copyWith(fontSize: 13)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _SearchField extends StatelessWidget {
  const _SearchField({required this.controller, required this.hint, required this.onChanged});
  final TextEditingController controller;
  final String hint;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    return TextField(
      controller: controller,
      onChanged: onChanged,
      textInputAction: TextInputAction.search,
      style: AppText.termInList.copyWith(fontSize: 15),
      decoration: InputDecoration(
        hintText: hint,
        hintStyle: AppText.translation.copyWith(fontSize: 14, color: AppColors.tertiary),
        prefixIcon: const Icon(LucideIcons.search, size: 17, color: AppColors.tertiary),
        filled: true,
        fillColor: AppColors.faintInk,
        isDense: true,
        contentPadding: const EdgeInsets.symmetric(vertical: 12),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadii.field),
          borderSide: BorderSide.none,
        ),
      ),
    );
  }
}

/// One pool word: the term, its translation, and the five dots. No thumbnail and no swipe actions —
/// this is a list of what is being learned, not a place to edit a collection.
class _PoolRow extends StatelessWidget {
  const _PoolRow({required this.row, required this.showDivider, required this.onTap});
  final PoolWordRow row;
  final bool showDivider;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return InkWell(
      onTap: onTap,
      child: DecoratedBox(
        decoration: BoxDecoration(
          border: showDivider
              ? const Border(bottom: BorderSide(color: AppColors.dividerFaint))
              : null,
        ),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(
            AppSpacing.screenH,
            AppSpacing.wordRowPadV,
            AppSpacing.screenH,
            AppSpacing.wordRowPadV,
          ),
          child: Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      row.term.termText ?? '',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: AppText.termInList,
                    ),
                    const SizedBox(height: 3),
                    Text(
                      row.term.translation ?? '',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: AppText.translation.copyWith(fontSize: 13),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: AppSpacing.s8),
              // Five dots and no words, which is right for a list of two hundred rows and wrong for
              // somebody meeting it. The dots are their own tap target now: the row still opens the
              // word, and the dots open the legend that says what they mean (Ч.4). A key rather
              // than a hit-test guess is what keeps the two taps apart in a test.
              if (row.position.isKnown)
                LadderKnownDash(label: l.ladderKnownDash)
              else
                GestureDetector(
                  key: ladderDotsLegendKey,
                  behavior: HitTestBehavior.opaque,
                  onTap: () {
                    AppHaptics.light();
                    showLadderLegend(context);
                  },
                  child: Padding(
                    // Room for a finger without moving the dots: they sit where they always did.
                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 10),
                    child: LadderDots(step: row.position.step),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Empty extends StatelessWidget {
  const _Empty({required this.title, this.message});
  final String title;
  final String? message;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: AppSpacing.s26),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(title, textAlign: TextAlign.center, style: AppText.sectionLabel),
            if (message != null) ...[
              const SizedBox(height: AppSpacing.s12),
              Text(
                message!,
                textAlign: TextAlign.center,
                style: AppText.translation.copyWith(fontSize: 13.5, height: 1.4),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
