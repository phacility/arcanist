<?php

/**
 * Calls `hlint` and parses its results.
 */
final class ArcanistHLintLinter extends ArcanistExternalLinter {

  public function getInfoName() {
    return 'Haskell Linter';
  }

  public function getInfoURI() {
    return 'https://github.com/ndmitchell/hlint';
  }

  public function getInfoDescription() {
    return pht('HLint is a linter for Haskell code.');
  }

  public function getLinterName() {
    return 'HLINT';
  }

  public function getLinterConfigurationName() {
    return 'hlint';
  }

  public function getDefaultBinary() {
    return 'hlint';
  }

  public function getInstallInstructions() {
    return pht('Install hlint with `%s`.', 'cabal install hlint');
  }

  protected function getMandatoryFlags() {
    return array('--json');
  }

  public function getVersion() {
    list($stdout, $stderr) = execx(
      '%C --version', $this->getExecutableCommand());

    $matches = null;
    if (preg_match('@HLint v(.*),@', $stdout, $matches)) {
      return $matches[1];
    }

    return null;
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    $json = phutil_json_decode($stdout);
    $messages = array();
    foreach ($json as $fix) {
      if ($fix === null) {
        return;
      }

      $message = new ArcanistLintMessage();
      $message->setCode($this->getLinterName());
      $message->setPath($path);
      $message->setLine($fix['startLine']);
      $message->setChar($fix['startColumn']);
      $message->setName($fix['hint']);
      $message->setOriginalText($fix['from']);
      $message->setReplacementText($fix['to']);

      /* Some improvements may slightly change semantics, so attach
         all necessary notes too. */
      $notes = '';
      foreach ($fix['note'] as $note) {
        $notes .= phutil_console_format(
          ' **%s**: %s.',
          pht('NOTE'),
          trim($note, '"'));
      }

      $message->setDescription(
        pht(
          'In module `%s`, declaration `%s`.',
          $fix['module'],
          $fix['decl']).$notes);

      switch ($fix['severity']) {
        case 'Error':
          $message->setSeverity(ArcanistLintSeverity::SEVERITY_ERROR);
          break;
        case 'Warning':
          $message->setSeverity(ArcanistLintSeverity::SEVERITY_WARNING);
          break;
        default:
          $message->setSeverity(ArcanistLintSeverity::SEVERITY_ADVICE);
          break;
      }

      $messages[] = $message;
    }

    return $messages;
  }
}
