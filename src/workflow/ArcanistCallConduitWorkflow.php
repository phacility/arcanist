<?php

/**
 * Provides command-line access to the Conduit API.
 */
final class ArcanistCallConduitWorkflow extends ArcanistWorkflow {

  public function getWorkflowName() {
    return 'call-conduit';
  }

  public function getWorkflowInformation() {
    $help = pht(<<<EOTEXT
Make a call to Conduit, the Phabricator API.

For example:

  $ echo '{}' | arc call-conduit conduit.ping
EOTEXT
);

    return $this->newWorkflowInformation()
      ->addExample(pht('**call-conduit** __method__'))
      ->setHelp($help);
  }

  public function getWorkflowArguments() {
    return array(
      $this->newWorkflowArgument('argv')
        ->setWildcard(true),
    );
  }

  public function runWorkflow() {
    $argv = $this->getArgument('argv');

    if (!$argv) {
      // TOOLSETS: We should call "conduit.query" and list available methods
      // here.
      throw new PhutilArgumentUsageException(
        pht(
          'Provide the name of the Conduit method you want to call on the '.
          'command line.'));
    } else if (count($argv) > 1) {
      throw new PhutilArgumentUsageException(
        pht(
          'Provide the name of only one method to call.'));
    }

    $method = head($argv);

    if (!function_exists('posix_isatty') || posix_isatty(STDIN)) {
      fprintf(
        STDERR,
        tsprintf(
          "%s\n",
          pht('Waiting for JSON parameters on stdin...')));
    }

    $params = @file_get_contents('php://stdin');
    try {
      $params = phutil_json_decode($params);
    } catch (PhutilJSONParserException $ex) {
      throw new ArcanistUsageException(
        pht('Provide method parameters on stdin as a JSON blob.'));
    }

    $error = null;
    $error_message = null;
    try {
      $result = $this->getConduitEngine()->resolveCall($method, $params);
    } catch (ConduitClientException $ex) {

      // TOOLSETS: We should use "conduit.query" to suggest similar calls if
      // it looks like the user called a method which does not exist.

      $error = $ex->getErrorCode();
      $error_message = $ex->getMessage();
      $result = null;
    }

    echo id(new PhutilJSON())
      ->encodeFormatted(
        array(
          'error' => $error,
          'errorMessage' => $error_message,
          'response' => $result,
        ));

    return 0;
  }

}
