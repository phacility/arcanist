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
       ),
      'xhpast.php-version' => '5.2.3',
      'xhpast.php-version.windows' => '5.3.0',
      'xhpast.dynamic-string.classes' => array(
        'ExecFuture' => 0,
      ),
      'xhpast.dynamic-string.functions' => array(
        'pht' => 0,

        'hsprintf' => 0,
        'jsprintf' => 0,

        'hgsprintf' => 0,

        'csprintf' => 0,
        'vcsprintf' => 0,
        'execx' => 0,
        'exec_manual' => 0,
        'phutil_passthru' => 0,

        'qsprintf' => 1,
        'vqsprintf' => 1,
        'queryfx' => 1,
        'queryfx_all' => 1,
        'queryfx_one' => 1,
      ),
    );
  }

  public function getLinterSeverityMap() {
    $advice  = ArcanistLintSeverity::SEVERITY_ADVICE;
    $error   = ArcanistLintSeverity::SEVERITY_ERROR;
    $warning = ArcanistLintSeverity::SEVERITY_WARNING;

    return array(
      ArcanistTodoCommentXHPASTLinterRule::ID         => $advice,
      ArcanistCommentSpacingXHPASTLinterRule::ID      => $error,
      ArcanistRaggedClassTreeEdgeXHPASTLinterRule::ID => $warning,
    );
  }

}
