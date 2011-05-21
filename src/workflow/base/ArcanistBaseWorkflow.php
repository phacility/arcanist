<?php

/*
 * Copyright 2011 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Implements a runnable command, like "arc diff" or "arc help".
 *
 * @group workflow
 */
class ArcanistBaseWorkflow {

  private $conduit;
  private $userGUID;
  private $userName;
  private $repositoryAPI;
  private $workingCopy;
  private $arguments;
  private $command;

  private $arcanistConfiguration;
  private $parentWorkflow;

  private $changeCache = array();

  public function __construct() {

  }

  public function setArcanistConfiguration($arcanist_configuration) {
    $this->arcanistConfiguration = $arcanist_configuration;
    return $this;
  }

  public function getArcanistConfiguration() {
    return $this->arcanistConfiguration;
  }

  public function getCommandHelp() {
    return get_class($this).": Undocumented";
  }

  public function requiresWorkingCopy() {
    return false;
  }

  public function requiresConduit() {
    return false;
  }

  public function requiresAuthentication() {
    return false;
  }

  public function requiresRepositoryAPI() {
    return false;
  }

  public function setCommand($command) {
    $this->command = $command;
    return $this;
  }

  public function getCommand() {
    return $this->command;
  }

  public function setUserName($user_name) {
    $this->userName = $user_name;
    return $this;
  }

  public function getUserName() {
    return $this->userName;
  }

  public function getArguments() {
    return array();
  }

  private function setParentWorkflow($parent_workflow) {
    $this->parentWorkflow = $parent_workflow;
    return $this;
  }

  protected function getParentWorkflow() {
    return $this->parentWorkflow;
  }

  public function buildChildWorkflow($command, array $argv) {
    $arc_config = $this->getArcanistConfiguration();
    $workflow = $arc_config->buildWorkflow($command);
    $workflow->setParentWorkflow($this);
    $workflow->setCommand($command);

    if ($this->repositoryAPI) {
      $workflow->setRepositoryAPI($this->repositoryAPI);
    }

    if ($this->userGUID) {
      $workflow->setUserGUID($this->getUserGUID());
      $workflow->setUserName($this->getUserName());
    }

    if ($this->conduit) {
      $workflow->setConduit($this->conduit);
    }

    if ($this->workingCopy) {
      $workflow->setWorkingCopy($this->workingCopy);
    }

    $workflow->setArcanistConfiguration($arc_config);

    $workflow->parseArguments(array_values($argv));

    return $workflow;
  }

  public function getArgument($key, $default = null) {
    $args = $this->arguments;
    if (!array_key_exists($key, $args)) {
      return $default;
    }
    return $args[$key];
  }

  final public function getCompleteArgumentSpecification() {
    $spec = $this->getArguments();
    $arc_config = $this->getArcanistConfiguration();
    $command = $this->getCommand();
    $spec += $arc_config->getCustomArgumentsForCommand($command);
    return $spec;
  }

  public function parseArguments(array $args) {

    $spec = $this->getCompleteArgumentSpecification();

    $dict = array();

    $more_key = null;
    if (!empty($spec['*'])) {
      $more_key = $spec['*'];
      unset($spec['*']);
      $dict[$more_key] = array();
    }

    $short_to_long_map = array();
    foreach ($spec as $long => $options) {
      if (!empty($options['short'])) {
        $short_to_long_map[$options['short']] = $long;
      }
    }

    $more = array();
    for ($ii = 0; $ii < count($args); $ii++) {
      $arg = $args[$ii];
      $arg_name = null;
      $arg_key = null;
      if ($arg == '--') {
        $more = array_merge(
          $more,
          array_slice($args, $ii + 1));
        break;
      } else if (!strncmp($arg, '--', 2)) {
        $arg_key = substr($arg, 2);
        if (!array_key_exists($arg_key, $spec)) {
          throw new ArcanistUsageException(
            "Unknown argument '{$arg_key}'. Try 'arc help'.");
        }
      } else if (!strncmp($arg, '-', 1)) {
        $arg_key = substr($arg, 1);
        if (empty($short_to_long_map[$arg_key])) {
          throw new ArcanistUsageException(
            "Unknown argument '{$arg_key}'. Try 'arc help'.");
        }
        $arg_key = $short_to_long_map[$arg_key];
      } else {
        $more[] = $arg;
        continue;
      }

      $options = $spec[$arg_key];
      if (empty($options['param'])) {
        $dict[$arg_key] = true;
      } else {
        if ($ii == count($args) - 1) {
          throw new ArcanistUsageException(
            "Option '{$arg}' requires a parameter.");
        }
        $dict[$arg_key] = $args[$ii + 1];
        $ii++;
      }
    }

    if ($more) {
      if ($more_key) {
        $dict[$more_key] = $more;
      } else {
        $example = reset($more);
        throw new ArcanistUsageException(
          "Unrecognized argument '{$example}'. Try 'arc help'.");
      }
    }

    foreach ($dict as $key => $value) {
      if (empty($spec[$key]['conflicts'])) {
        continue;
      }
      foreach ($spec[$key]['conflicts'] as $conflict => $more) {
        if (isset($dict[$conflict])) {
          if ($more) {
            $more = ': '.$more;
          } else {
            $more = '.';
          }
          // TODO: We'll always display these as long-form, when the user might
          // have typed them as short form.
          throw new ArcanistUsageException(
            "Arguments '--{$key}' and '--{$conflict}' are mutually exclusive".
            $more);
        }
      }
    }

    $this->arguments = $dict;

    $this->didParseArguments();

    return $this;
  }

  protected function didParseArguments() {
    // Override this to customize workflow argument behavior.
  }

  public function getWorkingCopy() {
    if (!$this->workingCopy) {
      $workflow = get_class($this);
      throw new Exception(
        "This workflow ('{$workflow}') requires a working copy, override ".
        "requiresWorkingCopy() to return true.");
    }
    return $this->workingCopy;
  }

  public function setWorkingCopy(
    ArcanistWorkingCopyIdentity $working_copy) {
    $this->workingCopy = $working_copy;
    return $this;
  }

  public function getConduit() {
    if (!$this->conduit) {
      $workflow = get_class($this);
      throw new Exception(
        "This workflow ('{$workflow}') requires a Conduit, override ".
        "requiresConduit() to return true.");
    }
    return $this->conduit;
  }

  public function setConduit(ConduitClient $conduit) {
    $this->conduit = $conduit;
    return $this;
  }

  public function getUserGUID() {
    if (!$this->userGUID) {
      $workflow = get_class($this);
      throw new Exception(
        "This workflow ('{$workflow}') requires authentication, override ".
        "requiresAuthentication() to return true.");
    }
    return $this->userGUID;
  }

  public function setUserGUID($guid) {
    $this->userGUID = $guid;
    return $this;
  }

  public function setRepositoryAPI($api) {
    $this->repositoryAPI = $api;
    return $this;
  }

  public function getRepositoryAPI() {
    if (!$this->repositoryAPI) {
      $workflow = get_class($this);
      throw new Exception(
        "This workflow ('{$workflow}') requires a Repository API, override ".
        "requiresRepositoryAPI() to return true.");
    }
    return $this->repositoryAPI;
  }

  protected function shouldRequireCleanUntrackedFiles() {
    return empty($this->arguments['allow-untracked']);
  }

  protected function requireCleanWorkingCopy() {
    $api = $this->getRepositoryAPI();

    $working_copy_desc = phutil_console_format(
      "  Working copy: __%s__\n\n",
      $api->getPath());

    $untracked = $api->getUntrackedChanges();
    if ($this->shouldRequireCleanUntrackedFiles()) {
      if (!empty($untracked)) {
        echo "You have untracked files in this working copy.\n\n".
             $working_copy_desc.
             "  Untracked files in working copy:\n".
             "    ".implode("\n    ", $untracked)."\n\n";

        if ($api instanceof ArcanistGitAPI) {
          echo phutil_console_wrap(
            "Since you don't have '.gitignore' rules for these files and have ".
            "not listed them in '.git/info/exclude', you may have forgotten ".
            "to 'git add' them to your commit.");
        } else if ($api instanceof ArcanistSubversionAPI) {
          echo phutil_console_wrap(
            "Since you don't have 'svn:ignore' rules for these files, you may ".
            "have forgotten to 'svn add' them.");
        }

        $prompt = "Do you want to continue without adding these files?";
        if (!phutil_console_confirm($prompt, $default_no = false)) {
          throw new ArcanistUserAbortException();
        }
      }
    }

    $incomplete = $api->getIncompleteChanges();
    if ($incomplete) {
      throw new ArcanistUsageException(
        "You have incompletely checked out directories in this working copy. ".
        "Fix them before proceeding.\n\n".
        $working_copy_desc.
        "  Incomplete directories in working copy:\n".
        "    ".implode("\n    ", $incomplete)."\n\n".
        "You can fix these paths by running 'svn update' on them.");
    }

    $conflicts = $api->getMergeConflicts();
    if ($conflicts) {
      throw new ArcanistUsageException(
        "You have merge conflicts in this working copy. Resolve merge ".
        "conflicts before proceeding.\n\n".
        $working_copy_desc.
        "  Conflicts in working copy:\n".
        "    ".implode("\n    ", $conflicts)."\n");
    }

    $unstaged = $api->getUnstagedChanges();
    if ($unstaged) {
      throw new ArcanistUsageException(
        "You have unstaged changes in this working copy. Stage and commit (or ".
        "revert) them before proceeding.\n\n".
        $working_copy_desc.
        "  Unstaged changes in working copy:\n".
        "    ".implode("\n    ", $unstaged)."\n");
    }

    $uncommitted = $api->getUncommittedChanges();
    if ($uncommitted) {
      throw new ArcanistUsageException(
        "You have uncommitted changes in this branch. Commit (or revert) them ".
        "before proceeding.\n\n".
        $working_copy_desc.
        "  Uncommitted changes in working copy\n".
        "    ".implode("\n    ", $uncommitted)."\n");
    }
  }

  protected function chooseRevision(
    array $revision_data,
    $revision_id,
    $prompt = null) {

    $revisions = array();
    foreach ($revision_data as $data) {
      $ref = ArcanistDifferentialRevisionRef::newFromDictionary($data);
      $revisions[$ref->getID()] = $ref;
    }

    if ($revision_id) {
      $revision_id = $this->normalizeRevisionID($revision_id);
      if (empty($revisions[$revision_id])) {
        throw new ArcanistChooseInvalidRevisionException();
      }
      return $revisions[$revision_id];
    }

    if (!count($revisions)) {
      throw new ArcanistChooseNoRevisionsException();
    }

    $repository_api = $this->getRepositoryAPI();

    $candidates = array();
    $cur_path = $repository_api->getPath();
    foreach ($revisions as $revision) {
      $source_path = $revision->getSourcePath();
      if ($source_path == $cur_path) {
        $candidates[] = $revision;
      }
    }

    if (count($candidates) == 1) {
      $candidate = reset($candidates);
      $revision_id = $candidate->getID();
    }

    if ($revision_id) {
      return $revisions[$revision_id];
    }

    $revision_indexes = array_keys($revisions);

    echo "\n";
    $ii = 1;
    foreach ($revisions as $revision) {
      echo '  ['.$ii++.'] D'.$revision->getID().' '.$revision->getName()."\n";
    }

    while (true) {
      $id = phutil_console_prompt($prompt);
      $id = trim(strtoupper($id), 'D');
      if (isset($revisions[$id])) {
        return $revisions[$id];
      }
      if (isset($revision_indexes[$id - 1])) {
        return $revisions[$revision_indexes[$id - 1]];
      }
    }
  }

  protected function loadDiffBundleFromConduit(
    ConduitClient $conduit,
    $diff_id) {

    return $this->loadBundleFromConduit(
      $conduit,
      array(
      'diff_id' => $diff_id,
    ));
  }

  protected function loadRevisionBundleFromConduit(
    ConduitClient $conduit,
    $revision_id) {

    return $this->loadBundleFromConduit(
      $conduit,
      array(
      'revision_id' => $revision_id,
    ));
  }

  private function loadBundleFromConduit(
    ConduitClient $conduit,
    $params) {

    $future = $conduit->callMethod('differential.getdiff', $params);
    $diff = $future->resolve();

    $changes = array();
    foreach ($diff['changes'] as $changedict) {
      $changes[] = ArcanistDiffChange::newFromDictionary($changedict);
    }
    $bundle = ArcanistBundle::newFromChanges($changes);
    $bundle->setConduit($conduit);
    return $bundle;
  }

  protected function getChangedLines($path, $mode) {
    if (is_dir($path)) {
      return array();
    }

    $change = $this->getChange($path);
    $lines = $change->getChangedLines($mode);
    return array_keys($lines);
  }

  private function getChange($path) {
    $repository_api = $this->getRepositoryAPI();

    if ($repository_api instanceof ArcanistSubversionAPI) {
      if (empty($this->changeCache[$path])) {
        $diff = $repository_api->getRawDiffText($path);
        $parser = new ArcanistDiffParser();
        $changes = $parser->parseDiff($diff);
        if (count($changes) != 1) {
          throw new Exception("Expected exactly one change.");
        }
        $this->changeCache[$path] = reset($changes);
      }
    } else {
      if (empty($this->changeCache)) {
        $diff = $repository_api->getFullGitDiff();
        $parser = new ArcanistDiffParser();
        $changes = $parser->parseDiff($diff);
        foreach ($changes as $change) {
          $this->changeCache[$change->getCurrentPath()] = $change;
        }
      }
    }

    if (empty($this->changeCache[$path])) {
      if ($repository_api instanceof ArcanistGitAPI) {
        // This can legitimately occur under git if you make a change, "git
        // commit" it, and then revert the change in the working copy and run
        // "arc lint".
        $change = new ArcanistDiffChange();
        $change->setCurrentPath($path);
        return $change;
      } else {
        throw new Exception(
          "Trying to get change for unchanged path '{$path}'!");
      }
    }

    return $this->changeCache[$path];
  }

  final public function willRunWorkflow() {
    $spec = $this->getCompleteArgumentSpecification();
    foreach ($this->arguments as $arg => $value) {
      if (empty($spec[$arg])) {
        continue;
      }
      $options = $spec[$arg];
      if (!empty($options['supports'])) {
        $system_name = $this->getRepositoryAPI()->getSourceControlSystemName();
        if (!in_array($system_name, $options['supports'])) {
          $extended_info = null;
          if (!empty($options['nosupport'][$system_name])) {
            $extended_info = ' '.$options['nosupport'][$system_name];
          }
          throw new ArcanistUsageException(
            "Option '--{$arg}' is not supported under {$system_name}.".
            $extended_info);
        }
      }
    }
  }

  protected function parseGitRelativeCommit(ArcanistGitAPI $api, array $argv) {
    if (count($argv) == 0) {
      return;
    }
    if (count($argv) != 1) {
      throw new ArcanistUsageException(
        "Specify exactly one commit.");
    }
    $base = reset($argv);
    if ($base == ArcanistGitAPI::GIT_MAGIC_ROOT_COMMIT) {
      $merge_base = $base;
    } else {
      list($err, $merge_base) = exec_manual(
        '(cd %s; git merge-base %s HEAD)',
        $api->getPath(),
        $base);
      if ($err) {
        throw new ArcanistUsageException(
          "Unable to parse git commit name '{$base}'.");
      }
    }
    $api->setRelativeCommit(trim($merge_base));
  }

  protected function normalizeRevisionID($revision_id) {
    return ltrim(strtoupper($revision_id), 'D');
  }

  protected function shouldShellComplete() {
    return true;
  }

  protected function getShellCompletions(array $argv) {
    return array();
  }

  protected function getSupportedRevisionControlSystems() {
    return array('any');
  }

  protected function getPassthruArgumentsAsMap($command) {
    $map = array();
    foreach ($this->getCompleteArgumentSpecification() as $key => $spec) {
      if (!empty($spec['passthru'][$command])) {
        if (isset($this->arguments[$key])) {
          $map[$key] = $this->arguments[$key];
        }
      }
    }
    return $map;
  }

  protected function getPassthruArgumentsAsArgv($command) {
    $spec = $this->getCompleteArgumentSpecification();
    $map = $this->getPassthruArgumentsAsMap($command);
    $argv = array();
    foreach ($map as $key => $value) {
      $argv[] = '--'.$key;
      if (!empty($spec[$key]['param'])) {
        $argv[] = $value;
      }
    }
    return $argv;
  }

}
