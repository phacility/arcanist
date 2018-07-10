<?php

/**
 * Uses rust-fmt to lint code.
 */
final class ArcanistRustLinter extends ArcanistExternalLinter {

  public function getLinterName() {
    return 'rustfmt';
  }

  public function getLinterConfigurationName() {
    return 'rustfmt';
  }

  public function getDefaultBinary() {
    #return 'cargo +nightly fmt --all -- --check';
    return 'cargo';
  }

  public function getMandatoryFlags() {
      return array('+nightly', 'fmt', '--all', '--', '--emit', 'checkstyle');
  }

  public function getInstallInstructions() {
    return pht(
      'Install rustfmt nightly from `%s`'.
      'https://github.com/rust-lang-nursery/rustfmt');
  }

  protected function getDefaultMessageSeverity($code) {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    $lines = explode("\n", $stderr);

    $dom = new DOMDocument();
    $ok = @$dom->loadXML($stdout);

    if (!$ok) {
      return false;
    }

    $errors = $dom->getElementsByTagName('error');

    if (!$errors) {
      return $errors;
    }

    $messages = array();

    $i = 0;
    foreach ($dom->getElementsByTagName('file') as $file) {
        foreach ($file->getElementsByTagName('error') as $error) {

            $message = new ArcanistLintMessage();
            $message->setPath($file->getAttribute('name'));
            $message->setLine($error->getAttribute('line'));
            $message->setDescription($error->getAttribute('message'));
            $message->setName($i++);

            switch ($error->getAttribute('severity')) {
                case 'warning':
                    $message->setSeverity(ArcanistLintSeverity::SEVERITY_WARNING);
                    break;

                default:
                    $message->setSeverity(ArcanistLintSeverity::SEVERITY_ERROR);
            }

            $messages[] = $message;
        }
    }

    return $messages;
  }

}
