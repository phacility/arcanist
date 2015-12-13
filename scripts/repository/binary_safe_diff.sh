#!/bin/sh

diff "$@"
RES="$?"
if [ "$RES" = "2" ]; then
  exit 1
fi
exit "$RES"
