<?php

final class ArcanistWeldWorkflow extends ArcanistWorkflow {

  public function getWorkflowName() {
    return 'weld';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **weld** [options] __file__ __file__ ...
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          Robustly fuse two or more files together. The resulting joint is
          much stronger than the one created by tools like __cat__.
EOTEXT
      );
  }

  public function getArguments() {
    return array(
      '*' => 'files',
    );
  }

  public function run() {
    $files = $this->getArgument('files');
    if (count($files) < 2) {
      throw new ArcanistUsageException(
        pht('Specify two or more files to weld together.'));
    }

    $buffer = array();
    foreach ($files as $file) {
      $data = Filesystem::readFile($file);
      if (!strlen($data)) {
        continue;
      }
      $lines = phutil_split_lines($data, true);

      $overlap = mt_rand(16, 32);

      if (count($buffer) > 6) {
        $overlap = min($overlap, ceil(count($buffer) / 2));
      }

      if (count($lines) > 6) {
        $overlap = min($overlap, ceil(count($lines) / 2));
      }

      $overlap = min($overlap, count($buffer));
      $overlap = min($overlap, count($lines));

      $buffer_len = count($buffer);
      for ($ii = 0; $ii < $overlap; $ii++) {
        $buffer[$buffer_len - $overlap + $ii] = $this->weldLines(
          $buffer[$buffer_len - $overlap + $ii],
          $lines[$ii],
          ($ii + 0.5) / $overlap);
      }

      for ($ii = $overlap; $ii < count($lines); $ii++) {
        $buffer[] = $lines[$ii];
      }
    }

    echo implode('', $buffer);
  }

  private function weldLines($u, $v, $bias) {
    $newline = null;
    $matches = null;

    if (preg_match('/([\r\n]+)\z/', $u, $matches)) {
      $newline = $matches[1];
    }

    if (preg_match('/([\r\n]+)\z/', $v, $matches)) {
      $newline = $matches[1];
    }

    $u = rtrim($u, "\r\n");
    $v = rtrim($v, "\r\n");

    $u = phutil_utf8v_combined($u);
    $v = phutil_utf8v_combined($v);

    $len = max(count($u), count($v));

    while (count($u) < $len) {
      $u[] = ' ';
    }
    while (count($v) < $len) {
      $v[] = ' ';
    }

    $rand_max = mt_getrandmax();

    $result = array();
    for ($ii = 0; $ii < $len; $ii++) {
      $uc = $u[$ii];
      $vc = $v[$ii];

      $threshold = $bias;
      if ($uc == ' ') {
        $threshold = 1;
      }

      if ($vc == ' ') {
        $threshold = 0;
      }

      if ((mt_rand() / $rand_max) > $threshold) {
        $r = $uc;
      } else {
        $r = $vc;
      }

      $result[] = $r;
    }

    if ($newline !== null) {
      $result[] = $newline;
    }

    return implode('', $result);
  }

}
