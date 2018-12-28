<?php

final class ArcanistContinueInsideSwitchXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 128;

  public function getLintName() {
    return pht('Continue Inside Switch');
  }

  public function process(XHPASTNode $root) {
    $continues = $root->selectDescendantsOfType('n_CONTINUE');

    $valid_containers = array(
      'n_WHILE' => true,
      'n_FOREACH' => true,
      'n_FOR' => true,
      'n_DO_WHILE' => true,
    );

    foreach ($continues as $continue) {
      // If this is a "continue 2;" or similar, assume it's legitimate.
      $label = $continue->getChildByIndex(0);
      if ($label->getTypeName() !== 'n_EMPTY') {
        continue;
      }

      $node = $continue->getParentNode();
      while ($node) {
        $node_type = $node->getTypeName();

        // If we hit a valid loop which you can actually "continue;" inside,
        // this is legitimate and we're done here.
        if (isset($valid_containers[$node_type])) {
          break;
        }

        if ($node_type === 'n_SWITCH') {
          $this->raiseLintAtNode(
            $continue,
            pht(
              'In a "switch" statement, "continue;" is equivalent to "break;" '.
              'but causes compile errors beginning with PHP 7.0.0.'),
            'break');
        }

        $node = $node->getParentNode();
      }
    }
  }

}
