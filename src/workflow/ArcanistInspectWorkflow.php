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
      $this->newWorkflowArgument('all')
        ->setHelp(pht('Load all object hardpoints.')),
      $this->newWorkflowArgument('objects')
        ->setWildcard(true),
    );
  }

  public function runWorkflow() {
    $is_all = $this->getArgument('all');
    $objects = $this->getArgument('objects');

    $inspectors = ArcanistRefInspector::getAllInspectors();

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
    $ref_lists = array();
    foreach ($objects as $description) {
      $matches = null;
      if (!preg_match('/^(\w+)(?:\(([^)]+)\))?\z/', $description, $matches)) {
        throw new PhutilArgumentUsageException(
          pht(
            'Object specification "%s" is unknown, expected a specification '.
            'like "commit(HEAD)".'));
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

      $ref_lists[get_class($ref)][] = $ref;
      $all_refs[] = $ref;
    }

    if ($is_all) {
      foreach ($ref_lists as $ref_class => $refs) {
        $ref = head($refs);

        $hardpoint_list = $ref->getHardpointList();
        $hardpoints = $hardpoint_list->getHardpoints();

        if ($hardpoints) {
          $hardpoint_keys = mpull($hardpoints, 'getHardpointKey');

          $this->loadHardpoints(
            $refs,
            $hardpoint_keys);
        }
      }
    }

    $list = array();
    foreach ($all_refs as $ref) {
      $out = $this->describeRef($ref, 0);
      $list[] = implode('', $out);
    }
    $list = implode("\n", $list);

    echo tsprintf('%B', $list);

    return 0;
  }

  private function describeRef(ArcanistRefPro $ref, $depth) {
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
    ArcanistRefPro $ref,
    ArcanistHardpoint $hardpoint,
    $depth) {
    $indent = str_repeat(' ', $depth);

    $children = array();
    $values = array();

    $hardpoint_key = $hardpoint->getHardpointKey();
    if ($ref->hasAttachedHardpoint($hardpoint_key)) {
      $mode = '*';
      $value = $ref->getHardpoint($hardpoint_key);
      if ($value instanceof ArcanistRefPro) {
        $children[] = $value;
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

}
