<?php
/**
 * Applies changes from one or more Differentials to the current working copy.
 */
final class ArcanistBulkPatchWorkflow extends ArcanistDiffBasedWorkflow {
  // Dependencies
  private $console;
  private $uberRefProvider;

  // Params
  private $diffIds = array();
  private $baseDiffIds = array();
  private $allDiffIds = array();
  private $tmpDir;
  private $forceStagingGitDiffs = false;

  // State
  private $gitBasedPatches = array();
  private $bundles = array();

  public function __construct() {
    $this->console = PhutilConsole::getConsole();
  }

  public function getWorkflowName() {
    return 'bulkpatch';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **patch** __--basediffs__ __diff_ids__
      **patch** __--diffs__ __diff_ids__
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          Supports: git
          Apply the changes in a list of diffs to the current working copy.
          Optionally support 'base' diffs, applied in a single commit, for CI
          and other situations where correct commit history is not important.
          By default, we try to apply the 'git diff' created from arcanist
          changes directly to the current working copy. If this does not
          successfully apply, we try to fetch the appropriate refs from the
          staging repository and generate a new git diff that can support a
          3-way merge.
EOTEXT
      );
  }

  public function getArguments() {
    return array(
      'diffs' => array(
        'param' => 'diffs',
        'help' => pht(
          'Apply changes from a list of comma separated Differential diffs '.
          'to the current working copy. This is similar to arc patch --nobranch '.
          'but supports multiple diffs in a single command and avoids costly '.
          'git checkout operations.'
        ),
      ),
      'basediffs' => array(
        'param' => 'diffs',
        'help' => pht(
          'Apply changes from a list of comma separated Differential diffs '.
          'as per --diffs, but combine each diff into a single commit. This '.
          'is designed for CI and other situations where only the last diff '.
          'is under test, but we are speculating on the outcome of other diffs '.
          'and need to set the repository up in a certain state.'),
      ),
      'force-staging-git-diffs' => array(
        'help' => pht(
         'Pre-emptively fetch base and diff refs from staging and generate '.
         'git-based diffs instead of first trying arc export generated git '.
         'diffs. This may increase overall patching duration.'),
      ),
      'tmp' => array(
        'param' => 'tmp',
        'help' => pht(
          'If git fallback is required, the path to create the temporary bare '.
          'clone. This must be on the same filesystem as the working directory. '.
          'Defaults to the parent of the current working directory.'
         ),
      ),
      '*' => 'name',
    );
  }

  protected function didParseArguments() {
    $diffParams = $this->getArgument('diffs');

    $diffs = array();
    if ($diffParams != null) {
      $diffs = explode(",", $diffParams);
    }

    $baseDiffParams = $this->getArgument('basediffs');

    $baseDiffs = array();
    if ($baseDiffParams != null) {
      $baseDiffs = explode(",", $baseDiffParams);
    }

    $this->diffIds = $diffs;
    $this->baseDiffIds = $baseDiffs;
    $this->allDiffIds = array_merge($diffs, $baseDiffs);

    if (count($this->allDiffIds) == 0) {
      throw new ArcanistUsageException("At least one diff id must be specified");
    }

    $this->tmpDir = $this->getArgument("tmp");
    if ($this->tmpDir == null) {
      $this->tmpDir = '..';
    }
    Filesystem::assertExists($this->tmpDir);

    $forceStagingGitDiffsArg = $this->getArgument("force-staging-git-diffs");
    if ($forceStagingGitDiffsArg) {
      $this->forceStagingGitDiffs = true;
    }
  }

  public function requiresConduit() {
    return true;
  }

  public function requiresRepositoryAPI() {
    return true;
  }

  public function requiresWorkingCopy() {
    return true;
  }

  public function run() {
    $this->uberRefProvider = new UberRefProvider(
      $this->getConfigurationManager()->getConfigFromAnySource('uber.arcanist.use_non_tag_refs', false)
    );
    $repository_api = $this->getRepositoryAPI();
    if (!$repository_api instanceof ArcanistGitAPI) {
      $message = pht(
        "arc bulkpatch only works for git repositories");
      throw new ArcanistUsageException($message);
    }

    // Eagerly load diffs to check valid args passed
    // TOOD: Use futures to fetch in parallel
    $this->authenticateConduit();
    foreach ($this->allDiffIds as $diffId) {
      $bundle = $this->loadDiffBundleFromConduit(
        $this->getConduit(),
        $diffId);
      $this->writeOkay(pht("LOADED"), pht("Loaded bundle for diff %s.", $diffId));
      $this->bundles[$diffId] = $bundle;
    }

    // If set, fetch all refs from staging repo and generate git diffs to use
    // for patching, instead of first trying 'arc export --git' diff
    if ($this->forceStagingGitDiffs) {
      $this->gitBasedPatches = $this->generateGitDiffPatches();
      $this->writeOkay(pht("OK"), pht("Pre-emptively fetched from git staging."));
    }

    // Apply all base diffs. Base diffs are combined into a single commit, designed for
    // use in CI and other systems where commit history is not important.
    if (!empty($this->baseDiffIds)) {
      foreach ($this->baseDiffIds as $diffId) {
        $err = $this->applyDiffWithFallback($diffId);
        if ($err) {
          return $err;
        }
        $this->writeOkay(
          pht('APPLIED'),
          pht('Successfully applied diff %s.', $diffId));
      }

      $repository_api->execPassthru('submodule update --init --recursive');

      $baseDiffs = implode(" ", $this->baseDiffIds);
      $commit_message = sprintf('Applied diffs: %s', $baseDiffs);
      $future = $repository_api->execFutureLocal(
        'commit --allow-empty -a -F -');
      $future->write($commit_message);
      $future->resolvex();

      $this->writeOkay(
          pht('COMMITTED'),
          pht('Successfully committed %s.', $baseDiffs));
    }

    // Apply regular diffs. These are diffs committed individually with the correct
    // commit message and author, as per a single 'arc patch --nobranch' commit.
    if (!empty($this->diffIds)) {
      foreach ($this->diffIds as $diffId) {
        $err = $this->applyDiffWithFallback($diffId);
        if ($err) {
          return $err;
        }

        $this->writeOkay(
          pht('APPLIED'),
          pht('Successfully applied diff %s.', $diffId));

        $repository_api->execPassthru('submodule update --init --recursive');

        $bundle = $this->bundles[$diffId];

        $flags = array();
        if ($bundle->getFullAuthor()) {
          $flags[] = csprintf('--author=%s', $bundle->getFullAuthor());
        }

        $commit_message = $this->getCommitMessage($bundle);

        $future = $repository_api->execFutureLocal(
          'commit -a %Ls -F - --no-verify',
          $flags);
        $future->write($commit_message);

        $future->resolvex();

        $this->writeOkay(
            pht('COMMITTED'),
            pht('Successfully committed diff %s.', $diffId));
      }
    }

    return 0;
  }

  private function generateGitDiffPatches() {
    $repository_api = $this->getRepositoryAPI();
    list($success, $message, $staging, $staging_uri) = $this->validateStagingSetup();
    if (!$success) {
      throw new ArcanistUsageException($message);
    }
    $prefix = idx($staging, 'prefix', 'phabricator');

    $refSpec = "";
    foreach ($this->allDiffIds as $id) {
      $baseRef = $this->uberRefProvider->getBaseRefName($prefix, $id);
      $diffRef = $this->uberRefProvider->getDiffRefName($prefix, $id);
      $refSpec = $refSpec . sprintf("+%s:%s +%s:%s ", $baseRef, $baseRef, $diffRef, $diffRef);
    }

    $repoPath = Filesystem::createTemporaryDirectory('.tmpgit', 0700, $this->tmpDir);
    $diffPatchMap = array();
    try {
      // Clone local repo bare
      $err = $repository_api->execPassthru('clone --bare --local . %s', $repoPath);
      if ($err) {
        throw new Exception(pht("Failed to create local clone with error code: %s", $err));
      }

      // Fetch refSpec
      $err = $repository_api->execPassthru('-C %s fetch --depth=1 --no-tags %s ' . $refSpec, $repoPath, $staging_uri);
      if ($err) {
        throw new Exception(pht("Failed to fetch from staging into local clone with error code: %s", $err));
      }

      // Run git diff repeatedly
      foreach ($this->allDiffIds as $id) {
        $patchfile = new TempFile();
        $baseRef = $this->uberRefProvider->getBaseRefName($prefix, $id);
        $diffRef = $this->uberRefProvider->getDiffRefName($prefix, $id);
        $err = $repository_api->execPassthru('-C %s diff --binary %s %s > %s', $repoPath, $baseRef, $diffRef, $patchfile);
        if ($err) {
          throw new Exception(pht("Failed to generate git patch for diff %s with error code: %s", $id, $err));
        }
        $this->writeOkay("SUCCESS", pht('Generated patch for diff %s.', $id));
        $diffPatchMap[$id] = $patchfile;
      }
    }
    finally {
      try {
        Filesystem::remove($repoPath);
      } catch (Exception $ex) {
        $this->writeWarn(pht('WARNING'), pht('Failed to cleanup temporary git directory: %s', $ex));
      } catch (Throwable $ex) {
        $this->writeWarn(pht('WARNING'), pht('Failed to cleanup temporary git directory: %s', $ex));
      }
    }

    return $diffPatchMap;
  }

  private function applyDiffWithFallback($diffId) {
    if (empty($this->gitBasedPatches)) {
      $bundle = $this->bundles[$diffId];
      // Check if all patches apply
      $err = $this->applyArcBasedPatch($bundle);
      if ($err) {
        $this->writeWarn(pht('PATCH FAILURE'),
            pht('Unable to apply arc generated patch - falling back to fetching from git staging area.'));
        $this->gitBasedPatches = $this->generateGitDiffPatches();
        return $this->applyGitBasedPatch($diffId);
      }
    } else {
      return $this->applyGitBasedPatch($diffId);
    }
  }


  private function applyArcBasedPatch(ArcanistBundle $bundle) {
    $patchfile = new TempFile();
    Filesystem::writeFile($patchfile, $bundle->toGitPatch());
    $passthru = new PhutilExecPassthru(
      'git apply --whitespace nowarn --index --verbose -- %s',
      $patchfile);
    $passthru->setCWD($this->getRepositoryAPI()->getPath());
    return $passthru->execute();
  }

  private function applyGitBasedPatch($diffId) {
    $patchfile = $this->gitBasedPatches[$diffId];
    if ($patchfile == null) {
      $this->console->writeOut(
        "<bg:red>** %s **</bg> %s\n",
        pht('PATCH FAILURE'),
        pht('Unable to find Git generated fallback patch.', $diffId));
      return 1;
    }

    $passthru = new PhutilExecPassthru(
      'git apply --3way --whitespace nowarn --index --verbose -- %s',
      $patchfile);
    $passthru->setCWD($this->getRepositoryAPI()->getPath());
    $err = $passthru->execute();

    if ($err) {
      $this->console->writeOut(
        "<bg:red>** %s **</bg> %s\n",
        pht('PATCH FAILURE'),
        pht('Unable to apply git generated patch - %s does not apply to current working state.', $diffId));
    }
    return $err;
  }

  // See ArcanistPatchWorkflow.php
  private function getCommitMessage(ArcanistBundle $bundle) {
    $revision_id    = $bundle->getRevisionID();
    $commit_message = null;

    if ($revision_id) {
      $this->authenticateConduit(); // already checks if authenticated
      $conduit        = $this->getConduit();
      $commit_message = $conduit->callMethodSynchronous(
          'differential.getcommitmessage',
          array(
            'revision_id' => $revision_id,
          ));
    } else {
      throw new Exception(pht("Failed to fetch commit message: bundle has no revision_id"));
    }

    if (!$commit_message) {
      throw new Exception(pht("Failed to fetch commit message."));
    }

    return $commit_message;
  }
}
