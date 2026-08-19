import AudioToolbox
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
