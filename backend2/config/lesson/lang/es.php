<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| LANGUAGE PACK · es — what the code-only checks read of Spanish
|--------------------------------------------------------------------------
|
| docs/plan-v2.md §4 (наряд GEN-2b), наряд LANG-1 (key spec: docs/research/lang-1/pack-keys.md). Spanish is written on
| BOTH sides: as a TARGET (what the learner says and hears — the day's checks' target rules, the judge of the
| talk's constructions, the comparison of speech) and as the LEARNER'S OWN language (readings, native frames, the
| listening, the translation guard, the title of the talk). No key is null: a rule that is no rule of Spanish is written
| as the spec's no-op, so its absence is a decision and never a rule switched off.
|
| How the words are written. Every list is read through {@see \App\Modules\Plan\Domain\Check\Language\LanguagePack::normal()}
| (folded, lower case, the plain apostrophe) or through the kernel's canonical form: the normal spelling of the language
| is right for all of them — Spanish folds into itself (fold touches ß, œ, ş, ţ only), and its accents are letters, kept
| on both sides («más» and «mas», «sí» and «si» are two words). The Spanish opening marks ¿ ¡ are the core's business
| (FrameText, SentenceEnds, FrameWords read past them); they are no end of a sentence and are not listed here.
|
| Every list is a counter's reading of a rule, not the rule: the codes built on them are heuristics and the canon names
| them so.
*/
return [
    // A reading (`pronunciation_native`) of a Spanish learner is written in the SPANISH alphabet — a–z, ñ, the acute
    // vowels and ü — with digits, punctuation (¿ ¡ are \p{P}), spaces and the stress mark U+0301. A reading with a letter
    // Spanish does not write («ə» of IPA, «à», «ş») is a warning (`pronunciation.script`), never a failure: the fatal
    // check is `script_letters`, which stays the Latin writing as a whole.
    'script' => '/^[a-zA-ZñÑáéíóúüÁÉÍÓÚÜ\p{N}\p{P}\s\x{0301}]*$/u',

    // One LETTER of the Latin writing, matched alone — the spec's reference string character for character: two packs
    // are neighbours for the translation guard exactly when they write this very string, and a stricter alphabet here
    // would fail an honest reading (`pronunciation.foreign_script` is fatal).
    'script_letters' => '/^[\p{Latin}]$/u',

    // FREQUENT AND DISTINCTIVE (наряд LANG-1 §5, `common_words`). The order said «the 30 most frequent words»; this list
    // is «frequent AND distinctive» on purpose: the guard of the role's translation reads a learner's grey line against
    // EVERY pack in Latin letters (en pl ro it de fr), and a word that is also an ordinary word of the learner's language
    // but sits only in the Spanish list counts as Spanish inside that learner's own line — two such words and an honest
    // translation is refused (the probe that refused «Для записи к врачу приходите до двенадцати» for ru because a uk list
    // held «для» and «до»). So the most frequent Spanish words a neighbour spells alike are left out: «de», «la», «que»,
    // «en», «y», «el» (ro «he»), «los» (de, pl), «las» (pl), «un», «una», «es» (de, fr), «no», «se», «me», «te», «lo», «le»,
    // «su», «mi», «tu», «con», «por» (ro), «para» (pl), «del», «al», «era», «son», «bien» (fr), «este» (ro «is»), «tengo»,
    // «tiene» (it «I hold», «holds»), «hay», «soy» (en «hay», «soy»), «nada» (pl), «vale» (it, ro), «donde» (it, literary).
    // Kept: the question words with their accent, the forms of estar/tener/poder/querer no neighbour writes, and the
    // everyday adverbs. «pero» is Italian only as the rare «pear tree» (the common «però» has its accent). One run of
    // letters each, lower case, accents as written.
    'common_words' => [
        'qué', 'cómo', 'dónde', 'cuándo', 'cuál', 'cuánto', 'muy', 'más', 'también', 'pero', 'porque', 'cuando',
        'usted', 'yo', 'él', 'está', 'están', 'estoy', 'tienes', 'tenemos', 'puedo', 'puede', 'quiero', 'hoy',
        'mañana', 'ahora', 'gracias', 'sí', 'hola', 'eso', 'esto', 'algo', 'aquí', 'después', 'hasta',
    ],

    // The marks a sentence ends with, and what each says. ¿ and ¡ OPEN a sentence and are never an end
    // ({@see \App\Modules\Plan\Domain\Check\Language\SentenceEnds} leaves them out even if listed): not written.
    'sentence_ends' => ['.' => 'statement', '?' => 'question', '!' => 'exclamation', '…' => 'ellipsis'],

    // Words whose dot ends no sentence (наряд CHECK-1): «la Dra. Ruiz», «a las 4 p. m.», «núm. 12», «Vivo en EE. UU.» —
    // read in the text as written, letter case aside, a space inside one being any run of spaces. The RAE writes the
    // hours «a. m.» / «p. m.» and the plural «EE. UU.» with a space; the spellings without one are common and listed too.
    // No abbreviation that is also a word with a dot («No.» is the answer «no»); none of one letter. Without «EE. UU.» a
    // filler «EE. UU.» (a trip, an address) carried a sentence of its own — the fatal `filler.ungrammatical`.
    'abbreviations' => [
        'Sr.', 'Sra.', 'Srta.', 'Sres.', 'Dr.', 'Dra.', 'Ud.', 'Uds.', 'Vd.', 'Vds.', 'Dña.', 'Lic.', 'Ing.', 'Prof.',
        'Profa.', 'Sto.', 'Sta.',
        'a. m.', 'p. m.', 'a.m.', 'p.m.', 'p. ej.', 'etc.', 'EE. UU.', 'EE.UU.', 'núm.', 'Nro.', 'tel.', 'tfno.', 'ext.',
        'aprox.', 'pág.', 'Avda.', 'Av.', 'hab.', 'Dto.', 'Depto.', 'Cía.', 'min.', 'seg.', 'hrs.', 'km.',
    ],

    // Spanish asks by intonation and by the marks ¿ … ?, never by inverting an auxiliary and a subject pronoun
    // («¿Usted tiene cita?», «¿Tiene usted cita?», «¿Tiene cita?» all ask): a question is its mark alone — the no-op.
    'question_word_order' => ['auxiliaries' => [], 'subjects' => []],

    // Words that carry no content of their own: articles and the contracted al/del, prepositions, conjunctions,
    // pronouns and possessives, the question and relative words, the forms of ser, estar, haber, tener and poder, the
    // particles of politeness and yes/no.
    'function_words' => [
        'el', 'la', 'los', 'las', 'lo', 'un', 'una', 'unos', 'unas', 'al', 'del',
        'a', 'ante', 'bajo', 'con', 'contra', 'de', 'desde', 'en', 'entre', 'hacia', 'hasta', 'para', 'por', 'según',
        'sin', 'sobre', 'tras', 'durante',
        'y', 'e', 'o', 'u', 'ni', 'pero', 'sino', 'que', 'porque', 'si', 'como', 'cuando', 'donde', 'adonde', 'mientras',
        'aunque', 'pues', 'entonces', 'quien', 'quienes', 'cual', 'cuales', 'cuanto', 'cuanta', 'cuantos', 'cuantas',
        'yo', 'tú', 'él', 'ella', 'ello', 'usted', 'nosotros', 'nosotras', 'vosotros', 'vosotras', 'ellos', 'ellas',
        'ustedes', 'me', 'te', 'se', 'le', 'les', 'nos', 'os', 'mí', 'ti', 'conmigo', 'contigo',
        'mi', 'mis', 'tu', 'tus', 'su', 'sus', 'nuestro', 'nuestra', 'nuestros', 'nuestras', 'vuestro', 'vuestra',
        'vuestros', 'vuestras', 'mío', 'mía', 'tuyo', 'tuya', 'suyo', 'suya',
        'este', 'esta', 'esto', 'estos', 'estas', 'ese', 'esa', 'eso', 'esos', 'esas', 'aquel', 'aquella', 'aquello',
        'aquellos', 'aquellas',
        'aquí', 'ahí', 'allí', 'allá', 'acá',
        'qué', 'quién', 'quiénes', 'cuál', 'cuáles', 'cuándo', 'dónde', 'adónde', 'cómo', 'cuánto', 'cuánta',
        'cuántos', 'cuántas',
        'ser', 'es', 'son', 'soy', 'eres', 'somos', 'sois', 'era', 'eran', 'fue', 'fueron', 'sea',
        'estar', 'está', 'están', 'estoy', 'estás', 'estamos', 'estáis', 'estaba', 'estaban',
        'haber', 'he', 'has', 'ha', 'hemos', 'habéis', 'han', 'hay', 'había',
        'tener', 'tengo', 'tienes', 'tiene', 'tenemos', 'tenéis', 'tienen',
        'poder', 'puedo', 'puedes', 'puede', 'podemos', 'podéis', 'pueden',
        'no', 'sí', 'muy', 'también', 'tampoco', 'ya', 'todavía', 'aún', 'solo', 'sólo', 'más', 'menos', 'tan',
        'todo', 'toda', 'todos', 'todas', 'cada', 'algo', 'alguien', 'algún', 'alguno', 'alguna', 'algunos',
        'algunas', 'ningún', 'ninguno', 'ninguna', 'nada', 'nadie', 'otro', 'otra', 'otros', 'otras', 'uno',
        'favor', 'gracias', 'vale', 'claro', 'bueno', 'bien', 'ok', 'okay', 'oh', 'ah', 'perdón', 'perdone', 'disculpe',
        'hola',
    ],

    // THE WORDS A RECOGNISER EATS (наряд FIX-2, п. 2) — articles, the short prepositions, «y», and the forms of ser, estar
    // and haber: left out of BOTH sides when a line the learner is LOOKING AT is compared with what was said. Narrower
    // than `function_words`: «no» flips the meaning and is not here, nor «sin» (against «con»), nor «desde»/«hasta»
    // (opposites), nor the pronouns («me duele» is not «duele»). In the canonical form of the comparison: lower case,
    // accents kept.
    'unstressed_words' => [
        'el', 'la', 'los', 'las', 'un', 'una', 'unos', 'unas',
        'a', 'al', 'de', 'del', 'en', 'con', 'por', 'para',
        'y', 'e',
        'es', 'son', 'soy', 'eres', 'somos', 'está', 'están', 'estoy', 'estás', 'estamos',
        'he', 'has', 'ha', 'hemos', 'han',
    ],

    // A NUMBER SAID EITHER WAY IS THE SAME NUMBER (наряд FIX-2 п. 2, LANG-1 §4): 0–29 are one word each in Spanish —
    // «veintiuno»…«veintinueve» included, the one-word compounds {@see \App\Modules\Shared\Domain\Service\SpokenNumbers}
    // cannot cut —, the tens join the unit by «y» (`number_tens_joiners`: «treinta y cinco» → 35), the hundreds and
    // «mil» multiply («doscientos» → 200, «dos mil veinte» → 2020, «ciento treinta y uno» → 131), «un millón» is 1 000 000
    // by the article before a scale. «un»/«una» are NOT number words: they are the articles too, and read as «1» every
    // «una cita» would be «1 cita» on one side and an article dropped on the other. So where «un»/«una» can only be the
    // number — after «veinti-» and after a tens word and «y» — the entry says it whole: «veintiún», «treinta y un(a)».
    // Written as the language writes them (accents kept — the canonical form keeps them too).
    'number_words' => [
        'cero' => '0', 'uno' => '1', 'dos' => '2', 'tres' => '3', 'cuatro' => '4', 'cinco' => '5', 'seis' => '6',
        'siete' => '7', 'ocho' => '8', 'nueve' => '9', 'diez' => '10', 'once' => '11', 'doce' => '12', 'trece' => '13',
        'catorce' => '14', 'quince' => '15', 'dieciséis' => '16', 'diecisiete' => '17', 'dieciocho' => '18',
        'diecinueve' => '19', 'veinte' => '20', 'veintiuno' => '21', 'veintiún' => '21', 'veintiuna' => '21',
        'veintidós' => '22', 'veintitrés' => '23', 'veinticuatro' => '24', 'veinticinco' => '25', 'veintiséis' => '26',
        'veintisiete' => '27', 'veintiocho' => '28', 'veintinueve' => '29',
        'treinta' => '30', 'cuarenta' => '40', 'cincuenta' => '50', 'sesenta' => '60', 'setenta' => '70',
        'ochenta' => '80', 'noventa' => '90',
        'treinta y un' => '31', 'treinta y una' => '31', 'cuarenta y un' => '41', 'cuarenta y una' => '41',
        'cincuenta y un' => '51', 'cincuenta y una' => '51', 'sesenta y un' => '61', 'sesenta y una' => '61',
        'setenta y un' => '71', 'setenta y una' => '71', 'ochenta y un' => '81', 'ochenta y una' => '81',
        'noventa y un' => '91', 'noventa y una' => '91',
        'cien' => '100', 'ciento' => '100', 'doscientos' => '200', 'doscientas' => '200', 'trescientos' => '300',
        'trescientas' => '300', 'cuatrocientos' => '400', 'cuatrocientas' => '400', 'quinientos' => '500',
        'quinientas' => '500', 'seiscientos' => '600', 'seiscientas' => '600', 'setecientos' => '700',
        'setecientas' => '700', 'ochocientos' => '800', 'ochocientas' => '800', 'novecientos' => '900',
        'novecientas' => '900',
        'mil' => '1000', 'millón' => '1000000', 'millones' => '1000000',
    ],

    // No word joins a number after a SCALE: «ciento veinte», «dos mil cinco» — the parts stand side by side.
    'number_joiners' => [],

    // The joiner after a TENS word (наряд LANG-1 §4): «treinta y uno» → 31, «ciento cuarenta y dos» → 142. After anything
    // below twenty «y» starts the next number — «las dos y cinco» is 2 and 5, «uno y dos» two numbers.
    'number_tens_joiners' => ['y'],

    // Two forms of one word in an inflected language: both at least four letters, sharing all but the last two letters
    // of the shorter («cita» — «citas», «médico» — «médica», «necesito» — «necesita»). One letter is no content word.
    'word_forms' => ['stem_min' => 4, 'stem_tail' => 2, 'content_min_letters' => 2],

    // A number: a digit anywhere in the word («2», «14A», «10:30»), a cardinal, an ordinal. Not «un»/«una» — the
    // articles, every «una cita» would be a count — nor «media» (the «y media» of an hour is no amount of its own), nor
    // «cuarto» (a room as often as a quarter).
    'number_pattern' => '/\d|^(?:cero|uno|dos|tres|cuatro|cinco|seis|siete|ocho|nueve|diez|once|doce|trece|catorce|quince|dieci\p{L}+|veint\p{L}*|treinta|cuarenta|cincuenta|sesenta|setenta|ochenta|noventa|cien|ciento|doscient[oa]s|trescient[oa]s|cuatrocient[oa]s|quinient[oa]s|seiscient[oa]s|setecient[oa]s|ochocient[oa]s|novecient[oa]s|mil|miles|millón|millones|docenas?|primer|primer[oa]s?|segund[oa]s?|tercer|tercer[oa]s?|quint[oa]s?|sext[oa]s?|séptim[oa]s?|octav[oa]s?|noven[oa]s?|décim[oa]s?)$/u',

    // Time and duration words — the units, the parts of the day, the days, the months, «hoy», «ayer», «antes». (The
    // greetings «buenos días», «buenas tardes» are time words too: a line that opens with one says a time — the Russian
    // pack's «добрый день» the same.)
    'time_pattern' => '/^(?:segundos?|minutos?|horas?|días?|semanas?|mes|meses|años?|quincenas?|mañanas?|tardes?|noches?|mediodía|medianoche|madrugadas?|hoy|ayer|anteayer|anoche|ahora|luego|después|antes|pronto|temprano|enseguida|lunes|martes|miércoles|jueves|viernes|sábados?|domingos?|finde|enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|setiembre|octubre|noviembre|diciembre)$/u',

    // The units something is COUNTED in — what makes a run of time words an amount without a numeral of its own
    // («unos minutos», «unas semanas»); with a numeral the numeral makes it one («tres días», «dos horas», «un año» no).
    // NOT «día»/«días», «hora»/«horas», «año»/«años», «semana», «mes»: without a numeral Spanish says them far more often
    // as no amount at all — «Buenos días», «Que tenga un buen día», «¿A qué hora le viene bien?», «las horas de visita»,
    // «¿Cuántos años tiene?», «el fin de semana», «¿Qué día de la semana?» —, and an option of «Поймай число» read off
    // «Buenos días, tiene cita a las diez» was «Días», the first of two runs of one word, taken for the right answer. So
    // «una hora», «media hora», «un mes» give no value (the card is not dealt rather than dealt wrong). The units that
    // are no time words (veces, grados, pastillas…) never join a run and change nothing — written for the day they do.
    'amount_pattern' => '/^(?:segundos?|minutos?|semanas|meses|quincenas?|veces|grados?|pastillas?|comprimidos?|cápsulas?|gotas?|dosis|kilos?|gramos?|litros?|metros?|kilómetros?|euros?)$/u',

    // What carries an amount and is said WITH it, right before it — prepositions, articles, determiners: «a las diez»,
    // «dentro de dos días», «desde hace tres días», «estos dos días», «cerca de las cinco». Read only to the left.
    'amount_prefix' => '/^(?:a|al|en|de|por|para|durante|dentro|desde|hace|hasta|tras|cada|un|una|unos|unas|el|la|los|las|est(?:e|a|os|as)|es(?:e|a|os|as)|próxim[oa]s?|siguientes?|últim[oa]s?|más|menos|casi|media|medio|aproximadamente|como|sobre|cerca|alrededor)$/u',

    // The prompt's STOP LIST (numbers, family, time words, colours, ser / estar / tener / ir) and plain words a learner
    // knows at any level of the plan: not vocabulary.
    'everyday_words' => [
        'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve', 'diez', 'once', 'doce', 'veinte',
        'treinta', 'cuarenta', 'cincuenta', 'cien', 'mil', 'primero', 'primera', 'segundo', 'segunda', 'tercero', 'tercera',
        'madre', 'padre', 'mamá', 'papá', 'padres', 'hermano', 'hermana', 'hijo', 'hija', 'hijos', 'niño', 'niña',
        'niños', 'bebé', 'familia', 'marido', 'esposo', 'esposa', 'abuelo', 'abuela',
        'día', 'días', 'semana', 'semanas', 'mes', 'meses', 'año', 'años', 'hoy', 'mañana', 'ayer', 'tarde', 'noche',
        'hora', 'horas', 'minuto', 'minutos', 'ahora', 'luego', 'pronto', 'tiempo',
        'rojo', 'roja', 'azul', 'verde', 'amarillo', 'amarilla', 'negro', 'negra', 'blanco', 'blanca', 'marrón', 'gris',
        'naranja', 'rosa', 'morado',
        'ser', 'es', 'soy', 'eres', 'somos', 'son', 'estar', 'estoy', 'está', 'están', 'tener', 'tengo', 'tiene',
        'tienes', 'tienen', 'ir', 'voy', 'va', 'vas', 'vamos', 'van', 'hay',
        'trabajo', 'casa', 'escuela', 'colegio', 'hombre', 'mujer', 'gente', 'persona', 'amigo', 'amiga', 'comida',
        'agua', 'coche', 'habitación', 'puerta', 'mesa', 'nombre', 'cosa', 'cosas', 'bueno', 'buena', 'malo', 'mala',
        'grande', 'pequeño', 'pequeña', 'nuevo', 'nueva', 'viejo', 'vieja', 'hola', 'comer', 'beber', 'ver', 'venir',
        'hacer', 'dar', 'querer', 'quiero', 'saber', 'pensar', 'decir', 'mirar', 'necesitar', 'ayudar', 'lugar',
        'ciudad', 'calle', 'dinero', 'libro', 'teléfono', 'móvil', 'perro', 'gato', 'mano', 'cabeza', 'ojo', 'ojos',
        'problema', 'pregunta', 'respuesta',
    ],

    // Ordinary adjectives and quantifiers: the head of a free combination («mucho dolor», «otra cosa») that is no chunk.
    'ordinary_heads' => [
        'grande', 'gran', 'pequeño', 'pequeña', 'bueno', 'buena', 'buen', 'malo', 'mala', 'mal', 'bonito', 'bonita',
        'mucho', 'mucha', 'muchos', 'muchas', 'poco', 'poca', 'pocos', 'pocas', 'otro', 'otra', 'otros', 'otras',
        'diferente', 'importante', 'todo', 'toda', 'varios', 'varias', 'algún', 'alguna', 'algunos', 'algunas',
    ],

    // A partner line that says nothing but «we are done» — its whole text, punctuation aside. A role of a clinic closes
    // with «Que se mejore» and «Cuídese» as often as with «Hasta luego».
    'closers' => [
        'algo más', 'necesita algo más', 'nada más', 'perfecto', 'muy bien', 'bien', 'vale', 'de acuerdo', 'genial',
        'estupendo', 'excelente', 'fenomenal', 'claro', 'claro que sí', 'por supuesto', 'gracias', 'muchas gracias',
        'gracias a usted', 'a usted', 'de nada', 'no hay problema', 'no pasa nada', 'hasta luego', 'hasta mañana',
        'hasta pronto', 'adiós', 'que tenga un buen día', 'que tenga buen día', 'buen día', 'que le vaya bien',
        'que se mejore', 'cuídese', 'entendido', 'listo', 'todo listo', 'nos vemos', 'nos vemos pronto', 'está bien',
        'muy amable', 'un placer', 'con gusto', 'le esperamos', 'perfecto gracias', 'perfecto muchas gracias',
        'muy bien gracias', 'vale gracias',
    ],

    // The verbs a check uses to name who said something («¿Qué dice el paciente?», «¿Qué ha pedido la paciente?»).
    'saying_verbs' => [
        'decir', 'dice', 'dicen', 'dijo', 'dijeron', 'decía', 'dicho', 'contar', 'cuenta', 'cuentan', 'contó',
        'contado', 'responder', 'responde', 'responden', 'respondió', 'respondido', 'contestar', 'contesta', 'contestan',
        'contestó', 'contestado', 'mencionar', 'menciona', 'mencionan', 'mencionó', 'mencionado', 'comentar', 'comenta',
        'comentan', 'comentó', 'comentado', 'explicar', 'explica', 'explican', 'explicó', 'explicado', 'querer', 'quiere',
        'quieren', 'quería', 'quiso', 'pedir', 'pide', 'piden', 'pidió', 'pedido',
    ],

    // The words a partner names alternatives with («¿hoy o mañana?», «siete u ocho»).
    'alternative_words' => ['o', 'u'],

    // One sentence that asks, then goes on after a comma with «y / o» and asks again — its own question word, or a verb a
    // question opens with, or a clitic before one: «¿Le duele la garganta, y desde cuándo tiene fiebre?», «¿Tiene fiebre,
    // y le duele la cabeza?». Spanish opens a question with ¿, so a sentence counts only when its ¿ stands before the
    // comma («Bien, ¿y desde cuándo?» is one question). «la», «lo» are left out: they are the article as often.
    'second_question_pattern' => '/¿[^?¿]*,\s*(?:y|e|o|u)\s+(?:qué|cuál|cuáles|cuándo|cuánto|cuánta|cuántos|cuántas|dónde|adónde|cómo|quién|quiénes|por\s+qué|desde\s+cuándo|tiene|tienes|tienen|puede|puedes|pueden|hay|está|están|ha|has|han|necesita|necesitas|quiere|quieres|toma|tomas|le|les)(?!\p{L})[^?]*\?/iu',

    // Articles: «an article after an article» at the seam of a frame and its filler («Necesito una ___» + «una cita»).
    // Not «lo» (a pronoun as often as the neuter article), not al/del (a preposition in them — spelt out for the judge by
    // `contractions`).
    'articles' => ['el', 'la', 'los', 'las', 'un', 'una', 'unos', 'unas'],

    // WORDS A SENTENCE CANNOT END ON (наряд FIX-3 §7): a move that stops on an article, a short possessive, a short
    // preposition or «y» broke off — «Tengo dolor de», «Necesito una», «Mi número es mi». «al»/«del» end on their article
    // once spelt out. «una» stays although it ends the hour one o'clock («Tengo cita a la una»): the list is read only for
    // a move the role already did NOT understand, and then it only takes the «not understood» back — a learner trailing
    // off on the article is far likelier there than a misunderstood «a la una», and the error falls on the lenient side.
    // Not «nuestro»…: a possessive of its own ends a sentence («Es nuestra»). «tú», «mí», «él» with their accent end
    // sentences («¿Y tú?», «para mí»).
    'dangling_words' => [
        'el', 'la', 'los', 'las', 'un', 'una', 'unos', 'unas', 'mi', 'mis', 'tu', 'tus', 'su', 'sus', 'de', 'a', 'en',
        'con', 'para', 'y', 'e', 'o', 'u',
    ],

    // Spanish says no word twice at a seam and stays Spanish («de de», «a a las cinco»): every doubling counts.
    'seam_repeatable_words' => [],

    // No article of Spanish changes with the next word's SOUND the way a / an does («el agua» is the feminine noun's
    // own rule, not a sound rule of the article): the no-op.
    'article_sound' => [
        'before_vowel' => '',
        'before_consonant' => '',
        'vowel' => '/(?!)/u',
        'consonant' => '/(?!)/u',
        'spelled' => '/(?!)/u',
        'exception' => '/(?!)/u',
    ],

    // A clause where a value should stand. Spanish drops its subject («tengo fiebre»), so only an explicit subject pronoun
    // with a finite form of ser / estar / tener / haber / poder / ir opens a whole sentence («él es mi hijo»); a filler
    // that opens with a subordinator («si es posible», «cuando pueda») is a clause. Narrow on purpose: the fatal
    // `filler.ungrammatical` reads `SENTENCE` after a frame's own words, and «él», «usted» before «es», «está» is a
    // sentence in any Spanish. «como» is no subordinator here — «como a las cinco» is a value.
    'clause' => [
        'subjects' => ['yo', 'tú', 'él', 'ella', 'usted', 'nosotros', 'nosotras', 'vosotros', 'vosotras', 'ellos', 'ellas', 'ustedes'],
        'finite' => [
            'soy', 'eres', 'es', 'somos', 'sois', 'son', 'era', 'eran', 'fue', 'fueron',
            'estoy', 'estás', 'está', 'estamos', 'estáis', 'están', 'estaba', 'estaban',
            'tengo', 'tienes', 'tiene', 'tenemos', 'tenéis', 'tienen', 'tenía', 'tenían',
            'he', 'has', 'ha', 'hemos', 'habéis', 'han',
            'puedo', 'puedes', 'puede', 'podemos', 'podéis', 'pueden',
            'voy', 'vas', 'va', 'vamos', 'vais', 'van',
        ],
        'contractions' => [],
        'subordinators' => ['si', 'cuando', 'porque', 'aunque', 'mientras', 'que'],
        'subordinators_before_subject' => [],
    ],

    // «The frame must stand alone: no unresolved it / that / one / there» (FRAMES). Spanish drops «it» and has no «there»
    // of existence («hay»); what a frame can lean on is the neuter pronoun — «eso», «esto», «ello» («Eso es ___.»),
    // resolved when a determiner and a content word before it name the thing. The object clitics lo / la are left out:
    // «la» is the article far more often («¿La cita es ___?»).
    'unresolved_pronouns' => [
        'words' => ['eso', 'esto', 'ello'],
        'frame_initial_subject' => [],
        'existential' => [],
        'determiner_or_number' => [],
        'partitive' => [],
        'be_forms' => [],
        'determiners' => [
            'el', 'la', 'los', 'las', 'un', 'una', 'unos', 'unas', 'mi', 'mis', 'tu', 'tus', 'su', 'sus', 'nuestro',
            'nuestra', 'nuestros', 'nuestras', 'este', 'esta', 'estos', 'estas', 'ese', 'esa', 'esos', 'esas',
        ],
    ],

    // Spanish marks no gender on a past form said by «yo» («fui», «he ido», «estuve»): the no-op. (The gendered words of
    // a Spanish line about oneself are adjectives — «estoy cansado/a» — which no pattern tells from «soy Ana».)
    'gendered_past_pattern' => '/(?!)/u',

    // «The native frame contains NO word that agrees with the slot in gender or number» (FRAMES) — read at the slot:
    //  - the word right before `___` agrees when it is one of `words` — an article, the contracted al/del, a possessive,
    //    a demonstrative, a quantifier, «cuál» («Necesito una ___», «Voy al ___», «¿Cuál es su ___?» is fine — «es» is no
    //    word here, «cuál» stands before it) — or one of `short_forms`, the predicate adjectives of a booking
    //    («¿Está libre ___?» + «las dos» — the scouting day es→en);
    //  - one of the two words after `___` agrees when it is one of them («___ está incluido?»).
    // No ending is read (`suffixes_before_slot` empty): Spanish verbs end in -o and -a as its adjectives do («Tengo ___»,
    // «Necesita ___»), and a suffix rule would flag every frame. Verbs agree in number too and are not listed («___ no me
    // viene bien»), as in the Russian pack.
    'agreement' => [
        'words' => [
            'el', 'la', 'los', 'las', 'un', 'una', 'unos', 'unas', 'al', 'del',
            'mi', 'mis', 'tu', 'tus', 'su', 'sus', 'nuestro', 'nuestra', 'nuestros', 'nuestras', 'vuestro', 'vuestra',
            'vuestros', 'vuestras',
            'este', 'esta', 'estos', 'estas', 'ese', 'esa', 'esos', 'esas', 'aquel', 'aquella', 'aquellos', 'aquellas',
            'mucho', 'mucha', 'muchos', 'muchas', 'poco', 'poca', 'pocos', 'pocas', 'otro', 'otra', 'otros', 'otras',
            'algún', 'alguno', 'alguna', 'algunos', 'algunas', 'ningún', 'ninguno', 'ninguna', 'todo', 'toda', 'todos',
            'todas', 'cuánto', 'cuánta', 'cuántos', 'cuántas', 'cuál', 'cuáles', 'mismo', 'misma', 'mismos', 'mismas',
            'primer', 'primero', 'primera', 'primeros', 'primeras', 'buen', 'bueno', 'buena', 'buenos', 'buenas',
        ],
        'short_forms' => [
            'libre', 'libres', 'disponible', 'disponibles', 'abierto', 'abierta', 'abiertos', 'abiertas', 'cerrado',
            'cerrada', 'cerrados', 'cerradas', 'ocupado', 'ocupada', 'ocupados', 'ocupadas', 'incluido', 'incluida',
            'incluidos', 'incluidas', 'permitido', 'permitida', 'permitidos', 'permitidas', 'listo', 'lista', 'listos',
            'listas', 'necesario', 'necesaria', 'necesarios', 'necesarias', 'confirmado', 'confirmada', 'confirmados',
            'confirmadas', 'reservado', 'reservada', 'reservados', 'reservadas', 'pagado', 'pagada', 'pagados', 'pagadas',
            'caro', 'cara', 'caros', 'caras', 'barato', 'barata', 'baratos', 'baratas',
        ],
        'suffixes_before_slot' => [],
        'min_letters' => 99,
        'after_slot_words' => 2,
    ],

    // «НЕ ПОНЯЛ» НА ЯЗЫКЕ ЦЕЛИ (наряд CONV-2, п. 4а) — what a rescue move of the talk says in the learner's own bubble
    // (кадр 37-7, en «Sorry?»): the Spanish «Sorry?» of a line not caught.
    'rescue_line' => '¿Perdón?',

    // THE RESCUE KIT (наряд LANG-1b §2): the six lines a learner of this language says when stuck, each with its translation into
    // every learner's language of the plan. Row 1 is `rescue_line`. Said in the learner's voice, filed by (target,
    // gender, voice, line) — `RescueKits`. The same matrix writes every pack: `docs/research/lang-1b/tools/rescue-kit.py`.
    'rescue' => [
        ['target' => '¿Perdón?', 'native' => ['ru' => 'Простите?', 'uk' => 'Перепрошую?', 'be' => 'Прабачце?', 'pl' => 'Słucham?', 'ro' => 'Poftim?', 'es' => '¿Perdón?', 'it' => 'Scusi?', 'de' => 'Wie bitte?', 'fr' => "Pardon\u{00A0}?"]],
        ['target' => '¿Puede hablar más despacio, por favor?', 'native' => ['ru' => 'Можно помедленнее, пожалуйста?', 'uk' => 'Можна повільніше, будь ласка?', 'be' => 'Можна павольней, калі ласка?', 'pl' => 'Proszę mówić trochę wolniej.', 'ro' => 'Puteți vorbi mai rar, vă rog?', 'es' => '¿Puede hablar más despacio, por favor?', 'it' => 'Può parlare più lentamente, per favore?', 'de' => 'Können Sie bitte langsamer sprechen?', 'fr' => "Vous pouvez parler plus lentement, s'il vous plaît\u{00A0}?"]],
        ['target' => 'No entiendo.', 'native' => ['ru' => 'Я не понимаю.', 'uk' => 'Я не розумію.', 'be' => 'Я не разумею.', 'pl' => 'Nie rozumiem.', 'ro' => 'Nu înțeleg.', 'es' => 'No entiendo.', 'it' => 'Non capisco.', 'de' => 'Ich verstehe nicht.', 'fr' => 'Je ne comprends pas.']],
        ['target' => 'Un momento.', 'native' => ['ru' => 'Одну минуту.', 'uk' => 'Хвилинку.', 'be' => 'Хвілінку.', 'pl' => 'Chwileczkę.', 'ro' => 'Un moment.', 'es' => 'Un momento.', 'it' => 'Un momento.', 'de' => 'Einen Moment.', 'fr' => 'Un instant.']],
        ['target' => '¿Me lo puede escribir?', 'native' => ['ru' => 'Можете это записать?', 'uk' => 'Можете це записати?', 'be' => 'Можаце гэта запісаць?', 'pl' => 'Proszę mi to zapisać.', 'ro' => 'Îmi puteți scrie asta?', 'es' => '¿Me lo puede escribir?', 'it' => 'Me lo può scrivere?', 'de' => 'Können Sie mir das aufschreiben?', 'fr' => "Vous pouvez me l'écrire\u{00A0}?"]],
        ['target' => 'Gracias.', 'native' => ['ru' => 'Спасибо.', 'uk' => 'Дякую.', 'be' => 'Дзякуй.', 'pl' => 'Dziękuję.', 'ro' => 'Mulțumesc.', 'es' => 'Gracias.', 'it' => 'Grazie.', 'de' => 'Danke.', 'fr' => 'Merci.']],
    ],

    // THE ROLE'S NEUTRAL MOVE (наряд BACK-TAILS-2 §9) — the same line as every pack's, in this language, in the polite
    // «usted» of the roles (en «I see. Please go on.», ru «Понятно. Продолжайте, пожалуйста.»).
    'neutral_reply' => 'Entiendo. Continúe, por favor.',

    // THE FORMS OF ONE WORD and THE PERSONS SWAPPED (наряд BACK-TAILS-2 §§2, 9) — the no-ops: Spanish words are compared as
    // they are, after the canonical form. A Spanish base of a verb is not a suffix rule («tengo» — «tiene» — «tuve»), and
    // a swap of persons is not one word for one («mi» is «su» to a role that says «usted» and «tu» to one that says «tú»):
    // written wrong they would find echoes nobody said. Left for a later order.
    'irregular_forms' => [],
    'inflection_rules' => [],
    'person_swap' => [],

    // ─── THE JUDGE OF THE TALK'S CONSTRUCTIONS (наряд FIX-4 §2, LANG-1 §1) — what the judge may forgive of Spanish.

    // «al» and «del» ARE «a el» and «de el» — the one contraction Spanish must write. Spelt out (and the article then left
    // out of the comparison), «Voy al médico» says «Voy a ___.» and «Voy a la farmacia» says «Voy al ___.»; read as words
    // of their own each would be one difference, and an honest line only «almost».
    'contractions' => ['al' => 'a el', 'del' => 'de el'],
    'contractions_before' => [],

    // THE WORDS A MOVE MAY OPEN WITH before its construction: «Hola, buenos días, necesito una cita», «Sí, tengo fiebre»,
    // «Vale, gracias, ¿puedo pagar con tarjeta?», «Perdona, tengo una pregunta», «Doctora, me duele la garganta» — the
    // greetings, yes / no, the fillers, «perdone» and «perdona» (usted and tú), and the address of the role. Not «este»
    // (the hesitation of some speakers is a demonstrative first).
    'intro_words' => [
        'hola', 'buenos días', 'buenas tardes', 'buenas noches', 'buenas', 'sí', 'no', 'vale', 'bueno', 'bien', 'muy bien',
        'pues', 'claro', 'perfecto', 'genial', 'de acuerdo', 'ok', 'okay', 'gracias', 'muchas gracias', 'por favor',
        'perdón', 'perdone', 'perdona', 'disculpe', 'disculpa', 'oiga', 'oye', 'mire', 'mira', 'a ver', 'y', 'entonces',
        'doctor', 'doctora', 'señor', 'señora', 'señorita', 'eh', 'ah', 'oh', 'mm',
    ],

    // THE WORDS A NEW CLAUSE OPENS WITH (наряд FIX-4b §1): «Tengo dolor de garganta y también tengo fiebre», «Tengo fiebre,
    // además tengo tos» say both constructions; «e», «u» are «y», «o» before i- and o-; «luego» is the English «then».
    // Not «también»: it opens constructions of its own («También tengo ___»), and as a starter it would say «Tengo ___»
    // inside every one of them.
    'clause_starters' => ['y', 'e', 'pero', 'o', 'u', 'entonces', 'así que', 'además', 'luego'],

    // A CONSTRUCTION SAID IN THE NEGATIVE IS THE SAME CONSTRUCTION (DECISIONS п. 395, наряд LANG-1 §1): «No tengo fiebre»
    // says «Tengo ___.» — «no» stands before the verb, the first word of the move too, so it is free anywhere.
    'negation' => ['words' => ['no'], 'after' => null, 'do_support' => []],

    // THE PARTITIVE «DE» GOES WITH A QUANTITY: «Tengo ___ de experiencia» is said as «Tengo mucha experiencia», «No tengo
    // ninguna experiencia» — a window of one of `determiners` leaves the frame's «de» out. «algo de», «un poco de» keep it.
    'partitive' => [
        'word' => 'de',
        'determiners' => [
            'mucho', 'mucha', 'muchos', 'muchas', 'poco', 'poca', 'pocos', 'pocas', 'bastante', 'bastantes', 'suficiente',
            'suficientes', 'más', 'menos', 'ningún', 'ninguno', 'ninguna', 'algún', 'alguna', 'cierto', 'cierta',
        ],
    ],

    // THE TITLE OF A TALK (наряд LANG-1 §6): Spanish puts no role in a case — «Conversación: recepcionista y médico», the
    // roles as written, the first letter lowered (an acronym keeps its capitals); «y» is «e» before a role that starts with
    // the sound i- («médico e internista», «e hijo»), but not before «hie-» («y hielo»).
    'talk_title_template' => [
        'title' => 'Conversación: {roles}',
        'and' => 'y',
        'anyone' => 'Conversación',
        'lower_first' => true,
        'and_before' => ['/^h?[ií](?![aeouáéóú])/iu' => 'e'],
    ],
];
