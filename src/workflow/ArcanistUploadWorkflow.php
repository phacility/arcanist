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
      $path = Filesystem::resolvePath($path);

      $name = basename($path);
      $this->writeStatus(pht("Uploading '%s'...", $name));

      $hash = @sha1_file($path);
      if (!$hash) {
        throw new Exception(pht('Unable to read file "%s"!', $path));
      }
      $length = filesize($path);

      $do_chunk_upload = false;

      $phid = null;
      try {
        $result = $conduit->callMethodSynchronous(
          'file.allocate',
          array(
            'name' => $name,
            'contentLength' => $length,
            'contentHash' => $hash,
          ));

        $phid = $result['filePHID'];
        if (!$result['upload']) {
          if (!$phid) {
            $this->writeStatus(
              pht(
                'Unable to upload file "%s": the server refused to accept '.
                'it. This usually means it is too large.',
                $name));
            continue;
          }
          // Otherwise, the server completed the upload by referencing known
          // file data.
        } else {
          if ($phid) {
            $do_chunk_upload = true;
          } else {
            // This is a small file that doesn't need to be uploaded in
            // chunks, so continue normally.
          }
        }
      } catch (Exception $ex) {
        $this->writeStatus(
          pht('Unable to use allocate method, trying older upload method.'));
      }

      if ($do_chunk_upload) {
        $this->uploadChunks($phid, $path);
      }

      if (!$phid) {
        try {
          $data = Filesystem::readFile($path);
        } catch (FilesystemException $ex) {
          $this->writeStatus(
            pht('Unable to read file "%s".', $ex->getMessage()));
          $results[$path] = null;
          continue;
        }

        $phid = $conduit->callMethodSynchronous(
          'file.upload',
          array(
            'data_base64' => base64_encode($data),
            'name' => $name,
          ));
      }

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
