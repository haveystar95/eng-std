import 'package:flutter/painting.dart';

import 'colors.dart';
import 'typography.dart' show AppFonts;

/// TYPE OF THE START AND THE ACCOUNT — the splash and its sign-in (41-1, 41-4), the five «why» sheets (41-2), the
/// profile and its sheets (42-x), the pre-permission sheets (41-3, 43-1). Values are read off
/// `account-canvas.dc.html`; the sheets of 41-3 / 42-x / 43-1 are the session's exit sheet 30-8 and use
/// [AppTextSession] where the canvas says so.
abstract final class AppTextStart {
  // ── 41-1 / 41-4 ──

  /// «Готов говорить.» under the wordmark — Inter 15/20, grey.
  static const slogan = TextStyle(fontFamily: AppFonts.inter, fontSize: 15, height: 20 / 15, color: AppColors.secondary);

  /// A door's label — Inter 17/600 (colour by the button).
  static const signInButton = TextStyle(fontFamily: AppFonts.inter, fontSize: 17, fontWeight: FontWeight.w600, height: 1.2);

  /// «Не удалось войти. Попробуй ещё раз» — ink 15/20 over the buttons.
  static const signInError = TextStyle(fontFamily: AppFonts.inter, fontSize: 15, height: 20 / 15, color: AppColors.ink);

  /// The legal line — 13/18 grey; its two documents in brass.
  static const legal = TextStyle(fontFamily: AppFonts.inter, fontSize: 13, height: 18 / 13, color: AppColors.tertiary);
  static const legalLink = TextStyle(color: AppColors.brassInk);

  // ── 41-2 ──

  /// «Пропустить» — 13/18 grey.
  static const skip = TextStyle(fontFamily: AppFonts.inter, fontSize: 13, height: 18 / 13, color: AppColors.tertiary);

  /// A sheet's headline — Literata 30/36 500, tracking −0.01em; its sentence dots in brass.
  static const sheetTitle = TextStyle(
    fontFamily: AppFonts.literata,
    fontSize: 30,
    height: 36 / 30,
    fontWeight: FontWeight.w500,
    letterSpacing: -.3,
    color: AppColors.ink,
  );

  /// A sheet's thought — Inter 15/20 grey.
  static const sheetThought = TextStyle(fontFamily: AppFonts.inter, fontSize: 15, height: 20 / 15, color: AppColors.secondary);

  /// A line said in the target language (a, b) — Literata 22/28 500.
  static const sheetLine = TextStyle(
    fontFamily: AppFonts.literata,
    fontSize: 22,
    height: 28 / 22,
    fontWeight: FontWeight.w500,
    color: AppColors.ink,
  );

  /// Its translation (a) — 13/18 grey.
  static const sheetLineNative = TextStyle(fontFamily: AppFonts.inter, fontSize: 13, height: 18 / 13, color: AppColors.tertiary);

  /// The small caps on the scene cards (a) — Inter 11/14 600, tracking .06em, upper case.
  static const sceneCaps = TextStyle(
    fontFamily: AppFonts.inter,
    fontSize: 11,
    height: 14 / 11,
    fontWeight: FontWeight.w600,
    letterSpacing: .66,
    color: AppColors.ink,
  );

  /// c · the brow over the route and the event's date — Inter 11/14 600, tracking .08em, caps, grey.
  static const routeBrow = TextStyle(
    fontFamily: AppFonts.inter,
    fontSize: 11,
    height: 14 / 11,
    fontWeight: FontWeight.w600,
    letterSpacing: .88,
    color: AppColors.tertiary,
  );

  /// c · a day's number and «сегодня» under the route — 11/14 grey.
  static const routeNumber = TextStyle(fontFamily: AppFonts.inter, fontSize: 11, height: 14 / 11, color: AppColors.tertiary);

  /// c · the event's name under its point — 13/18 ink.
  static const routeEvent = TextStyle(fontFamily: AppFonts.inter, fontSize: 13, height: 18 / 13, color: AppColors.ink);

  /// c · a stage's name under its icon — 11/14 (ink for the first two, grey after).
  static const routeStage = TextStyle(fontFamily: AppFonts.inter, fontSize: 11, height: 14 / 11);

  /// d · the translation in the role's bubble — 15/20 grey.
  static const bubbleNative = TextStyle(fontFamily: AppFonts.inter, fontSize: 15, height: 20 / 15, color: AppColors.secondary);

  /// e · a cover's name — Literata 17/22 500.
  static const coverTitle = TextStyle(
    fontFamily: AppFonts.literata,
    fontSize: 17,
    height: 22 / 17,
    fontWeight: FontWeight.w500,
    color: AppColors.ink,
  );

  // ── 42-x · the profile ──

  /// A row's label — 15/20 ink.
  static const rowLabel = TextStyle(fontFamily: AppFonts.inter, fontSize: 15, height: 20 / 15, color: AppColors.ink);

  /// A row's value — 15/20 grey.
  static const rowValue = TextStyle(fontFamily: AppFonts.inter, fontSize: 15, height: 20 / 15, color: AppColors.secondary);

  /// A group's caps label — 11/14 600, tracking .08em, grey.
  static const groupLabel = TextStyle(
    fontFamily: AppFonts.inter,
    fontSize: 11,
    height: 14 / 11,
    fontWeight: FontWeight.w600,
    letterSpacing: .88,
    color: AppColors.tertiary,
  );

  /// The avatar's letter — Literata 26/500 on the paper circle 56.
  static const avatarLetter = TextStyle(fontFamily: AppFonts.literata, fontSize: 26, fontWeight: FontWeight.w500, color: AppColors.ink);

  /// The name beside it — 17/22 600.
  static const name = TextStyle(fontFamily: AppFonts.inter, fontSize: 17, height: 22 / 17, fontWeight: FontWeight.w600, color: AppColors.ink);

  /// «Вход через Apple» — 15/20 ink.
  static const door = rowLabel;

  /// «Выйти» — 15/20 500 ink; «Удалить аккаунт» — the same in terracotta.
  static const signOut = TextStyle(fontFamily: AppFonts.inter, fontSize: 15, height: 20 / 15, fontWeight: FontWeight.w500, color: AppColors.ink);
  static const deleteAccount = TextStyle(
    fontFamily: AppFonts.inter,
    fontSize: 15,
    height: 20 / 15,
    fontWeight: FontWeight.w500,
    color: AppColors.destructiveText,
  );

  /// The version at the foot — 13/18 grey.
  static const version = TextStyle(fontFamily: AppFonts.inter, fontSize: 13, height: 18 / 13, color: AppColors.tertiary);

  /// 42-2: the field's label (13/18 grey) and its text (17/22 ink).
  static const fieldLabel = sheetNote;
  static const field = TextStyle(fontFamily: AppFonts.inter, fontSize: 17, height: 22 / 17, color: AppColors.ink);

  /// 42-4: the wheel — the chosen value 22/500 ink, the others 17 grey.
  static const wheelChosen = TextStyle(
    fontFamily: AppFonts.inter,
    fontSize: 22,
    fontWeight: FontWeight.w500,
    color: AppColors.ink,
    fontFeatures: [FontFeature.tabularFigures()],
  );
  static const wheelOther = TextStyle(
    fontFamily: AppFonts.inter,
    fontSize: 17,
    color: AppColors.tertiary,
    fontFeatures: [FontFeature.tabularFigures()],
  );

  // ── The rescue kit on the plan tab (21-2b) ──

  /// The toggle and the count line — 14 grey.
  static const kitToggle = TextStyle(fontFamily: AppFonts.inter, fontSize: 14, color: AppColors.tertiary);

  /// A line in the target language — Literata 17/1.3 500.
  static const kitLine = TextStyle(fontFamily: AppFonts.literata, fontSize: 17, fontWeight: FontWeight.w500, height: 1.3, color: AppColors.ink);

  /// Its translation — 14 grey.
  static const kitNative = TextStyle(fontFamily: AppFonts.inter, fontSize: 14, color: AppColors.secondary);

  /// «Откроется с подпиской» on the paper dock of the day window (23-0a) — 13/18 grey.
  static const lockedNote = TextStyle(fontFamily: AppFonts.inter, fontSize: 13, height: 18 / 13, color: AppColors.tertiary);

  // ── Sheets over a screen (30-8 recipe) ──

  /// The quiet note under a sheet's sentence — 13/18 grey (42-3 «Подписку отмени в App Store»).
  static const sheetNote = TextStyle(fontFamily: AppFonts.inter, fontSize: 13, height: 18 / 13, color: AppColors.tertiary);

  /// Brass accent inside a Literata line: the sentence dots of 41-2, the wordmark's.
  static const brassDot = TextStyle(color: AppColors.brassInk);
}
