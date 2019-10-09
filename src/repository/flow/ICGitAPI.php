<?php

final class ICGitAPI extends Phobject {

  protected $api;

  public function __construct(ArcanistGitAPI $api) {
    $this->api = $api;
  }

  public function getAPI() {
    return $this->api;
  }

  public function getParentSha($sha) {
    list($err, $stdout) = $this->api->execManualLocal("rev-parse --verify $sha^");
    if ($err) {
      return ArcanistGitAPI::GIT_MAGIC_ROOT_COMMIT;
    }
    return rtrim($stdout);
  }

  public function forEachRef(array $fields, $pattern) {
    $column_id = uniqid();
    $row_id = uniqid();
    $format = '%('.implode(")%01{$column_id}%01%(", $fields).")%01{$row_id}%01";
    list($stdout, $stderr) = $this->api->execxLocal(
      'for-each-ref --format=%s %s',
      $format,
      $pattern);
    $results = [];
    $delimiter = "\1{$column_id}\1";
    $break = "\1$row_id\1";
    $lines = explode($break, $stdout);
    array_pop($lines);
    foreach ($lines as $index => $line) {
      if ($index) {
        $line = substr($line, 1);
      }
      $field_values = explode($delimiter, $line, count($fields));
      $result = [];
      foreach ($fields as $field_index => $field_name) {
        $result[$field_name] = $field_values[$field_index];
      }
      $results[] = $result;
    }
    return $results;
  }

  public function diff() {
    return $this->api->getFullGitDiff($this->api->getHeadCommit());
  }

  public function apply($diff) {
    if (!$diff) {
      return ['', ''];
    }
    $future = $this->api->execFutureLocal('apply');
    $future->write($diff);
    return $future->resolvex();
  }

  public function createSnapshot($name) {
    $fixture = PhutilDirectoryFixture::newEmptyFixture();
    execx(
      'tar -C %s -cf %s .',
      $this->api->getPath('.git'),
      $fixture->getPath('data'));
    Filesystem::writeFile($fixture->getPath('diff'), $this->diff());
    $archive = (new TempFile($name))->setPreserveFile(true);
    $fixture->saveToArchive($archive);
    return $archive;
  }

  public function loadSnapshot($path) {
    $metadata_path = $this->api->getPath('.git');
    Filesystem::remove($metadata_path);
    Filesystem::createDirectory($metadata_path);
    $fixture = PhutilDirectoryFixture::newFromArchive($path);
    execx(
      'tar -xf %s -C %s',
      $fixture->getPath('data'),
      $metadata_path);
    $this->api->execxLocal('reset --hard');
    $this->apply(Filesystem::readFile($fixture->getPath('diff')));
  }

  // --( Parsing `git status --porcelain` )------------------------------------

  public function getAllEditsAndFiles() {
    return $this->parseStatus(array("C","R","U","M","A","D","?")); // like CRU-mad, bro?
  }

  public function getNewFiles() {
    return $this->parseStatus(array("A", "D", "?"));
  }

  public function getNewEdits() {
    return $this->parseStatus(array("U", "R", "M"));
  }

  public function parseStatus($codes) {
    // Info about codes: https://git-scm.com/docs/git-status#_short_format
    $code_end = 2;
    $filename_start = 3;

    list($err, $stdout, $stderr) = $this->api->execManualLocal("status --porcelain");

    $lines = explode("\n", $stdout);
    $files = array();
    foreach ($lines as $line) {
      $status = substr($line, 0, $code_end);
      if ($status[0] === "D") {
        // Special case for when file is removed via `git rm`:
        // The file is already staged, attempting to do so again will crash
        continue;
      }
      foreach($codes as $code) {
        if (strpos($status, $code) !== false) {
          array_push($files, substr($line, $filename_start));
          break;
        }
      }
    }
    return $files;
  }


  // --( Parsing `git log` )---------------------------------------------------

  public function doesRevisionExistInLog($rid) {
    list($err, $stdout) = $this->api->execManualLocal("log -E --grep '^Differential Revision:.*D{$rid}$'");
    return ($stdout !== "");
  }

  public function getCommitCount() {
    try {
      return count($this->api->getLocalCommitInformation());
    } catch (Exception $e) {
      // HEAD does not point to a valid commit
      return 0;
    }
  }

  // --( Branch manipulation )-------------------------------------------------

  public function checkoutBranch($name) {
    // Regular checkout
    list($err, $stdout, $stderr) = $this->api->execManualLocal("checkout %s", $name);
    echo $stdout;
    if ($err) {
      throw new ArcanistUsageException($stderr);
    }
  }

  public function createAndCheckoutBranchFromHead($name) {
    // Creates new branch tracking HEAD, checkout
    list($err, $stdout, $stderr) = $this->api->execManualLocal("checkout --track -b %s", $name);
    echo $stdout;
    if ($err) {
      throw new ArcanistUsageException($stderr);
    }
  }

  public function createAndCheckoutBranch($name, $upstream) {
    // Creates new branch tracking upstream, checkout
    $branches = $this->api->getAllBranches();

    if (in_array($name, ipull($branches, 'name'))) {
      // Branch already exists in tree
      throw new ArcanistUsageException(phutil_console_format(pht(
        "A branch with the name **'%s'** already exists in your tree.\n", $name)));
    } else if (!in_array($upstream, ipull($branches, 'name'))) {
      // Upstream doesn't exist in tree
      throw new ArcanistUsageException(phutil_console_format(pht(
        "Upstream branch **'%s'** does not exist.\n", $upstream)));
    }

    list($err, $stdout, $stderr) = $this->api->execManualLocal("checkout --track -b %s %s", $name, $upstream);
    echo $stdout;
    if ($err) {
      throw new ArcanistUsageException($stderr);
    }
  }

}
