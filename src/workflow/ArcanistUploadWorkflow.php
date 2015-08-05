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

    $uploader = id(new ArcanistFileUploader())
      ->setConduitClient($conduit);

    foreach ($this->paths as $path) {
      $file = id(new ArcanistFileDataRef())
        ->setName(basename($path))
        ->setPath($path);

      $uploader->addFile($file);
    }

    $files = $uploader->uploadFiles();

    $results = array();
    foreach ($files as $file) {
      // TODO: This could be handled more gracefully; just preserving behavior
      // until we introduce `file.query` and modernize this.
      if ($file->getErrors()) {
        throw new Exception(implode("\n", $file->getErrors()));
      }
      $phid = $file->getPHID();
      $name = $file->getName();

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
      $this->writeStatus(pht('Done.'));
    }

    return 0;
  }

  private function writeStatus($line) {
    $this->writeStatusMessage($line."\n");
  }

  private function uploadChunks($file_phid, $path) {
    $conduit = $this->getConduit();

    $f = @fopen($path, 'rb');
    if (!$f) {
      throw new Exception(pht('Unable to open file "%s"', $path));
    }

    $this->writeStatus(pht('Beginning chunked upload of large file...'));
    $chunks = $conduit->callMethodSynchronous(
      'file.querychunks',
      array(
        'filePHID' => $file_phid,
      ));

    $remaining = array();
    foreach ($chunks as $chunk) {
      if (!$chunk['complete']) {
        $remaining[] = $chunk;
      }
    }

    $done = (count($chunks) - count($remaining));

    if ($done) {
      $this->writeStatus(
        pht(
          'Resuming upload (%d of %d chunks remain).',
          new PhutilNumber(count($remaining)),
          new PhutilNumber(count($chunks))));
    } else {
      $this->writeStatus(
        pht(
          'Uploading chunks (%d chunks to upload).',
          new PhutilNumber(count($remaining))));
    }

    $progress = new PhutilConsoleProgressBar();
    $progress->setTotal(count($chunks));

    for ($ii = 0; $ii < $done; $ii++) {
      $progress->update(1);
    }

    $progress->draw();

    // TODO: We could do these in parallel to improve upload performance.
    foreach ($remaining as $chunk) {
      $offset = $chunk['byteStart'];

      $ok = fseek($f, $offset);
      if ($ok !== 0) {
        throw new Exception(
          pht(
            'Failed to %s!',
            'fseek()'));
      }

      $data = fread($f, $chunk['byteEnd'] - $chunk['byteStart']);
      if ($data === false) {
        throw new Exception(
          pht(
            'Failed to %s!',
            'fread()'));
      }

      $conduit->callMethodSynchronous(
        'file.uploadchunk',
        array(
          'filePHID' => $file_phid,
          'byteStart' => $offset,
          'dataEncoding' => 'base64',
          'data' => base64_encode($data),
        ));

      $progress->update(1);
    }
  }

}
