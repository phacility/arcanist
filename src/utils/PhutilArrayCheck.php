<?php

final class PhutilArrayCheck
  extends Phobject {

  private $instancesOf;
  private $uniqueMethod;
  private $context;

  private $object;
  private $method;

  public function setInstancesOf($instances_of) {
    $this->instancesOf = $instances_of;
    return $this;
  }

  public function getInstancesOf() {
    return $this->instancesOf;
  }

  public function setUniqueMethod($unique_method) {
    $this->uniqueMethod = $unique_method;
    return $this;
  }

  public function getUniqueMethod() {
    return $this->uniqueMethod;
  }

  public function setContext($object, $method) {
    if (is_array($object)) {
      foreach ($object as $idx => $value) {
        if (!is_object($value)) {
          throw new Exception(
            pht(
              'Expected an object, string, or list of objects for "object" '.
              'context. Got a list ("%s"), but the list item at index '.
              '"%s" (with type "%s") is not an object.',
              phutil_describe_type($object),
              $idx,
              phutil_describe_type($value)));
        }
      }
    } else if (!is_object($object) && !is_string($object)) {
      throw new Exception(
        pht(
          'Expected an object, string, or list of objects for "object" '.
          'context, got "%s".',
          phutil_describe_type($object)));
    }

    if (!is_string($method)) {
      throw new Exception(
        pht(
          'Expected a string for "method" context, got "%s".',
          phutil_describe_type($method)));
    }

    $argv = func_get_args();
    $argv = array_slice($argv, 2);

    $this->context = array(
      'object' => $object,
      'method' => $method,
      'argv' => $argv,
    );

    return $this;
  }

  public function checkValues($maps) {
    foreach ($maps as $idx => $map) {
      $maps[$idx] = $this->checkValue($map);
    }

    $unique = $this->getUniqueMethod();
    if ($unique === null) {
      $result = array();

      foreach ($maps as $map) {
        foreach ($map as $value) {
          $result[] = $value;
        }
      }
    } else {
      $items = array();
      foreach ($maps as $idx => $map) {
        foreach ($map as $key => $value) {
          $items[$key][$idx] = $value;
        }
      }

      foreach ($items as $key => $values) {
        if (count($values) === 1) {
          continue;
        }
        $this->raiseValueException(
          pht(
            'Unexpected return value from calls to "%s(...)". More than one '.
            'object returned a value with unique key "%s". This key was '.
            'returned by objects with indexes: %s.',
            $unique,
            $key,
            implode(', ', array_keys($values))));
      }

      $result = array();
      foreach ($items as $key => $values) {
        $result[$key] = head($values);
      }
    }

    return $result;
  }

  public function checkValue($items) {
    if (!$this->context) {
      throw new PhutilInvalidStateException('setContext');
    }

    if (!is_array($items)) {
      $this->raiseValueException(
        pht(
          'Expected value to be a list, got "%s".',
          phutil_describe_type($items)));
    }

    $instances_of = $this->getInstancesOf();
    if ($instances_of !== null) {
      foreach ($items as $idx => $item) {
        if (!($item instanceof $instances_of)) {
          $this->raiseValueException(
            pht(
              'Expected value to be a list of objects which are instances of '.
              '"%s", but item with index "%s" is "%s".',
              $instances_of,
              $idx,
              phutil_describe_type($item)));
        }
      }
    }

    $unique = $this->getUniqueMethod();
    if ($unique !== null) {
      if ($instances_of === null) {
        foreach ($items as $idx => $item) {
          if (!is_object($item)) {
            $this->raiseValueException(
              pht(
                'Expected value to be a list of objects to support calling '.
                '"%s" to generate unique keys, but item with index "%s" is '.
                '"%s".',
                $unique,
                $idx,
                phutil_describe_type($item)));
          }
        }
      }

      $map = array();

      foreach ($items as $idx => $item) {
        $key = call_user_func(array($item, $unique));

        if (!is_string($key) && !is_int($key)) {
          $this->raiseValueException(
            pht(
              'Expected method "%s->%s()" to return a string or integer for '.
              'use as a unique key, got "%s" from object at index "%s".',
              get_class($item),
              $unique,
              phutil_describe_type($key),
              $idx));
        }

        $key = phutil_string_cast($key);

        $map[$key][$idx] = $item;
      }

      $result = array();
      foreach ($map as $key => $values) {
        if (count($values) === 1) {
          $result[$key] = head($values);
          continue;
        }

        $classes = array();
        foreach ($values as $value) {
          $classes[] = get_class($value);
        }
        $classes = array_fuse($classes);

        if (count($classes) === 1) {
          $class_display = head($classes);
        } else {
          $class_display = sprintf(
            '[%s]',
            implode(', ', $classes));
        }

        $index_display = array();
        foreach ($values as $idx => $value) {
          $index_display[] = pht(
            '"%s" (%s)',
            $idx,
            get_class($value));
        }
        $index_display = implode(', ', $index_display);

        $this->raiseValueException(
          pht(
            'Expected method "%s->%s()" to return a unique key, got "%s" '.
            'from %s object(s) at indexes: %s.',
            $class_display,
            $unique,
            $key,
            phutil_count($values),
            $index_display));
      }

      $items = $result;
    }

    return $items;
  }

  private function raiseValueException($message) {
    $context = $this->context;

    $object = $context['object'];
    $method = $context['method'];
    $argv = $context['argv'];

    if (is_array($object)) {
      $classes = array();
      foreach ($object as $item) {
        $classes[] = get_class($item);
      }
      $classes = array_fuse($classes);
      $n = count($object);

      $object_display = sprintf(
        '[%s]<%d>->%s',
        implode(', ', $classes),
        $n,
        $method);
    } else if (is_object($object)) {
      $object_display = sprintf(
        '%s->%s',
        get_class($object),
        $method);
    } else {
      $object_display = sprintf(
        '%s::%s',
        $object,
        $method);
    }

    if (count($argv)) {
      $call_display = sprintf(
        '%s(...)',
        $object_display);
    } else {
      $call_display = sprintf(
        '%s()',
        $object_display);
    }

    throw new Exception(
      pht(
        'Unexpected return value from call to "%s": %s.',
        $call_display,
        $message));
  }

}
