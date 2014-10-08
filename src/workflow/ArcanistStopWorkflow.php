<?php

/**
 * Stop time tracking on an object
 */
final class ArcanistStopWorkflow extends ArcanistPhrequentWorkflow {

  public function getWorkflowName() {
    return 'stop';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **stop** [--note __note__] [__objects__]
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
Start tracking work in Phrequent.
EOTEXT
      );
  }

  public function requiresConduit() {
    return true;
  }

  public function desiresWorkingCopy() {
    return false;
  }

  public function requiresAuthentication() {
    return true;
  }

  public function getArguments() {
    return array(
      'note' => array(
        'param' => 'note',
        'help' =>
          'A note to attach to the tracked time.',
      ),
      '*' => 'name',
    );
  }

  public function run() {
    $conduit = $this->getConduit();

    $names = $this->getArgument('name');

    $object_lookup = $conduit->callMethodSynchronous(
      'phid.lookup',
      array(
        'names' => $names,
      ));

    foreach ($names as $object_name) {
      if (!array_key_exists($object_name, $object_lookup)) {
        throw new ArcanistUsageException(
          "No such object '".$object_name."' found.");
        return 1;
      }
    }

    if (count($names) === 0) {
      // Implicit stop; add an entry so the loop will call
      // phrequent.pop with a null objectPHID.
      $object_lookup[] = array('phid' => null);
    }

    $stopped_phids = array();
    foreach ($object_lookup as $ref) {
      $object_phid = $ref['phid'];

      $stopped_phid = $conduit->callMethodSynchronous(
        'phrequent.pop',
        array(
          'objectPHID' => $object_phid,
          'note' => $this->getArgument('note'),
        ));
      if ($stopped_phid !== null) {
        $stopped_phids[] = $stopped_phid;
      }
    }

    if (count($stopped_phids) === 0) {
      if (count($names) === 0) {
        echo phutil_console_format(
          "Not currently tracking time against any object\n");
      } else {
        $name = '';
        foreach ($object_lookup as $ref) {
          if ($name === '') {
            $name = $ref['fullName'];
          } else {
            $name = ', '.$ref['fullName'];
          }
        }

        echo phutil_console_format(
          "Not currently tracking time against %s\n",
          $name);
      }
      return 1;
    }

    $phid_query = $conduit->callMethodSynchronous(
      'phid.query',
      array(
        'phids' => $stopped_phids,
      ));

    $name = '';
    foreach ($phid_query as $ref) {
      if ($name === '') {
        $name = $ref['fullName'];
      } else {
        $name .= ', '.$ref['fullName'];
      }
    }

    echo phutil_console_format(
      "Stopped:  %s\n\n",
      $name);

    $this->printCurrentTracking(true);
  }

}
