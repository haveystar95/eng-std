FROM php:8.4-cli

# `lame` — КОДИРОВЩИК MP3 для озвучки реплик (наряд TTS-1).
#
# Gemini TTS отвечает сырым PCM, а в докачку на телефон он поедет как WAV — втрое тяжелее mp3 того
# же места (3.5 МБ на план против 0.6). Ставится `lame`, а не ffmpeg: нужна ровно одна операция —
# «PCM 24 кГц моно → mp3», и она у lame один вызов и 2 МБ образа против ~300 МБ у ffmpeg.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libpq-dev libicu-dev lame \
    && docker-php-ext-install pdo pdo_pgsql pgsql bcmath zip intl pcntl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
