# Eng Std — мобильное приложение (Flutter)

Персональный тренажёр английских слов: карточки с интервальным повторением (FSRS на бэкенде),
произношение (TTS), коллекции слов и генерация новых коллекций через ИИ (Claude).

## Стек
- Flutter 3.44 / Dart 3.12
- Riverpod — состояние
- Dio — HTTP к Laravel API
- flutter_tts — озвучка слов

## Структура
```
lib/
  core/        config.dart (API URL, токен, демо-режим), theme.dart
  data/        models.dart, api_client.dart, demo_data.dart, providers.dart
  features/
    home/        нижняя навигация
    training/    экран тренировки (карточки + оценки again/hard/good/easy)
    collections/ список коллекций, детали, диалог ИИ-генерации
```

## Запуск

### Демо-режим (без бэкенда)
Приложение сразу работает на встроенных примерах — можно листать карточки и коллекции:
```bash
flutter run
```

### С бэкендом Laravel
```bash
flutter run \
  --dart-define=API_BASE_URL=http://192.168.1.10:8000 \
  --dart-define=API_TOKEN=<sanctum-token>
```
(IP машины с Laravel — не `localhost`, если запускаешь на реальном iPhone.)

## На свой iPhone
1. Подключи телефон, доверься компьютеру.
2. `open ios/Runner.xcworkspace` → в Xcode выбери свою команду (Signing & Capabilities) и Bundle ID.
3. `flutter run -d <device-id>` либо запуск из Xcode.

API-контракт с бэкендом: см. [../docs/API_CONTRACT.md](../docs/API_CONTRACT.md).
