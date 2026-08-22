import 'package:eng_std/data/models.dart';
import 'package:eng_std/features/training/session/intro_card.dart';
import 'package:eng_std/features/word_card/word_card_screen.dart';
import 'package:eng_std/features/word_card/word_card_subject.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

/// Visual harness for the ACQUISITION LADDER surfaces (кадры 16b / 16d / 16e), rendered with sample
/// data so they can be looked at without a backend, a login or a synced collection.
///
/// It renders the REAL widgets — [SessionIntroCard], [LadderDots], [showWordLadderSheet] — not
/// copies of them, which is the only kind of preview worth trusting. Lives outside `lib/` like
/// `preview.dart`, so its Russian sample copy is exempt from the cyrillic guard.
///
///     flutter run -d <simulator> --target tool/ladder_preview.dart
void main() {
  runApp(const ProviderScope(child: _LadderPreviewApp()));
}

class _LadderPreviewApp extends StatelessWidget {
  const _LadderPreviewApp();

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      debugShowCheckedModeBanner: false,
      theme: buildAppTheme(),
      locale: const Locale('ru'),
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      supportedLocales: AppLocalizations.supportedLocales,
      home: const _Gallery(),
    );
  }
}

/// The sample word every frame is built from — the mock's own «fill out».
final _fillOut = Word(
  termId: '01AAAAAAAAAAAAAAAAAAAAAAA1',
  term: 'fill out',
  translation: 'заполнять (форму, анкету)',
  transcription: 'fɪl aʊt',
  example: 'Please fill out this application to proceed.',
  type: 'phrasal_verb',
  ladderStep: 1, // узнавание — the current rung in the mock
);

final _introCard = SessionCard(
  termId: _fillOut.termId,
  mode: ExerciseMode.intro,
  type: 'phrasal_verb',
  prompt: _fillOut.translation,
  answer: _fillOut.term,
  transcription: _fillOut.transcription,
  example: _fillOut.example,
  acceptedVariants: const ['fill in', 'complete'],
  ladderStep: 0,
);

class _Gallery extends StatelessWidget {
  const _Gallery();

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 3,
      child: Scaffold(
        backgroundColor: AppColors.paper,
        body: SafeArea(
          child: Column(
            children: [
              const TabBar(
                labelColor: AppColors.ink,
                unselectedLabelColor: AppColors.tertiary,
                indicatorColor: AppColors.ink,
                tabs: [
                  Tab(text: '16b'),
                  Tab(text: '16d'),
                  Tab(text: '16e'),
                ],
              ),
              const Expanded(
                child: TabBarView(
                  children: [_IntroFrame(), _ListFrame(), _CardFrame()],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// 16b — знакомство: the word is shown, the single exit is «Понятно →».
class _IntroFrame extends StatelessWidget {
  const _IntroFrame();

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(AppSpacing.screenH, 14, AppSpacing.screenH, 0),
          child: Row(
            children: [
              Text(l.sessionHeaderIntro, style: AppTextExercise.sessionHeader),
              const Spacer(),
              Text('4 из 20', style: AppTextExercise.sessionHeader),
            ],
          ),
        ),
        Expanded(
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(AppSpacing.screenH, 18, AppSpacing.screenH, AppSpacing.s26),
            child: SessionIntroCard(
              card: _introCard,
              autoPronounce: false, // a preview must not talk
              onSpeak: (text, {bool slow = false}) async {},
            ),
          ),
        ),
        Container(
          decoration: const BoxDecoration(
            color: AppColors.paper,
            border: Border(top: BorderSide(color: AppColors.hairline)),
          ),
          padding: const EdgeInsets.fromLTRB(AppSpacing.screenH, 12, AppSpacing.screenH, 12),
          child: PrimaryButton(label: l.sessionIntroGot, onPressed: () {}),
        ),
      ],
    );
  }
}

/// 16d — the ladder folded into the word list: five dots on the right, «— знаю» for a triage known.
class _ListFrame extends StatelessWidget {
  const _ListFrame();

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    // One row per rung, plus the word that is outside the ladder — the whole vocabulary of the
    // component on one screen.
    const rows = <(String, String, int?)>[
      ('fill out', 'заполнять (форму, анкету)', 1),
      ('tenant', 'арендатор', 4),
      ('ATM', 'банкомат', 5),
      ('wire transfer', 'банковский перевод', 0),
      ('menu', 'меню', 3),
    ];

    return ListView(
      padding: const EdgeInsets.only(top: AppSpacing.s16),
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: AppSpacing.screenH),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text('Слова', style: AppText.screenTitle),
              const SizedBox(height: 2),
              Text('24 слова · Банк и платежи', style: AppText.translation.copyWith(fontSize: 13, color: AppColors.secondary)),
              const SizedBox(height: AppSpacing.s16),
            ],
          ),
        ),
        for (final (term, translation, step) in rows)
          _PreviewRow(term: term, translation: translation, step: step),
        _PreviewRow(term: 'invoice', translation: 'счёт', step: null, knownLabel: l.ladderKnownDash),
      ],
    );
  }
}

class _PreviewRow extends StatelessWidget {
  const _PreviewRow({
    required this.term,
    required this.translation,
    required this.step,
    this.knownLabel,
  });

  final String term;
  final String translation;
  final int? step;
  final String? knownLabel;

  @override
  Widget build(BuildContext context) {
    final known = knownLabel != null;
    return DecoratedBox(
      decoration: const BoxDecoration(
        color: AppColors.paper,
        border: Border(bottom: BorderSide(color: AppColors.dividerFaint)),
      ),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(
            AppSpacing.screenH, AppSpacing.wordRowPadV, AppSpacing.screenH, AppSpacing.wordRowPadV),
        child: Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(term,
                      style: known
                          ? AppText.termInList.copyWith(color: AppColors.tertiary)
                          : AppText.termInList),
                  const SizedBox(height: 3),
                  Text(translation,
                      style: AppText.translation.copyWith(
                        fontSize: 13,
                        color: known ? AppColors.tertiary : null,
                      )),
                ],
              ),
            ),
            const SizedBox(width: AppSpacing.s8),
            if (known) LadderKnownDash(label: knownLabel!) else LadderDots(step: step),
          ],
        ),
      ),
    );
  }
}

/// 16e — the word's card: the same dots, captioned and joined by a line.
///
/// The compact sheet this used to open is gone — a word gets the full card wherever it is met
/// («Фаза 3»), so the preview opens that.
class _CardFrame extends StatelessWidget {
  const _CardFrame();

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.screenH),
        child: PrimaryButton(
          label: 'Открыть карточку слова',
          onPressed: () => Navigator.of(context).push(MaterialPageRoute<void>(
            builder: (_) => WordCardScreen(
              subject: WordCardSubject.fromWord(_fillOut),
              mode: WordCardMode.folder,
              onSpeak: () {},
              onTrain: () {},
            ),
          )),
        ),
      ),
    );
  }
}
