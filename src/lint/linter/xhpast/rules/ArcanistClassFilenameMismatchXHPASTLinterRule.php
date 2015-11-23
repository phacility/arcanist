<?php

/**
 * Lint that if the file declares exactly one interface or class, the name of
 * the file matches the name of the class, unless the class name is funky like
 * an XHP element.
 */
final class ArcanistClassFilenameMismatchXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 19;

  public function getLintName() {
    return pht('Class-Filename Mismatch');
  }

  public function process(XHPASTNode $root) {
    $classes = $root->selectDescendantsOfType('n_CLASS_DECLARATION');
    $interfaces = $root->selectDescendantsOfType('n_INTERFACE_DECLARATION');

    if (count($classes) + count($interfaces) !== 1) {
      return;
    }

    $declarations = count($classes) ? $classes : $interfaces;
    $declarations->rewind();
    $declaration = $declarations->current();

    $decl_name = $declaration->getChildByIndex(1);
    $decl_string = $decl_name->getConcreteString();

    // Exclude strangely named classes, e.g. XHP tags.
    if (!preg_match('/^\w+$/', $decl_string)) {
      return;
    }

    $rename = $decl_string.'.php';

    $path = $this->getActivePath();
    $filename = basename($path);

    if ($rename === $filename) {
      return;
    }

    $this->raiseLintAtNode(
      $decl_name,
      pht(
        "The name of this file differs from the name of the ".
        'class or interface it declares. Rename the file to `%s`.',
        $rename));
  }

}
