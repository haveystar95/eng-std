import AVFoundation
import Flutter
import Speech
import UIKit

@main
@objc class AppDelegate: FlutterAppDelegate, FlutterImplicitEngineDelegate {
  /// Every sound of the app — lines, phrases, words and the short sounds — plays through this one engine
  /// (work order SESSION-2a §1); see `AudioMixer` below and `lib/data/audio_mixer.dart`.
  private let audioMixer = AudioMixer()

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
    audioMixer.register(engineBridge.applicationRegistrar.messenger())
    registerSpeechProbeChannel(engineBridge.applicationRegistrar.messenger())
    registerLinksChannel(engineBridge.applicationRegistrar.messenger())
    registerPushChannel(engineBridge.applicationRegistrar.messenger())
    registerAppInfoChannel(engineBridge.applicationRegistrar.messenger())
  }

  /// BUILD VERSION (work order SESSION-1b): "1.0.0 (2)" — `CFBundleShortVersionString` and `CFBundleVersion`
  /// from Info.plist, i.e. `version` from pubspec. Shown small on the stage entry (canvas 30-1, the owner's
  /// rule): it tells whether the phone runs the expected build.
  private func registerAppInfoChannel(_ messenger: FlutterBinaryMessenger) {
    let channel = FlutterMethodChannel(name: "com.denis.engstd/app_info", binaryMessenger: messenger)
    channel.setMethodCallHandler { call, result in
      guard call.method == "version" else {
        result(FlutterMethodNotImplemented)
        return
      }
      let info = Bundle.main.infoDictionary
      result([
        "name": info?["CFBundleShortVersionString"] as? String ?? "",
        "build": info?["CFBundleVersion"] as? String ?? "",
      ])
    }
  }

  /// PUSH-ТОКЕН APNs (наряд PLAN-UI-3 §4) — `lib/data/plan/push_registration.dart`.
  ///
  /// Dart зовёт `register` один раз, после разрешения на уведомления; ответ приходит позже методом
  /// `token` (hex) или `failed` (текст ошибки). Без entitlement `aps-environment` — а у бесплатной
  /// Personal Team его нет — iOS отвечает `didFailToRegister…`: это ожидаемо, Dart пишет в лог и
  /// ничего не показывает человеку.
  private var pushChannel: FlutterMethodChannel?

  private func registerPushChannel(_ messenger: FlutterBinaryMessenger) {
    let channel = FlutterMethodChannel(name: "com.denis.engstd/push", binaryMessenger: messenger)
    pushChannel = channel
    channel.setMethodCallHandler { call, result in
      guard call.method == "register" else {
        result(FlutterMethodNotImplemented)
        return
      }
      DispatchQueue.main.async { UIApplication.shared.registerForRemoteNotifications() }
      result(nil)
    }
  }

  override func application(
    _ application: UIApplication, didRegisterForRemoteNotificationsWithDeviceToken deviceToken: Data
  ) {
    let hex = deviceToken.map { String(format: "%02x", $0) }.joined()
    pushChannel?.invokeMethod("token", arguments: hex)
    super.application(application, didRegisterForRemoteNotificationsWithDeviceToken: deviceToken)
  }

  override func application(
    _ application: UIApplication, didFailToRegisterForRemoteNotificationsWithError error: Error
  ) {
    pushChannel?.invokeMethod("failed", arguments: error.localizedDescription)
    super.application(application, didFailToRegisterForRemoteNotificationsWithError: error)
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
}

/// THE APP'S ONE AUDIO ENGINE (work order SESSION-2a §1) — the Dart side is `lib/data/audio_mixer.dart`.
///
/// Speech (the server file of a line, a phrase, a word) and the short sounds (verdict, microphone, stage, day) are
/// player nodes of ONE `AVAudioEngine` and meet in its main mixer. A short sound is scheduled on a node of its own:
/// it never touches the speech node or the audio session, so it cannot stop a line — it plays over it. The level of
/// every sound arrives with the call (the constants are `AudioLevels` in Dart) and is set on the node, so a sound is
/// as loud the tenth time as the first; the system-sound path it replaces followed the ringer and the route instead.
///
/// `playSpeech` answers when the line has ACTUALLY ended: `true` — played to the end, `false` — cut (`stopSpeech`,
/// the next line, an interruption of the audio session, the engine stopped by a configuration change). No timer
/// anywhere on this path: «playing» on the screen ends when the sound does.
///
/// The session itself is not configured here: `Pronouncer.warmUp` sets it (`playAndRecord` + `mixWithOthers` for the
/// day session, so the recognizer never has to change it — see `packages/speech_to_text/UPSTREAM.md`).
final class AudioMixer {
  private let engine = AVAudioEngine()
  private let speechNode = AVAudioPlayerNode()
  private let pace = AVAudioUnitTimePitch()
  private var effectNodes: [AVAudioPlayerNode] = []
  private var nextEffectNode = 0
  private var effects: [String: AVAudioPCMBuffer] = [:]

  /// The Dart call waiting for the current line to end.
  private var speechResult: FlutterResult?

  /// Grows with every line and every cut: a completion that arrives for an older line is ignored.
  private var speechSerial = 0

  /// Every buffer is brought to this format before it is scheduled, so the graph is wired once and never rewired.
  private let format = AVAudioFormat(standardFormatWithSampleRate: 44_100, channels: 2)!

  /// Short sounds that may overlap (the microphone cue and a verdict).
  private static let effectVoices = 3

  /// Leading silence below −50 dBFS is cut from a short sound; 2 ms are kept before the first audible frame.
  private static let silenceThreshold: Float = 0.003_16

  private var observers: [NSObjectProtocol] = []

  init() {
    engine.attach(speechNode)
    engine.attach(pace)
    engine.connect(speechNode, to: pace, format: format)
    engine.connect(pace, to: engine.mainMixerNode, format: format)
    for _ in 0..<Self.effectVoices {
      let node = AVAudioPlayerNode()
      engine.attach(node)
      engine.connect(node, to: engine.mainMixerNode, format: format)
      effectNodes.append(node)
    }
    let center = NotificationCenter.default
    // A route or format change stops the engine by itself and no completion arrives for what was playing.
    observers.append(center.addObserver(forName: .AVAudioEngineConfigurationChange, object: engine, queue: .main) { [weak self] _ in
      self?.cutSpeech()
    })
    observers.append(center.addObserver(forName: AVAudioSession.interruptionNotification, object: nil, queue: .main) { [weak self] note in
      let raw = note.userInfo?[AVAudioSessionInterruptionTypeKey] as? UInt
      if raw == AVAudioSession.InterruptionType.began.rawValue { self?.cutSpeech() }
    })
  }

  deinit {
    observers.forEach(NotificationCenter.default.removeObserver)
  }

  func register(_ messenger: FlutterBinaryMessenger) {
    let channel = FlutterMethodChannel(name: "com.denis.engstd/audio_mixer", binaryMessenger: messenger)
    channel.setMethodCallHandler { [weak self] call, result in
      guard let self else {
        result(nil)
        return
      }
      let args = call.arguments as? [String: Any] ?? [:]
      switch call.method {
      case "playSpeech": self.playSpeech(args, result)
      case "stopSpeech":
        self.cutSpeech()
        result(nil)
      case "loadEffects": self.loadEffects(args, result)
      case "playEffect": self.playEffect(args, result)
      case "releaseEffects":
        for name in args["names"] as? [String] ?? [] { self.effects.removeValue(forKey: name) }
        result(nil)
      case "pause":
        self.cutSpeech()
        self.effectNodes.forEach { $0.stop() }
        self.engine.stop()
        result(nil)
      default: result(FlutterMethodNotImplemented)
      }
    }
  }

  private func level(_ args: [String: Any], default value: Float) -> Float {
    max(0, min(1, (args["level"] as? NSNumber)?.floatValue ?? value))
  }

  private func running() -> Bool {
    if engine.isRunning { return true }
    do {
      engine.prepare()
      try engine.start()
      return true
    } catch {
      NSLog("[audio-mixer] engine did not start: \(error.localizedDescription)")
      return false
    }
  }

  // MARK: speech

  private func playSpeech(_ args: [String: Any], _ result: @escaping FlutterResult) {
    guard let path = args["path"] as? String else {
      result(FlutterError(code: "bad_args", message: "expected a `path`", details: nil))
      return
    }
    // The path comes from our own cache. A missing file is an honest answer (the disk was cleaned): Dart then reads
    // the line with the system voice instead of silence.
    guard FileManager.default.fileExists(atPath: path) else {
      result(FlutterError(code: "no_file", message: "no audio at \(path)", details: nil))
      return
    }
    let volume = level(args, default: 1)
    // RATE: «Say it aloud» plays the sample at 0.85×, the listening stage at 0.75× — the server voice is always at
    // normal pace (DECISIONS item 318); the time-pitch unit keeps the pitch.
    let rate = max(0.5, min(2.0, (args["rate"] as? NSNumber)?.floatValue ?? 1))
    // The line before this one is cut the moment this one is asked for, not when it starts.
    cutSpeech()
    let serial = speechSerial
    let target = format
    DispatchQueue.global(qos: .userInitiated).async {
      let buffer = Self.decode(URL(fileURLWithPath: path), to: target)
      DispatchQueue.main.async { [weak self] in
        guard let self else { return }
        // Cut or replaced while decoding: this line never sounds.
        guard serial == self.speechSerial else {
          result(false)
          return
        }
        guard let buffer else {
          result(FlutterError(code: "play_failed", message: "undecodable audio", details: nil))
          return
        }
        guard self.running() else {
          result(FlutterError(code: "play_failed", message: "engine not running", details: nil))
          return
        }
        self.speechNode.volume = volume
        self.pace.rate = rate
        self.speechResult = result
        self.speechNode.scheduleBuffer(buffer, at: nil, options: [], completionCallbackType: .dataPlayedBack) { [weak self] _ in
          DispatchQueue.main.async {
            guard let self, serial == self.speechSerial else { return }
            self.finishSpeech(ended: true)
          }
        }
        self.speechNode.play()
      }
    }
  }

  /// Whatever line is sounding or being prepared stops, and whoever waits for it hears `false`.
  private func cutSpeech() {
    speechSerial += 1
    speechNode.stop()
    finishSpeech(ended: false)
  }

  private func finishSpeech(ended: Bool) {
    let pending = speechResult
    speechResult = nil
    pending?(ended)
  }

  // MARK: short sounds

  /// `{effects: {name: asset}}` — decoded in the background and kept by name; answers how many decoded.
  private func loadEffects(_ args: [String: Any], _ result: @escaping FlutterResult) {
    let wanted = args["effects"] as? [String: String] ?? [:]
    let target = format
    DispatchQueue.global(qos: .userInitiated).async {
      var loaded: [String: AVAudioPCMBuffer] = [:]
      for (name, asset) in wanted {
        if let buffer = Self.decodeAsset(asset, to: target) { loaded[name] = buffer }
      }
      DispatchQueue.main.async { [weak self] in
        self?.effects.merge(loaded) { _, new in new }
        result(loaded.count)
      }
    }
  }

  /// `{name, asset, level}` — a sound not loaded yet is decoded on the spot from `asset`.
  private func playEffect(_ args: [String: Any], _ result: @escaping FlutterResult) {
    guard let name = args["name"] as? String else {
      result(FlutterError(code: "bad_args", message: "expected a `name`", details: nil))
      return
    }
    var buffer = effects[name]
    if buffer == nil, let asset = args["asset"] as? String {
      buffer = Self.decodeAsset(asset, to: format)
      effects[name] = buffer
    }
    guard let buffer, running() else {
      result(false)
      return
    }
    let node = effectNodes[nextEffectNode]
    nextEffectNode = (nextEffectNode + 1) % effectNodes.count
    node.stop()
    node.volume = level(args, default: 0.38)
    node.scheduleBuffer(buffer, completionHandler: nil)
    node.play()
    result(true)
  }

  // MARK: decoding

  /// A bundled asset (`assets/sounds/correct.mp3`) with its leading silence cut; the asset file itself is untouched.
  private static func decodeAsset(_ asset: String, to format: AVAudioFormat) -> AVAudioPCMBuffer? {
    let key = FlutterDartProject.lookupKey(forAsset: asset)
    guard let path = Bundle.main.path(forResource: key, ofType: nil),
      let buffer = decode(URL(fileURLWithPath: path), to: format)
    else { return nil }
    return trimLeadingSilence(buffer)
  }

  /// Any file iOS can read → float stereo at 44.1 kHz: the sample rate by `AVAudioConverter` with the channel count
  /// kept, then a mono file is copied into both channels (a converter's default channel map would put it left only).
  private static func decode(_ url: URL, to format: AVAudioFormat) -> AVAudioPCMBuffer? {
    guard let file = try? AVAudioFile(forReading: url),
      let source = AVAudioPCMBuffer(pcmFormat: file.processingFormat, frameCapacity: AVAudioFrameCount(file.length))
    else { return nil }
    do { try file.read(into: source) } catch { return nil }
    guard source.frameLength > 0 else { return nil }

    let channels = source.format.channelCount
    var resampled = source
    if source.format.sampleRate != format.sampleRate || source.format.commonFormat != .pcmFormatFloat32 || source.format.isInterleaved {
      guard let middle = AVAudioFormat(standardFormatWithSampleRate: format.sampleRate, channels: channels),
        let converter = AVAudioConverter(from: source.format, to: middle),
        let out = AVAudioPCMBuffer(
          pcmFormat: middle,
          frameCapacity: AVAudioFrameCount(Double(source.frameLength) * format.sampleRate / source.format.sampleRate) + 1024)
      else { return nil }
      var fed = false
      var error: NSError?
      let status = converter.convert(to: out, error: &error) { _, inputStatus in
        if fed {
          inputStatus.pointee = .endOfStream
          return nil
        }
        fed = true
        inputStatus.pointee = .haveData
        return source
      }
      guard status != .error, out.frameLength > 0 else { return nil }
      resampled = out
    }

    guard let stereo = AVAudioPCMBuffer(pcmFormat: format, frameCapacity: resampled.frameLength),
      let from = resampled.floatChannelData, let to = stereo.floatChannelData
    else { return nil }
    let frames = Int(resampled.frameLength)
    stereo.frameLength = resampled.frameLength
    for c in 0..<2 {
      to[c].update(from: from[min(c, Int(channels) - 1)], count: frames)
    }
    return stereo
  }

  private static func trimLeadingSilence(_ buffer: AVAudioPCMBuffer) -> AVAudioPCMBuffer? {
    guard let samples = buffer.floatChannelData else { return nil }
    let frames = Int(buffer.frameLength)
    let channels = Int(buffer.format.channelCount)
    func audible(_ frame: Int) -> Bool {
      for c in 0..<channels where abs(samples[c][frame]) > silenceThreshold { return true }
      return false
    }
    guard let first = (0..<frames).first(where: audible) else { return nil }
    let start = max(0, first - Int(buffer.format.sampleRate * 0.002))
    if start == 0 { return buffer }
    guard let trimmed = AVAudioPCMBuffer(pcmFormat: buffer.format, frameCapacity: AVAudioFrameCount(frames - start)) else { return nil }
    trimmed.frameLength = AVAudioFrameCount(frames - start)
    for c in 0..<channels {
      trimmed.floatChannelData![c].update(from: samples[c] + start, count: frames - start)
    }
    return trimmed
  }
}
