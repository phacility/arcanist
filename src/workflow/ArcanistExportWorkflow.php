<?php

/**
 * Exports changes from Differential or the working copy to a file.
 */
final class ArcanistExportWorkflow extends ArcanistWorkflow {

  const SOURCE_LOCAL      = 'local';
  const SOURCE_DIFF       = 'diff';
  const SOURCE_REVISION   = 'revision';

  const FORMAT_GIT        = 'git';
  const FORMAT_UNIFIED    = 'unified';
  const FORMAT_BUNDLE     = 'arcbundle';

  private $source;
  private $sourceID;
  private $format;

  public function getWorkflowName() {
    return 'export';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **export** [__paths__] __format__ (svn)
      **export** [__commit_range__] __format__ (git, hg)
      **export** __--revision__ __revision_id__ __format__
      **export** __--diff__ __diff_id__ __format__
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          Supports: svn, git, hg
          Export the local changeset (or a Differential changeset) to a file,
          in some __format__: git diff (__--git__), unified diff
          (__--unified__), or arc bundle (__--arcbundle__ __path__) format.
EOTEXT
      );
  }

  public function getArguments() {
    return array(
      'git' => array(
        'help' => pht(
          "Export change as a git patch. This format is more complete than ".
          "unified, but less complete than arc bundles. These patches can be ".
          "applied with '%s' or '%s'.",
          'git apply',
          'arc patch'),
      ),
      'unified' => array(
        'help' => pht(
          "Export change as a unified patch. This format is less complete ".
          "than git patches or arc bundles. These patches can be applied with ".
          "'%s' or '%s'.",
          'patch',
          'arc patch'),
      ),
      'arcbundle' => array(
        'param' => 'file',
        'help' => pht(
          "Export change as an arc bundle. This format can represent all ".
          "changes. These bundles can be applied with '%s'.",
          'arc patch'),
      ),
      'encoding' => array(
        'param' => 'encoding',
        'help' => pht(
          'Attempt to convert non UTF-8 patch into specified encoding.'),
      ),
      'revision' => array(
        'param' => 'revision_id',
        'help' => pht(
          'Instead of exporting changes from the working copy, export them '.
          'from a Differential revision.'),
      ),
      'diff' => array(
        'param' => 'diff_id',
        'help' => pht(
          'Instead of exporting changes from the working copy, export them '.
          'from a Differential diff.'),
      ),
      '*' => 'paths',
    );
  }

  protected function didParseArguments() {
    $source = self::SOURCE_LOCAL;
    $requested = 0;
    if ($this->getArgument('revision')) {
      $source = self::SOURCE_REVISION;
      $requested++;

      $source_id = $this->getArgument($source);
      $this->sourceID = $this->normalizeRevisionID($source_id);
    }
    if ($this->getArgument('diff')) {
      $source = self::SOURCE_DIFF;
      $requested++;

      $this->sourceID = $this->getArgument($source);
    }
    $this->source = $source;

    if ($requested > 1) {
      throw new ArcanistUsageException(
        pht(
          "Options '%s' and '%s' are not compatible. Choose exactly ".
          "one change source.",
          '--revision',
          '--diff'));
    }

    $format = null;
    $requested = 0;
    if ($this->getArgument('git')) {
      $format = self::FORMAT_GIT;
      $requested++;
    }
    if ($this->getArgument('unified')) {
      $format = self::FORMAT_UNIFIED;
      $requested++;
    }
    if ($this->getArgument('arcbundle')) {
      $format = self::FORMAT_BUNDLE;
      $requested++;
    }

    if ($requested === 0) {
      throw new ArcanistUsageException(
        pht(
          "Specify one of '%s', '%s' or '%s' to choose an export format.",
          '--git',
          '--unified',
          '--arcbundle <path>'));
    } else if ($requested > 1) {
      throw new ArcanistUsageException(
        pht(
          "Options '%s', '%s' and '%s' are not compatible. ".
          "Choose exactly one export format.",
          '--git',
          '--unified',
          '--arcbundle'));
    }

    $this->format = $format;
  }

  public function requiresConduit() {
    return true;
  }

  public function requiresAuthentication() {
    return $this->requiresConduit();
  }

  public function requiresRepositoryAPI() {
    return $this->getSource() == self::SOURCE_LOCAL;
  }

  public function requiresWorkingCopy() {
    return $this->getSource() == self::SOURCE_LOCAL;
  }

  private function getSource() {
    return $this->source;
  }

  private function getSourceID() {
    return $this->sourceID;
  }

  private function getFormat() {
    return $this->format;
  }

  public function run() {
    $source = $this->getSource();

    switch ($source) {
      case self::SOURCE_LOCAL:
        $repository_api = $this->getRepositoryAPI();
        $parser = new ArcanistDiffParser();
        $parser->setRepositoryAPI($repository_api);

        if ($repository_api instanceof ArcanistGitAPI) {
          $this->parseBaseCommitArgument($this->getArgument('paths'));
          $diff = $repository_api->getFullGitDiff(
            $repository_api->getBaseCommit(),
            $repository_api->getHeadCommit());
          $changes = $parser->parseDiff($diff);
          $authors = $this->getConduit()->callMethodSynchronous(
            'user.query',
            array(
              'phids' => array($this->getUserPHID()),
            ));
          $author_dict = reset($authors);

          list($email) = $repository_api->execxLocal('config user.email');

          $author = sprintf('%s <%s>',
            $author_dict['realName'],
            $email);
        } else if ($repository_api instanceof ArcanistMercurialAPI) {
          $this->parseBaseCommitArgument($this->getArgument('paths'));
          $diff = $repository_api->getFullMercurialDiff();
          $changes = $parser->parseDiff($diff);
          $authors = $this->getConduit()->callMethodSynchronous(
            'user.query',
            array(
              'phids' => array($this->getUserPHID()),
            ));

          list($author) = $repository_api->execxLocal('showconfig ui.username');
        } else {
          // TODO: paths support
          $paths = $repository_api->getWorkingCopyStatus();
          $changes = $parser->parseSubversionDiff(
            $repository_api,
            $paths);
          $author = $this->getUserName();
        }

        $bundle = ArcanistBundle::newFromChanges($changes);
        $bundle->setBaseRevision(
          $repository_api->getSourceControlBaseRevision());
        // NOTE: we can't get a revision ID for SOURCE_LOCAL

        $parser = new PhutilEmailAddress($author);
        $bundle->setAuthorName($parser->getDisplayName());
        $bundle->setAuthorEmail($parser->getAddress());
        break;
      case self::SOURCE_REVISION:
        $bundle = $this->loadRevisionBundleFromConduit(
          $this->getConduit(),
          $this->getSourceID());
        break;
      case self::SOURCE_DIFF:
        $bundle = $this->loadDiffBundleFromConduit(
          $this->getConduit(),
          $this->getSourceID());
        break;
    }

    $try_encoding = nonempty($this->getArgument('encoding'), null);

    if ($try_encoding) {
      $bundle->setEncoding($try_encoding);
    }

    $format = $this->getFormat();

    switch ($format) {
      case self::FORMAT_GIT:
        echo $bundle->toGitPatch();
        break;
      case self::FORMAT_UNIFIED:
        echo $bundle->toUnifiedDiff();
        break;
      case self::FORMAT_BUNDLE:
        $path = $this->getArgument('arcbundle');
        echo pht("Writing bundle to '%s'...", $path)."\n";
        $bundle->writeToDisk($path);
        echo pht('Done.')."\n";
        break;
    }

    return 0;
  }

}
