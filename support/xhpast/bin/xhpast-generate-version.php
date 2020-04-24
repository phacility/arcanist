#!/usr/bin/env php
<?php

$arcanist_root = dirname(dirname(dirname(dirname(__FILE__))));
require_once $arcanist_root.'/support/init/init-script.php';

echo PhutilXHPASTBinary::EXPECTED_VERSION;
echo "\n";
