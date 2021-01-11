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
    list($err, $stdout) = $this->api->execManualLocal(
      "rev-parse --verify $sha^");
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
    $results = array();
    $delimiter = "\1{$column_id}\1";
    $break = "\1$row_id\1";
    $lines = explode($break, $stdout);
    array_pop($lines);
    foreach ($lines as $index => $line) {
      if ($index) {
        $line = substr($line, 1);
      }
      $field_values = explode($delimiter, $line, count($fields));
      $result = array();
      foreach ($fields as $field_index => $field_name) {
        $result[$field_name] = $field_values[$field_index];
      }
      $results[] = $result;
    }
    return $results;
  }

  // --( Parsing `git log` )---------------------------------------------------

  public function doesRevisionExistInLog($rid) {
    list($err, $stdout) = $this->api
      ->execManualLocal("log -E --grep '^Differential Revision:.*D{$rid}$'");
    return ($stdout !== '');
  }

  // --( Branch manipulation )-------------------------------------------------

  public function checkoutBranch($name) {
    // Regular checkout
    list($err, $stdout, $stderr) = $this->api
      ->execManualLocal('checkout %s', $name);
    echo $stdout;
    if ($err) {
      throw new ArcanistUsageException($stderr);
    }
  }

  public function createAndCheckoutBranchFromHead($name) {
    // Creates new branch tracking HEAD, checkout
    list($err, $stdout, $stderr) = $this->api
      ->execManualLocal('checkout --track -b %s', $name);
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
        "A branch with the name **'%s'** already exists in your ".
        "tree.\n", $name)));
    } else if (!in_array($upstream, ipull($branches, 'name'))) {
      // Upstream doesn't exist in tree
      throw new ArcanistUsageException(phutil_console_format(pht(
        "Upstream branch **'%s'** does not exist.\n", $upstream)));
    }

    list($err, $stdout, $stderr) = $this->api
      ->execManualLocal('checkout --track -b %s %s', $name, $upstream);
    echo $stdout;
    if ($err) {
      throw new ArcanistUsageException($stderr);
    }
  }

  public function getGitCommitLog($base, $head) {
    list($stdout) = $this->api->execxLocal(
      'log --first-parent --format=medium %s..%s',
      $base,
      $head);
    return $stdout;
  }

  // check if rev actually exists
  public function revParseVerify($rev) {
    list($err, $stdout, $stderr) = $this->api
      ->execManualLocal('rev-parse --verify %s', $rev);
    if ($err) {
      return false;
    }
    return true;
  }

  public function getDefaultRemoteBranch($remote = 'origin') {
    $ref_path = sprintf('refs/remotes/%s/', $remote);
    $ref_head = $ref_path.'HEAD';
    list($stdout, $stderr) = $this->api->execxLocal(
      'symbolic-ref %s', $ref_head);
    $branch = trim($stdout);
    if (empty($branch)) {
      throw new ArcanistUsageException(
        sprintf('Remote %s has not default branch', $remote));
    }
    $branch = str_replace($ref_path, '', $branch);
    return $branch;
  }

}
