<?php

abstract class ArcanistUnitRenderer extends Phobject {
  abstract public function renderUnitResult(ArcanistUnitTestResult $result);
  abstract public function renderPostponedResult($count);
}
