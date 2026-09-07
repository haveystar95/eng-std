#!/usr/bin/env bash
# СБОРКА НА ТЕЛЕФОН СО ШТАМПОМ ВЕРСИИ — наряд DAY-GATE-1, Ч.0.4.
#
# Зачем скрипт, а не строчка в CLAUDE.md: 07.09 телефон показывал одно поведение, репозиторий —
# другой код, и не было НИ ОДНОГО способа сказать, ту ли сборку смотрят. Версия, которую вбивают
# руками, отвечает на этот вопрос ровно до первого раза, когда её забыли обновить, — то есть не
# отвечает. Здесь она берётся из git и попадает в бинарник через `--dart-define`.
#
# Он же ставит штамп СЕРВЕРУ: `backend2/storage/app/commit`. Контейнер монтирует только `backend2/`,
# `.git` лежит уровнем выше, и «спросить git» изнутри контейнера нечем — поэтому штамп кладут
# снаружи, тем же движением, что и клиентский. Обе половины строки версии на экране приезжают из
# одного места, и разъехаться им негде.
#
#   ./scripts/build_ios.sh                      — собрать .app (release)
#   ./scripts/build_ios.sh run <device-id>      — собрать и поставить на устройство
#   ./scripts/build_ios.sh defines              — только напечатать флаги (для ручного вызова)
#
# ВНИМАНИЕ про грязное дерево: SHA описывает КОММИТ, а не рабочую копию. Несохранённые правки
# помечаются суффиксом `+`, потому что «сборка из коммита abc1234» и «сборка из коммита abc1234
# плюс что-то» — это две разные сборки, и путать их дороже всего.
set -euo pipefail

cd "$(dirname "$0")/.."
REPO_ROOT="$(cd .. && pwd)"

SHA="$(git rev-parse --short=8 HEAD)"
if ! git diff --quiet HEAD -- . "$REPO_ROOT/backend2"; then
  SHA="${SHA}+"
fi
BUILT_AT="$(date '+%Y-%m-%d %H:%M')"

DEFINES=(
  "--dart-define=BUILD_SHA=${SHA}"
  "--dart-define=BUILD_AT=${BUILT_AT}"
)

# Штамп серверу — тот же коммит, то же движение. Папка существует у любой установки Laravel.
printf '%s\n' "$SHA" > "$REPO_ROOT/backend2/storage/app/commit"

case "${1:-build}" in
  defines)
    printf '%s\n' "${DEFINES[@]}"
    ;;
  run)
    DEVICE="${2:?usage: build_ios.sh run <device-id>}"
    PATH="/opt/homebrew/bin:$PATH" LANG=en_US.UTF-8 \
      flutter run --release -d "$DEVICE" "${DEFINES[@]}"
    ;;
  build)
    PATH="/opt/homebrew/bin:$PATH" LANG=en_US.UTF-8 \
      flutter build ios --release "${DEFINES[@]}"
    ;;
  *)
    echo "usage: build_ios.sh [build|run <device-id>|defines]" >&2
    exit 64
    ;;
esac
