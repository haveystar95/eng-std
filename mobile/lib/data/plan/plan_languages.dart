/// THE TWO SIDES OF A PLAN'S PAIR — `GET /languages` (наряд LANG-1 §7): what a plan may TEACH
/// (`targets`) and what it may be READ in (`natives`).
///
/// «Кэш в памяти, запас в бандле»: the lists are asked once per app run ([planLanguagesProvider] is
/// kept alive), and when the network does not answer the screens draw the copy bundled with the
/// build ([PlanLanguages.bundled] — the same codes as [kPlanTargetCodes] and [kNativeLanguageCodes]).
/// A picker is never empty and never waits on the network to show its first row.
///
/// The server sends codes with their endonym and flag, and the CLIENT's catalogue still names and
/// draws them (`lib/l10n/language_endonyms.dart`, HYG-1): the server's spelling only stands in for a
/// code this build does not know ([resolveLanguage]).
///
/// Who reads what:
///   * 22-2 (the plan entry) offers [PlanLanguages.targetsFor] the learner's native — a pair of a
///     language with itself is not a pair, and the server refuses it (`language_pair_invalid`);
///   * the native pickers (onboarding, the profile row «Родной язык») offer [PlanLanguages.nativesFor]
///     the chosen target.
library;

import 'package:flutter/foundation.dart' show debugPrint, immutable;
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../api_client.dart';
import '../languages.dart';
import '../providers.dart';

/// The plan targets bundled with the build, in the server's order (`LanguageRoles::planTargets()`).
const List<String> kPlanTargetCodes = ['en', 'pl', 'ro', 'es', 'it', 'de', 'fr'];

@immutable
class PlanLanguages {
  const PlanLanguages({required this.targets, required this.natives, this.fromBundle = false});

  /// A DEFENSIVE read of `data` of `GET /languages`: an entry may be an object `{code, endonym, flag}`
  /// or a bare code string; anything else — a number, a map without a code, a code that is not two
  /// or three letters — is dropped, and so is a repeated code. A half that is missing or not a list
  /// comes back empty; [orBundled] fills it.
  factory PlanLanguages.fromJson(Object? data) {
    if (data is! Map) return const PlanLanguages(targets: [], natives: []);

    return PlanLanguages(targets: _list(data['targets']), natives: _list(data['natives']));
  }

  /// The lists bundled with the build — what the screens draw when the network does not answer.
  static final PlanLanguages bundled = PlanLanguages(
    targets: [for (final code in kPlanTargetCodes) languageByCode(code)],
    natives: [for (final code in kNativeLanguageCodes) languageByCode(code)],
    fromBundle: true,
  );

  /// What a plan may teach, in the order 22-2 offers it.
  final List<Language> targets;

  /// What a plan may be read in, in the order the native pickers offer it.
  final List<Language> natives;

  /// These are the bundled lists: the server was not reached. A screen that opens may ask again.
  final bool fromBundle;

  List<String> get targetCodes => [for (final l in targets) l.code];

  List<String> get nativeCodes => [for (final l in natives) l.code];

  /// Each half the server left empty is the bundle's; a full answer is returned as it is.
  PlanLanguages orBundled() {
    if (targets.isNotEmpty && natives.isNotEmpty) return this;

    return PlanLanguages(
      targets: targets.isEmpty ? bundled.targets : targets,
      natives: natives.isEmpty ? bundled.natives : natives,
      fromBundle: targets.isEmpty && natives.isEmpty,
    );
  }

  /// THE NATIVE 22-2 SUBTRACTS: the profile's, when it is one of [natives]; otherwise the guess
  /// from the device's own language ([defaultNativeLanguageFor]) — the same guess onboarding offers.
  String nativeFor({String? profileNative, required String deviceLanguage}) {
    final own = (profileNative ?? '').trim().toLowerCase();
    if (own.isNotEmpty && nativeCodes.contains(own)) return own;

    return defaultNativeLanguageFor(deviceLanguage);
  }

  /// THE TARGETS 22-2 OFFERS a learner whose native is [native]: every target but that language.
  ///
  /// Never empty: a server list that leaves nothing (one target, and it is the native) gives way to
  /// the bundled one, so the step always has a card to preselect.
  List<Language> targetsFor(String native) {
    final offered = [for (final l in targets) if (l.code != native) l];
    if (offered.isNotEmpty || identical(this, bundled)) return offered;

    return bundled.targetsFor(native);
  }

  /// THE NATIVES a picker offers against the chosen [target] — every native but that language.
  List<Language> nativesFor({String? target}) => [for (final l in natives) if (l.code != target) l];

  static List<Language> _list(Object? raw) {
    if (raw is! List) return const [];
    final seen = <String>{};

    return [
      for (final item in raw)
        if (_entry(item) case final language? when seen.add(language.code)) language,
    ];
  }

  static final _code = RegExp(r'^[a-z]{2,3}$');

  static Language? _entry(Object? item) {
    final Object? code;
    String? endonym, flag;
    if (item is String) {
      code = item;
    } else if (item is Map) {
      code = item['code'];
      endonym = item['endonym'] is String ? item['endonym'] as String : null;
      flag = item['flag'] is String ? item['flag'] as String : null;
    } else {
      return null;
    }
    if (code is! String) return null;
    final c = code.trim().toLowerCase();
    if (!_code.hasMatch(c)) return null;

    return resolveLanguage(c, endonym: endonym, flag: flag);
  }
}

/// Ask the server for both lists; ANY failure — no network, a 401 before sign-in, a body of the
/// wrong shape — answers with [PlanLanguages.bundled]. Never throws: a picker has something to draw
/// whatever the network did.
Future<PlanLanguages> loadPlanLanguages(ApiClient api) async {
  try {
    return (await api.pairLanguages()).orBundled();
  } catch (e) {
    debugPrint('[languages] the bundled lists stand in: $e');

    return PlanLanguages.bundled;
  }
}

/// THE LISTS FOR THIS APP RUN — asked once and kept (not `autoDispose`), like the server's commit
/// line in the profile: the catalogue changes by deployment, not by session. Never in error — see
/// [loadPlanLanguages]. A screen that finds [PlanLanguages.fromBundle] may `ref.invalidate` it to
/// ask again.
final planLanguagesProvider = FutureProvider<PlanLanguages>(
  (ref) => loadPlanLanguages(ref.read(apiClientProvider)),
);
