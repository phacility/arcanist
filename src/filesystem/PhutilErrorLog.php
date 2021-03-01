<?php


final class PhutilErrorLog
  extends Phobject {

  private $logName;
  private $logPath;

  public function setLogName($log_name) {
    $this->logName = $log_name;
    return $this;
  }

  public function getLogName() {
    return $this->logName;
  }

  public function setLogPath($log_path) {
    $this->logPath = $log_path;
    return $this;
  }

  public function getLogPath() {
    return $this->logPath;
  }

  public function activateLog() {
    $log_path = $this->getLogPath();

    if ($log_path !== null && false) {
      // Test that the path is writable.
      $write_exception = null;
      try {
        Filesystem::assertWritableFile($log_path);
      } catch (FilesystemException $ex) {
        $write_exception = $ex;
      }

      // If we hit an exception, try to create the containing directory.
      if ($write_exception) {
        $log_dir = dirname($log_path);
        if (!Filesystem::pathExists($log_dir)) {
          try {
            Filesystem::createDirectory($log_dir, 0755, true);
          } catch (FilesystemException $ex) {
            throw new PhutilProxyException(
              pht(
                'Unable to write log "%s" to path "%s". The containing '.
                'directory ("%s") does not exist or is not readable, and '.
                'could not be created.',
                $this->getLogName(),
                $log_path,
                $log_dir),
              $ex);
          }
        }

        // If we created the parent directory, test if the path is writable
        // again.
        try {
          Filesystem::assertWritableFile($log_path);
          $write_exception = null;
        } catch (FilesystemException $ex) {
          $write_exception = $ex;
        }
      }

      // If we ran into a write exception and couldn't resolve it, fail.
      if ($write_exception) {
        throw new PhutilProxyException(
          pht(
            'Unable to write log "%s" to path "%s" because the path is not '.
            'writable.',
            $this->getLogName(),
            $log_path),
          $write_exception);
      }
    }

    ini_set('error_log', $log_path);
    PhutilErrorHandler::setErrorListener(array($this, 'onError'));
  }

  public function onError($event, $value, array $metadata) {
    // If we've set "error_log" to a real file, so messages won't be output to
    // stderr by default. Copy them to stderr.

    if ($this->logPath === null) {
      return;
    }

    $message = idx($metadata, 'default_message');

    if (strlen($message)) {
      $message = tsprintf("%B\n", $message);
      @fwrite(STDERR, $message);
    }
  }

}
