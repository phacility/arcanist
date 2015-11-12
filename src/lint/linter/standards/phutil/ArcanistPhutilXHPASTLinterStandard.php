<?php

final class ArcanistPhutilXHPASTLinterStandard
  extends ArcanistLinterStandard {

  public function getKey() {
    return 'phutil.xhpast';
  }

  public function getName() {
    return pht('Phutil XHPAST');
  }

  public function getDescription() {
    return pht('PHP Coding Standards for Phutil libraries.');
  }

  public function supportsLinter(ArcanistLinter $linter) {
    return $linter instanceof ArcanistXHPASTLinter;
  }

  public function getLinterConfiguration() {
    return array(
      'xhpast.blacklisted.function' => array(
        'eval' => pht(
          'The `%s` function should be avoided. It is potentially unsafe '.
          'and makes debugging more difficult.',
          'eval'),
      'xhpast.php-version' => '5.2.3',
      'xhpast.php-version.windows' => '5.3.0',
      ),
    );
  }

  public function getLinterSeverityMap() {
    $advice = ArcanistLintSeverity::SEVERITY_ADVICE;
    $error  = ArcanistLintSeverity::SEVERITY_ERROR;

    return array(
      ArcanistTodoCommentXHPASTLinterRule::ID    => $advice,
      ArcanistCommentSpacingXHPASTLinterRule::ID => $error,
    );
  }

}
