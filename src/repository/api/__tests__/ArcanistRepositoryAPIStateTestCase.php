<?php

final class ArcanistRepositoryAPIStateTestCase extends ArcanistTestCase {

  public function testStateParsing() {
    $dir = dirname(__FILE__).'/state/';

    $tests = Filesystem::listDirectory($dir, $include_hidden = false);
    foreach ($tests as $test) {
      $fixture = PhutilDirectoryFixture::newFromArchive($dir.'/'.$test);

      $fixture_path = $fixture->getPath();
      $working_copy = ArcanistWorkingCopyIdentity::newFromPath($fixture_path);

      $api = ArcanistRepositoryAPI::newAPIFromWorkingCopyIdentity(
        $working_copy);

      if ($api->supportsRelativeLocalCommits()) {
        $api->setDefaultBaseCommit();
      }

      $this->assertCorrectState($test, $api);
    }
  }

  private function assertCorrectState($test, ArcanistRepositoryAPI $api) {
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
        $expect = array(
          'ADDED'       => $f_add,
          'COPIED_TO'   => $f_add,
          'DELETED'     => $f_del,
          'MODIFIED'    => $f_mod,
          'MOVED'       => $f_del,
          'MOVED_TO'    => $f_add,
          'PROPCHANGE'  => $f_mod,
          'UNTRACKED'   => $f_unt,
        );
        $this->assertEqual($expect, $api->getUncommittedStatus());
        $this->assertEqual($expect, $api->getCommitRangeStatus());
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
          'UNCOMMITTED' => $f_add,
          'UNSTAGED'    => $f_add,
        );
        $this->assertEqual($expect_range, $api->getCommitRangeStatus());
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
        break;
      default:
        throw new Exception(
          "No test cases for working copy '{$test}'!");
    }
  }


}
