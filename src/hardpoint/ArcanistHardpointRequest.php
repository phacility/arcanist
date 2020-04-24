<?php

final class ArcanistHardpointRequest
  extends Phobject {

  private $engine;
  private $objects;
  private $hardpoint;
  private $hardpointDefinition;
  private $tasks = array();
  private $isComplete;

  public static function newFromSpecification($spec) {
    if ($spec instanceof ArcanistHardpointRequest) {
      return $spec;
    }

    if (is_string($spec)) {
      return id(new self())->setHardpoint($spec);
    }

    throw new Exception(
      pht(
        'Unknown Hardpoint request specification (of type "%s").',
        phutil_describe_type($spec)));
  }

  public function setEngine(ArcanistHardpointEngine $engine) {
    $this->engine = $engine;
    return $this;
  }

  public function getEngine() {
    return $this->engine;
  }

  public function setHardpoint($hardpoint) {
    $this->hardpoint = $hardpoint;
    return $this;
  }

  public function getHardpoint() {
    return $this->hardpoint;
  }

  public function setObjects(array $objects) {
    $this->objects = $objects;
    return $this;
  }

  public function getObjects() {
    return $this->objects;
  }

  public function newTask() {
    $task = id(new ArcanistHardpointTask())
      ->setRequest($this);

    $this->tasks[] = $task;
    $this->isComplete = false;

    return $task;
  }

  public function isComplete() {
    return $this->isComplete;
  }

  public function getTasks() {
    return $this->tasks;
  }

  public function updateTasks() {
    $any_progress = false;

    foreach ($this->tasks as $task) {
      $did_update = $task->updateTask();
      if ($did_update) {
        $any_progress = true;
      }
    }

    foreach ($this->tasks as $task_key => $task) {
      if ($task->isComplete()) {
        unset($this->tasks[$task_key]);
      }
    }

    if (!$this->tasks) {

      // TODO: We can skip or modify this check if the hardpoint is a vector
      // hardpoint.

      $objects = $this->getObjects();
      $hardpoint = $this->getHardpoint();
      foreach ($objects as $object) {
        if (!$object->hasAttachedHardpoint($hardpoint)) {
          throw new Exception(
            pht(
              'Unable to load hardpoint "%s" for object (of type "%s"). '.
              'All hardpoint query tasks resolved but none attached '.
              'a value to the hardpoint.',
              $hardpoint,
              phutil_describe_type($object)));
        }
      }

      // We may arrive here if a request is queued that can be satisfied
      // immediately, most often because it requests hardpoints which are
      // already attached. We don't have to do any work, so we have no tasks
      // to update or complete and can complete the request immediately.
      if (!$this->isComplete) {
        $any_progress = true;
      }

      $this->isComplete = true;
    }

    return $any_progress;
  }


  public function setHardpointDefinition($hardpoint_definition) {
    $this->hardpointDefinition = $hardpoint_definition;
    return $this;
  }

  public function getHardpointDefinition() {
    return $this->hardpointDefinition;
  }

}
