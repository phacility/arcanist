#!/usr/bin/env php
<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

require_once dirname(dirname(__FILE__)).'/__init_script__.php';

$args = new PhutilArgumentParser($argv);
$args->parseStandardArguments();
$args->parse(
  array(
    array(
      'name'      => 'quiet',
      'help'      => 'Do not print status messages to stdout.',
    ),
    array(
      'name'      => 'skip-hello',
      'help'      => 'Do not send "capability" message when clients connect. '.
                     'Clients must be configured not to expect the message. '.
                     'This deviates from the Mercurial protocol, but slightly '.
                     'improves performance.',
    ),
    array(
      'name'      => 'do-not-daemonize',
      'help'      => 'Remain in the foreground instead of daemonizing.',
    ),
    array(
      'name'      => 'client-limit',
      'param'     => 'limit',
      'help'      => 'Exit after serving __limit__ clients.',
    ),
    array(
      'name'      => 'idle-limit',
      'param'     => 'seconds',
      'help'      => 'Exit after __seconds__ spent idle.',
    ),
    array(
      'name'      => 'repository',
      'wildcard'  => true,
    ),
  ));

$repo = $args->getArg('repository');
if (count($repo) !== 1) {
  throw new Exception("Specify exactly one working copy!");
}
$repo = head($repo);

id(new ArcanistHgProxyServer($repo))
  ->setQuiet($args->getArg('quiet'))
  ->setClientLimit($args->getArg('client-limit'))
  ->setIdleLimit($args->getArg('idle-limit'))
  ->setDoNotDaemonize($args->getArg('do-not-daemonize'))
  ->setSkipHello($args->getArg('skip-hello'))
  ->start();
