/// THE WORDS THAT KEEP THE NEXT ONE — the lists of the typography rule (`lib/data/typography.dart`, наряд CLIENT-22-1
/// §2), by native language, in one place beside the client's other language settings (`language_endonyms.dart`): facts
/// about a language written in its own letters, the one kind of text the Cyrillic guard lets live outside the `.arb`
/// (`test/l10n/no_cyrillic_outside_l10n_test.dart`).
library;

/// By native language — the languages that bind every word of one or two letters. A language not here binds its
/// one-letter words only.
///
/// ru is the work order's list word for word; uk and be are lists of the same form (the same prepositions,
/// conjunctions and particles, and their prepositions of three letters). The one- and two-letter members are bound by
/// their length anyway — the rule binds EVERY such word («на те дни»: «те» is no preposition and still keeps «дни»),
/// the list names them so it reads as the canon; what it adds is the three-letter prepositions and the compound one.
const Map<String, List<String>> kBindingWords = {
  'ru': [
    'в', 'с', 'к', 'у', 'о', 'и', 'а', 'но', 'на', 'по', 'за', 'из', 'от', 'до', 'во', 'со', 'ко', 'об', 'не', 'ни', //
    'же', 'ли', 'бы', 'под', 'для', 'при', 'над', 'без', 'про', 'из-за',
  ],
  'uk': [
    'в', 'у', 'з', 'о', 'і', 'й', 'а', 'та', 'на', 'по', 'за', 'із', 'зі', 'до', 'об', 'не', 'ні', 'же', 'чи', 'би', //
    'від', 'під', 'для', 'при', 'над', 'без', 'про', 'між', 'з-за', 'із-за',
  ],
  'be': [
    'у', 'ў', 'з', 'і', 'й', 'а', 'о', 'на', 'па', 'за', 'са', 'да', 'ад', 'аб', 'не', 'ні', 'жа', 'ці', 'бы', 'ды', //
    'пад', 'для', 'пры', 'над', 'без', 'пра', 'між', 'з-за',
  ],
};
