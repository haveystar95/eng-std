/// The no-break space a number keeps with what stands before it.
final String _nbsp = String.fromCharCode(0xA0);

final RegExp _leadingDigit = RegExp(r'^\d');

/// «a · b · c» — parts of one line through a dot, joined by the code. A part that starts with a number keeps that number
/// with the dot before it — a no-break space, so a line never breaks between «·» and «52 карточки» (приёмка CLIENT-CONV-1c
/// 22.09; the `.arb` join of two strings is `planDot`).
String dotJoin(Iterable<String> parts) {
  final out = StringBuffer();
  for (final part in parts) {
    if (out.isNotEmpty) out.write(_leadingDigit.hasMatch(part) ? ' ·$_nbsp' : ' · ');
    out.write(part);
  }
  return out.toString();
}
