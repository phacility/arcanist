<?php

/**
 * Ensures that files are not executable unless they are either binary or
 * contain a shebang.
 */
final class ArcanistChmodLinter extends ArcanistLinter {

  const LINT_INVALID_EXECUTABLE = 1;

  public function getInfoName() {
    return 'Chmod';
  }

  public function getInfoDescription() {
    return pht(
      'Checks the permissions on files and ensures that they are not made to '.
      'be executable unnecessarily. In particular, a file should not be '.
      'executable unless it is either binary or contain a shebang.');
  }

  public function getLinterName() {
    return 'CHMOD';
  }

  public function getLinterConfigurationName() {
    return 'chmod';
  }

  public function getLintNameMap() {
    return array(
      self::LINT_INVALID_EXECUTABLE => pht('Invalid Executable'),
    );
  }

  protected function getDefaultMessageSeverity($code) {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  protected function shouldLintBinaryFiles() {
    return true;
  }

  public function lintPath($path) {
    $engine = $this->getEngine();

    if (is_executable($engine->getFilePathOnDisk($path))) {
      if ($engine->isBinaryFile($path)) {
        $mime = Filesystem::getMimeType($engine->getFilePathOnDisk($path));

        switch ($mime) {
          // Archives
          case 'application/jar':
          case 'application/java-archive':
          case 'application/x-bzip2':
          case 'application/x-gzip':
          case 'application/x-rar-compressed':
          case 'application/x-tar':
          case 'application/zip':

          // Audio
          case 'audio/midi':
          case 'audio/mpeg':
          case 'audio/mp4':
          case 'audio/x-wav':

          // Fonts
          case 'application/vnd.ms-fontobject':
          case 'application/x-font-ttf':
          case 'application/x-woff':

          // Images
          case 'application/x-shockwave-flash':
          case 'image/gif':
          case 'image/jpeg':
          case 'image/png':
          case 'image/tiff':
          case 'image/x-icon':
          case 'image/x-ms-bmp':

          // Miscellaneous
          case 'application/msword':
          case 'application/pdf':
          case 'application/postscript':
          case 'application/rtf':
          case 'application/vnd.ms-excel':
          case 'application/vnd.ms-powerpoint':

          // Video
          case 'video/mpeg':
          case 'video/quicktime':
          case 'video/x-flv':
          case 'video/x-msvideo':
          case 'video/x-ms-wmv':

            $this->raiseLintAtPath(
              self::LINT_INVALID_EXECUTABLE,
              pht("'%s' files should not be executable.", $mime));
            return;

          default:
            // Path is a binary file, which makes it a valid executable.
            return;
        }
      } else if ($this->getShebang($path)) {
        // Path contains a shebang, which makes it a valid executable.
        return;
      } else {
        $this->raiseLintAtPath(
          self::LINT_INVALID_EXECUTABLE,
          pht(
            'Executable files should either be binary or contain a shebang.'));
      }
    }
  }

  /**
   * Returns the path's shebang.
   *
   * @param  string
   * @return string|null
   */
  private function getShebang($path) {
    $line = head(phutil_split_lines($this->getEngine()->loadData($path), true));

    $matches = array();
    if (preg_match('/^#!(.*)$/', $line, $matches)) {
      return $matches[1];
    } else {
      return null;
    }
  }

}
