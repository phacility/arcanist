<?php

$args = array_slice($argv, 1);
foreach ($args as $key => $arg) {
  $args[$key] = addcslashes($arg, "\\\n");
}
$args = implode($args, "\n");
echo $args;
