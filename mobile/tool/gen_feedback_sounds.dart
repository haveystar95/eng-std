// Generates the two verdict sounds in `assets/sounds/` (QA-22).
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
// WHY GENERATED AT ALL, rather than two files someone downloaded: these are the only two audio
// assets in the app, they are four lines of arithmetic each, and having the recipe in the repo
// means the pitch, the length and the fade are all reviewable and tweakable in the same place —
// «make the wrong tone a touch softer» is a number here, not a trip to a sample library and a
// licence question.
//
// WHY WAV: `AudioServicesCreateSystemSoundID` (the iOS API these are played through — see
// `AppDelegate.swift`) accepts CAF, AIF and WAV. WAV is the one that is trivial to write by hand,
// and at this length the size difference is nothing.
//
// FORMAT: 44.1 kHz, 16-bit signed PCM, mono. System sounds are capped at 30 seconds by iOS; these
// are under a third of a second.

import 'dart:io';
import 'dart:math' as math;
import 'dart:typed_data';

const int _sampleRate = 44100;

/// Headroom. Peak-normalising to 1.0 is what makes a generated tone sound harsh on a phone
/// speaker, and it leaves nothing for the 16-bit rounding — so everything is scaled to sit here.
const double _peak = 0.72;

void main() {
  final dir = Directory('assets/sounds');
  dir.createSync(recursive: true);

  File('${dir.path}/verdict_correct.wav').writeAsBytesSync(_wav(_correct()));
  File('${dir.path}/verdict_wrong.wav').writeAsBytesSync(_wav(_wrong()));

  stdout.writeln('wrote ${dir.path}/verdict_correct.wav');
  stdout.writeln('wrote ${dir.path}/verdict_wrong.wav');
}

/// «Верно» — a warm rising major duplet: E5 → G#5, the interval that makes it read as major
/// rather than merely as two beeps.
///
/// The two notes OVERLAP by a third of a note, so it lands as one gesture instead of as
/// «beep. beep» — the second note begins while the first is still decaying, which is what a
/// struck instrument does and what makes the pair feel like a chord rather than a sequence.
///
/// Warmth is the second harmonic at a quarter amplitude and nothing above it. A pure sine is
/// thin and a bright harmonic stack is a game-show buzzer; one octave of colour is the whole
/// difference, and it stays under the paper/ink brief's «деликатно».
List<double> _correct() {
  const noteMs = 110;
  const overlapMs = 38;
  final note1 = _tone(freq: 659.25, ms: noteMs, harmonic2: 0.25); // E5
  final note2 = _tone(freq: 830.61, ms: noteMs, harmonic2: 0.25); // G#5 — a major third up

  final offset = _samples(noteMs - overlapMs);
  final out = List<double>.filled(offset + note2.length, 0);
  for (var i = 0; i < note1.length; i++) {
    out[i] += note1[i];
  }
  for (var i = 0; i < note2.length; i++) {
    out[offset + i] += note2[i] * 0.92; // the answer note a hair under the question note
  }

  return _normalize(out);
}

/// «Не то» — one soft low tone, A3, with a slight downward glide.
///
/// Deliberately NOT a buzzer and not a second, lower duplet: a wrong answer in this trainer is
/// never a failure event, it is the ordinary other half of a review, and the sound has to be
/// something a person can hear thirty times in a session without flinching. Low, quiet, one note,
/// gone quickly.
///
/// The glide is a couple of semitones down across the tone — a falling pitch reads as «no» in a
/// way a flat one does not, and it does the job that loudness would otherwise have to do.
List<double> _wrong() {
  const ms = 210;
  final n = _samples(ms);
  final out = List<double>.filled(n, 0);
  var phase = 0.0;
  for (var i = 0; i < n; i++) {
    final t = i / (n - 1);
    final freq = 220.0 * math.pow(2, -2 / 12 * t); // A3, sliding ~2 semitones down
    phase += 2 * math.pi * freq / _sampleRate;
    out[i] = (math.sin(phase) + 0.18 * math.sin(2 * phase)) * _envelope(t);
  }

  // Quieter than the accepted sound: the two must not feel like a matched pair of announcements.
  return _normalize(out, scale: 0.78);
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

/// The fade, over normalised position [t] in 0…1.
///
/// A raised-cosine attack (never an instant one — a waveform that starts at full amplitude IS a
/// click, which is exactly the artefact these sounds exist to avoid) and an exponential decay,
/// forced to exactly zero at both ends so no sample can be left hanging at the edge of the file.
double _envelope(double t) {
  const attack = 0.06;
  if (t <= 0 || t >= 1) return 0;
  final rise = t < attack ? 0.5 * (1 - math.cos(math.pi * t / attack)) : 1.0;
  final decay = math.exp(-3.2 * t);
  // The last tenth is walked down to silence so the exponential's own tail cannot end on a step.
  final tail = t > 0.9 ? (1 - t) / 0.1 : 1.0;
  return rise * decay * tail;
}

/// Scale so the loudest sample sits at [_peak] × [scale]. Clipping is impossible by construction
/// rather than by hope: the peak is measured, not assumed.
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

/// A 16-bit mono PCM WAV. Written by hand — the header is 44 bytes and pulling a package in for it
/// would be the only dependency this script has.
Uint8List _wav(List<double> samples) {
  final data = ByteData(samples.length * 2);
  for (var i = 0; i < samples.length; i++) {
    // Clamp before rounding: a value of exactly 1.0 would round to 32768, which does not fit.
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
  u32(16); // PCM chunk size
  u16(1); // PCM, uncompressed
  u16(1); // mono
  u32(_sampleRate);
  u32(_sampleRate * 2); // byte rate = rate × channels × bytes-per-sample
  u16(2); // block align
  u16(16); // bits per sample
  ascii('data');
  u32(pcm.length);

  return Uint8List.fromList([...header.buffer.asUint8List(), ...pcm]);
}
