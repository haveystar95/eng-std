import AudioToolbox
import AVFoundation
import Flutter
import Speech
import UIKit

@main
@objc class AppDelegate: FlutterAppDelegate, FlutterImplicitEngineDelegate, AVAudioPlayerDelegate {
  /// The four sounds of the day — «верно», «неверно», «этап закрыт», «день закрыт» (токен-лист
  /// 4к-3) — registered with AudioServices once each and kept for the app's life (QA-22, DAY-UI). Keyed by the name Dart sends, so `AppFeedback` names a SOUND and never a file path.
  ///
  /// Cached deliberately: `AudioServicesCreateSystemSoundID` reads and parses the file, and doing
  /// that on every answer would put file I/O on the main thread at the exact moment the card is
  /// animating its verdict. Two sounds, a few KB each, created on first use and never disposed —
  /// `AudioServicesDisposeSystemSoundID` would only ever run at app teardown, where it buys
  /// nothing.
  private var soundIds: [String: SystemSoundID] = [:]

  /// ССЫЛКИ `engstd://…` (наряд DAY-UI) — см. `lib/data/deep_links.dart`. Ссылка холодного старта
  /// лежит здесь, пока Dart не спросит `initial`; тёплая уходит в канал сразу.
  private var linksChannel: FlutterMethodChannel?
  private var pendingLink: String?

  override func application(
    _ application: UIApplication,
    didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?
  ) -> Bool {
    if let url = launchOptions?[.url] as? URL, url.scheme == "engstd" {
      pendingLink = url.absoluteString
    }
    return super.application(application, didFinishLaunchingWithOptions: launchOptions)
  }

  static var current: AppDelegate? { UIApplication.shared.delegate as? AppDelegate }

  /// Ссылка `engstd://…` из сцены (`SceneDelegate`) или из старого пути `open url`.
  /// Не наша схема — игнорируется; Dart ещё не поднял канал — ссылка ждёт `initial`.
  @discardableResult
  func handleLink(_ url: URL) -> Bool {
    guard url.scheme == "engstd" else { return false }
    if let channel = linksChannel {
      channel.invokeMethod("open", arguments: url.absoluteString)
    } else {
      pendingLink = url.absoluteString
    }
    return true
  }

  override func application(
    _ app: UIApplication, open url: URL, options: [UIApplication.OpenURLOptionsKey: Any] = [:]
  ) -> Bool {
    if handleLink(url) { return true }
    return super.application(app, open: url, options: options)
  }

  private func registerLinksChannel(_ messenger: FlutterBinaryMessenger) {
    let channel = FlutterMethodChannel(name: "com.denis.engstd/links", binaryMessenger: messenger)
    linksChannel = channel
    channel.setMethodCallHandler { [weak self] call, result in
      guard call.method == "initial" else {
        result(FlutterMethodNotImplemented)
        return
      }
      let link = self?.pendingLink
      self?.pendingLink = nil
      result(link)
    }
  }

  func didInitializeImplicitFlutterEngine(_ engineBridge: FlutterImplicitEngineBridge) {
    GeneratedPluginRegistrant.register(with: engineBridge.pluginRegistry)
    // `applicationRegistrar` is the app's own registrar (as opposed to a per-plugin one) — this is
    // an application-level channel, not a plugin, so that is the right messenger to hang it on.
    registerFeedbackSoundChannel(engineBridge.applicationRegistrar.messenger())
    registerLineAudioChannel(engineBridge.applicationRegistrar.messenger())
    registerSpeechProbeChannel(engineBridge.applicationRegistrar.messenger())
    registerLinksChannel(engineBridge.applicationRegistrar.messenger())
  }

  /// ЧТО ОС ДУМАЕТ ПРО МИКРОФОН — наряд DAY-GATE-1, Ч.0.1. См. `lib/data/speech/speech_diagnostics.dart`.
  ///
  /// Читающий канал: НИ ОДНОГО разрешения он не запрашивает и ни одной сессии не поднимает, поэтому
  /// служебная строка может опрашивать его хоть каждую секунду, не показывая человеку системных
  /// окон и не отбирая аудиосессию у тренажёра.
  ///
  /// Почему не через `speech_to_text`: плагин отвечает на этот вопрос ОДНИМ булевым
  /// (`hasPermission` = распознавание И микрофон), а вся диагностика 07.09 упирается ровно в то,
  /// что эти два разрешения — разные и могут разойтись. Плюс «поддерживается ли язык цели» плагин
  /// не отвечает вовсе: его `locales()` возвращает список имён, а не «создастся ли распознаватель
  /// для en_US и жив ли он сейчас».
  private func registerSpeechProbeChannel(_ messenger: FlutterBinaryMessenger) {
    let channel = FlutterMethodChannel(
      name: "com.denis.engstd/speech_probe", binaryMessenger: messenger)

    channel.setMethodCallHandler { call, result in
      guard call.method == "probe" else {
        result(FlutterMethodNotImplemented)
        return
      }
      let locale = (call.arguments as? [String: Any])?["locale"] as? String ?? ""
      // Пустая строка — это НЕ «локаль по умолчанию»: `SFSpeechRecognizer(locale:)` на ней
      // возвращает nil, и один живой вызов с пустым localeId стоил проекта целого шага «Скажи
      // вслух» (наряд, находка F1). Здесь она честно отвечает «языка нет».
      let recognizer = locale.isEmpty ? nil : SFSpeechRecognizer(locale: Locale(identifier: locale))

      var payload: [String: Any] = [
        "recognition": Self.speechAuthWord(SFSpeechRecognizer.authorizationStatus()),
        "microphone": Self.recordPermissionWord(),
        "recognizer_supported": recognizer != nil,
        "recognizer_available": recognizer?.isAvailable ?? false,
        "on_device_supported": false,
      ]
      if #available(iOS 13.0, *), let recognizer {
        payload["on_device_supported"] = recognizer.supportsOnDeviceRecognition
      }
      result(payload)
    }
  }

  private static func speechAuthWord(_ status: SFSpeechRecognizerAuthorizationStatus) -> String {
    switch status {
    case .authorized: return "granted"
    case .denied: return "denied"
    case .restricted: return "restricted"
    case .notDetermined: return "not_determined"
    @unknown default: return "unknown"
    }
  }

  /// Разрешение на запись, спрошенное тем API, которое действует на этой системе.
  ///
  /// `AVAudioSession.recordPermission` объявлено устаревшим в iOS 17 в пользу
  /// `AVAudioApplication.shared.recordPermission`; телефон владельца стоит на iOS 27. Старая ветка
  /// остаётся, потому что цель развёртывания — 15.0.
  private static func recordPermissionWord() -> String {
    if #available(iOS 17.0, *) {
      switch AVAudioApplication.shared.recordPermission {
      case .granted: return "granted"
      case .denied: return "denied"
      case .undetermined: return "not_determined"
      @unknown default: return "unknown"
      }
    }
    switch AVAudioSession.sharedInstance().recordPermission {
    case .granted: return "granted"
    case .denied: return "denied"
    case .undetermined: return "not_determined"
    @unknown default: return "unknown"
    }
  }

  /// ОЗВУЧКА РЕПЛИКИ, сделанная сервером заранее (наряд TTS-1) — см. `lib/data/line_audio.dart`.
  ///
  /// Почему снова нативный канал, а не пакет-плеер: тот же довод, что и у звука вердикта выше, плюс
  /// один новый. Тренажёр весь стоит на `AVSpeechSynthesizer`, который держит СВОЮ аудиосессию
  /// (`playback` + `mixWithOthers`, поднятую один раз на всю посадку в `Pronouncer.warmUp`), и
  /// пакет-плеер поднял бы вторую — со своими категориями, своим временем жизни и своей манерой
  /// деактивировать сессию после каждого файла. Ровно эта деактивация уже стоила проекта ~600 мс
  /// заморозки на каждом произнесённом слове (F20). `AVAudioPlayer` без единой настройки сессии
  /// играет в ТУ ЖЕ сессию, которую поднял синтезатор, и делить им нечего.
  ///
  /// Плеер один и переиспользуется: реплики звучат по одной, а вторая начатая перебивает первую —
  /// то же поведение, что и `Pronouncer.stop()` перед каждой фразой.
  private var linePlayer: AVAudioPlayer?

  /// РЕЗУЛЬТАТ `play` ОТДАЁТСЯ, КОГДА ФАЙЛ ДОИГРАЛ (наряд DAY-FIX-3, Ч.1.1): микрофон прогона
  /// открывается по концу реплики собеседника, и Dart ждёт именно этого ответа. `stop` и новая
  /// `play` закрывают предыдущее ожидание сразу — перебитая реплика кончилась.
  private var linePlayResult: FlutterResult?

  private func finishLinePlay() {
    let pending = linePlayResult
    linePlayResult = nil
    pending?(nil)
  }

  private func registerLineAudioChannel(_ messenger: FlutterBinaryMessenger) {
    let channel = FlutterMethodChannel(
      name: "com.denis.engstd/line_audio", binaryMessenger: messenger)

    channel.setMethodCallHandler { [weak self] call, result in
      guard let self = self else {
        result(FlutterError(code: "gone", message: "no app delegate", details: nil))
        return
      }

      switch call.method {
      case "stop":
        self.linePlayer?.stop()
        self.linePlayer = nil
        self.finishLinePlay()
        result(nil)

      case "play":
        guard let path = (call.arguments as? [String: Any])?["path"] as? String else {
          result(FlutterError(code: "bad_args", message: "expected a `path`", details: nil))
          return
        }
        // Путь приходит ИЗ НАШЕГО кэша, который эта же сборка и наполняет (файлы лежат в Application
        // Support). Проверка на существование — не безопасность, а честный ответ: файла может не
        // быть после чистки диска, и тогда Dart играет системным голосом вместо тишины.
        guard FileManager.default.fileExists(atPath: path) else {
          result(FlutterError(code: "no_file", message: "no audio at \(path)", details: nil))
          return
        }

        do {
          self.linePlayer?.stop()
          self.finishLinePlay()
          let player = try AVAudioPlayer(contentsOf: URL(fileURLWithPath: path))
          self.linePlayer = player
          player.delegate = self
          player.prepareToPlay()
          self.linePlayResult = result
          if !player.play() {
            self.linePlayResult = nil
            result(FlutterError(code: "play_failed", message: "player refused to start", details: nil))
          }
        } catch {
          result(FlutterError(code: "play_failed", message: error.localizedDescription, details: nil))
        }

      default:
        result(FlutterMethodNotImplemented)
      }
    }
  }

  func audioPlayerDidFinishPlaying(_ player: AVAudioPlayer, successfully flag: Bool) {
    if player === linePlayer { finishLinePlay() }
  }

  func audioPlayerDecodeErrorDidOccur(_ player: AVAudioPlayer, error: Error?) {
    if player === linePlayer { finishLinePlay() }
  }

  /// `AppFeedback`'s side of the verdict sound — see `lib/theme/feedback.dart`.
  ///
  /// Why AudioServices rather than an audio package: this is a UI sound, and the system-sound path
  /// is what makes it BEHAVE like one — it honours the ringer/silent switch on its own, it does not
  /// touch or need an AVAudioSession (so it cannot duck, interrupt or fight the trainer's own TTS
  /// session, which holds `playAndRecord` for the whole training screen), and it costs no pub
  /// dependency. A player package would have given us all three problems to solve by hand.
  private func registerFeedbackSoundChannel(_ messenger: FlutterBinaryMessenger) {
    let channel = FlutterMethodChannel(
      name: "com.denis.engstd/feedback_sound", binaryMessenger: messenger)

    channel.setMethodCallHandler { [weak self] call, result in
      guard call.method == "play" else {
        result(FlutterMethodNotImplemented)
        return
      }
      guard let self = self,
        let name = (call.arguments as? [String: Any])?["sound"] as? String
      else {
        result(FlutterError(code: "bad_args", message: "expected a `sound` name", details: nil))
        return
      }
      guard let id = self.soundId(for: name) else {
        // A missing asset is a build problem, not a runtime state to handle — but it must never
        // take the answer down with it, so it is reported and the card carries on silently.
        result(FlutterError(code: "no_sound", message: "unknown sound \(name)", details: nil))
        return
      }
      AudioServicesPlaySystemSound(id)
      result(nil)
    }
  }

  /// The cached SystemSoundID for [name], creating it on first use. Nil when the asset is not in
  /// the bundle or AudioServices refuses it.
  private func soundId(for name: String) -> SystemSoundID? {
    if let existing = soundIds[name] { return existing }
    // Only the two names this app actually ships — the channel argument comes from our own Dart,
    // but a lookup keyed by an arbitrary string is a file-path parameter in disguise.
    guard ["verdict_correct", "verdict_wrong", "stage_closed", "day_closed"].contains(name) else { return nil }

    let key = FlutterDartProject.lookupKey(forAsset: "assets/sounds/\(name).wav")
    guard let path = Bundle.main.path(forResource: key, ofType: nil) else { return nil }

    var id: SystemSoundID = 0
    let status = AudioServicesCreateSystemSoundID(URL(fileURLWithPath: path) as CFURL, &id)
    guard status == kAudioServicesNoError else { return nil }

    soundIds[name] = id
    return id
  }
}
