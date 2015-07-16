<?php

/**
 * Uses `ruby` to detect various errors in Ruby code.
 */
final class ArcanistRubyLinter extends ArcanistExternalLinter {

  public function getInfoURI() {
    return 'https://www.ruby-lang.org/';
  }

  public function getInfoName() {
    return pht('Ruby');
  }

  public function getInfoDescription() {
    return pht(
      'Use `%s` to check for syntax errors in Ruby source files.',
      'ruby');
  }

  public function getLinterName() {
    return 'RUBY';
  }

  public function getLinterConfigurationName() {
    return 'ruby';
  }

  public function getDefaultBinary() {
    return 'ruby';
  }

  public function getVersion() {
    list($stdout) = execx('%C --version', $this->getExecutableCommand());

    $matches = array();
    $regex = '/^ruby (?P<version>\d+\.\d+\.\d+)p\d+/';
    if (preg_match($regex, $stdout, $matches)) {
      return $matches['version'];
    } else {
      return false;
    }
  }

  public function getInstallInstructions() {
    return pht('Install `%s` from <%s>.', 'ruby', 'http://www.ruby-lang.org/');
  }

  protected function getMandatoryFlags() {
    // -w: turn on warnings
    // -c: check syntax
    return array('-w', '-c');
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    $lines = phutil_split_lines($stderr, false);

    $messages = array();
    foreach ($lines as $line) {
      $matches = null;

      if (!preg_match('/(.*?):(\d+): (.*?)$/', $line, $matches)) {
        continue;
      }

      foreach ($matches as $key => $match) {
        $matches[$key] = trim($match);
      }

      $code = head(explode(',', $matches[3]));

      $message = new ArcanistLintMessage();
      $message->setPath($path);
      $message->setLine($matches[2]);
      $message->setCode($this->getLinterName());
      $message->setName(pht('Syntax Error'));
      $message->setDescription($matches[3]);
      $message->setSeverity($this->getLintMessageSeverity($code));

      $messages[] = $message;
    }

    return $messages;
  }

}
