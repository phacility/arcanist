<?php

/** This linter invokes git-secrets to check for the presence of secrets in code */
final class UberGitSecretsLinter extends ArcanistExternalLinter {
  public function getInfoName() {
    return 'GitSecrets';
  }

  public function getInfoURI() {
    return 'https://code.uberinternal.com/diffusion/ENGITXJ/';
  }

  public function getInfoDescription() {
    return 'git-secrets is a static analysis tool for identifying secrets in code';
  }

  public function getLinterName() {
    return 'git-secrets';
  }

  public function getLinterConfigurationName() {
    return 'git-secrets';
  }

  public function getDefaultBinary() {
    return '/usr/local/etc/git-secrets/git-secrets';
  }

  public function getInstallInstructions() {
    return pht(
      'In a monorepo, git-secrets should already be present alongside other'.
      'tooling. To install for use in another repo, use `sudo chef-client`.');
  }

  protected function getMandatoryFlags() {
    $options = array();
    $options[] = '--scan';
    return $options;
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    $lines = phutil_split_lines($stdout, false);
    $messages = array();

    foreach ($lines as $line) {
      $matches = explode(':', $line, 3);

      if (count($matches) < 3) {
        continue;
      }

      $message = id(new ArcanistLintMessage())
        ->setPath($path)
        ->setLine($matches[1])
        ->setChar(null)
        ->setCode($this->getLinterName())
        ->setName($this->getLinterName())
        ->setDescription('Secret detected')
        ->setSeverity(ArcanistLintSeverity::SEVERITY_ERROR);

      $messages[] = $message;
    }

    return $messages;
  }
}
