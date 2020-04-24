<?php

if ($argc != 2) {
  echo "usage: sleep <duration>\n";
  exit(1);
}

// NOTE: Sleep for the requested duration even if our actual sleep() call is
// interrupted by a signal.

$then = microtime(true) + (double)$argv[1];
while (true) {
  $now = microtime(true);
  if ($now >= $then) {
    break;
  }

  $sleep = max(1, ($then - $now));
  usleep((int)($sleep * 1000000));
}
