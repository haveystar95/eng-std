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
    _timer = Timer(_debounce, () => _runFreeSearch(query));
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
