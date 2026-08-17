import 'package:flutter/painting.dart';

/// Third-party brand colours, isolated here so the monochrome rule (and the hex-guard) hold in the
/// rest of the app: the ONLY place brand colour appears is a sign-in provider's own logo. Values are
/// the published Google brand palette. Apple's mark ships inside the `sign_in_with_apple` button.
abstract final class GoogleBrand {
  static const blue = Color(0xFF4285F4);
  static const red = Color(0xFFEA4335);
  static const yellow = Color(0xFFFBBC05);
  static const green = Color(0xFF34A853);
}
