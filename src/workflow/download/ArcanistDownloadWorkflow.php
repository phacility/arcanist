<?php

/*
 * Copyright 2011 Facebook, Inc.
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

/**
 * Download a file from Phabricator.
 *
 * @group workflow
 */
final class ArcanistDownloadWorkflow extends ArcanistBaseWorkflow {

  private $id;
  private $saveAs;
  private $show;

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
      **download** __file__ [--as __name__] [--show]
          Supports: filesystems
          Download a file to local disk, e.g.:

            $ arc download F33              # Download file 'F33'

EOTEXT
      );
  }

  public function getArguments() {
    return array(
      'show' => array(
        'conflicts' => array(
          'as' => 'Use --show to direct the file to stdout, or --as to direct '.
                  'it to a named location.',
        ),
        'help' => 'Write file to stdout instead of to disk.',
      ),
      'as' => array(
        'param' => 'name',
        'help' => 'Save the file with a specific name rather than the default.',
      ),
      '*' => 'argv',
    );
  }

  protected function didParseArguments() {
    $argv = $this->getArgument('argv');
    if (!$argv) {
      throw new ArcanistUsageException("Specify a file to download.");
    }
    if (count($argv) > 1) {
      throw new ArcanistUsageException("Specify exactly one file to download.");
    }

    $file = reset($argv);
    if (!preg_match('/^F?\d+/', $file)) {
      throw new ArcanistUsageException("Specify file by ID, e.g. F123.");
    }

    $this->id = (int)ltrim($file, 'F');
    $this->saveAs = $this->getArgument('as');
    $this->show = $this->getArgument('show');
  }

  public function requiresAuthentication() {
    return true;
  }

  public function run() {

    $conduit = $this->getConduit();

    $this->writeStatusMessage("Getting file information...\n");
    $info = $conduit->callMethodSynchronous(
      'file.info',
      array(
        'id' => $this->id,
      ));

    $bytes = number_format($info['byteSize']);
    $desc = '('.$bytes.' bytes)';
    if ($info['name']) {
      $desc = "'".$info['name']."' ".$desc;
    }

    $this->writeStatusMessage("Downloading file {$desc}...\n");
    $data = $conduit->callMethodSynchronous(
      'file.download',
      array(
        'phid' => $info['phid'],
      ));

    $data = base64_decode($data);

    if ($this->show) {
      echo $data;
    } else {
      $path = Filesystem::writeUniqueFile(
        nonempty($this->saveAs, $info['name'], 'file'),
        $data);
      $this->writeStatusMessage("Saved file as '{$path}'.\n");
    }

    return 0;
  }

}
