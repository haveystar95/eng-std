import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/languages.dart';

/// «На каком языке это произносится» has exactly one answer in the app: the language of the pair the
/// card belongs to, run through [ttsLocaleFor].
///
/// The table is pinned because it is the whole fix: a locale invented at a call site is how an
/// Italian collection came to be read out by an English voice on the owner's phone (MIX-1b). Every
/// language the app teaches is here, plus the two it supports from — a pair is `learned ← support`,
/// and the support side is spoken too wherever a native string is read aloud.
void main() {
  group('every language of the canon has its own voice', () {
    const expected = {
      'en': 'en-US',
      'es': 'es-ES',
      'it': 'it-IT',
      'pl': 'pl-PL',
      'ro': 'ro-RO',
      'de': 'de-DE',
      'fr': 'fr-FR',
      'pt': 'pt-PT',
      'tr': 'tr-TR',
      // The support side of every pair we teach.
      'ru': 'ru-RU',
      'uk': 'uk-UA',
    };

    expected.forEach((code, locale) {
      test('$code → $locale', () => expect(ttsLocaleFor(code), locale));
    });

    test('no two languages share a voice — a mapping collision would be silent', () {
      expect(expected.values.toSet().length, expected.length);
    });
  });

  group('the reference languages are covered too', () {
    // zh/ja carry no trainers (a PHRASEBOOK collection: term, translation, audio). The audio is the
    // whole point of such a collection, so their locales have to be real ones.
    test('zh → zh-CN', () => expect(ttsLocaleFor('zh'), 'zh-CN'));
    test('ja → ja-JP', () => expect(ttsLocaleFor('ja'), 'ja-JP'));
  });

  group('an unknown language degrades, it does not throw', () {
    // The caller is a card that has to be pronounceable. An unmapped language gets the default
    // voice rather than an exception on the audio path — the same degradation iOS itself gives us
    // when the locale is right but its voice pack is not installed.
    test('an unmapped code falls back instead of failing', () {
      expect(ttsLocaleFor('xx'), 'en-US');
      expect(ttsLocaleFor(''), 'en-US');
    });
  });

  group('listening for a language and speaking it are the same question', () {
    // `speech_to_text` wants underscores. Deriving the STT locale from the TTS one is what keeps the
    // app from ever speaking a word in one locale and listening for it in another.
    test('the STT locale is the TTS one, in the shape speech_to_text wants', () {
      expect(sttLocaleFor('it'), 'it_IT');
      expect(sttLocaleFor('pl'), 'pl_PL');
      expect(sttLocaleFor('en'), 'en_US');
    });

    test('every canon language answers both', () {
      for (final code in ['en', 'es', 'it', 'pl', 'ro', 'de', 'fr', 'pt', 'tr', 'ru', 'uk']) {
        expect(sttLocaleFor(code), ttsLocaleFor(code).replaceAll('-', '_'), reason: code);
      }
    });
  });
}
