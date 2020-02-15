<?php

final class ArcanistWeldWorkflow
  extends ArcanistArcWorkflow {

  public function getWorkflowName() {
    return 'weld';
  }

  public function getWorkflowInformation() {
    $help = pht(<<<EOTEXT
Robustly fuse two or more files together. The resulting joint is much stronger
than the one created by tools like __cat__.
EOTEXT
      );

    return $this->newWorkflowInformation()
      ->setSynopsis(pht('Robustly fuse files together.'))
      ->addExample('**weld** [options] -- __file__ __file__ ...')
      ->setHelp($help);
  }

  public function getWorkflowArguments() {
    return array(
      $this->newWorkflowArgument('files')
        ->setWildcard(true),
    );
  }

  public function runWorkflow() {
    $files = $this->getArgument('files');

    if (count($files) < 2) {
      throw new PhutilArgumentUsageException(
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

    // If the inputs are UTF8, split glyphs (so two valid UTF8 inputs always
    // produce a sensible, valid UTF8 output). If they aren't, split bytes.

    if (phutil_is_utf8($u)) {
      $u = phutil_utf8v_combined($u);
    } else {
      $u = str_split($u);
    }

    if (phutil_is_utf8($v)) {
      $v = phutil_utf8v_combined($v);
    } else {
      $v = str_split($v);
    }

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
