import 'dart:async';

import 'package:flutter/foundation.dart' show debugPrint, defaultTargetPlatform, TargetPlatform;
import 'package:flutter_tts/flutter_tts.dart';

import 'line_audio.dart';
import 'models.dart';
import 'languages.dart';

/// Speaks a term out loud.
///
/// System TTS is the default: it is free, offline (so a session works with no
/// network), instant, and covers 100% of terms — including user words that will
/// never have server audio. `ttsHint` fixes the most common system-synth
/// misreadings ("ATM" → "A T M") without generating any audio.
///
/// ОДНО ИСКЛЮЧЕНИЕ, и оно про реплики сцены (наряд TTS-1). Реплика собеседника,
/// спасатель и такт 1 диалога стоят на СЛУШАНИИ: их не читают глазами, их
/// понимают на слух, и системный синтез на них слышно (канон
/// `../backend2/docs/plan-dialogue.md` §7). У них есть файл, сделанный сервером
/// заранее, — [LineAudioCache] знает какой, и [speakText] играет его. Всё
/// остальное — слова, связки, поиск, коллекции — как было.
class Pronouncer {
  Pronouncer([FlutterTts? tts, LineAudioCache? lines]) : _tts = tts ?? FlutterTts(), _lines = lines {
    // Конец произнесения — для тех, кто его ждёт ({@see speakText} с `awaitDone`). Отмена и
    // ошибка — тоже конец: реплика, которую перебили, не зазвучит уже никогда.
    _tts.setCompletionHandler(_finishUtterance);
    _tts.setCancelHandler(_finishUtterance);
    _tts.setErrorHandler((_) => _finishUtterance());
  }

  final FlutterTts _tts;

  /// СЕРВЕРНАЯ ОЗВУЧКА РЕПЛИК, если труба включена (наряд TTS-1). Null — приложение говорит ровно
  /// как до наряда, одним системным синтезом.
  ///
  /// Кэш стоит ЗДЕСЬ, а не на экранах, потому что здесь единственное место, через которое проходит
  /// каждое произнесение: пузырь диалога, кнопка повтора, панель спасателей, шпаргалка, разогрев и
  /// интро-карточка зовут один и тот же `speak`. Развести их по вызывающим значило бы шесть раз
  /// написать одно правило и один раз забыть.
  final LineAudioCache? _lines;
  bool _audioSessionReady = false;
  // Cache what we've already pushed to the engine so a repeat speak() is ONE platform-channel call
  // (speak) instead of three (setLanguage + setSpeechRate + speak). The redundant round-trips were a
  // per-answer stall that landed on the card-transition animation (F20).
  String? _lastLocale;
  double? _lastRate;

  /// Locales iOS has already refused, so the log says it once instead of once per card.
  final Set<String> _missingVoices = {};

  /// When we last asked the engine to say something, and how long the iOS audio route stays awake
  /// after that. Drives [_wakeRoute].
  DateTime? _lastSpokeAt;
  static const Duration _routeIdleAfter = Duration(seconds: 3);

  /// Normal and slowed speech rates. Slow (a touch under normal) is the listening card's
  /// «замедленно» replay — a beat slower so a learner can catch each sound (кадр 12g/12h).
  static const double _rateNormal = 0.45;
  static const double _rateSlow = 0.30;

  /// ТЕМП РЕПЛИК — своя ручка, ниже темпа слов (канон §7: «темп реплик ниже темпа слов»).
  ///
  /// Отдельная константа, а не `_rateSlow`: медленный повтор — это ЖЕСТ («ещё раз, помедленнее»),
  /// а это ОБЫЧНАЯ скорость целого яруса. Слово живёт секунду и произносится в вакууме; реплика в
  /// восемь слов на той же скорости проезжает мимо уха целиком, и первое, что теряется, — конец
  /// вопроса, то есть ровно то, что надо было расслышать.
  ///
  /// 0.38 против 0.45 у слов — примерно −15 % темпа. Цифра выбрана по замеру Ч.0.3: серверные
  /// файлы читаются со скоростью ~11 знаков в секунду против ~15 у системного голоса на 0.45, и
  /// эта ручка сближает системный путь с серверным, чтобы переключение трубы не меняло ощущение
  /// урока. Для серверных файлов темп заложен ПРИ ГЕНЕРАЦИИ и здесь не участвует.
  static const double _rateLine = 0.38;

  /// Open the iOS audio session ONCE for the whole training session and push the engine's language
  /// and rate before any card needs them.
  ///
  /// The important part is [FlutterTts.autoStopSharedSession]`(false)`. flutter_tts defaults it to
  /// true, and its iOS side then runs this after EVERY utterance:
  ///
  ///     if shouldDeactivateAndNotifyOthers(session) && autoStopSharedSession {
  ///       try session.setActive(false, options: .notifyOthersOnDeactivation)
  ///     }
  ///
  /// — and `shouldDeactivateAndNotifyOthers` is true precisely because we ask for `duckOthers`.
  /// `setActive(false, .notifyOthersOnDeactivation)` is a blocking system teardown, and it was the
  /// ~600 ms freeze that hit the trainer once per spoken word: measured on the device, the stalls
  /// landed on exactly the listening cards and vanished on the rest when auto-pronounce was turned
  /// off. It is also why the first word came out clipped — the next utterance started while the
  /// session was still coming back up.
  ///
  /// So: raise the session once here, keep it up for the session, and drop it once in [release].
  /// Ducking now lasts for the whole training session instead of flickering per word, which is
  /// also the better behaviour for someone training with music on.
  Future<void> warmUp({required String targetLang}) async {
    _released = false;
    await _configureIosAudioSession();
    if (defaultTargetPlatform == TargetPlatform.iOS) {
      await _tts.autoStopSharedSession(false);
      await _tts.setSharedInstance(true);
    }
    await _applyLocale(ttsLocaleFor(targetLang));
    if (_lastRate != _rateNormal) {
      _lastRate = _rateNormal;
      await _tts.setSpeechRate(_rateNormal);
    }
    await _wakeRoute();
  }

  /// The iOS audio ROUTE powers down after a few seconds of silence even while the session stays
  /// active, and AVSpeechSynthesizer starts speaking before it is back up — so the first word after
  /// a pause is heard from the middle. Confirmed on device: a second tap right after is always
  /// clean, and a warm-up done once on screen entry does NOT help, because the route has gone back
  /// to sleep by the time the user actually taps.
  ///
  /// So the wake-up is put where it can't be heard: a silent utterance queued immediately before
  /// the real one. AVSpeechSynthesizer queues utterances, so the word follows with no gap, and the
  /// glitch lands in the silence. Only when we have actually been quiet — back-to-back replays stay
  /// instant.
  Future<void> _wakeRoute() async {
    if (defaultTargetPlatform != TargetPlatform.iOS) return;
    final last = _lastSpokeAt;
    if (last != null && DateTime.now().difference(last) < _routeIdleAfter) return;
    await _tts.setVolume(0);
    await _tts.speak('a');
    await _tts.setVolume(1);
    _lastSpokeAt = DateTime.now();
  }

  /// Say a card's term. [slow] drops the rate for a deliberate, easier-to-parse replay (listening
  /// exercise). Goes through [speakText] so a card whose term IS a scene's line gets the same file
  /// the conversation plays — one line, one voice, whichever screen asks for it.
  Future<void> speak(Word word, {required String targetLang, bool slow = false}) =>
      speakText(word.ttsHint ?? word.term, targetLang: targetLang, slow: slow);

  /// Say [text] — a line of a scene from its own file when there is one, and the system voice
  /// everywhere else.
  ///
  /// ONE method, and the branch inside it is the whole of наряд TTS-1's Ч.2.2. Which path a string
  /// takes is decided by the CACHE and not by the caller: a caller that had to know would be a
  /// caller that can be wrong, and there are six of them.
  ///
  /// [awaitDone] — ВЕРНУТЬСЯ, КОГДА РЕПЛИКА ДОИГРАЛА (наряд DAY-FIX-3, Ч.1.1): микрофон прогона
  /// открывается по концу воспроизведения, и «сказать» без «дождаться» ему не поможет. Файл
  /// отвечает концом сам (нативный плеер держит результат до `didFinish`); синтезатор — своим
  /// обработчиком завершения, а если тот молчит (тесты, чужой движок) — по оценке длины текста.
  Future<void> speakText(
    String text, {
    required String targetLang,
    bool slow = false,
    bool awaitDone = false,
  }) async {
    final line = text.trim();
    if (line.isEmpty) return;

    final lines = _lines;
    if (lines != null && !slow && await lines.play(line)) {
      // Файл этой реплики уже на диске: играем ЕГО. Темп в нём заложен при генерации, поэтому
      // ускорять и замедлять нечего — и кнопка повтора играет ровно тот же файл, мгновенно.
      _lastSpokeAt = DateTime.now();

      return;
    }

    final utterance = awaitDone ? _expectUtterance() : null;
    var started = false;
    try {
      started = await _speakWithEngine(line, targetLang: targetLang, slow: slow, isLine: lines?.knows(line) ?? false);
    } finally {
      // Движок не взял реплику (нет плагина — тесты, превью; отказ, исключение канала) — ждать
      // нечего и некого.
      if (utterance != null && !started) _finishUtterance();
    }
    if (utterance == null) return;
    if (started) _armUtteranceGuard(line);
    await utterance;
  }

  /// Текущее произнесение синтезатора, если кто-то ждёт его конца.
  Completer<void>? _utterance;
  Timer? _utteranceGuard;

  /// Ждать конца ЭТОГО произнесения. Обработчики плагина глобальны, поэтому один completer на
  /// движок: новая реплика закрывает ожидание предыдущей (её всё равно перебили `stop`).
  Future<void> _expectUtterance() {
    _finishUtterance();
    // Экран уже отпустил голос — ждать нечего: реплика после `release` никем не слушается.
    if (_released) return Future<void>.value();
    final completer = Completer<void>();
    _utterance = completer;

    return completer.future;
  }

  /// Голос отпущен экраном ({@see release}); снимается следующим [warmUp].
  bool _released = false;

  /// Страховка от движка, который взял реплику и не сказал, что кончил: ~350 мс на слово плюс
  /// секунда на разгон, но не дольше двенадцати секунд — реплика в двенадцать слов не звучит
  /// дольше. Заводится ПОСЛЕ того, как движок взял реплику: до этого ждать нечего, а таймер,
  /// заведённый под вызов, который никогда не вернётся (тесты), висел бы вечно.
  void _armUtteranceGuard(String line) {
    _utteranceGuard?.cancel();
    if (_utterance == null || _released) return;
    final words = line.split(RegExp(r'\s+')).length;
    _utteranceGuard = Timer(
      Duration(milliseconds: (1000 + words * 350).clamp(1200, 12000)),
      _finishUtterance,
    );
  }

  void _finishUtterance() {
    _utteranceGuard?.cancel();
    _utteranceGuard = null;
    final pending = _utterance;
    _utterance = null;
    if (pending != null && !pending.isCompleted) pending.complete();
  }

  /// True when the engine actually took the utterance (the plugin answers 1 on success); false
  /// where there is no engine to speak of — a test, a preview, a refused platform call.
  Future<bool> _speakWithEngine(
    String line, {
    required String targetLang,
    required bool slow,
    required bool isLine,
  }) async {
    await _configureIosAudioSession();
    // Interrupt any still-playing utterance instead of queueing behind it — a growing TTS queue was
    // a per-card platform-thread load that got worse through a session (F20). This is
    // `stopSpeaking(.immediate)`, which raises `didCancel`, NOT `didFinish` — so it never trips the
    // session teardown that [warmUp] disables.
    await _tts.stop();
    // Три темпа, не два: слово, реплика и замедленный повтор — разные вещи (see [_rateLine]).
    final rate = slow ? _rateSlow : (isLine ? _rateLine : _rateNormal);
    // Only touch the engine when a setting actually changes — otherwise speak() is one channel call,
    // not three, so it stops stalling the card transition (F20).
    await _applyLocale(ttsLocaleFor(targetLang));
    if (_lastRate != rate) {
      _lastRate = rate;
      await _tts.setSpeechRate(rate);
    }
    // Queued BEFORE the word, so a cold route wakes up during silence rather than mid-syllable.
    await _wakeRoute();
    final result = await _tts.speak(line);
    _lastSpokeAt = DateTime.now();

    return result == 1;
  }

  /// Pronunciation is intentional media, not a notification, so it must play through the iOS
  /// hardware silent switch. The default audio-session category respects the mute switch (silent
  /// → no sound); `.playback` overrides it, the way media/player apps do (device-batch F10). Set
  /// once, iOS-only (`defaultTargetPlatform` avoids importing dart:io so web/preview still builds).
  ///
  /// `mixWithOthers`, NOT `duckOthers`. Ducking is only released when the session is deactivated
  /// with `.notifyOthersOnDeactivation`, and flutter_tts exposes no way to pass that: its
  /// `setSharedInstance(false)` calls a plain `setActive(false)`. The one code path that did notify
  /// was the per-utterance teardown — the very thing that froze the trainer for ~600 ms a word and
  /// that we disable. Result on device: music stayed quiet after the app had finished speaking.
  ///
  /// Mixing removes the problem instead of managing it: there is no ducking to restore, so nothing
  /// can be left ducked. It also makes the plugin's `shouldDeactivateAndNotifyOthers` false by
  /// construction, so the per-utterance teardown can never come back even if the flag drifts.
  /// The trade is deliberate: a pronounced word now plays over the user's music rather than
  /// lowering it (F20-r2).
  Future<void> _configureIosAudioSession() async {
    if (_audioSessionReady || defaultTargetPlatform != TargetPlatform.iOS) return;
    _audioSessionReady = true;
    await _tts.setIosAudioCategory(IosTextToSpeechAudioCategory.playback, [
      IosTextToSpeechAudioCategoryOptions.mixWithOthers,
    ], IosTextToSpeechAudioMode.defaultMode);
  }

  /// Push a locale to the engine, and only when it actually changes (a repeat [speak] is then ONE
  /// channel call, not two — F20).
  ///
  /// iOS answers 0 when it has no voice for the locale: the language pack is simply not installed.
  /// It leaves its own language untouched in that case, so the word is read by whatever the engine
  /// already had — the system default on a fresh engine. That is the degradation we want (a word in
  /// the wrong accent beats a crash or a silent card), and it is the behaviour a card got everywhere
  /// before the pair started deciding the locale, so nothing gets worse for an unsupported language.
  ///
  /// It is LOGGED, once per locale, because it is otherwise invisible: «почему у него не тот голос»
  /// has to be answerable from the log rather than by guessing at Settings → Accessibility.
  Future<void> _applyLocale(String locale) async {
    if (_lastLocale == locale) return;
    final accepted = await _tts.setLanguage(locale);
    if (accepted == 1) {
      _lastLocale = locale;
      return;
    }
    // Deliberately NOT cached: the engine is not in this locale, and remembering it as if it were
    // would skip the next attempt and leave a later card reading in some earlier card's language.
    _lastLocale = null;
    if (_missingVoices.add(locale)) {
      debugPrint('[tts] no installed voice for $locale — using the system default voice');
    }
  }

  /// Silence whatever is sounding — the synthesiser FIRST.
  ///
  /// Order matters and it is not stylistic: «Дальше» must cut the engine in the same turn of the
  /// event loop it is tapped in (QA-21, and its test pins the flutter_tts channel call). Awaiting
  /// the file player first pushes `stop` past a microtask, and the word carries over the slide onto
  /// the next card again.
  Future<void> stop() async {
    // Перебитая реплика кончилась — тот, кто её ждал, не должен ждать дальше. ДО каналов, а не
    // после: ожидание закрывается в этом же обороте, чем бы ни ответила платформа.
    _finishUtterance();
    final stopping = _tts.stop();
    await _lines?.stop();
    await stopping;
  }

  /// Give the audio session back when the training session ends. This is the one place the
  /// expensive `setActive(false)` runs — on a screen the user is already leaving, where a stall
  /// costs nothing — instead of after every spoken word.
  Future<void> release() async {
    _released = true;
    await stop();
    if (defaultTargetPlatform == TargetPlatform.iOS) {
      _audioSessionReady = false;
      // Hand the plugin back to its DEFAULT self-deactivating behaviour BEFORE dropping the
      // session. `autoStopSharedSession` lives on the plugin's native singleton, so leaving it
      // false would apply to every other speaker in the app — the collection screen owns a
      // Pronouncer with no lifecycle of its own, and its word button would then leave the session
      // active, ducking other apps' audio indefinitely. Holding the session is a TRAINING-SCREEN
      // behaviour and must not outlive the training screen. It also means the realtime dialog,
      // which raises its own `playAndRecord` session, never meets a session we are still holding.
      await _tts.autoStopSharedSession(true);
      await _tts.setSharedInstance(false);
    }
  }
}
