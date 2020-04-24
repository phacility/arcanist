<?php

final class ArcanistUploadWorkflow
  extends ArcanistArcWorkflow {

  public function getWorkflowName() {
    return 'upload';
  }

  public function getWorkflowInformation() {
    $help = pht(<<<EOTEXT
Upload one or more files from local disk to Phabricator.
EOTEXT
);

    return $this->newWorkflowInformation()
      ->setSynopsis(pht('Upload files.'))
      ->addExample(pht('**upload** [__options__] -- __file__ [__file__ ...]'))
      ->setHelp($help);
  }

  public function getWorkflowArguments() {
    return array(
      $this->newWorkflowArgument('json')
        ->setHelp(pht('Output upload information in JSON format.')),
      $this->newWorkflowArgument('temporary')
        ->setHelp(
          pht(
            'Mark the file as temporary. Temporary files will be '.
            'deleted after 24 hours.')),
      $this->newWorkflowArgument('paths')
        ->setWildcard(true)
        ->setIsPathArgument(true),
    );
  }

  public function runWorkflow() {
    if (!$this->getArgument('paths')) {
      throw new PhutilArgumentUsageException(
        pht('Specify one or more paths to files you want to upload.'));
    }

    $is_temporary = $this->getArgument('temporary');
    $is_json = $this->getArgument('json');
    $paths = $this->getArgument('paths');

    $conduit = $this->getConduitEngine();
    $results = array();

    $uploader = id(new ArcanistFileUploader())
      ->setConduitEngine($conduit);

    foreach ($paths as $key => $path) {
      $file = id(new ArcanistFileDataRef())
        ->setName(basename($path))
        ->setPath($path);

      if ($is_temporary) {
        $expires_at = time() + phutil_units('24 hours in seconds');
        $file->setDeleteAfterEpoch($expires_at);
      }

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

      $info = $conduit->resolveCall(
        'file.info',
        array(
          'phid' => $phid,
        ));

      $results[$path] = $info;

      if (!$is_json) {
        $id = $info['id'];
        echo "  F{$id} {$name}: ".$info['uri']."\n\n";
      }
    }

    if ($is_json) {
      $output = id(new PhutilJSON())->encodeFormatted($results);
      echo $output;
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
    $chunks = $conduit->resolveCall(
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
          'Resuming upload (%s of %s chunks remain).',
          phutil_count($remaining),
          phutil_count($chunks)));
    } else {
      $this->writeStatus(
        pht(
          'Uploading chunks (%s chunks to upload).',
          phutil_count($remaining)));
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

      $conduit->resolveCall(
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
