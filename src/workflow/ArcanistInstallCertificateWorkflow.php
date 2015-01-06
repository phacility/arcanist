<?php

/**
 * Installs arcanist certificates.
 */
final class ArcanistInstallCertificateWorkflow extends ArcanistWorkflow {

  public function getWorkflowName() {
    return 'install-certificate';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **install-certificate** [uri]
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          Supports: http, https
          Installs Conduit credentials into your ~/.arcrc for the given install
          of Phabricator. You need to do this before you can use 'arc', as it
          enables 'arc' to link your command-line activity with your account on
          the web. Run this command from within a project directory to install
          that project's certificate, or specify an explicit URI (like
          "https://phabricator.example.com/").
EOTEXT
      );
  }

  public function getArguments() {
    return array(
      '*' => 'uri',
    );
  }

  protected function shouldShellComplete() {
    return false;
  }

  public function requiresConduit() {
    return false;
  }

  public function requiresWorkingCopy() {
    return false;
  }

  public function run() {
    $console = PhutilConsole::getConsole();

    $uri = $this->determineConduitURI();
    $this->setConduitURI($uri);
    $configuration_manager = $this->getConfigurationManager();

    $config = $configuration_manager->readUserConfigurationFile();

    $console->writeOut(
      "%s\n",
      pht('Trying to connect to server...'));

    $conduit = $this->establishConduit()->getConduit();
    try {
      $conduit->callMethodSynchronous('conduit.ping', array());
    } catch (Exception $ex) {
      throw new ArcanistUsageException(
        pht(
          'Failed to connect to server (%s): %s',
          $uri,
          $ex->getMessage()));
    }

    $token_uri = new PhutilURI($uri);
    $token_uri->setPath('/conduit/token/');

    // Check if this server supports the more modern token-based login.
    $is_token_auth = false;
    try {
      $capabilities = $conduit->callMethodSynchronous(
        'conduit.getcapabilities',
        array());
      $auth = idx($capabilities, 'authentication', array());
      if (in_array('token', $auth)) {
        $token_uri->setPath('/conduit/login/');
        $is_token_auth = true;
      }
    } catch (Exception $ex) {
      // Ignore.
    }

    echo phutil_console_format("**LOGIN TO PHABRICATOR**\n");
    echo "Open this page in your browser and login to Phabricator if ".
         "necessary:\n";
    echo "\n";
    echo "    {$token_uri}\n";
    echo "\n";
    echo 'Then paste the API Token on that page below.';


    do {
      $token = phutil_console_prompt('Paste API Token from that page:');
      $token = trim($token);
      if (strlen($token)) {
        break;
      }
    } while (true);

    if ($is_token_auth) {
      if (strlen($token) != 32) {
        throw new ArcanistUsageException(
          pht(
            'The token "%s" is not formatted correctly. API tokens should '.
            'be 32 characters long. Make sure you visited the correct URI '.
            'and copy/pasted the token correctly.',
            $token));
      }

      if (strncmp($token, 'cli-', 4) !== 0) {
        throw new ArcanistUsageException(
          pht(
            'The token "%s" is not formatted correctly. Valid API tokens '.
            'should begin "cli-" and be 32 characters long. Make sure you '.
            'visited the correct URI and copy/pasted the token correctly.',
            $token));
      }

      $conduit->setConduitToken($token);
      try {
        $conduit->callMethodSynchronous('user.whoami', array());
      } catch (Exception $ex) {
        throw new ArcanistUsageException(
          pht(
            'The token "%s" is not a valid API Token. The server returned '.
            'this response when trying to use it as a token: %s',
            $token,
            $ex->getMessage()));
      }

      $config['hosts'][$uri] = array(
        'token' => $token,
      );
    } else {
      echo "\n";
      echo "Downloading authentication certificate...\n";
      $info = $conduit->callMethodSynchronous(
        'conduit.getcertificate',
        array(
          'token' => $token,
          'host'  => $uri,
        ));

      $user = $info['username'];
      echo "Installing certificate for '{$user}'...\n";
      $config['hosts'][$uri] = array(
        'user' => $user,
        'cert' => $info['certificate'],
      );
    }

    echo "Writing ~/.arcrc...\n";
    $configuration_manager->writeUserConfigurationFile($config);

    if ($is_token_auth) {
      echo phutil_console_format(
        "<bg:green>** SUCCESS! **</bg> API Token installed.\n");
    } else {
      echo phutil_console_format(
        "<bg:green>** SUCCESS! **</bg> Certificate installed.\n");
    }

    return 0;
  }

  private function determineConduitURI() {
    $uri = $this->getArgument('uri');
    if (count($uri) > 1) {
      throw new ArcanistUsageException('Specify at most one URI.');
    } else if (count($uri) == 1) {
      $uri = reset($uri);
    } else {
      $conduit_uri = $this->getConduitURI();
      if (!$conduit_uri) {
        throw new ArcanistUsageException(
          'Specify an explicit URI or run this command from within a project '.
          'which is configured with a .arcconfig.');
      }
      $uri = $conduit_uri;
    }

    $uri = new PhutilURI($uri);
    $uri->setPath('/api/');

    return (string)$uri;
  }

}
