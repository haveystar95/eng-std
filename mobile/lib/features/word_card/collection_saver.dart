import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import '../../data/api_client.dart' show termLanguageMismatch;
import '../../data/models.dart';
import '../../data/providers.dart';
import '../../l10n/language_endonyms.dart';
import '../search/search_pair.dart' show LearningPair;
import 'word_card_subject.dart';

/// «Куда положить это слово» — the sheet, the «new collection» dialog, the save, and the repair when
/// the server refuses the pair.
///
/// Lifted out of [WordCardScreen] because the SEARCH SCREEN needs the identical flow. A word whose
/// card already exists — one the learner just built, or an orphan whose only collection was deleted
/// — has nothing left to build, so the offer on the result is «положить в коллекцию» and it has to
/// be the SAME offer the card makes: same pair filter, same «создать коллекцию этой пары», same
/// 422 repair. Two copies of this would drift, and the half that drifted would be the one that
/// decides which collections a word may legally enter (DECISIONS п. 81, п. 141).
class CollectionSaver {
  const CollectionSaver({required this.ref, required this.collections, this.pair});

  final WidgetRef ref;

  /// The learner's shelf as the LOCAL mirror last had it. Passed in rather than read here: the
  /// sheet opens from a tap, where watching a provider is not allowed, and it must not open on a
  /// list it fetched itself.
  final List<WordCollection> collections;

  /// The pair the word was found in — «изучаемый ← язык поддержки». Null when the caller has none
  /// to state, and then every own collection is offered exactly as it always was: the server's gate
  /// is still the truth, and this filter only spares the learner a refusal it can see coming.
  final LearningPair? pair;

  static const _newCollectionSentinel = 'new';

  /// The learner's own collections, and only the ones this word can actually go into.
  ///
  /// FILTERED BY THE WHOLE PAIR, both halves. The server's gate checks only the studied language —
  /// that is the rule that makes a card answerable — but a collection whose SUPPORT language
  /// differs would show this word's translation in a language it does not hold, so offering it
  /// would be offering a collection that half-works.
  List<WordCollection> get collectionsForPair {
    final own = collections.where((c) => c.isOwned && !c.isSubscribed);
    final p = pair;

    return (p == null ? own : own.where((c) => c.targetLang == p.learned && c.sourceLang == p.support))
        .toList();
  }

  /// «Сохранённые» exists and is of another pair — so the one-tap save into it cannot succeed.
  ///
  /// Only ever true of a default that EXISTS. A learner who has never saved anything has no default
  /// collection yet, and the server makes it in the pair of the lookup itself, so the one-tap save
  /// is exactly right for them.
  bool get defaultIsOfAnotherPair {
    final p = pair;
    if (p == null) return false;
    for (final c in collections) {
      if (!c.isDefault) continue;

      return c.targetLang != p.learned || c.sourceLang != p.support;
    }

    return false;
  }

  /// Open the sheet, then save into whatever it returns. Null when the learner backed out, or when
  /// the save did not happen. [enroll] names WHICH ACT this is — see [save].
  Future<SavedSearchResult?> pickAndSave(
    BuildContext context,
    WordCardSubject subject, {
    required bool enroll,
  }) async {
    final l = AppLocalizations.of(context);
    final offered = collectionsForPair;
    final holding = {for (final f in subject.folders) f.id};

    final p = pair;

    final choice = await showAppBottomSheet<String>(
      context: context,
      builder: (context) => Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          // The heading says WHAT this is; the badge under it says WHICH SHELF the list belongs to.
          // Both, because the list is filtered and a filtered list that does not say so reads as a
          // list with collections missing from it.
          Padding(
            padding: const EdgeInsets.only(top: AppSpacing.s8, bottom: AppSpacing.s4),
            child: Text(
              l.searchAddToCollection,
              textAlign: TextAlign.center,
              style: AppText.sectionLabel,
            ),
          ),
          if (p != null)
            Padding(
              padding: const EdgeInsets.only(bottom: AppSpacing.s8),
              child: Center(child: PairBadge(learned: p.learned, support: p.support)),
            ),
          const Divider(height: 1, thickness: 1, color: AppColors.hairline),
          for (final collection in offered)
            if (holding.contains(collection.id))
              // Inert, and it says why: tapping it would spend a round trip to be told the word is
              // already there. Shown rather than hidden, because a learner looking for a collection
              // they remember putting this word in has to find it.
              AppSheetRow(
                enabled: false,
                title: Text(
                  l.searchAlreadyIn(collection.title),
                  style: AppText.translation.copyWith(color: AppColors.tertiary),
                ),
                trailing: _WordCount(collection.wordsCount, muted: true),
              )
            else
              AppSheetRow(
                title: Text(collection.title, style: AppText.translation),
                // How big the shelf is — the one fact that tells «Аэропорт» with four words apart
                // from «Аэропорт» with two hundred, which is what the learner is actually choosing
                // between when two collections share a topic.
                trailing: _WordCount(collection.wordsCount),
                onTap: () => Navigator.of(context).pop(collection.id),
              ),
          // ALWAYS present, and it names the pair. When the list above is empty — the learner's
          // first word in this pair — it is the only way forward, and it must not read as an
          // afterthought, so it keeps the ink weight of a real row rather than fading out.
          AppSheetRow(
            leading: const Icon(LucideIcons.plus, size: 18, color: AppColors.ink),
            title: Text(
              p == null ? l.searchNewCollection : l.searchNewCollectionInPair(_pairLabel(p)),
              style: AppText.translation,
            ),
            onTap: () => Navigator.of(context).pop(_newCollectionSentinel),
          ),
        ],
      ),
    );

    if (choice == null || !context.mounted) return null;
    if (choice == _newCollectionSentinel) {
      final created = await _createCollection(context, l, learned: pair?.learned, support: pair?.support);
      if (created == null || !context.mounted) return null;

      return save(context, subject, created, enroll: enroll);
    }

    return save(context, subject, choice, enroll: enroll);
  }

  /// The save itself. [collectionId] null means «Сохранённые» — the server makes it if it has to.
  ///
  /// [enroll] is the ACT the learner chose, and the two are genuinely different things rather than
  /// a setting on one thing. «Сохранить» (false) files the word on a shelf, where the swipe pass
  /// finds it; «Учить сразу» (true) files it AND puts it in the trainer's queue. It is required, not
  /// defaulted: a default is how one of the two would get picked by accident at a new call site.
  Future<SavedSearchResult?> save(
    BuildContext context,
    WordCardSubject subject,
    String? collectionId, {
    required bool enroll,
  }) async {
    final l = AppLocalizations.of(context);
    try {
      return await ref
          .read(apiClientProvider)
          .addSearchResult(
            lookupId: subject.lookupId,
            termId: subject.termId,
            collectionId: collectionId,
            enroll: enroll,
          );
    } catch (error) {
      if (!context.mounted) return null;
      final mismatch = termLanguageMismatch(error);
      if (mismatch == null) {
        failed(context, l);

        return null;
      }

      return _offerCollectionOfItsOwnPair(context, l, subject, mismatch, enroll: enroll);
    }
  }

  /// The server refused the save: the word is not of that collection's pair.
  ///
  /// The sheet filters by pair, so reaching here means the list was built from a mirror the server
  /// has since moved past — a collection re-created in another pair on another device, a sync that
  /// has not landed. RARE IS NOT NEVER, and the one thing that must not happen is a silent nothing:
  /// the learner tapped a collection and the word did not go in. So it is said in words, with both
  /// languages named, and the way out is offered in the same breath — a collection of the word's
  /// own pair, which is what «одна коллекция — одна пара» leaves as the only answer.
  Future<SavedSearchResult?> _offerCollectionOfItsOwnPair(
    BuildContext context,
    AppLocalizations l,
    WordCardSubject subject,
    ({String expected, String actual}) mismatch, {
    required bool enroll,
  }) async {
    final ok = await showCenterAlert(
      context: context,
      title: l.searchPairMismatchTitle,
      message: l.searchPairMismatchMessage(
        languageByCode(mismatch.expected).endonym,
        languageByCode(mismatch.actual).endonym,
      ),
      confirmLabel: l.searchPairMismatchCreate,
      cancelLabel: l.commonCancel,
      destructive: false,
    );
    if (ok != true || !context.mounted) return null;

    // The word's OWN language leads, whatever the pill currently says — `actual_lang` came from the
    // server and is the only half this refusal is certain about. The support side is the pill's if
    // there is one and it is not the same language; otherwise it is left out and the server fills
    // it from the profile.
    final support = pair?.support;
    final created = await _createCollection(
      context,
      l,
      learned: mismatch.actual,
      support: support == mismatch.actual ? null : support,
    );
    if (created == null || !context.mounted) return null;

    return save(context, subject, created, enroll: enroll);
  }

  /// Make a collection and hand back its id.
  ///
  /// Born in the pair the caller states, so the word about to be saved into it passes the server's
  /// gate rather than bouncing off it. A missing half is OMITTED from the request rather than
  /// guessed: the server then fills it from the profile, which is exactly what «профиль — только
  /// дефолт» means (DECISIONS п. 142). Guessing it here is how a collection would be born in a pair
  /// nobody chose.
  Future<String?> _createCollection(
    BuildContext context,
    AppLocalizations l, {
    String? learned,
    String? support,
  }) async {
    // The app's own alert frame, not Material's `AlertDialog` — that one arrived with its own
    // surface, radius and type, and was the last place in the app still contradicting the tokens.
    // The pair is pre-filled as the name, exactly as before: it is the honest default for a folder
    // whose whole identity is «these two languages», and it is the first thing the field selects
    // away from if the learner wants something else.
    final title = await showCenterPrompt(
      context: context,
      title: l.searchNewCollection,
      // The pair is the pre-filled NAME and is not repeated as a message above it: it is already on
      // screen, and saying it twice in a 274-point box reads as two different facts.
      initialValue: learned == null ? '' : _pairText(learned, support),
      confirmLabel: l.commonSave,
      cancelLabel: l.commonCancel,
    );
    if (title == null || title.isEmpty || !context.mounted) return null;

    try {
      final collection = await ref
          .read(apiClientProvider)
          .createCollection(title: title, sourceLang: support, targetLang: learned);

      return collection.id;
    } catch (_) {
      if (context.mounted) failed(context, l);

      return null;
    }
  }

  void failed(BuildContext context, AppLocalizations l) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(l.searchSaveFailed)));
  }

  /// «Сохранено в „X" · в очереди на разбор» / «Сохранено в „X" · учится» — WHAT JUST HAPPENED, in
  /// the words the rest of the app uses for those two states.
  ///
  /// Read off the ACT, never off `saved.enrolled`. The two disagree in exactly one case and the
  /// server is right about a different question there: «Учить сразу» on a word already in the queue
  /// answers `enrolled: false` («this call changed nothing»), and a toast that repeated it would
  /// tell the learner their word is waiting to be sorted when it is being studied.
  static String savedLine(AppLocalizations l, SavedSearchResult saved, {required bool enroll}) =>
      enroll ? l.searchSavedLearning(saved.collectionTitle) : l.searchSavedShelf(saved.collectionTitle);

  /// The same sentence as a toast. Every save shows one, on both screens, so the act names itself
  /// even when the line under the button is off-screen or the screen has no such line.
  static void toastSaved(
    BuildContext context,
    AppLocalizations l,
    SavedSearchResult saved, {
    required bool enroll,
  }) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(savedLine(l, saved, enroll: enroll))));
  }

  /// «English → Русский» — the pair as the learner reads it: endonyms, studied language first,
  /// which is the order a collection's own pair is written in (DECISIONS п. 135).
  static String _pairLabel(LearningPair pair) => _pairText(pair.learned, pair.support);

  static String _pairText(String learned, String? support) => support == null
      ? languageByCode(learned).endonym
      : '${languageByCode(learned).endonym} → ${languageByCode(support).endonym}';
}

/// How many words are on this shelf. A quiet trailing numeral, not a badge: it is a fact about the
/// row, and the row's name is what the learner is reading.
class _WordCount extends StatelessWidget {
  const _WordCount(this.count, {this.muted = false});

  final int count;

  /// The row is inert (the word is already there), so its counter must not be the brightest thing
  /// on a line the learner cannot tap.
  final bool muted;

  @override
  Widget build(BuildContext context) => Text(
    '$count',
    // Tabular figures, like every other counter here: a column of numbers on a list of rows must not
    // jitter as the digits change.
    style: AppText.counterSmall.copyWith(
      color: muted ? AppColors.tertiary : AppColors.secondary,
    ),
  );
}
