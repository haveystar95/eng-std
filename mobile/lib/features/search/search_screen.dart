import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import '../../data/local/app_database.dart' show Term;
import '../../data/models.dart';
import '../../data/pronouncer.dart';
import '../../data/providers.dart';
import '../../data/search/suggestions.dart';
import '../../data/search/word_list.dart';
import '../word_card/word_card_screen.dart';
import '../word_card/word_card_subject.dart';
import 'dictionary_row.dart';
import 'language_pill.dart';
import 'search_history.dart';
import 'search_pair.dart';
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

  /// The model could not name a word for what was typed. An answer, not an error — so it is a flag
  /// the small card reads, not a thrown failure.
  bool _notRecognized = false;
  int _dailyCap = 0;
  int _usedToday = 0;
  String? _lookupError;

  List<RecentSearch> _recent = const [];

  /// Which way the field is pointing, and what the pill may offer.
  ///
  /// Null until the first read lands. The screen is fully usable meanwhile — every call simply
  /// omits the pair and the server answers in the learner's profile one, which is where the pill
  /// would have started anyway.
  SearchLanguages? _languages;
  SearchPair? _pair;

  SearchPairStore get _pairs => SearchPairStore(ref.read(appDatabaseProvider));

  /// The pair a save from this screen has to obey — «изучаемый ← язык поддержки», the shape a
  /// COLLECTION carries. Null while the pill has not loaded, which is also when every request omits
  /// the pair and the server answers in the profile one: with nothing stated, nothing is filtered.
  LearningPair? get _learningPair {
    final pair = _pair;
    final languages = _languages;
    if (pair == null || languages == null) return null;

    return LearningPair.of(pair, languages.taught);
  }

  /// Terms the LOCAL mirror already holds, keyed by id — the only place a search result can get a
  /// photo from, since `/search` carries none. Filled for the word that was actually asked for, so
  /// its 88 pt plate in кадр 03 is a picture rather than an empty rectangle.
  final Map<String, Term> _mirrored = {};

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
    unawaited(_loadPair());
  }

  /// Ask the server which pairs exist, then restore the one this device was last set to.
  ///
  /// Deliberately silent on failure: offline, the pill simply does not appear and every request
  /// omits the pair, which the server reads as «the learner's own». A search screen that refused to
  /// work because it could not draw a language label would be trading the feature for the setting.
  Future<void> _loadPair() async {
    try {
      final languages = await ref.read(apiClientProvider).searchLanguages();
      final pair = await _pairs.load(languages);
      if (!mounted) return;
      setState(() {
        _languages = languages;
        _pair = pair;
      });
    } catch (_) {
      // No pill this session.
    }
  }

  /// A swap or a language picked in either pill — all the same event: remember the new pair and
  /// re-ask, because anything on screen answers the old question.
  void _changePair(SearchPair next) {
    if (next == _pair || next.source == next.target) return;
    setState(() => _pair = next);
    unawaited(_pairs.save(next));
    _reaskCurrentQuery();
  }

  /// Re-run whatever is on screen in the new pair.
  ///
  /// EVERYTHING PAIR-SHAPED IS DROPPED FIRST — the echo, the hits, the outcome flags. A translation
  /// from the previous direction sitting under a freshly flipped pill is the one thing this control
  /// must never show, and the hit list is no different: «invoice» found in EN→RU is not an answer
  /// to the same word asked RU→PL. The mirror cache (`_mirrored`) is deliberately kept — it is
  /// photos keyed by term id, and a term's photo does not depend on which way it was asked for.
  void _reaskCurrentQuery() {
    if (_query.isEmpty) return;
    setState(() {
      _hint = null;
      _hits = const [];
      _lookupError = null;
      _limitReached = false;
      _notRecognized = false;
      _searching = true;
    });
    unawaited(_runFreeSearch(_query));
    unawaited(_fetchHint(_query));
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
      _notRecognized = false;
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
      _notRecognized = false;
    });
    unawaited(_fetchHint(query));
    await _runFreeSearch(query);
    await _loadMirrored(_exactHit?.termId);
  }

  /// Read one term out of the local mirror, if it is there. Cheap, offline, and the whole reason
  /// кадр 03 can show a photo at all.
  Future<void> _loadMirrored(String? termId) async {
    if (termId == null || _mirrored.containsKey(termId)) return;
    final term = await ref.read(appDatabaseProvider).termById(termId);
    if (term == null || !mounted) return;
    setState(() => _mirrored[termId] = term);
  }

  /// Ask for the grey echo. Fire-and-forget by design: it has no spinner, no error path and no
  /// retry — the line either appears or it does not, and everything else is indifferent to which.
  ///
  /// The out-of-order guard is the QUERY, not the search's generation counter. Keying it on the
  /// counter looked equivalent and was not: submitting fires the hint first and the free search
  /// second, the search bumps the counter, and the hint that arrived afterwards was thrown away
  /// every single time — so кадр 05 opened with an empty «перевод» row even though the answer was
  /// in hand (caught on the simulator, DSN-2). What the guard actually has to enforce is «never
  /// beside a word it is not about», and that is a comparison of words.
  Future<void> _fetchHint(String query) async {
    try {
      final hint = await ref
          .read(apiClientProvider)
          .instantHint(query, source: _pair?.source, target: _pair?.target);
      // Kept when there is something to SAY — a translation, or the one honest «this is too long
      // to be a word» the field does put a line up for. An answerless hint is still dropped: it
      // has nothing to add and would only overwrite one that had.
      if (!mounted || hint.query.isEmpty || !(hint.hasText || hint.queryTooLong)) return;
      if (query.toLowerCase() != _query.toLowerCase()) return;
      setState(() => _hint = hint);
    } catch (_) {
      // Offline, throttled, dead tunnel — all the same thing here: no echo.
    }
  }

  Future<void> _runFreeSearch(String query) async {
    final generation = ++_generation;
    setState(() => _searching = true);
    try {
      final hits = await ref
          .read(apiClientProvider)
          .search(query, source: _pair?.source, target: _pair?.target);
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

  /// «Вы искали» remembers a search that ENDED somewhere — a card opened, or a card built.
  ///
  /// Not every submitted string, which is what it used to be: typing is cheap and most of it leads
  /// nowhere, so remembering it filled the section with words the learner glanced at and abandoned.
  /// Three lines of that are a log of keystrokes; three lines of words they actually opened are a
  /// way back in, which is the only reason the section exists.
  ///
  /// [word] is always the word being LEARNED — the English one — whichever language the query that
  /// found it was typed in. A row is a way back to a WORD, and «случай» is not one.
  Future<void> _remember({required String word, String? translation, String? cefr}) async {
    final recent = await _history.remember(
      RecentSearch(word: word, translation: translation, cefr: cefr),
    );
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
      _notRecognized = false;
    });
    try {
      final outcome = await ref
          .read(apiClientProvider)
          .lookupWord(query, source: _pair?.source, target: _pair?.target);
      if (!mounted || generation != _generation) return;
      setState(() {
        _lookingUp = false;
        _limitReached = outcome.limitReached;
        _notRecognized = outcome.notRecognized;
        _dailyCap = outcome.dailyCap;
        _usedToday = outcome.usedToday;
      });
      final card = outcome.card;
      // кадр 08 — the cap; or a query the model could not place. Both are answers and neither is
      // an error, so both are drawn by _missing()/_body() rather than thrown.
      if (card == null) return;
      // The word that was actually built, not the string that found it: a Russian query ends in an
      // English card, and the English word is what the learner would want to get back to.
      await _remember(word: card.text, translation: card.translation, cefr: card.cefr);
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

  /// A hit as the screen draws it: the server's answer, plus whatever the LOCAL mirror already
  /// knows about the same term.
  ///
  /// `/search` carries no image, so the only picture a search result can have is one already on
  /// the device — which is exactly the case for a word the learner has met before, and exactly the
  /// case where they would notice it missing.
  WordCardSubject _subjectFor(SearchHit hit) {
    final term = _mirrored[hit.termId];
    if (term == null) return WordCardSubject.fromHit(hit);

    return WordCardSubject(
      termId: hit.termId,
      text: hit.text,
      type: hit.type,
      transcription: hit.transcription ?? term.transcription,
      translation: hit.translation ?? term.translation,
      description: hit.description ?? term.description,
      example: hit.example ?? term.example,
      exampleTranslation: hit.exampleTranslation ?? term.exampleTranslation,
      cefr: hit.cefr,
      imageUrl: term.imageUrl,
      imageAuthor: term.imageAuthor,
      imageAuthorUrl: term.imageAuthorUrl,
      folders: hit.folders,
    );
  }

  /// Open the word's own screen (кадр 06).
  Future<void> _openHitCard(SearchHit hit) async {
    await _loadMirrored(hit.termId);
    if (!mounted) return;
    // The search ended somewhere, which is the only kind of search «Вы искали» keeps.
    unawaited(_remember(word: hit.text, translation: hit.translation, cefr: hit.cefr));
    await _openCard(_subjectFor(hit));
  }

  Future<void> _openCard(WordCardSubject subject) async {
    await Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => WordCardScreen(
          subject: subject,
          // The pair the word was FOUND in. It decides which collections the save sheet may offer,
          // and which pair a collection created from that sheet is born in — «одна коллекция —
          // одна пара» (DECISIONS п. 81).
          pair: _learningPair,
          onSpeak: () => _speak(subject.text, subject.type),
          onSaved: _afterSave,
        ),
      ),
    );
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

    // Stated, not inherited: coming back from a word card that had a photo, the light glyphs it
    // asked for would otherwise stay on this screen's paper and vanish into it.
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark,
      child: Scaffold(
        backgroundColor: AppColors.paper,
        body: SafeArea(
          bottom: false,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(AppSpacing.s22, 18, AppSpacing.s22, 0),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // OVER the field, not beside it: the right-hand end of the field is already
                    // spoken for by the echo and the clear button, and a third thing there would
                    // make the busiest corner of the screen the one nobody looks at. Above it, the
                    // pair is read before the word is typed — which is the order it matters in.
                    if (_pair case final pair? when _languages != null)
                      Padding(
                        // Pulled back by the pill's own padding so its text lines up with the
                        // field's, which is what makes it read as a caption and not a control.
                        padding: const EdgeInsets.only(bottom: 2, left: 20),
                        child: Align(
                          alignment: Alignment.centerLeft,
                          child: LanguagePairBar(
                            pair: pair,
                            languages: _languages!,
                            onChanged: _changePair,
                          ),
                        ),
                      ),
                    _field(l),
                  ],
                ),
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
    // The word the card is BEING BUILT FOR, and the line that confirms it — the same two strings
    // кадр 04 showed a moment ago, in the same order, so the small card grows into the assembling
    // one instead of being replaced by a different word.
    final headline = _hint?.headline(_query) ?? _query;

    if (_lookingUp) {
      return [
        const SizedBox(height: 30),
        AssemblingCard(term: headline, translation: _hint?.support(_query)),
      ];
    }
    if (_query.isEmpty) return _empty(l);
    if (_submitted == null) return _typing(l);
    if (_limitReached) {
      return [
        const SizedBox(height: 34),
        AiLimitCard(
          query: _hint?.headline(_submitted!) ?? _submitted!,
          translation: _hint?.support(_submitted!),
          used: _usedToday,
          cap: _dailyCap,
        ),
      ];
    }

    final exact = _exactHit;

    return exact != null ? _found(l, exact) : _missing(l);
  }

  /// Кадр 01 — nothing typed yet: three words the learner has been to before, and nothing else.
  ///
  /// No line about how many words we hold. WHERE a word lives — our catalogue, the offline
  /// dictionary, a model call — is the app's kitchen, and a count of it is a number the learner
  /// cannot use for anything.
  List<Widget> _empty(AppLocalizations l) {
    if (_recent.isEmpty) return const [];

    return [
      const SizedBox(height: AppSpacing.s26),
      SearchSectionLabel(l.searchRecentLabel),
      const SizedBox(height: AppSpacing.s8),
      for (var i = 0; i < _recent.length; i++)
        DictionaryRow(
          term: _recent[i].word,
          translation: _recent[i].translation,
          level: _recent[i].cefr,
          trailing: RowTrailing.level,
          showDivider: i < _recent.length - 1,
          onTap: () => _submit(_recent[i].word),
        ),
    ];
  }

  /// Кадр 02 — mid-typing. The list of words is the main object on the page: it is the only thing
  /// that leads anywhere, so it is set at reading size with translations, and the pills it replaced
  /// are gone. Same two sources as before — catalogue first, dictionary after.
  ///
  /// UNLABELLED, and that is the point. The rows come from two places and the learner has no use
  /// for the difference: one opens its card, the other becomes the query, and both are just words.
  List<Widget> _typing(AppLocalizations l) {
    final suggestions = mergeSuggestions(known: _hits, dictionary: _dictionaryHits);

    return [
      const SizedBox(height: 20),
      if (suggestions.isNotEmpty) ...[
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
      SearchResultCard(subject: _subjectFor(exact), onOpen: () => _openHitCard(exact)),
      if (rest.isNotEmpty) ...[
        const SizedBox(height: 14),
        SearchSectionLabel(l.searchSimilar),
        const SizedBox(height: 2),
        for (var i = 0; i < rest.length; i++)
          DictionaryRow(
            term: rest[i].text,
            translation: rest[i].translation,
            height: 52,
            termStyle: AppText.searchRowTerm.copyWith(fontSize: 19),
            showDivider: i < rest.length - 1,
            onTap: () => _openHitCard(rest[i]),
          ),
      ],
    ];
  }

  /// Кадр 04 — a word we do not hold yet. A SMALL CARD of it, not a refusal.
  ///
  /// The instant translation is a full answer and is set as one: the term large in the antiqua the
  /// word card uses, the confirming line under it in ink. The learner asked what a word means and
  /// has been told, free, in the time it took to press Enter.
  ///
  /// THE HEADLINE IS ALWAYS THE ENGLISH WORD, whichever half of the pair was typed. Somebody who
  /// types «случай» is reaching for a word they cannot yet name, and the name is the thing they
  /// came for; the query goes underneath, as confirmation that we understood it. Somebody who
  /// types «occasion» sees their own word large and its meaning underneath. Same layout, same
  /// hierarchy, no announcement — the screen says nothing about languages, direction or detection.
  /// It just answers.
  ///
  /// What the button then sells is honestly what is missing — meaning, example, photo — rather than
  /// «find», which would be selling something already delivered. And nothing on this screen says
  /// the word is «not in the database»: where a word lives is the app's kitchen. The difference
  /// between a word we hold and a new one is expressed by ONE thing, the presence of this button.
  List<Widget> _missing(AppLocalizations l) {
    final near = _hits;
    final asked = _submitted!;
    final hint = _hint;

    // Too long to be a word or a phrase. A calm line about what the field is FOR, and no button:
    // there is nothing here to build a card out of.
    if (hint?.queryTooLong ?? false) {
      return [const SizedBox(height: 34), Text(l.searchQueryTooLong, style: AppText.searchNote)];
    }

    final headline = hint?.headline(asked) ?? asked;
    final support = hint?.support(asked);

    return [
      const SizedBox(height: 34),
      Text(headline, style: AppText.cardTerm),
      if ((support ?? '').isNotEmpty) ...[
        const SizedBox(height: AppSpacing.s12),
        Text(support!, style: AppText.cardTranslation),
      ],
      const SizedBox(height: AppSpacing.s26),
      PrimaryButton(
        label: _searching ? l.searchLooking : l.searchBuildCard,
        minHeight: 54,
        trailingIcon: LucideIcons.sparkles,
        enabled: !_searching,
        onPressed: _askAi,
      ),
      const SizedBox(height: 10),
      Text(l.searchBuildCardNote, textAlign: TextAlign.center, style: AppText.searchFootnote),
      // Advice, in the same quiet footnote the other outcomes use. Not red, not a banner: the app
      // did not break, the spelling did.
      if (_notRecognized) ...[
        const SizedBox(height: AppSpacing.s12),
        Text(l.searchNotRecognized, textAlign: TextAlign.center, style: AppText.searchFootnote),
      ],
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
          child: SearchSectionLabel(l.searchSimilar),
        ),
        for (var i = 0; i < near.length; i++)
          DictionaryRow(
            term: near[i].text,
            translation: near[i].translation,
            height: 52,
            termStyle: AppText.searchRowTerm.copyWith(fontSize: 19),
            showDivider: i < near.length - 1,
            onTap: () => _openHitCard(near[i]),
          ),
      ],
    ];
  }

  void _openHit(String termId) {
    for (final hit in _hits) {
      if (hit.termId == termId) {
        unawaited(_openHitCard(hit));

        return;
      }
    }
  }

  /// Spoken in the language BEING LEARNED, which is the language every search result is in — read
  /// off the pair rather than pinned to English, or a Polish word would be pronounced by an English
  /// voice the moment the deployment teaches more than one language.
  void _speak(String text, String type) {
    _pronouncer.speak(
      Word(termId: 'search', term: text, translation: '', type: type),
      targetLang: _learningPair?.learned ?? 'en',
    );
  }
}
