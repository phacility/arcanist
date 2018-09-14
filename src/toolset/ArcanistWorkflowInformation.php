<?php

final class ArcanistWorkflowInformation
  extends Phobject {

  private $help;
  private $examples = array();

  public function setHelp($help) {
    $this->help = $help;
    return $this;
  }

  public function getHelp() {
    return $this->help;
  }

  public function addExample($example) {
    $this->examples[] = $example;
    return $this;
  }

  public function getExamples() {
    return $this->examples;
  }

}

