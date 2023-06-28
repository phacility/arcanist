<?php

final class ArcanistObjectListHardpoint
  extends ArcanistHardpoint {

  public function isVectorHardpoint() {
    return true;
  }

  public function mergeHardpointValues(
    ArcanistHardpointObject $object,
    $old,
    $new) {

    foreach ($new as $item) {
      $phid = $item->getPHID();
      if (!isset($old[$phid])) {
        $old[$phid] = $item;
      }
    }

    return $old;
  }

}
