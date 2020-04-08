<?php

final class ArcanistVectorHardpoint
  extends ArcanistHardpoint {

  public function isVectorHardpoint() {
    return true;
  }

  public function mergeHardpointValues(
    ArcanistHardpointObject $object,
    $old,
    $new) {

    foreach ($new as $item) {
      $old[] = $item;
    }

    return $old;
  }

}
