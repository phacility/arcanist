<?php

abstract class ArcanistUnitRenderer {
  abstract public function renderUnitResult(ArcanistUnitTestResult $result);
  abstract public function renderPostponedResult($count);
}
