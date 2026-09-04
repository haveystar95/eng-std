/// ГОЛОСА РЕПЛИК — дев-экран прослушки (наряд TTS-1, Ч.2.4).
///
/// Владелец выбирает голос УШАМИ и на телефоне. Файл на диске ноутбука через встроенный плеер и та
/// же реплика в динамике iPhone — это два разных впечатления, а решение принимается про второе:
/// именно там реплика прозвучит в уроке. Поэтому образцы едут в сборке, а не остаются в
/// `docs/research/tts-1/samples/`.
///
/// Пять реплик у всех кандидатов ОДНИ И ТЕ ЖЕ и в одном темпе — иначе сравнивается не голос, а
/// длина фразы. Четыре из полки «Тебе скажут» живого qa-плана (короткое утверждение, два вопроса
/// разной длины и самый длинный вопрос дня 2) плюс один спасатель.
///
/// Последний блок — СИСТЕМНЫЙ голос, и он единственный играется вживую, а не файлом: сравнивать
/// надо с тем, что стоит в Настройках ЭТОГО телефона, включая скачанный enhanced-голос. Записанный
/// файл системного голоса отвечал бы на вопрос про чужое устройство.
library;

import 'dart:async';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import '../../data/pronouncer.dart';

/// Реплики образца — те же строки, что озвучены в `docs/research/tts-1/samples/`.
const _lines = <({String key, String text})>[
  (key: 'l1', text: 'Thanks for joining today.'),
  (key: 'l2', text: 'Could you briefly introduce yourself?'),
  (key: 'l3', text: 'Can you tell me about your previous experience?'),
  (key: 'l4', text: 'What were your main responsibilities in your last role?'),
  (key: 'r1', text: 'Could you speak more slowly, please?'),
];

/// Кандидат: как назвать, каким префиксом лежат его файлы, и во что обходится план.
///
/// Цена — за ВЕСЬ qa-план (реплики полки hear пяти сцен плюс спасательный набор, 1053 знака),
/// посчитанная по замеренному темпу каждого голоса. Она стоит рядом с кнопкой намеренно: разница
/// между кандидатами — центы, и это тоже часть ответа.
const _voices = <({String label, String prefix, String price, bool favourite})>[
  (label: 'OpenAI · gpt-4o-mini-tts · coral (F)', prefix: '4omini-coral', price: r'$0.023', favourite: true),
  (label: 'OpenAI · gpt-4o-mini-tts · ash (M)', prefix: '4omini-ash', price: r'$0.026', favourite: false),
  (label: 'OpenAI · tts-1 · nova (F)', prefix: 'tts1-nova', price: r'$0.016', favourite: false),
  (label: 'OpenAI · tts-1 · alloy (M)', prefix: 'tts1-alloy', price: r'$0.016', favourite: false),
  (label: 'Google · gemini-2.5-flash-tts · Aoede (F)', prefix: 'gemini25-Aoede', price: r'$0.019', favourite: false),
  (label: 'Google · gemini-2.5-flash-tts · Puck (M)', prefix: 'gemini25-Puck', price: r'$0.019', favourite: false),
];

/// Системный ряд — не файл и не кандидат: это то, чем приложение читает сейчас, на голосе ЭТОГО
/// телефона. Отдельно от [_voices], потому что он играется вживую (см. `_playSample`).
const _systemPrefix = 'system';

class VoiceBakeoffScreen extends StatefulWidget {
  const VoiceBakeoffScreen({super.key});

  @override
  State<VoiceBakeoffScreen> createState() => _VoiceBakeoffScreenState();
}

class _VoiceBakeoffScreenState extends State<VoiceBakeoffScreen> {
  /// БЕЗ кэша реплик: этот экран играет образцы, а не озвучку плана, и системный ряд обязан быть
  /// именно системным. Темп — тот же, которым читаются реплики в уроке.
  final _pronouncer = Pronouncer();
  static const _channel = MethodChannel('com.denis.engstd/line_audio');

  String? _playing;
  Timer? _sequence;

  @override
  void initState() {
    super.initState();
    unawaited(_pronouncer.warmUp(targetLang: 'en'));
  }

  @override
  void dispose() {
    _sequence?.cancel();
    unawaited(_pronouncer.release());
    unawaited(_channel.invokeMethod<void>('stop').catchError((_) {}));
    super.dispose();
  }

  /// Ассет → файл на диске: нативный плеер принимает путь, а ассет живёт в бандле. Копируется один
  /// раз за запуск и переиспользуется.
  Future<String?> _fileOf(String name) async {
    try {
      final dir = await getTemporaryDirectory();
      final file = File(p.join(dir.path, 'tts_samples', name));
      if (file.existsSync() && file.lengthSync() > 0) return file.path;

      final bytes = await rootBundle.load('assets/tts_samples/$name');
      file.parent.createSync(recursive: true);
      file.writeAsBytesSync(bytes.buffer.asUint8List(), flush: true);

      return file.path;
    } catch (_) {
      return null;
    }
  }

  Future<void> _stop() async {
    _sequence?.cancel();
    _sequence = null;
    await _pronouncer.stop();
    try {
      await _channel.invokeMethod<void>('stop');
    } on PlatformException {
      // nothing was playing
    } on MissingPluginException {
      // not iOS
    }
    if (mounted) setState(() => _playing = null);
  }

  Future<void> _playSample(String prefix, String lineKey) async {
    await _stop();
    if (prefix == _systemPrefix) {
      // Системный ряд — вживую и на голосе ЭТОГО телефона. Файл ответил бы про чужой.
      setState(() => _playing = '$prefix#$lineKey');
      final text = _lines.firstWhere((l) => l.key == lineKey).text;
      await _pronouncer.speakText(text, targetLang: 'en');
      if (mounted) setState(() => _playing = null);

      return;
    }

    final path = await _fileOf('$prefix-$lineKey.mp3');
    if (path == null || !mounted) return;
    setState(() => _playing = '$prefix#$lineKey');
    try {
      await _channel.invokeMethod<void>('play', {'path': path});
    } on PlatformException {
      if (mounted) setState(() => _playing = null);
    } on MissingPluginException {
      if (mounted) setState(() => _playing = null);
    }
  }

  /// Все пять подряд, по секунде с небольшим на реплику — ровно та подача, в которой голос слышно
  /// как ГОЛОС, а не как отдельную фразу.
  Future<void> _playAll(String prefix) async {
    await _stop();
    var i = 0;
    Future<void> next() async {
      if (!mounted || i >= _lines.length) {
        if (mounted) setState(() => _playing = null);

        return;
      }
      await _playSample(prefix, _lines[i].key);
      i++;
      _sequence = Timer(const Duration(milliseconds: 4200), () => unawaited(next()));
    }

    await next();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);

    return Scaffold(
      backgroundColor: AppColors.paper,
      appBar: AppBar(title: Text(l.devVoicesTitle)),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(
          AppSpacing.screenH,
          AppSpacing.s16,
          AppSpacing.screenH,
          AppSpacing.s26,
        ),
        children: [
          Text(
            l.devVoicesLead,
            style: AppText.translation.copyWith(fontSize: 13.5, height: 1.55, color: AppColors.secondary),
          ),
          const SizedBox(height: AppSpacing.s16),
          for (final voice in [
            ..._voices,
            (label: l.devVoicesSystem, prefix: _systemPrefix, price: '', favourite: false),
          ]) ...[
            _VoiceBlock(
              voice: voice,
              playing: _playing,
              price: voice.price.isEmpty ? null : l.devVoicesPrice(voice.price),
              favouriteLabel: l.devVoicesFavourite,
              playAllLabel: l.devVoicesPlayAll,
              stopLabel: l.devVoicesStop,
              onPlay: (key) => unawaited(_playSample(voice.prefix, key)),
              onPlayAll: () => unawaited(_playAll(voice.prefix)),
              onStop: () => unawaited(_stop()),
              note: voice.prefix == _systemPrefix ? l.devVoicesSystemNote : null,
            ),
            const SizedBox(height: AppSpacing.s12),
          ],
        ],
      ),
    );
  }
}

class _VoiceBlock extends StatelessWidget {
  const _VoiceBlock({
    required this.voice,
    required this.playing,
    required this.price,
    required this.favouriteLabel,
    required this.playAllLabel,
    required this.stopLabel,
    required this.onPlay,
    required this.onPlayAll,
    required this.onStop,
    this.note,
  });

  final ({String label, String prefix, String price, bool favourite}) voice;
  final String? playing, price, note;
  final String favouriteLabel, playAllLabel, stopLabel;
  final void Function(String lineKey) onPlay;
  final VoidCallback onPlayAll, onStop;

  @override
  Widget build(BuildContext context) {
    final active = playing?.startsWith('${voice.prefix}#') ?? false;

    return PaperCard(
      radius: 16,
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  voice.label,
                  style: AppText.termInList.copyWith(fontSize: 15, height: 1.3),
                ),
              ),
              if (price != null) ...[
                const SizedBox(width: AppSpacing.s8),
                Text(price!, style: AppText.blockLabel.copyWith(color: AppColors.brassInk)),
              ],
            ],
          ),
          if (voice.favourite) ...[
            const SizedBox(height: 4),
            Text(
              favouriteLabel,
              style: AppText.blockLabel.copyWith(color: AppColors.brassInk, letterSpacing: .4),
            ),
          ],
          if (note != null) ...[
            const SizedBox(height: 6),
            Text(
              note!,
              style: AppText.translation.copyWith(fontSize: 12.5, height: 1.45, color: AppColors.tertiary),
            ),
          ],
          const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (var i = 0; i < _lines.length; i++)
                _LineChip(
                  label: '${i + 1}',
                  hint: _lines[i].text,
                  active: playing == '${voice.prefix}#${_lines[i].key}',
                  onTap: () => onPlay(_lines[i].key),
                ),
              _LineChip(
                label: active ? stopLabel : playAllLabel,
                hint: null,
                active: false,
                onTap: active ? onStop : onPlayAll,
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _LineChip extends StatelessWidget {
  const _LineChip({
    required this.label,
    required this.hint,
    required this.active,
    required this.onTap,
  });

  final String label;
  final String? hint;
  final bool active;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final chip = InkWell(
      borderRadius: BorderRadius.circular(999),
      onTap: () {
        AppHaptics.light();
        onTap();
      },
      child: Container(
        constraints: const BoxConstraints(minWidth: 40, minHeight: 34),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
        decoration: BoxDecoration(
          color: active ? AppColors.brassInk.withValues(alpha: .12) : Colors.transparent,
          border: Border.all(color: AppColors.brassInk.withValues(alpha: active ? .6 : .3)),
          borderRadius: BorderRadius.circular(999),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(active ? LucideIcons.volume2 : LucideIcons.play, size: 12, color: AppColors.brassInk),
            const SizedBox(width: 6),
            Text(
              label,
              style: AppText.blockLabel.copyWith(color: AppColors.brassInk, letterSpacing: .4),
            ),
          ],
        ),
      ),
    );

    return hint == null ? chip : Tooltip(message: hint!, child: chip);
  }
}
