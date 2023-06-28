<?php

final class ArcanistInspectWorkflow
  extends ArcanistArcWorkflow {

  public function getWorkflowName() {
    return 'inspect';
  }

  public function getWorkflowInformation() {
    $help = pht(<<<EOTEXT
Inspect internal object properties.
EOTEXT
);

    return $this->newWorkflowInformation()
      ->setSynopsis(pht('Show internal object information.'))
      ->addExample(pht('**inspect** [__options__] -- __object__'))
      ->setHelp($help);
  }

  public function getWorkflowArguments() {
    return array(
      $this->newWorkflowArgument('explore')
        ->setHelp(pht('Load all object hardpoints.')),
      $this->newWorkflowArgument('objects')
        ->setWildcard(true),
    );
  }

  public function runWorkflow() {
    $is_explore = $this->getArgument('explore');
    $objects = $this->getArgument('objects');

    $inspectors = ArcanistRefInspector::getAllInspectors();

    foreach ($inspectors as $inspector) {
      $inspector->setWorkflow($this);
    }

    if (!$objects) {
      echo tsprintf(
        "%s\n\n",
        pht('Choose an object to inspect:'));

      foreach ($inspectors as $inspector) {
        echo tsprintf(
          "    - %s\n",
          $inspector->getInspectFunctionName());
      }

      echo tsprintf("\n");

      return 0;
    }

    $all_refs = array();
    foreach ($objects as $description) {
      $matches = null;
      $pattern = '/^([\w-]+)(?:\((.*)\))?\z/';
      if (!preg_match($pattern, $description, $matches)) {
        throw new PhutilArgumentUsageException(
          pht(
            'Object specification "%s" is unknown, expected a specification '.
            'like "commit(HEAD)".',
            $description));
      }

      $function = $matches[1];

      if (!isset($inspectors[$function])) {
        ksort($inspectors);
        throw new PhutilArgumentUsageException(
          pht(
            'Unknown object type "%s", supported types are: %s.',
            $function,
            implode(', ', array_keys($inspectors))));
      }

      $inspector = $inspectors[$function];

      if (isset($matches[2])) {
        $arguments = array($matches[2]);
      } else {
        $arguments = array();
      }

      $ref = $inspector->newInspectRef($arguments);

      $all_refs[] = $ref;
    }

    if ($is_explore) {
      $this->exploreRefs($all_refs);
    }

    $list = array();
    foreach ($all_refs as $ref) {
      $out = $this->describeRef($ref, 0);
      $list[] = $out;
    }
    $list = phutil_glue($list, "\n");

    echo tsprintf('%B', $list);

    return 0;
  }

  private function describeRef(ArcanistRef $ref, $depth) {
    $indent = str_repeat(' ', $depth);

    $out = array();

    $out[] = tsprintf(
      "%s+ [%s] %s\n",
      $indent,
      get_class($ref),
      $ref->getRefDisplayName());

    $hardpoint_list = $ref->getHardpointList();
    foreach ($hardpoint_list->getHardpoints() as $hardpoint) {
      $lines = $this->describeHardpoint($ref, $hardpoint, $depth + 1);
      foreach ($lines as $line) {
        $out[] = $line;
      }
    }

    return $out;
  }

  private function describeHardpoint(
    ArcanistRef $ref,
    ArcanistHardpoint $hardpoint,
    $depth) {
    $indent = str_repeat(' ', $depth);

    $children = array();
    $values = array();

    $hardpoint_key = $hardpoint->getHardpointKey();
    if ($ref->hasAttachedHardpoint($hardpoint_key)) {
      $mode = '*';
      $value = $ref->getHardpoint($hardpoint_key);

      if ($value instanceof ArcanistRef) {
        $children[] = $value;
      } else if (is_array($value)) {
        foreach ($value as $key => $child) {
          if ($child instanceof ArcanistRef) {
            $children[] = $child;
          } else {
            $values[] = $value;
          }
        }
      } else {
        $values[] = $value;
      }

    } else {
      $mode = 'o';
    }

    $out = array();
    $out[] = tsprintf(
      "%s%s [%s] %s\n",
      $indent,
      $mode,
      get_class($hardpoint),
      $hardpoint->getHardpointKey());

    foreach ($children as $child) {
      $lines = $this->describeRef($child, $depth + 1);
      foreach ($lines as $line) {
        $out[] = $line;
      }
    }

    foreach ($values as $value) {
      $lines = $this->describeValue($value, $depth + 1);
      foreach ($lines as $line) {
        $out[] = $line;
      }
    }

    return $out;
  }

  private function describeValue($value, $depth) {
    $indent = str_repeat(' ', $depth);

    if (is_string($value)) {
      $display_value = '"'.addcslashes(substr($value, 0, 64), "\n\r\t\\\"").'"';
    } else if (is_scalar($value)) {
      $display_value = phutil_string_cast($value);
    } else if ($value === null) {
      $display_value = 'null';
    } else {
      $display_value = phutil_describe_type($value);
    }

    $out = array();
    $out[] = tsprintf(
      "%s> %s\n",
      $indent,
      $display_value);
    return $out;
  }

  private function exploreRefs(array $refs) {
    $seen = array();
    $look = $refs;

    while ($look) {
      $ref_map = $this->getRefsByClass($look);
      $look = array();

      $children = $this->inspectHardpoints($ref_map);

      foreach ($children as $child) {
        $hash = spl_object_hash($child);

        if (isset($seen[$hash])) {
          continue;
        }

        $seen[$hash] = true;
        $look[] = $child;
      }
    }
  }

  private function getRefsByClass(array $refs) {
    $ref_lists = array();
    foreach ($refs as $ref) {
      $ref_lists[get_class($ref)][] = $ref;
    }

    foreach ($ref_lists as $ref_class => $refs) {
      $typical_ref = head($refs);

      $hardpoint_list = $typical_ref->getHardpointList();
      $hardpoints = $hardpoint_list->getHardpoints();

      if (!$hardpoints) {
        unset($ref_lists[$ref_class]);
        continue;
      }

      $hardpoint_keys = mpull($hardpoints, 'getHardpointKey');

      $ref_lists[$ref_class] = array(
        'keys' => $hardpoint_keys,
        'refs' => $refs,
      );
    }

    return $ref_lists;
  }

  private function inspectHardpoints(array $ref_lists) {
    foreach ($ref_lists as $ref_class => $spec) {
      $refs = $spec['refs'];
      $keys = $spec['keys'];

      $this->loadHardpoints($refs, $keys);
    }

    $child_refs = array();

    foreach ($ref_lists as $ref_class => $spec) {
      $refs = $spec['refs'];
      $keys = $spec['keys'];
      foreach ($refs as $ref) {
        foreach ($keys as $key) {
          $value = $ref->getHardpoint($key);

          if (!is_array($value)) {
            $value = array($value);
          }

          foreach ($value as $child) {
            if ($child instanceof ArcanistRef) {
              $child_refs[] = $child;
            }
          }
        }
      }
    }

    return $child_refs;
  }

}
