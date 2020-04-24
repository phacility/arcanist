<?php

final class ArcanistHardpointTask
  extends Phobject {

  private $request;
  private $query;
  private $objects;

  private $isComplete;
  private $generator;
  private $hasRewound;
  private $sendFuture;

  private $blockingRequests = array();
  private $blockingFutures = array();

  public function setRequest(ArcanistHardpointRequest $request) {
    $this->request = $request;
    return $this;
  }

  public function getRequest() {
    return $this->request;
  }

  public function setQuery(ArcanistHardpointQuery $query) {
    $this->query = $query;
    return $this;
  }

  public function getQuery() {
    return $this->query;
  }

  public function setObjects(array $objects) {
    $this->objects = $objects;
    return $this;
  }

  public function getObjects() {
    return $this->objects;
  }

  public function isComplete() {
    return $this->isComplete;
  }

  public function updateTask() {
    if ($this->isComplete()) {
      return false;
    }

    // If we're blocked by other requests, we have to wait for them to
    // resolve.
    if ($this->getBlockingRequests()) {
      return false;
    }

    // If we're blocked by futures, we have to wait for them to resolve.
    if ($this->getBlockingFutures()) {
      return false;
    }

    $query = $this->getQuery();

    // If we've previously produced a generator, iterate it.

    if ($this->generator) {
      $generator = $this->generator;

      $has_send = false;
      $send_value = null;

      // If our last iteration generated a single future and it was marked to
      // be sent back to the generator, resolve the future (it should already
      // be ready to resolve) and send the result.

      if ($this->sendFuture) {
        $has_send = true;
        $future = $this->sendFuture;
        $this->sendFuture = null;

        $send_value = $future->resolve();
      }

      if ($has_send && !$this->hasRewound) {
        throw new Exception(
          pht(
            'Generator has never rewound, but has a value to send. This '.
            'is invalid.'));
      }

      if (!$this->hasRewound) {
        $this->hasRewound = true;
        $generator->rewind();
      } else if ($has_send) {
        $generator->send($send_value);
      } else {
        $generator->next();
      }

      $generator_result = null;
      if ($generator->valid()) {
        $result = $generator->current();

        if ($result instanceof Future) {
          $result = new ArcanistHardpointFutureList($result);
        }

        if ($result instanceof ArcanistHardpointFutureList) {
          $futures = $result->getFutures();
          $is_send = $result->getSendResult();

          $this->getRequest()->getEngine()->addFutures($futures);

          foreach ($futures as $future) {
            $this->blockingFutures[] = $future;
          }

          if ($is_send) {
            if (count($futures) === 1) {
              $this->sendFuture = head($futures);
            } else {
              throw new Exception(
                pht(
                  'Hardpoint future list is marked to send results to the '.
                  'generator, but the list does not have exactly one future '.
                  '(it has %s).',
                  phutil_count($futures)));
            }
          }

          return true;
        }

        $is_request = ($result instanceof ArcanistHardpointRequest);
        $is_request_list = ($result instanceof ArcanistHardpointRequestList);
        if ($is_request || $is_request_list) {
          if ($is_request) {
            $request_list = array($result);
          } else {
            $request_list = $result->getRequests();
          }

          // TODO: Make sure these requests have already been added to the
          // engine.

          foreach ($request_list as $blocking_request) {
            $this->blockingRequests[] = $blocking_request;
          }

          return true;
        }

        if ($result instanceof ArcanistHardpointTaskResult) {
          $generator_result = $result;
        } else {
          throw new Exception(
            pht(
              'Hardpoint generator (for query "%s") yielded an unexpected '.
              'value (of type "%s").',
              get_class($query),
              phutil_describe_type($result)));
        }
      }

      $this->generator = null;

      if ($generator_result !== null) {
        $result = $generator_result->getValue();
      } else {
        $result = $generator->getReturn();

        if ($result instanceof ArcanistHardpointTaskResult) {
          throw new Exception(
            pht(
              'Generator (for query "%s") returned an '.
              '"ArcanistHardpointTaskResult" object, which is not a valid '.
              'thing to return from a generator.'.
              "\n\n".
              'This almost always means the generator implementation has a '.
              '"return $this->yield..." statement which should be '.
              'a "yield $this->yield..." instead.',
              get_class($query)));
        }
      }

      $this->attachResult($result);

      return true;
    }

    $objects = $this->getObjects();
    $hardpoint = $this->getRequest()->getHardpoint();

    $result = $query->loadHardpoint($objects, $hardpoint);
    if ($result instanceof Generator) {
      $this->generator = $result;
      $this->hasRewound = false;

      // If we produced a generator, we can attempt to iterate it immediately.
      return $this->updateTask();
    }

    $this->attachResult($result);

    return true;
  }

  public function getBlockingRequests() {
    $blocking = array();

    foreach ($this->blockingRequests as $key => $request) {
      if (!$request->isComplete()) {
        $blocking[$key] = $request;
      }
    }

    $this->blockingRequests = $blocking;

    return $blocking;
  }

  public function getBlockingFutures() {
    $blocking = array();

    foreach ($this->blockingFutures as $key => $future) {
      if (!$future->hasResult() && !$future->hasException()) {
        $blocking[$key] = $future;
      }
    }

    $this->blockingFutures = $blocking;

    return $blocking;
  }

  private function attachResult($result) {
    $objects = $this->getObjects();
    $hardpoint = $this->getRequest()->getHardpoint();

    $definition = $this->getRequest()->getHardpointDefinition();
    $is_vector = $definition->isVectorHardpoint();

    foreach ($result as $object_key => $value) {
      if (!isset($objects[$object_key])) {
        throw new Exception(
          pht(
            'Bad object key ("%s").',
            $object_key));
      }

      $object = $objects[$object_key];

      if ($is_vector) {
        $object->mergeHardpoint($hardpoint, $value);
      } else {
        if (!$object->hasAttachedHardpoint($hardpoint)) {
          $object->attachHardpoint($hardpoint, $value);
        }
      }
    }

    $this->isComplete = true;
  }

}
