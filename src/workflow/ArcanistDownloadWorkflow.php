<?php

/**
 * Download a file from Phabricator.
 */
final class ArcanistDownloadWorkflow extends ArcanistWorkflow {

  private $id;
  private $saveAs;
  private $show;

  public function getWorkflowName() {
    return 'download';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **download** __file__ [--as __name__] [--show]
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          Supports: filesystems
          Download a file to local disk, e.g.:

            $ arc download F33              # Download file 'F33'
EOTEXT
      );
  }

  public function getArguments() {
    return array(
      'show' => array(
        'conflicts' => array(
          'as' => pht(
            'Use %s to direct the file to stdout, or %s to direct '.
            'it to a named location.',
            '--show',
            '--as'),
        ),
        'help' => pht('Write file to stdout instead of to disk.'),
      ),
      'as' => array(
        'param' => 'name',
        'help' => pht(
          'Save the file with a specific name rather than the default.'),
      ),
      '*' => 'argv',
    );
  }

  protected function didParseArguments() {
    $argv = $this->getArgument('argv');
    if (!$argv) {
      throw new ArcanistUsageException(pht('Specify a file to download.'));
    }
    if (count($argv) > 1) {
      throw new ArcanistUsageException(
        pht('Specify exactly one file to download.'));
    }

    $file = reset($argv);
    if (!preg_match('/^F?\d+$/', $file)) {
      throw new ArcanistUsageException(
        pht('Specify file by ID, e.g. %s.', 'F123'));
    }

    $this->id = (int)ltrim($file, 'F');
    $this->saveAs = $this->getArgument('as');
    $this->show = $this->getArgument('show');
  }

  public function requiresAuthentication() {
    return true;
  }

  public function run() {
    $conduit = $this->getConduit();

    $this->writeStatusMessage(pht('Getting file information...')."\n");
    $info = $conduit->callMethodSynchronous(
      'file.info',
      array(
        'id' => $this->id,
      ));

    $desc = pht('(%s bytes)', new PhutilNumber($info['byteSize']));
    if ($info['name']) {
      $desc = "'".$info['name']."' ".$desc;
    }

    $this->writeStatusMessage(pht('Downloading file %s...', $desc)."\n");
    $data = $conduit->callMethodSynchronous(
      'file.download',
      array(
        'phid' => $info['phid'],
      ));

    $data = base64_decode($data);

    if ($this->show) {
      echo $data;
    } else {
      $path = Filesystem::writeUniqueFile(
        nonempty($this->saveAs, $info['name'], 'file'),
        $data);
      $this->writeStatusMessage(pht("Saved file as '%s'.", $path)."\n");
    }

    return 0;
  }

}
