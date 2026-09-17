# Vendored fork of `speech_to_text`

- **Upstream package:** `speech_to_text` (pub.dev)
- **Vendored from version:** 7.4.0 (exact commit matches `pubspec.lock`'s `sha256:
  75587f7400f485fdf166beacd471549d98fe5d58e634f708916bb65dec05d6a4`)
- **Vendored on:** 2026-08-19
- **Why:** QA-20 — on-device speech recognition (`SFSpeechRecognizer`) sometimes mis-hears a
  close-sounding word (e.g. "What are your strengths" → "What are you strengths"), producing a
  false Fail in the speaking trainer. The fix is `SFSpeechRecognitionRequest.contextualStrings`,
  which tells the recogniser what vocabulary to expect. Upstream 7.4.0's Swift side sets only
  `taskHint` and `addsPunctuation` on the request — it does not expose `contextualStrings` at all,
  and there is no Dart-level parameter to carry it even if it did.
- **Upstream PR:** not yet filed — tracked as SLV-5. This fork is the interim.
- **Package name is unchanged** (`speech_to_text`), wired in via `dependency_overrides: speech_to_text:
  path: packages/speech_to_text` in the app's `pubspec.yaml`, so every existing
  `package:speech_to_text/...` import resolves here transparently.

## What's changed vs. upstream 7.4.0

Only `lib/speech_to_text.dart` differs from upstream. Everything else (Android, the platform
interface dependency, tests, example app) is byte-for-byte the pub.dev release.

### `lib/speech_to_text.dart`

1. `SpeechToText.listen()` gained one new optional parameter:

   ```dart
   Future listen({
     ...
     List<String>? contextualStrings,
   })
   ```

   The words the caller expects to hear — passed straight through to
   `SFSpeechRecognitionRequest.contextualStrings` on iOS. Harmless no-op wherever the platform
   doesn't support it (Android, currently — this app is iOS-only so Android was not touched).

2. Where that list is forwarded is the interesting part, and the reason the diff is bigger than a
   one-line parameter add. The upstream `listen()` always delegates to
   `SpeechToTextPlatform.instance.listen(localeId: ..., options: usedOptions)` — an abstract
   interface (`SpeechListenOptions` in the separate `speech_to_text_platform_interface` package)
   that has no field for this. Properly adding one would mean also forking
   `speech_to_text_platform_interface`, which this task deliberately did not do (only
   `speech_to_text` is vendored).

   Instead, `listen()` now calls a new private `_startListening(options, contextualStrings)`:

   - **`contextualStrings` null or empty (the default — every existing call site, unchanged):**
     delegates to `SpeechToTextPlatform.instance.listen(...)` exactly as upstream did. Zero
     behavioural change, byte-identical code path.
   - **`contextualStrings` non-empty:** builds the `listen` method-channel call's argument map
     itself (mirroring `MethodChannelSpeechToText.listen()` in `speech_to_text_platform_interface`
     2.4.0 field-for-field: `partialResults`, `onDevice`, `listenMode`, `sampleRate`,
     `enableHaptics`, `autoPunctuation`, `pauseFor`, `listenFor`, `localeId`), adds a
     `contextualStrings` key, and invokes `MethodChannel('plugin.csdcorp.com/speech_to_text')`
     directly — the SAME channel name the platform interface already uses, so the native side
     (Swift) sees no difference in how it's invoked, just one extra argument key when present.

   This bypasses `SpeechToTextPlatform.instance` pluggability (web/desktop platform swapping)
   **only** on the branch where a caller actually passes `contextualStrings` — which today is only
   this app's speaking-card call site, on iOS. If this package is ever used on web/desktop with
   `contextualStrings` set, that argument is silently dropped (falls back to the plain path) —
   not a concern for this iOS-only app.

### `darwin/speech_to_text/Sources/speech_to_text/SpeechToTextPlugin.swift`

- `handle(_:result:)`'s `listen` case now reads an optional `contextualStrings` key
  (`[String]`, defaults to `[]` if absent — old callers with no key see identical behaviour) out
  of the call arguments and threads it through to `listenForSpeech(...)`.
- `listenForSpeech(...)` gained a `contextualStrings: [String]` parameter and, right after the
  existing `addsPunctuation` block, sets:

  ```swift
  if !contextualStrings.isEmpty {
    currentRequest.contextualStrings = contextualStrings
  }
  ```

  before starting the recognition task.

- **`initialize` now asks for BOTH permissions on every path** (наряд DAY-GATE-1, Ч.0.2 — the
  second fork-local change, and a straight bug fix rather than a feature).

  Upstream's `switch` on `SFSpeechRecognizer.authorizationStatus()` requested the microphone only
  inside the `.notDetermined` branch, on its way through the speech-recognition prompt. The
  `default:` branch — i.e. speech recognition ALREADY `.authorized` — went straight to
  `setupSpeechRecognition`, never looking at the microphone at all.

  Those are two independent grants. Speech authorized + microphone missing is reachable in ordinary
  use: an install whose first speech grant predates the microphone prompt, or a person who switches
  the microphone off later in Settings → Privacy → Microphone. In that state upstream's
  `initialize` answers **true** while `hasPermission` answers **false**, `listen` starts an
  `AVAudioEngine` over an input node with no permission, no partial result ever arrives, and the
  calling card sits on «Слушаю…» until its own watchdog — with nothing anywhere naming the cause.
  That is the shape of the failure the owner hit on device on 07.09.

  The two branches now share `requestMicrophoneThenSetup(_:)`, and the iOS request itself goes
  through `requestIosMicrophonePermission(_:)`, which uses `AVAudioApplication.requestRecordPermission`
  on iOS 17+ (`AVAudioSession.requestRecordPermission` is deprecated there; the owner's phone runs
  iOS 27) and the old API below it, since the deployment target is 15.0.

  **Upstream-worthy:** yes — this is a defect in upstream, not a local preference. Track with SLV-5.

- **A session the app already runs in `playAndRecord` is left alone** (наряд SESSION-2a §1 — the third
  fork-local change). Upstream swaps the category to `playAndRecord` on every `listen` and, on every stop,
  restores the remembered category and calls `setActive(false, .notifyOthersOnDeactivation)`. Deactivating the
  session stops every player in the app without a completion callback: on the phone (17.09) the partner's reply,
  started the moment a voice answer was accepted, was cut by the stop of the recording that had just ended, and its
  bubble stayed «playing». The day session now raises its session in `playAndRecord` with the recognizer's own
  options (`Pronouncer.warmUp(recording: true)`); `listenForSpeech` sees that category, sets `sessionOwnedByApp`,
  and neither swaps the category nor restores or deactivates it on stop. Any other category — the old behaviour,
  byte for byte.

  **Upstream-worthy:** as an option, perhaps; as is, it is this app's session ownership rule.

### Android — untouched

Per this task's scope (the app is iOS-only), the Android Kotlin plugin was not modified. It
already ignores unknown keys in the `listen` method-channel call, so the added `contextualStrings`
argument is silently and safely dropped there.

## For the next update

When bumping this fork to a newer upstream `speech_to_text` release: re-apply the changes
above (`lib/speech_to_text.dart`'s `listen()`/`_startListening`, and the Swift
`contextualStrings` plumbing) against the new version, re-diff `MethodChannelSpeechToText.listen()`
in whatever `speech_to_text_platform_interface` version pairs with it (the argument map this fork
builds by hand must stay in sync with that method), and update the "Vendored from version" line
above.
