#!/usr/bin/env php
<?php

require_once dirname(__FILE__).'/__init_script__.php';
ini_set('memory_limit', -1);

$args = new PhutilArgumentParser($argv);
$args->parseStandardArguments();

$args->parsePartial(array());


// TODO: This is pretty minimal and should be shared with "arc".
$working_directory = getcwd();
$working_copy = ArcanistWorkingCopyIdentity::newFromPath($working_directory);
$config = id(new ArcanistConfigurationManager())
  ->setWorkingCopyIdentity($working_copy);

foreach ($config->getProjectConfig('load') as $load) {
  $load = Filesystem::resolvePath($working_copy->getProjectRoot().'/'.$load);
  phutil_load_library($load);
}


$workflows = id(new PhutilClassMapQuery())
  ->setAncestorClass('PhageWorkflow')
  ->execute();
$workflows[] = new PhutilHelpArgumentWorkflow();
$args->parseWorkflows($workflows);
