import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../session/parts/session_bits.dart';

/// THE VOICE OF THE LEARNER'S OWN LINES (кадр 38-1, наряд FIX-3 §6).
///
/// The learner's lines are spoken by the voice of their profile's gender — one voice across every scene, the server's
/// rule (`PUT /profile` `gender`). IT IS ASKED ONCE, BEFORE THE FIRST PLAN, and never again: until it is said the
/// server speaks male, and the answer stands in the profile, where it can be changed.
///
/// Two plates and one button — no players and no «voice settings»: choosing a voice is not a setting, it is one
/// question with two answers, and «Можно поменять в профиле» takes the fear out of it.
const kVoiceMale = 'male';
const kVoiceFemale = 'female';

/// Returns the gender chosen with «Дальше»; null — the sheet was dismissed and nothing is saved.
Future<String?> showVoiceGenderSheet(BuildContext context, {String? current}) {
  AppHaptics.light();
  return showModalBottomSheet<String>(
    context: context,
    backgroundColor: AppColors.ground,
    barrierColor: AppColors.windowSheetScrim,
    elevation: 0,
    isScrollControlled: true,
    shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(22))),
    builder: (sheet) => _VoiceSheet(current: current ?? kVoiceMale),
  );
}

class _VoiceSheet extends StatefulWidget {
  const _VoiceSheet({required this.current});

  final String current;

  @override
  State<_VoiceSheet> createState() => _VoiceSheetState();
}

class _VoiceSheetState extends State<_VoiceSheet> {
  late String _chosen = widget.current;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final media = MediaQuery.of(context);
    return Padding(
      key: const ValueKey('voice-gender-sheet'),
      padding: EdgeInsets.fromLTRB(24, 24, 24, 24 + media.padding.bottom),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(l.planVoiceTitle, style: AppTextSession.stageTitle),
          const SizedBox(height: 32),
          Row(
            children: [
              Expanded(
                child: _VoicePlate(
                  value: kVoiceMale,
                  title: l.planVoiceMale,
                  hint: l.planVoiceMaleHint,
                  chosen: _chosen == kVoiceMale,
                  onTap: () => _pick(kVoiceMale),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _VoicePlate(
                  value: kVoiceFemale,
                  title: l.planVoiceFemale,
                  hint: l.planVoiceFemaleHint,
                  chosen: _chosen == kVoiceFemale,
                  onTap: () => _pick(kVoiceFemale),
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Text(l.planVoiceInProfile, style: AppTextSession.meta),
          const SizedBox(height: 32),
          SessionDockButton(
            key: const ValueKey('voice-gender-next'),
            label: l.planSessionNext,
            onTap: () => Navigator.of(context).pop(_chosen),
          ),
        ],
      ),
    );
  }

  void _pick(String value) {
    if (_chosen == value) return;
    AppHaptics.light();
    setState(() => _chosen = value);
  }
}

/// ONE PLATE 132 (кадр 38-1): the microphone 20 in the corner, the name 17/600 and its line 13 at the foot; chosen —
/// a sage wash 15 % with a sage check, the rest paper with the sheet's shadow.
class _VoicePlate extends StatelessWidget {
  const _VoicePlate({required this.value, required this.title, required this.hint, required this.chosen, required this.onTap});

  final String value;
  final String title;
  final String hint;
  final bool chosen;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Semantics(
    button: true,
    selected: chosen,
    label: title,
    child: GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: onTap,
      child: AnimatedContainer(
        key: ValueKey('voice-$value'),
        duration: AppMotion.sessionChipSelect,
        height: 132,
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: chosen ? AppColors.sessionSageWash : AppColors.paper,
          borderRadius: BorderRadius.circular(16),
          boxShadow: chosen ? null : kSessionSheetShadow,
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            SizedBox(
              height: 20,
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Icon(LucideIcons.mic, size: 20, color: chosen ? AppColors.verdictKnown : AppColors.tertiary),
                  if (chosen)
                    const SizedBox(
                      key: ValueKey('voice-chosen'),
                      width: 20,
                      height: 20,
                      child: Center(child: Icon(LucideIcons.check, size: 18, color: AppColors.verdictKnown)),
                    ),
                ],
              ),
            ),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(title, style: AppTextSession.sheetTitle),
                const SizedBox(height: 4),
                Text(hint, style: AppTextSession.meta),
              ],
            ),
          ],
        ),
      ),
    ),
  );
}
