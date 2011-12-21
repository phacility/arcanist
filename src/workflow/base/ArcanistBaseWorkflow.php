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
 * = Managing Conduit =
 *
 * Workflows have the builtin ability to open a Conduit connection to a
 * Phabricator installation, so methods can be invoked over the API. Workflows
 * may either not need this (e.g., "help"), or may need a Conduit but not
 * authentication (e.g., calling only public APIs), or may need a Conduit and
 * authentication (e.g., "arc diff").
 *
 * To specify that you need an //unauthenticated// conduit, override
 * @{method:requiresConduit} to return ##true##. To specify that you need an
 * //authenticated// conduit, override @{method:requiresAuthentication} to
 * return ##true##. You can also manually invoke @{method:establishConduit}
 * and/or @{method:authenticateConduit} later in a workflow to upgrade it.
 * Once a conduit is open, you can access the client by calling
 * @{method:getConduit}, which allows you to invoke methods. You can get
 * verified information about the user identity by calling @{method:getUserPHID}
 * or @{method:getUserName} after authentication occurs.
 *
 * @task  conduit   Conduit
 * @group workflow
 */
class ArcanistBaseWorkflow {

  private $conduit;
  private $conduitURI;
  private $conduitCredentials;
  private $conduitAuthenticated;

  private $userPHID;
  private $userName;
  private $repositoryAPI;
  private $workingCopy;
  private $arguments;
  private $command;

  private $arcanistConfiguration;
  private $parentWorkflow;
  private $workingDirectory;

  private $changeCache = array();

  public function __construct() {

  }


/* -(  Conduit  )------------------------------------------------------------ */


  /**
   * Set the URI which the workflow will open a conduit connection to when
   * @{method:establishConduit} is called. Arcanist makes an effort to set
   * this by default for all workflows (by reading ##.arcconfig## and/or the
   * value of ##--conduit-uri##) even if they don't need Conduit, so a workflow
   * can generally upgrade into a conduit workflow later by just calling
   * @{method:establishConduit}.
   *
   * You generally should not need to call this method unless you are
   * specifically overriding the default URI. It is normally sufficient to
   * just invoke @{method:establishConduit}.
   *
   * NOTE: You can not call this after a conduit has been established.
   *
   * @param string  The URI to open a conduit to when @{method:establishConduit}
   *                is called.
   * @return this
   * @task conduit
   */
  final public function setConduitURI($conduit_uri) {
    if ($this->conduit) {
      throw new Exception(
        "You can not change the Conduit URI after a conduit is already open.");
    }
    $this->conduitURI = $conduit_uri;
    return $this;
  }


  /**
   * Open a conduit channel to the server which was previously configured by
   * calling @{method:setConduitURI}. Arcanist will do this automatically if
   * the workflow returns ##true## from @{method:requiresConduit}, or you can
   * later upgrade a workflow and build a conduit by invoking it manually.
   *
   * You must establish a conduit before you can make conduit calls.
   *
   * NOTE: You must call @{method:setConduitURI} before you can call this
   * method.
   *
   * @return this
   * @task conduit
   */
  final public function establishConduit() {
    if ($this->conduit) {
      return $this;
    }

    if (!$this->conduitURI) {
      throw new Exception(
        "You must specify a Conduit URI with setConduitURI() before you can ".
        "establish a conduit.");
    }

    $this->conduit = new ConduitClient($this->conduitURI);

    return $this;
  }


  /**
   * Set credentials which will be used to authenticate against Conduit. These
   * credentials can then be used to establish an authenticated connection to
   * conduit by calling @{method:authenticateConduit}. Arcanist sets some
   * defaults for all workflows regardless of whether or not they return true
   * from @{method:requireAuthentication}, based on the ##~/.arcrc## and
   * ##.arcconf## files if they are present. Thus, you can generally upgrade a
   * workflow which does not require authentication into an authenticated
   * workflow by later invoking @{method:requireAuthentication}. You should not
   * normally need to call this method unless you are specifically overriding
   * the defaults.
   *
   * NOTE: You can not call this method after calling
   * @{method:authenticateConduit}.
   *
   * @param dict  A credential dictionary, see @{method:authenticateConduit}.
   * @return this
   * @task conduit
   */
  final public function setConduitCredentials(array $credentials) {
    if ($this->conduitAuthenticated) {
      throw new Exception(
        "You may not set new credentials after authenticating conduit.");
    }

    $this->conduitCredentials = $credentials;
    return $this;
  }


  /**
   * Open and authenticate a conduit connection to a Phabricator server using
   * provided credentials. Normally, Arcanist does this for you automatically
   * when you return true from @{method:requiresAuthentication}, but you can
   * also upgrade an existing workflow to one with an authenticated conduit
   * by invoking this method manually.
   *
   * You must authenticate the conduit before you can make authenticated conduit
   * calls (almost all calls require authentication).
   *
   * This method uses credentials provided via @{method:setConduitCredentials}
   * to authenticate to the server:
   *
   *    - ##user## (required) The username to authenticate with.
   *    - ##certificate## (required) The Conduit certificate to use.
   *    - ##description## (optional) Description of the invoking command.
   *
   * Successful authentication allows you to call @{method:getUserPHID} and
   * @{method:getUserName}, as well as use the client you access with
   * @{method:getConduit} to make authenticated calls.
   *
   * NOTE: You must call @{method:setConduitURI} and
   * @{method:setConduitCredentials} before you invoke this method.
   *
   * @return this
   * @task conduit
   */
  final public function authenticateConduit() {
    if ($this->conduitAuthenticated) {
      return $this;
    }

    $this->establishConduit();

    $credentials = $this->conduitCredentials;
    if (!$credentials) {
      throw new Exception(
        "Set conduit credentials with setConduitCredentials() before ".
        "authenticating conduit!");
    }

    if (empty($credentials['user']) || empty($credentials['certificate'])) {
      throw new Exception(
        "Credentials must include a 'user' and a 'certificate'.");
    }

    $description = idx($credentials, 'description', '');
    $user        = $credentials['user'];
    $certificate = $credentials['certificate'];

    try {
      $connection = $this->getConduit()->callMethodSynchronous(
        'conduit.connect',
        array(
          'client'              => 'arc',
          'clientVersion'       => 3,
          'clientDescription'   => php_uname('n').':'.$description,
          'user'                => $user,
          'certificate'         => $certificate,
          'host'                => $this->conduitURI,
        ));
    } catch (ConduitClientException $ex) {
      if ($ex->getErrorCode() == 'ERR-NO-CERTIFICATE' ||
          $ex->getErrorCode() == 'ERR-INVALID-USER') {
        $conduit_uri = $this->conduitURI;
        $message =
          "\n".
          phutil_console_format(
            "YOU NEED TO __INSTALL A CERTIFICATE__ TO LOGIN TO PHABRICATOR").
          "\n\n".
          phutil_console_format(
            "    To do this, run: **arc install-certificate**").
          "\n\n".
          "The server '{$conduit_uri}' rejected your request:".
          "\n".
          $ex->getMessage();
        throw new ArcanistUsageException($message);
      } else {
        throw $ex;
      }
    }

    $this->userName = $user;
    $this->userPHID = $connection['userPHID'];

    $this->conduitAuthenticated = true;

    return $this;
  }


  /**
   * Override this to return true if your workflow requires a conduit channel.
   * Arc will build the channel for you before your workflow executes. This
   * implies that you only need an unauthenticated channel; if you need
   * authentication, override @{method:requiresAuthentication}.
   *
   * @return bool True if arc should build a conduit channel before running
   *              the workflow.
   * @task conduit
   */
  public function requiresConduit() {
    return false;
  }


  /**
   * Override this to return true if your workflow requires an authenticated
   * conduit channel. This implies that it requires a conduit. Arc will build
   * and authenticate the channel for you before the workflow executes.
   *
   * @return bool True if arc should build an authenticated conduit channel
   *              before running the workflow.
   * @task conduit
   */
  public function requiresAuthentication() {
    return false;
  }


  /**
   * Returns the PHID for the user once they've authenticated via Conduit.
   *
   * @return phid Authenticated user PHID.
   * @task conduit
   */
  final public function getUserPHID() {
    if (!$this->userPHID) {
      $workflow = get_class($this);
      throw new Exception(
        "This workflow ('{$workflow}') requires authentication, override ".
        "requiresAuthentication() to return true.");
    }
    return $this->userPHID;
  }

  /**
   * Deprecated. See @{method:getUserPHID}.
   *
   * @deprecated
   */
  final public function getUserGUID() {
    phutil_deprecated(
      'ArcanistBaseWorkflow::getUserGUID',
      'This method has been renamed to getUserPHID().');
    return $this->getUserPHID();
  }

  /**
   * Return the username for the user once they've authenticated via Conduit.
   *
   * @return string Authenticated username.
   * @task conduit
   */
  final public function getUserName() {
    return $this->userName;
  }


  /**
   * Get the established @{class@libphutil:ConduitClient} in order to make
   * Conduit method calls. Before the client is available it must be connected,
   * either implicitly by making @{method:requireConduit} or
   * @{method:requireAuthentication} return true, or explicitly by calling
   * @{method:establishConduit} or @{method:authenticateConduit}.
   *
   * @return @{class@libphutil:ConduitClient} Live conduit client.
   * @task conduit
   */
  final public function getConduit() {
    if (!$this->conduit) {
      $workflow = get_class($this);
      throw new Exception(
        "This workflow ('{$workflow}') requires a Conduit, override ".
        "requiresConduit() to return true.");
    }
    return $this->conduit;
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

  public function getArguments() {
    return array();
  }

  public function setWorkingDirectory($working_directory) {
    $this->workingDirectory = $working_directory;
    return $this;
  }

  public function getWorkingDirectory() {
    return $this->workingDirectory;
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

    if ($this->userPHID) {
      $workflow->userPHID = $this->getUserPHID();
      $workflow->userName = $this->getUserName();
    }

    if ($this->conduit) {
      $workflow->conduit = $this->conduit;
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

  public function requireCleanWorkingCopy() {
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
        } else if ($api instanceof ArcanistMercurialAPI) {
          echo phutil_console_wrap(
            "Since you don't have '.hgignore' rules for these files, you ".
            "may have forgotten to 'hg add' them to your commit.");
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
    $bundle->setProjectID($diff['projectName']);
    return $bundle;
  }

  /**
   * Return a list of lines changed by the current diff, or ##null## if the
   * change list is meaningless (for example, because the path is a directory
   * or binary file).
   *
   * @param string      Path within the repository.
   * @param string      Change selection mode (see ArcanistDiffHunk).
   * @return list|null  List of changed line numbers, or null to indicate that
   *                    the path is not a line-oriented text file.
   */
  protected function getChangedLines($path, $mode) {
    $repository_api = $this->getRepositoryAPI();
    $full_path = $repository_api->getPath($path);
    if (is_dir($full_path)) {
      return null;
    }

    $change = $this->getChange($path);

    if ($change->getFileType() !== ArcanistDiffChangeType::FILE_TEXT) {
      return null;
    }

    $lines = $change->getChangedLines($mode);
    return array_keys($lines);
  }

  private function getChange($path) {
    $repository_api = $this->getRepositoryAPI();

    if ($repository_api instanceof ArcanistSubversionAPI) {
      // NOTE: In SVN, we don't currently support a "get all local changes"
      // operation, so special case it.
      if (empty($this->changeCache[$path])) {
        $diff = $repository_api->getRawDiffText($path);
        $parser = new ArcanistDiffParser();
        $changes = $parser->parseDiff($diff);
        if (count($changes) != 1) {
          throw new Exception("Expected exactly one change.");
        }
        $this->changeCache[$path] = reset($changes);
      }
    } else if ($repository_api->supportsRelativeLocalCommits()) {
      if (empty($this->changeCache)) {
        $changes = $repository_api->getAllLocalChanges();
        foreach ($changes as $change) {
          $this->changeCache[$change->getCurrentPath()] = $change;
        }
      }
    } else {
      throw new Exception("Missing VCS support.");
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

  public static function getUserConfigurationFileLocation() {
    return getenv('HOME').'/.arcrc';
  }

  public static function readUserConfigurationFile() {
    $user_config = array();
    $user_config_path = self::getUserConfigurationFileLocation();
    if (Filesystem::pathExists($user_config_path)) {
      $mode = fileperms($user_config_path);
      if (!$mode) {
        throw new Exception("Unable to get perms of '{$user_config_path}'!");
      }
      if ($mode & 0177) {
        // Mode should allow only owner access.
        $prompt = "File permissions on your ~/.arcrc are too open. ".
                  "Fix them by chmod'ing to 600?";
        if (!phutil_console_confirm($prompt, $default_no = false)) {
          throw new ArcanistUsageException("Set ~/.arcrc to file mode 600.");
        }
        execx('chmod 600 %s', $user_config_path);
      }

      $user_config_data = Filesystem::readFile($user_config_path);
      $user_config = json_decode($user_config_data, true);
      if (!is_array($user_config)) {
        throw new ArcanistUsageException(
          "Your '~/.arcrc' file is not a valid JSON file.");
      }
    }
    return $user_config;
  }

  /**
   * Write a message to stderr so that '--json' flags or stdout which is meant
   * to be piped somewhere aren't disrupted.
   *
   * @param string  Message to write to stderr.
   * @return void
   */
  protected function writeStatusMessage($msg) {
    file_put_contents('php://stderr', $msg);
  }

  protected function isHistoryImmutable() {
    $working_copy = $this->getWorkingCopy();
    return ($working_copy->getConfig('immutable_history') === true);
  }

}
