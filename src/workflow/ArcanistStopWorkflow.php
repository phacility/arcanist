<?php

/**
 * Stop time tracking on an object.
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
          Stop tracking work in Phrequent.
EOTEXT
      );
  }

  public function requiresConduit() {
    return true;
  }

  public function requiresAuthentication() {
    return true;
  }

  public function getArguments() {
    return array(
      'note' => array(
        'param' => 'note',
        'help' => pht('A note to attach to the tracked time.'),
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
          pht("No such object '%s' found.", $object_name));
        return 1;
      }
    }

    if (count($names) === 0) {
      // Implicit stop; add an entry so the loop will call
      // `phrequent.pop` with a null `objectPHID`.
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
          "%s\n",
          pht('Not currently tracking time against any object.'));
      } else {
        echo phutil_console_format(
          "%s\n",
          pht(
            'Not currently tracking time against %s.',
            implode(', ', ipull($object_lookup, 'fullName'))));
      }
      return 1;
    }

    $phid_query = $conduit->callMethodSynchronous(
      'phid.query',
      array(
        'phids' => $stopped_phids,
      ));

    echo phutil_console_format(
      "%s  %s\n\n",
      pht('Stopped:'),
      implode(', ', ipull($phid_query, 'fullName')));
    $this->printCurrentTracking();
  }

}
