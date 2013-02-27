<?php

/**
 * @group lint
 */
final class ArcanistLintNoneRenderer extends ArcanistLintRenderer {

  public function renderLintResult(ArcanistLintResult $result) {
    return '';
  }

  public function renderOkayResult() {
    return '';
  }

}
