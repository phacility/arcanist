<?php

/**
 * Download a file from Phabricator.
 */
final class ArcanistDownloadWorkflow extends ArcanistWorkflow {

  private $id;
  private $saveAs;
  private $show;

  public function getWorkflowName() {
    return 'download';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **download** __file__ [--as __name__] [--show]
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
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
          'as' => pht(
            'Use %s to direct the file to stdout, or %s to direct '.
            'it to a named location.',
            '--show',
            '--as'),
        ),
        'help' => pht('Write file to stdout instead of to disk.'),
      ),
      'as' => array(
        'param' => 'name',
        'help' => pht(
          'Save the file with a specific name rather than the default.'),
      ),
      '*' => 'argv',
    );
  }

  protected function didParseArguments() {
    $argv = $this->getArgument('argv');
    if (!$argv) {
      throw new ArcanistUsageException(pht('Specify a file to download.'));
    }
    if (count($argv) > 1) {
      throw new ArcanistUsageException(
        pht('Specify exactly one file to download.'));
    }

    $file = reset($argv);
    if (!preg_match('/^F?\d+$/', $file)) {
      throw new ArcanistUsageException(
        pht('Specify file by ID, e.g. %s.', 'F123'));
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

    $id = $this->id;
    $display_name = 'F'.$id;
    $is_show = $this->show;
    $save_as = $this->saveAs;
    $path = null;

    try {
      $file = $conduit->callMethodSynchronous(
        'file.search',
        array(
          'constraints' => array(
            'ids' => array($id),
          ),
        ));

      $data = $file['data'];
      if (!$data) {
        throw new ArcanistUsageException(
          pht(
            'File "%s" is not a valid file, or not visible.',
            $display_name));
      }

      $file = head($data);
      $data_uri = idxv($file, array('fields', 'dataURI'));

      if ($data_uri === null) {
        throw new ArcanistUsageException(
          pht(
            'File "%s" can not be downloaded.',
            $display_name));
      }

      if ($is_show) {
        // Skip all the file path stuff if we're just going to echo the
        // file contents.
      } else {
        if ($save_as !== null) {
          $path = Filesystem::resolvePath($save_as);

          $try_unique = false;
        } else {
          $path = idxv($file, array('fields', 'name'), $display_name);
          $path = basename($path);
          $path = Filesystem::resolvePath($path);

          $try_unique = true;
        }

        if ($try_unique) {
          $path = Filesystem::writeUniqueFile($path, '');
        } else {
          if (Filesystem::pathExists($path)) {
            throw new ArcanistUsageException(
              pht(
                'File "%s" already exists.',
                $save_as));
          }

          Filesystem::writeFile($path, '');
        }

        $display_path = Filesystem::readablePath($path);
      }

      $size = idxv($file, array('fields', 'size'), 0);

      if ($is_show) {
        $file_handle = null;
      } else {
        $file_handle = fopen($path, 'ab+');
        if ($file_handle === false) {
          throw new Exception(
            pht(
              'Failed to open file "%s" for writing.',
              $path));
        }

        $this->writeInfo(
          pht('DATA'),
          pht(
            'Downloading "%s" (%s byte(s))...',
            $display_name,
            new PhutilNumber($size)));
      }

      $future = new HTTPSFuture($data_uri);

      // For small files, don't bother drawing a progress bar.
      $minimum_bar_bytes = (1024 * 1024 * 4);

      if ($is_show || ($size < $minimum_bar_bytes)) {
        $bar = null;
      } else {
        $bar = id(new PhutilConsoleProgressBar())
          ->setTotal($size);
      }

      // TODO: We should stream responses to disk, but cURL gives us the raw
      // HTTP response data and BaseHTTPFuture can not currently parse it in
      // a stream-oriented way. Until this is resolved, buffer the file data
      // in memory and write it to disk in one shot.

      list($status, $data) = $future->resolve();
      if ($status->getStatusCode() !== 200) {
        throw new Exception(
          pht(
            'Got HTTP %d status response, expected HTTP 200.',
            $status->getStatusCode()));
      }

      if (strlen($data)) {
        if ($is_show) {
          echo $data;
        } else {
          $ok = fwrite($file_handle, $data);
          if ($ok === false) {
            throw new Exception(
              pht(
                'Failed to write file data to "%s".',
                $path));
          }
        }
      }

      if ($bar) {
        $bar->update(strlen($data));
      }

      if ($bar) {
        $bar->done();
      }

      if ($file_handle) {
        $ok = fclose($file_handle);
        if ($ok === false) {
          throw new Exception(
            pht(
              'Failed to close file handle for "%s".',
              $path));
        }
      }

      if (!$is_show) {
        $this->writeOkay(
          pht('DONE'),
          pht(
            'Saved "%s" as "%s".',
            $display_name,
            $display_path));
      }

      return 0;
    } catch (Exception $ex) {

      // If we created an empty file, clean it up.
      if (!$is_show) {
        if ($path !== null) {
          Filesystem::remove($path);
        }
      }

      // If we fail for any reason, fall back to the older mechanism using
      // "file.info" and "file.download".
    }

    $this->writeStatusMessage(pht('Getting file information...')."\n");
    $info = $conduit->callMethodSynchronous(
      'file.info',
      array(
        'id' => $this->id,
      ));

    $desc = pht('(%s bytes)', new PhutilNumber($info['byteSize']));
    if ($info['name']) {
      $desc = "'".$info['name']."' ".$desc;
    }

    $this->writeStatusMessage(pht('Downloading file %s...', $desc)."\n");
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
      $this->writeStatusMessage(pht("Saved file as '%s'.", $path)."\n");
    }

    return 0;
  }

}
