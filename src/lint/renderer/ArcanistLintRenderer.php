<?php

/**
 * Shows lint messages to the user.
 *
 * @group lint
 */
abstract class ArcanistLintRenderer {

  public function renderPreamble() {
    return '';
  }

  abstract public function renderLintResult(ArcanistLintResult $result);
  abstract public function renderOkayResult();

  public function renderPostamble() {
    return '';
  }

}
