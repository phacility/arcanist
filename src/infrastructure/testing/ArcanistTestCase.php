<?php

abstract class ArcanistTestCase extends ArcanistPhutilTestCase {

  protected function getLink($method) {
    $arcanist_project = 'PHID-APRJ-703e0b140530f17ede30';
    return
      'https://secure.phabricator.com/diffusion/symbol/'.$method.
      '/?lang=php&projects='.$arcanist_project.
      '&jump=true&context='.get_class($this);
  }

}
