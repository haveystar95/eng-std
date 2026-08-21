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
import '../word_card/word_card_screen.dart';
import '../word_card/word_card_subject.dart';
import 'dictionary_row.dart';
import 'search_history.dart';
import 'search_result_card.dart';
import 'search_states.dart';

/// «Поиск» — the fifth tab, composed as направление 1a «Словарная статья» (кадры 01–05, 08).
///
/// TWO SEARCHES, and keeping them apart is the whole design. Typing runs the FREE one, over words
/// the database already has: instant, debounced, costs nothing. Only an explicit tap on «Find with
/// AI» spends a model call — a search box that generated on its own would burn the daily cap while
/// the learner was still typing, and they would never see it happen.
///
/// TWO MOMENTS, and keeping THOSE apart is what the reskin added. While the learner types, the
/// screen is a dictionary opening at a letter: a plain list of rows, and the field's own echo of
/// what the fragment means, italic and grey, INSIDE the field. Nothing is claimed to be the answer
/// yet. Only submitting — Enter, a tapped row, a tapped recent — turns the page into a RESULT: one
/// raised leaf for the word that was asked for, and the near misses demoted to flat lines under it.
///
/// A cap that has been spent is not an error here: the free half keeps working and the screen says
/// when the model comes back (кадр 08).
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

  /// The italic echo inside the field. Null until an answer arrives for the CURRENT query — it is
  /// never shown beside a word it is not about.
  InstantHint? _hint;

  /// Guards against an out-of-order response overwriting a newer one: every dispatch takes the next
  /// number and only the latest is allowed to land.
  int _generation = 0;

  String _query = '';

  /// The query the learner actually ASKED for, as opposed to the one they are still typing. Null
  /// while typing; that null is the difference between кадр 02 and кадры 03/04.
  String? _submitted;

  List<SearchHit> _hits = const [];
  bool _searching = false;

  bool _lookingUp = false;
  bool _limitReached = false;
  int _dailyCap = 0;
  int _usedToday = 0;
  String? _lookupError;

  List<RecentSearch> _recent = const [];

  SearchHistory get _history => SearchHistory(ref.read(appDatabaseProvider));

  @override
  void initState() {
    super.initState();
    // Lazily, and only now: most sessions never open search, and the parse is not worth paying for
    // on app start. A first keystroke that beats the read simply gets no dictionary row yet.
    _words.ensureLoaded().then((_) {
      if (!mounted) return;
      setState(() => _dictionaryHits = _query.isEmpty ? const [] : _suggestFor(_query));
    });
    _history.load().then((recent) {
      if (mounted) setState(() => _recent = recent);
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    _controller.dispose();
    _pronouncer.release();
    super.dispose();
  }

  // ── input ─────────────────────────────────────────────────────────────────

  void _onChanged(String raw) {
    _timer?.cancel();
    final query = raw.trim();
    setState(() {
      _query = query;
      // Straight away, with no await anywhere: the dictionary is in memory, so the rows paint in
      // the same frame as the character that was typed. That immediacy IS the feature — everything
      // else on this screen has to wait for a server.
      _dictionaryHits = _suggestFor(query);
      // Typing takes the page back to «набор»: whatever was asked for a moment ago is not what is
      // in the field now, and leaving a result under a different word is the one thing this screen
      // must never do.
      _submitted = null;
      _hint = null;
      _lookupError = null;
      _limitReached = false;
      if (query.isEmpty) {
        _hits = const [];
        _searching = false;
      }
    });
    if (query.isEmpty) return;
    _timer = Timer(_debounce, () {
      unawaited(_runFreeSearch(query));
      unawaited(_fetchHint(query));
    });
  }

  List<String> _suggestFor(String query) => _words.loaded?.startingWith(query) ?? const [];

  /// Ask for the whole word — Enter, a tapped dictionary row, or a tapped recent.
  ///
  /// The learner has said which word they meant, so nothing here waits for a debounce that is never
  /// coming, and the hint is fetched at once rather than on a timer.
  Future<void> _submit(String word) async {
    final query = word.trim();
    if (query.isEmpty) return;
    AppHaptics.light();
    _timer?.cancel();
    if (_controller.text != query) {
      _controller.value = TextEditingValue(
        text: query,
        selection: TextSelection.collapsed(offset: query.length),
      );
    }
    FocusScope.of(context).unfocus();
    setState(() {
      _query = query;
      _submitted = query;
      _hint = null;
      _lookupError = null;
      _limitReached = false;
    });
    unawaited(_fetchHint(query));
    await _runFreeSearch(query);
    await _remember(query);
  }

  /// Ask for the grey echo. Fire-and-forget by design: it has no spinner, no error path and no
  /// retry — the line either appears or it does not, and everything else is indifferent to which.
  Future<void> _fetchHint(String query) async {
    final generation = _generation;
    try {
      final hint = await ref.read(apiClientProvider).instantHint(query);
      if (!mounted || generation != _generation || hint.query.isEmpty || !hint.hasText) return;
      setState(() => _hint = hint);
    } catch (_) {
      // Offline, throttled, dead tunnel — all the same thing here: no echo.
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

  /// «Вы искали» remembers the word WITH what it turned out to mean, when that is known — three
  /// bare spellings would be a log, three dictionary lines are a way back in.
  Future<void> _remember(String query) async {
    final hit = _exactHit;
    final recent = await _history.remember(RecentSearch(
      word: hit?.text ?? query,
      translation: hit?.translation ?? _hint?.translation,
      cefr: hit?.cefr,
    ));
    if (mounted) setState(() => _recent = recent);
  }

  // ── the model ─────────────────────────────────────────────────────────────

  /// Кадр 04 → 05 → 06. One tap, one call, and the answer opens as the word's own card — the
  /// assembling frame is the wait, not a destination.
  Future<void> _askAi() async {
    final query = _query;
    if (query.isEmpty || _lookingUp) return;
    AppHaptics.light();
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
        _limitReached = outcome.limitReached;
        _dailyCap = outcome.dailyCap;
        _usedToday = outcome.usedToday;
      });
      final card = outcome.card;
      if (card == null) return; // кадр 08 — the cap, which is an answer and not an error.
      await _remember(query);
      if (!mounted) return;
      await _openCard(WordCardSubject.fromLookup(card));
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _lookingUp = false;
        _lookupError = AppLocalizations.of(context).searchLookupFailed;
      });
    }
  }

  // ── the card ──────────────────────────────────────────────────────────────

  /// Open the word's own screen (кадр 06).
  ///
  /// A hit's photo is fetched from the LOCAL term mirror first: `/search` carries no image, so the
  /// only picture a search result can have is one already on the device — which is exactly the case
  /// for a word the learner has met before, and exactly the case where they would notice it missing.
  Future<void> _openCard(WordCardSubject subject) async {
    var enriched = subject;
    final termId = subject.termId;
    if (termId != null && !subject.hasPhoto) {
      final term = await ref.read(appDatabaseProvider).termById(termId);
      if (term != null) {
        enriched = WordCardSubject(
          termId: subject.termId,
          lookupId: subject.lookupId,
          text: subject.text,
          type: subject.type,
          transcription: subject.transcription ?? term.transcription,
          translation: subject.translation ?? term.translation,
          description: subject.description ?? term.description,
          example: subject.example ?? term.example,
          exampleTranslation: subject.exampleTranslation ?? term.exampleTranslation,
          cefr: subject.cefr,
          imageUrl: term.imageUrl,
          imageAuthor: term.imageAuthor,
          imageAuthorUrl: term.imageAuthorUrl,
          folders: subject.folders,
        );
      }
    }
    if (!mounted) return;

    await Navigator.of(context).push(MaterialPageRoute<void>(
      builder: (_) => WordCardScreen(
        subject: enriched,
        onSpeak: () => _speak(enriched.text, enriched.type),
        onSaved: _afterSave,
      ),
    ));
  }

  /// A save always ends by re-running the FREE search: the word is now in the database, so the next
  /// pass returns it as an ordinary hit with its folder attached.
  Future<void> _afterSave(SavedSearchResult saved) async {
    ref.read(syncServiceProvider).sync();
    if (_query.isNotEmpty) unawaited(_runFreeSearch(_query));
  }

  // ── layout ────────────────────────────────────────────────────────────────

  /// The hit that IS the word that was asked for, as opposed to one that merely starts with it.
  /// Deliberately exact: «hollow» is not an answer to «hole», and treating it as one would stop the
  /// learner ever generating the word they meant.
  SearchHit? get _exactHit {
    final asked = (_submitted ?? '').toLowerCase();
    if (asked.isEmpty) return null;
    for (final hit in _hits) {
      if (hit.text.trim().toLowerCase() == asked) return hit;
    }

    return null;
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);

    return Scaffold(
      backgroundColor: AppColors.paper,
      body: SafeArea(
        bottom: false,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(AppSpacing.s22, 18, AppSpacing.s22, 0),
              child: _field(l),
            ),
            Expanded(
              child: ListView(
                // Scrolling the results puts the keyboard away, the way every list with a search
                // field behaves.
                keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
                padding: const EdgeInsets.fromLTRB(
                  AppSpacing.s22,
                  0,
                  AppSpacing.s22,
                  AppTabBarMetrics.height + AppTabBarMetrics.bottomInset + AppSpacing.s26,
                ),
                children: _body(l),
              ),
            ),
          ],
        ),
      ),
    );
  }

  /// The field is a RULE, not a box (направление 1a): one hairline under a line of Literata, the way
  /// a headword sits on a dictionary page. It darkens to full ink while it has focus or content.
  Widget _field(AppLocalizations l) {
    final active = _query.isNotEmpty;
    final echo = _hint?.translation;
    final showEcho = _submitted == null && (echo ?? '').isNotEmpty;

    return Container(
      height: 48,
      decoration: BoxDecoration(
        border: Border(
          bottom: BorderSide(color: active ? AppColors.ink : AppColors.dashed, width: 1.5),
        ),
      ),
      child: Row(
        children: [
          Icon(LucideIcons.search, size: 18, color: active ? AppColors.ink : AppColors.tertiary),
          const SizedBox(width: 10),
          Expanded(
            child: TextField(
              controller: _controller,
              onChanged: _onChanged,
              onSubmitted: _submit,
              textInputAction: TextInputAction.search,
              autocorrect: false,
              decoration: InputDecoration(
                isDense: true,
                border: InputBorder.none,
                contentPadding: EdgeInsets.zero,
                hintText: l.searchFieldHint,
                hintStyle: AppText.searchInput.copyWith(color: AppColors.tertiary),
              ),
              style: AppText.searchInput,
            ),
          ),
          // The echo of what is being typed lives INSIDE the field, italic and grey: it is feedback
          // about the input, not a result, and the two must never share a level (макет, «общее
          // решение по иерархии набора»).
          if (showEcho)
            Padding(
              padding: const EdgeInsets.only(left: AppSpacing.s8),
              child: Text(echo!, style: AppText.searchEcho),
            )
          else if (active)
            Semantics(
              button: true,
              child: InkResponse(
                onTap: () {
                  _controller.clear();
                  _onChanged('');
                },
                radius: 22,
                child: const SizedBox(
                  width: AppSpacing.minTap,
                  height: AppSpacing.minTap,
                  child: Icon(LucideIcons.x, size: 15, color: AppColors.tertiary),
                ),
              ),
            ),
        ],
      ),
    );
  }

  List<Widget> _body(AppLocalizations l) {
    if (_lookingUp) {
      return [
        const SizedBox(height: 30),
        AssemblingCard(term: _query, translation: _hint?.translation),
      ];
    }
    if (_query.isEmpty) return _empty(l);
    if (_submitted == null) return _typing(l);
    if (_limitReached) {
      return [
        const SizedBox(height: 34),
        AiLimitCard(query: _submitted!, used: _usedToday, cap: _dailyCap),
      ];
    }

    final exact = _exactHit;

    return exact != null ? _found(l, exact) : _missing(l);
  }

  /// Кадр 01 — nothing typed yet.
  List<Widget> _empty(AppLocalizations l) {
    final size = _words.loaded?.length ?? 0;

    return [
      const SizedBox(height: AppSpacing.s26),
      if (_recent.isNotEmpty) ...[
        SearchSectionLabel(l.searchRecentLabel),
        const SizedBox(height: AppSpacing.s8),
        for (final recent in _recent)
          DictionaryRow(
            term: recent.word,
            translation: recent.translation,
            level: recent.cefr,
            trailing: RowTrailing.level,
            onTap: () => _submit(recent.word),
          ),
        const SizedBox(height: 30),
      ],
      if (size > 0)
        Container(
          padding: const EdgeInsets.only(top: AppSpacing.s16),
          decoration: _recent.isEmpty
              ? null
              : const BoxDecoration(border: Border(top: BorderSide(color: AppColors.dividerFaint))),
          child: Text(l.searchBaseSize(size), style: AppText.searchNote),
        ),
    ];
  }

  /// Кадр 02 — mid-typing. The list of words is the main object on the page: it is the only thing
  /// that leads anywhere, so it is set at reading size with translations, and the pills it replaced
  /// are gone. Same two sources as before — catalogue first, dictionary after.
  List<Widget> _typing(AppLocalizations l) {
    final suggestions = mergeSuggestions(known: _hits, dictionary: _dictionaryHits);

    return [
      const SizedBox(height: 20),
      if (suggestions.isNotEmpty) ...[
        SearchSectionLabel(l.searchInBaseLabel),
        const SizedBox(height: 6),
        for (var i = 0; i < suggestions.length; i++)
          DictionaryRow(
            term: suggestions[i].word,
            translation: suggestions[i].translation,
            prefix: _query,
            trailing: RowTrailing.chevron,
            height: 58,
            termStyle: AppText.searchRowTerm.copyWith(fontSize: 23),
            showDivider: i < suggestions.length - 1,
            // A catalogue word goes straight to its card — the row already showed what it means, so
            // a second stop at a search result would say nothing new. A dictionary word is only a
            // spelling, so it becomes the query instead.
            onTap: () {
              final termId = suggestions[i].termId;
              if (termId == null) {
                _submit(suggestions[i].word);
              } else {
                _openHit(termId);
              }
            },
          ),
        const SizedBox(height: AppSpacing.s22),
      ],
      Text(l.searchPressEnter(_query), style: AppText.searchNote),
    ];
  }

  /// Кадр 03 — the word was found. One raised leaf, then the rest of the matches as flat lines.
  List<Widget> _found(AppLocalizations l, SearchHit exact) {
    final rest = _hits.where((h) => h.termId != exact.termId).toList();

    return [
      const SizedBox(height: AppSpacing.s26),
      SearchResultCard(
        subject: WordCardSubject.fromHit(exact),
        onOpen: () => _openCard(WordCardSubject.fromHit(exact)),
      ),
      if (rest.isNotEmpty) ...[
        const SizedBox(height: 14),
        SearchSectionLabel(l.searchMoreInBase),
        const SizedBox(height: 2),
        for (var i = 0; i < rest.length; i++)
          DictionaryRow(
            term: rest[i].text,
            translation: rest[i].translation,
            height: 52,
            termStyle: AppText.searchRowTerm.copyWith(fontSize: 19),
            showDivider: i < rest.length - 1,
            onTap: () => _openCard(WordCardSubject.fromHit(rest[i])),
          ),
      ],
    ];
  }

  /// Кадр 04 — the word is not in the database. The offer to have it written, what it costs in one
  /// grey line UNDER the button, and the near misses below a rule.
  List<Widget> _missing(AppLocalizations l) {
    final near = _hits;

    return [
      const SizedBox(height: 38),
      Text(l.searchMissTitle(_submitted!), style: AppText.searchMissTitle),
      const SizedBox(height: 10),
      ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 300),
        child: Text(l.searchMissBody, style: AppText.searchMissBody),
      ),
      const SizedBox(height: AppSpacing.s26),
      PrimaryButton(
        label: _searching ? l.searchLooking : l.searchAskAi,
        minHeight: 54,
        trailingIcon: LucideIcons.sparkles,
        enabled: !_searching,
        onPressed: _askAi,
      ),
      const SizedBox(height: 10),
      Text(l.searchAskAiNote, textAlign: TextAlign.center, style: AppText.searchFootnote),
      if (_lookupError != null) ...[
        const SizedBox(height: AppSpacing.s12),
        Text(_lookupError!, textAlign: TextAlign.center, style: AppText.searchFootnote),
      ],
      if (near.isNotEmpty) ...[
        const SizedBox(height: 34),
        Container(
          padding: const EdgeInsets.only(top: AppSpacing.s16),
          decoration: const BoxDecoration(
            border: Border(top: BorderSide(color: AppColors.dividerFaint)),
          ),
          child: SearchSectionLabel(l.searchSimilarInBase),
        ),
        for (var i = 0; i < near.length; i++)
          DictionaryRow(
            term: near[i].text,
            translation: near[i].translation,
            height: 52,
            termStyle: AppText.searchRowTerm.copyWith(fontSize: 19),
            showDivider: i < near.length - 1,
            onTap: () => _openCard(WordCardSubject.fromHit(near[i])),
          ),
      ],
    ];
  }

  void _openHit(String termId) {
    for (final hit in _hits) {
      if (hit.termId == termId) {
        unawaited(_openCard(WordCardSubject.fromHit(hit)));

        return;
      }
    }
  }

  void _speak(String text, String type) {
    _pronouncer.speak(
      Word(termId: 'search', term: text, translation: '', type: type),
      targetLang: 'en',
    );
  }
}
