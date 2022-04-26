<?php

/**
 * Support of "differential.mandatory_fields" config value of .arcconfig.
 *
 * Validate if all required fields in commit message are populated. Throw an
 * exception with error message if not.
 *
 * Example of config:
 *
 * "differential.mandatory_fields": [{
 *   "field_name": "revertPlan",
 *   "field_message": "Revert plan is mandatory",
 *   "paths_regex": [
 *      "/^src\\//",
 *      "/^idl\\//"
 *   ]
 * }]
 *
 */
final class UberMandatoryFields extends Phobject {

  private $workflow;

  public function __construct(ArcanistWorkflow $workflow) {
    $this->workflow = $workflow;
  }

  public function validateRevision(array $revision, array $affected_paths) {
    $fields = $this->loadMandatoryFields();
    if (empty($fields)) {
      return;
    }

    $console = PhutilConsole::getConsole();
    $conduit = $this->workflow->getConduit();

    foreach ($fields as $field) {
      $name = $field['field_name'];
      $is_empty = $this->revisionFieldEmpty($revision, $name);

      if ($is_empty && $this->affectedPathExist($field, $affected_paths)) {
        $updated = false;
        while (!$updated) {
          $msg = $field['field_message'].' Enter message to update:';
          $value = phutil_console_prompt($msg);
          if (trim($value) == '') {
            $console->writeOut(
              "    <bg:red>**Message can't be empty**</bg>\n");
            continue;
          }
          $updated = true;
          $response = $conduit->callMethodSynchronous(
            'differential.revision.edit',
            array(
              'objectIdentifier' => $revision['id'],
              'transactions' => array(
                array(
                  'type' => $name,
                  'value' => $value,
                ),
              ),
            ));
          if (isset($response['transactions'])
            && !empty($response['transactions'])) {
            $console->writeOut("    <bg:green>**Revision updated**</bg>\n");
          }
        }
      }
    }
  }

  private function loadMandatoryFields() {
    return $this
      ->workflow
      ->getConfigurationManager()
      ->getConfigFromAnySource('differential.mandatory_fields');
  }

  /**
   * Check if revision field is empty.
   *
   * Fields can be added at the root level or as auxiliary field. Both places
   * must be checked if field exists and if it is empty.
   */
  private function revisionFieldEmpty(array $revision, $field_name) {
    return (
      array_key_exists($field_name, $revision)
        && empty($revision[$field_name]))
        || (
      array_key_exists($field_name, $revision['auxiliary'])
        && empty($revision['auxiliary'][$field_name]));
  }

  private function affectedPathExist(
      array $mandatory_field, array $affected_paths) {

    $paths_regex = idx($mandatory_field, 'paths_regex');
    if (empty($paths_regex)) {
      return true;
    }

    // Check if affected paths match regex.
    foreach ($affected_paths as $_path) {
      foreach ($paths_regex as $_regex) {
        if (preg_match($_regex, $_path)) {
          return true;
        }
      }
    }
    return false;
  }

  public function validateCommitMessage($message, array $affected_paths) {
    if (!$message instanceof ArcanistDifferentialCommitMessage) {
      return;
    }

    $fields = $this->loadMandatoryFields();
    if (empty($fields)) {
      return;
    }

    $missing_fields = array();
    foreach ($fields as $field) {
      $field_name = $field['field_name'];
      $paths_regex = idx($field, 'paths_regex');

      $is_empty = empty($message->getFieldValue($field_name));
      if ($is_empty && $this->affectedPathExist($field, $affected_paths)) {
        $missing_fields[] = $field['field_message'];
      }
    }

    if (!empty($missing_fields)) {
      throw new ArcanistUsageException(
        "Following errors were found:\n - " . implode("\n - ", $missing_fields));
    }
  }
}
