import 'dart:async';

import 'package:flutter/material.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import '../../../data/api_client.dart';
import '../../../data/line_audio.dart';
import '../../../data/plan/day_contract.dart';
import '../../../data/pronouncer.dart';

/// ГОЛОС ДНЯ — один на окно и один на сессию: всё, что звучит в дне, — файлом сервера (DAY-UI-3: реплики
/// обоих говорящих, фразы и слова; окно докачивает их все при открытии, сессия дозапрашивает реплики
/// собеседника по `audio_id`), а системный синтез — замена, пока файла нет. Ключ файла — текст строки
/// (`LineAudioCache`), поэтому слово карточки звучит тем же файлом, что и слово окна. Один экземпляр
/// держит аудиосессию всю посадку.
///
/// «Звук вердикта ждёт конца реплики»: карточка ждёт [speakLine] и только потом зовёт
/// `AppFeedback` — это и есть правило 4к-3 «никогда поверх озвучки».
class DayVoice {
  DayVoice({required LineAudioCache lines, required this.targetLang, Pronouncer? pronouncer})
    : _lines = lines,
      _pronouncer = pronouncer ?? Pronouncer(null, lines);

  final LineAudioCache _lines;
  final Pronouncer _pronouncer;
  final String targetLang;

  /// Реплика собеседника сейчас звучит — микрофон не открывается поверх динамика.
  final ValueNotifier<bool> speaking = ValueNotifier(false);

  Future<void> warmUp() => _pronouncer.warmUp(targetLang: targetLang);

  /// Запомнить реплики дня и докачать файлы. Ничего не ждёт: экран смотрит на [hasFile] той строки,
  /// которая вот-вот прозвучит.
  Future<void> prepare(Iterable<DayCard> cards) async {
    final texts = <String>[];
    final refs = <LineAudioRef>[];
    for (final c in cards) {
      final partner = c.partner;
      if (partner != null) {
        texts.add(partner.textTarget);
        if (c.audioId case final id?) refs.add((text: partner.textTarget, url: ApiClient.planAudioUrl(id)));
      }
      for (final x in c.exchanges) {
        for (final m in x.messages) {
          if (!m.isPartner) continue;
          texts.add(m.textTarget);
          if (x.audioId case final id?) refs.add((text: m.textTarget, url: ApiClient.planAudioUrl(id)));
        }
      }
    }
    _lines.note(texts);
    if (refs.isEmpty) return;
    unawaited(_lines.preload(refs).catchError((Object e) => debugPrint('[day-voice] preload: $e')));
  }

  /// Строки окна дня с адресами серверного голоса — слова, реплики «в разговоре», фразы и обе реплики
  /// каждого обмена (DAY-UI-3): запомнить и докачать. «Прослушать» без файла читает телефон.
  Future<void> preload(Iterable<LineAudioRef> lines) async {
    _lines.note(lines.map((l) => l.text));
    await _lines.preload(lines).catchError((Object e) => debugPrint('[day-voice] preload: $e'));
  }

  /// Есть ли у реплики файл сервера (иначе читает телефон — тихая строка на карточке).
  bool hasFile(String text) => _lines.fileFor(text) != null;

  /// Сказать реплику собеседника и ДОЖДАТЬСЯ конца.
  Future<void> speakLine(String text) async {
    speaking.value = true;
    try {
      await _pronouncer.speakText(text, targetLang: targetLang, awaitDone: true);
    } finally {
      speaking.value = false;
    }
  }

  /// Слово, фраза или строка окна — файлом, если он на диске, иначе системным голосом; не ждём.
  Future<void> speak(String text) => _pronouncer.speakText(text, targetLang: targetLang);

  Future<void> stop() => _pronouncer.stop();

  Future<void> release() async {
    await _pronouncer.release();
    speaking.dispose();
  }
}

/// КАРТОЧКА СОБЕСЕДНИКА (токен-лист 2б «Реплика собеседника», кадры 23-7, 23-8): слоёная бумага
/// radius 24, латунный лейбл «ВРАЧ ГОВОРИТ» / «ВРАЧ ОТВЕЧАЕТ», воспроизведение 28 справа, волна
/// 24×2 по длительности; текст Literata 19 и перевод раскрываются, когда [revealed].
class PartnerLineCard extends StatefulWidget {
  const PartnerLineCard({
    super.key,
    required this.label,
    required this.text,
    required this.translation,
    required this.voice,
    this.explanation,
    this.revealed = false,
    this.autoplay = true,
    this.onPlayed,
  });

  final String label;
  final String text;
  final String translation;
  final String? explanation;
  final DayVoice voice;
  final bool revealed;

  /// Реплика звучит сама при появлении карточки — один раз.
  final bool autoplay;

  /// Реплика доиграла (каждый раз).
  final VoidCallback? onPlayed;

  @override
  State<PartnerLineCard> createState() => _PartnerLineCardState();
}

class _PartnerLineCardState extends State<PartnerLineCard> with SingleTickerProviderStateMixin {
  late final AnimationController _wave = AnimationController(vsync: this, duration: const Duration(seconds: 3));
  bool _playing = false;

  @override
  void initState() {
    super.initState();
    if (widget.autoplay) {
      // После слайда карточки, а не на первом кадре: канал произнесения стоил бы кадра перехода.
      Timer(AppMotion.nextTaskEnter + const Duration(milliseconds: 60), () {
        if (mounted) unawaited(_play());
      });
    }
  }

  @override
  void dispose() {
    _wave.dispose();
    super.dispose();
  }

  Future<void> _play() async {
    if (_playing) return;
    setState(() => _playing = true);
    final words = widget.text.trim().split(RegExp(r'\s+')).length;
    // Волна идёт по ОЖИДАЕМОЙ длине реплики и доливается, когда файл честно доиграл.
    _wave.duration = Duration(milliseconds: (900 + words * 380).clamp(1200, 12000));
    if (!MediaQuery.of(context).disableAnimations) _wave.forward(from: 0);
    try {
      await widget.voice.speakLine(widget.text);
    } finally {
      if (mounted) {
        _wave.value = 1;
        setState(() => _playing = false);
        widget.onPlayed?.call();
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final noFile = !widget.voice.hasFile(widget.text);

    return PaperCard(
      padding: const EdgeInsets.fromLTRB(18, 16, 18, 14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(child: Text(widget.label.toUpperCase(), style: AppTextDay.speakerLabel)),
              PlayCircle(size: 28, onTap: () => unawaited(_play()), label: widget.label),
            ],
          ),
          if (widget.revealed) ...[
            const SizedBox(height: 10),
            Text(widget.text, style: AppTextDay.partnerLine),
            if (widget.translation.isNotEmpty) ...[
              const SizedBox(height: 5),
              Text(widget.translation, style: AppTextDay.partnerTranslation),
            ],
            if (widget.explanation case final e? when e.isNotEmpty) ...[
              const SizedBox(height: 8),
              Text(e, style: AppTextDay.explanation),
            ],
            const SizedBox(height: 12),
          ] else
            const SizedBox(height: 10),
          Center(
            child: AnimatedBuilder(
              animation: _wave,
              builder: (_, _) => LineWave(progress: _playing || _wave.value > 0 ? _wave.value : 0),
            ),
          ),
          if (noFile) ...[
            const SizedBox(height: 8),
            Text(l.dayNoVoice, style: AppTextDay.quiet.copyWith(fontSize: 12.5)),
          ],
        ],
      ),
    );
  }
}
