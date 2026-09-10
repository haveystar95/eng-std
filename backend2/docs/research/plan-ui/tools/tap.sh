#!/bin/sh
# tap.sh "50%,71%" [more points...] → maestro point taps on the iPhone 17 simulator
export JAVA_HOME=/opt/homebrew/opt/openjdk@21
export PATH="/opt/homebrew/opt/openjdk@21/bin:$PATH"
F=/private/tmp/claude-502/-Users-yalantisdenys-eng-std/501f5e0e-4074-4c92-b5b6-6828916ba30d/scratchpad/_tap.yaml
printf 'appId: com.denis.engstd\n---\n' > "$F"
for p in "$@"; do printf -- '- tapOn:\n    point: "%s"\n' "$p" >> "$F"; done
~/.maestro/bin/maestro --device 6633B08F-35EA-47D8-99AE-B96791B84058 test --no-reinstall-driver "$F" 2>&1 | grep -cE "COMPLETED" >/dev/null && echo tapped
