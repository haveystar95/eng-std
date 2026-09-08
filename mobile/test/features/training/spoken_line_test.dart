import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/speech/speech_grading_config.dart';
import 'package:eng_std/features/training/session/spoken_line.dart';

/// ЗАЧЁТ ВСЕЙ ФРАЗЫ НА ТЕЛЕФОНЕ — наряд SPEECH-2, Ч.3. Зеркало серверного `SpokenLineTest`:
/// те же случаи, те же слова, тот же ответ. Разъехавшийся вердикт — это «Не то» на экране над
/// ответом, который журнал в ту же секунду засчитал верным.
void main() {
  const config = SpeechGradingConfig();

  // ПРАВИЛО: Ч.3.1 — фраза на экране засчитывается покрытием ≥ read_aloud (0.9).
  // ЛОВИТ: чтение вслух, засчитанное по половине текста. Текст перед глазами, и «прочитал две
  // трети» — это не прочитал; порог 0.7, унаследованный от вспоминания, здесь просто неверен.
  test('фраза на экране судится порогом чтения — с обеих его сторон', () {
    const line = 'Could you take a photo of us please';

    final whole = SpokenLine.judge(transcript: line, line: line, printed: true, config: config);
    expect(whole.credit, SpokenCredit.correct);
    expect(whole.threshold, 'read_aloud');

    final half = SpokenLine.judge(
      transcript: 'could you take photo',
      line: line,
      printed: true,
      config: config,
    );
    expect(half.credit, SpokenCredit.almost);
    expect(half.missing, ['of', 'us', 'please']);
  });

  // ПРАВИЛО: Ч.3.1 / Ч.3.5 — артикли и одна съеденная связка не считаются, и «Не то» про них не
  // говорят.
  // ЛОВИТ: вердикт, который винит человека в дикции микрофона. Распознаватель ест безударные
  // слова; ошибка, записанная за это в память ученика, — не его ошибка.
  test('ни артикль, ни одна съеденная связка не делают чтение неверным', () {
    const line = 'I would like to open a bank account';

    expect(
      SpokenLine.judge(
        transcript: 'i would like to open bank account',
        line: line,
        printed: true,
        config: config,
      ).credit,
      SpokenCredit.correct,
    );
    expect(
      SpokenLine.judge(
        transcript: 'i would like open a bank account',
        line: line,
        printed: true,
        config: config,
      ).credit,
      SpokenCredit.correct,
    );
    // …но не смысловое слово: «bank» прощению не подлежит.
    expect(
      SpokenLine.judge(
        transcript: 'i would like to open a account',
        line: line,
        printed: true,
        config: config,
      ).credit,
      isNot(SpokenCredit.correct),
    );
  });

  // ПРАВИЛО: Ч.3.2 — без текста зачёт = ключ ОБЯЗАТЕЛЕН И остальные слова реплики ≥ 0.6.
  // ЛОВИТ: возврат к «ключ есть — верно» (движок 08.09 закрывался на первом узнанном ключевом
  // слове и ставил «верно» над недослушанной фразой) и обратную ошибку — зачёт без ключа.
  test('реплика без текста спрашивает ключ И остальное', () {
    const line = "Yes, I'm looking for a place to rent for long-term living.";
    const keys = ['a place to rent'];

    expect(
      SpokenLine.judge(
        transcript: 'I want a place to rent',
        line: line,
        keys: keys,
        config: config,
      ).credit,
      isNot(SpokenCredit.correct),
    );
    expect(
      SpokenLine.judge(
        transcript: 'yes i am looking for long term living',
        line: line,
        keys: keys,
        config: config,
      ).credit,
      SpokenCredit.wrong,
    );
    expect(
      SpokenLine.judge(
        transcript: "Yes I'm looking for a place to rent for long-term living",
        line: line,
        keys: keys,
        config: config,
      ).credit,
      SpokenCredit.correct,
    );
  });

  // ПРАВИЛО: Ч.3.2 — упрощённая форма это другой способ сказать ВСЮ реплику, а не её кусок.
  // ЛОВИТ: правило «ключ + остальное», применённое к перефразу: оно требовало бы сказать реплику
  // дважды и отменило бы канон GEN-1 Y4 боком. Различает их один вопрос — стоит ли ключ в реплике
  // сплошным куском.
  test('перефраз засчитывается как вся реплика, фрагмент — как половина', () {
    const line = 'It hurts in my lower back.';

    expect(
      SpokenLine.judge(
        transcript: 'my back hurts',
        line: line,
        keys: const ['my back hurts'],
        config: config,
      ).credit,
      SpokenCredit.correct,
    );
    expect(
      SpokenLine.judge(
        transcript: 'in my lower back',
        line: line,
        keys: const ['in my lower back'],
        config: config,
      ).credit,
      isNot(SpokenCredit.correct),
    );
  });

  // ПРАВИЛО: Ч.3.4 / фикс DAY-GATE-1 — реплика БЕЗ ключа судится целиком.
  // ЛОВИТ: возврат к списку из одних упрощённых форм. Владелец 08.09 сказал реплику слово в слово
  // и получил «Не то», потому что «что засчитывается» не содержало самой реплики.
  test('реплика без ключа судится целиком', () {
    const line = 'Sorry, could you repeat that?';

    final said = SpokenLine.judge(transcript: 'sorry could you repeat that', line: line, config: config);
    expect(said.threshold, 'whole_line');
    expect(said.credit, SpokenCredit.correct);
    expect(
      SpokenLine.judge(transcript: 'sorry', line: line, config: config).credit,
      SpokenCredit.wrong,
    );
  });

  // ПРАВИЛО: Ч.4.2 — распознанная аббревиатура приводится к канону ДО всякого счёта, по таблице,
  // приехавшей с сервера.
  // ЛОВИТ: промах на ровном месте. `SFSpeechRecognizer` пишет «sequel» за SQL и «a p i» за API —
  // это не ошибка человека, а то, как звучит буква, и штраф за неё уходит в append-only журнал.
  test('аббревиатура, произнесённая по буквам, — та же аббревиатура', () {
    const withTable = SpeechGradingConfig(
      normalizationVersion: '1',
      normalization: {'sequel': 'sql', 'a p i': 'api', 'h r': 'hr', 'a i': 'ai'},
    );

    expect(
      SpokenLine.judge(
        transcript: 'I write sequel queries every day',
        line: 'I write SQL queries every day',
        printed: true,
        config: withTable,
      ).credit,
      SpokenCredit.correct,
    );
    // …и замена идёт от САМОЙ ДЛИННОЙ формы: «a p i» съедается целиком, а не оставляет после
    // «a i» → `ai` осиротевшее «p».
    expect(
      SpokenLine.normalizeAbbreviations('the a p i is ready', withTable.normalization),
      'the api is ready',
    );
    // Без таблицы (старый пейлоад, офлайн-сборка) ничего не ломается — просто не чинится.
    expect(
      SpokenLine.judge(
        transcript: 'I write sequel queries every day',
        line: 'I write SQL queries every day',
        printed: true,
        config: const SpeechGradingConfig(),
      ).credit,
      isNot(SpokenCredit.correct),
    );
  });

  // ПРАВИЛО: Ч.3.3 — пороги приезжают с сервера; своих чисел у экрана нет.
  // ЛОВИТ: дефолты клиента, разъехавшиеся с `config/learning.php`. Пейлоад приходит не всегда
  // (офлайн-запуск, старый кэш), и в этот момент телефон судит СВОИМИ числами — они обязаны быть
  // теми же, иначе расхождение экрана и журнала случается ровно там, где его никто не смотрит.
  test('дефолты клиента совпадают с дефолтами серверного конфига', () {
    final php = File('../backend2/config/learning.php').readAsStringSync();
    double serverDefault(String key) {
      final match = RegExp("'$key' => \\(float\\) env\\('[A-Z_]+', ([0-9.]+)\\)").firstMatch(php);
      expect(match, isNotNull, reason: 'в конфиге нет ключа $key');

      return double.parse(match!.group(1)!);
    }

    expect(SpeechGradingConfig.empty.readAloud, serverDefault('read_aloud_coverage'));
    expect(SpeechGradingConfig.empty.recallRest, serverDefault('recall_rest_coverage'));
    expect(SpeechGradingConfig.empty.wholeLine, serverDefault('whole_line_coverage'));
    expect(SpeechGradingConfig.empty.almostFloor, serverDefault('almost_floor'));
  });

  // ПРАВИЛО: Ч.3.3 — порог приезжает с сервера и им же судят.
  // ЛОВИТ: числа, зашитые в код экрана. Тот же замок, что и на сервере: сдвинутый порог обязан
  // сдвинуть вердикт.
  test('судит теми числами, которые ему дали', () {
    const line = 'Could you take a photo of us please';
    const lenient = SpeechGradingConfig(readAloud: 0.5);

    expect(
      SpokenLine.judge(transcript: 'could you take photo', line: line, printed: true, config: config).credit,
      SpokenCredit.almost,
    );
    expect(
      SpokenLine.judge(transcript: 'could you take photo', line: line, printed: true, config: lenient).credit,
      SpokenCredit.correct,
    );
  });
}
