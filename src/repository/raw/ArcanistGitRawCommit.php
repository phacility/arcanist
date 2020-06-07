<?php

final class ArcanistGitRawCommit
  extends Phobject {

  private $treeHash;
  private $parents = array();
  private $rawAuthor;
  private $rawCommitter;
  private $message;

  const GIT_EMPTY_TREE_HASH = '4b825dc642cb6eb9a060e54bf8d69288fbee4904';

  public static function newEmptyCommit() {
    $raw = new self();
    $raw->setTreeHash(self::GIT_EMPTY_TREE_HASH);
    return $raw;
  }

  public static function newFromRawBlob($blob) {
    $lines = phutil_split_lines($blob);

    $seen = array();
    $raw = new self();

    $pattern = '(^(\w+) ([^\n]+)\n?\z)';
    foreach ($lines as $key => $line) {
      unset($lines[$key]);

      $is_divider = ($line === "\n");
      if ($is_divider) {
        break;
      }

      $matches = null;
      $ok = preg_match($pattern, $line, $matches);
      if (!$ok) {
        throw new Exception(
          pht(
            'Expected to match pattern "%s" against line "%s" in raw commit '.
            'blob: %s',
            $pattern,
            $line,
            $blob));
      }

      $label = $matches[1];
      $value = $matches[2];

      // Detect unexpected repeated lines.

      if (isset($seen[$label])) {
        switch ($label) {
          case 'parent':
            break;
          default:
            throw new Exception(
              pht(
                'Encountered two "%s" lines ("%s", "%s") while parsing raw '.
                'commit blob, expected at most one: %s',
                $label,
                $seen[$label],
                $line,
                $blob));
        }
      } else {
        $seen[$label] = $line;
      }

      switch ($label) {
        case 'tree':
          $raw->setTreeHash($value);
          break;
        case 'parent':
          $raw->addParent($value);
          break;
        case 'author':
          $raw->setRawAuthor($value);
          break;
        case 'committer':
          $raw->setRawCommitter($value);
          break;
        default:
          throw new Exception(
            pht(
              'Unknown attribute label "%s" in line "%s" while parsing raw '.
              'commit blob: %s',
              $label,
              $line,
              $blob));
      }
    }

    $message = implode('', $lines);
    $raw->setMessage($message);

    return $raw;
  }

  public function getRawBlob() {
    $out = array();

    $tree = $this->getTreeHash();
    if ($tree !== null) {
      $out[] = sprintf("tree %s\n", $tree);
    }

    $parents = $this->getParents();
    foreach ($parents as $parent) {
      $out[] = sprintf("parent %s\n", $parent);
    }

    $raw_author = $this->getRawAuthor();
    if ($raw_author !== null) {
      $out[] = sprintf("author %s\n", $raw_author);
    }

    $raw_committer = $this->getRawCommitter();
    if ($raw_committer !== null) {
      $out[] = sprintf("committer %s\n", $raw_committer);
    }

    $out[] = "\n";

    $message = $this->getMessage();
    if ($message !== null) {
      $out[] = $message;
    }

    return implode('', $out);
  }

  public function setTreeHash($tree_hash) {
    $this->treeHash = $tree_hash;
    return $this;
  }

  public function getTreeHash() {
    return $this->treeHash;
  }

  public function setRawAuthor($raw_author) {
    $this->rawAuthor = $raw_author;
    return $this;
  }

  public function getRawAuthor() {
    return $this->rawAuthor;
  }

  public function setRawCommitter($raw_committer) {
    $this->rawCommitter = $raw_committer;
    return $this;
  }

  public function getRawCommitter() {
    return $this->rawCommitter;
  }

  public function setParents(array $parents) {
    $this->parents = $parents;
    return $this;
  }

  public function getParents() {
    return $this->parents;
  }

  public function addParent($hash) {
    $this->parents[] = $hash;
    return $this;
  }

  public function setMessage($message) {
    $this->message = $message;
    return $this;
  }

  public function getMessage() {
    return $this->message;
  }

}
