<?php

final class ArcanistRaggedClassTreeEdgeXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 87;

  public function getLintName() {
    return pht('Class Not %s Or %s', 'abstract', 'final');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_DISABLED;
  }

  public function process(XHPASTNode $root) {
    $parser = new PhutilDocblockParser();

    $classes = $root->selectDescendantsOfType('n_CLASS_DECLARATION');
    foreach ($classes as $class) {
      $is_final = false;
      $is_abstract = false;
      $is_concrete_extensible = false;

      $attributes = $class->getChildOfType(0, 'n_CLASS_ATTRIBUTES');
      foreach ($attributes->getChildren() as $child) {
        if ($child->getConcreteString() == 'final') {
          $is_final = true;
        }
        if ($child->getConcreteString() == 'abstract') {
          $is_abstract = true;
        }
      }

      $docblock = $class->getDocblockToken();
      if ($docblock) {
        list($text, $specials) = $parser->parse($docblock->getValue());
        $is_concrete_extensible = idx($specials, 'concrete-extensible');
      }

      if (!$is_final && !$is_abstract && !$is_concrete_extensible) {
        $this->raiseLintAtNode(
          $class->getChildOfType(1, 'n_CLASS_NAME'),
          pht(
            "This class is neither '%s' nor '%s', and does not have ".
            "a docblock marking it '%s'.",
            'final',
            'abstract',
            '@concrete-extensible'));
      }
    }
  }

}
