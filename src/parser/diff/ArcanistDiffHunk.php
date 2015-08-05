<?php

/**
 * Represents a contiguous set of added and removed lines in a diff.
 */
final class ArcanistDiffHunk extends Phobject {

  protected $oldOffset;
  protected $oldLength;
  protected $newOffset;
  protected $newLength;
  protected $addLines;
  protected $delLines;
  protected $isMissingOldNewline = false;
  protected $isMissingNewNewline = false;
  protected $corpus;

  public function toDictionary() {
    return array(
      'oldOffset' => $this->oldOffset,
      'newOffset' => $this->newOffset,
      'oldLength' => $this->oldLength,
      'newLength' => $this->newLength,
      'addLines'  => $this->addLines,
      'delLines'  => $this->delLines,
      'isMissingOldNewline' => $this->isMissingOldNewline,
      'isMissingNewNewline' => $this->isMissingNewNewline,
      'corpus'    => (string)$this->corpus,
    );
  }

  public static function newFromDictionary(array $dict) {
    $obj = new ArcanistDiffHunk();

    $obj->oldOffset = $dict['oldOffset'];
    $obj->newOffset = $dict['newOffset'];
    $obj->oldLength = $dict['oldLength'];
    $obj->newLength = $dict['newLength'];
    $obj->addLines = $dict['addLines'];
    $obj->delLines = $dict['delLines'];
    $obj->isMissingOldNewline = $dict['isMissingOldNewline'];
    $obj->isMissingNewNewline = $dict['isMissingNewNewline'];
    $obj->corpus = $dict['corpus'];

    return $obj;
  }

  public function getChangedLines($type) {
    $old_map = array();
    $new_map = array();
    $cover_map = array();

    $oline = $this->getOldOffset();
    $nline = $this->getNewOffset();
    foreach (explode("\n", $this->getCorpus()) as $line) {
      $char = strlen($line) ? $line[0] : '~';
      switch ($char) {
        case '-':
          $old_map[$oline] = true;
          $cover_map[$oline] = true;
          ++$oline;
          break;
        case '+':
          $new_map[$nline] = true;
          if ($oline > 1) {
            $cover_map[$oline - 1] = true;
          }
          $cover_map[$oline] = true;
          ++$nline;
          break;
        default:
          ++$oline;
          ++$nline;
          break;
      }
    }

    switch ($type) {
      case 'new':
        return $new_map;
      case 'old':
        return $old_map;
      case 'cover':
        return $cover_map;
      default:
        throw new Exception(pht("Unknown line change type '%s'.", $type));
    }
  }

  public function setOldOffset($old_offset) {
    $this->oldOffset = $old_offset;
    return $this;
  }

  public function getOldOffset() {
    return $this->oldOffset;
  }

  public function setNewOffset($new_offset) {
    $this->newOffset = $new_offset;
    return $this;
  }

  public function getNewOffset() {
    return $this->newOffset;
  }

  public function setOldLength($old_length) {
    $this->oldLength = $old_length;
    return $this;
  }

  public function getOldLength() {
    return $this->oldLength;
  }

  public function setNewLength($new_length) {
    $this->newLength = $new_length;
    return $this;
  }

  public function getNewLength() {
    return $this->newLength;
  }

  public function setAddLines($add_lines) {
    $this->addLines = $add_lines;
    return $this;
  }

  public function getAddLines() {
    return $this->addLines;
  }

  public function setDelLines($del_lines) {
    $this->delLines = $del_lines;
    return $this;
  }

  public function getDelLines() {
    return $this->delLines;
  }

  public function setCorpus($corpus) {
    $this->corpus = $corpus;
    return $this;
  }

  public function getCorpus() {
    return $this->corpus;
  }

  public function setIsMissingOldNewline($missing) {
    $this->isMissingOldNewline = (bool)$missing;
    return $this;
  }

  public function getIsMissingOldNewline() {
    return $this->isMissingOldNewline;
  }

  public function setIsMissingNewNewline($missing) {
    $this->isMissingNewNewline = (bool)$missing;
    return $this;
  }

  public function getIsMissingNewNewline() {
    return $this->isMissingNewNewline;
  }

}
