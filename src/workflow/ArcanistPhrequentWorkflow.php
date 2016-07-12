<?php

/**
 * Base workflow for Phrequent workflows.
 */
abstract class ArcanistPhrequentWorkflow extends ArcanistWorkflow {

  protected function printCurrentTracking() {
    $conduit = $this->getConduit();

    $results = $conduit->callMethodSynchronous(
      'phrequent.tracking',
      array());
    $results = $results['data'];

    if (count($results) === 0) {
      echo phutil_console_format(
        "%s\n",
        pht('Not currently tracking time against any object.'));
      return 0;
    }

    $phids_to_lookup = array();
    foreach ($results as $result) {
      $phids_to_lookup[] = $result['phid'];
    }

    $phid_query = $conduit->callMethodSynchronous(
      'phid.query',
      array(
        'phids' => $phids_to_lookup,
      ));

    $phid_map = array();
    foreach ($phids_to_lookup as $lookup) {
      if (array_key_exists($lookup, $phid_query)) {
        $phid_map[$lookup] = $phid_query[$lookup]['fullName'];
      } else {
        $phid_map[$lookup] = pht('Unknown Object');
      }
    }

    $table = id(new PhutilConsoleTable())
      ->addColumn('type', array('title' => pht('Status')))
      ->addColumn('time', array('title' => pht('Tracked'), 'align' => 'right'))
      ->addColumn('name', array('title' => pht('Name')))
      ->setBorders(false);

    $i = 0;
    foreach ($results as $result) {
      if ($i === 0) {
        $column_type = pht('In Progress');
      } else {
        $column_type = pht('Suspended');
      }

      $table->addRow(array(
        'type' => '('.$column_type.')',
        'time' => tsprintf($result['time']),
        'name' => $phid_map[$result['phid']],
      ));

      $i++;
    }

    $table->draw();
    return 0;
  }

}
