<?php

final class ArcanistPasteWorkflow
  extends ArcanistArcWorkflow {

  public function getWorkflowName() {
    return 'paste';
  }

  public function getWorkflowInformation() {
    $help = pht(<<<EOTEXT
Share and grab text using the Paste application. To create a paste, use the
"--input" flag or provide the text on stdin:

  $ cat list_of_ducks.txt | arc paste --
  $ arc paste --input list_of_ducks.txt

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
      $this->newWorkflowArgument('input')
        ->setParameter('path')
        ->setIsPathArgument(true)
        ->setHelp(pht('Create a paste using the content in a file.')),
      $this->newWorkflowArgument('browse')
        ->setHelp(pht('After creating a paste, open it in a web browser.')),
      $this->newWorkflowArgument('argv')
        ->setWildcard(true),
    );
  }

  public function runWorkflow() {
    $set_language = $this->getArgument('lang');
    $set_title = $this->getArgument('title');
    $is_browse = $this->getArgument('browse');
    $input_path = $this->getArgument('input');

    $argv = $this->getArgument('argv');
    if (count($argv) > 1) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify only one paste to retrieve.'));
    }

    $is_read = (count($argv) === 1);

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

      if ($is_browse) {
        throw new PhutilArgumentUsageException(
          pht(
            'Flag "--browse" is not supported when reading pastes. Use '.
            '"arc browse" to browse known objects.'));
      }

      if ($input_path !== null) {
        throw new PhutilArgumentUsageException(
          pht(
            'Flag "--input" is not supported when reading pastes.'));
      }

      $paste_symbol = $argv[0];

      $paste_ref = $symbols->loadPasteForSymbol($paste_symbol);
      if (!$paste_ref) {
        throw new PhutilArgumentUsageException(
          pht(
            'Paste "%s" does not exist, or you do not have access '.
            'to see it.',
            $paste_symbol));
      }

      echo $paste_ref->getContent();

      return 0;
    }

    if ($input_path === null || $input_path === '-') {
      $content = $this->readStdin();
    } else {
      $content = Filesystem::readFile($input_path);
    }

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
    $conduit_future = $conduit_engine->newFuture($method, $parameters);
    $result = $conduit_future->resolve();

    $paste_phid = idxv($result, array('object', 'phid'));
    $paste_ref = $symbols->loadPasteForSymbol($paste_phid);

    $uri = $paste_ref->getURI();
    $uri = $this->getAbsoluteURI($uri);

    $log = $this->getLogEngine();

    $log->writeSuccess(
      pht('DONE'),
      pht('Created a new paste.'));

    echo tsprintf(
      '%s',
      $paste_ref->newRefView()
        ->setURI($uri));

    if ($is_browse) {
      $this->openURIsInBrowser(array($uri));
    }

    return 0;
  }

}
