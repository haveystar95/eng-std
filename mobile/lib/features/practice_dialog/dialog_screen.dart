import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/l10n/language_endonyms.dart';

import '../../data/api_client.dart';
import '../../data/models.dart';
import '../../data/providers.dart';
import 'dialog_controller.dart';
import 'dialog_models.dart';
import 'dialog_providers.dart';

/// Open the pre-start sheet (кадр: «что будет» + полоса target_words + «Начать разговор»). Reached
/// from [DialogEntryButton] on the collection screen. On «Начать» it pushes [PracticeDialogScreen].
Future<void> showPracticeDialogPrestart(
  BuildContext context, {
  required String collectionId,
  required String title,
  required String targetLang,
}) {
  return showAppBottomSheet<void>(
    context: context,
    builder: (_) =>
        _PrestartSheet(collectionId: collectionId, title: title, targetLang: targetLang),
  );
}

class _PrestartSheet extends ConsumerWidget {
  const _PrestartSheet({required this.collectionId, required this.title, required this.targetLang});
  final String collectionId, title, targetLang;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final words = ref.watch(collectionWordsProvider(collectionId)).value ?? const <Word>[];
    final langName = languageByCode(targetLang).endonym;

    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Container(
              width: 36,
              height: 36,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: AppColors.ink.withValues(alpha: 0.06),
              ),
              child: const Icon(LucideIcons.messageCircle, size: 19, color: AppColors.ink),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                l.practiceDialogPrestartTitle,
                style: AppText.stepTitle.copyWith(fontSize: 21),
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        Text(
          l.practiceDialogPrestartBody(langName),
          style: AppText.translation.copyWith(height: 1.4),
        ),
        const SizedBox(height: AppSpacing.s22),
        Text(
          l.practiceDialogPrestartWordsLabel.toUpperCase(),
          style: AppText.sectionLabel.copyWith(fontSize: 11, color: AppColors.tertiary),
        ),
        const SizedBox(height: 10),
        ConstrainedBox(
          constraints: const BoxConstraints(maxHeight: 132),
          child: SingleChildScrollView(
            child: Wrap(
              spacing: 7,
              runSpacing: 7,
              children: [for (final w in words) _WordPill(text: w.term)],
            ),
          ),
        ),
        const SizedBox(height: AppSpacing.s22),
        PrimaryButton(
          label: l.practiceDialogStart,
          minHeight: 52,
          trailingIcon: LucideIcons.arrowRight,
          onPressed: () {
            AppHaptics.light();
            Navigator.of(context).pop(); // close the sheet
            Navigator.of(context).push(
              MaterialPageRoute(
                builder: (_) => PracticeDialogScreen(
                  collectionId: collectionId,
                  title: title,
                  targetLang: targetLang,
                ),
              ),
            );
          },
        ),
      ],
    );
  }
}

/// A plain outline word pill (pre-start bar). The live coverage bar uses [_CoverageChip].
class _WordPill extends StatelessWidget {
  const _WordPill({required this.text});
  final String text;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(AppRadii.chip),
        border: Border.all(color: AppColors.hairline),
      ),
      child: Text(text, style: AppTextExercise.dictionaryChip.copyWith(fontSize: 15)),
    );
  }
}

/// The conversation itself (start → speaking/listening → finale). Owns a [DialogController] for the
/// collection; nothing here writes to reviews or progress. Voice/transport is the fake scripted
/// channel until WebRTC lands (see [realtimeChannelFactoryProvider]).
class PracticeDialogScreen extends ConsumerStatefulWidget {
  const PracticeDialogScreen({
    super.key,
    required this.collectionId,
    required this.title,
    required this.targetLang,
  });

  final String collectionId, title, targetLang;

  @override
  ConsumerState<PracticeDialogScreen> createState() => _PracticeDialogScreenState();
}

class _PracticeDialogScreenState extends ConsumerState<PracticeDialogScreen> {
  late final DialogController _controller;

  @override
  void initState() {
    super.initState();
    _controller = DialogController(
      repository: ref.read(dialogRepositoryProvider),
      channel: ref.read(realtimeChannelFactoryProvider)(),
      collectionId: widget.collectionId,
      clientId: ApiClient.ulid(),
    );
    _controller.start();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _confirmExit() async {
    final l = AppLocalizations.of(context);
    final ok = await showCenterAlert(
      context: context,
      title: l.practiceDialogExitTitle,
      message: l.practiceDialogExitMessage,
      confirmLabel: l.practiceDialogExitConfirm,
      cancelLabel: l.practiceDialogExitCancel,
    );
    if (ok == true) {
      AppHaptics.light();
      await _controller.finish(); // land on the finale so the user still gets a recap
    }
  }

  void _onClose() {
    final s = _controller.status;
    if (s == DialogStatus.active || s == DialogStatus.finishing) {
      _confirmExit();
    } else {
      Navigator.of(context).maybePop();
    }
  }

  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark,
      child: AnimatedBuilder(
        animation: _controller,
        builder: (context, _) {
          final active =
              _controller.status == DialogStatus.active ||
              _controller.status == DialogStatus.finishing;
          return PopScope(
            canPop: !active,
            onPopInvokedWithResult: (didPop, _) {
              if (didPop) {
                // Leaving a finished/errored dialog: refresh the collection-screen result row
                // (an early exit that ran finish() is a result too). ref is safe — still mounted.
                ref.invalidate(lastDialogProvider(widget.collectionId));
              } else {
                _confirmExit();
              }
            },
            child: Scaffold(
              backgroundColor: AppColors.paper,
              body: SafeArea(child: _body()),
            ),
          );
        },
      ),
    );
  }

  Widget _body() {
    switch (_controller.status) {
      case DialogStatus.idle:
      case DialogStatus.starting:
        return _Centered(child: _StateIndicator(phase: DialogPhase.connecting));
      case DialogStatus.error:
        return _ErrorView(
          kind: _controller.errorKind ?? DialogErrorKind.unknown,
          resetsAt: _controller.rateResetsAt,
          onClose: () => Navigator.of(context).maybePop(),
        );
      case DialogStatus.finished:
        return _Finale(
          summary: _controller.summary,
          onDone: () => Navigator.of(context).maybePop(),
        );
      case DialogStatus.active:
      case DialogStatus.finishing:
        return _Conversation(controller: _controller, onClose: _onClose);
    }
  }
}

class _Centered extends StatelessWidget {
  const _Centered({required this.child});
  final Widget child;
  @override
  Widget build(BuildContext context) => Center(child: child);
}

// ── Active conversation ──

class _Conversation extends StatelessWidget {
  const _Conversation({required this.controller, required this.onClose});
  final DialogController controller;
  final VoidCallback onClose;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        _Header(remainingSeconds: controller.remainingSeconds, onClose: onClose),
        _CoverageBar(words: controller.targetWords, used: controller.usedCount),
        const SizedBox(height: 6),
        _StateIndicator(phase: controller.phase),
        const SizedBox(height: 6),
        Expanded(child: _Feed(feed: controller.feed)),
      ],
    );
  }
}

class _Header extends StatelessWidget {
  const _Header({required this.remainingSeconds, required this.onClose});
  final int remainingSeconds;
  final VoidCallback onClose;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(14, 8, AppSpacing.screenH, 4),
      child: Row(
        children: [
          InkResponse(
            radius: 22,
            onTap: onClose,
            child: const SizedBox(
              width: 40,
              height: 40,
              child: Icon(LucideIcons.x, size: 22, color: AppColors.secondary),
            ),
          ),
          const Spacer(),
          const Icon(LucideIcons.clock, size: 13, color: AppColors.tertiary),
          const SizedBox(width: 5),
          Text(
            _fmt(remainingSeconds),
            style: AppText.counterSmall.copyWith(fontSize: 13, color: AppColors.tertiary),
          ),
        ],
      ),
    );
  }

  static String _fmt(int seconds) {
    final m = (seconds ~/ 60).toString();
    final s = (seconds % 60).toString().padLeft(2, '0');
    return '$m:$s';
  }
}

/// The coverage bar: one chip per target word, each painting from outline to ink fill the moment
/// the server marks it used (micro «прокраска», §4е segmentFill). Scrolls if the words overflow.
class _CoverageBar extends StatelessWidget {
  const _CoverageBar({required this.words, required this.used});
  final List<TargetWord> words;
  final int used;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Padding(
      padding: const EdgeInsets.fromLTRB(AppSpacing.screenH, 2, AppSpacing.screenH, 0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            l.practiceDialogCoverageLabel(used, words.length),
            style: AppText.counterSmall.copyWith(fontSize: 11.5, color: AppColors.tertiary),
          ),
          const SizedBox(height: 7),
          ConstrainedBox(
            constraints: const BoxConstraints(maxHeight: 90),
            child: SingleChildScrollView(
              child: Wrap(
                spacing: 6,
                runSpacing: 6,
                children: [for (final w in words) _CoverageChip(text: w.text, used: w.used)],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _CoverageChip extends StatelessWidget {
  const _CoverageChip({required this.text, required this.used});
  final String text;
  final bool used;

  @override
  Widget build(BuildContext context) {
    final reduce = MediaQuery.disableAnimationsOf(context);
    final dur = reduce ? Duration.zero : AppMotion.segmentFill;
    return AnimatedContainer(
      duration: dur,
      curve: AppMotion.linear,
      padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 6),
      decoration: BoxDecoration(
        color: used ? AppColors.ink : Colors.transparent,
        borderRadius: BorderRadius.circular(AppRadii.chip),
        border: Border.all(color: used ? AppColors.ink : AppColors.hairline),
      ),
      child: AnimatedDefaultTextStyle(
        duration: dur,
        style: AppTextExercise.dictionaryChip.copyWith(
          fontSize: 14,
          color: used ? AppColors.paper : AppColors.ink,
        ),
        child: Text(text),
      ),
    );
  }
}

/// The centre indicator: an ink waveform while the bot [DialogPhase.botSpeaking], a pulsing mic
/// while [DialogPhase.listening], a faint waveform while [DialogPhase.connecting]. Caption below.
class _StateIndicator extends StatefulWidget {
  const _StateIndicator({required this.phase});
  final DialogPhase phase;

  @override
  State<_StateIndicator> createState() => _StateIndicatorState();
}

class _StateIndicatorState extends State<_StateIndicator> with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 900),
  )..repeat(reverse: true);

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final reduce = MediaQuery.disableAnimationsOf(context);
    final listening = widget.phase == DialogPhase.listening;
    final caption = switch (widget.phase) {
      DialogPhase.listening => l.practiceDialogStateListening,
      DialogPhase.botSpeaking => l.practiceDialogStateSpeaking,
      DialogPhase.connecting => l.practiceDialogStateConnecting,
      DialogPhase.closed => l.practiceDialogStateConnecting,
    };
    final faint = widget.phase == DialogPhase.connecting;

    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        SizedBox(
          height: 56,
          child: Center(
            child: listening
                ? _MicPulse(animation: _c, reduce: reduce)
                : _Waveform(animation: _c, reduce: reduce, faint: faint),
          ),
        ),
        const SizedBox(height: 6),
        Text(
          caption,
          style: AppText.transcription.copyWith(fontSize: 12.5, color: AppColors.secondary),
        ),
      ],
    );
  }
}

class _Waveform extends AnimatedWidget {
  const _Waveform({required this.animation, required this.reduce, required this.faint})
    : super(listenable: animation);
  final Animation<double> animation;
  final bool reduce, faint;

  static const _bars = [0.4, 0.75, 1.0, 0.6, 0.9, 0.5, 0.8, 0.35];

  @override
  Widget build(BuildContext context) {
    final t = reduce ? 1.0 : animation.value;
    final base = faint ? 0.28 : 0.9;
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        for (var i = 0; i < _bars.length; i++) ...[
          if (i > 0) const SizedBox(width: 4),
          Container(
            width: 3,
            height: 8 + 26 * _bars[i] * (0.35 + 0.65 * (i.isEven ? t : 1 - t)),
            decoration: BoxDecoration(
              color: AppColors.ink.withValues(alpha: base * (0.35 + 0.65 * _bars[i])),
              borderRadius: BorderRadius.circular(2),
            ),
          ),
        ],
      ],
    );
  }
}

class _MicPulse extends AnimatedWidget {
  const _MicPulse({required this.animation, required this.reduce}) : super(listenable: animation);
  final Animation<double> animation;
  final bool reduce;

  @override
  Widget build(BuildContext context) {
    final t = reduce ? 0.5 : animation.value;
    final ring = 40.0 + 16.0 * t;
    return SizedBox(
      width: 56,
      height: 56,
      child: Stack(
        alignment: Alignment.center,
        children: [
          Container(
            width: ring,
            height: ring,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: AppColors.ink.withValues(alpha: 0.06 * (1 - t)),
            ),
          ),
          Container(
            width: 40,
            height: 40,
            alignment: Alignment.center,
            decoration: const BoxDecoration(shape: BoxShape.circle, color: AppColors.ink),
            child: const Icon(LucideIcons.mic, size: 19, color: AppColors.paper),
          ),
        ],
      ),
    );
  }
}

/// The transcript feed: bot replies (paper card, left) and recognised user speech (faint ink, right).
/// Reversed so the latest line stays pinned to the bottom.
class _Feed extends StatelessWidget {
  const _Feed({required this.feed});
  final List<TranscriptEvent> feed;

  @override
  Widget build(BuildContext context) {
    if (feed.isEmpty) return const SizedBox.shrink();
    return ListView.builder(
      reverse: true,
      padding: const EdgeInsets.fromLTRB(AppSpacing.screenH, 8, AppSpacing.screenH, 12),
      itemCount: feed.length,
      itemBuilder: (context, i) => _FeedBubble(event: feed[feed.length - 1 - i]),
    );
  }
}

class _FeedBubble extends StatelessWidget {
  const _FeedBubble({required this.event});
  final TranscriptEvent event;

  @override
  Widget build(BuildContext context) {
    final isUser = event.role == DialogRole.user;
    final bubble = Container(
      constraints: BoxConstraints(maxWidth: MediaQuery.sizeOf(context).width * 0.76),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      decoration: BoxDecoration(
        color: isUser ? AppColors.faintInk : AppColors.surfaceRaised,
        borderRadius: BorderRadius.circular(16),
        boxShadow: isUser ? null : AppShadows.card,
      ),
      child: Text(
        event.text,
        style: TextStyle(
          fontFamily: AppFonts.inter,
          fontSize: 14.5,
          height: 1.35,
          color: isUser ? AppColors.ink : AppColors.inkBody,
        ),
      ),
    );
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Align(alignment: isUser ? Alignment.centerRight : Alignment.centerLeft, child: bubble),
    );
  }
}

// ── Finale ──

class _Finale extends StatelessWidget {
  const _Finale({required this.summary, required this.onDone});
  final DialogSummary? summary;
  final VoidCallback onDone;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final s = summary;
    return Padding(
      padding: const EdgeInsets.fromLTRB(AppSpacing.screenHWide, 24, AppSpacing.screenHWide, 24),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Icon(LucideIcons.messageCircle, size: 34, color: AppColors.tertiary),
          const SizedBox(height: 16),
          Text(
            l.practiceDialogFinaleTitle,
            textAlign: TextAlign.center,
            style: AppText.displayTerm.copyWith(fontSize: 30),
          ),
          const SizedBox(height: 14),
          if (s != null && s.summary.isNotEmpty) ...[
            Text(
              s.summary,
              textAlign: TextAlign.center,
              style: AppText.usageExample.copyWith(fontSize: 16, height: 1.45),
            ),
            const SizedBox(height: 16),
          ],
          Text(
            l.practiceDialogFinaleWords(s?.wordsUsed ?? 0, s?.wordsTotal ?? 0),
            textAlign: TextAlign.center,
            style: AppText.counterHeader.copyWith(fontSize: 15, color: AppColors.secondary),
          ),
          const SizedBox(height: 28),
          PrimaryButton(label: l.practiceDialogFinaleDone, minHeight: 52, onPressed: onDone),
        ],
      ),
    );
  }
}

// ── Error ──

class _ErrorView extends StatelessWidget {
  const _ErrorView({required this.kind, required this.resetsAt, required this.onClose});
  final DialogErrorKind kind;
  final DateTime? resetsAt;
  final VoidCallback onClose;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final message = switch (kind) {
      DialogErrorKind.subscriptionRequired => l.practiceDialogErrorSubscription,
      DialogErrorKind.rateLimited =>
        resetsAt != null
            ? l.practiceDialogErrorRateLimited(_fmtTime(resetsAt!))
            : l.practiceDialogErrorRateLimitedNoTime,
      DialogErrorKind.offline => l.practiceDialogErrorOffline,
      DialogErrorKind.network || DialogErrorKind.unknown => l.practiceDialogErrorGeneric,
    };
    return Padding(
      padding: const EdgeInsets.fromLTRB(AppSpacing.screenHWide, 24, AppSpacing.screenHWide, 24),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Icon(LucideIcons.messageCircleOff, size: 34, color: AppColors.tertiary),
          const SizedBox(height: 16),
          Text(
            message,
            textAlign: TextAlign.center,
            style: AppText.translation.copyWith(height: 1.4),
          ),
          const SizedBox(height: 24),
          QuietButton(label: l.practiceDialogClose, onPressed: onClose),
        ],
      ),
    );
  }

  static String _fmtTime(DateTime t) {
    final h = t.hour.toString().padLeft(2, '0');
    final m = t.minute.toString().padLeft(2, '0');
    return '$h:$m';
  }
}
