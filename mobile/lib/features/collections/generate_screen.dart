import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/design.dart';
import '../../core/glass.dart';
import '../../core/languages.dart';
import '../../data/models.dart';
import '../../data/providers.dart';

/// Size presets — the user picks a feel, the system picks the count (decided: no number slider).
enum _Size {
  small('Маленькая', '≈10 слов', 10),
  medium('Средняя', '≈15 слов', 15),
  large('Большая', '≈22 слова', 22);

  const _Size(this.label, this.hint, this.count);
  final String label;
  final String hint;
  final int count;
}

const _placeholders = [
  'иду в банк',
  'собеседование в IT',
  'в аптеке',
  'заказать еду в кафе',
  'аренда квартиры',
  'у врача',
];

/// The AI-collection create screen. One situation field, a size feel, levels, and the target
/// language. Everything reads the profile for defaults; the button greys out on an exhausted quota.
class GenerateScreen extends ConsumerStatefulWidget {
  const GenerateScreen({super.key});

  @override
  ConsumerState<GenerateScreen> createState() => _GenerateScreenState();
}

class _GenerateScreenState extends ConsumerState<GenerateScreen> {
  final _topicCtrl = TextEditingController();
  late Set<String> _levels;
  late String _targetLang;
  _Size _size = _Size.medium;
  int _hintIndex = 0;
  Timer? _hintTimer;
  bool _submitting = false;

  @override
  void initState() {
    super.initState();
    final profile = ref.read(authControllerProvider).value?.profile;
    _levels = {profile?.cefrLevel ?? 'B1'};
    _targetLang = profile?.targetLanguage ?? 'en';
    // Rotate the placeholder examples while the field is empty.
    _hintTimer = Timer.periodic(const Duration(seconds: 3), (_) {
      if (mounted && _topicCtrl.text.isEmpty) {
        setState(() => _hintIndex = (_hintIndex + 1) % _placeholders.length);
      }
    });
    _topicCtrl.addListener(() => setState(() {}));
  }

  @override
  void dispose() {
    _hintTimer?.cancel();
    _topicCtrl.dispose();
    super.dispose();
  }

  String get _sourceLang => ref.read(authControllerProvider).value?.profile?.nativeLanguage ?? 'ru';

  Future<void> _submit() async {
    final topic = _topicCtrl.text.trim();
    if (topic.isEmpty) {
      AppFeedback.warn();
      return;
    }
    setState(() => _submitting = true);
    final messenger = ScaffoldMessenger.of(context);
    final navigator = Navigator.of(context);
    try {
      await ref.read(generationControllerProvider).start(
            topic: topic,
            levels: _levels.toList(),
            size: _size.count,
            sourceLang: _sourceLang,
            targetLang: _targetLang,
          );
      ref.invalidate(generationQuotaProvider); // the count just went up
      AppFeedback.success();
      navigator.pop();
      messenger.showSnackBar(
        const SnackBar(content: Text('ИИ подбирает слова — коллекция появится в списке')),
      );
    } catch (e) {
      if (!mounted) return;
      setState(() => _submitting = false);
      AppFeedback.warn();
      messenger.showSnackBar(SnackBar(content: Text('Не удалось запустить: $e')));
    }
  }

  @override
  Widget build(BuildContext context) {
    final quota = ref.watch(generationQuotaProvider).value;
    final target = languageByCode(_targetLang);
    final source = languageByCode(_sourceLang);
    final exhausted = quota?.exhausted ?? false;

    return Scaffold(
      extendBodyBehindAppBar: true,
      backgroundColor: Colors.transparent,
      // Body resizes for the keyboard (default), and the footer lives INSIDE the body (not
      // bottomNavigationBar, which sits behind the keyboard) so «Создать» stays reachable and the
      // scroll keeps the input field visible.
      resizeToAvoidBottomInset: true,
      appBar: AppBar(title: const Text('Новая коллекция')),
      body: AmbientBackground(
        child: SafeArea(
          child: Column(
            children: [
              Expanded(
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(AppSpacing.md, AppSpacing.sm, AppSpacing.md, 24),
                  children: [
              _situationField(),
              const SizedBox(height: AppSpacing.md),
              _section('Размер набора'),
              const SizedBox(height: 10),
              Row(
                children: _Size.values.map((s) {
                  final selected = _size == s;
                  return Expanded(
                    child: Padding(
                      padding: EdgeInsets.only(right: s == _Size.large ? 0 : 8),
                      child: _SizeChip(size: s, selected: selected, onTap: () => setState(() => _size = s)),
                    ),
                  );
                }).toList(),
              ),
              const SizedBox(height: AppSpacing.md),
              _section('Уровни (можно несколько)'),
              const SizedBox(height: 10),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: kCefrLevels.map((lvl) {
                  final selected = _levels.contains(lvl);
                  return GlassChip(
                    label: lvl,
                    selected: selected,
                    onTap: () => setState(() {
                      if (selected) {
                        if (_levels.length > 1) _levels.remove(lvl);
                      } else {
                        _levels.add(lvl);
                      }
                    }),
                  );
                }).toList(),
              ),
              const SizedBox(height: AppSpacing.md),
              _section('Язык'),
              const SizedBox(height: 10),
              _languageRow(target, source),
                  ],
                ).animate().fadeIn(duration: 160.ms),
              ),
              _footer(quota, exhausted),
            ],
          ),
        ),
      ),
    );
  }

  Widget _situationField() {
    return GlassCard(
      padding: const EdgeInsets.all(AppSpacing.md),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 34,
                height: 34,
                alignment: Alignment.center,
                decoration: BoxDecoration(gradient: AppGradients.brand, borderRadius: BorderRadius.circular(AppRadii.sm)),
                child: const Icon(Icons.auto_awesome_rounded, color: Colors.white, size: 18),
              ),
              const SizedBox(width: 10),
              const Expanded(
                child: Text('Опиши ситуацию',
                    style: TextStyle(color: AppColors.textPrimary, fontSize: 16, fontWeight: FontWeight.w800)),
              ),
            ],
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _topicCtrl,
            autofocus: true,
            minLines: 1,
            maxLines: 3,
            style: const TextStyle(color: AppColors.textPrimary, fontSize: 17, fontWeight: FontWeight.w600),
            decoration: InputDecoration(
              hintText: 'напр.: «${_placeholders[_hintIndex]}»',
              hintStyle: const TextStyle(color: AppColors.textMuted, fontWeight: FontWeight.w400),
              border: InputBorder.none,
              enabledBorder: InputBorder.none,
              focusedBorder: InputBorder.none,
              contentPadding: EdgeInsets.zero,
            ),
          ),
          const SizedBox(height: 4),
          const Text('ИИ подберёт слова и фразы, которые реально нужны в этой ситуации',
              style: TextStyle(color: AppColors.textMuted, fontSize: 12)),
        ],
      ),
    );
  }

  Widget _languageRow(Language target, Language source) {
    return GlassCard(
      padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md, vertical: 6),
      child: Row(
        children: [
          Expanded(
            child: DropdownButtonHideUnderline(
              child: DropdownButton<String>(
                value: _targetLang,
                isExpanded: true,
                dropdownColor: AppColors.surfaceHi,
                borderRadius: BorderRadius.circular(AppRadii.md),
                icon: const Icon(Icons.expand_more_rounded, color: AppColors.textSecondary),
                style: const TextStyle(color: AppColors.textPrimary, fontSize: 16, fontWeight: FontWeight.w700),
                items: kLanguages
                    .map((l) => DropdownMenuItem(value: l.code, child: Text('${l.flag}  ${l.name}')))
                    .toList(),
                onChanged: (v) => setState(() => _targetLang = v ?? _targetLang),
              ),
            ),
          ),
          const Icon(Icons.arrow_forward_rounded, color: AppColors.textMuted, size: 18),
          const SizedBox(width: 12),
          Text('${source.flag}  ${source.name}',
              style: const TextStyle(color: AppColors.textSecondary, fontSize: 15, fontWeight: FontWeight.w600)),
        ],
      ),
    );
  }

  Widget _section(String title) => Text(title,
      style: const TextStyle(color: AppColors.textSecondary, fontSize: 13, fontWeight: FontWeight.w700));

  Widget _footer(GenerationQuota? quota, bool exhausted) {
    return Container(
      // Inside SafeArea + the resizing body, so no manual keyboard/safe-area padding needed.
      padding: const EdgeInsets.fromLTRB(AppSpacing.md, 12, AppSpacing.md, 12),
      decoration: const BoxDecoration(
        color: AppColors.bg,
        border: Border(top: BorderSide(color: Colors.white10)),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (quota != null) ...[
            Text(
              exhausted
                  ? 'Лимит на сегодня исчерпан · обновится в ${_time(quota.resetsAt)}'
                  : 'Осталось ${quota.remaining} из ${quota.limit} сегодня',
              style: TextStyle(
                  color: exhausted ? AppColors.review : AppColors.textSecondary,
                  fontSize: 12,
                  fontWeight: FontWeight.w600),
            ),
            const SizedBox(height: 8),
          ],
          GlassButton(
            label: exhausted ? 'Лимит исчерпан' : 'Создать',
            icon: Icons.auto_awesome_rounded,
            busy: _submitting,
            disabled: exhausted,
            onTap: _submit,
          ),
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

class _SizeChip extends StatelessWidget {
  const _SizeChip({required this.size, required this.selected, required this.onTap});
  final _Size size;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return SpringTap(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 8),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(AppRadii.md),
          gradient: selected ? AppGradients.brand : null,
          color: selected ? null : Colors.white.withValues(alpha: 0.06),
          border: Border.all(color: selected ? Colors.transparent : Colors.white.withValues(alpha: 0.14)),
          boxShadow: selected ? AppShadows.glow(AppColors.primary) : null,
        ),
        child: Column(
          children: [
            Text(size.label,
                style: TextStyle(
                    color: selected ? Colors.white : AppColors.textPrimary,
                    fontWeight: FontWeight.w800,
                    fontSize: 14)),
            const SizedBox(height: 2),
            Text(size.hint,
                style: TextStyle(
                    color: selected ? Colors.white70 : AppColors.textMuted, fontSize: 11)),
          ],
        ),
      ),
    );
  }
}
