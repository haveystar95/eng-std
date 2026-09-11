// Generates the four sounds of the day in `assets/sounds/` (QA-22 → токен-лист 4к-3, DAY-UI).
//
// REGENERATE WITH:
//
//     cd mobile && dart run tool/gen_feedback_sounds.dart
//
// Deterministic: no randomness, no time, no network — the same source always writes the same
// bytes, so a regenerated file is either identical or the result of an edit you made here. That is
// why the WAVs are committed alongside this script rather than built at install time: the app must
// not depend on a Dart toolchain being run before it can make a sound, and a reviewer must be able
// to see that the committed bytes match the recipe.
//
// THE FOUR (4к-3): «верно» — короткий мягкий тон вверх, 120 мс · «неверно» — низкий глухой,
// 160 мс, без резкости, не «ошибка» · «этап закрыт» — две ноты вверх, 240 мс · «день закрыт» —
// три ноты вверх, 300 мс. All quiet, soft attack, one key (E major pentatonic), no reverb,
// synthesised without libraries. Nothing longer than 300 ms.
//
// WHY WAV: `AudioServicesCreateSystemSoundID` (the iOS API these are played through — see
// `AppDelegate.swift`) accepts CAF, AIF and WAV. WAV is the one that is trivial to write by hand.
//
// FORMAT: 44.1 kHz, 16-bit signed PCM, mono.

import 'dart:io';
import 'dart:math' as math;
import 'dart:typed_data';

const int _sampleRate = 44100;

/// Headroom. Peak-normalising to 1.0 is what makes a generated tone sound harsh on a phone
/// speaker, and it leaves nothing for the 16-bit rounding — so everything is scaled to sit here.
const double _peak = 0.72;

// E major pentatonic, one key for the four sounds.
const double _e5 = 659.25, _gs5 = 830.61, _b5 = 987.77, _a3 = 220.0;

void main() {
  final dir = Directory('assets/sounds');
  dir.createSync(recursive: true);

  final files = {
    'verdict_correct': _correct(),
    'verdict_wrong': _wrong(),
    'stage_closed': _stageClosed(),
    'day_closed': _dayClosed(),
  };
  for (final entry in files.entries) {
    File('${dir.path}/${entry.key}.wav').writeAsBytesSync(_wav(entry.value));
    stdout.writeln('wrote ${dir.path}/${entry.key}.wav (${(entry.value.length * 1000 / _sampleRate).round()} ms)');
  }
}

/// «Верно» — one soft tone gliding UP a major third across 120 ms (E5 → G#5). Warmth is the
/// second harmonic at a quarter amplitude and nothing above it.
List<double> _correct() => _glide(from: _e5, to: _gs5, ms: 120, harmonic2: 0.25);

/// «Неверно» — one low dull tone, A3, sliding a couple of semitones down across 160 ms. A falling
/// pitch reads as «no» in a way a flat one does not, and it does the job loudness would otherwise
/// have to do. Quieter than «верно»: the two must not feel like a matched pair of announcements.
List<double> _wrong() {
  final out = _glide(from: _a3, to: _a3 * math.pow(2, -2 / 12), ms: 160, harmonic2: 0.18);
  return _normalize(out, scale: 0.78);
}

/// «Этап закрыт» — two notes up, 240 ms in all (E5 → B5), overlapping by a third of a note so it
/// lands as one gesture.
List<double> _stageClosed() => _sequence([_e5, _b5], noteMs: 140, overlapMs: 40);

/// «День закрыт» — three notes up, 300 ms in all (E5 → G#5 → B5).
List<double> _dayClosed() => _sequence([_e5, _gs5, _b5], noteMs: 120, overlapMs: 30);

List<double> _sequence(List<double> notes, {required int noteMs, required int overlapMs}) {
  final step = _samples(noteMs - overlapMs);
  final total = step * (notes.length - 1) + _samples(noteMs);
  final out = List<double>.filled(total, 0);
  for (var n = 0; n < notes.length; n++) {
    final tone = _tone(freq: notes[n], ms: noteMs, harmonic2: 0.25);
    final gain = n == 0 ? 1.0 : 0.92; // the answering notes a hair under the first
    for (var i = 0; i < tone.length; i++) {
      out[n * step + i] += tone[i] * gain;
    }
  }
  return _normalize(out);
}

List<double> _glide({required double from, required double to, required int ms, double harmonic2 = 0}) {
  final n = _samples(ms);
  final out = List<double>.filled(n, 0);
  var phase = 0.0;
  for (var i = 0; i < n; i++) {
    final t = i / (n - 1);
    final freq = from * math.pow(to / from, t);
    phase += 2 * math.pi * freq / _sampleRate;
    out[i] = (math.sin(phase) + harmonic2 * math.sin(2 * phase)) * _envelope(t);
  }
  return _normalize(out);
}

/// One note: [freq] Hz for [ms], with an optional second harmonic at [harmonic2] amplitude, under
/// the shared fade envelope.
List<double> _tone({required double freq, required int ms, double harmonic2 = 0}) {
  final n = _samples(ms);
  return List<double>.generate(n, (i) {
    final t = i / (n - 1);
    final phase = 2 * math.pi * freq * i / _sampleRate;
    return (math.sin(phase) + harmonic2 * math.sin(2 * phase)) * _envelope(t);
  });
}

/// The fade, over normalised position [t] in 0…1: a raised-cosine attack (never an instant one —
/// a waveform that starts at full amplitude IS a click) and an exponential decay, forced to exactly
/// zero at both ends so no sample can be left hanging at the edge of the file.
double _envelope(double t) {
  const attack = 0.08;
  if (t <= 0 || t >= 1) return 0;
  final rise = t < attack ? 0.5 * (1 - math.cos(math.pi * t / attack)) : 1.0;
  final decay = math.exp(-3.2 * t);
  final tail = t > 0.9 ? (1 - t) / 0.1 : 1.0;
  return rise * decay * tail;
}

/// Scale so the loudest sample sits at [_peak] × [scale]. Clipping is impossible by construction.
List<double> _normalize(List<double> samples, {double scale = 1.0}) {
  var loudest = 0.0;
  for (final s in samples) {
    if (s.abs() > loudest) loudest = s.abs();
  }
  if (loudest == 0) return samples;
  final gain = _peak * scale / loudest;

  return samples.map((s) => s * gain).toList();
}

int _samples(int ms) => (_sampleRate * ms / 1000).round();

/// A 16-bit mono PCM WAV. Written by hand — the header is 44 bytes.
Uint8List _wav(List<double> samples) {
  final data = ByteData(samples.length * 2);
  for (var i = 0; i < samples.length; i++) {
    final v = (samples[i].clamp(-1.0, 1.0) * 32767).round();
    data.setInt16(i * 2, v, Endian.little);
  }
  final pcm = data.buffer.asUint8List();

  final header = ByteData(44);
  var at = 0;
  void ascii(String s) {
    for (final c in s.codeUnits) {
      header.setUint8(at++, c);
    }
  }

  void u32(int v) {
    header.setUint32(at, v, Endian.little);
    at += 4;
  }

  void u16(int v) {
    header.setUint16(at, v, Endian.little);
    at += 2;
  }

  ascii('RIFF');
  u32(36 + pcm.length);
  ascii('WAVE');
  ascii('fmt ');
  u32(16);
  u16(1);
  u16(1);
  u32(_sampleRate);
  u32(_sampleRate * 2);
  u16(2);
  u16(16);
  ascii('data');
  u32(pcm.length);

  return Uint8List.fromList([...header.buffer.asUint8List(), ...pcm]);
}
