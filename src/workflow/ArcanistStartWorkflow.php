<?php

/**
 * Start time tracking on an object.
 */
final class ArcanistStartWorkflow extends ArcanistPhrequentWorkflow {

  public function getWorkflowName() {
    return 'start';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **start** __object__
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

  public function requiresAuthentication() {
    return true;
  }

  public function getArguments() {
    return array(
      '*' => 'name',
    );
  }

  public function run() {
    $conduit = $this->getConduit();

    $started_phids = array();
    $short_name = $this->getArgument('name');

    foreach ($short_name as $object_name) {
      $object_lookup = $conduit->callMethodSynchronous(
        'phid.lookup',
        array(
          'names' => array($object_name),
        ));

      if (!array_key_exists($object_name, $object_lookup)) {
        echo phutil_console_format(
          "%s\n",
          pht("No such object '%s' found.", $object_name));
        return 1;
      }

      $object_phid = $object_lookup[$object_name]['phid'];

      $started_phids[] = $conduit->callMethodSynchronous(
        'phrequent.push',
        array(
          'objectPHID' => $object_phid,
        ));
    }

    $phid_query = $conduit->callMethodSynchronous(
      'phid.query',
      array(
        'phids' => $started_phids,
      ));

    echo phutil_console_format(
      "%s:  %s\n\n",
      pht('Started'),
      implode(', ', ipull($phid_query, 'fullName')));
    $this->printCurrentTracking();
  }

}
