import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:url_launcher/url_launcher.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import '../../data/local/cached_image_provider.dart';
import '../../data/models.dart';
import '../../data/practice/learning_ladder.dart';
import '../../data/providers.dart';
import 'word_card_subject.dart';

/// The word card — направление «Фото-герой» (кадры 06, 07, 09).
///
/// A whole screen and not a sheet, because a sheet has a ceiling and this does not: the photo is
/// 246 pt of the viewport before a single line of text, and the article under it is four blocks
/// long. It replaces the bottom sheet only where a word is the learner's OWN — from search, and
/// from their own folders. A store deck's word keeps the compact sheet it always had: a catalogue
/// entry is browsed, not studied, and the ladder it would show is empty by definition.
///
/// The three frames are ONE layout with three switches:
///  * a photo, or a lower neutral plate (кадр 06 vs a word nobody photographed);
///  * the ladder strip, present only when the card was opened from a folder (кадр 09);
///  * the action pair, which reads «+ Сохранённые» before a save and names the folder after it
///    (кадр 07). The card does NOT close on a save — the learner opened it to read it.
class WordCardScreen extends ConsumerStatefulWidget {
  const WordCardScreen({
    super.key,
    required this.subject,
    this.mode = WordCardMode.search,
    this.onSpeak,
    this.onSaved,
    this.onTrain,
    this.onEnroll,
    this.onUnenroll,
  });

  final WordCardSubject subject;
  final WordCardMode mode;

  /// Pronounce the term. Null → the button is not drawn (nothing to play).
  final VoidCallback? onSpeak;

  /// The word reached a folder. The search screen re-runs its free search on this, which is what
  /// turns the card's own button into «В „…"» from the server's answer rather than from a local
  /// guess.
  final Future<void> Function(SavedSearchResult saved)? onSaved;

  /// Folder mode only — the same three pool actions the compact sheet offers.
  final VoidCallback? onTrain, onEnroll, onUnenroll;

  /// The photo's Hero tag, shared with the search result that opened this card.
  static String heroTag(String? termId) => 'word-photo-${termId ?? ''}';

  @override
  ConsumerState<WordCardScreen> createState() => _WordCardScreenState();
}

class _WordCardScreenState extends ConsumerState<WordCardScreen> {
  late WordCardSubject _subject = widget.subject;
  bool _saving = false;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final fromFolder = widget.mode == WordCardMode.folder;
    final heroHeight = !_subject.hasPhoto
        ? AppWordCard.heroHeightPlate
        : fromFolder
            ? AppWordCard.heroHeightFromFolder
            : AppWordCard.heroHeight;

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: _subject.hasPhoto ? SystemUiOverlayStyle.light : SystemUiOverlayStyle.dark,
      child: Scaffold(
        backgroundColor: AppColors.paper,
        body: Stack(
          children: [
            ListView(
              padding: EdgeInsets.zero,
              children: [
                Stack(
                  children: [
                    Positioned(
                      top: 0,
                      left: 0,
                      right: 0,
                      height: heroHeight,
                      child: _Hero(subject: _subject, height: heroHeight, l: l),
                    ),
                    Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        // The paper rides 22 pt up onto the photo, so the term sits exactly on the
                        // seam — the single move that makes this composition «фото-герой» and not
                        // «фото, а под ним текст».
                        SizedBox(height: heroHeight - AppWordCard.heroOverlap),
                        _Article(subject: _subject, fromFolder: fromFolder, onSpeak: widget.onSpeak, l: l),
                      ],
                    ),
                  ],
                ),
                _actions(l, fromFolder),
                SizedBox(height: MediaQuery.viewPaddingOf(context).bottom + AppSpacing.s26),
              ],
            ),
            // Outside the scroll view: the two round controls stay put while the article moves,
            // which is what keeps «назад» reachable from anywhere in a long card.
            Positioned(
              top: MediaQuery.viewPaddingOf(context).top + AppSpacing.s8,
              left: AppSpacing.s22,
              right: AppSpacing.s22,
              child: Row(
                children: [
                  _RoundOverlayButton(
                    icon: LucideIcons.arrowLeft,
                    label: l.wordCardBack,
                    onTap: () => Navigator.of(context).maybePop(),
                  ),
                  const Spacer(),
                  if (_menuActions(l).isNotEmpty)
                    Builder(
                      builder: (anchor) => _RoundOverlayButton(
                        icon: LucideIcons.ellipsis,
                        label: l.wordCardMenu,
                        onTap: () => _openMenu(anchor, l),
                      ),
                    ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  // ── actions ───────────────────────────────────────────────────────────────

  /// The bottom of the card, and the one place the three frames actually differ.
  ///
  /// From a FOLDER (кадр 09) the word is already owned, so the main action is the training run and
  /// the folder move drops to a quiet second line. From SEARCH it is the save — a filled button and
  /// a square folder picker beside it (кадр 06) — until the word is in a folder, at which point the
  /// button gutters to an outline that STATES where it is, and the second action moves under it on
  /// its own line (кадр 07). The pair never changes place; only its weight does.
  Widget _actions(AppLocalizations l, bool fromFolder) {
    final saved = _subject.savedIn;

    return Padding(
      padding: const EdgeInsets.fromLTRB(AppSpacing.s22, AppSpacing.s26, AppSpacing.s22, 0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (fromFolder)
            ..._folderActions(l)
          else if (saved != null) ...[
            _OutlineState(label: l.wordCardSavedIn(saved.title)),
            const SizedBox(height: AppSpacing.s12),
            _QuietLink(
              icon: LucideIcons.folderPlus,
              label: l.wordCardAddToAnother,
              onTap: _saving ? null : _pickFolder,
            ),
          ] else ...[
            Row(
              children: [
                Expanded(
                  child: PrimaryButton(
                    label: l.searchSaveToDefault,
                    minHeight: AppWordCard.actionHeight,
                    enabled: !_saving,
                    onPressed: () => _save(null),
                  ),
                ),
                const SizedBox(width: AppSpacing.s12),
                _SquareButton(
                  icon: LucideIcons.folderPlus,
                  label: l.searchAddToCollection,
                  onTap: _saving ? null : _pickFolder,
                ),
              ],
            ),
            const SizedBox(height: 9),
            Text(l.wordCardFolderHint, textAlign: TextAlign.center, style: AppText.searchFootnote),
          ],
        ],
      ),
    );
  }

  /// Кадр 09. The rung decides the verb, exactly as the compact sheet decided it: a word outside
  /// the pool is offered the DECISION («Учить это слово»), a word inside it the run — inert with a
  /// reason while it still has no introduction behind it.
  List<Widget> _folderActions(AppLocalizations l) {
    if (!_subject.enrolled) {
      return [
        PrimaryButton(
          label: l.poolEnrollAction,
          minHeight: AppWordCard.actionHeight,
          onPressed: widget.onEnroll == null
              ? null
              : () {
                  AppHaptics.light();
                  Navigator.of(context).maybePop();
                  widget.onEnroll!();
                },
        ),
        const SizedBox(height: AppSpacing.s8),
        Text(l.poolEnrollNote, textAlign: TextAlign.center, style: AppText.searchFootnote),
      ];
    }

    final trainable = LearningLadder.admitsPractice(_subject.ladderStep);

    return [
      PrimaryButton(
        label: l.ladderTrainWord,
        minHeight: AppWordCard.actionHeight,
        enabled: trainable && widget.onTrain != null,
        onPressed: () {
          Navigator.of(context).maybePop();
          widget.onTrain?.call();
        },
      ),
      if (!trainable) ...[
        const SizedBox(height: AppSpacing.s8),
        Text(l.ladderTrainLockedIntro, textAlign: TextAlign.center, style: AppText.searchFootnote),
      ],
      const SizedBox(height: AppSpacing.s12),
      _QuietLink(
        icon: LucideIcons.folderPlus,
        label: l.wordCardAddToAnother,
        onTap: _saving ? null : _pickFolder,
      ),
    ];
  }

  List<ContextMenuAction> _menuActions(AppLocalizations l) => [
        if (widget.mode == WordCardMode.folder && _subject.enrolled && widget.onUnenroll != null)
          ContextMenuAction(
            icon: LucideIcons.pause,
            label: l.poolUnenrollAction,
            onSelected: () => _confirmUnenroll(l),
          ),
      ];

  Future<void> _openMenu(BuildContext anchor, AppLocalizations l) async {
    AppHaptics.light();
    await showFloatingContextMenu(
      context: context,
      anchorContext: anchor,
      barrierLabel: l.commonCloseMenu,
      actions: _menuActions(l),
    );
  }

  /// «Убрать из изучения» is a PAUSE and the confirmation has to say so, or the learner reads the
  /// verb as a delete and never presses it.
  Future<void> _confirmUnenroll(AppLocalizations l) async {
    final ok = await showCenterAlert(
      context: context,
      title: l.poolUnenrollTitle(_subject.text),
      message: l.poolUnenrollMessage,
      confirmLabel: l.poolUnenrollConfirm,
      cancelLabel: l.commonCancel,
      destructive: false,
    );
    if (ok != true || !mounted) return;
    AppHaptics.light();
    Navigator.of(context).maybePop();
    widget.onUnenroll?.call();
  }

  // ── saving ────────────────────────────────────────────────────────────────

  static const _newCollectionSentinel = 'new';

  Future<void> _pickFolder() async {
    final l = AppLocalizations.of(context);
    // From the LOCAL mirror, like every other screen — the shelf has to be listable offline.
    final folders = ref.read(collectionsProvider).value ?? const <WordCollection>[];
    final own = folders.where((c) => c.isOwned && !c.isSubscribed).toList();

    final choice = await showAppBottomSheet<String>(
      context: context,
      builder: (context) => Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.symmetric(vertical: AppSpacing.s8),
            child: Text(l.searchAddToCollection, style: AppText.sectionLabel),
          ),
          for (final folder in own)
            ListTile(
              title: Text(folder.title, style: AppText.translation),
              onTap: () => Navigator.of(context).pop(folder.id),
            ),
          ListTile(
            leading: const Icon(LucideIcons.plus, size: 18, color: AppColors.ink),
            title: Text(l.searchNewCollection, style: AppText.translation),
            onTap: () => Navigator.of(context).pop(_newCollectionSentinel),
          ),
        ],
      ),
    );

    if (choice == null || !mounted) return;
    if (choice == _newCollectionSentinel) {
      final created = await _createCollection(l);
      if (created == null) return;
      await _save(created);

      return;
    }
    await _save(choice);
  }

  Future<String?> _createCollection(AppLocalizations l) async {
    final controller = TextEditingController();
    final title = await showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        backgroundColor: AppColors.surfaceRaised,
        title: Text(l.searchNewCollection, style: AppText.collectionNameCard),
        content: TextField(controller: controller, autofocus: true, style: AppText.translation),
        actions: [
          TextButton(onPressed: () => Navigator.of(context).pop(), child: Text(l.commonCancel)),
          TextButton(
            onPressed: () => Navigator.of(context).pop(controller.text.trim()),
            child: Text(l.commonSave),
          ),
        ],
      ),
    );
    if (title == null || title.isEmpty || !mounted) return null;

    try {
      final collection = await ref.read(apiClientProvider).createCollection(title: title);

      return collection.id;
    } catch (_) {
      if (mounted) _failed(l);

      return null;
    }
  }

  Future<void> _save(String? collectionId) async {
    final l = AppLocalizations.of(context);
    setState(() => _saving = true);
    SavedSearchResult? done;
    try {
      final saved = await ref.read(apiClientProvider).addSearchResult(
            lookupId: _subject.lookupId,
            termId: _subject.termId,
            collectionId: collectionId,
          );
      if (!mounted) return;
      setState(() {
        _saving = false;
        // The card STAYS — кадр 07 is the same card with a different button, not a screen the save
        // dismisses. Folding the answer back into the subject is what turns the pair into its
        // saved state without a second network round trip.
        _subject = _subject.copyWith(folders: [
          SavedFolder(
            id: saved.collectionId,
            title: saved.collectionTitle,
            isDefault: saved.collectionIsDefault,
          ),
          ..._subject.folders.where((f) => f.id != saved.collectionId),
        ]);
      });
      AppHaptics.light();
      done = saved;
    } catch (_) {
      if (!mounted) return;
      setState(() => _saving = false);
      _failed(l);
    }

    // OUTSIDE the try, deliberately. What follows a save is bookkeeping — a sync nudge, a re-run
    // of the free search — and the word is already in the folder by the time any of it runs.
    // Letting those failures fall into the catch above made an offline device answer a SUCCESSFUL
    // save with «Не удалось сохранить», which is the one lie this screen must not tell (caught on
    // the simulator, DSN-2).
    if (done == null) return;
    try {
      await widget.onSaved?.call(done);
    } catch (_) {
      // The save stands. Nothing to tell the learner.
    }
  }

  void _failed(AppLocalizations l) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(l.searchSaveFailed)));
  }
}

// ── the photo ───────────────────────────────────────────────────────────────

class _Hero extends StatelessWidget {
  const _Hero({required this.subject, required this.height, required this.l});

  final WordCardSubject subject;
  final double height;
  final AppLocalizations l;

  @override
  Widget build(BuildContext context) {
    final author = subject.imageAuthor;

    Widget plate = ColoredBox(
      color: AppColors.photoPlate,
      child: Center(
        child: Text(l.wordCardNoPhoto, style: AppText.photoCredit),
      ),
    );

    if (subject.hasPhoto) {
      plate = Image(
        image: CachedNetworkImage(subject.imageUrl!),
        fit: BoxFit.cover,
        errorBuilder: (_, _, _) => const ColoredBox(color: AppColors.photoPlate),
      );
    }

    return SizedBox(
      height: height,
      child: Stack(
        fit: StackFit.expand,
        children: [
          if (subject.termId != null)
            Hero(tag: WordCardScreen.heroTag(subject.termId), child: plate)
          else
            plate,
          // A scrim only where the round controls sit, so light glyphs read over any photograph.
          if (subject.hasPhoto)
            DecoratedBox(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [AppColors.ink.withValues(alpha: 0.42), AppColors.ink.withValues(alpha: 0)],
                  stops: const [0, 0.5],
                ),
              ),
            ),
          // Attribution is the Pexels licence's half of the bargain: small, on the photo, and — when
          // the photographer has a page — a link to it.
          if (subject.hasPhoto && (author ?? '').isNotEmpty)
            Positioned(
              left: AppSpacing.s22,
              // Clear of the paper that rides up onto the photo — an attribution half-covered by
              // the article is an attribution nobody can read, and the licence asks for a readable
              // one.
              bottom: AppWordCard.heroOverlap + AppSpacing.s16,
              child: _PhotoCredit(author: author!, url: subject.imageAuthorUrl, l: l),
            ),
        ],
      ),
    );
  }
}

class _PhotoCredit extends StatelessWidget {
  const _PhotoCredit({required this.author, required this.url, required this.l});

  final String author;
  final String? url;
  final AppLocalizations l;

  @override
  Widget build(BuildContext context) {
    final text = Text(l.wordCardPhotoCredit(author), style: AppText.photoCredit);
    if ((url ?? '').isEmpty) return text;

    return GestureDetector(
      onTap: () => launchUrl(Uri.parse(url!), mode: LaunchMode.externalApplication),
      child: text,
    );
  }
}

// ── the article ─────────────────────────────────────────────────────────────

class _Article extends StatelessWidget {
  const _Article({required this.subject, required this.fromFolder, required this.onSpeak, required this.l});

  final WordCardSubject subject;
  final bool fromFolder;
  final VoidCallback? onSpeak;
  final AppLocalizations l;

  @override
  Widget build(BuildContext context) {
    final translation = subject.translation ?? '';
    final description = subject.description ?? '';
    final example = subject.example ?? '';

    return Container(
      decoration: const BoxDecoration(
        color: AppColors.paper,
        borderRadius: BorderRadius.vertical(top: Radius.circular(AppRadii.alert)),
      ),
      padding: const EdgeInsets.fromLTRB(AppSpacing.s22, AppSpacing.s22, AppSpacing.s22, 0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              Flexible(child: Text(subject.text, style: AppText.cardTerm)),
              if (onSpeak != null) ...[
                const SizedBox(width: AppSpacing.s12),
                _SpeakButton(onTap: onSpeak!, label: l.wordCardSpeak),
              ],
            ],
          ),
          // Transcription and level share ONE line under the term (кадр 06) — the term keeps the
          // line above it to itself, which is what makes it read as a headword.
          if ((subject.transcription ?? '').isNotEmpty || (subject.cefr ?? '').isNotEmpty) ...[
            const SizedBox(height: 10),
            Row(
              children: [
                if ((subject.transcription ?? '').isNotEmpty)
                  Text('/${subject.transcription}/', style: AppText.cardTranscription),
                if ((subject.cefr ?? '').isNotEmpty) ...[
                  const SizedBox(width: AppSpacing.s12),
                  _LevelBadge(subject.cefr!),
                ],
              ],
            ),
          ],
          // Кадр 09: the ladder is the FIRST sheet under the head. Opening a word from a folder,
          // the first question is «where am I with it», not «what does it mean».
          //
          // Only when there IS a standing to report. A word still in the catalogue has none, and
          // the sentence that used to sit here — «слово в каталоге, ты его пока не учишь» — pushed
          // the translation down the page to say what the button below already says by existing.
          if (fromFolder && (subject.isKnown || subject.enrolled)) ...[
            const SizedBox(height: 18),
            _LadderStrip(subject: subject, l: l),
          ],
          if (translation.isNotEmpty) ...[
            const SizedBox(height: AppSpacing.s16),
            Text(translation, style: AppText.cardTranslation),
          ],
          if (description.isNotEmpty) ...[
            const SizedBox(height: 20),
            _RaisedSheet(child: Text(description, style: AppText.cardDefinition)),
          ],
          if (example.isNotEmpty) ...[
            const SizedBox(height: AppSpacing.s12),
            _RaisedSheet(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(l.wordCardExampleLabel.toUpperCase(),
                      style: AppText.sectionLabel.copyWith(color: AppColors.tertiary)),
                  const SizedBox(height: 9),
                  _ExampleLine(sentence: example, term: subject.text),
                  if ((subject.exampleTranslation ?? '').isNotEmpty) ...[
                    const SizedBox(height: AppSpacing.s8),
                    Text(subject.exampleTranslation!, style: AppText.cardExampleTranslation),
                  ],
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }
}

/// «Значение» and «Пример» — two lifted leaves, so they read as two separate thoughts rather than
/// as one wall of text (кадр 06). A leaf, not a card: hairline plus the raised paper, no shadow.
class _RaisedSheet extends StatelessWidget {
  const _RaisedSheet({required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) => Container(
        width: double.infinity,
        padding: const EdgeInsets.all(AppSpacing.s16),
        decoration: BoxDecoration(
          color: AppColors.surfaceRaised,
          borderRadius: BorderRadius.circular(AppRadii.sheet),
          border: Border.all(color: AppColors.hairline),
        ),
        child: child,
      );
}

/// Кадр 09 — the acquisition ladder, cut in as a band between the head and the article.
class _LadderStrip extends StatelessWidget {
  const _LadderStrip({required this.subject, required this.l});

  final WordCardSubject subject;
  final AppLocalizations l;

  @override
  Widget build(BuildContext context) {
    // A «знаю» word never walked the ladder, and five pale dots would claim «at the very
    // beginning» — the opposite of what «знаю» means. A dash says it instead, and unlike a word
    // still in the catalogue this IS a standing worth reporting: no action on the card implies it.
    if (subject.isKnown) {
      return _RaisedSheet(child: Row(children: [LadderKnownDash(label: l.ladderKnownDash)]));
    }

    final step = subject.ladderStep;
    final position = step == null ? 0 : LadderDots.indexFor(step);

    return _RaisedSheet(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(l.wordCardProgressLabel.toUpperCase(),
                  style: AppText.sectionLabel.copyWith(color: AppColors.tertiary)),
              Text(
                l.wordCardProgressCount(position + 1, LadderDots.rungs.length),
                style: AppText.levelMark.copyWith(color: AppColors.secondary),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.s12),
          LadderTrack(
            step: step,
            labels: [l.ladderStep0, l.ladderStep1, l.ladderStep3, l.ladderStep4, l.ladderStep5],
          ),
        ],
      ),
    );
  }
}

/// The example with its term picked out — the same emphasis the intro card uses, so a word looks
/// the same before and after it is saved.
class _ExampleLine extends StatelessWidget {
  const _ExampleLine({required this.sentence, required this.term});

  final String sentence;
  final String term;

  @override
  Widget build(BuildContext context) {
    final at = sentence.toLowerCase().indexOf(term.toLowerCase());
    if (at < 0) return Text(sentence, style: AppText.cardExample);

    return Text.rich(
      TextSpan(children: [
        TextSpan(text: sentence.substring(0, at)),
        TextSpan(
          text: sentence.substring(at, at + term.length),
          style: const TextStyle(fontWeight: FontWeight.w500),
        ),
        TextSpan(text: sentence.substring(at + term.length)),
      ]),
      style: AppText.cardExample,
    );
  }
}

// ── small parts ─────────────────────────────────────────────────────────────

class _LevelBadge extends StatelessWidget {
  const _LevelBadge(this.level);

  final String level;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
        decoration: BoxDecoration(
          color: AppColors.inkBody,
          borderRadius: BorderRadius.circular(5),
        ),
        child: Text(level, style: AppText.cardLevelBadge),
      );
}

/// The speaker beside the term: an INK disc on the card (кадр 06), not an outline — it is the only
/// thing on the head line that can be pressed, and it has to look it.
class _SpeakButton extends StatelessWidget {
  const _SpeakButton({required this.onTap, required this.label});

  final VoidCallback onTap;
  final String label;

  @override
  Widget build(BuildContext context) => Semantics(
        button: true,
        label: label,
        child: InkResponse(
          onTap: () {
            AppHaptics.light();
            onTap();
          },
          radius: AppWordCard.speakButton / 2 + 6,
          child: Container(
            width: AppWordCard.speakButton,
            height: AppWordCard.speakButton,
            alignment: Alignment.center,
            decoration: const BoxDecoration(shape: BoxShape.circle, color: AppColors.ink),
            child: const Icon(LucideIcons.volume2, size: 20, color: AppColors.paper),
          ),
        ),
      );
}

class _RoundOverlayButton extends StatelessWidget {
  const _RoundOverlayButton({required this.icon, required this.label, required this.onTap});

  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Semantics(
        button: true,
        label: label,
        child: InkResponse(
          onTap: onTap,
          radius: AppWordCard.overlayButton / 2 + 6,
          child: Container(
            width: AppWordCard.overlayButton,
            height: AppWordCard.overlayButton,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: AppColors.paper.withValues(alpha: 0.86),
            ),
            child: Icon(icon, size: 19, color: AppColors.ink),
          ),
        ),
      );
}

/// The square beside the main button — «в другую папку». Same height as its neighbour, outline
/// only: two filled buttons side by side would be two primary actions.
class _SquareButton extends StatelessWidget {
  const _SquareButton({required this.icon, required this.label, required this.onTap});

  final IconData icon;
  final String label;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) => Semantics(
        button: true,
        label: label,
        child: Material(
          // The same radius the main button carries — the pair has to read as one control split
          // in two, and two different corner radii side by side read as two unrelated buttons.
          color: AppColors.surfaceRaised,
          borderRadius: BorderRadius.circular(AppRadii.button),
          clipBehavior: Clip.antiAlias,
          child: InkWell(
            onTap: onTap,
            child: Container(
              width: AppWordCard.actionHeight,
              height: AppWordCard.actionHeight,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(AppRadii.button),
                border: Border.all(color: AppColors.track),
              ),
              child: Icon(icon, size: 21, color: AppColors.ink),
            ),
          ),
        ),
      );
}

/// Кадр 07 — the button gone out to a STATE: an outline on layered paper with a green tick. It is
/// not disabled-looking, it is finished-looking, which is a different thing and the difference is
/// the whole frame.
class _OutlineState extends StatelessWidget {
  const _OutlineState({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) => Container(
        height: AppWordCard.actionHeight,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: AppColors.surfaceRaised,
          borderRadius: BorderRadius.circular(AppRadii.button),
          border: Border.all(color: AppColors.dashed, width: 1.5),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(LucideIcons.check, size: 18, color: AppColors.verdictKnown),
            const SizedBox(width: 9),
            Flexible(
              child: Text(label, maxLines: 1, overflow: TextOverflow.ellipsis, style: AppText.sheetButton),
            ),
          ],
        ),
      );
}

/// The second action, as a line rather than a button — it is still there, it just no longer argues
/// with the state above it (кадры 07/09).
/// The second action under the main button — «Добавить в другую коллекцию».
///
/// Set in INK, not terracotta. Rule 01 keeps the interface monochrome and terracotta is reserved
/// for what destroys: «Удалить аккаунт», the «Не то» verdict. Adding a word to one more collection
/// destroys nothing, and painting it in the delete colour made the safest action on the card look
/// like the dangerous one (QA-OBS-19).
class _QuietLink extends StatelessWidget {
  const _QuietLink({required this.icon, required this.label, required this.onTap});

  final IconData icon;
  final String label;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) => Semantics(
        button: true,
        label: label,
        child: InkWell(
          onTap: onTap,
          child: SizedBox(
            height: 46,
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(icon, size: 18, color: AppColors.ink),
                const SizedBox(width: AppSpacing.s8),
                Text(
                  label,
                  style: AppTextExercise.answerAuxButton.copyWith(color: AppColors.ink, fontSize: 15),
                ),
              ],
            ),
          ),
        ),
      );
}
