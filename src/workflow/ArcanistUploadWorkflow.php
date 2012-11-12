<?php

/**
 * Upload a file to Phabricator.
 *
 * @group workflow
 */
final class ArcanistUploadWorkflow extends ArcanistBaseWorkflow {

  private $paths;
  private $json;

  public function getWorkflowName() {
    return 'upload';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **upload** __file__ [__file__ ...] [--json]
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
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
        $id = $info['id'];
        echo "  F{$id} {$name}: ".$info['uri']."\n\n";
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
