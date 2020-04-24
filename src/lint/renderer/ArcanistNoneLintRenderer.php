<?php

final class ArcanistNoneLintRenderer extends ArcanistLintRenderer {

  const RENDERERKEY = 'none';

  public function renderLintResult(ArcanistLintResult $result) {
    return null;
  }

}
