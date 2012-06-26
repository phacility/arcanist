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
      'name'      => 'skip-hello',
      'help'      => 'Do not expect "capability" message when connecting. '.
                     'The server must be configured not to send the message. '.
                     'This deviates from the Mercurial protocol, but slightly '.
                     'improves performance.',
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

$client = new ArcanistHgProxyClient($repo);
$client->setSkipHello($args->getArg('skip-hello'));

$t_start = microtime(true);

$result = $client->executeCommand(
  array('log', '--template', '{node}', '--rev', 2));

$t_end   = microtime(true);
var_dump($result);

echo "\nExecuted in ".((int)(1000000 * ($t_end - $t_start)))."us.\n";
