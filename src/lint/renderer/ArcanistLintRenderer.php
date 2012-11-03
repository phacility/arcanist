<?php

/**
 * Shows lint messages to the user.
 *
 * @group lint
 */
interface ArcanistLintRenderer {
  public function renderLintResult(ArcanistLintResult $result);
  public function renderOkayResult();
}
