import AudioToolbox
import AVFoundation
import Flutter
import UIKit

@main
@objc class AppDelegate: FlutterAppDelegate, FlutterImplicitEngineDelegate {
  /// The verdict sounds, registered with AudioServices once each and kept for the app's life
  /// (QA-22). Keyed by the name Dart sends, so `AppFeedback` names a SOUND and never a file path.
  ///
  /// Cached deliberately: `AudioServicesCreateSystemSoundID` reads and parses the file, and doing
  /// that on every answer would put file I/O on the main thread at the exact moment the card is
  /// animating its verdict. Two sounds, a few KB each, created on first use and never disposed —
  /// `AudioServicesDisposeSystemSoundID` would only ever run at app teardown, where it buys
  /// nothing.
  private var soundIds: [String: SystemSoundID] = [:]

  override func application(
    _ application: UIApplication,
    didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?
  ) -> Bool {
    return super.application(application, didFinishLaunchingWithOptions: launchOptions)
  }

  func didInitializeImplicitFlutterEngine(_ engineBridge: FlutterImplicitEngineBridge) {
    GeneratedPluginRegistrant.register(with: engineBridge.pluginRegistry)
    // `applicationRegistrar` is the app's own registrar (as opposed to a per-plugin one) — this is
    // an application-level channel, not a plugin, so that is the right messenger to hang it on.
    registerFeedbackSoundChannel(engineBridge.applicationRegistrar.messenger())
    registerLineAudioChannel(engineBridge.applicationRegistrar.messenger())
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
          let player = try AVAudioPlayer(contentsOf: URL(fileURLWithPath: path))
          self.linePlayer = player
          player.prepareToPlay()
          player.play()
          result(nil)
        } catch {
          result(FlutterError(code: "play_failed", message: error.localizedDescription, details: nil))
        }

      default:
        result(FlutterMethodNotImplemented)
      }
    }
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
    guard ["verdict_correct", "verdict_wrong"].contains(name) else { return nil }

    let key = FlutterDartProject.lookupKey(forAsset: "assets/sounds/\(name).wav")
    guard let path = Bundle.main.path(forResource: key, ofType: nil) else { return nil }

    var id: SystemSoundID = 0
    let status = AudioServicesCreateSystemSoundID(URL(fileURLWithPath: path) as CFURL, &id)
    guard status == kAudioServicesNoError else { return nil }

    soundIds[name] = id
    return id
  }
}
