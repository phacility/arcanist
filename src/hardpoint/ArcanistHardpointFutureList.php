<?php

final class ArcanistHardpointFutureList
  extends Phobject {

  private $futures;
  private $sendResult;

  public static function newFromFutures(array $futures) {
    assert_instances_of($futures, 'Future');

    $object = new self();
    $object->futures = $futures;

    return $object;
  }

  public function getFutures() {
    return $this->futures;
  }

  public function setSendResult($send_result) {
    $this->sendResult = $send_result;
    return $this;
  }

  public function getSendResult() {
    return $this->sendResult;
  }

}
