<?php

/**
 * Upload a list of @{class:ArcanistFileDataRef} objects over Conduit.
 *
 *   // Create a new uploader.
 *   $uploader = id(new ArcanistFileUploader())
 *     ->setConduitClient($conduit);
 *
 *   // Queue one or more files to be uploaded.
 *   $file = id(new ArcanistFileDataRef())
 *     ->setName('example.jpg')
 *     ->setPath('/path/to/example.jpg');
 *   $uploader->addFile($file);
 *
 *   // Upload the files.
 *   $files = $uploader->uploadFiles();
 *
 * For details about building file references, see @{class:ArcanistFileDataRef}.
 *
 * @task config Configuring the Uploader
 * @task add Adding Files
 * @task upload Uploading Files
 * @task internal Internals
 */
final class ArcanistFileUploader extends Phobject {

  private $conduit;
  private $files;


/* -(  Configuring the Uploader  )------------------------------------------- */


  /**
   * Provide a Conduit client to choose which server to upload files to.
   *
   * @param ConduitClient Configured client.
   * @return this
   * @task config
   */
  public function setConduitClient(ConduitClient $conduit) {
    $this->conduit = $conduit;
    return $this;
  }


/* -(  Adding Files  )------------------------------------------------------- */


  /**
   * Add a file to the list of files to be uploaded.
   *
   * You can optionally provide an explicit key which will be used to identify
   * the file. After adding files, upload them with @{method:uploadFiles}.
   *
   * @param ArcanistFileDataRef File data to upload.
   * @param null|string Optional key to use to identify this file.
   * @return this
   * @task add
   */
  public function addFile(ArcanistFileDataRef $file, $key = null) {

    if ($key === null) {
      $this->files[] = $file;
    } else {
      if (isset($this->files[$key])) {
        throw new Exception(
          pht(
            'Two files were added with identical explicit keys ("%s"); each '.
            'explicit key must be unique.',
            $key));
      }
      $this->files[$key] = $file;
    }

    return $this;
  }


/* -(  Uploading Files  )---------------------------------------------------- */


  /**
   * Upload files to the server.
   *
   * This transfers all files which have been queued with @{method:addFiles}
   * over the Conduit link configured with @{method:setConduitClient}.
   *
   * This method returns a map of all file data references. If references were
   * added with an explicit key when @{method:addFile} was called, the key is
   * retained in the result map.
   *
   * On return, files are either populated with a PHID (indicating a successful
   * upload) or a list of errors. See @{class:ArcanistFileDataRef} for
   * details.
   *
   * @return map<string, ArcanistFileDataRef> Files with results populated.
   * @task upload
   */
  public function uploadFiles() {
    if (!$this->conduit) {
      throw new PhutilInvalidStateException('setConduitClient');
    }

    $files = $this->files;
    foreach ($files as $key => $file) {
      try {
        $file->willUpload();
      } catch (Exception $ex) {
        $file->didFail($ex->getMessage());
        unset($files[$key]);
      }
    }

    $conduit = $this->conduit;
    $futures = array();
    foreach ($files as $key => $file) {
      $futures[$key] = $conduit->callMethod(
        'file.allocate',
        array(
          'name' => $file->getName(),
          'contentLength' => $file->getByteSize(),
          'contentHash' => $file->getContentHash(),
        ));
    }

    $iterator = id(new FutureIterator($futures))->limit(4);
    $chunks = array();
    foreach ($iterator as $key => $future) {
      try {
        $result = $future->resolve();
      } catch (Exception $ex) {
        // The most likely cause for a failure here is that the server does
        // not support `file.allocate`. In this case, we'll try the older
        // upload method below.
        continue;
      }

      $phid = $result['filePHID'];
      $file = $files[$key];

      // We don't need to upload any data. Figure out why not: this can either
      // be because of an error (server can't accept the data) or because the
      // server already has the data.
      if (!$result['upload']) {
        if (!$phid) {
          $file->didFail(
            pht(
              'Unable to upload file: the server refused to accept file '.
              '"%s". This usually means it is too large.',
              $file->getName()));
        } else {
          // These server completed the upload by creating a reference to known
          // file data. We don't need to transfer the actual data, and are all
          // set.
          $file->setPHID($phid);
        }
        unset($files[$key]);
        continue;
      }

      // The server wants us to do an upload.
      if ($phid) {
        $chunks[$key] = array(
          'file' => $file,
          'phid' => $phid,
        );
      }
    }

    foreach ($chunks as $key => $chunk) {
      $file = $chunk['file'];
      $phid = $chunk['phid'];
      try {
        $this->uploadChunks($file, $phid);
        $file->setPHID($phid);
      } catch (Exception $ex) {
        $file->didFail(
          pht(
            'Unable to upload file chunks: %s',
            $ex->getMessage()));
      }
      unset($files[$key]);
    }

    foreach ($files as $key => $file) {
      try {
        $phid = $this->uploadData($file);
        $file->setPHID($phid);
      } catch (Exception $ex) {
        $file->didFail(
          pht(
            'Unable to upload file data: %s',
            $ex->getMessage()));
      }
      unset($files[$key]);
    }

    foreach ($this->files as $file) {
      $file->didUpload();
    }

    return $this->files;
  }


/* -(  Internals  )---------------------------------------------------------- */


  /**
   * Upload missing chunks of a large file by calling `file.uploadchunk` over
   * Conduit.
   *
   * @task internal
   */
  private function uploadChunks(ArcanistFileDataRef $file, $file_phid) {
    $conduit = $this->conduit;

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
      $data = $file->readBytes($chunk['byteStart'], $chunk['byteEnd']);

      $conduit->callMethodSynchronous(
        'file.uploadchunk',
        array(
          'filePHID' => $file_phid,
          'byteStart' => $chunk['byteStart'],
          'dataEncoding' => 'base64',
          'data' => base64_encode($data),
        ));

      $progress->update(1);
    }
  }


  /**
   * Upload an entire file by calling `file.upload` over Conduit.
   *
   * @task internal
   */
  private function uploadData(ArcanistFileDataRef $file) {
    $conduit = $this->conduit;

    $data = $file->readBytes(0, $file->getByteSize());

    return $conduit->callMethodSynchronous(
      'file.upload',
      array(
        'name' => $file->getName(),
        'data_base64' => base64_encode($data),
      ));
  }


  /**
   * Write a status message.
   *
   * @task internal
   */
  private function writeStatus($message) {
    fwrite(STDERR, $message."\n");
  }

}
