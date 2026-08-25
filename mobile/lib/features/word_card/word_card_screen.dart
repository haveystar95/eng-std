import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:url_launcher/url_launcher.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import '../../data/app_settings.dart';
import '../../data/local/cached_image_provider.dart';
import '../../data/models.dart';
import '../../data/practice/learning_ladder.dart';
import '../../data/providers.dart';
import '../search/search_pair.dart' show LearningPair;
import 'collection_saver.dart';
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
    this.pair,
    this.onSpeak,
    this.onSaved,
    this.onTrain,
    this.onEnroll,
    this.onUnenroll,
  });

  final WordCardSubject subject;
  final WordCardMode mode;

  /// The pair this word was found in, when the caller knows one — «изучаемый ← язык поддержки».
  ///
  /// It is the SHEET's business: «одна коллекция — одна пара» (DECISIONS п. 81), so a collection of
  /// another pair is not offered at all. Null when the caller has no pair to state (the pill has
  /// not loaded, or the card was opened from a folder rather than from search), and then the sheet
  /// lists every own collection exactly as it always did — the server's gate is still the truth,
  /// and this filter only spares the learner a refusal it can see coming.
  final LearningPair? pair;

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

  /// A save happened on THIS card, in this visit. It is the difference between «сохранено» and «в
  /// коллекции» — a statement about a moment versus a statement about a fact.
  bool _justSaved = false;

  /// The learner's shelf as the LOCAL mirror last had it — written by `build`, read by the sheet.
  /// The sheet opens from a tap, where watching a provider is not allowed, and it must not open on
  /// a list it fetched itself.
  List<WordCollection> _collections = const [];

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final fromFolder = widget.mode == WordCardMode.folder;
    // Subscribed UNCONDITIONALLY, and this is the only place that does it. The mirror's first
    // emission lands after the first frame, so a card that merely `read` it when the sheet opened
    // got «no collections at all» on a cold open — and, watching it only in one branch, never
    // rebuilt when it arrived.
    _collections = ref.watch(collectionsProvider).value ?? const <WordCollection>[];
    // «Подсказка произношения» — the learner's own switch, defaulted from their own alphabet. The
    // card is one of the two places it is honoured; the trainers read it nowhere.
    final showTransliteration = ref.watch(transliterationEnabledProvider);
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
                        _Article(
                          subject: _subject,
                          fromFolder: fromFolder,
                          onSpeak: widget.onSpeak,
                          showTransliteration: showTransliteration,
                          l: l,
                        ),
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
            // Straight after a save the line says what just HAPPENED — the collection and the fact
            // that the word is now being studied, which is the half a learner cannot see. On a card
            // opened later the same slot states where the word lives; nothing «just happened» then,
            // and claiming it did would be a lie about the pool.
            SavedStateLine(
              label: _justSaved ? l.searchSavedTo(saved.title) : l.wordCardSavedIn(saved.title),
            ),
            const SizedBox(height: AppSpacing.s12),
            QuietLinkAction(
              icon: LucideIcons.folderPlus,
              label: l.wordCardAddToAnother,
              onTap: _saving ? null : _pickCollection,
            ),
          ] else if (_defaultIsOfAnotherPair) ...[
            // «Сохранённые» exists and studies another language, so the one-tap save has nowhere to
            // land: the server would refuse it, and offering a button that cannot work is worse
            // than not offering it. Choosing — or making — a collection of THIS pair becomes the
            // main action instead (DECISIONS п. 81).
            PrimaryButton(
              label: l.searchAddToCollection,
              minHeight: AppWordCard.actionHeight,
              enabled: !_saving,
              onPressed: _pickCollection,
            ),
            const SizedBox(height: 9),
            Text(l.searchPairNoDefault, textAlign: TextAlign.center, style: AppText.searchFootnote),
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
                  onTap: _saving ? null : _pickCollection,
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
      QuietLinkAction(
        icon: LucideIcons.folderPlus,
        label: l.wordCardAddToAnother,
        onTap: _saving ? null : _pickCollection,
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

  /// The sheet, the «new collection» dialog and the 422 repair, all of it shared with the search
  /// screen — see [CollectionSaver]. Rebuilt on every read so it always carries the shelf `build`
  /// last saw.
  CollectionSaver get _saver =>
      CollectionSaver(ref: ref, collections: _collections, pair: widget.pair);

  bool get _defaultIsOfAnotherPair => _saver.defaultIsOfAnotherPair;

  Future<void> _pickCollection() async {
    setState(() => _saving = true);
    final saved = await _saver.pickAndSave(context, _subject);
    if (!mounted) return;
    await _apply(saved);
  }

  Future<void> _save(String? collectionId) async {
    setState(() => _saving = true);
    final saved = await _saver.save(context, _subject, collectionId);
    if (!mounted) return;
    await _apply(saved);
  }

  /// Fold the server's answer back into the card.
  Future<void> _apply(SavedSearchResult? saved) async {
    setState(() {
      _saving = false;
      if (saved == null) return;
      _justSaved = true;
      // The card STAYS — кадр 07 is the same card with a different button, not a screen the save
      // dismisses. Folding the answer back into the subject is what turns the pair into its
      // saved state without a second network round trip.
      _subject = _subject.copyWith(
        folders: [
          SavedFolder(
            id: saved.collectionId,
            title: saved.collectionTitle,
            isDefault: saved.collectionIsDefault,
          ),
          ..._subject.folders.where((f) => f.id != saved.collectionId),
        ],
      );
    });
    if (saved == null) return;
    AppHaptics.light();

    // What follows a save is bookkeeping — a sync nudge, a re-run of the free search — and the word
    // is already in the collection by the time any of it runs. Letting those failures reach the
    // learner made an offline device answer a SUCCESSFUL save with «Не удалось сохранить», which is
    // the one lie this screen must not tell (caught on the simulator, DSN-2).
    try {
      await widget.onSaved?.call(saved);
    } catch (_) {
      // The save stands. Nothing to tell the learner.
    }
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
      child: Center(child: Text(l.wordCardNoPhoto, style: AppText.photoCredit)),
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
                  colors: [
                    AppColors.ink.withValues(alpha: 0.42),
                    AppColors.ink.withValues(alpha: 0),
                  ],
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
  const _Article({
    required this.subject,
    required this.fromFolder,
    required this.onSpeak,
    required this.showTransliteration,
    required this.l,
  });

  final WordCardSubject subject;
  final bool fromFolder;
  final VoidCallback? onSpeak;

  /// «Подсказка произношения». False hides the reading line and nothing else — the IPA, which is
  /// a different thing and has no switch, stays where it is.
  final bool showTransliteration;
  final AppLocalizations l;

  @override
  Widget build(BuildContext context) {
    // The pinned reading first, the alternatives after it, joined by « / ». A word with one
    // reading prints exactly what it printed before the list existed.
    final translation = subject.readings.join(' / ');
    final description = subject.description ?? '';
    final example = subject.example ?? '';
    final transliteration = showTransliteration ? (subject.transliteration ?? '') : '';
    final synonyms = subject.synonyms;

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
          // Transcription, the reading hint and the level share ONE line under the term (кадр 06) —
          // the term keeps the line above it to itself, which is what makes it read as a headword.
          //
          // The reading sits BESIDE the IPA, never in place of it: one is a notation the learner
          // has to have been taught, the other is the same word in letters they already read, and
          // a dictionary prints both. Slashes are the IPA's, so this takes the brackets.
          if ((subject.transcription ?? '').isNotEmpty ||
              transliteration.isNotEmpty ||
              (subject.cefr ?? '').isNotEmpty) ...[
            const SizedBox(height: 10),
            Wrap(
              crossAxisAlignment: WrapCrossAlignment.center,
              spacing: AppSpacing.s12,
              runSpacing: AppSpacing.s4,
              children: [
                if ((subject.transcription ?? '').isNotEmpty)
                  Text('/${subject.transcription}/', style: AppText.cardTranscription),
                if (transliteration.isNotEmpty)
                  Text('[$transliteration]', style: AppText.cardTransliteration),
                if ((subject.cefr ?? '').isNotEmpty) _LevelBadge(subject.cefr!),
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
          // «также: goal, aim» — the word's near-synonyms, in the language being learned. Drawn
          // ONLY when there are some: an empty «также:» would be the card claiming the станок has
          // been over this word and found nothing, which is not what an empty list means.
          if (synonyms.isNotEmpty) ...[
            const SizedBox(height: AppSpacing.s8),
            Text(l.wordCardAlso(synonyms.join(', ')), style: AppText.cardSynonyms),
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
                  Text(
                    l.wordCardExampleLabel.toUpperCase(),
                    style: AppText.sectionLabel.copyWith(color: AppColors.tertiary),
                  ),
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
      return _RaisedSheet(
        child: Row(children: [LadderKnownDash(label: l.ladderKnownDash)]),
      );
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
              Text(
                l.wordCardProgressLabel.toUpperCase(),
                style: AppText.sectionLabel.copyWith(color: AppColors.tertiary),
              ),
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
      TextSpan(
        children: [
          TextSpan(text: sentence.substring(0, at)),
          TextSpan(
            text: sentence.substring(at, at + term.length),
            style: const TextStyle(fontWeight: FontWeight.w500),
          ),
          TextSpan(text: sentence.substring(at + term.length)),
        ],
      ),
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
    decoration: BoxDecoration(color: AppColors.inkBody, borderRadius: BorderRadius.circular(5)),
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
