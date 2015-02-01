<?php

/**
 * Upload a file to Phabricator.
 */
final class ArcanistUploadWorkflow extends ArcanistWorkflow {

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
        'help' => pht('Output upload information in JSON format.'),
      ),
      '*' => 'paths',
    );
  }

  protected function didParseArguments() {
    if (!$this->getArgument('paths')) {
      throw new ArcanistUsageException(
        pht('Specify one or more files to upload.'));
    }

    $this->paths = $this->getArgument('paths');
    $this->json = $this->getArgument('json');
  }

  public function requiresAuthentication() {
    return true;
  }

  public function run() {
    $conduit = $this->getConduit();
    $results = array();

    foreach ($this->paths as $path) {
      $name = basename($path);
      $this->writeStatusMessage(pht("Uploading '%s'...", $name)."\n");

      try {
        $data = Filesystem::readFile($path);
      } catch (FilesystemException $ex) {
        $this->writeStatusMessage(
          pht('Unable to upload file: %s.', $ex->getMessage())."\n");
        $results[$path] = null;
        continue;
      }

      $phid = $conduit->callMethodSynchronous(
        'file.upload',
        array(
          'data_base64' => base64_encode($data),
          'name' => $name,
        ));
      $info = $conduit->callMethodSynchronous(
        'file.info',
        array(
          'phid' => $phid,
        ));

      $results[$path] = $info;

      if (!$this->json) {
        $id = $info['id'];
        echo "  F{$id} {$name}: ".$info['uri']."\n\n";
      }
    }

    if ($this->json) {
      echo json_encode($results)."\n";
    } else {
      $this->writeStatusMessage(pht('Done.')."\n");
    }

    return 0;
  }

}
