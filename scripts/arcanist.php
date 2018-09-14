#!/usr/bin/env php
<?php

if (function_exists('pcntl_async_signals')) {
  pcntl_async_signals(true);
} else {
  declare(ticks = 1);
}

require_once dirname(dirname(__FILE__)).'/scripts/init/init-arcanist.php';

$runtime = new ArcanistRuntime();
return $runtime->execute($argv);


$config_trace_mode = $base_args->getArg('trace');

$force_conduit = $base_args->getArg('conduit-uri');
$force_token = $base_args->getArg('conduit-token');
$is_anonymous = $base_args->getArg('anonymous');
$load = $base_args->getArg('load-phutil-library');
$help = $base_args->getArg('help');
$args = array_values($base_args->getUnconsumedArgumentVector());

$console = PhutilConsole::getConsole();
$config = null;
$workflow = null;

try {

  $command = strtolower($args[0]);
  $args = array_slice($args, 1);
  $workflow = $config->selectWorkflow(
    $command,
    $args,
    $configuration_manager,
    $console);
  $workflow->setConfigurationManager($configuration_manager);
  $workflow->setArcanistConfiguration($config);
  $workflow->setCommand($command);
  $workflow->setWorkingDirectory($working_directory);
  $workflow->parseArguments($args);

  // Write the command into the environment so that scripts (for example, local
  // Git commit hooks) can detect that they're being run via `arc` and change
  // their behaviors.
  putenv('ARCANIST='.$command);

  $need_working_copy = $workflow->requiresWorkingCopy();

  $supported_vcs_types = $workflow->getSupportedRevisionControlSystems();
  $vcs_type = $working_copy->getVCSType();
  if ($vcs_type || $need_working_copy) {
    if (!in_array($vcs_type, $supported_vcs_types)) {
      throw new ArcanistUsageException(
        pht(
          '`%s %s` is only supported under %s.',
          'arc',
          $workflow->getWorkflowName(),
          implode(', ', $supported_vcs_types)));
    }
  }

  $need_conduit         = $workflow->requiresConduit();
  $need_auth            = $workflow->requiresAuthentication();
  $need_repository_api  = $workflow->requiresRepositoryAPI();

  $want_repository_api = $workflow->desiresRepositoryAPI();
  $want_working_copy = $workflow->desiresWorkingCopy() ||
                       $want_repository_api;

  $need_conduit       = $need_conduit ||
                        $need_auth;
  $need_working_copy  = $need_working_copy ||
                        $need_repository_api;

  if ($need_working_copy || $want_working_copy) {
    if ($need_working_copy && !$working_copy->getVCSType()) {
      throw new ArcanistUsageException(
        pht(
          'This command must be run in a Git, Mercurial or Subversion '.
          'working copy.'));
    }
    $configuration_manager->setWorkingCopyIdentity($working_copy);
  }

  if ($force_conduit) {
    $conduit_uri = $force_conduit;
  } else {
    $conduit_uri = $configuration_manager->getConfigFromAnySource(
      'phabricator.uri');
    if ($conduit_uri === null) {
      $conduit_uri = $configuration_manager->getConfigFromAnySource('default');
    }
  }

  if ($conduit_uri) {
    // Set the URI path to '/api/'. TODO: Originally, I contemplated letting
    // you deploy Phabricator somewhere other than the domain root, but ended
    // up never pursuing that. We should get rid of all "/api/" silliness
    // in things users are expected to configure. This is already happening
    // to some degree, e.g. "arc install-certificate" does it for you.
    $conduit_uri = new PhutilURI($conduit_uri);
    $conduit_uri->setPath('/api/');
    $conduit_uri = (string)$conduit_uri;
  }
  $workflow->setConduitURI($conduit_uri);

  // Apply global CA bundle from configs.
  $ca_bundle = $configuration_manager->getConfigFromAnySource('https.cabundle');
  if ($ca_bundle) {
    $ca_bundle = Filesystem::resolvePath(
      $ca_bundle, $working_copy->getProjectRoot());
    HTTPSFuture::setGlobalCABundleFromPath($ca_bundle);
  }

  $blind_key = 'https.blindly-trust-domains';
  $blind_trust = $configuration_manager->getConfigFromAnySource($blind_key);
  if ($blind_trust) {
    $trust_extension = PhutilHTTPEngineExtension::requireExtension(
      ArcanistBlindlyTrustHTTPEngineExtension::EXTENSIONKEY);
    $trust_extension->setDomains($blind_trust);
  }

  if ($need_conduit) {
    if (!$conduit_uri) {

      $message = phutil_console_format(
        "%s\n\n  - %s\n  - %s\n  - %s\n",
        pht(
          'This command requires arc to connect to a Phabricator install, '.
          'but no Phabricator installation is configured. To configure a '.
          'Phabricator URI:'),
        pht(
          'set a default location with `%s`; or',
          'arc set-config default <uri>'),
        pht(
          'specify `%s` explicitly; or',
          '--conduit-uri=uri'),
        pht(
          "run `%s` in a working copy with an '%s'.",
          'arc',
          '.arcconfig'));

      $message = phutil_console_wrap($message);
      throw new ArcanistUsageException($message);
    }
    $workflow->establishConduit();
  }

  $hosts_config = idx($user_config, 'hosts', array());
  $host_config = idx($hosts_config, $conduit_uri, array());
  $user_name = idx($host_config, 'user');
  $certificate = idx($host_config, 'cert');
  $conduit_token = idx($host_config, 'token');
  if ($force_token) {
    $conduit_token = $force_token;
  }

  if ($is_anonymous) {
    $conduit_token = null;
  }

  $description = implode(' ', $original_argv);
  $credentials = array(
    'user' => $user_name,
    'certificate' => $certificate,
    'description' => $description,
    'token' => $conduit_token,
  );
  $workflow->setConduitCredentials($credentials);

  $basic_user = $configuration_manager->getConfigFromAnySource(
    'http.basicauth.user');
  $basic_pass = $configuration_manager->getConfigFromAnySource(
    'http.basicauth.pass');

  $engine = id(new ArcanistConduitEngine())
    ->setConduitURI($conduit_uri)
    ->setConduitToken($conduit_token)
    ->setBasicAuthUser($basic_user)
    ->setBasicAuthPass($basic_pass);

  $workflow->setConduitEngine($engine);

  if ($need_auth) {
    if ((!$user_name || !$certificate) && (!$conduit_token)) {
      $arc = 'arc';
      if ($force_conduit) {
        $arc .= csprintf(' --conduit-uri=%s', $conduit_uri);
      }

      $conduit_domain = id(new PhutilURI($conduit_uri))->getDomain();

      throw new ArcanistUsageException(
        phutil_console_format(
          "%s\n\n%s\n\n%s **%s:**\n\n      $ **{$arc} install-certificate**\n",
          pht('YOU NEED TO AUTHENTICATE TO CONTINUE'),
          pht(
            'You are trying to connect to a server (%s) that you '.
            'do not have any credentials stored for.',
            $conduit_domain),
          pht('To retrieve and store credentials for this server,'),
          pht('run this command')));
    }
    $workflow->authenticateConduit();
  }

  if ($need_repository_api ||
      ($want_repository_api && $working_copy->getVCSType())) {
    $repository_api = ArcanistRepositoryAPI::newAPIFromConfigurationManager(
      $configuration_manager);
    $workflow->setRepositoryAPI($repository_api);
  }

  $listeners = $configuration_manager->getConfigFromAnySource(
    'events.listeners');
  if ($listeners) {
    foreach ($listeners as $listener) {
      $console->writeLog(
        "%s\n",
        pht("Registering event listener '%s'.", $listener));
      try {
        id(new $listener())->register();
      } catch (PhutilMissingSymbolException $ex) {
        // Continue anyway, since you may otherwise be unable to run commands
        // like `arc set-config events.listeners` in order to repair the damage
        // you've caused. We're writing out the entire exception here because
        // it might not have been triggered by the listener itself (for example,
        // the listener might use a bad class in its register() method).
        $console->writeErr(
          "%s\n",
          pht(
            "ERROR: Failed to load event listener '%s': %s",
            $listener,
            $ex->getMessage()));
      }
    }
  }

  $config->willRunWorkflow($command, $workflow);
  $workflow->willRunWorkflow();
  try {
    $err = $workflow->run();
    $config->didRunWorkflow($command, $workflow, $err);
  } catch (Exception $e) {
    $workflow->finalize();
    throw $e;
  }
  $workflow->finalize();
  exit((int)$err);

} catch (ArcanistNoEffectException $ex) {
  echo $ex->getMessage()."\n";

} catch (Exception $ex) {
  $is_usage = ($ex instanceof ArcanistUsageException);
  if ($is_usage) {
    fwrite(STDERR, phutil_console_format(
      "**%s** %s\n",
      pht('Usage Exception:'),
      rtrim($ex->getMessage())));
  }

  if ($config) {
    $config->didAbortWorkflow($command, $workflow, $ex);
  }

  if ($config_trace_mode) {
    fwrite(STDERR, "\n");
    throw $ex;
  }

  if (!$is_usage) {
    fwrite(STDERR, phutil_console_format(
      "<bg:red>** %s **</bg>\n", pht('Exception')));

    while ($ex) {
      fwrite(STDERR, $ex->getMessage()."\n");

      if ($ex instanceof PhutilProxyException) {
        $ex = $ex->getPreviousException();
      } else {
        $ex = null;
      }
    }

    fwrite(STDERR, phutil_console_format(
      "(%s)\n",
      pht('Run with `%s` for a full exception trace.', '--trace')));
  }

  exit(1);
}





