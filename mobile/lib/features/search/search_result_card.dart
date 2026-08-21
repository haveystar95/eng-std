import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import '../../data/models.dart';
import '../../data/providers.dart';

/// One word on the search screen — from the database ([hit]) or straight from the model ([lookup]).
///
/// ONE widget for both on purpose. The two differ in exactly two things — where the word came from,
/// and whether it is already in a folder — and everything else about them is identical: the same
/// term, TTS button, translation, English description, example with the term in bold, CEFR badge and
/// save action. Two widgets would have meant two layouts drifting apart, and the learner would have
/// been able to tell which words the app had had before, which is not a distinction they should ever
/// have to think about.
class SearchResultCard extends ConsumerStatefulWidget {
  const SearchResultCard({
    super.key,
    this.hit,
    this.lookup,
    required this.onSpeak,
    required this.onSaved,
  }) : assert(hit != null || lookup != null, 'a card shows either a database hit or a lookup');

  final SearchHit? hit;
  final LookupCard? lookup;
  final VoidCallback onSpeak;
  final Future<void> Function(SavedSearchResult saved) onSaved;

  @override
  ConsumerState<SearchResultCard> createState() => _SearchResultCardState();
}

class _SearchResultCardState extends ConsumerState<SearchResultCard> {
  bool _saving = false;

  String get _text => widget.hit?.text ?? widget.lookup!.text;
  String? get _translation => widget.hit?.translation ?? widget.lookup?.translation;
  String? get _description => widget.hit?.description ?? widget.lookup?.description;
  String? get _example => widget.hit?.example ?? widget.lookup?.example;
  String? get _exampleTranslation => widget.hit?.exampleTranslation ?? widget.lookup?.exampleTranslation;
  String? get _transcription => widget.hit?.transcription ?? widget.lookup?.transcription;
  String? get _cefr => widget.hit?.cefr ?? widget.lookup?.cefr;

  /// The folder this word is already in, if any. Only a database hit can have one — a lookup has
  /// not been saved anywhere yet, by definition.
  SavedFolder? get _savedIn => widget.hit?.folders.isNotEmpty == true ? widget.hit!.folders.first : null;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final description = _description;
    final example = _example;

    return PaperCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(child: Text(_text, style: AppText.termInList)),
              IconButton(
                visualDensity: VisualDensity.compact,
                padding: EdgeInsets.zero,
                constraints: const BoxConstraints(minWidth: 32, minHeight: 32),
                icon: const Icon(LucideIcons.volume2, size: 18, color: AppColors.secondary),
                onPressed: widget.onSpeak,
              ),
              if (_cefr != null) ...[
                const SizedBox(width: AppSpacing.s8),
                AppChip(label: _cefr!),
              ],
            ],
          ),
          if (_transcription != null && _transcription!.isNotEmpty)
            Text(_transcription!, style: AppText.transcription),
          if (_translation != null && _translation!.isNotEmpty) ...[
            const SizedBox(height: AppSpacing.s4),
            Text(_translation!, style: AppText.translation),
          ],
          if (description != null && description.isNotEmpty) ...[
            const SizedBox(height: AppSpacing.s12),
            // In the language being LEARNED, never translated: this is the definition, and reading
            // it in English is part of what the word costs.
            Text(description, style: AppText.usageExample),
          ],
          if (example != null && example.isNotEmpty) ...[
            const SizedBox(height: AppSpacing.s12),
            _ExampleLine(sentence: example, term: _text),
            if (_exampleTranslation != null && _exampleTranslation!.isNotEmpty)
              Text(_exampleTranslation!, style: AppText.translation.copyWith(color: AppColors.secondary)),
          ],
          const SizedBox(height: AppSpacing.s16),
          _actions(l),
        ],
      ),
    );
  }

  Widget _actions(AppLocalizations l) {
    final saved = _savedIn;

    return Row(
      children: [
        Expanded(
          child: PrimaryButton(
            minHeight: 44,
            // Already saved → the button NAMES the folder and does nothing. A live-looking button
            // that silently no-ops is worse than a dead one that explains itself.
            label: saved != null ? l.searchAlreadyIn(saved.title) : l.searchSaveToDefault,
            enabled: saved == null && !_saving,
            onPressed: saved != null ? null : () => _save(null),
          ),
        ),
        const SizedBox(width: AppSpacing.s8),
        // …and the menu stays live even for a saved word: a second folder is a legitimate thing to
        // want, and only the DEFAULT destination is already taken.
        IconButton(
          icon: const Icon(LucideIcons.folderPlus, size: 20, color: AppColors.ink),
          tooltip: l.searchAddToCollection,
          onPressed: _saving ? null : _pickFolder,
        ),
      ],
    );
  }

  Future<void> _pickFolder() async {
    final l = AppLocalizations.of(context);
    // From the LOCAL mirror, like every other screen: the shelf must be listable offline, and the
    // folder the learner just made is already there because creating one refetches.
    final folders = ref.read(collectionsProvider).value ?? const <WordCollection>[];
    // Own, editable folders only: a store deck is a catalogue the learner cannot put anything into.
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

  /// Not a ULID, so it can never collide with a real folder id.
  static const _newCollectionSentinel = 'new';

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
    try {
      final saved = await ref.read(apiClientProvider).addSearchResult(
            lookupId: widget.lookup?.lookupId,
            termId: widget.hit?.termId,
            collectionId: collectionId,
          );
      if (!mounted) return;
      setState(() => _saving = false);
      await widget.onSaved(saved);
    } catch (_) {
      if (!mounted) return;
      setState(() => _saving = false);
      _failed(l);
    }
  }

  void _failed(AppLocalizations l) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(l.searchSaveFailed)));
  }
}

/// The example with its term in bold — the same emphasis the intro card uses, so a word looks the
/// same before and after it is saved.
class _ExampleLine extends StatelessWidget {
  const _ExampleLine({required this.sentence, required this.term});

  final String sentence;
  final String term;

  @override
  Widget build(BuildContext context) {
    final at = sentence.toLowerCase().indexOf(term.toLowerCase());
    if (at < 0) {
      return Text(sentence, style: AppText.usageExample);
    }

    return Text.rich(
      TextSpan(children: [
        TextSpan(text: sentence.substring(0, at)),
        TextSpan(
          text: sentence.substring(at, at + term.length),
          style: const TextStyle(fontWeight: FontWeight.w700),
        ),
        TextSpan(text: sentence.substring(at + term.length)),
      ]),
      style: AppText.usageExample,
    );
  }
}
