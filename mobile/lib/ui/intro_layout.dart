import 'package:flutter/material.dart';

import 'package:eng_std/theme/theme.dart';

import 'play_circle.dart';

/// ЗНАКОМСТВО ВО ВЕСЬ ЭКРАН (кадр 16a в «Базе», 23-1/23-13 в плане) — не карточка в карточке, а
/// экран: фото на всю ширину 220 сверху, под ним слово Literata 46 с воспроизведением 44, перевод
/// 17, чтение в слэшах 14, хайрлайн, пример курсивом с подчёркнутым словом, «также:». Одна краска —
/// фото. «Понятно» — на доке экрана, не здесь.
///
/// Один макет для коллекций и плана: [SessionIntroCard] и `WordIntroCard` дня подают в него свои
/// данные. Без фото ([photo] null) фото-полосы нет — верх начинается с бейджа.
class IntroLayout extends StatelessWidget {
  const IntroLayout({
    super.key,
    required this.term,
    required this.translation,
    required this.onSpeak,
    this.photo,
    this.badge,
    this.reading,
    this.example,
    this.exampleTranslation,
    this.also,
    this.below,
    this.termStyle = AppTextDay.wordBig,
  });

  final ImageProvider? photo;

  /// Бейдж над словом: контурный «новое слово» или латунная пилюля «Вернулось из дня 1».
  final Widget? badge;
  final String term;
  final TextStyle termStyle;
  final String translation;

  /// Чтение — транскрипция или кириллица, уже В СЛЭШАХ (правило 06).
  final String? reading;

  /// Пример с подчёркнутым словом — собранный `InlineSpan` (подчёркивание ставит вызывающий).
  final InlineSpan? example;
  final String? exampleTranslation;

  /// «также: fill in · complete».
  final String? also;

  /// Что стоит под примером — эхо «повтори вслух» у коллекций, ничего у плана.
  final Widget? below;
  final VoidCallback onSpeak;

  static const photoHeight = 220.0;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      if (photo != null)
        SizedBox(
          height: photoHeight,
          width: double.infinity,
          child: ColoredBox(
            color: AppColors.photoSlot,
            child: Image(image: photo!, fit: BoxFit.cover, errorBuilder: (_, _, _) => const SizedBox.shrink()),
          ),
        ),
      Padding(
        padding: const EdgeInsets.fromLTRB(AppSpacing.screenH, 18, AppSpacing.screenH, 0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (badge != null) ...[
              Align(alignment: Alignment.centerLeft, child: badge),
              const SizedBox(height: 14),
            ],
            Row(
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [
                Expanded(child: Text(term, style: termStyle)),
                const SizedBox(width: 14),
                PlayCircle(onTap: onSpeak, size: 44),
              ],
            ),
            const SizedBox(height: 14),
            Text(translation, style: AppTextDay.introTranslation),
            if (reading != null && reading!.isNotEmpty) ...[
              const SizedBox(height: 8),
              Text(reading!, style: AppTextDay.introReading),
            ],
            if (example != null) ...[
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 20),
                child: Divider(height: 1, thickness: 1, color: AppColors.hairlineSoft),
              ),
              Text.rich(example!, style: AppTextDay.example),
              if (exampleTranslation != null && exampleTranslation!.isNotEmpty) ...[
                const SizedBox(height: 4),
                Text(exampleTranslation!, style: AppTextDay.exampleTranslation),
              ],
            ],
            if (also != null && also!.isNotEmpty) ...[
              const SizedBox(height: 14),
              Text(also!, style: AppTextExercise.introAlso.copyWith(fontSize: 12.5)),
            ],
            if (below != null) ...[const SizedBox(height: 20), below!],
          ],
        ),
      ),
    ],
  );
}

/// Пример с ПОДЧЁРКНУТЫМ куском — 2 px `rgba(46,38,32,.55)` под словом на знакомстве (16a) или
/// 2 px ink под ключом фразы (23-4). [at] и [length] — где кусок стоит; вне текста — без выделения.
InlineSpan underlinedExample(String text, {required int at, required int length, Color color = AppColors.exampleUnderline}) {
  if (at < 0 || length <= 0 || at + length > text.length) return TextSpan(text: text);

  return TextSpan(
    children: [
      TextSpan(text: text.substring(0, at)),
      TextSpan(
        text: text.substring(at, at + length),
        style: TextStyle(
          color: AppColors.ink,
          decoration: TextDecoration.underline,
          decorationColor: color,
          decorationThickness: 2,
        ),
      ),
      TextSpan(text: text.substring(at + length)),
    ],
  );
}
