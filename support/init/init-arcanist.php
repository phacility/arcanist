<?php

require_once dirname(__FILE__).'/init-script.php';

$runtime = new ArcanistRuntime();
$err = $runtime->execute($argv);

exit($err);
