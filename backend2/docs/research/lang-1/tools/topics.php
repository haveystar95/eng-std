<?php

declare(strict_types=1);

/**
 * LANG-1 · THE GOAL OF THE RUN, PER NATIVE LANGUAGE: one topic for every pair — «запись к врачу» (booking a doctor's
 * appointment), level `beginner` — written in the learner's OWN language, since the goal is the learner's words. Every
 * ru→X pair gets the same Russian goal: the target is the only thing that changes between them (a controlled comparison).
 *
 * Read by `live.php` (the plan's goal) and by `export.php` (the goal line of a day).
 *
 * @return array<string, string> native language code → the goal in that language
 */
return [
    'ru' => 'Записываюсь к врачу: третий день болит горло и температура. Нужно выбрать удобное время и объяснить, что меня беспокоит',
    'uk' => 'Записуюся до лікаря: третій день болить горло і температура. Треба вибрати зручний час і пояснити, що мене турбує',
    'be' => 'Запісваюся да лекара: трэці дзень баліць горла і тэмпература. Трэба выбраць зручны час і растлумачыць, што мяне турбуе',
    'pl' => 'Zapisuję się do lekarza: od trzech dni boli mnie gardło i mam gorączkę. Muszę wybrać dogodny termin i wyjaśnić, co mi dolega',
    'ro' => 'Mă programez la medic: de trei zile mă doare gâtul și am febră. Trebuie să aleg o oră potrivită și să explic ce mă supără',
    'es' => 'Pido cita con el médico: llevo tres días con dolor de garganta y fiebre. Tengo que elegir una hora que me venga bien y explicar qué me pasa',
    'it' => 'Prendo un appuntamento dal medico: da tre giorni ho mal di gola e la febbre. Devo scegliere un orario comodo e spiegare cosa ho',
    'de' => 'Ich mache einen Termin beim Arzt: Seit drei Tagen habe ich Halsschmerzen und Fieber. Ich muss einen passenden Termin wählen und erklären, was mir fehlt',
    'fr' => "Je prends rendez-vous chez le médecin : j'ai mal à la gorge et de la fièvre depuis trois jours. Je dois choisir un horaire qui me convient et expliquer ce qui ne va pas",
];
