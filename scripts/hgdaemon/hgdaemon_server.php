#!/usr/bin/env php
<?php

require_once dirname(dirname(__FILE__)).'/__init_script__.php';

$args = new PhutilArgumentParser($argv);
$args->parseStandardArguments();
$args->parse(
  array(
    array(
      'name'      => 'quiet',
      'help'      => pht('Do not print status messages to stdout.'),
    ),
    array(
      'name'      => 'skip-hello',
      'help'      => pht(
        'Do not send "capability" message when clients connect. Clients '.
        'must be configured not to expect the message. This deviates '.
        'from the Mercurial protocol, but slightly improves performance.'),
    ),
    array(
      'name'      => 'do-not-daemonize',
      'help'      => pht('Remain in the foreground instead of daemonizing.'),
    ),
    array(
      'name'      => 'client-limit',
      'param'     => 'limit',
      'help'      => pht('Exit after serving __limit__ clients.'),
    ),
    array(
      'name'      => 'idle-limit',
      'param'     => 'seconds',
      'help'      => pht('Exit after __seconds__ spent idle.'),
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

id(new ArcanistHgProxyServer($repo))
  ->setQuiet($args->getArg('quiet'))
  ->setClientLimit($args->getArg('client-limit'))
  ->setIdleLimit($args->getArg('idle-limit'))
  ->setDoNotDaemonize($args->getArg('do-not-daemonize'))
  ->setSkipHello($args->getArg('skip-hello'))
  ->start();
