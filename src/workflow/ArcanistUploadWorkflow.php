<?php

final class ArcanistUploadWorkflow
  extends ArcanistArcWorkflow {

  public function getWorkflowName() {
    return 'upload';
  }

  public function getWorkflowInformation() {
    $help = pht(<<<EOTEXT
Upload one or more files from local disk to Phabricator.
EOTEXT
);

    return $this->newWorkflowInformation()
      ->setSynopsis(pht('Upload files.'))
      ->addExample(pht('**upload** [__options__] -- __file__ [__file__ ...]'))
      ->setHelp($help);
  }

  public function getWorkflowArguments() {
    return array(
      $this->newWorkflowArgument('json')
        ->setHelp(pht('Output upload information in JSON format.')),
      $this->newWorkflowArgument('browse')
        ->setHelp(
          pht(
            'After the upload completes, open the files in a web browser.')),
      $this->newWorkflowArgument('temporary')
        ->setHelp(
          pht(
            'Mark the file as temporary. Temporary files will be '.
            'deleted after 24 hours.')),
      $this->newWorkflowArgument('paths')
        ->setWildcard(true)
        ->setIsPathArgument(true),
    );
  }

  public function runWorkflow() {
    if (!$this->getArgument('paths')) {
      throw new PhutilArgumentUsageException(
        pht('Specify one or more paths to files you want to upload.'));
    }

    $is_temporary = $this->getArgument('temporary');
    $is_json = $this->getArgument('json');
    $is_browse = $this->getArgument('browse');
    $paths = $this->getArgument('paths');

    $conduit = $this->getConduitEngine();
    $results = array();

    $uploader = id(new ArcanistFileUploader())
      ->setConduitEngine($conduit);

    foreach ($paths as $key => $path) {
      $file = id(new ArcanistFileDataRef())
        ->setName(basename($path))
        ->setPath($path);

      if ($is_temporary) {
        $expires_at = time() + phutil_units('24 hours in seconds');
        $file->setDeleteAfterEpoch($expires_at);
      }

      $uploader->addFile($file);
    }

    $files = $uploader->uploadFiles();

    $phids = array();
    foreach ($files as $file) {
      // TODO: This could be handled more gracefully.
      if ($file->getErrors()) {
        throw new Exception(implode("\n", $file->getErrors()));
      }
      $phids[] = $file->getPHID();
    }

    $symbols = $this->getSymbolEngine();
    $symbol_refs = $symbols->loadFilesForSymbols($phids);

    $refs = array();
    foreach ($symbol_refs as $symbol_ref) {
      $ref = $symbol_ref->getObject();
      if ($ref === null) {
        throw new Exception(
          pht(
            'Failed to resolve symbol ref "%s".',
            $symbol_ref->getSymbol()));
      }
      $refs[] = $ref;
    }

    if ($is_json) {
      $json = array();

      foreach ($refs as $key => $ref) {
        $uri = $ref->getURI();
        $uri = $this->getAbsoluteURI($uri);

        $map = array(
          'argument' => $paths[$key],
          'id' => $ref->getID(),
          'phid' => $ref->getPHID(),
          'name' => $ref->getName(),
          'uri' => $uri,
        );

        $json[] = $map;
      }

      echo id(new PhutilJSON())->encodeAsList($json);
    } else {
      foreach ($refs as $ref) {
        $uri = $ref->getURI();
        $uri = $this->getAbsoluteURI($uri);
        echo tsprintf(
          '%s',
          $ref->newRefView()
            ->setURI($uri));
      }
    }

    if ($is_browse) {
      $uris = array();
      foreach ($refs as $ref) {
        $uri = $ref->getURI();
        $uri = $this->getAbsoluteURI($uri);
        $uris[] = $uri;
      }
      $this->openURIsInBrowser($uris);
    }

    return 0;
  }

  private function writeStatus($line) {
    $this->writeStatusMessage($line."\n");
  }

}
