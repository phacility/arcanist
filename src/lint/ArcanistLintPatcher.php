<?php

/**
 * Applies lint patches to the working copy.
 */
final class ArcanistLintPatcher extends Phobject {

  private $dirtyUntil     = 0;
  private $characterDelta = 0;
  private $modifiedData   = null;
  private $lineOffsets    = null;
  private $lintResult     = null;
  private $applyMessages  = array();

  public static function newFromArcanistLintResult(ArcanistLintResult $result) {
    $obj = new ArcanistLintPatcher();
    $obj->lintResult = $result;
    return $obj;
  }

  public function getUnmodifiedFileContent() {
    return $this->lintResult->getData();
  }

  public function getModifiedFileContent() {
    if ($this->modifiedData === null) {
      $this->buildModifiedFile();
    }
    return $this->modifiedData;
  }

  public function writePatchToDisk() {
    $path = $this->lintResult->getFilePathOnDisk();
    $data = $this->getModifiedFileContent();

    $ii = null;
    do {
      $lint = $path.'.linted'.($ii++);
    } while (file_exists($lint));

    // Copy existing file to preserve permissions. 'chmod --reference' is not
    // supported under OSX.
    if (Filesystem::pathExists($path)) {
      // This path may not exist if we're generating a new file.
      execx('cp -p %s %s', $path, $lint);
    }
    Filesystem::writeFile($lint, $data);

    list($err) = exec_manual('mv -f %s %s', $lint, $path);
    if ($err) {
      throw new Exception(
        pht(
          "Unable to overwrite path '%s', patched version was left at '%s'.",
          $path,
          $lint));
    }

    foreach ($this->applyMessages as $message) {
      $message->didApplyPatch();
    }
  }

  private function __construct() {}

  private function buildModifiedFile() {
    $data = $this->getUnmodifiedFileContent();

    foreach ($this->lintResult->getMessages() as $lint) {
      if (!$lint->isPatchable()) {
        continue;
      }

      $orig_offset  = $this->getCharacterOffset($lint->getLine() - 1);
      $orig_offset += $lint->getChar() - 1;

      $dirty = $this->getDirtyCharacterOffset();
      if ($dirty > $orig_offset) {
        continue;
      }

      // Adjust the character offset by the delta *after* checking for
      // dirtiness. The dirty character cursor is a cursor on the original file,
      // and should be compared with the patch position in the original file.
      $working_offset = $orig_offset + $this->getCharacterDelta();

      $old_str = $lint->getOriginalText();
      $old_len = strlen($old_str);
      $new_str = $lint->getReplacementText();
      $new_len = strlen($new_str);

      if ($working_offset == strlen($data)) {
        // Temporary hack to work around a destructive hphpi issue, see #451031.
        $data .= $new_str;
      } else {
        $data = substr_replace($data, $new_str, $working_offset, $old_len);
      }

      $this->changeCharacterDelta($new_len - $old_len);
      $this->setDirtyCharacterOffset($orig_offset + $old_len);

      $this->applyMessages[] = $lint;
    }

    $this->modifiedData = $data;
  }

  private function getCharacterOffset($line_num) {
    if ($this->lineOffsets === null) {
      $lines = explode("\n", $this->getUnmodifiedFileContent());
      $this->lineOffsets = array(0);
      $last = 0;
      foreach ($lines as $line) {
        $this->lineOffsets[] = $last + strlen($line) + 1;
        $last += strlen($line) + 1;
      }
    }

    if ($line_num >= count($this->lineOffsets)) {
      throw new Exception(pht('Data has fewer than %d lines.', $line));
    }

    return idx($this->lineOffsets, $line_num);
  }

  private function setDirtyCharacterOffset($offset) {
    $this->dirtyUntil = $offset;
    return $this;
  }

  private function getDirtyCharacterOffset() {
    return $this->dirtyUntil;
  }

  private function changeCharacterDelta($change) {
    $this->characterDelta += $change;
    return $this;
  }

  private function getCharacterDelta() {
    return $this->characterDelta;
  }

}
