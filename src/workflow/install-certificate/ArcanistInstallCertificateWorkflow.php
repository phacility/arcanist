<?php

/*
 * Copyright 2012 Facebook, Inc.
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
 * Installs arcanist certificates.
 *
 * @group workflow
 */
final class ArcanistInstallCertificateWorkflow extends ArcanistBaseWorkflow {

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

  public function shouldShellComplete() {
    return false;
  }

  public function requiresConduit() {
    return false;
  }

  public function requiresWorkingCopy() {
    return false;
  }

  public function run() {

    $uri = $this->determineConduitURI();

    echo "Installing certificate for '{$uri}'...\n";

    $config = self::readUserConfigurationFile();

    echo "Trying to connect to server...\n";
    $conduit = new ConduitClient($uri);
    try {
      $conduit->callMethodSynchronous('conduit.ping', array());
    } catch (Exception $ex) {
      throw new ArcanistUsageException(
        "Failed to connect to server: ".$ex->getMessage());
    }
    echo "Connection OK!\n";

    $token_uri = new PhutilURI($uri);
    $token_uri->setPath('/conduit/token/');

    echo "\n";
    echo phutil_console_format("**LOGIN TO PHABRICATOR**\n");
    echo "Open this page in your browser and login to Phabricator if ".
         "necessary:\n";
    echo "\n";
    echo "    {$token_uri}\n";
    echo "\n";
    echo "Then paste the token on that page below.";


    do {
      $token = phutil_console_prompt('Paste token from that page:');
      $token = trim($token);
      if (strlen($token)) {
        break;
      }
    } while (true);

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

    echo "Writing ~/.arcrc...\n";
    self::writeUserConfigurationFile($config);

    echo phutil_console_format(
      "<bg:green>** SUCCESS! **</bg> Certificate installed.\n");

    return 0;
  }

  private function determineConduitURI() {
    $uri = $this->getArgument('uri');
    if (count($uri) > 1) {
      throw new ArcanistUsageException("Specify at most one URI.");
    } else if (count($uri) == 1) {
      $uri = reset($uri);
    } else {
      $conduit_uri = $this->getConduitURI();
      if (!$conduit_uri) {
        throw new ArcanistUsageException(
          "Specify an explicit URI or run this command from within a project ".
          "which is configured with a .arcconfig.");
      }
      $uri = $conduit_uri;
    }

    $uri = new PhutilURI($uri);
    $uri->setPath('/api/');

    return (string)$uri;
  }

}
