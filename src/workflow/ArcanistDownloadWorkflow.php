<?php

final class ArcanistDownloadWorkflow
  extends ArcanistArcWorkflow {

  public function getWorkflowName() {
    return 'download';
  }

  public function getWorkflowInformation() {
    $help = pht(<<<EOTEXT
Download a file to local disk.
EOTEXT
      );

    return $this->newWorkflowInformation()
      ->setSynopsis(pht('Download a file to local disk.'))
      ->addExample(pht('**download** [__options__] -- __file__'))
      ->setHelp($help);
  }

  public function getWorkflowArguments() {
    return array(
      $this->newWorkflowArgument('as')
        ->setParameter('path')
        ->setHelp(pht('Save the file to a specific location.')),
      $this->newWorkflowArgument('argv')
        ->setWildcard(true),
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


  public function runWorkflow() {
    $file_symbols = $this->getArgument('argv');

    if (!$file_symbols) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify a file to download, like "F123".'));
    }

    if (count($file_symbols) > 1) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify exactly one file to download.'));
    }

    $file_symbol = head($file_symbols);

    $symbols = $this->getSymbolEngine();
    $file_ref = $symbols->loadFileForSymbol($file_symbol);
    if (!$file_ref) {
      throw new PhutilArgumentUsageException(
        pht(
          'File "%s" does not exist, or you do not have permission to '.
          'view it.',
          $file_symbol));
    }

    $is_stdout = false;
    $path = null;

    $save_as = $this->getArgument('as');
    if ($save_as === '-') {
      $is_stdout = true;
    } else if ($save_as === null) {
      $path = $file_ref->getName();
      $path = basename($path);
      $path = Filesystem::resolvePath($path);

      $try_unique = true;
    } else {
      $path = Filesystem::resolvePath($save_as);

      $try_unique = false;
    }

    $file_handle = null;
    if (!$is_stdout) {
      if ($try_unique) {
        $path = Filesystem::writeUniqueFile($path, '');
        Filesystem::remove($path);
      } else {
        if (Filesystem::pathExists($path)) {
          throw new PhutilArgumentUsageException(
            pht(
              'File "%s" already exists.',
              $path));
        }
      }
    }

    $display_path = Filesystem::readablePath($path);

    $display_name = $file_ref->getName();
    if (!strlen($display_name)) {
      $display_name = $file_ref->getMonogram();
    }

    $expected_bytes = $file_ref->getSize();
    $log = $this->getLogEngine();

    if (!$is_stdout) {
      $log->writeStatus(
        pht('DATA'),
        pht(
          'Downloading "%s" (%s byte(s)) to "%s"...',
          $display_name,
          new PhutilNumber($expected_bytes),
          $display_path));
    }

    $data_uri = $file_ref->getDataURI();
    $future = new HTTPSFuture($data_uri);

    if (!$is_stdout) {
      // For small files, don't bother drawing a progress bar.
      $minimum_bar_bytes = (1024 * 1024 * 4);
      if ($expected_bytes > $minimum_bar_bytes) {
        $progress = id(new PhutilConsoleProgressSink())
          ->setTotalWork($expected_bytes);

        $future->setProgressSink($progress);
      }

      // Compute a timeout based on the expected filesize.
      $transfer_rate = 32 * 1024;
      $timeout = (int)(120 + ($expected_bytes / $transfer_rate));

      $future
        ->setTimeout($timeout)
        ->setDownloadPath($path);
    }

    try {
      list($data) = $future->resolvex();
    } catch (Exception $ex) {
      Filesystem::removePath($path);
      throw $ex;
    }

    if ($is_stdout) {
      $file_bytes = strlen($data);
    } else {
      // TODO: This has various potential problems with clearstatcache() and
      // 32-bit systems, but just ignore them for now.
      $file_bytes = filesize($path);
    }

    if ($file_bytes !== $expected_bytes) {
      throw new Exception(
        pht(
          'Downloaded file size (%s bytes) does not match expected '.
          'file size (%s bytes). This download may be incomplete or '.
          'corrupt.',
          new PhutilNumber($file_bytes),
          new PhutilNumber($expected_bytes)));
    }

    if ($is_stdout) {
      echo $data;
    } else {
      $log->writeStatus(
        pht('DONE'),
        pht(
          'Saved "%s" as "%s".',
          $display_name,
          $display_path));
    }

    return 0;
  }

}
