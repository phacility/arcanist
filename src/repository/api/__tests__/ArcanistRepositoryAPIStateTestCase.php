<?php

final class ArcanistRepositoryAPIStateTestCase extends PhutilTestCase {

  public function testGitStateParsing() {
    if (Filesystem::binaryExists('git')) {
      $this->parseState('git_basic.git.tgz');
      $this->parseState('git_submodules_dirty.git.tgz');
      $this->parseState('git_submodules_staged.git.tgz');
      $this->parseState('git_spaces.git.tgz');
    } else {
      $this->assertSkipped(pht('Git is not installed'));
    }
  }

  public function testHgStateParsing() {
    if (Filesystem::binaryExists('hg')) {
      $this->parseState('hg_basic.hg.tgz');
    } else {
      $this->assertSkipped(pht('Mercurial is not installed'));
    }
  }

  public function testSvnStateParsing() {
    if (Filesystem::binaryExists('svn')) {
      $this->parseState('svn_basic.svn.tgz');
    } else {
      $this->assertSkipped(pht('Subversion is not installed'));
    }
  }

  private function parseState($test) {
    $dir = dirname(__FILE__).'/state/';
    $fixture = PhutilDirectoryFixture::newFromArchive($dir.'/'.$test);

    $fixture_path = $fixture->getPath();
    $working_copy = ArcanistWorkingCopyIdentity::newFromPath($fixture_path);
    $configuration_manager = new ArcanistConfigurationManager();
    $configuration_manager->setWorkingCopyIdentity($working_copy);
    $api = ArcanistRepositoryAPI::newAPIFromConfigurationManager(
      $configuration_manager);

    $api->setBaseCommitArgumentRules('arc:this');

    if ($api instanceof ArcanistSubversionAPI) {
      // Upgrade the repository so that the test will still pass if the local
      // `svn` is newer than the `svn` which created the repository.

      // NOTE: Some versions of Subversion (1.7.x?) exit with an error code on
      // a no-op upgrade, although newer versions do not. We just ignore the
      // error here; if it's because of an actual problem we'll hit an error
      // shortly anyway.
      $api->execManualLocal('upgrade');
    }

    $this->assertCorrectState($test, $api);
  }

  private function assertCorrectState($test, ArcanistRepositoryAPI $api) {
    if ($api instanceof ArcanistGitAPI) {
      $version = $api->getGitVersion();
      if (version_compare($version, '2.11.0', '<')) {
        // Behavior differs slightly on older versions of git; rather than code
        // both variants, skip the tests in the presence of such a git.
        $this->assertSkipped(pht('Behavior differs slightly on git < 2.11.0'));
        return;
      }
    }

    $f_mod = ArcanistRepositoryAPI::FLAG_MODIFIED;
    $f_add = ArcanistRepositoryAPI::FLAG_ADDED;
    $f_del = ArcanistRepositoryAPI::FLAG_DELETED;
    $f_unt = ArcanistRepositoryAPI::FLAG_UNTRACKED;
    $f_con = ArcanistRepositoryAPI::FLAG_CONFLICT;
    $f_mis = ArcanistRepositoryAPI::FLAG_MISSING;
    $f_uns = ArcanistRepositoryAPI::FLAG_UNSTAGED;
    $f_unc = ArcanistRepositoryAPI::FLAG_UNCOMMITTED;
    $f_ext = ArcanistRepositoryAPI::FLAG_EXTERNALS;
    $f_obs = ArcanistRepositoryAPI::FLAG_OBSTRUCTED;
    $f_inc = ArcanistRepositoryAPI::FLAG_INCOMPLETE;

    switch ($test) {
      case 'svn_basic.svn.tgz':
        $expect_uncommitted = array(
          'ADDED'       => $f_add,
          'COPIED_TO'   => $f_add,
          'DELETED'     => $f_del,
          'MODIFIED'    => $f_mod,
          'MOVED'       => $f_del,
          'MOVED_TO'    => $f_add,
          'PROPCHANGE'  => $f_mod,
          'UNTRACKED'   => $f_unt,
        );
        $this->assertEqual($expect_uncommitted, $api->getUncommittedStatus());

        $expect_range = array();
        $this->assertEqual($expect_range, $api->getCommitRangeStatus());

        $expect_working = array(
          'ADDED'       => $f_add,
          'COPIED_TO'   => $f_add,
          'DELETED'     => $f_del,
          'MODIFIED'    => $f_mod,
          'MOVED'       => $f_del,
          'MOVED_TO'    => $f_add,
          'PROPCHANGE'  => $f_mod,
          'UNTRACKED'   => $f_unt,
        );
        $this->assertEqual($expect_working, $api->getWorkingCopyStatus());
        break;
      case 'git_basic.git.tgz':
        $expect_uncommitted = array(
          'UNCOMMITTED' => $f_add | $f_unc,
          'UNSTAGED'    => $f_mod | $f_uns | $f_unc,
          'UNTRACKED'   => $f_unt,
        );

        $this->assertEqual($expect_uncommitted, $api->getUncommittedStatus());

        $expect_range = array(
          'ADDED'       => $f_add,
          'DELETED'     => $f_del,
          'MODIFIED'    => $f_mod,
          'UNSTAGED'    => $f_add,
        );
        $this->assertEqual($expect_range, $api->getCommitRangeStatus());

        $expect_working = array(
          'ADDED'       => $f_add,
          'DELETED'     => $f_del,
          'MODIFIED'    => $f_mod,
          'UNCOMMITTED' => $f_add | $f_unc,
          'UNSTAGED'    => $f_add | $f_mod | $f_uns | $f_unc,
          'UNTRACKED'   => $f_unt,
        );
        $this->assertEqual($expect_working, $api->getWorkingCopyStatus());
        break;
      case 'git_submodules_dirty.git.tgz':
        $expect_uncommitted = array(
          '.gitmodules'           => $f_mod | $f_uns | $f_unc,
          'added/'                => $f_unt,
          'deleted'               => $f_del | $f_uns | $f_unc,
          'modified-commit'       => $f_mod | $f_uns | $f_unc,
          'modified-commit-dirty' => $f_ext | $f_mod | $f_uns | $f_unc,
          'modified-dirty'        => $f_ext | $f_mod | $f_uns | $f_unc,
        );
        $this->assertEqual($expect_uncommitted, $api->getUncommittedStatus());
        break;
      case 'git_submodules_staged.git.tgz':
        $expect_uncommitted = array(
          '.gitmodules'           => $f_mod | $f_unc,
          'added'                 => $f_add | $f_unc,
          'deleted'               => $f_del | $f_unc,
          'modified-commit'       => $f_mod | $f_unc,
          'modified-commit-dirty' => $f_ext | $f_mod | $f_uns | $f_unc,
          'modified-dirty'        => $f_ext | $f_mod | $f_uns | $f_unc,
        );
        $this->assertEqual($expect_uncommitted, $api->getUncommittedStatus());
        break;
      case 'git_spaces.git.tgz':
        $expect_working = array(
          'SPACES ADDED'       => $f_add,
          'SPACES DELETED'     => $f_del,
          'SPACES MODIFIED'    => $f_mod,
          'SPACES UNCOMMITTED' => $f_add | $f_unc,
          'SPACES UNSTAGED'    => $f_add | $f_mod | $f_uns | $f_unc,
          'SPACES UNTRACKED'   => $f_unt,
        );
        $this->assertEqual($expect_working, $api->getWorkingCopyStatus());
        break;
      case 'hg_basic.hg.tgz':
        $expect_uncommitted = array(
          'UNCOMMITTED' => $f_mod | $f_unc,
          'UNTRACKED'   => $f_unt,
        );
        $this->assertEqual($expect_uncommitted, $api->getUncommittedStatus());

        $expect_range = array(
          'ADDED'       => $f_add,
          'DELETED'     => $f_del,
          'MODIFIED'    => $f_mod,
          'UNCOMMITTED' => $f_add,
        );
        $this->assertEqual($expect_range, $api->getCommitRangeStatus());

        $expect_working = array(
          'ADDED'       => $f_add,
          'DELETED'     => $f_del,
          'MODIFIED'    => $f_mod,
          'UNCOMMITTED' => $f_add | $f_mod | $f_unc,
          'UNTRACKED'   => $f_unt,
        );
        $this->assertEqual($expect_working, $api->getWorkingCopyStatus());
        break;
      default:
        throw new Exception(
          pht("No test cases for working copy '%s'!", $test));
    }
  }

}
