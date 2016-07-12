<?php

/**
 * Shows lint messages to the user.
 */
abstract class ArcanistLintRenderer extends Phobject {

  public function renderPreamble() {
    return '';
  }

  abstract public function renderLintResult(ArcanistLintResult $result);
  abstract public function renderOkayResult();

  public function renderPostamble() {
    return '';
  }

}
