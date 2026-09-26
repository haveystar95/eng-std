<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Domain\Check\Language\PackSkip;
use App\Modules\Plan\Domain\Check\LessonValidator;
use App\Modules\Plan\Domain\Lesson\EarlierDays;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\Service\ReplyNative;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;
use App\Modules\Shared\Domain\Service\LanguageRoles;

/**
 * THE PACKS TOGETHER (наряд LANG-1, the cross-pack integrator). Each language's own test holds its pack to its own
 * lines; what no single pack can see is what the packs do to EACH OTHER — the guard of the role's translation
 * ({@see ReplyNative}) reads a learner's line against the frequent words of every other pack in the same letters, so a
 * word one pack lists can refuse the ordinary lines of another. And every pair of the plan must find every key its
 * checks read, on both sides.
 *
 * The lines are the model's own, from the order's scouting run (`docs/research/lang-1/answers/<pair>.json` and
 * `final/<pair>.json`, the `text_native` fields where the language is the learner's, the `text_target` ones where it is
 * taught — ru-it's learner lines are left out: the model answered them in English). Measured with every pack deployed,
 * before this test was written (the report of the order): no line of any learner's language refused — 0 of 1 322, every
 * native field of the fourteen days but the frames (536 ru, 109 uk, 112 be, 113 pl, 110 ro, 117 es, 110 it, 108 de,
 * 107 fr; two frames of it-en the model wrote in English are refused, rightly); of another language's dialogue lines in
 * the learner's letters the guard lets through en 25/108, de 13/32, es 21/33, fr 12/31, it 12/24, pl 17/32, ro 14/31,
 * ru 52/87, uk 12/16, be 8/17 — each a line too short to hold two of its language's frequent words (the rule's own AND:
 * «Mam ból gardła i gorączkę.», «I have a sore throat.»). The «another language» lines below are ones the lists catch: a
 * canary for the words that catch them, not a sample of the rate.
 */

/**
 * Ordinary lines of each learner's language — every dialogue line of its day with English (the six days of Russian
 * learners for ru) and two questions of the day's checks — that the guard must keep as a translation.
 *
 * @return array<string, list<string>> learner's language → its lines
 */
function crossPackOwnLines(): array
{
    return [
        'ru' => [
            'Хорошо, тогда я запишу вас на завтра на четыре.',
            'Пожалуйста, возьмите с собой страховую карту.',
            'Да, клиника находится на Банхофштрассе, 12.',
            'Конечно. На какой день вам нужна запись?',
            'Понимаю. Значит, это срочная запись.',
            'Мой номер — 912 555 381.',
            'Отлично. Ваша запись на сегодня подтверждена.',
            'Я хотел(а) бы записаться на приём.',
            'Хорошо. По какому вопросу вы хотите записаться?',
            'Я записала. Приём завтра в десять. Приходите за десять минут до начала.',
            'Хорошо, я приду за десять минут до начала.',
            'Сначала вы оформляетесь, потом ждёте врача.',
            'К которому часу мне прийти?',
            'Приходите за пятнадцать минут до приёма.',
            'Есть что-нибудь после обеда?',
            'Пожалуйста, придите на десять минут раньше.',
            'Конечно. К какому специалисту?',
            'У нас есть завтра в десять или в два. Возьмите документ и приходите за десять минут раньше.',
            'Достаточно только удостоверения личности.',
            'Консультация стоит двести леев.',
            'Уже три дня.',
            'Какое время во второй половине дня ещё свободно?',
            'Какой документ, по словам администратора, достаточно принести?',
        ],
        'uk' => [
            'Я б хотів записатися на прийом до лікаря.',
            'Звісно. Що вас турбує?',
            'Що вас турбує?',
            'У мене болить горло і є температура.',
            'Сьогодні є записи на десяту або на третю.',
            'Третя мені підходить краще.',
            'Не могли б ви дати мені адресу клініки?',
            'Так. Це Грін-стріт, 18.',
            'Будь ласка, прийдіть на десять хвилин раніше для реєстрації.',
            'Я прийду на десять хвилин раніше.',
            'Ви працюєте завтра вранці?',
            'Так, завтра ми відчиняємося о восьмій.',
            'Ваш прийом о третій у лікаря Браун.',
            'Добре, о третій мені підходить.',
            'Будь ласка, принесіть посвідчення особи на стійку реєстрації.',
            'Я принесу своє посвідчення особи.',
            'Який час відкриття називає адміністраторка?',
            'Що сказав пацієнт про свою проблему?',
        ],
        'be' => [
            'Я хачу запісацца на прыём да лекара.',
            'Вядома. Што вас турбуе?',
            'Якія ў вас сімптомы?',
            'У мяне баліць горла.',
            'Як даўно гэта ў вас?',
            'Ужо тры дні.',
            'У вас таксама ёсць тэмпература?',
            'Так, у мяне таксама ёсць тэмпература.',
            'Які бліжэйшы вольны час?',
            'У нас ёсць сёння а чацвёртай і заўтра а дзявятай.',
            'Скажыце, калі ласка, павольней.',
            'Сёння а чацвёртай або заўтра а дзявятай.',
            'Час а чацвёртай яшчэ вольны.',
            'Чацвёртая мне падыходзіць.',
            'Калі ласка, запішыце мяне на сёння а чацвёртай.',
            'Гатова. Вы запісаны на сёння а чацвёртай.',
            'Вядома. Скажыце, калі ласка, у чым праблема.',
            'З якой праблемай пацыент запісваецца да лекара?',
            'Які дадатковы сімптом называе рэгістратар?',
        ],
        'pl' => [
            'Chcę umówić wizytę.',
            'Oczywiście. Co się dzieje?',
            'Czy to coś pilnego na dziś?',
            'Boli mnie gardło i mam gorączkę.',
            'Mamy dziś o czwartej albo jutro o dziewiątej.',
            'To trwa od trzech dni.',
            'Czy mogę przyjść dziś o czwartej?',
            'Tak, ten termin jest jeszcze wolny.',
            'Potrzebuję twojego imienia i nazwiska do rezerwacji.',
            'Nazywam się Jan Kowalski.',
            'Proszę wziąć dokument tożsamości i przyjść dziesięć minut wcześniej.',
            'Dobrze, wezmę dowód.',
            'Czy mogę dostać adres?',
            'Tak. To Green Street 12, obok apteki.',
            'Jesteś zapisany na dziś na czwartą do doktor Lee.',
            'Dobrze, do zobaczenia dziś o czwartej.',
            'Oczywiście. Proszę powiedzieć, co się dzieje.',
            'Po co pacjent dzwoni lub podchodzi do recepcji?',
            'Jaki punkt orientacyjny podaje recepcjonista?',
        ],
        'ro' => [
            'Aș vrea să fac o programare.',
            'Sigur. Pentru ce este consultația?',
            'Care pare să fie problema?',
            'Mă doare gâtul.',
            'De cât timp o ai?',
            'O am de trei zile.',
            'Avem azi la patru sau mâine la nouă.',
            'Azi la patru este bine pentru mine.',
            'Ce trebuie să aduc?',
            'Te rog să aduci actul de identitate și cardul de asigurare.',
            'Ar trebui să vin mai devreme?',
            'Da, te rog să vii cu cincisprezece minute mai devreme.',
            'Mai am nevoie și de numărul tău de telefon pentru programare.',
            'Iată numărul meu de telefon.',
            'Ești programat azi la patru la doamna doctor Lee.',
            'Bine, ne vedem azi la patru.',
            'Cu cât mai devreme trebuie să ajungă pacientul?',
            'Ce detaliu suplimentar are nevoie recepționera?',
        ],
        'es' => [
            'Quisiera una cita con el médico.',
            'Claro. ¿Qué problema tienes?',
            '¿Qué problema tienes?',
            'Tengo dolor de garganta y fiebre.',
            '¿Cuánto tiempo llevas con eso?',
            'Llevo tres días con eso.',
            'Tenemos citas hoy a las diez y a las dos.',
            'Las diez no me vienen bien.',
            '¿Están libres las dos?',
            'Sí, las dos están libres.',
            '¿Tienen algo mañana por la mañana?',
            'No, pero las dos de hoy siguen libres.',
            '¿Me dices tu nombre, por favor?',
            'Me llamo Carlos Ruiz.',
            'Tienes cita hoy a las dos. Por favor, llega diez minutos antes.',
            'Estaré allí diez minutos antes.',
            'Claro.',
            '¿Qué hora dice la recepcionista que sigue libre?',
            '¿Qué dato personal pide la recepcionista?',
        ],
        'it' => [
            'Ho bisogno di un appuntamento dal medico.',
            'Certo. Qual è il problema?',
            'Può dirmi brevemente i sintomi?',
            'Ho mal di gola e la febbre.',
            'Da quanto tempo ha questi sintomi?',
            'Li ho da tre giorni.',
            'Che orari disponibili avete?',
            'Abbiamo le 10 di oggi o le 3 di domani.',
            'Posso prendere le 3 di domani?',
            'Sì, quell\'orario è ancora libero.',
            'Per favore, arrivi dieci minuti prima per la registrazione.',
            'Arriverò dieci minuti prima.',
            'Può ripeterlo, per favore?',
            'Per favore, venga dieci minuti prima del suo appuntamento.',
            'È prenotato per domani alle 3.',
            'Va bene, domani alle 3 va bene.',
            'Certo. Mi dica il problema.',
            'Prima di che cosa deve arrivare in anticipo il paziente?',
            'Quali due opzioni di appuntamento offre l\'addetta?',
        ],
        'de' => [
            'Ich hätte gern einen Arzttermin.',
            'Natürlich. Was ist denn das Problem?',
            'Welche Beschwerden haben Sie?',
            'Ich habe Halsschmerzen und Fieber.',
            'Wie lange haben Sie das schon?',
            'Ich habe das seit drei Tagen.',
            'Wir haben heute Termine um zehn oder um zwei.',
            'Zwei Uhr passt mir besser.',
            'Was brauchen Sie für den Namen?',
            'Ich brauche bitte Ihren vollständigen Namen.',
            'Und wie ist Ihre Telefonnummer?',
            'Meine Telefonnummer ist 0176 234567.',
            'Bitte kommen Sie fünfzehn Minuten früher wegen des Formulars.',
            'Ich komme fünfzehn Minuten früher.',
            'Könnten Sie mir die Adresse geben?',
            'Ja, das ist King Street 14.',
            'Nach welcher persönlichen Angabe fragt die Empfangskraft?',
            'Welche zwei Uhrzeiten bietet die Empfangskraft an?',
        ],
        'fr' => [
            'Je voudrais prendre rendez-vous.',
            'Bien sûr. Quelle est la raison de votre visite ?',
            'C\'est pour un mal de gorge ou autre chose ?',
            'C\'est pour un mal de gorge.',
            'Depuis combien de temps avez-vous de la fièvre ?',
            'J\'ai ça depuis trois jours.',
            'Quels horaires sont disponibles aujourd\'hui ?',
            'Nous avons 10 h et 15 h aujourd\'hui.',
            '15 h me convient.',
            'D\'accord, je peux vous réserver 15 h aujourd\'hui.',
            'Puis-je avoir votre nom complet, s\'il vous plaît ?',
            'Je m\'appelle Marie Dupont.',
            'Quel est votre numéro de téléphone ?',
            'Mon numéro de téléphone est le 06 12 34 56 78.',
            'Vous êtes inscrit pour 15 h aujourd\'hui avec le Dr Lee.',
            'Merci, à tout à l\'heure.',
            'Quelle heure de rendez-vous la réceptionniste confirme-t-elle ?',
            'Quel renseignement personnel la réceptionniste demande-t-elle ?',
        ],
    ];
}

/**
 * Five lines of each language of the plan — the role's or the learner's, as the model wrote them in that language — that
 * a learner of ANOTHER language in the same letters must not be handed as a translation. English from the days with
 * English (one with the typographic apostrophe of uk-en), the other taught languages from their day with English or with
 * a Russian learner, ru uk be from their own days.
 *
 * @return array<string, list<string>> language → its lines
 */
function crossPackForeignLines(): array
{
    return [
        'en' => [
            'Of course. What seems to be the problem?',
            'How long have you had these symptoms?',
            'You\'re booked for today at four with Dr. Lee.',
            'Please bring your ID and arrive ten minutes early.',
            'Yes. It’s 18 Green Street.',
        ],
        'pl' => [
            'Oczywiście. Co się dzieje?',
            'Mamy dziś o czwartej albo jutro o dziewiątej.',
            'Tak, ten termin jest jeszcze wolny.',
            'Dobrze. Jaki jest powód wizyty?',
            'Czy mogę przyjść dziś o czwartej?',
        ],
        'ro' => [
            'Sigur. Pentru ce este consultația?',
            'Avem mâine la zece sau la două. Mai veniți cu actul și ajungeți cu zece minute înainte.',
            'Te rog să aduci actul de identitate și cardul de asigurare.',
            'De când aveți simptomele?',
            'Ești programat azi la patru la doamna doctor Lee.',
        ],
        'es' => [
            'Tenemos hoy a las cuatro o mañana a las diez.',
            'Gracias. ¿Cuál es su número de teléfono?',
            'Perfecto. Su cita está confirmada para hoy.',
            'No, pero las dos de hoy siguen libres.',
            '¿Tienen algo mañana por la mañana?',
        ],
        'it' => [
            'Certo. Qual è il problema?',
            'Da quanto tempo ha questi sintomi?',
            'Abbiamo posto oggi alle tre o domani alle nove.',
            'Per favore, venga dieci minuti prima del suo appuntamento.',
            'È prenotato per domani alle 3.',
        ],
        'de' => [
            'Natürlich. Was ist denn das Problem?',
            'Wie lange haben Sie das schon?',
            'Wir haben heute Termine um zehn oder um zwei.',
            'Bitte bringen Sie Ihre Versicherungskarte mit.',
            'Ja, die Klinik ist in der Bahnhofstraße zwölf.',
        ],
        'fr' => [
            'Bien sûr. Quelle est la raison de votre visite ?',
            'Nous avons une place demain à dix heures.',
            'J\'ai besoin de votre nom et de votre date de naissance.',
            'C\'est noté. Rendez-vous demain à dix heures. Arrivez dix minutes avant.',
            'Puis-je avoir votre nom complet, s\'il vous plaît ?',
        ],
        'ru' => [
            'Конечно. На какой день вам нужна запись?',
            'Хорошо, тогда я запишу вас на завтра на четыре.',
            'Пожалуйста, возьмите с собой страховую карту.',
            'Как долго у вас эти симптомы?',
            'Мне нужны ваши имя и дата рождения.',
        ],
        'uk' => [
            'Звісно. Що вас турбує?',
            'Сьогодні є записи на десяту або на третю.',
            'Не могли б ви дати мені адресу клініки?',
            'У мене болить горло і є температура.',
            'Адміністраторка каже, що прийом буде у лікаря Браун.',
        ],
        'be' => [
            'Вядома. Што вас турбуе?',
            'Як даўно гэта ў вас?',
            'У нас ёсць сёння а чацвёртай і заўтра а дзявятай.',
            'Калі ласка, запішыце мяне на сёння а чацвёртай.',
            'Вядома. Скажыце, калі ласка, у чым праблема.',
        ],
    ];
}

// The canaries cover the plan: every learner's language has its own lines, every language of the plan its lines for the
// others. CATCHES a language added to the plan and left out of the canaries below — they would pass over it.
it('holds canary lines for every language of the plan', function () {
    $all = array_values(array_unique([...LanguageRoles::planTargets(), ...LanguageRoles::planNatives()]));

    expect(array_keys(crossPackOwnLines()))->toEqualCanonicalizing(LanguageRoles::planNatives())
        ->and(array_keys(crossPackForeignLines()))->toEqualCanonicalizing($all);
    foreach (crossPackOwnLines() as $code => $lines) {
        expect(count($lines))->toBeGreaterThanOrEqual(10, "{$code}: ten lines at least");
    }
    foreach (crossPackForeignLines() as $code => $lines) {
        expect($lines)->toHaveCount(5, "{$code}: five lines");
    }
});

// Canon (LANG-1 §5 + the main session's update to `common_words`: «frequent AND distinctive — not an ordinary word in ANY
// same-script neighbour»). Every ordinary line of a learner's language stays a translation with EVERY pack deployed — the
// guard compares it with the frequent words of each neighbour, not only with the target's. CATCHES a word of one pack's
// list that is an ordinary word of a neighbour (the probe of the order: a uk list with «для», «до» refused «Для записи к
// врачу приходите до двенадцати» for ru), and a learner's own list too thin to hold its lines against a neighbour's.
it('keeps every ordinary line of a learner\'s language a translation, with every pack deployed', function (string $native) {
    $pack = lessonPacks()->for($native);
    $refused = array_values(array_filter(
        crossPackOwnLines()[$native],
        static fn (string $line): bool => ReplyNative::missing('—', $line, $pack),
    ));

    expect($pack->commonWords())->not->toBe([])
        ->and($refused)->toBe([]);
})->with(LanguageRoles::planNatives());

// Canon (LANG-1 §5): for EVERY pair of languages in the same letters — the learner N and another language X of the plan —
// X's lines are no translation under N, and the same lines are one under X's own learner. The learners of a language are
// found by the letters their packs write, so a pack whose `script_letters` drifted from its script's string would leave
// this test with nobody to ask — it says so. CATCHES a list that lost the words its language is told by (the other
// language's line kept), a neighbour that dropped out of the comparison, and a pair where the guard refuses both ways.
it('refuses a line of another language in the learner\'s letters, for every learner of those letters', function (string $language) {
    $packs = lessonPacks();
    $letters = $packs->for($language)->asNeighbour()['script_letters'];
    $learners = array_values(array_filter(
        LanguageRoles::planNatives(),
        static fn (string $native): bool => $native !== $language && $packs->for($native)->asNeighbour()['script_letters'] === $letters,
    ));
    $wrong = [];
    foreach (crossPackForeignLines()[$language] as $line) {
        foreach ($learners as $learner) {
            if (! ReplyNative::missing('—', $line, $packs->for($learner))) {
                $wrong[] = "kept under {$learner}: {$line}";
            }
        }
        if (in_array($language, LanguageRoles::planNatives(), true) && ReplyNative::missing('—', $line, $packs->for($language))) {
            $wrong[] = "refused under {$language} itself: {$line}";
        }
    }

    expect($letters)->not->toBeNull()
        ->and(count($learners))->toBe(in_array($language, ['ru', 'uk', 'be'], true) ? 2 : (in_array($language, LanguageRoles::planNatives(), true) ? 5 : 6))
        ->and($wrong)->toBe([]);
})->with(array_values(array_unique([...LanguageRoles::planTargets(), ...LanguageRoles::planNatives()])));

/**
 * Words each language of the plan says ALL THE TIME — its commonest function words, pronouns, forms of «be/have», «yes/no»,
 * «today/tomorrow» — whether or not another language says them too, as normal() keeps them (lower case, one run of
 * letters). Not the pack's `common_words` (those are only the distinctive ones): the words a NEIGHBOUR may never list,
 * since a line of this language holds them as a matter of course. A word here is one a native speaker would call
 * ordinary in its plain spelling — rare homographs are left out (Polish «mir», Italian «od», Ukrainian colloquial «он»).
 *
 * @return array<string, list<string>> language → its ordinary words
 */
function crossPackOrdinaryWords(): array
{
    return [
        'en' => [
            'the', 'a', 'an', 'and', 'or', 'but', 'to', 'of', 'in', 'on', 'at', 'for', 'with', 'from', 'by', 'as', 'i', 'me',
            'my', 'you', 'he', 'him', 'his', 'she', 'her', 'it', 'we', 'us', 'our', 'they', 'them', 'this', 'that', 'is',
            'am', 'are', 'was', 'were', 'be', 'have', 'has', 'do', 'will', 'can', 'not', 'no', 'yes', 'so', 'if', 'then',
            'now', 'go', 'come', 'get', 'see', 'want', 'man', 'also', 'die', 'hat', 'war', 'ok', 'okay',
        ],
        'pl' => [
            'i', 'w', 'we', 'z', 'ze', 'na', 'do', 'od', 'po', 'za', 'o', 'u', 'dla', 'przez', 'nie', 'tak', 'to', 'ten',
            'ta', 'te', 'ja', 'ty', 'on', 'ona', 'my', 'wy', 'oni', 'mi', 'mnie', 'go', 'jej', 'was', 'pan', 'pani', 'się',
            'jest', 'są', 'mam', 'ma', 'a', 'ale', 'albo', 'lub', 'czy', 'że', 'co', 'jak', 'gdzie', 'kiedy', 'już', 'tu',
            'tam', 'no', 'bo', 'moi', 'moja', 'dobrze', 'proszę', 'dziś', 'jutro', 'da',
        ],
        'ro' => [
            'și', 'în', 'de', 'la', 'a', 'al', 'ale', 'pe', 'cu', 'din', 'pentru', 'nu', 'să', 'că', 'o', 'un', 'e', 'este',
            'sunt', 'am', 'ai', 'are', 'se', 'ce', 'cine', 'mai', 'mă', 'te', 'ne', 'vă', 'eu', 'tu', 'el', 'ea', 'noi',
            'voi', 'ei', 'da', 'dar', 'sau', 'când', 'cum', 'unde', 'bine', 'aici', 'acum', 'azi', 'mâine', 'după', 'dacă',
            'ora', 'zi', 'est',
        ],
        'es' => [
            'de', 'la', 'el', 'los', 'las', 'un', 'una', 'y', 'e', 'o', 'u', 'a', 'al', 'del', 'en', 'con', 'por', 'para',
            'sin', 'que', 'qué', 'no', 'sí', 'si', 'se', 'me', 'te', 'le', 'lo', 'nos', 'les', 'mi', 'tu', 'su', 'yo', 'tú',
            'él', 'ella', 'es', 'son', 'está', 'hay', 'como', 'cuando', 'donde', 'muy', 'más', 'pero', 'ya', 'bien', 'hoy',
            'este', 'esta', 'eso', 'todo', 'nada', 'pan', 'va',
        ],
        'it' => [
            'di', 'e', 'ed', 'il', 'lo', 'la', 'i', 'gli', 'le', 'un', 'una', 'uno', 'a', 'ad', 'da', 'in', 'con', 'su',
            'per', 'tra', 'del', 'della', 'dei', 'al', 'alla', 'nel', 'che', 'non', 'è', 'sono', 'ha', 'ho', 'hanno', 'si',
            'mi', 'ti', 'ci', 'vi', 'ne', 'io', 'tu', 'lui', 'lei', 'noi', 'voi', 'loro', 'ma', 'o', 'se', 'come', 'quando',
            'dove', 'anche', 'più', 'bene', 'sì', 'no', 'qui', 'oggi', 'questo', 'cosa', 'poi', 'prima', 'va', 'est', 'c',
        ],
        'de' => [
            'der', 'die', 'das', 'den', 'dem', 'des', 'ein', 'eine', 'und', 'oder', 'aber', 'in', 'im', 'an', 'am', 'auf',
            'aus', 'bei', 'mit', 'nach', 'von', 'zu', 'zum', 'für', 'um', 'bis', 'ich', 'du', 'er', 'sie', 'es', 'wir', 'ihr',
            'mir', 'mich', 'dir', 'uns', 'ist', 'sind', 'bin', 'war', 'hat', 'haben', 'kann', 'will', 'nicht', 'ja', 'nein',
            'was', 'wer', 'wie', 'wo', 'so', 'da', 'hier', 'heute', 'jetzt', 'noch', 'gut', 'bitte', 'man', 'also', 'her',
            'bring', 'mal', 'total',
        ],
        'fr' => [
            'de', 'du', 'des', 'la', 'le', 'les', 'l', 'un', 'une', 'et', 'ou', 'où', 'à', 'au', 'aux', 'en', 'dans', 'sur',
            'pour', 'par', 'avec', 'sans', 'que', 'qu', 'qui', 'ne', 'n', 'pas', 'plus', 'je', 'j', 'tu', 'il', 'elle',
            'on', 'nous', 'vous', 'me', 'm', 'te', 't', 'se', 's', 'moi', 'toi', 'lui', 'ce', 'c', 'ça', 'est', 'es', 'sont',
            'a', 'ai', 'as', 'd', 'y', 'si', 'oui', 'non', 'bien', 'très', 'ici', 'là', 'mais', 'merci', 'mon', 'ma', 'son',
            'sa', 'va',
        ],
        'ru' => [
            'и', 'в', 'во', 'на', 'с', 'со', 'к', 'по', 'за', 'из', 'от', 'до', 'для', 'о', 'об', 'у', 'не', 'нет', 'да',
            'а', 'но', 'или', 'что', 'как', 'так', 'это', 'то', 'та', 'все', 'всё', 'я', 'ты', 'он', 'она', 'мы', 'вы',
            'они', 'мне', 'меня', 'вас', 'вам', 'нас', 'нам', 'его', 'её', 'их', 'же', 'бы', 'ли', 'ну', 'вот', 'уже',
            'ещё', 'тут', 'там', 'где', 'когда', 'можно', 'нужно', 'надо', 'хорошо', 'спасибо', 'сегодня', 'завтра', 'день',
            'три', 'два', 'будь', 'хочу', 'ласка',
        ],
        'uk' => [
            'і', 'й', 'та', 'в', 'у', 'на', 'з', 'із', 'зі', 'до', 'від', 'для', 'по', 'за', 'о', 'про', 'не', 'ні', 'так',
            'а', 'але', 'або', 'чи', 'що', 'як', 'це', 'то', 'все', 'я', 'ти', 'він', 'вона', 'ми', 'ви', 'вони', 'мені',
            'мене', 'вас', 'вам', 'нас', 'його', 'її', 'їх', 'же', 'б', 'би', 'вже', 'уже', 'ще', 'тут', 'там', 'де', 'коли',
            'якщо', 'дуже', 'можна', 'треба', 'добре', 'дякую', 'будь', 'ласка', 'хочу', 'сьогодні', 'завтра', 'день', 'три',
            'два',
        ],
        'be' => [
            'і', 'й', 'у', 'ў', 'на', 'з', 'са', 'да', 'ад', 'для', 'па', 'за', 'аб', 'пра', 'праз', 'не', 'ні', 'так', 'а',
            'але', 'або', 'ці', 'што', 'як', 'гэта', 'усё', 'я', 'ты', 'ён', 'яна', 'мы', 'вы', 'яны', 'мне', 'мяне', 'вас',
            'вам', 'нас', 'яго', 'яе', 'іх', 'ж', 'б', 'бы', 'ужо', 'яшчэ', 'тут', 'там', 'дзе', 'калі', 'вельмі', 'можна',
            'трэба', 'добра', 'дзякуй', 'ласка', 'хачу', 'сёння', 'заўтра', 'дзень', 'тры', 'два',
        ],
    ];
}

// Canon (the main session's update to `common_words`: «frequent AND distinctive — NOT an ordinary word (same spelling) in
// ANY same-script neighbour»). No pack lists a word that a neighbour in its letters says as a matter of course
// ({@see crossPackOrdinaryWords()}): such a word, in the neighbour's lines, counts for the pack's language — two of them
// and an ordinary neighbour line is «not a translation». CATCHES the order's probe (a uk list with «для», «до»: «Для
// записи к врачу приходите до двенадцати» refused for ru), a Romance list taking «la», «de», a Polish «moi» in the fr
// list, an English «no», «me» under a Spanish learner.
it('lists no word a neighbour in the same letters says as an ordinary word', function () {
    $packs = lessonPacks();
    $ordinary = crossPackOrdinaryWords();
    $clash = [];
    foreach ($ordinary as $language => $words) {
        $letters = $packs->for($language)->asNeighbour()['script_letters'];
        foreach ($ordinary as $neighbour => $unused) {
            if ($neighbour === $language || $packs->for($neighbour)->asNeighbour()['script_letters'] !== $letters) {
                continue;
            }
            $listed = array_values(array_intersect($packs->for($neighbour)->commonWords(), $words));
            if ($listed !== []) {
                $clash[] = "{$neighbour} lists {$language}'s ordinary ".implode(', ', $listed);
            }
        }
    }

    expect(array_keys($ordinary))->toEqualCanonicalizing(array_keys(crossPackForeignLines()))
        ->and($clash)->toBe([]);
});

// Canon (LANG-1 §5, the whole sample): EVERY line the scouting run wrote in a learner's language — the dialogue, the
// checks and their options, the explanations, the listening questions and options, the topic, the roles, the slot hints
// and fillers, the vocabulary's translations: every `*_native` field of the fourteen days but the frames (it-en wrote two of
// them in English) and the readings (the target's sounds in the learner's letters) — stays a translation under its own
// learner with every pack deployed. Read from the research files, as EnRuPackTest and DePackTest read them, so a list
// written tomorrow meets all 1 322 lines. CATCHES what the hand-picked canaries above are too few to: a shared word of
// short lines («El motivo de la visita», «La cita de las doce» under an es list with «la», «de» written into fr or ro).
it('keeps every learner\'s line of the scouting run a translation, with every pack deployed', function () {
    $native = ['text_native', 'explanation_native', 'options_native', 'description_native', 'title_native', 'role_native', 'hint_native', 'native', 'translation_native'];
    $lines = [];
    foreach (['answers', 'final'] as $dir) {
        foreach (glob(dirname(__DIR__, 4)."/docs/research/lang-1/{$dir}/*.json") ?: [] as $file) {
            $learner = explode('-', basename($file, '.json'))[0];
            $day = json_decode((string) file_get_contents($file), true);
            $walk = static function (mixed $node, string $key) use (&$walk, &$lines, $learner, $native): void {
                if (is_array($node)) {
                    foreach ($node as $inner => $value) {
                        $walk($value, is_string($inner) ? $inner : $key);
                    }
                } elseif (is_string($node) && trim($node) !== '' && in_array($key, $native, true)) {
                    $lines[$learner][trim($node)] = true;
                }
            };
            $walk($day, '');
        }
    }
    $refused = [];
    foreach ($lines as $learner => $said) {
        $pack = lessonPacks()->for($learner);
        foreach (array_keys($said) as $line) {
            if (ReplyNative::missing('—', (string) $line, $pack)) {
                $refused[] = "{$learner}: {$line}";
            }
        }
    }

    expect(array_keys($lines))->toEqualCanonicalizing(LanguageRoles::planNatives())
        ->and(array_sum(array_map('count', $lines)))->toBeGreaterThan(1300)
        ->and($refused)->toBe([]);
});

// Canon (наряд BACK-TAILS-2 §9 + LANG-1): the role's neutral move is the target's `neutral_reply` said with the learner's
// `neutral_reply` as its translation — and it goes out through the guard like any other answer
// (ConversationMoves::translated()). For every pair of the plan the learner's line is a translation of the target's.
// CATCHES a neutral line blanked in the talk: a learner's pack copying the target's words, or writing a line that reads as
// a neighbour's language (a Latin learner's line of English-list words, a Belarusian one of Russian-list words).
it('keeps the learner\'s neutral reply a translation of the target\'s, for every pair of the plan', function () {
    $packs = lessonPacks();
    $wrong = [];
    foreach (LanguageRoles::planNatives() as $native) {
        foreach (LanguageRoles::planTargets() as $target) {
            if ($native === $target) {
                continue;
            }
            $said = $packs->for($target)->neutralReply();
            $translation = $packs->for($native)->neutralReply();
            if ($said === null || $translation === null || ReplyNative::missing($said, $translation, $packs->for($native))) {
                $wrong[] = "{$native} → {$target}: «{$said}» / «{$translation}»";
            }
        }
    }

    expect($wrong)->toBe([]);
});

// Canon (pack-keys §7, «пропусков нет на каждой стороне, которой язык бывает»): every pair the plan reads a language in
// finds every key its checks ask for — a taught language as the target of a Russian learner (ru → T), a learner's language
// with English taught (N → en). The whole pair is asked, so the other side (ru, en) is held too. The fake lesson is
// English-Russian: its findings mean nothing for another pair, the skips — which check does not run for want of a key —
// do not depend on the text. CATCHES a key of any pack left out or null (`lang.pack_missing` on every day of the pair).
it('leaves no check of a plan pair without its key', function (string $native, string $target) {
    $context = lessonContext($native, $target);
    $request = new LessonRequest('x', 'x', 'English', 'Russian', PlanLevel::Beginner, null, 8, 8, FakePlanModel::roles(), new EarlierDays);
    (new LessonValidator)->run((new LessonParser)->parse(FakePlanModel::lessonPayload($request)), $context);

    expect(array_map(static fn (PackSkip $skip): array => $skip->toArray(), $context->skips->all()))->toBe([]);
})->with(static function (): Generator {
    foreach (LanguageRoles::planTargets() as $target) {
        yield "ru → {$target}, the target side of {$target}" => ['ru', $target];
    }
    foreach (LanguageRoles::planNatives() as $native) {
        yield "{$native} → en, the learner's side of {$native}" => [$native, 'en'];
    }
});
