<?php

require_once dirname(__FILE__).'/init-script.php';

$runtime = new ArcanistRuntime();
return $runtime->execute($argv);
