<?php

// class uses fzf tool to quickly search/filter text
final class UberFZF extends Phobject {
  private $multi = false;
  private $header = '';

  /**
    * checks if `fzf` tool is available and throws exception if not
    * also suggests commands to install `fzf`
  */
  public function requireFZF() {
    try {
      id(new ExecFuture('fzf --version'))
        ->resolvex();
    }
    catch (CommandException $e) {
      throw new ArcanistUsageException('Looks like you do not have `fzf`, '.
        'please install using `brew install fzf` or `apt-get install fzf` '.
        'or `dnf install fzf` and try again.');
    }
    return $this;
  }

  public function setMulti($multi) {
    $this->multi = (bool)$multi;
    return $this;
  }

  public function setHeader($header) {
    $this->header = $header;
    return $this;
  }

  private function buildFZFCommand() {
    $cmd = array('fzf', '--read0', '--print0');
    $args = array();

    if ($this->multi) {
      $cmd[] = '--multi';
    }

    if ($this->header) {
      $cmd[] = '--header %s';
      $args[] = $this->header;
    }
    return array(implode(' ', $cmd), $args);
  }

  public function fuzzyChoosePrompt(&$lines = array()) {
    // temporary place to store all the lines
    $input = new TempFile();
    $result = new TempFile();
    $firstline = true;
    foreach ($lines as $line) {
      $p_line = $line;
      if (!$firstline) {
        $p_line = "\0".$line;
      }
      $firstline = false;
      Filesystem::appendFile($input, $p_line);
    }
    list($cmd, $args) = $this->buildFZFCommand();
    $fzf = id(new PhutilExecPassthru($cmd, ...$args));
    $err = $fzf->execute(
      array(
      0 => array('file', (string)$input, 'r'),
            1 => array('file', (string)$result, 'w'),
            3 => STDERR,
    ));
    // we ignore error code from fzf and treat it as nothing was selected
    $result = Filesystem::readFile($result);
    return array_filter(explode("\0", $result));
  }
}
