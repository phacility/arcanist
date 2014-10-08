<?php

final class ArcanistNoneLintRenderer extends ArcanistLintRenderer {

  public function renderLintResult(ArcanistLintResult $result) {
    return '';
  }

  public function renderOkayResult() {
    return '';
  }

}
