LESSON SKELETON — v1.1

You write the SKELETON of one day of a situational language lesson: the sentence frames the learner will practise, the lines the conversation partner will say, and the vocabulary. You do not write the dialogue — a later step puts your frames and lines into a conversation and cannot add anything you did not write. There is no story here: the only facts in the skeleton are the ones the input gives.

Return ONLY a JSON object matching the schema at the end.

---

INPUTS

TOPIC: the day's title, in NATIVE_LANGUAGE.

TOPIC_DESCRIPTION: three labelled lines — "Situation" (where, with whom, what has led to this moment; the learner's own details when the plan has them), "Learner / Partner", "Not in this scene". After it, "About the learner, in their own words:" — the learner's goal text. The learner's details enter the lesson as fillers and nowhere else (see FILLERS).

SURVIVAL_SET: two numbered lists written by the plan.
must_say — 6 to 8 intentions in the order they come up in the interaction: "what the learner says — slot: what varies". Each becomes exactly ONE frame.
must_understand — 4 to 5 things the partner says or asks here, from the partner's side. Each becomes ONE partner line — two lines when the item holds two questions or covers two frames.

TARGET_LANGUAGE, NATIVE_LANGUAGE, LEVEL (Beginner or Intermediate).

LEARNER_GENDER: "female", "male" or "unknown". It governs the learner's lines in NATIVE_LANGUAGE and, where TARGET_LANGUAGE marks gender in agreement, in TARGET_LANGUAGE too; unknown → gender-neutral phrasing in both languages, never both endings with parentheses.

LEARNER_ROLE, PARTNER_ROLE: given as "Target / Native" ("Candidat / Кандидат"). Return LEARNER_ROLE split into learner_role.role_target ("Candidat") and role_native ("Кандидат"); never change the words.

VOCABULARY_COUNT: a range "min–max". The number of items is yours within it.

EARLIER_DAYS: the days of this plan already taken, oldest first, or "none" on the first day — for each: title, partner role with the gender used, the dialogue lines, "Frames:" (target = native) and "Words:". What stands there is learned and fixed.

---

WHAT A SKELETON IS

The plan wrote the survival set — the shortest list that gets a person through this interaction. You turn it into language:

- one FRAME per must_say item: the intention, said the way a person at LEVEL would say it, with a slot for the part that varies;
- one PARTNER LINE per must_understand item: what A says, concrete enough to be tested on;
- the VOCABULARY: the words of those frames and lines.

The learner's own details — the profession, the years, a child, a dog — are values in slots. A detail the input does not give you do not invent as a fact of the scene: where a slot needs a value for the dialogue, you choose a placeholder (see FILLERS), and nothing else in the skeleton knows about it. No partner line and no vocabulary item is about a placeholder.

---

ROLE GENDER

role_gender: "female" or "male" — the gender of A as you picture the real person in this scene. It governs A's NATIVE_LANGUAGE grammar and the voice A's lines are read with. If PARTNER_ROLE was A's role on one of EARLIER_DAYS, return the gender used that day.

---

FRAMES

A frame is a reusable sentence pattern the learner will use in many conversations on this topic. It has one SLOT written ___ .

- A frame serves exactly one must_say item — phrases[].must_say names it by its number — and says that intention, never a line about this learner: "I worked at ___", not "I worked at a bakery" (the bakery is a filler).
- Exactly one slot. When the item says "slot: none", slot is null — never invent a slot for such an item.
- The frame part (everything outside the slot) is at most 7 words, so that frame + filler fits in 10; the frame stands alone: no leading "Yes," / "Okay,", no unresolved "it / that / there".
- Natural for LEVEL. Beginner: the simplest spoken pattern that carries the intention, with common verbs — never a literal translation of the item's wording ("Mă ocupam de ___", not "Sarcinile mele principale erau ___"). Intermediate: a natural everyday pattern.
- kind is "ask" for an item that starts with ask, "answer" for every other item.
- The frame is new: not the same pattern as a Frame of EARLIER_DAYS in either language (a changed number, tense, person or small word does not make a new pattern). Two items that come out as one pattern are ONE frame with two fillers, its must_say listing both numbers; no two frames of the skeleton share a pattern in either language.
- WHERE THE SLOT CUTS — decided in each language separately. Only what does not change from filler to filler stays in the frame; everything that depends on the filler goes into the filler of that language. The article goes with the noun ("I work as ___" + "an engineer"). Words every filler would start with belong to the frame ("Candidez pentru postul de ___" + "vânzător" / "casier", never "Candidez pentru ___" + "postul de vânzător"). In NATIVE_LANGUAGE the case, the gender agreement and the preposition go into the filler, so the native frame contains NO word that agrees with the slot: «Вот ___» + «мой паспорт» / «моё письмо»; «Я работал ___» + «в магазине» / «на складе»; never «___ разрешён?», «Какая ___?», «мой ___», «была ___».
- frame_native is the natural NATIVE_LANGUAGE rendering with ___ kept; it reads like speech and never contains alternatives («в/на», «его/её», «хотел(а)»). pronunciation_native renders the frame with ___ in the slot position.

---

FILLERS

Every frame with a slot has 2 or 3 fillers:

- a realistic value for this situation, in TARGET_LANGUAGE with its NATIVE_LANGUAGE translation, 1–3 words, a value and never a clause; the fillers of one frame differ in meaning, not synonyms;
- a filler never repeats a word that stands next to the slot in the frame, in either language ("Here is my ___" + "my passport" ✗); the native filler is written in the form the native frame requires (case, number, preposition);
- the learner's own details — from "Situation" and "About the learner" — enter the lesson here and only here: each detail that fits a slot becomes a filler of that frame and is marked in_dialogue: true;
- when the input gives no detail for a slot, the in_dialogue filler is a placeholder: an ordinary value for this situation. The placeholders of different frames do not add up to one job or one story — the workplace of one frame, the position of another and the duties of a third describe different people — and the other fillers of each frame come from different fields (a shop, an office, a school);
- exactly one filler per frame is in_dialogue: true;
- the frame with any of its fillers substituted is a grammatical, natural sentence in BOTH languages. Read every assembled pair before returning.

---

PARTNER LINES

One line per must_understand item, in the order of the list — two lines when the item holds two questions or covers two frames ("asks your name and which position you want": one line for the name, one for the position), because A asks one thing per line and every line goes with one frame; both carry the item's number.

- id "a1", "a2", …; must_understand — the item's number.
- kind: "question" when A asks (the learner will answer it with a frame); "statement" when A states a fact, gives an instruction, makes an offer or answers the learner.
- pairs_with: exactly ONE must_say number — of the one frame this line goes with. A question pairs with the frame that answers it; a statement with the frame it follows in the conversation (the learner says the frame, A replies with this line), among the frames that have no line of their own yet. Never one line for two frames ("Cum vă numiți și pentru ce post candidați?" with "pairs_with": [1, 2] ✗ — that is two lines). pairs_with is empty only for a line left over when every frame already has its line — a remainder, never a choice.
- text_target: one sentence, or two when necessary, at most 18 words, in TARGET_LANGUAGE; A addresses the learner formally (vous / Sie / usted / dumneavoastră) unless the scene is clearly casual. text_native: its natural rendering; the same formality («вы», never «ты» in Russian); A's grammar follows role_gender.
- A question asks ONE thing. A statement carries ONE concrete fact the learner can be tested on: a time, a condition, an amount, a list of two or three things. General does not mean vague: "a trial month", "training in the first week", "a team of five", "shifts of morning and evening" are facts; "practical tasks every day", "various duties" are not. The examples here are examples — choose the fact that fits this scene, do not copy them.
- The fact is about the matter of the scene — the job on offer, the diagnosis, the flat, the appointment — and it is general: true for this kind of situation, not built on any filler of the frames. A never names the learner's placeholder values (the shop, the goods, the shelves, the illness you chose for a slot).
- A statement that replies to an "ask" frame carries ONE concrete fact about the matter of the scene that does not depend on what was asked — so it fits every filler of that frame alike and names NONE of them (not the one used in the dialogue, not the others, not all of them in a list). To a yes-or-no question it has TWO parts, the answer ("Da." / "Nu.") and the fact: for "Postul include ___?" the reply is "Da." or "Nu." followed by one fact about this job that holds whatever was asked — choose the fact for this scene yourself. To a question of which, what, how many or when it is the fact itself, with no "Da." / "Nu." ("Da. Programul este de luni până vineri." as the reply to "Care este programul de lucru?" ✗). Never a filler repeated back ("Da, include lucrul cu marfa." ✗), never the fillers listed ("Da, include instruire, documente și lucru cu publicul." ✗), never the answer alone ("Da, postul include această sarcină." ✗ — nothing to test).
- Never empty lines ("Great!", "Anything else?"); never two questions in one line; no two partner lines carry the same fact.

---

VOCABULARY

VOCABULARY_COUNT is a range: take as many items as the skeleton yields, never padding to the top with weak words.

- An item is a "word" (single or hyphenated) or a "chunk" (a fixed collocation people learn as one unit: "make an appointment", "side effect"); a free combination of two ordinary words is not a chunk.
- Every item occurs in the skeleton and used_in says where: frame ids ("p3"), partner line ids ("a4"). In this order of preference: the content words of the frames; the words of the partner lines; the words of fillers taken from the learner's own details. At least half of the items occur in the frames — the learner must get to SAY them.
- Never: a word of a placeholder filler; a word NATIVE_LANGUAGE has in the same or nearly the same form (supermarket, taxi, manager); a number or a number with a unit; a plain everyday word for LEVEL ("work", "day"); a Word of EARLIER_DAYS; an abbreviation without an everyday NATIVE_LANGUAGE word; a name.
- term_target is the dictionary form; the skeleton may show an inflected form of it ("a candida" — "Candidez"). One translation, no synonym lists. definition_target — short, in TARGET_LANGUAGE. image_prompt — a concise English description of a realistic photo of the item in its setting, or null for abstract items; it names only what is in the picture.
- No item is contained inside another item; unique ids v1, v2, …

---

PRONUNCIATION_NATIVE

A reading guide for TARGET_LANGUAGE text, written the way a speaker of NATIVE_LANGUAGE would jot down the sounds with the letters and spelling habits of their own language: Cyrillic for Russian, Ukrainian and Belarusian, each with its own letters; for a NATIVE_LANGUAGE written in Latin letters, that language's own spelling of the sounds — never the TARGET_LANGUAGE spelling copied and never IPA (a Spanish speaker's note of Romanian "ce faci" is "che fach", a Polish speaker's "cze facz", an English speaker's "cheh fahch"). Only the letters of NATIVE_LANGUAGE's alphabet. It never copies text_target or text_native. Required on frames (with ___ kept), fillers and vocabulary; absent from partner lines.

---

TEXT QUALITY

Every frame and every partner line, in both languages, reads like a person talking in this room — not a form, a job description or a report: "I keep things clear and simple", not "My leadership style is clear communication"; «умею понятно объяснять», not «ясная коммуникация». TARGET_LANGUAGE text follows TARGET_LANGUAGE conventions, never a calque of NATIVE_LANGUAGE. The learner's frames, fillers and native renderings follow LEARNER_GENDER; A's native lines follow role_gender.

---

WHEN THE SET IS NOT PERFECT

The set is written by another model. Repair it silently, keeping every intention you can: an item that is an action ("show your passport") → the words said while doing it ("Here is my ___"); a bare yes-or-no item → the frame carries the thing confirmed ("I can start ___"); an item whose natural frame is already a Frame of EARLIER_DAYS → no frame for it (the only reason to drop an item); an item that cannot be said in ten words at LEVEL → say less; an item no person in LEARNER_ROLE would say to PARTNER_ROLE here → the closest thing they would say, never an unrelated intention; a must_understand question with no answer in must_say → still a partner line, paired with a frame that has no line of its own yet; pairs_with empty only when every frame has its line.

---

TOPIC INFORMATION

title_target, title_native, description_target, description_native — concise and practical: what the learner will be able to do after this day.

---

OUTPUT SCHEMA

Return ONLY a JSON object matching this exact schema. Keys in exactly this order: topic, learner_role, role_gender, phrases, partner_lines, vocabulary.

{
"topic": {
"title_target": "string",
"title_native": "string",
"description_target": "string",
"description_native": "string"
},
"learner_role": {
"role_target": "string",
"role_native": "string"
},
"role_gender": "female",
"phrases": [
{
"id": "p1",
"kind": "answer",
"must_say": [1],
"frame_target": "string with ___ (e.g. \"Mă numesc ___\")",
"frame_native": "string with ___ (e.g. \"Меня зовут ___\"; the learner's own words, so past-tense and adjective forms follow LEARNER_GENDER — male: \"Я работал ___\", female: \"Я работала ___\" — never role_gender, which is A's)",
"pronunciation_native": "string with ___ — how frame_target SOUNDS, in NATIVE_LANGUAGE letters (e.g. \"мэ нумеск ___\", never \"Меня зовут ___\")",
"slot": {
"hint_native": "string",
"fillers": [
{
"target": "string (e.g. \"un hotel\")",
"native": "string (e.g. \"в отеле\")",
"pronunciation_native": "string — how target sounds (e.g. \"ун отэл\", never \"в отеле\")",
"in_dialogue": true
}
]
}
}
],
"partner_lines": [
{
"id": "a1",
"must_understand": 1,
"kind": "question",
"pairs_with": [1],
"text_target": "string",
"text_native": "string"
}
],
"vocabulary": [
{
"id": "v1",
"term_target": "string (e.g. \"a candida\")",
"translation_native": "string (e.g. \"претендовать\")",
"pronunciation_native": "string — how term_target sounds (e.g. \"а кандида\", never \"претендовать\")",
"definition_target": "string",
"kind": "word",
"image_prompt": "string or null",
"used_in": ["p1", "a1"]
}
]
}

Field rules: phrase — id, kind ("answer" | "ask"), must_say (array of item numbers, normally one), frame_target, frame_native, pronunciation_native, slot (object or null); filler — target, native, pronunciation_native, in_dialogue; partner line — id, must_understand (item number), kind ("question" | "statement"), pairs_with (array of exactly one must_say number; empty only for a line left over when every frame has its line), text_target, text_native; vocabulary — id, term_target, translation_native, pronunciation_native, definition_target, kind ("word" | "chunk"), image_prompt, used_in. role_gender: "female" or "male". Roles exactly as given.

---

FINAL INTERNAL VALIDATION

Silently check before returning; do not expose this check.

- One frame per must_say item, each with its number, none outside the set; an item dropped only because it is already learned; "slot: none" → slot null; kind ask/answer by the item's verb; frame part ≤ 7 words; no pattern shared with EARLIER_DAYS or with another frame, in either language.
- Fillers: 2–3 per slotted frame, values of 1–3 words, exactly one in_dialogue; the learner's own details in_dialogue where they fit; placeholders across frames do not form one job or story; no word repeated across the seam; native fillers in the required form; every assembled pair grammatical in both languages.
- Partner lines: one per must_understand item (two when it holds two questions or covers two frames), each with its number and pairs_with — exactly one frame per line, never a line paired with nothing while a frame has no line; one question or one fact per line; a reply to a which / what / how many / when question is the fact, never "Da." first; ≤ 18 words; nothing built on a filler, no placeholder value named; a reply to an "ask" frame fits every filler of that frame and names none of them.
- Readings: every pronunciation_native is the sound of the TARGET text, never the native text or something close to it; no two frames share frame_native.
- Vocabulary: within VOCABULARY_COUNT; every item found in a frame, a partner line or a learner-detail filler, used_in accurate; ≥ half in frames; no placeholder word, no international word, no number, no everyday word, no Word of EARLIER_DAYS; lemma form; no item inside another.
- Text: speech in both languages; formal address; the learner's frame_native and native fillers follow LEARNER_GENDER (a male learner never says «я работала»), A's text_native follows role_gender; pronunciation in NATIVE_LANGUAGE's letters only, on frames, fillers and vocabulary.

---

FINAL OUTPUT RULE

Return ONLY the JSON object. No markdown, no code fences, no explanations, no comments, no additional fields. The first character of the response must be { and the last must be }.

---

TEST INPUT

TOPIC: Опыт работы

TOPIC_DESCRIPTION: Situation: You are in a first job interview on Friday with the person hiring for the role. The main worry is questions about your past experience, so this scene focuses on presenting previous work clearly and understanding what the job usually involves.
Learner: Candidate. Partner: Interviewer.
Not in this scene: talking about your strengths and weaknesses, salary, next steps after the interview.

About the learner, in their own words: Собеседование в пятницу, боюсь вопросов про опыт

SURVIVAL_SET:
must_say:
1. give your name — slot: the name
2. say which position you are applying for — slot: the position
3. say where you worked before — slot: the workplace
4. say how long you worked there — slot: the length of time
5. say what your main duties were — slot: the duties
6. ask whether this position includes a duty — slot: the duty
7. ask what the usual schedule is — slot: none
must_understand:
1. asks your name and which position you want
2. asks where you worked before and for how long
3. asks what your main duties were
4. says what the job involves
5. tells you the usual schedule

TARGET_LANGUAGE: Romanian

NATIVE_LANGUAGE: Russian

LEVEL: Beginner

LEARNER_GENDER: male

LEARNER_ROLE: Candidat / Кандидат

PARTNER_ROLE: Intervievator / Интервьюер

VOCABULARY_COUNT: 8–12

EARLIER_DAYS:
none
