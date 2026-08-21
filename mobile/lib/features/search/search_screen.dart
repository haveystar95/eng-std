import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import '../../data/models.dart';
import '../../data/pronouncer.dart';
import '../../data/providers.dart';
import '../../data/search/suggestions.dart';
import '../../data/search/word_list.dart';
import 'search_result_card.dart';

/// «Поиск» — the fifth tab.
///
/// TWO SEARCHES, and keeping them apart is the whole design. Typing runs the FREE one, over words
/// the database already has: instant, debounced, costs nothing. Only an explicit tap on «Найти с
/// ИИ» spends a model call — a search box that generated on its own would burn the daily cap while
/// the learner was still typing, and they would never see it happen.
///
/// A cap that has been spent is not an error here: the screen keeps showing the free results and
/// says so in one honest line.
class SearchScreen extends ConsumerStatefulWidget {
  const SearchScreen({super.key});

  @override
  ConsumerState<SearchScreen> createState() => _SearchScreenState();
}

class _SearchScreenState extends ConsumerState<SearchScreen> {
  /// Long enough that a fast typist fires one request, short enough to feel instant. The request it
  /// delays is free, so this is about the server's load and the list's stability, not about money.
  static const _debounce = Duration(milliseconds: 280);

  final _controller = TextEditingController();
  final _pronouncer = Pronouncer();
  Timer? _timer;

  /// The offline dictionary, read once, the first time this screen opens. It fills the silence
  /// between a keystroke and a server round trip — see [WordList].
  final _words = WordListLoader();
  List<String> _dictionaryHits = const [];

  /// The grey line under the field. Null until an answer arrives for the CURRENT query — it is
  /// never shown under a word it is not about.
  InstantHint? _hint;

  /// Guards against an out-of-order response overwriting a newer one: every dispatch takes the next
  /// number and only the latest is allowed to land.
  int _generation = 0;

  String _query = '';
  List<SearchHit> _hits = const [];
  bool _searching = false;

  LookupCard? _lookup;
  bool _lookingUp = false;
  bool _limitReached = false;
  int _dailyCap = 0;
  String? _lookupError;

  @override
  void initState() {
    super.initState();
    // Lazily, and only now: most sessions never open search, and the parse is not worth paying for
    // on app start. A first keystroke that beats the read simply gets no dictionary row yet.
    _words.ensureLoaded().then((_) {
      if (mounted && _query.isNotEmpty) setState(() => _dictionaryHits = _suggestFor(_query));
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    _controller.dispose();
    _pronouncer.release();
    super.dispose();
  }

  void _onChanged(String raw) {
    _timer?.cancel();
    final query = raw.trim();
    setState(() {
      _query = query;
      // Straight away, with no await anywhere: the dictionary is in memory, so the suggestion row
      // paints in the same frame as the character that was typed. That immediacy IS the feature —
      // everything else on this screen has to wait for a server.
      _dictionaryHits = _suggestFor(query);
      // The old hint belonged to the old word. Clearing it here rather than when the new one lands
      // is what stops «significant — значительный» sitting under «signif» for a beat.
      _hint = null;
      // A new query invalidates the old model answer — leaving it on screen under a different word
      // is the one thing this screen must never do.
      _lookup = null;
      _lookupError = null;
      if (query.isEmpty) {
        _hits = const [];
        _searching = false;
      }
    });
    if (query.isEmpty) return;
    _timer = Timer(_debounce, () {
      _runFreeSearch(query);
      _fetchHint(query);
    });
  }

  List<String> _suggestFor(String query) => _words.loaded?.startingWith(query) ?? const [];

  /// Tapping a suggestion completes the word and searches for it at once — the learner has said
  /// which word they meant, so making them press anything else would be asking twice.
  void _acceptSuggestion(String word) {
    AppHaptics.light();
    _controller.value = TextEditingValue(
      text: word,
      selection: TextSelection.collapsed(offset: word.length),
    );
    _timer?.cancel();
    setState(() {
      _query = word;
      _dictionaryHits = const [];
      _hint = null;
      _lookup = null;
      _lookupError = null;
    });
    unawaited(_runFreeSearch(word));
    // A tap says which word they meant, so the hint is worth fetching at once rather than waiting
    // for a debounce that will never fire — there is no further typing coming.
    unawaited(_fetchHint(word));
  }

  /// Ask for the grey line. Fire-and-forget by design: it has no spinner, no error path and no
  /// retry — the line either appears or it does not, and everything else on the screen is
  /// indifferent to which. A failure here must never reach the learner.
  Future<void> _fetchHint(String query) async {
    final generation = _generation;
    try {
      final hint = await ref.read(apiClientProvider).instantHint(query);
      // The same out-of-order guard the search uses: a slow answer for an old word must not paint
      // under a new one.
      if (!mounted || generation != _generation || hint.query.isEmpty) return;
      if (!hint.hasText) return;
      setState(() => _hint = hint);
    } catch (_) {
      // Offline, throttled, dead tunnel — all the same thing here: no line.
    }
  }

  Future<void> _runFreeSearch(String query) async {
    final generation = ++_generation;
    setState(() => _searching = true);
    try {
      final hits = await ref.read(apiClientProvider).search(query);
      if (!mounted || generation != _generation) return;
      setState(() {
        _hits = hits;
        _searching = false;
      });
    } catch (_) {
      if (!mounted || generation != _generation) return;
      // Offline or a server hiccup. Deliberately silent: the free search is a convenience, and a
      // red banner over an empty list would read as «this word does not exist».
      setState(() => _searching = false);
    }
  }

  Future<void> _askAi() async {
    final query = _query;
    if (query.isEmpty || _lookingUp) return;
    final generation = ++_generation;
    setState(() {
      _lookingUp = true;
      _lookupError = null;
    });
    try {
      final outcome = await ref.read(apiClientProvider).lookupWord(query);
      if (!mounted || generation != _generation) return;
      setState(() {
        _lookingUp = false;
        _lookup = outcome.card;
        _limitReached = outcome.limitReached;
        _dailyCap = outcome.dailyCap;
      });
    } catch (_) {
      if (!mounted || generation != _generation) return;
      setState(() {
        _lookingUp = false;
        _lookupError = AppLocalizations.of(context).searchLookupFailed;
      });
    }
  }

  /// A save always ends by re-running the FREE search: the word is now in the database, so the next
  /// pass returns it as an ordinary hit with its folder attached — which is what turns the card's
  /// button into «В „…"» without the screen having to fake that state locally.
  Future<void> _afterSave(SavedSearchResult saved) async {
    final l = AppLocalizations.of(context);
    ref.read(syncServiceProvider).sync();
    if (_query.isNotEmpty) unawaited(_runFreeSearch(_query));
    if (!mounted) return;
    setState(() => _lookup = null);
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(l.searchSavedTo(saved.collectionTitle))),
    );
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);

    return Scaffold(
      backgroundColor: AppColors.paper,
      body: SafeArea(
        bottom: false,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(
            AppSpacing.screenH,
            AppSpacing.s16,
            AppSpacing.screenH,
            AppTabBarMetrics.height + AppTabBarMetrics.bottomInset + AppSpacing.s26,
          ),
          children: [
            Text(l.searchTitle, style: AppText.screenTitle),
            const SizedBox(height: AppSpacing.s16),
            _field(l),
            ..._hintLine(),
            ..._suggestionRow(),
            const SizedBox(height: AppSpacing.s16),
            ..._results(l),
          ],
        ),
      ),
    );
  }

  Widget _field(AppLocalizations l) {
    return PaperCard(
      padding: const EdgeInsets.symmetric(horizontal: AppSpacing.s16, vertical: AppSpacing.s4),
      child: Row(
        children: [
          const Icon(LucideIcons.search, size: 18, color: AppColors.secondary),
          const SizedBox(width: AppSpacing.s12),
          Expanded(
            child: TextField(
              controller: _controller,
              onChanged: _onChanged,
              textInputAction: TextInputAction.search,
              autocorrect: false,
              decoration: InputDecoration(
                border: InputBorder.none,
                hintText: l.searchFieldHint,
                hintStyle: AppText.translation.copyWith(color: AppColors.tertiary),
              ),
              style: AppText.termInList,
            ),
          ),
          if (_query.isNotEmpty)
            IconButton(
              icon: const Icon(LucideIcons.x, size: 18, color: AppColors.secondary),
              onPressed: () {
                _controller.clear();
                _onChanged('');
              },
            ),
        ],
      ),
    );
  }

  /// «significant — значительный», in grey, directly under the field.
  ///
  /// No spinner and no source label. A spinner would promise something is coming, and this is a
  /// hint that is allowed not to arrive; a source label («from DeepL», «from cache») is the app's
  /// internal kitchen and means nothing to the person reading it. The line simply appears when it
  /// is ready, or never, and both are fine.
  ///
  /// Tapping it opens the full card — the learner has just been told what the word means and the
  /// obvious next question is «show me the rest», which is exactly what «Найти с ИИ» does.
  List<Widget> _hintLine() {
    final hint = _hint;
    if (hint == null || !hint.hasText || _lookup != null) return const [];

    return [
      const SizedBox(height: AppSpacing.s12),
      InkWell(
        onTap: _lookingUp ? null : _askAi,
        borderRadius: BorderRadius.circular(AppRadii.thumb),
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: AppSpacing.s4),
          child: Text(
            '${hint.query} — ${hint.translation}',
            style: AppText.translation.copyWith(color: AppColors.secondary),
          ),
        ),
      ),
    ];
  }

  /// The suggestion chips, directly under the field.
  ///
  /// Hidden as soon as the database has answered: once real results are on screen, a row of
  /// spellings is noise between the learner and the thing they were looking for. It exists to fill
  /// the gap BEFORE that, which is exactly where the screen used to be blank.
  List<Widget> _suggestionRow() {
    if (_query.isEmpty || _hits.isNotEmpty) return const [];

    final suggestions = mergeSuggestions(known: _hits, dictionary: _dictionaryHits);
    if (suggestions.isEmpty) return const [];

    return [
      const SizedBox(height: AppSpacing.s12),
      ChipScrollRow(
        children: [
          for (final suggestion in suggestions)
            AppChip(
              label: suggestion.word,
              // A word we already have is marked, so the two kinds of suggestion do not read as
              // one list of equals: this one can be saved, the others still need looking up.
              selected: suggestion.isKnown,
              onTap: () => _acceptSuggestion(suggestion.word),
            ),
        ],
      ),
    ];
  }

  List<Widget> _results(AppLocalizations l) {
    if (_query.isEmpty) {
      return [_note(l.searchEmptyHint)];
    }

    return [
      for (final hit in _hits) ...[
        SearchResultCard(
          key: ValueKey(hit.termId),
          hit: hit,
          onSpeak: () => _speak(hit.text, hit.type),
          onSaved: _afterSave,
        ),
        const SizedBox(height: AppSpacing.s12),
      ],
      if (_lookup != null) ...[
        SearchResultCard(
          key: ValueKey(_lookup!.lookupId),
          lookup: _lookup,
          onSpeak: () => _speak(_lookup!.text, _lookup!.type),
          onSaved: _afterSave,
        ),
        const SizedBox(height: AppSpacing.s12),
      ],
      // The AI offer appears only when the free search came back with nothing AND the model has not
      // already answered. It is a tap, never a fallback the debounce triggers.
      if (!_searching && _hits.isEmpty && _lookup == null) ..._aiOffer(l),
      if (_limitReached) _note(l.searchLimitReached(_dailyCap)),
      if (_lookupError != null) _note(_lookupError!),
    ];
  }

  List<Widget> _aiOffer(AppLocalizations l) {
    return [
      _note(l.searchNothingFound),
      const SizedBox(height: AppSpacing.s12),
      PrimaryButton(
        label: _lookingUp ? l.searchLooking : l.searchAskAi,
        subtitle: _lookingUp ? null : l.searchAskAiNote,
        // Greyed out rather than hidden once the cap is spent: a button that vanished would read as
        // a bug, and the line beside it already explains why it cannot be pressed.
        enabled: !_lookingUp && !_limitReached,
        onPressed: _askAi,
      ),
      const SizedBox(height: AppSpacing.s12),
    ];
  }

  Widget _note(String text) => Padding(
        padding: const EdgeInsets.symmetric(vertical: AppSpacing.s8),
        child: Text(text, style: AppText.translation.copyWith(color: AppColors.secondary)),
      );

  void _speak(String text, String type) {
    _pronouncer.speak(
      Word(termId: 'search', term: text, translation: '', type: type),
      targetLang: 'en',
    );
  }
}
