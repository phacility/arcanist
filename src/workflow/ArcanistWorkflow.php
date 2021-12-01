<?php

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
 * = Scratch Files =
 *
 * Arcanist workflows can read and write 'scratch files', which are temporary
 * files stored in the project that persist across commands. They can be useful
 * if you want to save some state, or keep a copy of a long message the user
 * entered if something goes wrong.
 *
 *
 * @task  conduit   Conduit
 * @task  scratch   Scratch Files
 * @task  phabrep   Phabricator Repositories
 */
abstract class ArcanistWorkflow extends Phobject {

  const COMMIT_DISABLE = 0;
  const COMMIT_ALLOW = 1;
  const COMMIT_ENABLE = 2;

  private $commitMode = self::COMMIT_DISABLE;

  private $conduit;
  private $conduitURI;
  private $conduitCredentials;
  private $conduitAuthenticated;
  private $conduitTimeout;

  private $userPHID;
  private $userName;
  private $repositoryAPI;
  private $configurationManager;
  private $arguments = array();
  private $command;

  private $stashed;
  private $shouldAmend;

  private $projectInfo;
  private $repositoryInfo;
  private $repositoryReasons;
  private $repositoryRef;

  private $arcanistConfiguration;
  private $parentWorkflow;
  private $workingDirectory;
  private $repositoryVersion;

  private $changeCache = array();
  private $conduitEngine;

  private $toolset;
  private $runtime;
  private $configurationEngine;
  private $configurationSourceList;

  private $promptMap;

  final public function setToolset(ArcanistToolset $toolset) {
    $this->toolset = $toolset;
    return $this;
  }

  final public function getToolset() {
    return $this->toolset;
  }

  final public function setRuntime(ArcanistRuntime $runtime) {
    $this->runtime = $runtime;
    return $this;
  }

  final public function getRuntime() {
    return $this->runtime;
  }

  final public function setConfigurationEngine(
    ArcanistConfigurationEngine $engine) {
    $this->configurationEngine = $engine;
    return $this;
  }

  final public function getConfigurationEngine() {
    return $this->configurationEngine;
  }

  final public function setConfigurationSourceList(
    ArcanistConfigurationSourceList $list) {
    $this->configurationSourceList = $list;
    return $this;
  }

  final public function getConfigurationSourceList() {
    return $this->configurationSourceList;
  }

  public function newPhutilWorkflow() {
    $arguments = $this->getWorkflowArguments();
    assert_instances_of($arguments, 'ArcanistWorkflowArgument');

    $specs = mpull($arguments, 'getPhutilSpecification');

    $phutil_workflow = id(new ArcanistPhutilWorkflow())
      ->setName($this->getWorkflowName())
      ->setWorkflow($this)
      ->setArguments($specs);

    $information = $this->getWorkflowInformation();

    if ($information !== null) {
      if (!($information instanceof ArcanistWorkflowInformation)) {
        throw new Exception(
          pht(
            'Expected workflow ("%s", of class "%s") to return an '.
            '"ArcanistWorkflowInformation" object from call to '.
            '"getWorkflowInformation()", got %s.',
            $this->getWorkflowName(),
            get_class($this),
            phutil_describe_type($information)));
      }
    }

    if ($information) {
      $synopsis = $information->getSynopsis();
      if (strlen($synopsis)) {
        $phutil_workflow->setSynopsis($synopsis);
      }

      $examples = $information->getExamples();
      if ($examples) {
        $examples = implode("\n", $examples);
        $phutil_workflow->setExamples($examples);
      }

      $help = $information->getHelp();
      if (strlen($help)) {
        // Unwrap linebreaks in the help text so we don't get weird formatting.
        $help = preg_replace("/(?<=\S)\n(?=\S)/", ' ', $help);

        $phutil_workflow->setHelp($help);
      }
    }

    return $phutil_workflow;
  }

  final public function newLegacyPhutilWorkflow() {
    $phutil_workflow = id(new ArcanistPhutilWorkflow())
      ->setName($this->getWorkflowName());

    $arguments = $this->getArguments();

    $specs = array();
    foreach ($arguments as $key => $argument) {
      if ($key == '*') {
        $key = $argument;
        $argument = array(
          'wildcard' => true,
        );
      }

      unset($argument['paramtype']);
      unset($argument['supports']);
      unset($argument['nosupport']);
      unset($argument['passthru']);
      unset($argument['conflict']);

      $spec = array(
        'name' => $key,
      ) + $argument;

      $specs[] = $spec;
    }

    $phutil_workflow->setArguments($specs);

    $synopses = $this->getCommandSynopses();
    $phutil_workflow->setSynopsis($synopses);

    $help = $this->getCommandHelp();
    if (strlen($help)) {
      $phutil_workflow->setHelp($help);
    }

    return $phutil_workflow;
  }

  final protected function newWorkflowArgument($key) {
    return id(new ArcanistWorkflowArgument())
      ->setKey($key);
  }

  final protected function newWorkflowInformation() {
    return new ArcanistWorkflowInformation();
  }

  final public function executeWorkflow(PhutilArgumentParser $args) {
    $runtime = $this->getRuntime();

    $this->arguments = $args;
    $caught = null;

    $runtime->pushWorkflow($this);

    try {
      $err = $this->runWorkflow($args);
    } catch (Exception $ex) {
      $caught = $ex;
    }

    try {
      $this->runWorkflowCleanup();
    } catch (Exception $ex) {
      phlog($ex);
    }

    $runtime->popWorkflow();

    if ($caught) {
      throw $caught;
    }

    return $err;
  }

  final public function getLogEngine() {
    return $this->getRuntime()->getLogEngine();
  }

  protected function runWorkflowCleanup() {
    // TOOLSETS: Do we need this?
    return;
  }

  public function __construct() {}

  public function run() {
    throw new PhutilMethodNotImplementedException();
  }

  /**
   * Finalizes any cleanup operations that need to occur regardless of
   * whether the command succeeded or failed.
   */
  public function finalize() {
    $this->finalizeWorkingCopy();
  }

  /**
   * Return the command used to invoke this workflow from the command like,
   * e.g. "help" for @{class:ArcanistHelpWorkflow}.
   *
   * @return string   The command a user types to invoke this workflow.
   */
  abstract public function getWorkflowName();

  /**
   * Return console formatted string with all command synopses.
   *
   * @return string  6-space indented list of available command synopses.
   */
  public function getCommandSynopses() {
    return array();
  }

  /**
   * Return console formatted string with command help printed in `arc help`.
   *
   * @return string  10-space indented help to use the command.
   */
  public function getCommandHelp() {
    return null;
  }

  public function supportsToolset(ArcanistToolset $toolset) {
    return false;
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
        pht(
          'You can not change the Conduit URI after a '.
          'conduit is already open.'));
    }
    $this->conduitURI = $conduit_uri;
    return $this;
  }

  /**
   * Returns the URI the conduit connection within the workflow uses.
   *
   * @return string
   * @task conduit
   */
  final public function getConduitURI() {
    return $this->conduitURI;
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
        pht(
          'You must specify a Conduit URI with %s before you can '.
          'establish a conduit.',
          'setConduitURI()'));
    }

    $this->conduit = new ConduitClient($this->conduitURI);

    if ($this->conduitTimeout) {
      $this->conduit->setTimeout($this->conduitTimeout);
    }

    return $this;
  }

  final public function getConfigFromAnySource($key) {
    $source_list = $this->getConfigurationSourceList();
    if ($source_list) {
      $value_list = $source_list->getStorageValueList($key);
      if ($value_list) {
        return last($value_list)->getValue();
      }

      return null;
    }

    return $this->configurationManager->getConfigFromAnySource($key);
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
    if ($this->isConduitAuthenticated()) {
      throw new Exception(
        pht('You may not set new credentials after authenticating conduit.'));
    }

    $this->conduitCredentials = $credentials;
    return $this;
  }


  /**
   * Get the protocol version the client should identify with.
   *
   * @return int Version the client should claim to be.
   * @task conduit
   */
  final public function getConduitVersion() {
    return 6;
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
    if ($this->isConduitAuthenticated()) {
      return $this;
    }

    $this->establishConduit();
    $credentials = $this->conduitCredentials;

    try {
      if (!$credentials) {
        throw new Exception(
          pht(
            'Set conduit credentials with %s before authenticating conduit!',
            'setConduitCredentials()'));
      }

      // If we have `token`, this server supports the simpler, new-style
      // token-based authentication. Use that instead of all the certificate
      // stuff.
      $token = idx($credentials, 'token');
      if (strlen($token)) {
        $conduit = $this->getConduit();

        $conduit->setConduitToken($token);

        try {
          $result = $this->getConduit()->callMethodSynchronous(
            'user.whoami',
            array());

          $this->userName = $result['userName'];
          $this->userPHID = $result['phid'];

          $this->conduitAuthenticated = true;

          return $this;
        } catch (Exception $ex) {
          $conduit->setConduitToken(null);
          throw $ex;
        }
      }

      if (empty($credentials['user'])) {
        throw new ConduitClientException(
          'ERR-INVALID-USER',
          pht('Empty user in credentials.'));
      }
      if (empty($credentials['certificate'])) {
        throw new ConduitClientException(
          'ERR-NO-CERTIFICATE',
          pht('Empty certificate in credentials.'));
      }

      $description = idx($credentials, 'description', '');
      $user        = $credentials['user'];
      $certificate = $credentials['certificate'];

      $connection = $this->getConduit()->callMethodSynchronous(
        'conduit.connect',
        array(
          'client'              => 'arc',
          'clientVersion'       => $this->getConduitVersion(),
          'clientDescription'   => php_uname('n').':'.$description,
          'user'                => $user,
          'certificate'         => $certificate,
          'host'                => $this->conduitURI,
        ));
    } catch (ConduitClientException $ex) {
      if ($ex->getErrorCode() == 'ERR-NO-CERTIFICATE' ||
          $ex->getErrorCode() == 'ERR-INVALID-USER' ||
          $ex->getErrorCode() == 'ERR-INVALID-AUTH') {
        $conduit_uri = $this->conduitURI;
        $message = phutil_console_format(
          "\n%s\n\n    %s\n\n%s\n%s",
          pht('YOU NEED TO __INSTALL A CERTIFICATE__ TO LOGIN TO PHABRICATOR'),
          pht('To do this, run: **%s**', 'arc install-certificate'),
          pht("The server '%s' rejected your request:", $conduit_uri),
          $ex->getMessage());
        throw new ArcanistUsageException($message);
      } else if ($ex->getErrorCode() == 'NEW-ARC-VERSION') {

        // Cleverly disguise this as being AWESOME!!!

        echo phutil_console_format("**%s**\n\n", pht('New Version Available!'));
        echo phutil_console_wrap($ex->getMessage());
        echo "\n\n";
        echo pht('In most cases, arc can be upgraded automatically.')."\n";

        $ok = phutil_console_confirm(
          pht('Upgrade arc now?'),
          $default_no = false);
        if (!$ok) {
          throw $ex;
        }

        $root = dirname(phutil_get_library_root('arcanist'));

        chdir($root);
        $err = phutil_passthru('%s upgrade', $root.'/bin/arc');
        if (!$err) {
          echo "\n".pht('Try running your arc command again.')."\n";
        }
        exit(1);
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
   * @return bool True if conduit is authenticated, false otherwise.
   * @task conduit
   */
  final protected function isConduitAuthenticated() {
    return (bool)$this->conduitAuthenticated;
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
        pht(
          "This workflow ('%s') requires authentication, override ".
          "%s to return true.",
          $workflow,
          'requiresAuthentication()'));
    }
    return $this->userPHID;
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
        pht(
          "This workflow ('%s') requires a Conduit, override ".
          "%s to return true.",
          $workflow,
          'requiresConduit()'));
    }
    return $this->conduit;
  }


  final public function setArcanistConfiguration(
    ArcanistConfiguration $arcanist_configuration) {

    $this->arcanistConfiguration = $arcanist_configuration;
    return $this;
  }

  final public function getArcanistConfiguration() {
    return $this->arcanistConfiguration;
  }

  final public function setConfigurationManager(
    ArcanistConfigurationManager $arcanist_configuration_manager) {

    $this->configurationManager = $arcanist_configuration_manager;
    return $this;
  }

  final public function getConfigurationManager() {
    return $this->configurationManager;
  }

  public function requiresWorkingCopy() {
    return false;
  }

  public function desiresWorkingCopy() {
    return false;
  }

  public function requiresRepositoryAPI() {
    return false;
  }

  public function desiresRepositoryAPI() {
    return false;
  }

  final public function setCommand($command) {
    $this->command = $command;
    return $this;
  }

  final public function getCommand() {
    return $this->command;
  }

  public function getArguments() {
    return array();
  }

  final public function setWorkingDirectory($working_directory) {
    $this->workingDirectory = $working_directory;
    return $this;
  }

  final public function getWorkingDirectory() {
    return $this->workingDirectory;
  }

  private function setParentWorkflow($parent_workflow) {
    $this->parentWorkflow = $parent_workflow;
    return $this;
  }

  final protected function getParentWorkflow() {
    return $this->parentWorkflow;
  }

  final public function buildChildWorkflow($command, array $argv) {
    $arc_config = $this->getArcanistConfiguration();
    $workflow = $arc_config->buildWorkflow($command);
    $workflow->setParentWorkflow($this);
    $workflow->setConduitEngine($this->getConduitEngine());
    $workflow->setCommand($command);
    $workflow->setConfigurationManager($this->getConfigurationManager());

    if ($this->repositoryAPI) {
      $workflow->setRepositoryAPI($this->repositoryAPI);
    }

    if ($this->userPHID) {
      $workflow->userPHID = $this->getUserPHID();
      $workflow->userName = $this->getUserName();
    }

    if ($this->conduit) {
      $workflow->conduit = $this->conduit;
      $workflow->setConduitCredentials($this->conduitCredentials);
      $workflow->conduitAuthenticated = $this->conduitAuthenticated;
    }

    $workflow->setArcanistConfiguration($arc_config);

    $workflow->parseArguments(array_values($argv));

    return $workflow;
  }

  final public function getArgument($key, $default = null) {
    // TOOLSETS: Remove this legacy code.
    if (is_array($this->arguments)) {
      return idx($this->arguments, $key, $default);
    }

    return $this->arguments->getArg($key);
  }

  final public function getCompleteArgumentSpecification() {
    $spec = $this->getArguments();
    $arc_config = $this->getArcanistConfiguration();
    $command = $this->getCommand();
    $spec += $arc_config->getCustomArgumentsForCommand($command);

    return $spec;
  }

  final public function parseArguments(array $args) {
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

    foreach ($spec as $long => $options) {
      if (!empty($options['repeat'])) {
        $dict[$long] = array();
      }
    }

    $more = array();
    $size = count($args);
    for ($ii = 0; $ii < $size; $ii++) {
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
        $parts = explode('=', $arg_key, 2);
        if (count($parts) == 2) {
          list($arg_key, $val) = $parts;

          array_splice($args, $ii, 1, array('--'.$arg_key, $val));
          $size++;
        }

        if (!array_key_exists($arg_key, $spec)) {
          $corrected = PhutilArgumentSpellingCorrector::newFlagCorrector()
            ->correctSpelling($arg_key, array_keys($spec));
          if (count($corrected) == 1) {
            PhutilConsole::getConsole()->writeErr(
              pht(
                "(Assuming '%s' is the British spelling of '%s'.)",
                '--'.$arg_key,
                '--'.head($corrected))."\n");
            $arg_key = head($corrected);
          } else {
            throw new ArcanistUsageException(
              pht(
                "Unknown argument '%s'. Try '%s'.",
                $arg_key,
                'arc help'));
          }
        }
      } else if (!strncmp($arg, '-', 1)) {
        $arg_key = substr($arg, 1);
        if (empty($short_to_long_map[$arg_key])) {
          throw new ArcanistUsageException(
            pht(
              "Unknown argument '%s'. Try '%s'.",
              $arg_key,
              'arc help'));
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
        if ($ii == $size - 1) {
          throw new ArcanistUsageException(
            pht(
              "Option '%s' requires a parameter.",
              $arg));
        }
        if (!empty($options['repeat'])) {
          $dict[$arg_key][] = $args[$ii + 1];
        } else {
          $dict[$arg_key] = $args[$ii + 1];
        }
        $ii++;
      }
    }

    if ($more) {
      if ($more_key) {
        $dict[$more_key] = $more;
      } else {
        $example = reset($more);
        throw new ArcanistUsageException(
          pht(
            "Unrecognized argument '%s'. Try '%s'.",
            $example,
            'arc help'));
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
            pht(
              "Arguments '%s' and '%s' are mutually exclusive",
              "--{$key}",
              "--{$conflict}").$more);
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

  final public function getWorkingCopy() {
    $configuration_engine = $this->getConfigurationEngine();

    // TOOLSETS: Remove this once all workflows are toolset workflows.
    if (!$configuration_engine) {
      throw new Exception(
        pht(
          'This workflow has not yet been updated to Toolsets and can '.
          'not retrieve a modern WorkingCopy object. Use '.
          '"getWorkingCopyIdentity()" to retrieve a previous-generation '.
          'object.'));
    }

    return $configuration_engine->getWorkingCopy();
  }


  final public function getWorkingCopyIdentity() {
    $configuration_engine = $this->getConfigurationEngine();
    if ($configuration_engine) {
      $working_copy = $configuration_engine->getWorkingCopy();
      $working_path = $working_copy->getWorkingDirectory();

      return ArcanistWorkingCopyIdentity::newFromPath($working_path);
    }

    $working_copy = $this->getConfigurationManager()->getWorkingCopyIdentity();
    if (!$working_copy) {
      $workflow = get_class($this);
      throw new Exception(
        pht(
          "This workflow ('%s') requires a working copy, override ".
          "%s to return true.",
          $workflow,
          'requiresWorkingCopy()'));
    }
    return $working_copy;
  }

  final public function setRepositoryAPI($api) {
    $this->repositoryAPI = $api;
    return $this;
  }

  final public function hasRepositoryAPI() {
    try {
      return (bool)$this->getRepositoryAPI();
    } catch (Exception $ex) {
      return false;
    }
  }

  final public function getRepositoryAPI() {
    $configuration_engine = $this->getConfigurationEngine();
    if ($configuration_engine) {
      $working_copy = $configuration_engine->getWorkingCopy();
      return $working_copy->getRepositoryAPI();
    }

    if (!$this->repositoryAPI) {
      $workflow = get_class($this);
      throw new Exception(
        pht(
          "This workflow ('%s') requires a Repository API, override ".
          "%s to return true.",
          $workflow,
          'requiresRepositoryAPI()'));
    }
    return $this->repositoryAPI;
  }

  final protected function shouldRequireCleanUntrackedFiles() {
    return empty($this->arguments['allow-untracked']);
  }

  final public function setCommitMode($mode) {
    $this->commitMode = $mode;
    return $this;
  }

  final public function finalizeWorkingCopy() {
    if ($this->stashed) {
      $api = $this->getRepositoryAPI();
      $api->unstashChanges();
      echo pht('Restored stashed changes to the working directory.')."\n";
    }
  }

  final public function requireCleanWorkingCopy() {
    $api = $this->getRepositoryAPI();

    $must_commit = array();

    $working_copy_desc = phutil_console_format(
      "  %s: __%s__\n\n",
      pht('Working copy'),
      $api->getPath());

    // NOTE: this is a subversion-only concept.
    $incomplete = $api->getIncompleteChanges();
    if ($incomplete) {
      throw new ArcanistUsageException(
        sprintf(
          "%s\n\n%s  %s\n    %s\n\n%s",
          pht(
            "You have incompletely checked out directories in this working ".
            "copy. Fix them before proceeding.'"),
          $working_copy_desc,
          pht('Incomplete directories in working copy:'),
          implode("\n    ", $incomplete),
          pht(
            "You can fix these paths by running '%s' on them.",
            'svn update')));
    }

    $conflicts = $api->getMergeConflicts();
    if ($conflicts) {
      throw new ArcanistUsageException(
        sprintf(
          "%s\n\n%s  %s\n    %s",
          pht(
            'You have merge conflicts in this working copy. Resolve merge '.
            'conflicts before proceeding.'),
          $working_copy_desc,
          pht('Conflicts in working copy:'),
          implode("\n    ", $conflicts)));
    }

    $missing = $api->getMissingChanges();
    if ($missing) {
      throw new ArcanistUsageException(
        sprintf(
          "%s\n\n%s  %s\n    %s\n",
          pht(
            'You have missing files in this working copy. Revert or formally '.
            'remove them (with `%s`) before proceeding.',
            'svn rm'),
          $working_copy_desc,
          pht('Missing files in working copy:'),
          implode("\n    ", $missing)));
    }

    $externals = $api->getDirtyExternalChanges();

    // TODO: This state can exist in Subversion, but it is currently handled
    // elsewhere. It should probably be handled here, eventually.
    if ($api instanceof ArcanistSubversionAPI) {
      $externals = array();
    }

    if ($externals) {
      $message = pht(
        '%s submodule(s) have uncommitted or untracked changes:',
        new PhutilNumber(count($externals)));

      $prompt = pht(
        'Ignore the changes to these %s submodule(s) and continue?',
        new PhutilNumber(count($externals)));

      $list = id(new PhutilConsoleList())
        ->setWrap(false)
        ->addItems($externals);

      id(new PhutilConsoleBlock())
        ->addParagraph($message)
        ->addList($list)
        ->draw();

      $ok = phutil_console_confirm($prompt, $default_no = false);
      if (!$ok) {
        throw new ArcanistUserAbortException();
      }
    }

    $uncommitted = $api->getUncommittedChanges();
    $unstaged = $api->getUnstagedChanges();

    // We already dealt with externals.
    $unstaged = array_diff($unstaged, $externals);

    // We only want files which are purely uncommitted.
    $uncommitted = array_diff($uncommitted, $unstaged);
    $uncommitted = array_diff($uncommitted, $externals);

    $untracked = $api->getUntrackedChanges();
    if (!$this->shouldRequireCleanUntrackedFiles()) {
      $untracked = array();
    }

    if ($untracked) {
      echo sprintf(
        "%s\n\n%s",
        pht('You have untracked files in this working copy.'),
        $working_copy_desc);

      if ($api instanceof ArcanistGitAPI) {
        $hint = pht(
          '(To ignore these %s change(s), add them to "%s".)',
          phutil_count($untracked),
          '.git/info/exclude');
      } else if ($api instanceof ArcanistSubversionAPI) {
        $hint = pht(
          '(To ignore these %s change(s), add them to "%s".)',
          phutil_count($untracked),
          'svn:ignore');
      } else if ($api instanceof ArcanistMercurialAPI) {
        $hint = pht(
          '(To ignore these %s change(s), add them to "%s".)',
          phutil_count($untracked),
          '.hgignore');
      }

      $untracked_list = "    ".implode("\n    ", $untracked);
      echo sprintf(
        "  %s\n  %s\n%s",
        pht('Untracked changes in working copy:'),
        $hint,
        $untracked_list);

      $prompt = pht(
        'Ignore these %s untracked file(s) and continue?',
        phutil_count($untracked));

      if (!phutil_console_confirm($prompt)) {
        throw new ArcanistUserAbortException();
      }
    }


    $should_commit = false;
    if ($unstaged || $uncommitted) {

      // NOTE: We're running this because it builds a cache and can take a
      // perceptible amount of time to arrive at an answer, but we don't want
      // to pause in the middle of printing the output below.
      $this->getShouldAmend();

      echo sprintf(
        "%s\n\n%s",
        pht('You have uncommitted changes in this working copy.'),
        $working_copy_desc);

      $lists = array();

      if ($unstaged) {
        $unstaged_list = "    ".implode("\n    ", $unstaged);
        $lists[] = sprintf(
          "  %s\n%s",
          pht('Unstaged changes in working copy:'),
          $unstaged_list);
      }

      if ($uncommitted) {
        $uncommitted_list = "    ".implode("\n    ", $uncommitted);
        $lists[] = sprintf(
          "%s\n%s",
          pht('Uncommitted changes in working copy:'),
          $uncommitted_list);
      }

      echo implode("\n\n", $lists)."\n";

      $all_uncommitted = array_merge($unstaged, $uncommitted);
      if ($this->askForAdd($all_uncommitted)) {
        if ($unstaged) {
          $api->addToCommit($unstaged);
        }
        $should_commit = true;
      } else {
        $permit_autostash = $this->getConfigFromAnySource('arc.autostash');
        if ($permit_autostash && $api->canStashChanges()) {
           echo pht(
            'Stashing uncommitted changes. (You can restore them with `%s`).',
            'git stash pop')."\n";
          $api->stashChanges();
          $this->stashed = true;
        } else {
          throw new ArcanistUsageException(
            pht(
              'You can not continue with uncommitted changes. '.
              'Commit or discard them before proceeding.'));
        }
      }
    }

    if ($should_commit) {
      if ($this->getShouldAmend()) {
        $commit = head($api->getLocalCommitInformation());
        $api->amendCommit($commit['message']);
      } else if ($api->supportsLocalCommits()) {
        $template = sprintf(
          "\n\n# %s\n#\n# %s\n#\n",
          pht('Enter a commit message.'),
          pht('Changes:'));

        $paths = array_merge($uncommitted, $unstaged);
        $paths = array_unique($paths);
        sort($paths);

        foreach ($paths as $path) {
          $template .= "#     ".$path."\n";
        }

        $commit_message = $this->newInteractiveEditor($template)
          ->setName(pht('commit-message'))
          ->setTaskMessage(pht(
            'Supply commit message for uncommitted changes, then save and '.
            'exit.'))
          ->editInteractively();

        if ($commit_message === $template) {
          throw new ArcanistUsageException(
            pht('You must provide a commit message.'));
        }

        $commit_message = ArcanistCommentRemover::removeComments(
          $commit_message);

        if (!strlen($commit_message)) {
          throw new ArcanistUsageException(
            pht('You must provide a nonempty commit message.'));
        }

        $api->doCommit($commit_message);
      }
    }
  }

  private function getShouldAmend() {
    if ($this->shouldAmend === null) {
      $this->shouldAmend = $this->calculateShouldAmend();
    }
    return $this->shouldAmend;
  }

  private function calculateShouldAmend() {
    $api = $this->getRepositoryAPI();

    if ($this->isHistoryImmutable() || !$api->supportsAmend()) {
      return false;
    }

    $commits = $api->getLocalCommitInformation();
    if (!$commits) {
      return false;
    }

    $commit = reset($commits);
    $message = ArcanistDifferentialCommitMessage::newFromRawCorpus(
      $commit['message']);

    if ($message->getGitSVNBaseRevision()) {
      return false;
    }

    if ($api->getAuthor() != $commit['author']) {
      return false;
    }

    if ($message->getRevisionID() && $this->getArgument('create')) {
      return false;
    }

    // TODO: Check commits since tracking branch. If empty then return false.

    // Don't amend the current commit if it has already been published.
    $repository = $this->loadProjectRepository();
    if ($repository) {
      $repo_id = $repository['id'];
      $commit_hash = $commit['commit'];
      $callsign =  idx($repository, 'callsign');
      if ($callsign) {
        // The server might be too old to support the new style commit names,
        // so prefer the old way
        $commit_name = "r{$callsign}{$commit_hash}";
      } else {
        $commit_name = "R{$repo_id}:{$commit_hash}";
      }

      $result = $this->getConduit()->callMethodSynchronous(
        'diffusion.querycommits',
        array('names' => array($commit_name)));
      $known_commit = idx($result['identifierMap'], $commit_name);
      if ($known_commit) {
        return false;
      }
    }

    if (!$message->getRevisionID()) {
      return true;
    }

    $in_working_copy = $api->loadWorkingCopyDifferentialRevisions(
      $this->getConduit(),
      array(
        'authors' => array($this->getUserPHID()),
        'status' => 'status-open',
      ));
    if ($in_working_copy) {
      return true;
    }

    return false;
  }

  private function askForAdd(array $files) {
    if ($this->commitMode == self::COMMIT_DISABLE) {
      return false;
    }
    if ($this->commitMode == self::COMMIT_ENABLE) {
      return true;
    }
    $prompt = $this->getAskForAddPrompt($files);
    return phutil_console_confirm($prompt);
  }

  private function getAskForAddPrompt(array $files) {
    if ($this->getShouldAmend()) {
      $prompt = pht(
        'Do you want to amend these %s change(s) to the current commit?',
        phutil_count($files));
    } else {
      $prompt = pht(
        'Do you want to create a new commit with these %s change(s)?',
        phutil_count($files));
    }
    return $prompt;
  }

  final protected function loadDiffBundleFromConduit(
    ConduitClient $conduit,
    $diff_id) {

    return $this->loadBundleFromConduit(
      $conduit,
      array(
      'ids' => array($diff_id),
    ));
  }

  final protected function loadRevisionBundleFromConduit(
    ConduitClient $conduit,
    $revision_id) {

    return $this->loadBundleFromConduit(
      $conduit,
      array(
      'revisionIDs' => array($revision_id),
    ));
  }

  private function loadBundleFromConduit(
    ConduitClient $conduit,
    $params) {

    $future = $conduit->callMethod('differential.querydiffs', $params);
    $diff = head($future->resolve());

    if ($diff == null) {
      throw new Exception(
        phutil_console_wrap(
          pht("The diff or revision you specified is either invalid or you ".
          "don't have permission to view it."))
      );
    }

    $changes = array();
    foreach ($diff['changes'] as $changedict) {
      $changes[] = ArcanistDiffChange::newFromDictionary($changedict);
    }
    $bundle = ArcanistBundle::newFromChanges($changes);
    $bundle->setConduit($conduit);
    // since the conduit method has changes, assume that these fields
    // could be unset
    $bundle->setBaseRevision(idx($diff, 'sourceControlBaseRevision'));
    $bundle->setRevisionID(idx($diff, 'revisionID'));
    $bundle->setAuthorName(idx($diff, 'authorName'));
    $bundle->setAuthorEmail(idx($diff, 'authorEmail'));
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
  final protected function getChangedLines($path, $mode) {
    $repository_api = $this->getRepositoryAPI();
    $full_path = $repository_api->getPath($path);
    if (is_dir($full_path)) {
      return null;
    }

    if (!file_exists($full_path)) {
      return null;
    }

    $change = $this->getChange($path);

    if ($change->getFileType() !== ArcanistDiffChangeType::FILE_TEXT) {
      return null;
    }

    $lines = $change->getChangedLines($mode);
    return array_keys($lines);
  }

  final protected function getChange($path) {
    $repository_api = $this->getRepositoryAPI();

    // TODO: Very gross
    $is_git = ($repository_api instanceof ArcanistGitAPI);
    $is_hg = ($repository_api instanceof ArcanistMercurialAPI);
    $is_svn = ($repository_api instanceof ArcanistSubversionAPI);

    if ($is_svn) {
      // NOTE: In SVN, we don't currently support a "get all local changes"
      // operation, so special case it.
      if (empty($this->changeCache[$path])) {
        $diff = $repository_api->getRawDiffText($path);
        $parser = $this->newDiffParser();
        $changes = $parser->parseDiff($diff);
        if (count($changes) != 1) {
          throw new Exception(pht('Expected exactly one change.'));
        }
        $this->changeCache[$path] = reset($changes);
      }
    } else if ($is_git || $is_hg) {
      if (empty($this->changeCache)) {
        $changes = $repository_api->getAllLocalChanges();
        foreach ($changes as $change) {
          $this->changeCache[$change->getCurrentPath()] = $change;
        }
      }
    } else {
      throw new Exception(pht('Missing VCS support.'));
    }

    if (empty($this->changeCache[$path])) {
      if ($is_git || $is_hg) {
        // This can legitimately occur under git/hg if you make a change,
        // "git/hg commit" it, and then revert the change in the working copy
        // and run "arc lint".
        $change = new ArcanistDiffChange();
        $change->setCurrentPath($path);
        return $change;
      } else {
        throw new Exception(
          pht(
            "Trying to get change for unchanged path '%s'!",
            $path));
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
            pht(
              "Option '%s' is not supported under %s.",
              "--{$arg}",
              $system_name).
            $extended_info);
        }
      }
    }
  }

  final protected function normalizeRevisionID($revision_id) {
    return preg_replace('/^D/i', '', $revision_id);
  }

  protected function shouldShellComplete() {
    return true;
  }

  protected function getShellCompletions(array $argv) {
    return array();
  }

  public function getSupportedRevisionControlSystems() {
    return array('git', 'hg', 'svn');
  }

  final protected function getPassthruArgumentsAsMap($command) {
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

  final protected function getPassthruArgumentsAsArgv($command) {
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

  /**
   * Write a message to stderr so that '--json' flags or stdout which is meant
   * to be piped somewhere aren't disrupted.
   *
   * @param string  Message to write to stderr.
   * @return void
   */
  final protected function writeStatusMessage($msg) {
    fwrite(STDERR, $msg);
  }

  final public function writeInfo($title, $message) {
    $this->writeStatusMessage(
      phutil_console_format(
        "<bg:blue>** %s **</bg> %s\n",
        $title,
        $message));
  }

  final public function writeWarn($title, $message) {
    $this->writeStatusMessage(
      phutil_console_format(
        "<bg:yellow>** %s **</bg> %s\n",
        $title,
        $message));
  }

  final public function writeOkay($title, $message) {
    $this->writeStatusMessage(
      phutil_console_format(
        "<bg:green>** %s **</bg> %s\n",
        $title,
        $message));
  }

  final protected function isHistoryImmutable() {
    $repository_api = $this->getRepositoryAPI();

    $config = $this->getConfigFromAnySource('history.immutable');
    if ($config !== null) {
      return $config;
    }

    return $repository_api->isHistoryDefaultImmutable();
  }

  /**
   * Workflows like 'lint' and 'unit' operate on a list of working copy paths.
   * The user can either specify the paths explicitly ("a.js b.php"), or by
   * specifying a revision ("--rev a3f10f1f") to select all paths modified
   * since that revision, or by omitting both and letting arc choose the
   * default relative revision.
   *
   * This method takes the user's selections and returns the paths that the
   * workflow should act upon.
   *
   * @param   list          List of explicitly provided paths.
   * @param   string|null   Revision name, if provided.
   * @param   mask          Mask of ArcanistRepositoryAPI flags to exclude.
   *                        Defaults to ArcanistRepositoryAPI::FLAG_UNTRACKED.
   * @return  list          List of paths the workflow should act on.
   */
  final protected function selectPathsForWorkflow(
    array $paths,
    $rev,
    $omit_mask = null) {

    if ($omit_mask === null) {
      $omit_mask = ArcanistRepositoryAPI::FLAG_UNTRACKED;
    }

    if ($paths) {
      $working_copy = $this->getWorkingCopyIdentity();
      foreach ($paths as $key => $path) {
        $full_path = Filesystem::resolvePath($path);
        if (!Filesystem::pathExists($full_path)) {
          throw new ArcanistUsageException(
            pht(
              "Path '%s' does not exist!",
              $path));
        }
        $relative_path = Filesystem::readablePath(
          $full_path,
          $working_copy->getProjectRoot());
        $paths[$key] = $relative_path;
      }
    } else {
      $repository_api = $this->getRepositoryAPI();

      if ($rev) {
        $this->parseBaseCommitArgument(array($rev));
      }

      $paths = $repository_api->getWorkingCopyStatus();
      foreach ($paths as $path => $flags) {
        if ($flags & $omit_mask) {
          unset($paths[$path]);
        }
      }
      $paths = array_keys($paths);
    }

    return array_values($paths);
  }

  final protected function renderRevisionList(array $revisions) {
    $list = array();
    foreach ($revisions as $revision) {
      $list[] = '     - D'.$revision['id'].': '.$revision['title']."\n";
    }
    return implode('', $list);
  }


/* -(  Scratch Files  )------------------------------------------------------ */


  /**
   * Try to read a scratch file, if it exists and is readable.
   *
   * @param string Scratch file name.
   * @return mixed String for file contents, or false for failure.
   * @task scratch
   */
  final protected function readScratchFile($path) {
    if (!$this->repositoryAPI) {
      return false;
    }
    return $this->getRepositoryAPI()->readScratchFile($path);
  }


  /**
   * Try to read a scratch JSON file, if it exists and is readable.
   *
   * @param string Scratch file name.
   * @return array Empty array for failure.
   * @task scratch
   */
  final protected function readScratchJSONFile($path) {
    $file = $this->readScratchFile($path);
    if (!$file) {
      return array();
    }
    return phutil_json_decode($file);
  }


  /**
   * Try to write a scratch file, if there's somewhere to put it and we can
   * write there.
   *
   * @param  string Scratch file name to write.
   * @param  string Data to write.
   * @return bool   True on success, false on failure.
   * @task scratch
   */
  final protected function writeScratchFile($path, $data) {
    if (!$this->repositoryAPI) {
      return false;
    }
    return $this->getRepositoryAPI()->writeScratchFile($path, $data);
  }


  /**
   * Try to write a scratch JSON file, if there's somewhere to put it and we can
   * write there.
   *
   * @param  string Scratch file name to write.
   * @param  array Data to write.
   * @return bool   True on success, false on failure.
   * @task scratch
   */
  final protected function writeScratchJSONFile($path, array $data) {
    return $this->writeScratchFile($path, json_encode($data));
  }


  /**
   * Try to remove a scratch file.
   *
   * @param   string  Scratch file name to remove.
   * @return  bool    True if the file was removed successfully.
   * @task scratch
   */
  final protected function removeScratchFile($path) {
    if (!$this->repositoryAPI) {
      return false;
    }
    return $this->getRepositoryAPI()->removeScratchFile($path);
  }


  /**
   * Get a human-readable description of the scratch file location.
   *
   * @param string  Scratch file name.
   * @return mixed  String, or false on failure.
   * @task scratch
   */
  final protected function getReadableScratchFilePath($path) {
    if (!$this->repositoryAPI) {
      return false;
    }
    return $this->getRepositoryAPI()->getReadableScratchFilePath($path);
  }


  /**
   * Get the path to a scratch file, if possible.
   *
   * @param string  Scratch file name.
   * @return mixed  File path, or false on failure.
   * @task scratch
   */
  final protected function getScratchFilePath($path) {
    if (!$this->repositoryAPI) {
      return false;
    }
    return $this->getRepositoryAPI()->getScratchFilePath($path);
  }

  final protected function getRepositoryEncoding() {
    return nonempty(
      idx($this->loadProjectRepository(), 'encoding'),
      'UTF-8');
  }

  final protected function loadProjectRepository() {
    list($info, $reasons) = $this->loadRepositoryInformation();
    return coalesce($info, array());
  }

  final protected function newInteractiveEditor($text) {
    $editor = new PhutilInteractiveEditor($text);

    $preferred = $this->getConfigFromAnySource('editor');
    if ($preferred) {
      $editor->setPreferredEditor($preferred);
    }

    return $editor;
  }

  final protected function newDiffParser() {
    $parser = new ArcanistDiffParser();
    if ($this->repositoryAPI) {
      $parser->setRepositoryAPI($this->getRepositoryAPI());
    }
    $parser->setWriteDiffOnFailure(true);
    return $parser;
  }

  final protected function dispatchEvent($type, array $data) {
    $data += array(
      'workflow' => $this,
    );

    $event = new PhutilEvent($type, $data);
    PhutilEventEngine::dispatchEvent($event);

    return $event;
  }

  final public function parseBaseCommitArgument(array $argv) {
    if (!count($argv)) {
      return;
    }

    $api = $this->getRepositoryAPI();
    if (!$api->supportsCommitRanges()) {
      throw new ArcanistUsageException(
        pht('This version control system does not support commit ranges.'));
    }

    if (count($argv) > 1) {
      throw new ArcanistUsageException(
        pht(
          'Specify exactly one base commit. The end of the commit range is '.
          'always the working copy state.'));
    }

    $api->setBaseCommit(head($argv));

    return $this;
  }

  final protected function getRepositoryVersion() {
    if (!$this->repositoryVersion) {
      $api = $this->getRepositoryAPI();
      $commit = $api->getSourceControlBaseRevision();
      $versions = array('' => $commit);
      foreach ($api->getChangedFiles($commit) as $path => $mask) {
        $versions[$path] = (Filesystem::pathExists($path)
          ? md5_file($path)
          : '');
      }
      $this->repositoryVersion = md5(json_encode($versions));
    }
    return $this->repositoryVersion;
  }


/* -(  Phabricator Repositories  )------------------------------------------- */


  /**
   * Get the PHID of the Phabricator repository this working copy corresponds
   * to. Returns `null` if no repository can be identified.
   *
   * @return phid|null  Repository PHID, or null if no repository can be
   *                    identified.
   *
   * @task phabrep
   */
  final protected function getRepositoryPHID() {
    return idx($this->getRepositoryInformation(), 'phid');
  }

  /**
   * Get the name of the Phabricator repository this working copy
   * corresponds to. Returns `null` if no repository can be identified.
   *
   * @return string|null  Repository name, or null if no repository can be
   *                      identified.
   *
   * @task phabrep
   */
  final protected function getRepositoryName() {
    return idx($this->getRepositoryInformation(), 'name');
  }


  /**
   * Get the URI of the Phabricator repository this working copy
   * corresponds to. Returns `null` if no repository can be identified.
   *
   * @return string|null  Repository URI, or null if no repository can be
   *                      identified.
   *
   * @task phabrep
   */
  final protected function getRepositoryURI() {
    return idx($this->getRepositoryInformation(), 'uri');
  }


  final protected function getRepositoryStagingConfiguration() {
    return idx($this->getRepositoryInformation(), 'staging');
  }


  /**
   * Get human-readable reasoning explaining how `arc` evaluated which
   * Phabricator repository corresponds to this working copy. Used by
   * `arc which` to explain the process to users.
   *
   * @return list<string> Human-readable explanation of the repository
   *                      association process.
   *
   * @task phabrep
   */
  final protected function getRepositoryReasons() {
    $this->getRepositoryInformation();
    return $this->repositoryReasons;
  }


  /**
   * @task phabrep
   */
  private function getRepositoryInformation() {
    if ($this->repositoryInfo === null) {
      list($info, $reasons) = $this->loadRepositoryInformation();
      $this->repositoryInfo = nonempty($info, array());
      $this->repositoryReasons = $reasons;
    }

    return $this->repositoryInfo;
  }


  /**
   * @task phabrep
   */
  private function loadRepositoryInformation() {
    list($query, $reasons) = $this->getRepositoryQuery();
    if (!$query) {
      return array(null, $reasons);
    }

    try {
      $method = 'repository.query';
      $results = $this->getConduitEngine()
        ->newFuture($method, $query)
        ->resolve();
    } catch (ConduitClientException $ex) {
      if ($ex->getErrorCode() == 'ERR-CONDUIT-CALL') {
        $reasons[] = pht(
          'This version of Arcanist is more recent than the version of '.
          'Phabricator you are connecting to: the Phabricator install is '.
          'out of date and does not have support for identifying '.
          'repositories by callsign or URI. Update Phabricator to enable '.
          'these features.');
        return array(null, $reasons);
      }
      throw $ex;
    }

    $result = null;
    if (!$results) {
      $reasons[] = pht(
        'No repositories matched the query. Check that your configuration '.
        'is correct, or use "%s" to select a repository explicitly.',
        'repository.callsign');
    } else if (count($results) > 1) {
      $reasons[] = pht(
        'Multiple repostories (%s) matched the query. You can use the '.
        '"%s" configuration to select the one you want.',
        implode(', ', ipull($results, 'callsign')),
        'repository.callsign');
    } else {
      $result = head($results);
      $reasons[] = pht('Found a unique matching repository.');
    }

    return array($result, $reasons);
  }


  /**
   * @task phabrep
   */
  private function getRepositoryQuery() {
    $reasons = array();

    $callsign = $this->getConfigFromAnySource('repository.callsign');
    if ($callsign) {
      $query = array(
        'callsigns' => array($callsign),
      );
      $reasons[] = pht(
        'Configuration value "%s" is set to "%s".',
        'repository.callsign',
        $callsign);
      return array($query, $reasons);
    } else {
      $reasons[] = pht(
        'Configuration value "%s" is empty.',
        'repository.callsign');
    }

    $uuid = $this->getRepositoryAPI()->getRepositoryUUID();
    if ($uuid !== null) {
      $query = array(
        'uuids' => array($uuid),
      );
      $reasons[] = pht(
        'The UUID for this working copy is "%s".',
        $uuid);
      return array($query, $reasons);
    } else {
      $reasons[] = pht(
        'This repository has no VCS UUID (this is normal for git/hg).');
    }

    // TODO: Swap this for a RemoteRefQuery.

    $remote_uri = $this->getRepositoryAPI()->getRemoteURI();
    if ($remote_uri !== null) {
      $query = array(
        'remoteURIs' => array($remote_uri),
      );
      $reasons[] = pht(
        'The remote URI for this working copy is "%s".',
        $remote_uri);
      return array($query, $reasons);
    } else {
      $reasons[] = pht(
        'Unable to determine the remote URI for this repository.');
    }

    return array(null, $reasons);
  }


  /**
   * Build a new lint engine for the current working copy.
   *
   * Optionally, you can pass an explicit engine class name to build an engine
   * of a particular class. Normally this is used to implement an `--engine`
   * flag from the CLI.
   *
   * @param string Optional explicit engine class name.
   * @return ArcanistLintEngine Constructed engine.
   */
  protected function newLintEngine($engine_class = null) {
    $working_copy = $this->getWorkingCopyIdentity();
    $config = $this->getConfigurationManager();

    if (!$engine_class) {
      $engine_class = $config->getConfigFromAnySource('lint.engine');
    }

    if (!$engine_class) {
      if (Filesystem::pathExists($working_copy->getProjectPath('.arclint'))) {
        $engine_class = 'ArcanistConfigurationDrivenLintEngine';
      }
    }

    if (!$engine_class) {
      throw new ArcanistNoEngineException(
        pht(
          "No lint engine is configured for this project. Create an '%s' ".
          "file, or configure an advanced engine with '%s' in '%s'.",
          '.arclint',
          'lint.engine',
          '.arcconfig'));
    }

    $base_class = 'ArcanistLintEngine';
    if (!class_exists($engine_class) ||
        !is_subclass_of($engine_class, $base_class)) {
      throw new ArcanistUsageException(
        pht(
          'Configured lint engine "%s" is not a subclass of "%s", but must be.',
          $engine_class,
          $base_class));
    }

    $engine = newv($engine_class, array())
      ->setWorkingCopy($working_copy)
      ->setConfigurationManager($config);

    return $engine;
  }

  /**
   * Build a new unit test engine for the current working copy.
   *
   * Optionally, you can pass an explicit engine class name to build an engine
   * of a particular class. Normally this is used to implement an `--engine`
   * flag from the CLI.
   *
   * @param string Optional explicit engine class name.
   * @return ArcanistUnitTestEngine Constructed engine.
   */
  protected function newUnitTestEngine($engine_class = null) {
    $working_copy = $this->getWorkingCopyIdentity();
    $config = $this->getConfigurationManager();

    if (!$engine_class) {
      $engine_class = $config->getConfigFromAnySource('unit.engine');
    }

    if (!$engine_class) {
      if (Filesystem::pathExists($working_copy->getProjectPath('.arcunit'))) {
        $engine_class = 'ArcanistConfigurationDrivenUnitTestEngine';
      }
    }

    if (!$engine_class) {
      throw new ArcanistNoEngineException(
        pht(
          "No unit test engine is configured for this project. Create an ".
          "'%s' file, or configure an advanced engine with '%s' in '%s'.",
          '.arcunit',
          'unit.engine',
          '.arcconfig'));
    }

    $base_class = 'ArcanistUnitTestEngine';
    if (!class_exists($engine_class) ||
        !is_subclass_of($engine_class, $base_class)) {
      throw new ArcanistUsageException(
        pht(
          'Configured unit test engine "%s" is not a subclass of "%s", '.
          'but must be.',
          $engine_class,
          $base_class));
    }

    $engine = newv($engine_class, array())
      ->setWorkingCopy($working_copy)
      ->setConfigurationManager($config);

    return $engine;
  }


  protected function openURIsInBrowser(array $uris) {
    $browser = $this->getBrowserCommand();

    // The "browser" may actually be a list of arguments.
    if (!is_array($browser)) {
      $browser = array($browser);
    }

    foreach ($uris as $uri) {
      $err = phutil_passthru('%LR %R', $browser, $uri);
      if ($err) {
        throw new ArcanistUsageException(
          pht(
            'Failed to open URI "%s" in browser ("%s"). '.
            'Check your "browser" config option.',
            $uri,
            implode(' ', $browser)));
      }
    }
  }

  private function getBrowserCommand() {
    $config = $this->getConfigFromAnySource('browser');
    if ($config) {
      return $config;
    }

    if (phutil_is_windows()) {
      // See T13504. We now use "bypass_shell", so "start" alone is no longer
      // a valid binary to invoke directly.
      return array(
        'cmd',
        '/c',
        'start',
      );
    }

    $candidates = array(
      'sensible-browser' => array('sensible-browser'),
      'xdg-open' => array('xdg-open'),
      'open' => array('open', '--'),
    );

    // NOTE: The "open" command works well on OS X, but on many Linuxes "open"
    // exists and is not a browser. For now, we're just looking for other
    // commands first, but we might want to be smarter about selecting "open"
    // only on OS X.

    foreach ($candidates as $cmd => $argv) {
      if (Filesystem::binaryExists($cmd)) {
        return $argv;
      }
    }

    throw new ArcanistUsageException(
      pht(
        "Unable to find a browser command to run. Set '%s' in your ".
        "Arcanist config to specify a command to use.",
        'browser'));
  }


  /**
   * Ask Phabricator to update the current repository as soon as possible.
   *
   * Calling this method after pushing commits allows Phabricator to discover
   * the commits more quickly, so the system overall is more responsive.
   *
   * @return void
   */
  protected function askForRepositoryUpdate() {
    // If we know which repository we're in, try to tell Phabricator that we
    // pushed commits to it so it can update. This hint can help pull updates
    // more quickly, especially in rarely-used repositories.
    if ($this->getRepositoryPHID()) {
      try {
        $this->getConduit()->callMethodSynchronous(
          'diffusion.looksoon',
          array(
            'repositories' => array($this->getRepositoryPHID()),
          ));
      } catch (ConduitClientException $ex) {
        // If we hit an exception, just ignore it. Likely, we are running
        // against a Phabricator which is too old to support this method.
        // Since this hint is purely advisory, it doesn't matter if it has
        // no effect.
      }
    }
  }

  protected function getModernLintDictionary(array $map) {
    $map = $this->getModernCommonDictionary($map);
    return $map;
  }

  protected function getModernUnitDictionary(array $map) {
    $map = $this->getModernCommonDictionary($map);

    $details = idx($map, 'userData');
    if (strlen($details)) {
      $map['details'] = (string)$details;
    }
    unset($map['userData']);

    return $map;
  }

  private function getModernCommonDictionary(array $map) {
    foreach ($map as $key => $value) {
      if ($value === null) {
        unset($map[$key]);
      }
    }
    return $map;
  }

  final public function setConduitEngine(
    ArcanistConduitEngine $conduit_engine) {
    $this->conduitEngine = $conduit_engine;
    return $this;
  }

  final public function getConduitEngine() {
    return $this->conduitEngine;
  }

  final public function getRepositoryRef() {
    $configuration_engine = $this->getConfigurationEngine();
    if ($configuration_engine) {
      // This is a toolset workflow and can always build a repository ref.
    } else {
      if (!$this->getConfigurationManager()->getWorkingCopyIdentity()) {
        return null;
      }

      if (!$this->repositoryAPI) {
        return null;
      }
    }

    if (!$this->repositoryRef) {
      $ref = id(new ArcanistRepositoryRef())
        ->setPHID($this->getRepositoryPHID())
        ->setBrowseURI($this->getRepositoryURI());

      $this->repositoryRef = $ref;
    }

    return $this->repositoryRef;
  }

  final public function getToolsetKey() {
    return $this->getToolset()->getToolsetKey();
  }

  final public function getConfig($key) {
    return $this->getConfigurationSourceList()->getConfig($key);
  }

  public function canHandleSignal($signo) {
    return false;
  }

  public function handleSignal($signo) {
    return;
  }

  final public function newCommand(PhutilExecutableFuture $future) {
    return id(new ArcanistCommand())
      ->setLogEngine($this->getLogEngine())
      ->setExecutableFuture($future);
  }

  final public function loadHardpoints(
    $objects,
    $requests) {
    return $this->getRuntime()->loadHardpoints($objects, $requests);
  }

  protected function newPrompts() {
    return array();
  }

  protected function newPrompt($key) {
    return id(new ArcanistPrompt())
      ->setWorkflow($this)
      ->setKey($key);
  }

  public function hasPrompt($key) {
    $map = $this->getPromptMap();
    return isset($map[$key]);
  }

  public function getPromptMap() {
    if ($this->promptMap === null) {
      $prompts = $this->newPrompts();
      assert_instances_of($prompts, 'ArcanistPrompt');

      // TODO: Move this somewhere modular.

      $prompts[] = $this->newPrompt('arc.state.stash')
        ->setDescription(
          pht(
            'Prompts the user to stash changes and continue when the '.
            'working copy has untracked, uncommitted, or unstaged '.
            'changes.'));

      // TODO: Swap to ArrayCheck?

      $map = array();
      foreach ($prompts as $prompt) {
        $key = $prompt->getKey();

        if (isset($map[$key])) {
          throw new Exception(
            pht(
              'Workflow ("%s") generates two prompts with the same '.
              'key ("%s"). Each prompt a workflow generates must have a '.
              'unique key.',
              get_class($this),
              $key));
        }

        $map[$key] = $prompt;
      }

      $this->promptMap = $map;
    }

    return $this->promptMap;
  }

  final public function getPrompt($key) {
    $map = $this->getPromptMap();

    $prompt = idx($map, $key);
    if (!$prompt) {
      throw new Exception(
        pht(
          'Workflow ("%s") is requesting a prompt ("%s") but it did not '.
          'generate any prompt with that name in "newPrompts()".',
          get_class($this),
          $key));
    }

    return clone $prompt;
  }

  final protected function getSymbolEngine() {
    return $this->getRuntime()->getSymbolEngine();
  }

  final protected function getViewer() {
    return $this->getRuntime()->getViewer();
  }

  final protected function readStdin() {
    $log = $this->getLogEngine();
    $log->writeWaitingForInput();

    // NOTE: We can't just "file_get_contents()" here because signals don't
    // interrupt it. If the user types "^C", we want to interrupt the read.

    $raw_handle = fopen('php://stdin', 'rb');
    $stdin = new PhutilSocketChannel($raw_handle);

    while ($stdin->update()) {
      PhutilChannel::waitForAny(array($stdin));
    }

    return $stdin->read();
  }

  final public function getAbsoluteURI($raw_uri) {
    // TODO: "ArcanistRevisionRef", at least, may return a relative URI.
    // If we get a relative URI, guess the correct absolute URI based on
    // the Conduit URI. This might not be correct for Conduit over SSH.

    $raw_uri = new PhutilURI($raw_uri);
    if (!strlen($raw_uri->getDomain())) {
      $base_uri = $this->getConduitEngine()
        ->getConduitURI();

      $raw_uri = id(new PhutilURI($base_uri))
        ->setPath($raw_uri->getPath());
    }

    $raw_uri = phutil_string_cast($raw_uri);

    return $raw_uri;
  }

  final public function writeToPager($corpus) {
    $is_tty = (function_exists('posix_isatty') && posix_isatty(STDOUT));

    if (!$is_tty) {
      echo $corpus;
    } else {
      $pager = $this->getConfig('pager');

      if (!$pager) {
        $pager = array('less', '-R', '--');
      }

      // Try to show the content through a pager.
      $err = id(new PhutilExecPassthru('%Ls', $pager))
        ->write($corpus)
        ->resolve();

      // If the pager exits with an error, print the content normally.
      if ($err) {
        echo $corpus;
      }
    }

    return $this;
  }

}
