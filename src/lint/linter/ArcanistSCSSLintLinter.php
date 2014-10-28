<?php
/**
 * A Linter for SCSS Files.
 *
 * This linter uses [[https://github.com/causes/scss-lint]] to detect
 * errors and potential problems in code.
 */

final class ArcanistSCSSLintLinter extends ArcanistExternalLinter {

  public function getInfoName() {
    return 'SCSSLint';
  }

  public function getInfoURI() {
    return 'https://github.com/causes/scss-lint';
  }

  public function getInfoDescription() {
    return pht('Use `scss-lint` to detect issues with SCSS source files.');
  }

  public function getLinterName() {
    return 'SCSSLint';
  }

  public function getLinterConfigurationName() {
    return 'scss-lint';
  }

  public function getMandatoryFlags() {
    return array(
      '--format=XML'
    );
  }

  public function getDefaultFlags() {
    return $this->getDeprecatedConfiguration('lint.scss-lint.options', array());
  }

  public function getDefaultBinary() {
    return 'scss-lint';
  }

  public function getVersion() {
    list($stdout) = execx('%C --version', $this->getExecutableCommand());

    $matches = array();
    if (preg_match('/^v(?P<version>\d+\.\d+\.\d+)$/', $stdout, $matches)) {
      return $matches['version'];
    } else {
      return false;
    }
  }

  public function getInstallInstructions() {
    return pht('Install scss-lint using `gem install scss-lint`. Also, Make sure you have ruby version >1.9 before installing.');
  }

  public function shouldExpectCommandErrors() {
    return true;
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {

    $report_dom = new DOMDocument();
    $ok = @$report_dom->loadXML($stdout);

    if (!$ok) {
      return false;
    }

    $files = $report_dom->getElementsByTagName('file');
    $messages = array();
    foreach ($files as $file) {
      foreach ($file->childNodes as $child) {
        if (!($child instanceof DOMElement)) {
          continue;
        }

        $data = $this->getData($path);
        $lines = explode("\n", $data);
        $name = $child->getAttribute('reason');
        $severity = ($child->getAttribute('severity') == 'warning')
          ? ArcanistLintSeverity::SEVERITY_WARNING
          : ArcanistLintSeverity::SEVERITY_ERROR;

        $message = new ArcanistLintMessage();
        $message->setPath($path);
        $message->setLine($child->getAttribute('line'));
        $message->setChar($child->getAttribute('char'));
        $message->setDescription($child->getAttribute('reason'));
        $message->setSeverity($severity);

        if ($child->hasAttribute('line') && $child->getAttribute('line') > 0) {
          $line = $lines[$child->getAttribute('line') - 1];
          $text = substr($line, $child->getAttribute('char') - 1);
          $message->setOriginalText($text);
        }

        $messages[] = $message;
      }
    }

    return $messages;
  }

   protected function getLintCodeFromLinterConfigurationKey($code) {
   	//As of now, There is no support for severity on scss-lint. Will update this
     //method after a pull request.
    throw new Exception(pht("scss-lint does not currently support custom severity levels as of now."));
   }

}
