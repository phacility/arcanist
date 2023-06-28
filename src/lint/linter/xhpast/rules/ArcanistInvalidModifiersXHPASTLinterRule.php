<?php

final class ArcanistInvalidModifiersXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 72;

  public function getLintName() {
    return pht('Invalid Modifiers');
  }

  public function process(XHPASTNode $root) {
    $methods = $root->selectDescendantsOfTypes(array(
      'n_CLASS_MEMBER_MODIFIER_LIST',
      'n_METHOD_MODIFIER_LIST',
    ));

    foreach ($methods as $method) {
      $modifiers = $method->getChildren();

      $is_abstract = false;
      $is_final    = false;
      $is_static   = false;
      $visibility  = null;
      $is_property = ($method->getTypeName() == 'n_CLASS_MEMBER_MODIFIER_LIST');
      $is_method = !$is_property;

      $final_modifier = null;
      $visibility_modifier = null;
      $abstract_modifier = null;

      foreach ($modifiers as $modifier) {
        switch ($modifier->getConcreteString()) {
          case 'abstract':
            if ($is_property) {
              $this->raiseLintAtNode(
                $modifier,
                pht(
                  'Properties cannot be declared "abstract".'));
            }

            if ($is_abstract) {
              $this->raiseLintAtNode(
                $modifier,
                pht(
                  'Multiple "abstract" modifiers are not allowed.'));
            }

            $abstract_modifier = $modifier;
            $is_abstract = true;
            break;

          case 'final':
            if ($is_property) {
              $this->raiseLintAtNode(
                $modifier,
                pht(
                  'Properties can not be declared "final".'));
            }

            if ($is_final) {
              $this->raiseLintAtNode(
                $modifier,
                pht(
                  'Multiple "final" modifiers are not allowed.'));
            }

            $final_modifier = $modifier;
            $is_final = true;
            break;
          case 'public':
          case 'protected':
          case 'private':
            if ($visibility !== null) {
              $this->raiseLintAtNode(
                $modifier,
                pht('Multiple access type modifiers are not allowed.'));
            }

            $visibility_modifier = $modifier;

            $visibility = $modifier->getConcreteString();
            $visibility = phutil_utf8_strtolower($visibility);
            break;

          case 'static':
            if ($is_static) {
              $this->raiseLintAtNode(
                $modifier,
                pht(
                  'Multiple "static" modifiers are not allowed.'));
            }
            break;
        }
      }

      $is_private = ($visibility === 'private');

      if ($is_final && $is_abstract) {
        if ($is_method) {
          $this->raiseLintAtNode(
            $final_modifier,
            pht('Methods may not be both "abstract" and "final".'));
        } else {
          // Properties can't be "abstract" and "final" either, but they can't
          // ever be "abstract" at all, and we've already raise a message about
          // that earlier.
        }
      }

      if ($is_private && $is_final) {
        if ($is_method) {
          $final_tokens = $final_modifier->getTokens();
          $space_tokens = last($final_tokens)->getWhitespaceTokensAfter();

          $final_offset = head($final_tokens)->getOffset();

          $final_string = array_merge($final_tokens, $space_tokens);
          $final_string = mpull($final_string, 'getValue');
          $final_string = implode('', $final_string);

          $this->raiseLintAtOffset(
            $final_offset,
            pht('Methods may not be both "private" and "final".'),
            $final_string,
            '');
        } else {
          // Properties can't be "final" at all, and we already raised a
          // message about this.
        }
      }
    }
  }

}
