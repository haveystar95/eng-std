import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/l10n/app_localizations.dart';

import '../../../data/plan_models.dart';
import '../../../data/pronouncer.dart';
import 'entry_ui.dart';

/// ШАГ СЛУХА — кадры V4·03б (плеер) и 03в/03г (итог).
///
/// A modal route and not a step of the host, «чтобы читалось как отступление от флоу» (переходы
/// записки): the offer belongs to the entry, but the minute of listening is a departure from it and
/// comes up from the bottom like a sheet.
///
/// ## It is not a test, and the code is what makes that true
///
/// There is no scoring here and no right answer — «Понял» and «Не совсем» are drawn identically,
/// neither is terracotta, and nothing is remembered except the pair (line, verdict). The one thing
/// the taps buy is a sentence about the PLAN, and that sentence is shown on the last frame before
/// anything is sent anywhere.
///
/// «Хватит» leaves at any moment and keeps what was already answered: a person who heard two lines
/// and said one of them was hard has told the plan something true, and throwing that away to punish
/// an early exit would be the app arguing with its own «это не тест».
class EntryListenScreen extends StatefulWidget {
  const EntryListenScreen({super.key, required this.lines, required this.targetLang});

  final List<ListenLine> lines;
  final String targetLang;

  @override
  State<EntryListenScreen> createState() => _EntryListenScreenState();
}

class _EntryListenScreenState extends State<EntryListenScreen> {
  final _pronouncer = Pronouncer();
  final _answers = <ListenAnswer>[];

  int _index = 0;
  bool _showText = false;
  bool _speaking = false;

  /// The verdict screen (кадр V4·03в/03г). A separate state and not a separate route: the frames
  /// share the header and the dots with the player, and pushing a second route would replay the
  /// bottom-up entrance for what is the same step arriving at its end.
  bool _done = false;

  @override
  void initState() {
    super.initState();
    // The first line plays on its own — the step promised «послушай», and a play button the learner
    // has to find first turns a minute of listening into a puzzle about the interface.
    WidgetsBinding.instance.addPostFrameCallback((_) => _play());
  }

  @override
  void dispose() {
    _pronouncer.release();
    super.dispose();
  }

  ListenLine get _line => widget.lines[_index];

  Future<void> _play() async {
    if (!mounted) return;
    setState(() => _speaking = true);
    await _pronouncer.speakText(_line.text, targetLang: widget.targetLang);
    if (mounted) setState(() => _speaking = false);
  }

  /// «Понял» / «Не совсем» — the same weight, and the same code path.
  void _answer(bool understood) {
    AppHaptics.light();
    _answers.add(ListenAnswer(line: _line, understood: understood));

    if (_index + 1 >= widget.lines.length) {
      setState(() => _done = true);
      return;
    }

    setState(() {
      _index++;
      _showText = false;
    });
    _play();
  }

  /// «Хватит» — out of the step, keeping what was already said.
  void _enough() {
    AppHaptics.light();
    if (_answers.isEmpty) {
      Navigator.of(context).pop(const <ListenAnswer>[]);
      return;
    }
    setState(() => _done = true);
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark,
      child: Scaffold(
        backgroundColor: AppColors.paper,
        body: SafeArea(
          bottom: false,
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 24),
            child: _done ? _result(l) : _player(l),
          ),
        ),
      ),
    );
  }

  // ── кадр V4·03б ─────────────────────────────────────────────────────────────────────────────

  Widget _player(AppLocalizations l) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      EntryHeader(
        kicker: '',
        step: _index + 1,
        steps: widget.lines.length,
        onBack: () => Navigator.of(context).pop(const <ListenAnswer>[]),
        trailing: Semantics(
          button: true,
          child: InkWell(
            onTap: _enough,
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 8),
              child: Text(
                l.planListenEnough,
                style: AppText.translation.copyWith(fontSize: 13.5, color: AppColors.secondary),
              ),
            ),
          ),
        ),
      ),
      Expanded(
        child: ListView(
          padding: const EdgeInsets.only(top: 24, bottom: 24),
          children: [
            Center(child: EntryDots(step: _index + 1, steps: widget.lines.length)),
            const SizedBox(height: 24),
            EntryOverline(
              _line.place.trim().isEmpty
                  ? l.planListenLine(_index + 1)
                  : l.planListenLineAt(_index + 1, _line.place),
              color: AppColors.brassInk,
              center: true,
            ),
            const SizedBox(height: 30),
            // The biggest object of the whole flow — «кнопка воспроизведения — самый крупный объект
            // флоу» (кадр V4·03б). 112, and dark: the line is the thing to do here.
            Center(
              child: Semantics(
                button: true,
                label: l.planListenListen,
                child: InkResponse(
                  onTap: _play,
                  radius: 60,
                  child: Container(
                    width: 112,
                    height: 112,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: AppColors.ink,
                      boxShadow: [
                        BoxShadow(
                          color: AppColors.ink.withValues(alpha: 0.24),
                          blurRadius: 32,
                          offset: const Offset(0, 14),
                        ),
                        BoxShadow(
                          color: AppColors.brassInk.withValues(alpha: 0.14),
                          blurRadius: 0,
                          spreadRadius: 8,
                        ),
                      ],
                    ),
                    child: const Icon(Icons.play_arrow_rounded, size: 44, color: AppColors.paper),
                  ),
                ),
              ),
            ),
            const SizedBox(height: 22),
            // The wave animates ONLY while something is playing (переходы записки). A wave that
            // moves in silence is a decoration pretending to be a signal.
            Center(child: _Wave(active: _speaking)),
            const SizedBox(height: 26),
            Text(
              l.planListenReplayHint,
              textAlign: TextAlign.center,
              style: AppText.translation.copyWith(
                fontSize: 14,
                height: 1.55,
                color: AppColors.secondary,
              ),
            ),
            const SizedBox(height: 18),
            Center(
              child: _showText
                  ? Column(
                      children: [
                        Text(
                          _line.text,
                          textAlign: TextAlign.center,
                          style: AppText.collectionNameCard.copyWith(fontSize: 18, height: 1.35),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          _line.translation,
                          textAlign: TextAlign.center,
                          style: AppText.translation.copyWith(
                            fontSize: 13.5,
                            height: 1.4,
                            color: AppColors.secondary,
                          ),
                        ),
                      ],
                    )
                  : Semantics(
                      button: true,
                      child: InkWell(
                        onTap: () {
                          AppHaptics.light();
                          setState(() => _showText = true);
                        },
                        child: Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 6),
                          child: Text(
                            l.planListenShowText,
                            style: AppText.translation.copyWith(
                              fontSize: 13.5,
                              color: AppColors.destructiveText,
                            ),
                          ),
                        ),
                      ),
                    ),
            ),
            const SizedBox(height: 40),
            EntryOverline(l.planListenFeelLabel, center: true),
            const SizedBox(height: 14),
            // BOTH THE SAME. Not a primary and a secondary: neither answer is the right one, and a
            // terracotta «Понял» would make the other one a failure.
            Row(
              children: [
                Expanded(
                  child: EntrySecondary(label: l.planListenGot, onPressed: () => _answer(true)),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: EntrySecondary(
                    label: l.planListenNotQuite,
                    onPressed: () => _answer(false),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            Text(
              l.planListenNoRightAnswer,
              textAlign: TextAlign.center,
              style: AppText.translation.copyWith(
                fontSize: 12.5,
                height: 1.5,
                color: AppColors.tertiary,
              ),
            ),
          ],
        ),
      ),
    ],
  );

  // ── кадры V4·03в / 03г ──────────────────────────────────────────────────────────────────────

  Widget _result(AppLocalizations l) {
    final emphasis = ListenEmphasis.of(_answers);
    final speaking = emphasis == ListenEmphasis.speaking;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        // Every dot brass: «три латунные точки закрылись: шаг прожит».
        EntryHeader(
          kicker: '',
          step: widget.lines.length,
          steps: widget.lines.length,
          dotsAllDone: true,
        ),
        Expanded(
          child: ListView(
            padding: const EdgeInsets.only(top: 44, bottom: 24),
            children: [
              Center(
                child: Container(
                  width: 52,
                  height: 52,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    border: Border.all(color: AppColors.brassInk.withValues(alpha: 0.4)),
                  ),
                  child: const Icon(Icons.check, size: 24, color: AppColors.brassInk),
                ),
              ),
              const SizedBox(height: 26),
              // ONE LINE, and no score anywhere — «никаких баллов, процентов и „твой уровень“».
              Text(
                speaking ? l.planListenResultSpeaking : l.planListenResultUnderstanding,
                textAlign: TextAlign.center,
                style: AppText.collectionNameScreen.copyWith(fontSize: 28, height: 1.24),
              ),
              const SizedBox(height: 16),
              Text(
                speaking
                    ? l.planListenResultSpeakingBody
                    : l.planListenResultUnderstandingBody,
                textAlign: TextAlign.center,
                style: AppText.translation.copyWith(
                  fontSize: 15,
                  height: 1.6,
                  color: AppColors.inkBody,
                ),
              ),
              const SizedBox(height: 28),
              Container(
                padding: const EdgeInsets.symmetric(vertical: 16),
                decoration: BoxDecoration(
                  border: Border(
                    top: BorderSide(color: AppColors.brassInk.withValues(alpha: 0.3)),
                    bottom: BorderSide(color: AppColors.brassInk.withValues(alpha: 0.3)),
                  ),
                ),
                child: Text(
                  l.planListenResultFootnote,
                  textAlign: TextAlign.center,
                  style: AppText.translation.copyWith(
                    fontSize: 13.5,
                    height: 1.55,
                    color: AppColors.secondary,
                  ),
                ),
              ),
              const SizedBox(height: 36),
              EntryCta(
                label: l.planEntryNext,
                onPressed: () => Navigator.of(context).pop(List<ListenAnswer>.of(_answers)),
              ),
              const SizedBox(height: 40),
            ],
          ),
        ),
      ],
    );
  }
}

/// The nine bars under the play button. Alive only while a line is playing.
class _Wave extends StatefulWidget {
  const _Wave({required this.active});

  final bool active;

  @override
  State<_Wave> createState() => _WaveState();
}

class _WaveState extends State<_Wave> with SingleTickerProviderStateMixin {
  static const _heights = [9.0, 17.0, 24.0, 14.0, 20.0, 8.0, 15.0, 11.0, 6.0];

  late final AnimationController _c = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 900),
  );

  @override
  void initState() {
    super.initState();
    if (widget.active) _c.repeat(reverse: true);
  }

  @override
  void didUpdateWidget(_Wave old) {
    super.didUpdateWidget(old);
    if (widget.active && !_c.isAnimating) {
      _c.repeat(reverse: true);
    } else if (!widget.active && _c.isAnimating) {
      _c.stop();
      _c.value = 0;
    }
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AnimatedBuilder(
    animation: _c,
    builder: (context, _) => SizedBox(
      height: 26,
      child: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          for (var i = 0; i < _heights.length; i++) ...[
            if (i > 0) const SizedBox(width: 3),
            Container(
              width: 3,
              height: widget.active
                  ? (_heights[i] * (0.55 + 0.45 * ((_c.value + i / _heights.length) % 1)))
                  : _heights[i],
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(2),
                color: i == 2 || i == 3
                    ? AppColors.brassInk
                    : (i == 4
                          ? AppColors.brassInk.withValues(alpha: 0.5)
                          : AppColors.ink.withValues(alpha: 0.2)),
              ),
            ),
          ],
        ],
      ),
    ),
  );
}
