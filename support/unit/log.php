#!/usr/bin/env php
<?php

$arcanist_root = dirname(dirname(dirname(__FILE__)));
require_once $arcanist_root.'/scripts/init/init-script.php';

$logs = array();
for ($ii = 0; $ii < $argv[1]; $ii++) {
  $logs[] = new PhutilDeferredLog($argv[2], 'abcdefghijklmnopqrstuvwxyz');
}
