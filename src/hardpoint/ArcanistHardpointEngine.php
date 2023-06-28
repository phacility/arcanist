<?php

final class ArcanistHardpointEngine
  extends Phobject {

  private $queries;
  private $queryHardpointMap = array();

  private $requests = array();

  private $futureIterator;
  private $waitFutures = array();

  public function setQueries(array $queries) {
    assert_instances_of($queries, 'ArcanistHardpointQuery');

    $this->queries = $queries;
    $this->queryHardpointMap = null;

    return $this;
  }

  private function getQueriesForHardpoint($hardpoint) {
    if ($this->queryHardpointMap === null) {
      $map = array();

      foreach ($this->queries as $query_key => $query) {
        $query->setHardpointEngine($this);

        $hardpoints = $query->getHardpoints();

        foreach ($hardpoints as $query_hardpoint) {
          $map[$query_hardpoint][$query_key] = $query;
        }
      }

      $this->queryHardpointMap = $map;
    }

    return idx($this->queryHardpointMap, $hardpoint, array());
  }

  public function requestHardpoints(array $objects, array $requests) {
    assert_instances_of($objects, 'ArcanistHardpointObject');

    $results = array();
    foreach ($requests as $request) {
      $request = ArcanistHardpointRequest::newFromSpecification($request)
        ->setEngine($this)
        ->setObjects($objects);

      $this->requests[] = $request;

      $this->startRequest($request);

      $results[] = $request;
    }

    return ArcanistHardpointRequestList::newFromRequests($results);
  }

  private function startRequest(ArcanistHardpointRequest $request) {
    $objects = $request->getObjects();
    $hardpoint = $request->getHardpoint();

    $queries = $this->getQueriesForHardpoint($hardpoint);

    $load = array();
    foreach ($objects as $object_key => $object) {
      if (!$object->hasHardpoint($hardpoint)) {
        throw new Exception(
          pht(
            'Object (with key "%s", of type "%s") has no hardpoint "%s". '.
            'Hardpoints on this object are: %s.',
            $object_key,
            phutil_describe_type($object),
            $hardpoint,
            $object->getHardpointList()->getHardpointListForDisplay()));
      }

      // If the object already has the hardpoint attached, we don't have to
      // do anything. Throw the object away.

      if ($object->hasAttachedHardpoint($hardpoint)) {
        unset($objects[$object_key]);
        continue;
      }

      $any_query = false;
      foreach ($queries as $query_key => $query) {
        if (!$query->canLoadObject($object)) {
          continue;
        }

        $any_query = true;
        $load[$query_key][$object_key] = $object;
      }

      if (!$any_query) {
        throw new Exception(
          pht(
            'No query exists which can load hardpoint "%s" for object '.
            '(with key "%s" of type "%s").',
            $hardpoint,
            $object_key,
            phutil_describe_type($object)));
      }
    }

    if (!$objects) {
      return;
    }

    $any_object = head($objects);
    $list = $object->getHardpointList();
    $definition = $list->getHardpointDefinition($any_object, $hardpoint);

    $is_vector = ($definition->isVectorHardpoint());

    if ($is_vector) {
      foreach ($objects as $object) {
        $object->attachHardpoint($hardpoint, array());
      }
    }

    $request->setHardpointDefinition($definition);

    foreach ($load as $query_key => $object_map) {
      $query = id(clone $queries[$query_key]);

      $task = $request->newTask()
        ->setQuery($query)
        ->setObjects($object_map);
    }
  }

  public function waitForRequests(array $wait_requests) {
    foreach ($wait_requests as $wait_key => $wait_request) {
      if ($wait_request->getEngine() !== $this) {
        throw new Exception(
          pht(
            'Attempting to wait on a hardpoint request (with index "%s", for '.
            'hardpoint "%s") that is part of a different engine.',
            $wait_key,
            $wait_request->getHardpoint()));
      }
    }

    while (true) {
      $any_progress = false;
      foreach ($this->requests as $req_key => $request) {
        $did_update = $request->updateTasks();
        if ($did_update) {
          $any_progress = true;
        }
      }

      // If we made progress by directly executing requests, continue
      // excuting them until we stop making progress. We want to queue all
      // reachable futures before we wait on futures.

      if ($any_progress) {
        continue;
      }

      foreach ($this->requests as $request_key => $request) {
        if ($request->isComplete()) {
          unset($this->requests[$request_key]);
        }
      }

      if (!$this->requests) {
        break;
      }

      $resolved_key = $this->updateFutures();

      if ($resolved_key === null) {
        throw new Exception(
          pht(
            'Hardpoint engine can not resolve: no request made progress '.
            'during the last update cycle and there are no futures '.
            'awaiting resolution.'));
      }
    }
  }

  private function updateFutures() {
    $iterator = $this->futureIterator;

    $is_rewind = false;
    $wait_futures = $this->waitFutures;
    if ($wait_futures) {
      if (!$this->futureIterator) {
        $iterator = id(new FutureIterator(array()))
          ->limit(32);
        foreach ($wait_futures as $wait_future) {
          $iterator->addFuture($wait_future);
        }
        $is_rewind = true;
        $this->futureIterator = $iterator;
      } else {
        foreach ($wait_futures as $wait_future) {
          $iterator->addFuture($wait_future);
        }
      }
      $this->waitFutures = array();
    }

    $resolved_key = null;
    if ($iterator) {
      if ($is_rewind) {
        $iterator->rewind();
      } else {
        $iterator->next();
      }

      if ($iterator->valid()) {
        $resolved_key = $iterator->key();
      } else {
        $this->futureIterator = null;
      }
    }

    return $resolved_key;
  }

  public function addFutures(array $futures) {
    assert_instances_of($futures, 'Future');
    $this->waitFutures += mpull($futures, null, 'getFutureKey');

    // TODO: We could reasonably add these futures to the iterator
    // immediately and start them here, instead of waiting.

    return $this;
  }

}
