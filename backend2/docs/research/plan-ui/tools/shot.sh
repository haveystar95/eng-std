#!/bin/sh
# shot.sh NAME → docs/research/plan-ui/shots/NAME.png (iPhone 17 Pro simulator; window fronted first so Metal keeps drawing)
open -a Simulator --args -CurrentDeviceUDID 6633B08F-35EA-47D8-99AE-B96791B84058
sleep 1.2
xcrun simctl io 6633B08F-35EA-47D8-99AE-B96791B84058 screenshot --type=png "/Users/yalantisdenys/eng-std/backend2/docs/research/plan-ui/shots/$1.png" >/dev/null 2>&1 && echo "$1"
