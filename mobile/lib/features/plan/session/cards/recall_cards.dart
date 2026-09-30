import 'dart:async';

import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/native_text.dart';

import '../../../../data/plan/session/session_models.dart';
import '../../../../data/plan/session/session_outcomes.dart';
import '../parts/session_bits.dart';
import 'card_kit.dart';

/// «ВСПОМНИ СВОИ РЕПЛИКИ» — the rehearsal's overview (кадр 37-3, наряд CLIENT-CONV-1b).
///
/// One page per scene of the plan, in the plan's order, each holding the learner's OWN lines of that scene: the line
/// in Literata, its translation, and «прослушать» 44 beside it. Nothing here is checked and nothing is chosen — the
/// overview reminds, it does not examine («Вычтено: галочки и счёт»). The scenes turn by a swipe or by «Дальше»;
/// on the last one the button reads «Дальше — повтори вслух», and that tap is the card's one answer (`passed`): what
/// follows is five or six of these lines said aloud on 35-4, then the stage summary 30-6.
///
/// The strip above the card follows the page: the scene named there is the scene whose lines are on screen
/// ([CardEnv.showScene]).
class RecallScenesCard extends StatefulWidget {
  const RecallScenesCard({super.key, required this.env, required this.payload});

  final CardEnv env;
  final RecallScenesPayload payload;

  @override
  State<RecallScenesCard> createState() => _RecallScenesCardState();
}

class _RecallScenesCardState extends State<RecallScenesCard> {
  final PageController _pages = PageController();
  int _page = 0;
  bool _answered = false;

  /// The scenes that have lines to show — a scene whose lesson holds no complete exchange would be an empty page.
  late final List<RecallScene> _scenes = [
    for (final s in widget.payload.scenes)
      if (s.lines.isNotEmpty) s,
  ];

  CardEnv get env => widget.env;

  /// The last scene is on screen — or there is none to show, and the sheet is walked by the one tap all the same.
  bool get _last => _page >= _scenes.length - 1;

  @override
  void initState() {
    super.initState();
    if (_scenes.isNotEmpty) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) env.showScene?.call(_scenes.first.sceneId);
      });
    }
  }

  @override
  void dispose() {
    _pages.dispose();
    super.dispose();
  }

  void _onPage(int page) {
    setState(() => _page = page);
    unawaited(env.voice.stop());
    env.showScene?.call(_scenes[page].sceneId);
  }

  void _next() {
    if (!_last) {
      final reduce = MediaQuery.maybeDisableAnimationsOf(context) ?? false;
      if (reduce) {
        _pages.jumpToPage(_page + 1);
      } else {
        unawaited(_pages.nextPage(duration: AppMotion.sessionCardChange, curve: AppMotion.sessionEaseOut));
      }
      return;
    }
    if (_answered) return;
    _answered = true;
    env.submit(const SessionAnswer(result: SessionResult.passed, attempts: 1));
    unawaited(env.next());
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(kSessionGutter, 24, kSessionGutter, 0),
          child: Text(l.planSessionRecallTask, key: const ValueKey('recall-task'), style: AppTextSession.task),
        ),
        const SizedBox(height: 14),
        Expanded(
          child: _SoftEdges(
            child: PageView.builder(
              key: const ValueKey('recall-pages'),
              controller: _pages,
              itemCount: _scenes.length,
              onPageChanged: _onPage,
              itemBuilder: (_, i) => _ScenePage(scene: _scenes[i], env: env),
            ),
          ),
        ),
        SessionDock(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              if (_scenes.length > 1) ...[
                _PageDots(count: _scenes.length, current: _page),
                const SizedBox(height: 20),
              ],
              DockButton(
                key: const ValueKey('recall-next'),
                label: _last ? l.planSessionRecallLast : l.planSessionNext,
                busy: env.advancing,
                onTap: _next,
              ),
            ],
          ),
        ),
      ],
    );
  }
}

/// A scene longer than the screen (seven lines on a doctor's visit) scrolls in its page; the page's edges FADE rather
/// than cut a line in half (the live pass of CLIENT-CONV-1b: the seventh line stood sliced under the dots). The top
/// fade covers only the first line's own air, so a page at rest shows every glyph whole.
class _SoftEdges extends StatelessWidget {
  const _SoftEdges({required this.child});

  final Widget child;

  static const double _top = 12;
  static const double _bottom = 28;

  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, box) {
      final h = box.maxHeight;
      if (!h.isFinite || h <= _top + _bottom) return child;
      return ShaderMask(
        key: const ValueKey('recall-soft-edges'),
        blendMode: BlendMode.dstIn,
        shaderCallback: (rect) => LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          // A mask: only the alpha counts — clear at the edges, whole in between.
          colors: const [Colors.transparent, Colors.black, Colors.black, Colors.transparent],
          stops: [0, _top / h, 1 - _bottom / h, 1],
        ).createShader(rect),
        child: child,
      );
    },
  );
}

/// One scene's page: its lines under hairlines, scrolled when a small phone cannot hold them.
class _ScenePage extends StatelessWidget {
  const _ScenePage({required this.scene, required this.env});

  final RecallScene scene;
  final CardEnv env;

  @override
  Widget build(BuildContext context) => SingleChildScrollView(
    key: ValueKey('recall-page-${scene.sceneId}'),
    // Under the last line — the bottom fade and air over it: scrolled to the end, the line stands clear of the fade.
    padding: const EdgeInsets.fromLTRB(kSessionGutter, 0, kSessionGutter, _SoftEdges._bottom + 8),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (final (i, line) in scene.lines.indexed)
          Container(
            key: ValueKey('recall-line-${scene.sceneId}-${line.ref}'),
            padding: const EdgeInsets.symmetric(vertical: 12),
            decoration: BoxDecoration(
              border: i == 0 ? null : const Border(top: BorderSide(color: AppColors.markerOutline)),
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(line.textTarget, style: AppTextSession.target22),
                      const SizedBox(height: 2),
                      Text(context.nativeText(line.textNative), style: AppTextSession.body),
                    ],
                  ),
                ),
                const SizedBox(width: 12),
                _LineListen(env: env, line: line, playKey: 'recall-${scene.sceneId}-${line.ref}'),
              ],
            ),
          ),
      ],
    ),
  );
}

/// «ПРОСЛУШАТЬ» 44 OF A LINE (37-3): a brass circle with the speaker; while the line plays the circle gives way to a
/// brass wave of nine bars in the same 44 box («реплика играет»).
class _LineListen extends StatelessWidget {
  const _LineListen({required this.env, required this.line, required this.playKey});

  final CardEnv env;
  final CardOwnLine line;
  final String playKey;

  /// The canvas's nine bars of «реплика играет».
  static const _bars = [8.0, 14.0, 20.0, 24.0, 18.0, 12.0, 20.0, 10.0, 6.0];

  @override
  Widget build(BuildContext context) => ValueListenableBuilder<Object?>(
    valueListenable: env.voice.playing,
    builder: (context, playing, _) {
      final now = playing == playKey;
      return Semantics(
        button: true,
        label: AppLocalizations.of(context).planWindowListen,
        child: GestureDetector(
          behavior: HitTestBehavior.opaque,
          onTap: () => unawaited(env.voice.play(line.audio, fallback: line.textTarget, key: playKey)),
          child: SizedBox(
            key: ValueKey('recall-listen-$playKey'),
            width: 44,
            height: 44,
            child: Center(
              child: now
                  // Nine bars 3 wide and 3 apart are 51 — wider than the 44 box, exactly as the frame draws them: the
                  // wave stands centred on the circle's place and spills into the air on both sides.
                  ? OverflowBox(
                      maxWidth: double.infinity,
                      child: SessionWave(key: const ValueKey('recall-listen-wave'), heights: _bars, playing: true),
                    )
                  : Container(
                      width: 44,
                      height: 44,
                      alignment: Alignment.center,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        border: Border.all(color: AppColors.brassInk, width: 1.5),
                      ),
                      child: const Icon(LucideIcons.volume1, size: 18, color: AppColors.brassInk),
                    ),
            ),
          ),
        ),
      );
    },
  );
}

/// The scenes as dots (37-3): the current one filled ink 10, the others an ink outline 8 at 22 %, 12 apart.
class _PageDots extends StatelessWidget {
  const _PageDots({required this.count, required this.current});

  final int count;
  final int current;

  @override
  Widget build(BuildContext context) => SizedBox(
    key: const ValueKey('recall-dots'),
    height: 10,
    child: Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        for (var i = 0; i < count; i++) ...[
          if (i > 0) const SizedBox(width: 12),
          Container(
            key: ValueKey('recall-dot-$i${i == current ? '-current' : ''}'),
            width: i == current ? 10 : 8,
            height: i == current ? 10 : 8,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: i == current ? AppColors.ink : null,
              border: i == current ? null : Border.all(color: AppColors.markerOutline, width: 1.5),
            ),
          ),
        ],
      ],
    ),
  );
}
