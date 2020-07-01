<?php

final class ArcanistCallConduitWorkflow
  extends ArcanistArcWorkflow {

  public function getWorkflowName() {
    return 'call-conduit';
  }

  public function getWorkflowInformation() {
    $help = pht(<<<EOTEXT
Allows you to make a raw Conduit method call:

  - Run this command from a working directory.
  - Call parameters are required, and read as a JSON blob from stdin.
  - Results are written to stdout as a JSON blob.

This workflow is primarily useful for writing scripts which integrate
with Phabricator. Examples:

  $ echo '{}' | arc call-conduit -- conduit.ping
  $ echo '{"phid":"PHID-FILE-xxxx"}' | arc call-conduit -- file.download
EOTEXT
      );

    return $this->newWorkflowInformation()
      ->setSynopsis(pht('Call Conduit API methods.'))
      ->addExample('**call-conduit** -- __method__')
      ->setHelp($help);
  }

  public function getWorkflowArguments() {
    return array(
      $this->newWorkflowArgument('method')
        ->setWildcard(true),
    );
  }

  public function runWorkflow() {
    $method = $this->getArgument('method');
    if (count($method) !== 1) {
      throw new PhutilArgumentUsageException(
        pht('Provide exactly one Conduit method name to call.'));
    }
    $method = head($method);

    $params = $this->readStdin();
    try {
      $params = phutil_json_decode($params);
    } catch (PhutilJSONParserException $ex) {
      throw new ArcanistUsageException(
        pht('Provide method parameters on stdin as a JSON blob.'));
    }

    $engine = $this->getConduitEngine();
    $conduit_future = $engine->newFuture($method, $params);

    $error = null;
    $error_message = null;
    try {
      $result = $conduit_future->resolve();
    } catch (ConduitClientException $ex) {
      $error = $ex->getErrorCode();
      $error_message = $ex->getMessage();
      $result = null;
    }

    echo id(new PhutilJSON())->encodeFormatted(
      array(
        'error' => $error,
        'errorMessage' => $error_message,
        'response' => $result,
      ));

    return 0;
  }

}
