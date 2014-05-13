<?php

/**
 * Shows lint messages to the user.
 *
 * @group lint
 */
abstract class ArcanistLintRenderer {

  abstract public function renderLintResult(ArcanistLintResult $result);
  abstract public function renderOkayResult();

  public function renderPostamble() {
    return '';
  }

}
