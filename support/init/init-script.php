<?php

function __arcanist_init_script__() {
  // Adjust the runtime language configuration to be reasonable and inline with
  // expectations. We do this first, then load libraries.

  // There may be some kind of auto-prepend script configured which starts an
  // output buffer. Discard any such output buffers so messages can be sent to
  // stdout (if a user wants to capture output from a script, there are a large
  // number of ways they can accomplish it legitimately; historically, we ran
  // into this on only one install which had some bizarre configuration, but it
  // was difficult to diagnose because the symptom is "no messages of any
  // kind").
  while (ob_get_level() > 0) {
    ob_end_clean();
  }

  error_reporting(E_ALL | E_STRICT);

  $config_map = array(
    // Always display script errors. Without this, they may not appear, which is
    // unhelpful when users encounter a problem. On the web this is a security
    // concern because you don't want to expose errors to clients, but in a
    // script context we always want to show errors.
    'display_errors'              => true,

    // Send script error messages to the server's `error_log` setting.
    'log_errors'                  => true,

    // Set the error log to the default, so errors go to stderr. Without this
    // errors may end up in some log, and users may not know where the log is
    // or check it.
    'error_log'                   => null,

    // XDebug raises a fatal error if the call stack gets too deep, but the
    // default setting is 100, which we may exceed legitimately with module
    // includes (and in other cases, like recursive filesystem operations
    // applied to 100+ levels of directory nesting). Stop it from triggering:
    // we explicitly limit recursive algorithms which should be limited.
    //
    // After Feb 2014, XDebug interprets a value of 0 to mean "do not allow any
    // function calls". Previously, 0 effectively disabled this check. For
    // context, see T5027.
    'xdebug.max_nesting_level'    => PHP_INT_MAX,

    // Don't limit memory, doing so just generally just prevents us from
    // processing large inputs without many tangible benefits.
    'memory_limit'                => -1,

    // See T13296. On macOS under PHP 7.3.x, PCRE currently segfaults after
    // "fork()" if "pcre.jit" is enabled.
    'pcre.jit' => 0,

    // See PHI1894. This option was introduced in PHP 7.4, and removes the
    // "args" value from exception backtraces. We have some unit tests which
    // inspect "args", and this option generally obscures useful debugging
    // information without any benefit in the context of Phabricator.
    'zend.exception_ignore_args' => 0,

    // See T13100. We'd like the regex engine to fail, rather than segfault,
    // if handed a pathological regular expression.
    'pcre.backtrack_limit' => 10000,
    'pcre.recusion_limit' => 10000,

    // NOTE: Phabricator applies a similar set of startup options for Web
    // environments in "PhabricatorStartup". Changes here may also be
    // appropriate to apply there.
  );

  foreach ($config_map as $config_key => $config_value) {
    ini_set($config_key, $config_value);
  }

  $php_version = phpversion();
  $min_version = '5.5.0';
  if (version_compare($php_version, $min_version, '<')) {
    echo sprintf(
      'UPGRADE PHP: '.
      'The installed version of PHP ("%s") is too old to run Arcanist. '.
      'Update PHP to at least the minimum required version ("%s").',
      $php_version,
      $min_version);
    echo "\n";

    exit(1);
  }

  if (!ini_get('date.timezone')) {
    // If the timezone isn't set, PHP issues a warning whenever you try to parse
    // a date (like those from Git or Mercurial logs), even if the date contains
    // timezone information (like "PST" or "-0700") which makes the
    // environmental timezone setting is completely irrelevant. We never rely on
    // the system timezone setting in any capacity, so prevent PHP from flipping
    // out by setting it to a safe default (UTC) if it isn't set to some other
    // value.
    date_default_timezone_set('UTC');
  }

  // Adjust `include_path`.
  ini_set('include_path', implode(PATH_SEPARATOR, array(
    dirname(dirname(__FILE__)).'/externals/includes',
    ini_get('include_path'),
  )));

  // Disable the insanely dangerous XML entity loader by default.
  // PHP 8 deprecates this function and disables this by default; remove once
  // PHP 7 is no longer supported or a future version has removed the function
  // entirely.
  if (function_exists('libxml_disable_entity_loader')) {
    @libxml_disable_entity_loader(true);
  }

  $root = dirname(dirname(dirname(__FILE__)));
  require_once $root.'/src/init/init-library.php';

  PhutilErrorHandler::initialize();

  // If "variables_order" excludes "E", silently repair it so that $_ENV has
  // the values we expect.
  PhutilExecutionEnvironment::repairMissingVariablesOrder();

  $router = PhutilSignalRouter::initialize();

  $handler = new PhutilBacktraceSignalHandler();
  $router->installHandler('phutil.backtrace', $handler);

  $handler = new PhutilConsoleMetricsSignalHandler();
  $router->installHandler('phutil.winch', $handler);
}

__arcanist_init_script__();
