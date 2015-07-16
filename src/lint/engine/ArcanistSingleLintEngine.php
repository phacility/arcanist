<?php

/**
 * Run a single linter on every path unconditionally. This is a glue engine for
 * linters like @{class:ArcanistScriptAndRegexLinter}, if you are averse to
 * writing a phutil library. Your linter will receive every path, including
 * paths which have been moved or deleted.
 *
 * Set which linter should be run by configuring `lint.engine.single.linter` in
 * `.arcconfig` or user config.
 */
final class ArcanistSingleLintEngine extends ArcanistLintEngine {

  public function buildLinters() {
    $key = 'lint.engine.single.linter';
    $linter_name = $this->getConfigurationManager()
      ->getConfigFromAnySource($key);

    if (!$linter_name) {
      throw new ArcanistUsageException(
        pht(
          "You must configure '%s' with the name of a linter ".
          "in order to use %s.",
          $key,
          __CLASS__));
    }

    if (!class_exists($linter_name)) {
      throw new ArcanistUsageException(
        pht(
          "Linter '%s' configured in '%s' does not exist!",
          $linter_name,
          $key));
    }

    if (!is_subclass_of($linter_name, 'ArcanistLinter')) {
      throw new ArcanistUsageException(
        pht(
          "Linter `%s` configured in '%s' MUST be a subclass of `%s`.",
          $linter_name,
          $key,
          'ArcanistLinter'));
    }

    // Filter the affected paths.
    $paths = $this->getPaths();
    foreach ($paths as $key => $path) {
      if (!$this->pathExists($path)) {
        // Don't lint removed files. In more complex linters it is sometimes
        // appropriate to lint removed files so you can raise a warning like
        // "you deleted X, but forgot to delete Y!", but most linters do not
        // operate correctly on removed files.
        unset($paths[$key]);
        continue;
      }
      $disk = $this->getFilePathOnDisk($path);
      if (is_dir($disk)) {
        // Don't lint directories. (In SVN, they can be directly modified by
        // changing properties on them, and may appear as modified paths.)
        unset($paths[$key]);
        continue;
      }
    }

    $linter = newv($linter_name, array());
    $linter->setPaths($paths);

    return array($linter);
  }

}
