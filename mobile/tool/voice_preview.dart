/// ГОЛОСА РЕПЛИК и «Готовим озвучку» — харнесс наряда TTS-1.
///
/// Два экрана, оба про озвучку и оба без сервера:
///
///  * **Голоса** — тот самый дев-экран Ч.2.4 (`VoiceBakeoffScreen`), не копия: он играет
///    настоящие образцы из бандла и настоящий системный синтез. Здесь он открыт напрямую, потому
///    что вход в него живёт за логином, а прослушивать голоса логин не требуется.
///  * **Диалог** — оболочка разговора в ДВУХ состояниях озвучки: реплика готова (кадр DL·06) и
///    реплика ещё качается (кадр DL·08, «Готовим озвучку»). Ровно та разница, которую канон §7
///    называет «никакая реплика не подаётся на слух, пока озвучка не готова», — и увидеть её рядом
///    на одном экране проще, чем ловить в живом дне.
///
/// Реплики — живые, из плана `01M1M0AY6M3DH9HN4MCQW6FRE0` (то же собеседование, что в замере).
///
/// ```bash
/// PATH="/opt/homebrew/bin:$PATH" LANG=en_US.UTF-8 \
///   flutter run --debug -d <simulator-udid> --target tool/voice_preview.dart
/// ```
library;

import 'package:flutter/material.dart';

import 'package:eng_std/data/locale_controller.dart' show kSupportedLocales;
import 'package:eng_std/data/models.dart' show PlanDialogue, PlanDialogueTurn;
import 'package:eng_std/features/plan/plan_dialogue.dart';
import 'package:eng_std/features/plan/plan_ui.dart';
import 'package:eng_std/features/profile/voice_bakeoff_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

void main() => runApp(const _VoicePreviewApp());

final _dialogue = PlanDialogue(
  dayIndex: 1,
  sceneTitle: 'Начало собеседования',
  sceneIntro: 'Вы на видеозвонке. Собеседник здоровается и просит рассказать о себе.',
  turns: const [
    PlanDialogueTurn(turn: 'role', termId: 't1', text: 'Thanks for joining today.', shelf: 'hear'),
    PlanDialogueTurn(turn: 'you', termId: 't2', text: 'Nice to meet you.', shelf: 'say'),
    PlanDialogueTurn(
      turn: 'role',
      termId: 't3',
      text: 'Could you briefly introduce yourself?',
      shelf: 'hear',
    ),
    PlanDialogueTurn(turn: 'you', termId: 't4', text: 'I am a software developer.', shelf: 'say'),
  ],
);

const _rescue = <({String text, String? translation})>[
  (text: 'Could you speak more slowly, please?', translation: 'Помедленнее, пожалуйста.'),
  (text: 'Could you repeat that, please?', translation: 'Повторите ещё раз, пожалуйста.'),
  (text: 'One moment, let me check.', translation: 'Секунду, я проверю.'),
];

class _VoicePreviewApp extends StatelessWidget {
  const _VoicePreviewApp();

  @override
  Widget build(BuildContext context) => MaterialApp(
    debugShowCheckedModeBanner: false,
    theme: buildAppTheme(),
    locale: const Locale('ru'),
    localizationsDelegates: AppLocalizations.localizationsDelegates,
    supportedLocales: kSupportedLocales,
    home: const _Home(),
  );
}

class _Home extends StatefulWidget {
  const _Home();

  @override
  State<_Home> createState() => _HomeState();
}

class _HomeState extends State<_Home> {
  /// Which pane opens first. `--dart-define=TTS_TAB=2` открывает нужную сразу — на iOS это
  /// единственный работающий способ: `Platform.environment` там пуст, и `simctl launch --setenv`
  /// до Dart не доходит (проверено).
  int _tab = const int.fromEnvironment('TTS_TAB');

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: AppColors.paper,
    body: SafeArea(
      child: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 10, 16, 6),
            child: Row(
              children: [
                for (var i = 0; i < 3; i++) ...[
                  Expanded(
                    child: InkWell(
                      onTap: () => setState(() => _tab = i),
                      child: Container(
                        padding: const EdgeInsets.symmetric(vertical: 9),
                        decoration: BoxDecoration(
                          color: _tab == i
                              ? AppColors.brassInk.withValues(alpha: .12)
                              : Colors.transparent,
                          border: Border.all(color: AppColors.brassInk.withValues(alpha: .3)),
                          borderRadius: BorderRadius.circular(999),
                        ),
                        child: Text(
                          const ['Голоса', 'Диалог · готово', 'Диалог · качается'][i],
                          textAlign: TextAlign.center,
                          style: AppText.blockLabel.copyWith(color: AppColors.brassInk),
                        ),
                      ),
                    ),
                  ),
                  if (i < 2) const SizedBox(width: 6),
                ],
              ],
            ),
          ),
          Expanded(
            child: switch (_tab) {
              0 => const VoiceBakeoffScreen(),
              _ => _DialoguePreview(voiceReady: _tab == 1),
            },
          ),
        ],
      ),
    ),
  );
}

class _DialoguePreview extends StatelessWidget {
  const _DialoguePreview({required this.voiceReady});

  final bool voiceReady;

  @override
  Widget build(BuildContext context) => ListView(
    padding: const EdgeInsets.fromLTRB(
      AppSpacing.screenH,
      AppSpacing.s16,
      AppSpacing.screenH,
      AppSpacing.s26,
    ),
    children: [
      PlanDialogueShell(
        dialogue: _dialogue,
        turnIndex: 3,
        voiceReady: voiceReady,
        rescue: _rescue,
        answeredAloud: const {'t2'},
        onSpeak: (_) {},
        card: PaperCard(
          radius: 16,
          padding: const EdgeInsets.fromLTRB(18, 18, 18, 18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const PlanLabel('Что ты ответишь?'),
              const SizedBox(height: 12),
              for (final option in const [
                'I am a software developer.',
                'I am here for this role.',
                'Yes, I can hear you clearly.',
              ]) ...[
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                  margin: const EdgeInsets.only(bottom: 8),
                  decoration: BoxDecoration(
                    border: Border.all(color: AppColors.dividerFaint),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Text(option, style: AppText.termInList.copyWith(fontSize: 15.5)),
                ),
              ],
            ],
          ),
        ),
      ),
    ],
  );
}
