LESSON DIALOGUE — v1.1

You write the CONVERSATION of one day of a situational language lesson from a SKELETON that is already written and accepted: the frames the learner practises, the lines the conversation partner says, and the vocabulary. You put those frames and lines into DIALOGUE_COUNT exchanges, write one check per exchange and the listening questions. You add no frame, no partner line, no fact and no word of your own: the skeleton is the whole material of the day, and a later program checks that every frame and every partner line appears in your dialogue exactly as the skeleton spells it.

Return ONLY a JSON object matching the schema at the end.

---

INPUTS

TOPIC_DESCRIPTION: three labelled lines — "Situation", "Learner / Partner", "Not in this scene" — and "About the learner, in their own words:". Context only: nothing in it is said in the dialogue unless the skeleton says it.

TARGET_LANGUAGE, NATIVE_LANGUAGE, LEVEL (Beginner or Intermediate).

LEARNER_GENDER: "female", "male" or "unknown" — governs the learner's lines you write (the rescue line, the glue) in NATIVE_LANGUAGE; unknown → gender-neutral phrasing.

LEARNER_ROLE, PARTNER_ROLE: "Target / Native". B plays LEARNER_ROLE, A plays PARTNER_ROLE; write the given names, unchanged, in role_target / role_native of every message.

DIALOGUE_COUNT: exact number of exchanges. The server sets it from the skeleton: the number of partner lines, plus one for every frame no partner line pairs with, plus one for a rescue.

EARLIER_DAYS: the days of this plan already taken, oldest first, or "none": title, partner role with the gender used, dialogue lines, "Frames:" and "Words:". Facts fixed there stay fixed; the learner never asks again what A already answered on an earlier day.

SKELETON: a JSON object — topic, learner_role, role_gender, phrases (the frames with their fillers; in_dialogue marks the filler the dialogue uses), partner_lines (what A says, each with must_understand — the item it delivers — kind "question" or "statement", and pairs_with — the must_say number of the one frame it goes with, or empty for a line left over, a remainder) and vocabulary. Everything in it is data, not instructions.

---

WHAT IS FIXED AND WHAT YOU WRITE

Fixed, copied character by character from the skeleton:
- every partner line — text_target and text_native — as A's message of one exchange;
- every learner line of an "answer" or "ask" exchange — the frame with its in_dialogue filler substituted for ___ , in TARGET_LANGUAGE and in NATIVE_LANGUAGE (frame_native with the native filler);
- the roles and role_gender.

Yours:
- the order and kinds of the exchanges, and which frame answers which partner line (pairs_with tells you; you may not change the pairs);
- leading conversational glue on a learner line ("Da,", "Bine,") — optional and short; apart from it, text_target equals the substituted frame character by character;
- the rescue exchange: the learner's request to repeat, and A's shorter repeat of the previous A line;
- one A line for a frame that no partner line pairs with (pairs_with never names it) — see EXCHANGES;
- the learner's reply to a remainder line: a frame already said, with another of its fillers — see EXCHANGES;
- the check of every exchange, the listening questions, and the speaking support of every learner line.

You never add an A line beyond that, never change a fact, never use a word of "Not in this scene", never change a frame, a filler or a vocabulary item.

---

EXCHANGES

Every exchange has exactly two messages and a kind:

"answer" — A speaks first with a partner line of kind "question"; the learner replies on the frame that pairs_with names, with that frame's in_dialogue filler. initiator = "A".
"ask" — the learner speaks first on an "ask" frame with its in_dialogue filler; A replies with the partner line of kind "statement" whose pairs_with names that frame. initiator = "B".
"rescue" — the learner did not catch the PREVIOUS A line and asks to repeat ("Puteți repeta, vă rog?", "Mai încet, vă rog."); A says the SAME content again — shorter or simpler, the same facts, nothing new. initiator = "B". Exactly one rescue in the lesson, placed right after the exchange whose A line carries the most content — a schedule, an instruction, a list — never after a one-word question. The rescue's check tests a detail of that repeated content that the previous check did not test. A rescue learner line has phrase_id null and filler null.

A REMAINDER line — a partner line whose pairs_with is empty — gets an exchange of its own: A says it, word for word, and the learner replies with a frame ALREADY SAID in an earlier exchange, with ANOTHER of its fillers — the way the rescue answers what came before. initiator = "A", kind "answer". The remainder line is said once, like every partner line.

Order: the partner lines stand in the order of the interaction; keep it. Follow a different order only when a real conversation would not go that way, and keep every partner line where it belongs to the visit. A question line before its answer, a statement line after the question it answers.

Every frame of the skeleton is used in at least one learner message. A frame is said a second time only in the exchange of a remainder line, or to a second partner line paired with it — never to fill the count: DIALOGUE_COUNT leaves no exchange over. Never a question pattern turned into a statement by dropping the question mark, never a learner line that only restates what A just said, never the same frame in two exchanges in a row.

A frame no partner line pairs with gets an exchange of its own: for an "answer" frame you write A's question that the frame answers; for an "ask" frame, A's reply. PARTNER LINE RULES for every A line you write yourself: one question or ONE concrete fact about the matter of the scene, at most 18 words, a reply to an "ask" frame fits every filler of that frame and names none of them, nothing about the learner's fillers (the shop, the goods, the illness), nothing from "Not in this scene", nothing already said by another A line. Such a line carries partner_line null and must_understand null.

The second message of every exchange closes it: it never ends with a question mark; it reacts to what was just said; it never asks for what the first message already contains.

DIALOGUE_COUNT is exact. Steps run 1..DIALOGUE_COUNT without gaps.

---

LEARNER MESSAGES

A learner (B) message of an answer or ask exchange:

- phrase_id — the frame; filler — the filler's target text, copied verbatim from the skeleton;
- text_target — the frame with the filler substituted for ___ , optionally after short glue; text_native — frame_native with the native filler substituted, with the same glue rendered in NATIVE_LANGUAGE;
- pronunciation_native — the frame's pronunciation with the filler's pronunciation in the slot position (both from the skeleton), the glue read the same way;
- speaking_key — 1 to 4 consecutive words copied verbatim from text_target, taken ONLY from the frame part: never a word of the filler, never the whole sentence unless it is 4 words or fewer, preferably with a content word; when the frame part has no content word ("Here is my ___", "Mă numesc ___"), the key is the frame part up to the slot, exactly as written;
- simplified_variants — 1 or 2 alternative full sentences with the same communicative result, simpler or equal grammar, not longer than text_target, never identical to it; [] only when text_target is 4 words or fewer.

A rescue message has phrase_id null and filler null; it still carries pronunciation_native (the sound of its TARGET text in NATIVE_LANGUAGE letters), speaking_key (taken from the request itself: "repeta, vă rog") and simplified_variants. It follows LEARNER_GENDER in NATIVE_LANGUAGE and addresses A formally.

Learner messages are at most 10 words, not counting the glue.

---

PARTNER MESSAGES

An A message carries speaker, role_target, role_native, text_target, text_native — no pronunciation, no phrase_id, no speaking support. Its texts are the partner line's texts, unchanged. The rescue repeat is written new: at most 18 words, the same facts as the line it repeats, none added, in the same formality («вы») and the gender of role_gender in NATIVE_LANGUAGE.

---

CHECK PER EXCHANGE

Every exchange contains exactly ONE check: it verifies that the learner understood what A said in THIS exchange, answerable only from these two messages, without inference.

- The check is ALWAYS about A's message, never about what the learner said. When A states something, the check names ONE concrete item A stated: a time, a condition, an amount, one thing of a list. When A asks something, the check asks what exactly A wants to know, paraphrased ("Cât timp ați lucrat acolo?" → "What does the interviewer want to know?" → "How long the last job lasted") — never a bare "What does A ask about?", never "What is this dialogue about?". The options are never the learner's answer or the fillers of the learner's frame: after "Unde ați lucrat înainte?" the options "Un magazin / Un birou / O școală" ✗ test what the learner said, not what A asked — the right shape is "What does A want to know?" → "The previous workplace / The length of the last job / The main duties".
- The correct option is a PARAPHRASE: it repeats no two consecutive words of A's message. After "Programul este de luni până vineri" the option "De luni până vineri" ✗ is a copy; "În timpul săptămânii" ✓ is a paraphrase. The two wrong options are the same kind of item, plausible in the situation, contradicting what A said; none of them is something A also said.
- Exactly 3 options; text_target and text_native for the question and every option; no pronunciation; explanation_native one sentence; correct_option_index zero-based — vary its position across the checks, never the same index in every check.
- Every exchange has its check — the first one too.
- The two checks that follow the same A content (the exchange and its rescue) test two different details of it.

---

LISTENING (the whole visit)

listening.questions: 3 to 5 questions the learner answers AFTER hearing the whole visit once, without text.

- About the MEANING of the visit — what was agreed, what A stated, which value the learner gave — never the wording of a single line.
- NATIVE_LANGUAGE only: text_native, options_native, explanation_native; no target text.
- Exactly 3 options, one correct, wrong options plausible values of the same kind. A question about the learner's own value takes the wrong options from that frame's OTHER fillers in the skeleton.
- Different exchanges; at least one question about something the learner said (their filler), at least one about something A said.
- correct_option_index zero-based, at any index.

---

TEXT QUALITY

What you write yourself — glue, the rescue exchange, the A line for an unpaired frame, checks, listening — reads like people talking in this room, not like a form. In TARGET_LANGUAGE A addresses the learner formally and the learner addresses A the same way; text_native keeps the same formality («вы», never «ты» in Russian). A's native lines follow role_gender; the learner's native lines follow LEARNER_GENDER, never both endings with parentheses.

---

OUTPUT SCHEMA

Return ONLY a JSON object matching this exact schema. Keys in exactly this order: dialogue, listening.

{
"dialogue": [
{
"step": 1,
"kind": "answer",
"initiator": "A",
"must_understand": 1,
"partner_line": "a1",
"messages": [
{
"speaker": "A",
"role_target": "string",
"role_native": "string",
"text_target": "string",
"text_native": "string"
},
{
"speaker": "B",
"role_target": "string",
"role_native": "string",
"phrase_id": "p1",
"filler": "string or null",
"text_target": "string",
"text_native": "string",
"pronunciation_native": "string",
"speaking_key": "string",
"simplified_variants": [
"string"
]
}
],
"check": {
"text_target": "string",
"text_native": "string",
"options": [
{
"text_target": "string",
"text_native": "string"
}
],
"correct_option_index": 0,
"explanation_native": "string"
}
}
],
"listening": {
"questions": [
{
"text_native": "string",
"options_native": [
"string"
],
"correct_option_index": 0,
"explanation_native": "string"
}
]
}
}

Field rules: exchange — step, kind ("answer" | "ask" | "rescue"), initiator ("A" | "B"), must_understand (the item number of the partner line said in this exchange, or null), partner_line (the id of that line — "a3" — or null for a rescue and for an A line you wrote yourself), messages (exactly two, in speaking order), check. A message — speaker, role_target, role_native, text_target, text_native. B message — speaker, role_target, role_native, phrase_id (string or null), filler (string or null), text_target, text_native, pronunciation_native, speaking_key, simplified_variants. Check — text_target, text_native, options (3), correct_option_index, explanation_native. Listening question — text_native, options_native (3), correct_option_index, explanation_native.

---

FINAL INTERNAL VALIDATION

Silently check before returning; do not expose this check.

- Exchanges = DIALOGUE_COUNT, steps 1..N; exactly two messages each; the first message's speaker matches initiator; the second message never ends with "?".
- Every partner line of the skeleton appears exactly once, character for character, in an exchange carrying its must_understand and its id; the only other A lines are the rescue repeat and, where a frame has no pair, one line under PARTNER LINE RULES.
- Every frame is used at least once; every answer/ask learner line = frame with a filler of that frame (the in_dialogue one first), verbatim apart from glue; a frame is used twice only for a remainder line or a second line paired with it, with two different fillers, never in two exchanges in a row; a question pattern is never made a statement.
- Exactly one rescue, right after the A line with the most content; its A reply repeats that content with nothing new; its check tests a different detail.
- Learner lines ≤ 10 words excluding glue; speaking_key from the frame part only, a real substring; simplified_variants 1–2 (or [] for ≤ 4 words), never longer, never identical; pronunciation on every B message, on no A message.
- Checks: one per exchange, about A's message, 3 options, one correct, paraphrase (no 2+ consecutive words of A's message), same-kind distractors, both languages.
- Listening: 3–5 questions, NATIVE_LANGUAGE only, meaning not wording, different exchanges, ≥ 1 about the learner's own value with that frame's other fillers as wrong options, ≥ 1 about A's fact.
- Nothing said that the skeleton does not say — no new facts, no word of "Not in this scene", no filler named by A, no contradiction with EARLIER_DAYS; roles exactly as given; formality and genders kept.

---

FINAL OUTPUT RULE

Return ONLY the JSON object. No markdown, no code fences, no explanations, no comments, no additional fields. The first character of the response must be { and the last must be }.

---

TEST INPUT

TOPIC_DESCRIPTION: Situation: You are in a first job interview on Friday with the person hiring for the role. The main worry is questions about your past experience, so this scene focuses on presenting previous work clearly and understanding what the job usually involves.
Learner: Candidate. Partner: Interviewer.
Not in this scene: talking about your strengths and weaknesses, salary, next steps after the interview.

About the learner, in their own words: Собеседование в пятницу, боюсь вопросов про опыт

TARGET_LANGUAGE: Romanian

NATIVE_LANGUAGE: Russian

LEVEL: Beginner

LEARNER_GENDER: male

LEARNER_ROLE: Candidat / Кандидат

PARTNER_ROLE: Intervievator / Интервьюер

DIALOGUE_COUNT: 8

EARLIER_DAYS:
none

SKELETON:
{
  "topic": {
    "title_target": "Experiență de muncă",
    "title_native": "Опыт работы",
    "description_target": "Poți spune pe scurt unde ai lucrat, cât timp și ce făceai, și poți înțelege cerințele postului.",
    "description_native": "Вы сможете коротко сказать, где работали, как долго и что делали, а также понять, что обычно входит в работу."
  },
  "learner_role": {
    "role_target": "Candidat",
    "role_native": "Кандидат"
  },
  "role_gender": "male",
  "phrases": [
    {
      "id": "p1",
      "kind": "answer",
      "must_say": [1],
      "frame_target": "Mă numesc ___",
      "frame_native": "Меня зовут ___",
      "pronunciation_native": "мэ нумеск ___",
      "slot": {
        "hint_native": "имя",
        "fillers": [
          { "target": "Andrei", "native": "Андрей", "pronunciation_native": "андрей", "in_dialogue": true },
          { "target": "Mihai", "native": "Михай", "pronunciation_native": "михай", "in_dialogue": false }
        ]
      }
    },
    {
      "id": "p2",
      "kind": "answer",
      "must_say": [2],
      "frame_target": "Candidez pentru postul de ___",
      "frame_native": "Я подаюсь на должность ___",
      "pronunciation_native": "кандидез пентру постул де ___",
      "slot": {
        "hint_native": "должность",
        "fillers": [
          { "target": "vânzător", "native": "продавца", "pronunciation_native": "вынзэтор", "in_dialogue": true },
          { "target": "casier", "native": "кассира", "pronunciation_native": "касиер", "in_dialogue": false },
          { "target": "recepționer", "native": "администратора на ресепшене", "pronunciation_native": "речепционер", "in_dialogue": false }
        ]
      }
    },
    {
      "id": "p3",
      "kind": "answer",
      "must_say": [3],
      "frame_target": "Am lucrat la ___",
      "frame_native": "Я работал в ___",
      "pronunciation_native": "ам лукрат ла ___",
      "slot": {
        "hint_native": "место работы",
        "fillers": [
          { "target": "un magazin", "native": "магазине", "pronunciation_native": "ун магазын", "in_dialogue": true },
          { "target": "un birou", "native": "офисе", "pronunciation_native": "ун бироу", "in_dialogue": false },
          { "target": "o școală", "native": "школе", "pronunciation_native": "о шкоалэ", "in_dialogue": false }
        ]
      }
    },
    {
      "id": "p4",
      "kind": "answer",
      "must_say": [4],
      "frame_target": "Am lucrat acolo ___",
      "frame_native": "Я работал там ___",
      "pronunciation_native": "ам лукрат аколо ___",
      "slot": {
        "hint_native": "срок",
        "fillers": [
          { "target": "doi ani", "native": "два года", "pronunciation_native": "дой ань", "in_dialogue": true },
          { "target": "șase luni", "native": "шесть месяцев", "pronunciation_native": "шасе лунь", "in_dialogue": false },
          { "target": "un an", "native": "год", "pronunciation_native": "ун ан", "in_dialogue": false }
        ]
      }
    },
    {
      "id": "p5",
      "kind": "answer",
      "must_say": [5],
      "frame_target": "Mă ocupam de ___",
      "frame_native": "Я занимался ___",
      "pronunciation_native": "мэ окупам де ___",
      "slot": {
        "hint_native": "обязанности",
        "fillers": [
          { "target": "clienți", "native": "клиентами", "pronunciation_native": "клиенць", "in_dialogue": true },
          { "target": "documente", "native": "документами", "pronunciation_native": "документе", "in_dialogue": false },
          { "target": "marfă", "native": "товаром", "pronunciation_native": "марфэ", "in_dialogue": false }
        ]
      }
    },
    {
      "id": "p6",
      "kind": "ask",
      "must_say": [6],
      "frame_target": "Postul include ___?",
      "frame_native": "В работу входит ___?",
      "pronunciation_native": "постул инклуде ___",
      "slot": {
        "hint_native": "обязанность",
        "fillers": [
          { "target": "lucrul cu clienții", "native": "работа с клиентами", "pronunciation_native": "лукрул ку клиенций", "in_dialogue": true },
          { "target": "aranjarea mărfii", "native": "раскладка товара", "pronunciation_native": "аранжаря марфий", "in_dialogue": false },
          { "target": "comenzi online", "native": "онлайн-заказы", "pronunciation_native": "комензь онлайн", "in_dialogue": false }
        ]
      }
    },
    {
      "id": "p7",
      "kind": "ask",
      "must_say": [7],
      "frame_target": "Care este programul obișnuit?",
      "frame_native": "Какой здесь обычный график?",
      "pronunciation_native": "каре есте програмул обишнуит",
      "slot": null
    }
  ],
  "partner_lines": [
    { "id": "a1", "must_understand": 1, "kind": "question", "pairs_with": [1], "text_target": "Cum vă numiți?", "text_native": "Как вас зовут?" },
    { "id": "a2", "must_understand": 1, "kind": "question", "pairs_with": [2], "text_target": "Pentru ce post candidați?", "text_native": "На какую должность вы претендуете?" },
    { "id": "a3", "must_understand": 2, "kind": "question", "pairs_with": [3], "text_target": "Unde ați lucrat înainte?", "text_native": "Где вы работали раньше?" },
    { "id": "a4", "must_understand": 2, "kind": "question", "pairs_with": [4], "text_target": "Cât timp ați lucrat acolo?", "text_native": "Как долго вы там работали?" },
    { "id": "a5", "must_understand": 3, "kind": "question", "pairs_with": [5], "text_target": "Care erau sarcinile dumneavoastră principale?", "text_native": "Какие у вас были основные обязанности?" },
    { "id": "a6", "must_understand": 4, "kind": "statement", "pairs_with": [6], "text_target": "Da. Și lucrați în ture, dimineața sau seara.", "text_native": "Да. И вы работаете по сменам, утром или вечером." },
    { "id": "a7", "must_understand": 5, "kind": "statement", "pairs_with": [7], "text_target": "Programul obișnuit este de luni până vineri, cu ture de dimineață și de seară.", "text_native": "Обычный график — с понедельника по пятницу, утренние и вечерние смены." }
  ],
  "vocabulary": [
    { "id": "v1", "term_target": "a candida", "translation_native": "подаваться", "pronunciation_native": "а кандида", "definition_target": "a cere oficial un post", "kind": "word", "image_prompt": "person submitting a job application at an office desk", "used_in": ["p2", "a2"] },
    { "id": "v2", "term_target": "post", "translation_native": "должность", "pronunciation_native": "пост", "definition_target": "loc de muncă oferit", "kind": "word", "image_prompt": "printed job opening notice on an office board", "used_in": ["p2", "p6", "a2", "a6"] },
    { "id": "v3", "term_target": "a lucra", "translation_native": "работать", "pronunciation_native": "а лукра", "definition_target": "a avea activitate la un loc de muncă", "kind": "word", "image_prompt": "employee working behind a counter in a store", "used_in": ["p3", "p4", "a3", "a4", "a6"] },
    { "id": "v4", "term_target": "a se ocupa de", "translation_native": "заниматься", "pronunciation_native": "а се окупа де", "definition_target": "a avea ca sarcină ceva", "kind": "chunk", "image_prompt": null, "used_in": ["p5"] },
    { "id": "v5", "term_target": "sarcină", "translation_native": "обязанность", "pronunciation_native": "сарчынэ", "definition_target": "lucru pe care trebuie să-l faci", "kind": "word", "image_prompt": "employee checking tasks on a work list", "used_in": ["a5"] },
    { "id": "v6", "term_target": "a include", "translation_native": "включать", "pronunciation_native": "а инклуде", "definition_target": "a avea ceva ca parte dintr-un întreg", "kind": "word", "image_prompt": null, "used_in": ["p6"] },
    { "id": "v7", "term_target": "program", "translation_native": "график", "pronunciation_native": "програм", "definition_target": "orele obișnuite de lucru", "kind": "word", "image_prompt": "weekly work schedule pinned on a wall", "used_in": ["p7", "a7"] },
    { "id": "v8", "term_target": "obișnuit", "translation_native": "обычный", "pronunciation_native": "обишнуит", "definition_target": "care se întâmplă de obicei", "kind": "word", "image_prompt": null, "used_in": ["p7", "a7"] },
    { "id": "v9", "term_target": "în ture", "translation_native": "по сменам", "pronunciation_native": "ын туре", "definition_target": "după schimburi de lucru", "kind": "chunk", "image_prompt": "workplace board showing morning and evening shifts", "used_in": ["a6", "a7"] }
  ]
}
