<?php

final class ArcanistPasteWorkflow
  extends ArcanistArcWorkflow {

  public function getWorkflowName() {
    return 'paste';
  }

  public function getWorkflowInformation() {
    $help = pht(<<<EOTEXT
Share and grab text using the Paste application. To create a paste,
use stdin to provide the text:

  $ cat list_of_ducks.txt | arc paste

To retrieve a paste, specify the paste ID:

  $ arc paste P123
EOTEXT
      );

    return $this->newWorkflowInformation()
      ->addExample('**paste** [__options__] --')
      ->addExample('**paste** [__options__] -- __object__')
      ->setHelp($help);
  }

  public function getWorkflowArguments() {
    return array(
      $this->newWorkflowArgument('title')
        ->setParameter('title')
        ->setHelp(pht('Title for the paste.')),
      $this->newWorkflowArgument('lang')
        ->setParameter('language')
        ->setHelp(pht('Language for the paste.')),
      $this->newWorkflowArgument('json')
        ->setHelp(pht('Output in JSON format.')),
      $this->newWorkflowArgument('argv')
        ->setWildcard(true),
    );
  }

  public function runWorkflow() {
    $set_language = $this->getArgument('lang');
    $set_title = $this->getArgument('title');

    $argv = $this->getArgument('argv');
    if (count($argv) > 1) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify only one paste to retrieve.'));
    }

    $symbols = $this->getSymbolEngine();

    if (count($argv) === 1) {
      if ($set_language !== null) {
        throw new PhutilArgumentUsageException(
          pht(
            'Flag "--lang" is not supported when reading pastes.'));
      }

      if ($set_title !== null) {
        throw new PhutilArgumentUsageException(
          pht(
            'Flag "--title" is not supported when reading pastes.'));
      }

      $paste_symbol = $argv[0];

      $paste_ref = $symbols->loadPasteForSymbol($paste_symbol);
      if (!$paste_ref) {
        throw new PhutilArgumentUsageException(
          pht(
            'Paste "%s" does not exist, or you do not have access '.
            'to see it.'));
      }

      echo $paste_ref->getContent();

      return 0;
    }

    $content = $this->readStdin();

    $xactions = array();

    if ($set_title === null) {
      $set_title = pht('Command-Line Input');
    }

    $xactions[] = array(
      'type' => 'title',
      'value' => $set_title,
    );

    if ($set_language !== null) {
      $xactions[] = array(
        'type' => 'language',
        'value' => $set_language,
      );
    }

    $xactions[] = array(
      'type' => 'text',
      'value' => $content,
    );

    $method = 'paste.edit';

    $parameters = array(
      'transactions' => $xactions,
    );

    $conduit_engine = $this->getConduitEngine();
    $conduit_call = $conduit_engine->newCall($method, $parameters);
    $conduit_future = $conduit_engine->newFuture($conduit_call);
    $result = $conduit_future->resolve();

    $paste_phid = idxv($result, array('object', 'phid'));
    $paste_ref = $symbols->loadPasteForSymbol($paste_phid);

    $log = $this->getLogEngine();

    $log->writeSuccess(
      pht('DONE'),
      pht('Created a new paste.'));

    echo tsprintf(
      '%s',
      $paste_ref->newDisplayRef()
        ->setURI($paste_ref->getURI()));

    return 0;
  }

}
