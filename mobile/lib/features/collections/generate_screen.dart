import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:speech_to_text/speech_to_text.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/l10n/language_endonyms.dart';

import '../../data/feature_flags.dart';
import '../../data/models.dart';
import '../../data/providers.dart';
import '../paywall/paywall_screen.dart';
import 'collection_edit_dialog.dart';

/// Which speech-recognition locale a dictated situation is captured in.
///
/// The language SPOKEN into this field is the collection's source language — the learner's own —
/// because the situation («иду открывать счёт в банке») is written in it and the server reads it as
/// such. Following the UI locale instead was QA-1: an interface left in English put Russian speech
/// through `en_US` and returned transliterated nonsense, on a phone whose owner speaks Russian.
///
/// Unknown codes fall back to `en_US` — a locale the recogniser always has.
String sttLocaleFor(String sourceLang) => _sttLocales[sourceLang] ?? 'en_US';

const _sttLocales = {
  'ru': 'ru_RU', 'en': 'en_US', 'uk': 'uk_UA', 'es': 'es_ES', 'de': 'de_DE',
  'fr': 'fr_FR', 'it': 'it_IT', 'pt': 'pt_PT', 'pl': 'pl_PL', 'tr': 'tr_TR',
  'zh': 'zh_CN', 'ja': 'ja_JP',
};

/// Size presets — the user picks a feel, the system picks the count (decided: no number slider).
/// 10 / 15 / 22 (маленькая / средняя / большая).
enum _Size { small(10), medium(15), large(22);

  const _Size(this.count);
  final int count;
}

/// The AI-collection create screen (кадры 2.4 / 6a–6c), rebuilt on the paper/ink system: a situation
/// field with a rotating placeholder and voice fill, a size feel, level chips, and the target
/// language (default from the profile — omitted from the request so the server applies its own
/// fallback, B4). The docked footer carries the quota and «Сгенерировать»; the button greys out on
/// an exhausted quota. Submitting enqueues the generation (client ULID, durable offline queue) and
/// pops — the pending card on the Collections tab carries it from there.
class GenerateScreen extends ConsumerStatefulWidget {
  const GenerateScreen({super.key, this.initialTopic, this.startVoice = false});

  /// Prefilled situation when arriving from the home generation card (a tapped example chip).
  final String? initialTopic;

  /// Start voice capture immediately (the home mic opens the create screen already recording).
  final bool startVoice;

  @override
  ConsumerState<GenerateScreen> createState() => _GenerateScreenState();
}

class _GenerateScreenState extends ConsumerState<GenerateScreen> {
  final _topicCtrl = TextEditingController();
  final _topicFocus = FocusNode();
  late Set<String> _levels;
  late String _targetLang;
  bool _targetExplicit = false; // B4: send target_lang only once the user picks one
  _Size _size = _Size.medium;
  int _hintIndex = 0;
  Timer? _hintTimer;
  bool _submitting = false;

  // Voice (кадр 6c).
  final SpeechToText _speech = SpeechToText();
  bool _speechInitDone = false;
  bool _listening = false;
  String _voiceBase = ''; // text already in the field when recording began (voice appends)
  int _recSeconds = 0;
  Timer? _recTimer;

  @override
  void initState() {
    super.initState();
    final profile = ref.read(authControllerProvider).value?.profile;
    _levels = {profile?.cefrLevel ?? 'B1'};
    _targetLang = profile?.targetLanguage ?? 'en';
    if (widget.initialTopic != null) {
      // Carry the tapped chip / typed topic in, cursor at the end so editing continues from it
      // (the home card is an ENTRANCE to this screen, not a form — 2.1↔2.4). autofocus below opens
      // the keyboard on the field unless we're arriving to record.
      final t = widget.initialTopic!;
      _topicCtrl.value = TextEditingValue(text: t, selection: TextSelection.collapsed(offset: t.length));
    }
    _hintTimer = Timer.periodic(const Duration(seconds: 3), (_) {
      if (mounted && _topicCtrl.text.isEmpty) {
        setState(() => _hintIndex = (_hintIndex + 1) % _kHintCount);
      }
    });
    _topicCtrl.addListener(() => setState(() {}));
    _topicFocus.addListener(() {
      if (mounted) setState(() {}); // reflect focus in the field border
    });
    if (widget.startVoice) {
      WidgetsBinding.instance.addPostFrameCallback((_) => _startListening());
    }
  }

  @override
  void dispose() {
    _hintTimer?.cancel();
    _recTimer?.cancel();
    if (_speech.isListening) _speech.stop();
    _topicCtrl.dispose();
    _topicFocus.dispose();
    super.dispose();
  }

  String get _sourceLang => ref.read(authControllerProvider).value?.profile?.nativeLanguage ?? 'ru';

  /// Leave the screen with the keyboard closed.
  ///
  /// Popping a route whose field still holds focus does NOT always tear the text-input connection
  /// down on iOS: the owner left this screen (Отмена, and again after the generation was refused)
  /// and the keyboard stayed up over the Collections/Profile tabs for the rest of the session. The
  /// unfocus has to happen BEFORE the pop — after it the node is already detached and nothing
  /// tells the platform to hide. `primaryFocus` covers the case where focus sits somewhere else
  /// on the screen (the topic field is not the only focusable thing here).
  void _dismissKeyboard() {
    _topicFocus.unfocus();
    FocusManager.instance.primaryFocus?.unfocus();
  }

  // ── Voice ──

  Future<void> _toggleVoice() async {
    if (_listening) {
      await _stopListening();
    } else {
      await _startListening();
    }
  }

  Future<void> _startListening() async {
    // Recognise the language the situation is WRITTEN in — the collection's source language, which
    // is the owner's own (see [sttLocaleFor]). Read before any await, like every other field here.
    final localeId = sttLocaleFor(_sourceLang);
    // Dismiss the keyboard while dictating (кадр 6c — the keyboard returns on stop).
    _topicFocus.unfocus();
    if (!_speechInitDone) {
      _speechInitDone = true;
      try {
        final ok = await _speech.initialize(
          onStatus: (s) {
            if ((s == 'notListening' || s == 'done') && mounted) _endRecordingState();
          },
          onError: (_) {
            if (mounted) _endRecordingState();
          },
        );
        if (!ok) {
          _speechInitDone = false;
          _permissionDenied();
          return;
        }
      } catch (_) {
        _speechInitDone = false;
        _permissionDenied();
        return;
      }
    }
    if (!_speech.isAvailable) {
      _permissionDenied();
      return;
    }
    AppHaptics.light();
    _voiceBase = _topicCtrl.text;
    setState(() {
      _listening = true;
      _recSeconds = 0;
    });
    _recTimer?.cancel();
    _recTimer = Timer.periodic(const Duration(seconds: 1), (_) {
      if (mounted) setState(() => _recSeconds++);
    });
    await _speech.listen(
      onResult: (r) {
        if (!mounted) return;
        final combined = _combine(_voiceBase, r.recognizedWords);
        _topicCtrl.value = TextEditingValue(
          text: combined,
          selection: TextSelection.collapsed(offset: combined.length),
        );
        setState(() {});
      },
      listenOptions: SpeechListenOptions(
        partialResults: true,
        cancelOnError: true,
        listenMode: ListenMode.dictation,
        localeId: localeId,
      ),
    );
  }

  Future<void> _stopListening() async {
    await _speech.stop();
    _endRecordingState();
    // The keyboard returns for manual editing (кадр 6c).
    if (mounted) _topicFocus.requestFocus();
  }

  void _endRecordingState() {
    _recTimer?.cancel();
    _recTimer = null;
    if (mounted && _listening) setState(() => _listening = false);
  }

  void _permissionDenied() {
    if (!mounted) return;
    _endRecordingState();
    AppHaptics.warning();
    final l = AppLocalizations.of(context);
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(l.generateVoicePermissionDenied)));
  }

  static String _combine(String base, String words) {
    final b = base.trim();
    if (b.isEmpty) return words;
    if (words.isEmpty) return b;
    return '$b $words';
  }

  // ── Submit ──

  Future<void> _submit() async {
    final topic = _topicCtrl.text.trim();
    if (topic.isEmpty) {
      AppHaptics.warning();
      return;
    }
    final navigator = Navigator.of(context);
    if (_listening) await _stopListening();
    _dismissKeyboard(); // the screen is about to pop — the keyboard must not outlive it
    setState(() => _submitting = true);
    try {
      // Enqueue: a client-ULID pending row (survives an app kill and an offline start); the durable
      // queue re-sends when the network returns. Voice never triggers this — «Сгенерировать» does.
      await ref.read(generationControllerProvider).start(
            topic: topic,
            levels: _levels.toList(),
            size: _size.count,
            sourceLang: _sourceLang,
            targetLang: _targetLang,
            targetLangExplicit: _targetExplicit,
          );
      ref.invalidate(generationQuotaProvider); // the count just went up
      AppHaptics.success();
      if (mounted) navigator.pop();
    } catch (e) {
      // QA-23. This used to have no catch and no finally, and the enqueue's very first act is a
      // LOCAL DB write — so anything wrong with the local store (and on a fresh install that write
      // is the first one the app ever makes) left `_submitting` true forever: «Сгенерировать» went
      // grey and stayed grey, with the exception going only to the Flutter error handler. The
      // owner's report was exactly that — «как будто приложение отдаёт какую-то ошибку, которую не
      // видно». A failure here must always be BOTH recoverable (the button comes back) and said out
      // loud.
      debugPrint('[generate] enqueue failed: $e');
      if (mounted) {
        AppHaptics.warning();
        // A SnackBar, like every other failure this app reports (profile save, store add): the
        // screen stays open with the typed prompt intact, so «попробовать ещё раз» is one tap.
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(AppLocalizations.of(context).generateEnqueueFailed('$e'))),
        );
      }
    } finally {
      // The button comes back even when the screen is being torn down — `mounted` guards setState,
      // never the reset itself.
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<void> _pickLanguage() async {
    final chosen = await showAppBottomSheet<String>(
      context: context,
      builder: (_) => _LanguagePicker(current: _targetLang, exclude: _sourceLang),
    );
    if (chosen != null) {
      setState(() {
        _targetLang = chosen;
        _targetExplicit = true; // an explicit choice → send target_lang (no longer the profile default)
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final quota = ref.watch(generationQuotaProvider).value;
    final exhausted = quota?.exhausted ?? false;

    return PopScope(
      // Covers the exits that don't go through «Отмена» — the iOS back-swipe and the system back.
      onPopInvokedWithResult: (_, _) => _dismissKeyboard(),
      child: AnnotatedRegion<SystemUiOverlayStyle>(
        value: SystemUiOverlayStyle.dark,
        child: Scaffold(
          backgroundColor: AppColors.paper,
          // The footer lives inside the body (not bottomNavigationBar) so it docks above the keyboard.
          resizeToAvoidBottomInset: true,
          body: SafeArea(
            bottom: false,
            child: Column(
              children: [
                _TopBar(onCancel: () {
                  _dismissKeyboard();
                  Navigator.of(context).maybePop();
                }),
                Expanded(
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(AppSpacing.screenH, 18, AppSpacing.screenH, 18),
                    children: [
                      _situationField(l),
                      const SizedBox(height: 8),
                      if (!_listening)
                        Text(l.generateSituationHelper,
                            style: AppText.translation.copyWith(fontSize: 12.5))
                      else
                        Text(l.generateVoiceHelper, style: AppText.translation.copyWith(fontSize: 12.5)),
                      const SizedBox(height: AppSpacing.s22),
                      _sectionLabel(l.generateSizeLabel),
                      const SizedBox(height: 9),
                      _sizeRow(l),
                      const SizedBox(height: AppSpacing.s22),
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.baseline,
                        textBaseline: TextBaseline.alphabetic,
                        children: [
                          Expanded(child: _sectionLabel(l.generateLevelLabel)),
                          Text(l.generateLevelMulti, style: AppText.transcription.copyWith(fontSize: 11.5, color: AppColors.tertiary)),
                        ],
                      ),
                      const SizedBox(height: 9),
                      _levelRow(),
                      const SizedBox(height: AppSpacing.s22),
                      _sectionLabel(l.generateLanguageLabel),
                      const SizedBox(height: 9),
                      _languageRow(l),
                    ],
                  ),
                ),
                _Footer(
                  quota: quota,
                  exhausted: exhausted,
                  submitting: _submitting,
                  onSubmit: _submit,
                  onManual: () => showCollectionEditor(context, ref),
                  // Premium upsell tap (кадр 15c) — only wired when the paywall flag is on; otherwise
                  // the row stays inert exactly as before A3.9.
                  onPremium: ref.watch(featureFlagsProvider).paywallEnabled
                      ? () => showPaywall(context, ref, const PaywallArgs(PaywallEntry.quota))
                      : null,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _sectionLabel(String text) =>
      Text(text.toUpperCase(), style: AppText.sectionLabel.copyWith(fontSize: 11, color: AppColors.tertiary));

  Widget _situationField(AppLocalizations l) {
    final border = _listening
        ? Border.all(color: AppColors.ink, width: 1.5)
        : Border.all(color: _topicFocus.hasFocus ? AppColors.ink : AppColors.hairline);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.baseline,
          textBaseline: TextBaseline.alphabetic,
          children: [
            Expanded(child: _sectionLabel(l.generateSituationLabel)),
            if (_listening)
              Text(l.generateVoiceListening(_fmtDuration(_recSeconds)),
                  style: AppText.counterSmall.copyWith(fontSize: 11.5, color: AppColors.secondary)),
          ],
        ),
        const SizedBox(height: 8),
        Container(
          padding: const EdgeInsets.fromLTRB(14, 13, 12, 12),
          constraints: const BoxConstraints(minHeight: 92),
          decoration: BoxDecoration(
            color: AppColors.field,
            borderRadius: BorderRadius.circular(AppRadii.field),
            border: border,
            boxShadow: _listening ? [BoxShadow(color: AppColors.ink.withValues(alpha: 0.06), blurRadius: 0, spreadRadius: 4)] : null,
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              TextField(
                controller: _topicCtrl,
                focusNode: _topicFocus,
                autofocus: !widget.startVoice,
                minLines: 1,
                maxLines: 4,
                cursorColor: AppColors.ink,
                style: const TextStyle(fontFamily: AppFonts.inter, fontSize: 15.5, height: 1.45, color: AppColors.ink),
                decoration: InputDecoration.collapsed(
                  hintText: _placeholderFor(l, _hintIndex),
                  hintStyle: const TextStyle(fontFamily: AppFonts.inter, fontSize: 15.5, height: 1.45, color: AppColors.tertiary),
                ),
              ),
              const SizedBox(height: 10),
              Row(
                children: [
                  if (_listening) const _Waveform() else const Spacer(),
                  const Spacer(),
                  if (_listening)
                    _StopButton(onTap: _stopListening)
                  else
                    InkResponse(
                      radius: 22,
                      onTap: _toggleVoice,
                      child: const SizedBox(
                        width: 30, height: 30,
                        child: Icon(LucideIcons.mic, size: 19, color: AppColors.secondary),
                      ),
                    ),
                ],
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _sizeRow(AppLocalizations l) {
    final labels = {_Size.small: l.generateSizeSmall, _Size.medium: l.generateSizeMedium, _Size.large: l.generateSizeLarge};
    return Row(
      children: [
        for (final s in _Size.values) ...[
          if (s != _Size.small) const SizedBox(width: 8),
          Expanded(
            child: _SizeChip(
              label: labels[s]!,
              hint: l.approxWords(s.count),
              selected: _size == s,
              onTap: () {
                AppHaptics.light();
                setState(() => _size = s);
              },
            ),
          ),
        ],
      ],
    );
  }

  Widget _levelRow() {
    return Row(
      children: [
        for (final lvl in _kCefrLevels) ...[
          if (lvl != _kCefrLevels.first) const SizedBox(width: 7),
          Expanded(
            child: _LevelPill(
              label: lvl,
              selected: _levels.contains(lvl),
              onTap: () {
                AppHaptics.light();
                setState(() {
                  if (_levels.contains(lvl)) {
                    if (_levels.length > 1) _levels.remove(lvl);
                  } else {
                    _levels.add(lvl);
                  }
                });
              },
            ),
          ),
        ],
      ],
    );
  }

  Widget _languageRow(AppLocalizations l) {
    final lang = languageByCode(_targetLang);
    return InkWell(
      onTap: _pickLanguage,
      borderRadius: BorderRadius.circular(AppRadii.field),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(AppRadii.field),
          border: Border.all(color: AppColors.hairline),
        ),
        child: Row(
          children: [
            MiniFlag(languageCode: _targetLang),
            const SizedBox(width: 12),
            Expanded(child: Text(lang.name, style: const TextStyle(fontFamily: AppFonts.inter, fontSize: 15.5, color: AppColors.ink))),
            if (!_targetExplicit) ...[
              Text(l.generateLanguageDefault, style: AppText.transcription.copyWith(fontSize: 12.5, color: AppColors.tertiary)),
              const SizedBox(width: 9),
            ],
            const Icon(LucideIcons.chevronDown, size: 16, color: AppColors.secondary),
          ],
        ),
      ),
    );
  }

  String _placeholderFor(AppLocalizations l, int i) => switch (i % _kHintCount) {
        0 => l.generatePlaceholder0,
        1 => l.generatePlaceholder1,
        2 => l.generatePlaceholder2,
        3 => l.generatePlaceholder3,
        _ => l.generatePlaceholder4,
      };

  static String _fmtDuration(int seconds) {
    final m = seconds ~/ 60;
    final s = (seconds % 60).toString().padLeft(2, '0');
    return '$m:$s';
  }
}

const int _kHintCount = 5;
const List<String> _kCefrLevels = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];

// ── Sub-widgets ──

class _TopBar extends StatelessWidget {
  const _TopBar({required this.onCancel});
  final VoidCallback onCancel;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Container(
      height: 44,
      decoration: const BoxDecoration(
        border: Border(bottom: BorderSide(color: AppColors.hairline)),
      ),
      child: Row(
        children: [
          InkWell(
            onTap: onCancel,
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: AppSpacing.screenH),
              child: Text(l.commonCancel, style: AppText.translation.copyWith(fontSize: 15, color: AppColors.secondary)),
            ),
          ),
          Expanded(
            child: Center(
              child: Text(l.generateScreenTitle,
                  style: const TextStyle(fontFamily: AppFonts.inter, fontSize: 16.5, fontWeight: FontWeight.w700, color: AppColors.ink)),
            ),
          ),
          // Balances the «Отмена» width so the title stays centred.
          Opacity(
            opacity: 0,
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: AppSpacing.screenH),
              child: Text(l.commonCancel, style: AppText.translation.copyWith(fontSize: 15)),
            ),
          ),
        ],
      ),
    );
  }
}

class _SizeChip extends StatelessWidget {
  const _SizeChip({required this.label, required this.hint, required this.selected, required this.onTap});
  final String label, hint;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: selected ? AppColors.ink : Colors.transparent,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(AppRadii.field),
        side: selected ? BorderSide.none : const BorderSide(color: AppColors.hairline),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          // Horizontal padding is not decoration: the hint is a translated string («примерно 15
          // слов», «about 15 words») inside a third of the screen width, and with no inset it ran
          // to the very edge of the chip — in English it wrapped and spilled over the ink fill.
          padding: const EdgeInsets.symmetric(vertical: 9, horizontal: 6),
          child: Column(
            children: [
              Text(label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                      fontFamily: AppFonts.inter,
                      fontSize: 13.5,
                      fontWeight: selected ? FontWeight.w700 : FontWeight.w600,
                      color: selected ? AppColors.paper : AppColors.ink)),
              const SizedBox(height: 2),
              // One line, always — a longer translation shrinks to fit instead of wrapping, so the
              // three chips stay the same height and read as one row.
              FittedBox(
                fit: BoxFit.scaleDown,
                child: Text(hint,
                    maxLines: 1,
                    style: AppText.counterSmall.copyWith(
                        fontSize: 11.5,
                        color: selected ? AppColors.paper.withValues(alpha: 0.7) : AppColors.tertiary)),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _LevelPill extends StatelessWidget {
  const _LevelPill({required this.label, required this.selected, required this.onTap});
  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    // The pill is drawn at its own 27pt; the tap zone around it is 44 (QA-OBS-15). The CEFR choice
    // in the profile is made of full-size tiles — this row is the same choice and must be as easy
    // to hit.
    return MinTapHeight(
      onTap: onTap,
      child: Material(
        color: selected ? AppColors.ink : Colors.transparent,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadii.chip),
          side: selected ? BorderSide.none : const BorderSide(color: AppColors.hairline),
        ),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.symmetric(vertical: 7),
            child: Center(
              child: Text(label,
                  style: TextStyle(
                      fontFamily: AppFonts.inter,
                      fontSize: 13,
                      fontWeight: selected ? FontWeight.w700 : FontWeight.w600,
                      color: selected ? AppColors.paper : AppColors.ink)),
            ),
          ),
        ),
      ),
    );
  }
}

/// The docked footer: quota line + «Сгенерировать» (кадр 6a); the exhausted variant greys the
/// button and shows the Premium row + manual fallback (кадр 6b / 15c).
class _Footer extends StatelessWidget {
  const _Footer({
    required this.quota,
    required this.exhausted,
    required this.submitting,
    required this.onSubmit,
    required this.onManual,
    this.onPremium,
  });

  final GenerationQuota? quota;
  final bool exhausted;
  final bool submitting;
  final VoidCallback onSubmit;
  final VoidCallback onManual;

  /// Tap handler for the «Нужно больше? Premium» upsell row (opens the paywall). Null when the
  /// paywall flag is off — the row then renders inert, as it did before A3.9.
  final VoidCallback? onPremium;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Container(
      padding: EdgeInsets.fromLTRB(
        AppSpacing.screenH, 12, AppSpacing.screenH, MediaQuery.viewPaddingOf(context).bottom + 14),
      decoration: const BoxDecoration(
        color: AppColors.paper,
        border: Border(top: BorderSide(color: AppColors.hairline)),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (exhausted && quota != null)
            Row(
              children: [
                Container(
                  width: 9, height: 9,
                  decoration: const BoxDecoration(color: AppColors.verdictUnknown, shape: BoxShape.circle),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(l.generateQuotaExhausted(_time(quota!.resetsAt)),
                      style: AppText.transcription.copyWith(fontSize: 12.5, color: AppColors.secondary)),
                ),
              ],
            )
          else if (quota != null)
            Text(l.generateQuotaRemaining(quota!.remaining),
                style: AppText.transcription.copyWith(fontSize: 12.5, color: AppColors.secondary)),
          if (quota != null) const SizedBox(height: 10),
          PrimaryButton(
            label: l.generateSubmit,
            enabled: !exhausted && !submitting,
            onPressed: exhausted ? null : onSubmit,
            minHeight: 52,
          ),
          if (exhausted) ...[
            const SizedBox(height: 12),
            // Premium upsell (кадр 15c). With the paywall flag on it's tappable → paywall; with it
            // off [onPremium] is null and the row renders exactly as before A3.9 (inert, no padding).
            Center(
              child: Opacity(
                opacity: 0.9,
                child: () {
                  final row = Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(l.generatePremiumUpsell,
                          style: const TextStyle(fontFamily: AppFonts.inter, fontSize: 13, fontWeight: FontWeight.w600, color: AppColors.ink)),
                      const SizedBox(width: 5),
                      const Icon(LucideIcons.chevronRight, size: 15, color: AppColors.ink),
                    ],
                  );
                  if (onPremium == null) return row;
                  return InkWell(
                    onTap: () {
                      AppHaptics.light();
                      onPremium!();
                    },
                    borderRadius: BorderRadius.circular(AppRadii.small),
                    child: Padding(padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4), child: row),
                  );
                }(),
              ),
            ),
            const SizedBox(height: 10),
            Center(
              child: InkWell(
                onTap: onManual,
                borderRadius: BorderRadius.circular(AppRadii.small),
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  child: Text(l.generateManual,
                      style: AppText.translation.copyWith(fontSize: 13, fontWeight: FontWeight.w600, color: AppColors.secondary)),
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }

  /// resets_at is an absolute instant; render it in device-local time (HH:MM).
  static String _time(DateTime t) {
    final h = t.hour.toString().padLeft(2, '0');
    final m = t.minute.toString().padLeft(2, '0');
    return '$h:$m';
  }
}

/// The recording waveform inside the field (кадр 6c) — animated ink bars.
class _Waveform extends StatefulWidget {
  const _Waveform();
  @override
  State<_Waveform> createState() => _WaveformState();
}

class _WaveformState extends State<_Waveform> with SingleTickerProviderStateMixin {
  late final AnimationController _c =
      AnimationController(vsync: this, duration: const Duration(milliseconds: 900))..repeat(reverse: true);
  static const _bars = [0.35, 0.7, 1.0, 0.6, 0.85, 0.5, 0.3];

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _c,
      builder: (context, _) {
        return Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            for (var i = 0; i < _bars.length; i++) ...[
              if (i > 0) const SizedBox(width: 3),
              Container(
                width: 2.5,
                height: 6 + 16 * _bars[i] * (0.4 + 0.6 * ((i.isEven ? _c.value : 1 - _c.value))),
                decoration: BoxDecoration(
                  color: AppColors.ink.withValues(alpha: 0.3 + 0.6 * _bars[i]),
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
            ],
          ],
        );
      },
    );
  }
}

class _StopButton extends StatelessWidget {
  const _StopButton({required this.onTap});
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Material(
      color: Colors.transparent,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(AppRadii.chip),
        side: const BorderSide(color: AppColors.ink),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              const SizedBox(width: 9, height: 9, child: ColoredBox(color: AppColors.ink)),
              const SizedBox(width: 7),
              Text(l.generateVoiceStop,
                  style: const TextStyle(fontFamily: AppFonts.inter, fontSize: 13, fontWeight: FontWeight.w700, color: AppColors.ink)),
            ],
          ),
        ),
      ),
    );
  }
}

/// Bottom-sheet language picker (MiniFlag + endonym), кадр 6b's «Язык изучения» dropdown.
class _LanguagePicker extends StatelessWidget {
  const _LanguagePicker({required this.current, required this.exclude});
  final String current;
  final String exclude;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final langs = kLanguages.where((lang) => lang.code != exclude).toList();
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(l.generateLanguageLabel, style: AppText.sheetButton.copyWith(fontSize: 17)),
        const SizedBox(height: 8),
        Flexible(
          child: ListView.builder(
            shrinkWrap: true,
            itemCount: langs.length,
            itemBuilder: (context, i) {
              final lang = langs[i];
              final selected = lang.code == current;
              return InkWell(
                onTap: () => Navigator.of(context).pop(lang.code),
                borderRadius: BorderRadius.circular(AppRadii.field),
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 12),
                  child: Row(
                    children: [
                      MiniFlag(languageCode: lang.code),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Text(lang.name,
                            style: const TextStyle(fontFamily: AppFonts.inter, fontSize: 15.5, color: AppColors.ink)),
                      ),
                      if (selected) const Icon(LucideIcons.check, size: 18, color: AppColors.ink),
                    ],
                  ),
                ),
              );
            },
          ),
        ),
      ],
    );
  }
}
