import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';
import 'package:eng_std/l10n/app_localizations.dart';

import '../../data/pending_content_refresher.dart';
import '../../data/pronouncer.dart';
import '../../data/models.dart';
import '../../data/providers.dart';
import '../../data/store_providers.dart';
import '../home/home_cta.dart';
import '../home/limit_reached_card.dart';
import '../practice_dialog/dialog_entry_button.dart';
import '../training/session_screen.dart';
import '../training/triage_screen.dart';
import '../word_card/word_card_screen.dart';
import '../word_card/word_card_subject.dart';
import 'collection_cta.dart';
import 'collection_edit_dialog.dart';
import 'word_edit_dialog.dart';
import 'word_ladder_sheet.dart';
import '../../data/local/cached_image_provider.dart';

/// Collection screen (кадр 2.3): cover photo, three ink-density segments, a
/// state-dependent primary action (triage → review → practice), and the word
/// list (Literata term, type badge, secondary translation, slashed transcription,
/// right-axis speaker). Swipe a row or long-press it for Изменить / Удалить
/// (no «Озвучить» — the speaker is on the row, rule 18). All reads are local.
class CollectionDetailScreen extends ConsumerStatefulWidget {
  const CollectionDetailScreen({
    super.key,
    required this.collectionId,
    required this.title,
    this.offerTriage = false,
  });

  final String collectionId;
  final String title;

  /// First-contact nudge on a freshly generated collection: offer «Разобрать» first.
  final bool offerTriage;

  @override
  ConsumerState<CollectionDetailScreen> createState() => _CollectionDetailScreenState();
}

class _CollectionDetailScreenState extends ConsumerState<CollectionDetailScreen> {
  final _pronouncer = Pronouncer();
  late bool _showTriagePrompt = widget.offerTriage;

  /// Pulls `/sync` on a widening backoff while a word here is still waiting for its photo, so the
  /// picture appears on its own instead of after a swipe-down nobody should have to think of.
  PendingContentRefresher? _refresher;

  @override
  void initState() {
    super.initState();
    // Raise the iOS audio session and prime the synthesizer on entry, the same way the training
    // screen does. Without it the FIRST word tapped here starts mid-way — you hear only its tail —
    // because the audio route is cold and wakes up while the utterance is already running; a second
    // tap right after is clean, which is the route being awake by then. This screen exists to be
    // listened to, so paying for it on entry is right (F20-r2).
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) unawaited(_pronouncer.warmUp(targetLang: _speakLang));
    });
  }

  @override
  void dispose() {
    _refresher?.dispose();
    // Hand the session back. Symmetric with the warm-up: this screen owns it only while it is on
    // screen, exactly like the training screen.
    unawaited(_pronouncer.release());
    super.dispose();
  }

  /// Is anything on this screen still waiting on the server?
  ///
  /// A missing PHOTO is the honest signal: it is the last thing to land (an image search after an
  /// enrichment after a save) and the only one the learner can see missing. A missing translation
  /// counts too — that is a word added bare, still being filled in.
  ///
  /// A word whose photo never arrives is not a bug and not a loop: the refresher's budget ends on
  /// its own, because a word the model refused to illustrate has nothing to wait for.
  bool _awaitingContent(List<Word> items) => items.any(
        (w) => (w.imageUrl == null || w.imageUrl!.isEmpty) || w.translation.trim().isEmpty,
      );

  /// Pronounce in the COLLECTION's language, not the profile's — a ru→de set must speak German even
  /// when the profile targets English (language lives on the collection; device-batch F16).
  String get _speakLang =>
      _collection?.targetLang ??
      ref.read(authControllerProvider).value?.profile?.targetLanguage ??
      'en';

  void _openTriage() {
    AppHaptics.light();
    Navigator.of(context).push(MaterialPageRoute(
      builder: (_) => TriageScreen(collectionId: widget.collectionId, title: widget.title),
    ));
  }

  void _openSession(bool practice, {bool learn = false, String? onlyTermId}) {
    Navigator.of(context).push(MaterialPageRoute(
      builder: (_) => SessionScreen(
        title: widget.title,
        collectionId: widget.collectionId,
        practice: practice,
        learn: learn, // «Учить N»: distinguish an empty session (quota spent) from «nothing here»
        targetLang: _collection?.targetLang, // speak this collection's language (F16)
        onlyTermId: onlyTermId, // «Тренировать слово» from the expanded card (кадр 16e)
      ),
    ));
  }

  Future<void> _speak(Word word) async {
    AppHaptics.light();
    await _pronouncer.speak(word, targetLang: _speakLang);
  }

  Future<void> _delete(Word word) async {
    await ref.read(apiClientProvider).removeWord(widget.collectionId, word.termId);
    // The item tombstone comes back through sync and drops the local row.
    ref.read(syncServiceProvider).sync();
  }

  Future<void> _confirmDelete(Word word) async {
    final l = AppLocalizations.of(context);
    final ok = await showCenterAlert(
      context: context,
      title: l.collectionDeleteWordTitle(word.term),
      message: l.collectionDeleteWordMessage,
      confirmLabel: l.actionDelete,
      cancelLabel: l.commonCancel,
    );
    if (ok == true) {
      AppHaptics.warning();
      await _delete(word);
    }
  }

  /// Move a word to ANOTHER of the learner's own folders.
  ///
  /// One server call, not remove+add: the two halves must not be able to half-happen offline and
  /// leave the word in neither folder. It is a change of shelf and nothing else — the word keeps its
  /// rung, its due date and its place in the pool, which is why this is not a destructive action and
  /// asks for no confirmation.
  Future<void> _moveWord(Word word) async {
    final l = AppLocalizations.of(context);
    final all = ref.read(collectionsProvider).value ?? const <WordCollection>[];
    // Own, editable folders, minus the one we are standing in. A store deck is a catalogue nobody
    // can put a word into.
    final targets = all
        .where((c) => c.isOwned && !c.isSubscribed && c.id != widget.collectionId)
        .toList();

    if (targets.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(l.collectionMoveWordNowhere)),
      );

      return;
    }

    final target = await showAppBottomSheet<WordCollection>(
      context: context,
      builder: (sheet) => Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.symmetric(vertical: AppSpacing.s8),
            child: Text(l.collectionMoveWordTitle, style: AppText.sectionLabel),
          ),
          for (final folder in targets)
            ListTile(
              title: Text(folder.title, style: AppText.translation),
              onTap: () => Navigator.of(sheet).pop(folder),
            ),
        ],
      ),
    );
    if (target == null || !mounted) return;

    try {
      await ref.read(apiClientProvider).moveWord(
            fromCollectionId: widget.collectionId,
            toCollectionId: target.id,
            termId: word.termId,
          );
      ref.read(syncServiceProvider).sync();
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(l.collectionMoveWordDone(target.title))),
      );
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(l.collectionMoveWordFailed)),
      );
    }
  }

  void _edit(Word word) => showWordEditor(
        context,
        ref,
        collectionId: widget.collectionId,
        existing: word,
        onRequestDelete: () => _confirmDelete(word),
      );

  void _add() => showWordEditor(context, ref, collectionId: widget.collectionId);

  /// Collection ⋯ menu (кадр 5e). Own collections only for now: Переименовать +
  /// Удалить коллекцию. (Shared-collection variant «Создать копию / Убрать из
  /// моих» + «Сменить обложку» need the store→detail flow + fork/unsubscribe API
  /// — deferred to A3.5; this screen is only reached for own collections.)
  Future<void> _openCollectionMenu(BuildContext anchor) async {
    final l = AppLocalizations.of(context);
    // Read-only store set (кадр 5e shared variant): the only action is «Убрать из моих» (unsubscribe);
    // no rename. Own collections keep rename + delete.
    final readOnly = _collection?.readOnly ?? false;
    await showFloatingContextMenu(
      context: context,
      anchorContext: anchor,
      barrierLabel: l.commonCloseMenu,
      actions: readOnly
          ? [
              ContextMenuAction(
                icon: LucideIcons.circleMinus,
                label: l.collectionMenuRemoveFromMine,
                destructive: true,
                onSelected: _confirmUnsubscribe,
              ),
            ]
          : [
              ContextMenuAction(
                icon: LucideIcons.pencil,
                label: l.collectionMenuRename,
                onSelected: () => showCollectionEditor(context, ref, existing: _collection),
              ),
              // Same rule as on the shelf: «Сохранённые» is renameable and undeletable.
              if (!(_collection?.isDefault ?? false))
                ContextMenuAction(
                  icon: LucideIcons.trash2,
                  label: l.collectionMenuDelete,
                  destructive: true,
                  onSelected: _confirmDeleteCollection,
                ),
            ],
    );
  }

  /// Remove a subscribed store set from «Мои» — unsubscribe (not delete). Words + progress are kept
  /// globally; the set reappears in the store as addable.
  Future<void> _confirmUnsubscribe() async {
    final l = AppLocalizations.of(context);
    final ok = await showCenterAlert(
      context: context,
      title: l.collectionUnsubscribeTitle(widget.title),
      message: l.collectionUnsubscribeMessage,
      confirmLabel: l.collectionMenuRemoveFromMine,
      cancelLabel: l.commonCancel,
    );
    if (ok != true) return;
    AppHaptics.warning();
    final done = await unsubscribeCollectionById(ref, widget.collectionId);
    if (done && mounted) Navigator.of(context).maybePop();
  }

  WordCollection? _collection;

  Future<void> _confirmDeleteCollection() async {
    final l = AppLocalizations.of(context);
    final ok = await showCenterAlert(
      context: context,
      title: l.collectionDeleteTitle(widget.title),
      message: l.collectionDeleteMessage,
      confirmLabel: l.actionDelete,
      cancelLabel: l.commonCancel,
    );
    if (ok == true) {
      AppHaptics.warning();
      try {
        await ref.read(apiClientProvider).deleteCollection(widget.collectionId);
        // Drop it locally right away — don't wait for a sync tombstone the delta feed
        // doesn't reliably carry for collections (this was the "backend deleted, front didn't" bug).
        await ref.read(appDatabaseProvider).deleteCollectionLocal(widget.collectionId);
        ref.read(syncServiceProvider).sync();
        if (mounted) Navigator.of(context).maybePop();
      } catch (_) {
        // Network/5xx: keep the collection and the screen; the user can retry.
        AppHaptics.warning();
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final words = ref.watch(collectionWordsProvider(widget.collectionId));
    final collection = ref
        .watch(collectionsProvider)
        .value
        ?.where((c) => c.id == widget.collectionId)
        .firstOrNull;
    _collection = collection;
    final readOnly = collection?.readOnly ?? false;
    final density = ref.watch(collectionDensityProvider(widget.collectionId)).value ??
        const CollectionDensity(confirmed: 0, familiar: 0, inProgress: 0);
    final cprog = ref.watch(collectionsProgressProvider).value?[widget.collectionId];
    final untriaged = ref.watch(untriagedByCollectionProvider).value?[widget.collectionId] ?? 0;
    final learnable = ref.watch(learnableByCollectionProvider).value?[widget.collectionId] ?? 0;
    final due = cprog?.due ?? 0;
    final total = cprog?.total ?? collection?.wordsCount ?? 0;
    final remainingNewQuota = ref.watch(statsProvider).value?.newRemaining ?? 0;
    final cta = computeCollectionCta(
      untriaged: untriaged,
      learnable: learnable,
      due: due,
      remainingNewQuota: remainingNewQuota,
    );

    // Light status-bar glyphs over the dark cover photo (overrides the app-wide
    // dark default set in main()).
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light,
      child: Scaffold(
      backgroundColor: AppColors.paper,
      body: words.when(
        loading: () => const Center(child: CircularProgressIndicator(color: AppColors.ink)),
        error: (e, _) => _CoverScaffold(
          imageUrl: collection?.imageUrl,
          isDefault: collection?.isDefault ?? false,
          child: Center(
            child: Text(l.triageLoadError(e.toString()),
                textAlign: TextAlign.center, style: AppText.translation),
          ),
        ),
        data: (items) {
          // Every rebuild says whether anything is still missing; the refresher decides whether
          // that is worth another look and stops on its own when it is not.
          (_refresher ??= PendingContentRefresher(ref.read(syncServiceProvider)))
              .nudge(pending: _awaitingContent(items));

          return ListView(
          padding: EdgeInsets.only(bottom: MediaQuery.viewPaddingOf(context).bottom + AppSpacing.s26),
          children: [
            _Cover(
              imageUrl: collection?.imageUrl,
              isDefault: collection?.isDefault ?? false,
              onBack: () => Navigator.of(context).maybePop(),
              onMenu: _openCollectionMenu,
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(AppSpacing.screenH, 18, AppSpacing.screenH, 0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(widget.title, style: AppText.collectionNameScreen),
                  const SizedBox(height: 6),
                  Text(
                    _subtitle(l, total, due),
                    style: AppText.translation.copyWith(fontSize: 13),
                  ),
                  const SizedBox(height: AppSpacing.s16),
                  InkSegments.fromCounts(
                    confirmed: density.confirmed,
                    familiar: density.familiar,
                    inProgress: density.inProgress,
                  ),
                  const SizedBox(height: 11),
                  _DensityLegend(density: density),
                  const SizedBox(height: 18),
                  _CtaButton(cta: cta, onTriage: _openTriage, onSession: _openSession),
                  // «Разобрать N» — the swipe pass over what is left, for as long as anything is
                  // left (QA-25). The primary CTA above outranks triage the moment the first swipes
                  // produce something to learn or to review, and the rest of the collection then had
                  // no way in at all.
                  if (showsSecondaryTriage(cta, untriaged)) ...[
                    SizedBox(height: cta.kind == HomeCtaKind.none ? 0 : AppSpacing.s12),
                    _TriageButton(count: untriaged, onTap: _openTriage),
                  ],
                  // «Тренировка» — always available under the primary CTA (Training Loop v2 / F17):
                  // drills every word in the collection at any moment, ignoring due/status, and moves
                  // no progress. Hidden only on an empty collection (nothing to drill).
                  if (total > 0) ...[
                    SizedBox(
                      height: cta.kind == HomeCtaKind.none && !showsSecondaryTriage(cta, untriaged)
                          ? 0
                          : AppSpacing.s12,
                    ),
                    _PracticeButton(onTap: () => _openSession(true)),
                  ],
                  // «Разговор · 3 мин» — premium-only, collapses to nothing otherwise (self-spaced).
                  DialogEntryButton(collectionId: widget.collectionId, title: widget.title),
                  if (_showTriagePrompt) ...[
                    const SizedBox(height: AppSpacing.s12),
                    _TriageBanner(
                      onStart: _openTriage,
                      onDismiss: () => setState(() => _showTriagePrompt = false),
                    ),
                  ],
                  const SizedBox(height: 20),
                  Text(l.collectionWordsLabel, style: AppText.sectionLabel),
                  const SizedBox(height: 6),
                ],
              ),
            ),
            if (items.isEmpty)
              _Empty(l: l)
            else
              for (var i = 0; i < items.length; i++)
                _WordRow(
                  word: items[i],
                  showDivider: i < items.length - 1,
                  onSpeak: () => _speak(items[i]),
                  // Own folder → the full card; a store deck stays on the compact sheet.
                  folder: readOnly
                      ? null
                      : SavedFolder(
                          id: widget.collectionId,
                          title: widget.title,
                          isDefault: collection?.isDefault ?? false,
                        ),
                  // Read-only store set: no per-word edit/delete (swipe + menu suppressed).
                  onEdit: readOnly ? null : () => _edit(items[i]),
                  onDelete: readOnly ? null : () => _confirmDelete(items[i]),
                  onMove: readOnly ? null : () => _moveWord(items[i]),
                  // Practice, narrowed to this word. Practice and not a study session: drilling one
                  // word on demand must not spend the day's new-term quota on it.
                  onTrain: () => _openSession(true, onlyTermId: items[i].termId),
                  // The two pool decisions. Local first, then queued for the server — see PoolSync.
                  onEnroll: () => ref.read(poolSyncProvider).enroll(items[i].termId),
                  onUnenroll: () => ref.read(poolSyncProvider).unenroll(items[i].termId),
                ),
            // «Добавить слово» — own collections only.
            if (!readOnly)
              Padding(
                padding: const EdgeInsets.fromLTRB(AppSpacing.screenH, 16, AppSpacing.screenH, 0),
                child: _AddWordButton(label: l.collectionAddWord, onTap: _add),
              ),
          ],
          );
        },
      ),
      ),
    );
  }

  String _subtitle(AppLocalizations l, int total, int due) {
    final words = l.collectionWordsCount(total);
    return due > 0 ? '$words · ${l.collectionDueSuffix(due)}' : words;
  }
}

/// Cover photo header with a top scrim + back chip. Monochrome placeholder when
/// the collection has no cover.
class _Cover extends StatelessWidget {
  const _Cover({required this.imageUrl, required this.onBack, this.onMenu, this.isDefault = false});
  final String? imageUrl;
  final bool isDefault;
  final VoidCallback onBack;

  /// Opens the collection ⋯ menu, anchored at the button's own context.
  final void Function(BuildContext anchor)? onMenu;

  static const _height = 226.0;

  @override
  Widget build(BuildContext context) {
    final hasImage = !isDefault && imageUrl != null && imageUrl!.isNotEmpty;
    return SizedBox(
      height: _height,
      child: Stack(
        fit: StackFit.expand,
        children: [
          if (hasImage)
            Image(
              image: CachedNetworkImage(imageUrl!),
              fit: BoxFit.cover,
              errorBuilder: (_, _, _) => _CoverPlaceholder(isDefault: isDefault),
            )
          else
            _CoverPlaceholder(isDefault: isDefault),
          // Top scrim so the white status bar + back chip read over any photo.
          DecoratedBox(
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                colors: [AppColors.ink.withValues(alpha: 0.55), AppColors.ink.withValues(alpha: 0)],
                stops: const [0, 0.53],
              ),
            ),
          ),
          Positioned(
            top: MediaQuery.viewPaddingOf(context).top + AppSpacing.s8,
            left: 20,
            child: _RoundIcon(icon: LucideIcons.arrowLeft, onTap: onBack),
          ),
          if (onMenu != null)
            Positioned(
              top: MediaQuery.viewPaddingOf(context).top + AppSpacing.s8,
              right: 20,
              child: Builder(
                builder: (anchor) =>
                    _RoundIcon(icon: LucideIcons.ellipsis, onTap: () => onMenu!(anchor)),
              ),
            ),
        ],
      ),
    );
  }
}

class _CoverPlaceholder extends StatelessWidget {
  const _CoverPlaceholder({this.isDefault = false});

  /// «Сохранённые» has a cover of its OWN rather than an empty frame waiting for a photo that is
  /// never coming — see [CollectionCover] for why this folder is drawn and not photographed.
  final bool isDefault;

  @override
  Widget build(BuildContext context) => isDefault
      ? const ColoredBox(
          color: AppColors.ink,
          child: Center(child: Icon(LucideIcons.bookmark, size: 44, color: AppColors.paper)),
        )
      : const ColoredBox(
          color: AppColors.track,
          child: Center(child: Icon(LucideIcons.image, size: 34, color: AppColors.tertiary)),
        );
}

/// Translucent dark round button over the cover (white glyph on the photo).
class _RoundIcon extends StatelessWidget {
  const _RoundIcon({required this.icon, required this.onTap});
  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      child: InkResponse(
        onTap: onTap,
        radius: 24,
        child: Container(
          width: 34,
          height: 34,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: AppColors.ink.withValues(alpha: 0.38),
          ),
          child: Icon(icon, size: 17, color: Colors.white),
        ),
      ),
    );
  }
}

/// Three-swatch density legend (§4). Wraps so it never clips (rule 16).
class _DensityLegend extends StatelessWidget {
  const _DensityLegend({required this.density});
  final CollectionDensity density;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Wrap(
      spacing: 14,
      runSpacing: 6,
      children: [
        _item(InkDensity.filled, l.collectionDensityConfirmed(density.confirmed)),
        _item(InkDensity.halftone, l.collectionDensityFamiliar(density.familiar)),
        _item(InkDensity.outline, l.collectionDensityInProgress(density.inProgress)),
      ],
    );
  }

  Widget _item(InkDensity d, String label) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        SizedBox(
          width: 9,
          height: 9,
          child: d == InkDensity.outline
              ? DecoratedBox(
                  decoration: BoxDecoration(
                    border: Border.all(color: AppInkDensity.outlineColor, width: AppInkDensity.outlineWidth),
                  ),
                )
              : ColoredBox(color: AppInkDensity.solid(d)),
        ),
        const SizedBox(width: 6),
        Text(label, style: AppText.transcription.copyWith(fontSize: 11.5)),
      ],
    );
  }
}

/// State-dependent primary action. Triage/review are ink-filled; practice is a
/// quiet outline (кадр 2.3 — «долг закрыт: кнопка становится тихой, контурной»).
class _CtaButton extends StatelessWidget {
  const _CtaButton({required this.cta, required this.onTriage, required this.onSession});
  final HomeCta cta;
  final VoidCallback onTriage;
  final void Function(bool practice, {bool learn}) onSession;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    if (cta.kind == HomeCtaKind.none) return const SizedBox.shrink();
    // New quota spent while new words remain (F13b): the same inactive card as home — the collection's
    // «Свободная тренировка» button sits right below it.
    if (cta.kind == HomeCtaKind.limitReached) return const LimitReachedCard();

    final (String label, String subtitle, VoidCallback onTap, bool filled) = switch (cta.kind) {
      HomeCtaKind.triage => (l.collectionTriageButton(cta.count), l.collectionTriageSubtitle, onTriage, true),
      HomeCtaKind.learn => (l.collectionLearnButton(cta.count), l.collectionLearnSubtitle, () => onSession(false, learn: true), true),
      HomeCtaKind.review => (l.collectionReviewButton(cta.count), l.collectionReviewSubtitle, () => onSession(false), true),
      HomeCtaKind.practice => (l.collectionPracticeButton, l.collectionPracticeSubtitle, () => onSession(true), false),
      HomeCtaKind.limitReached || HomeCtaKind.none => ('', '', () => onSession(false), false), // unreachable; keeps the switch exhaustive
    };

    final fg = filled ? AppColors.paper : AppColors.ink;
    final subColor = filled ? AppColors.paper.withValues(alpha: 0.66) : AppColors.secondary;

    return Material(
      color: filled ? AppColors.ink : Colors.transparent,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(AppRadii.button),
        side: filled ? BorderSide.none : const BorderSide(color: AppInkDensity.outlineColor),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () {
          AppHaptics.light();
          onTap();
        },
        child: Padding(
          padding: const EdgeInsets.fromLTRB(18, 13, 18, 13),
          child: Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(label, style: AppText.primaryButton.copyWith(color: fg, fontSize: 17)),
                    const SizedBox(height: 3),
                    Text(subtitle, style: AppText.primaryButtonSub.copyWith(color: subColor)),
                  ],
                ),
              ),
              if (filled) ...[
                const SizedBox(width: AppSpacing.s12),
                Container(
                  width: 32,
                  height: 32,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: AppColors.paper.withValues(alpha: 0.16),
                  ),
                  child: const Icon(LucideIcons.arrowRight, size: 16, color: AppColors.paper),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

/// Secondary «Тренировка» button — always under the primary CTA (Training Loop v2 / F17). Quiet
/// outline (never ink-filled: it's a low-priority, repeatable action that moves no progress).
/// Opens a free-practice session over the whole collection.
class _PracticeButton extends StatelessWidget {
  const _PracticeButton({required this.onTap});
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Material(
      color: Colors.transparent,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(AppRadii.button),
        side: const BorderSide(color: AppInkDensity.outlineColor),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () {
          AppHaptics.light();
          onTap();
        },
        child: Container(
          height: 48,
          alignment: Alignment.center,
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(LucideIcons.dumbbell, size: 17, color: AppColors.ink),
              const SizedBox(width: 9),
              Text(l.collectionPracticeButton,
                  style: AppText.sheetButton.copyWith(fontWeight: FontWeight.w600)),
            ],
          ),
        ),
      ),
    );
  }
}

/// Secondary «Разобрать N» button (QA-25) — the same quiet outline as «Тренировка», for the same
/// reason: it is a low-priority action beside whatever the primary CTA turned out to be. It exists
/// while the collection still holds a word nobody has swiped, and disappears with the last one.
class _TriageButton extends StatelessWidget {
  const _TriageButton({required this.count, required this.onTap});
  final int count;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Material(
      color: Colors.transparent,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(AppRadii.button),
        side: const BorderSide(color: AppInkDensity.outlineColor),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () {
          AppHaptics.light();
          onTap();
        },
        child: Container(
          height: 48,
          alignment: Alignment.center,
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(LucideIcons.layers, size: 17, color: AppColors.ink),
              const SizedBox(width: 9),
              Text(l.collectionTriageButton(count),
                  style: AppText.sheetButton.copyWith(fontWeight: FontWeight.w600)),
            ],
          ),
        ),
      ),
    );
  }
}

/// First-contact nudge (reskinned from the old glass banner).
class _TriageBanner extends StatelessWidget {
  const _TriageBanner({required this.onStart, required this.onDismiss});
  final VoidCallback onStart, onDismiss;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return PaperCard(
      padding: const EdgeInsets.all(AppSpacing.cardPadding),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(l.collectionTriageBannerTitle, style: AppText.sheetButton),
                const SizedBox(height: 3),
                Text(l.collectionTriageBannerBody, style: AppText.translation.copyWith(fontSize: 12.5)),
              ],
            ),
          ),
          const SizedBox(width: AppSpacing.s12),
          QuietButton(label: l.collectionTriageBannerStart, onPressed: onStart),
          InkResponse(
            onTap: onDismiss,
            radius: 20,
            child: const Padding(
              padding: EdgeInsets.only(left: 4),
              child: Icon(LucideIcons.x, size: 18, color: AppColors.tertiary),
            ),
          ),
        ],
      ),
    );
  }
}

class _AddWordButton extends StatelessWidget {
  const _AddWordButton({required this.label, required this.onTap});
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(AppRadii.field),
        side: const BorderSide(color: AppInkDensity.outlineColor),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Container(
          height: 48,
          alignment: Alignment.center,
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(LucideIcons.plus, size: 17, color: AppColors.ink),
              const SizedBox(width: 9),
              Text(label, style: AppText.sheetButton.copyWith(fontWeight: FontWeight.w600)),
            ],
          ),
        ),
      ),
    );
  }
}

class _Empty extends StatelessWidget {
  const _Empty({required this.l});
  final AppLocalizations l;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(AppSpacing.screenH, 24, AppSpacing.screenH, 24),
      child: Column(
        children: [
          const Icon(LucideIcons.bookOpen, size: 40, color: AppColors.tertiary),
          const SizedBox(height: AppSpacing.s12),
          Text(l.collectionEmptyTitle, style: AppText.stepTitle.copyWith(fontSize: 20)),
          const SizedBox(height: 6),
          Text(l.collectionEmptyBody,
              textAlign: TextAlign.center, style: AppText.translation.copyWith(color: AppColors.secondary)),
        ],
      ),
    );
  }
}

/// A word row with a two-action swipe reveal (Изменить / Удалить) and a
/// long-press [FloatingContextMenu] (same two, no «Озвучить» — rule 18). The
/// speaker lives on the row's right axis (rule 19).
class _WordRow extends StatefulWidget {
  const _WordRow({
    required this.word,
    required this.showDivider,
    required this.onSpeak,
    required this.onEdit,
    required this.onDelete,
    required this.folder,
    this.onMove,
    this.onTrain,
    this.onEnroll,
    this.onUnenroll,
  });

  final Word word;
  final bool showDivider;
  final VoidCallback onSpeak;

  /// The folder this row is being read IN, or null on a read-only store deck. Non-null is what
  /// opens the full word card (кадр 09) instead of the compact sheet — see [_WordRowState._openCard].
  final SavedFolder? folder;

  /// «Тренировать слово» from the expanded card: a practice session filtered to this term.
  final VoidCallback? onTrain;

  /// The two pool decisions, offered by the expanded card — «Учить это слово» for a word still in
  /// the catalogue, «Убрать из изучения» for one already being studied.
  final VoidCallback? onEnroll, onUnenroll;

  /// Null on a read-only (store-subscribed) collection — the row then has no swipe actions and no
  /// long-press menu, only tap-to-speak.
  final VoidCallback? onEdit, onDelete;

  /// «Перенести в…» — a change of shelf. Absent on a read-only store deck, like edit and delete.
  final VoidCallback? onMove;

  @override
  State<_WordRow> createState() => _WordRowState();
}

class _WordRowState extends State<_WordRow> {
  static const _actionW = 76.0;
  static const _actionsW = _actionW * 2;

  double _offset = 0; // 0 (closed) .. -_actionsW (open)

  void _onDragUpdate(DragUpdateDetails d) =>
      setState(() => _offset = (_offset + d.delta.dx).clamp(-_actionsW, 0.0));
  void _onDragEnd(DragEndDetails d) {
    final v = d.velocity.pixelsPerSecond.dx;
    final open = v < -300 || (v <= 300 && _offset < -_actionsW / 2);
    setState(() => _offset = open ? -_actionsW : 0);
  }

  void _close() => setState(() => _offset = 0);

  /// The word's card.
  ///
  /// From the learner's OWN folder it is the full screen (кадр 09): a photo, the article, and the
  /// ladder cut in as a band under the head — a word they own is worth a page. A store deck's word
  /// keeps the compact sheet (кадр 16e): it is a catalogue entry being browsed, its ladder is empty
  /// by definition, and none of the folder actions apply to it.
  void _openCard() {
    final folder = widget.folder;
    if (folder == null) {
      showWordLadderSheet(
        context: context,
        word: widget.word,
        onSpeak: widget.onSpeak,
        onTrain: () => widget.onTrain?.call(),
        onEnroll: widget.onEnroll,
        onUnenroll: widget.onUnenroll,
      );

      return;
    }

    Navigator.of(context).push(MaterialPageRoute<void>(
      builder: (_) => WordCardScreen(
        subject: WordCardSubject.fromWord(widget.word, folders: [folder]),
        mode: WordCardMode.folder,
        onSpeak: widget.onSpeak,
        onTrain: () => widget.onTrain?.call(),
        onEnroll: widget.onEnroll,
        onUnenroll: widget.onUnenroll,
      ),
    ));
  }

  Future<void> _menu() async {
    AppHaptics.light();
    final l = AppLocalizations.of(context);
    await showFloatingContextMenu(
      context: context,
      anchorContext: context,
      barrierLabel: l.commonCloseMenu,
      actions: [
        ContextMenuAction(icon: LucideIcons.pencil, label: l.actionEdit, onSelected: () => widget.onEdit?.call()),
        // Not destructive: the word keeps its rung, its due date and its place in the pool — only
        // the shelf changes. Styling it in red would say the opposite.
        if (widget.onMove != null)
          ContextMenuAction(
            icon: LucideIcons.folderInput,
            label: l.collectionMoveWord,
            onSelected: () => widget.onMove?.call(),
          ),
        ContextMenuAction(
          icon: LucideIcons.trash2,
          label: l.actionDelete,
          destructive: true,
          onSelected: () => widget.onDelete?.call(),
        ),
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    // Read-only store set: no swipe actions and no long-press menu — but the row still opens its
    // expanded card, which is where the ladder is captioned and the word can be pronounced.
    if (widget.onEdit == null && widget.onDelete == null) {
      return GestureDetector(
        behavior: HitTestBehavior.opaque,
        onTap: _openCard,
        child: _RowBody(word: widget.word, showDivider: widget.showDivider),
      );
    }
    return ClipRect(
      child: Stack(
        children: [
          // Revealed actions behind the row.
          Positioned.fill(
            child: Row(
              mainAxisAlignment: MainAxisAlignment.end,
              children: [
                _SwipeAction(
                  icon: LucideIcons.pencil,
                  label: l.actionEdit,
                  color: AppColors.paper,
                  background: AppColors.ink,
                  onTap: () {
                    _close();
                    widget.onEdit?.call();
                  },
                ),
                _SwipeAction(
                  icon: LucideIcons.trash2,
                  label: l.actionDelete,
                  color: AppColors.destructiveText,
                  background: AppColors.faintInk,
                  onTap: () {
                    _close();
                    widget.onDelete?.call();
                  },
                ),
              ],
            ),
          ),
          Transform.translate(
            offset: Offset(_offset, 0),
            child: GestureDetector(
              behavior: HitTestBehavior.opaque,
              onHorizontalDragUpdate: _onDragUpdate,
              onHorizontalDragEnd: _onDragEnd,
              onLongPress: _menu,
              // A tap closes the swipe when it is open; otherwise it opens the word's card. The
              // swipe wins because closing what you just opened is what a tap means there.
              onTap: _offset != 0 ? _close : _openCard,
              child: _RowBody(word: widget.word, showDivider: widget.showDivider),
            ),
          ),
        ],
      ),
    );
  }
}

class _SwipeAction extends StatelessWidget {
  const _SwipeAction({
    required this.icon,
    required this.label,
    required this.color,
    required this.background,
    required this.onTap,
  });
  final IconData icon;
  final String label;
  final Color color, background;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: 76,
        color: background,
        alignment: Alignment.center,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 17, color: color),
            const SizedBox(height: 5),
            Text(label, style: AppText.transcription.copyWith(fontSize: 12.5, fontWeight: FontWeight.w700, color: color)),
          ],
        ),
      ),
    );
  }
}

class _RowBody extends StatelessWidget {
  const _RowBody({required this.word, required this.showDivider});
  final Word word;
  final bool showDivider;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      // Opaque so the revealed actions don't show through the closed row.
      decoration: BoxDecoration(
        color: AppColors.paper,
        border: showDivider
            ? const Border(bottom: BorderSide(color: AppColors.dividerFaint))
            : null,
      ),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(AppSpacing.screenH, AppSpacing.wordRowPadV, AppSpacing.screenH, AppSpacing.wordRowPadV),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            _Thumb(word: word),
            const SizedBox(width: AppSpacing.wordRowGap),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Flexible(
                        child: Text(word.term,
                            maxLines: 1, overflow: TextOverflow.ellipsis, style: AppText.termInList),
                      ),
                      const SizedBox(width: 7),
                      _TypeBadge(type: word.type),
                    ],
                  ),
                  const SizedBox(height: 3),
                  Text(word.translation,
                      maxLines: 1, overflow: TextOverflow.ellipsis, style: AppText.translation.copyWith(fontSize: 13)),
                  if (word.transcription != null && word.transcription!.isNotEmpty) ...[
                    const SizedBox(height: 2),
                    Text('/${word.transcription}/',
                        style: AppText.transcription.copyWith(fontSize: 11.5, color: AppColors.tertiary)),
                  ],
                ],
              ),
            ),
            const SizedBox(width: AppSpacing.s8),
            // The word's place on the ladder — five dots, no labels and no numbers, so the row stays
            // a row of the dictionary (кадр 16d). A «знаю» word gets a dash instead: it never walked
            // the ladder, and five pale dots would have said «at the very beginning».
            //
            // The dots take the slot the speaker button used to hold. Speech did not disappear —
            // it moved into the expanded card, one tap away, where there is room for it beside the
            // term it pronounces. A row cannot carry both without becoming a control panel.
            //
            // A word that is not in the POOL gets neither: it has no rung, because the ladder
            // measures progress THROUGH a word and this one has not been started. It carries a
            // quiet «в каталоге» instead — the collection is a catalogue now, and most of what it
            // holds is honestly that. Neutral by design: nothing here is wrong or missing.
            if (word.isKnown)
              LadderKnownDash(label: AppLocalizations.of(context).ladderKnownDash)
            else if (!word.enrolled)
              Text(AppLocalizations.of(context).poolInCatalogue, style: AppText.ladderLockedNote)
            else if (word.ladderStep != null)
              LadderDots(step: word.ladderStep),
          ],
        ),
      ),
    );
  }
}

class _Thumb extends StatelessWidget {
  const _Thumb({required this.word});
  final Word word;

  @override
  Widget build(BuildContext context) {
    final radius = BorderRadius.circular(4);
    final placeholder = DecoratedBox(
      decoration: BoxDecoration(color: AppColors.track, borderRadius: radius),
      child: Icon(word.isPhrase ? LucideIcons.quote : LucideIcons.type, size: 18, color: AppColors.tertiary),
    );
    final url = word.imageUrl;
    return SizedBox(
      width: AppSpacing.wordThumb,
      height: AppSpacing.wordThumb,
      child: (url == null || url.isEmpty)
          ? placeholder
          : ClipRRect(
              borderRadius: radius,
              child: Image(image: CachedNetworkImage(url), fit: BoxFit.cover, errorBuilder: (_, _, _) => placeholder),
            ),
    );
  }
}

/// Type badge (§2) — copy reused from the triage keys.
class _TypeBadge extends StatelessWidget {
  const _TypeBadge({required this.type});
  final String type;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final label = switch (type) {
      'word' => l.triageTermTypeWord,
      'phrase' => l.triageTermTypePhrase,
      'idiom' => l.triageTermTypeIdiom,
      'phrasal_verb' => l.triageTermTypePhrasalVerb,
      _ => l.triageTermTypePhrase,
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 2),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(5),
        border: Border.all(color: AppColors.hairline),
      ),
      child: Text(label.toUpperCase(), style: AppText.badge.copyWith(fontSize: 9.5)),
    );
  }
}

/// Cover + centred child, used for the error state.
class _CoverScaffold extends StatelessWidget {
  const _CoverScaffold({required this.imageUrl, required this.child, this.isDefault = false});
  final String? imageUrl;
  final bool isDefault;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        _Cover(imageUrl: imageUrl, isDefault: isDefault, onBack: () => Navigator.of(context).maybePop()),
        Expanded(child: child),
      ],
    );
  }
}
