#!/usr/bin/env php
<?php

require_once dirname(dirname(__FILE__)).'/__init_script__.php';

$args = new PhutilArgumentParser($argv);
$args->parseStandardArguments();
$args->parse(
  array(
    array(
      'name'      => 'skip-hello',
      'help'      => pht(
        'Do not expect "capability" message when connecting. The server '.
        'must be configured not to send the message. This deviates from '.
        'the Mercurial protocol, but slightly improves performance.'),
    ),
    array(
      'name'      => 'repository',
      'wildcard'  => true,
    ),
  ));

$repo = $args->getArg('repository');
if (count($repo) !== 1) {
  throw new Exception(pht('Specify exactly one working copy!'));
}
$repo = head($repo);

$client = new ArcanistHgProxyClient($repo);
$client->setSkipHello($args->getArg('skip-hello'));

$t_start = microtime(true);

$result = $client->executeCommand(
  array('log', '--template', '{node}', '--rev', 2));

$t_end = microtime(true);
var_dump($result);

$console = PhutilConsole::getConsole();
$console->writeOut(
  "\n%s\n",
  pht(
    'Executed in %d us.',
    ((int)(1000000 * ($t_end - $t_start)))));
