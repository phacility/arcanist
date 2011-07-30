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
 * Upload a file to Phabricator.
 *
 * @group workflow
 */
final class ArcanistUploadWorkflow extends ArcanistBaseWorkflow {

  private $paths;
  private $json;

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
      **upload** __file__ [__file__ ...] [--json]
          Supports: filesystems
          Upload a file from local disk.

EOTEXT
      );
  }

  public function getArguments() {
    return array(
      'json' => array(
        'help' => 'Output upload information in JSON format.',
      ),
      '*' => 'paths',
    );
  }

  protected function didParseArguments() {
    if (!$this->getArgument('paths')) {
      throw new ArcanistUsageException("Specify one or more files to upload.");
    }

    $this->paths = $this->getArgument('paths');
    $this->json = $this->getArgument('json');
  }

  public function requiresAuthentication() {
    return true;
  }

  private function getPaths() {
    return $this->paths;
  }

  private function getJSON() {
    return $this->json;
  }

  public function run() {

    $conduit = $this->getConduit();

    $results = array();

    foreach ($this->paths as $path) {
      $name = basename($path);
      $this->writeStatusMessage("Uploading '{$name}'...\n");
      try {
        $data = Filesystem::readFile($path);
      } catch (FilesystemException $ex) {
        $this->writeStatusMessage(
          "Unable to upload file: ".$ex->getMessage()."\n");
        $results[$path] = null;
        continue;
      }

      $phid = $conduit->callMethodSynchronous(
        'file.upload',
        array(
          'data_base64' => base64_encode($data),
          'name'        => $name,
        ));
      $info = $conduit->callMethodSynchronous(
        'file.info',
        array(
          'phid'        => $phid,
        ));

      $results[$path] = $info;

      if (!$this->getJSON()) {
        echo "  {$name}: ".$info['uri']."\n\n";
      }
    }

    if ($this->getJSON()) {
      echo json_encode($results)."\n";
    } else {
      $this->writeStatusMessage("Done.\n");
    }

    return 0;
  }

}
