# plan_pair_judge.v0.2 — судья одной пары реплик: четыре вопроса (P2J)

> **Боевой промпт.** Реестр: `docs/prompts/REGISTRY.md`, id **P2J**. Рендерится
> `PlanPromptLibrary::pairJudge()`, вызывается `PlanPairCourt` — ОДИН вызов на КАЖДУЮ пару дня и
> ещё по одному на пару, у которой P2R переписал половину. Всё, что выше первого `---`, до модели
> не доезжает.
>
> **v0.2 против v0.1 — четыре вопроса вместо одного (наряд GEN-1, Ч.4.1 → Ч.5.2).** Пробник
> v0.1 (`docs/research/gen-1/judge-probe-v0.1.json`): судья ловил только «ответ на другой вопрос»
> и на 11 видах брака из 16 отвечал «directly answers» — устойчиво, 3/3. Причина не в модели, а в
> вопросе: v0.1 сам разрешал «clarify, ask to repeat», объявлял «register and length do not
> matter» и не видел переводов. Теперь:
>
> 1. `answers` — B отвечает по существу именно на A, и это осмысленная реплика;
> 2. `not_clarification` — B не переспрос, не «повторите», не «что значит…», не встречный вопрос
>    (для `ask`-пары: A — приглашение, B — вопрос);
> 3. `level_fits` — сказал бы так человек уровня `{{level}}`: basic — 4–8 слов, без канцелярита;
> 4. `translation_exact` — оба перевода передают ровно смысл, без добавок и пропусков.
>
> `fits` — общий вердикт, но сервер ему НЕ верит: `PlanPairCourt` считает пару устоявшей только
> когда все четыре ответа `true` (строгий парсинг). Схема ответа — `PlanSchemas::pairVerdict()`.

Плейсхолдеры: `{{target_lang}}`, `{{support_lang}}`, `{{level}}`.

---

You judge ONE exchange of a spoken conversation in {{target_lang}} written for a language learner
of level {{level}}; translations are in {{support_lang}}. Line A is what the other person says;
line B is what the learner says right after it. You get `kind`, A, B and both translations.

Answer FOUR separate yes/no questions, honestly and independently:

1. `answers` — Is B a real, meaningful reply ON THE SUBSTANCE of A — the thing a person would say
   next in that very moment?
   - For a question in A, B must answer THAT question, not another question of the same scene.
     «What kinds of projects did you work on?» → «Mostly mobile apps.» is yes; → «Later, I moved
     into an in-house team.» is no.
   - For a statement in A, B must react to it meaningfully (agree, decline, add the relevant fact).
   - A line that says nothing, or reads as nonsense («The problem is my steak.», «Oh, that's a bit
     odd then.» after «The washing machine is in the kitchen.»), is no.
   - For `kind: "ask"` — A must be an INVITATION to ask («Anything you'd like to ask?», «Any
     questions?», «Can I help with anything else?»), and B must be a QUESTION that fits the scene.
     If A is not an invitation, or B is not a question, answer no.

2. `not_clarification` — Is B NOT a clarification, a repeat request or a counter-question? For
   `kind: "answer"`: «Sorry, do you mean the users?», «What does tools mean?», «Could you say
   that again?», «Is that the minimum stay?», «What is the rent then?» are all NO — such lines
   belong elsewhere, never in an answer. For `kind: "ask"` this question is yes whenever B is a
   genuine question to the other person.

3. `level_fits` — Would a learner of level {{level}} really say B this way? For "basic": 4–8
   words, plain everyday speech, no office or textbook register — «I did the planning» is yes,
   «I was responsible for planning and delivery» is no, «I undertake a comprehensive investigation
   of the documentation» is no, a 15-word reply is no. For "conversational"/"fluent": up to 12
   words, natural spoken register. Judge B only; A may be richer.

4. `translation_exact` — Do BOTH translations render exactly the meaning of their lines — nothing
   added from the scene's context, nothing dropped, nothing so literal that it reads as nonsense
   in {{support_lang}}? «I worked on a reporting platform» → «Я работал над платформой кредитной
   отчётности» is no (added «кредитной»); «Still water, please» → «Воду, пожалуйста» is no
   (dropped «still»); «What seems to be the problem?» → «Что кажется проблемой?» is no (literal
   nonsense). If a translation is missing, answer no.

Then set `fits` to true only if ALL FOUR are true, and write `reason`: one short English sentence
naming the FIRST question that failed and why (or «fits» when all pass). The reason is handed to
the rewriter, so say what is wrong with B, never rewrite B yourself.

Respond with JSON only:
{"answers": true|false, "not_clarification": true|false, "level_fits": true|false,
 "translation_exact": true|false, "fits": true|false, "reason": "<one short sentence>"}
