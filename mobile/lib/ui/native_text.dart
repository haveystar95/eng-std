import 'package:flutter/widgets.dart';

import '../data/typography.dart';

/// THE LEARNER'S NATIVE LANGUAGE, for the typography of the server's texts (наряд CLIENT-22-1 §2): the held plan's
/// `native_lang`, the profile's native before there is a plan. The app puts one over every route (`main.dart`,
/// `MaterialApp.builder` — sheets and alerts are routes too); without one — a test that pumps a bare screen — the
/// interface's language stands in.
class NativeLanguageScope extends InheritedWidget {
  const NativeLanguageScope({super.key, required this.language, required super.child});

  final String language;

  static String of(BuildContext context) =>
      context.dependOnInheritedWidgetOfExactType<NativeLanguageScope>()?.language ??
      Localizations.maybeLocaleOf(context)?.languageCode ??
      'ru';

  @override
  bool updateShouldNotify(NativeLanguageScope oldWidget) => oldWidget.language != language;
}

/// THE RULE OF THE LEARNER'S LANGUAGE, taken once in a build and handed to the helpers that compose a line out of the
/// server's texts (`planRouteDayName`, `WindowTexts`, …) — they have no context of their own.
class NativeTypesetter {
  const NativeTypesetter(this.language);

  factory NativeTypesetter.of(BuildContext context) => NativeTypesetter(NativeLanguageScope.of(context));

  final String language;

  /// A text the plan or the lesson writes in the learner's language — see [NativeTypesetting.nativeText].
  String call(String text) => typeset(text, language);

  /// A string the server composes from its packs — see [NativeTypesetting.composedText].
  String composed(String text) => typeset(text, composedLanguage(language));
}

/// THE DOOR A SERVER TEXT IN THE LEARNER'S LANGUAGE TAKES TO THE SCREEN — set by the typography rule of that language
/// (`lib/data/typography.dart`). The text is taken as its field in the contract says it is: a `*_native` field, a
/// translation, a role, a title — never a frame, a line, a word or an option in the language being learned, which keep
/// their plain spaces (the recognizer compares them).
extension NativeTypesetting on BuildContext {
  /// What the plan or the lesson writes in the learner's language: scene and day titles, roles, the event, the lesson's
  /// lines and their translations, questions and hints, the judge's reason.
  String nativeText(String text) => typeset(text, NativeLanguageScope.of(this));

  /// [nativeText] that may be absent.
  String? nativeTextOrNull(String? text) => text == null ? null : nativeText(text);

  /// What the SERVER composes from its own packs — `summary`, `until_phrase`, `highlights`, the slot labels: ru, uk
  /// and en have packs, every other native reads these in English (plan-api, «Строки сервера для родных вне ru/uk/en»).
  String composedText(String text) => typeset(text, composedLanguage(NativeLanguageScope.of(this)));
}
