<?php

final class ArcanistPrompt
  extends Phobject {

  private $key;
  private $workflow;
  private $description;
  private $query;

  public function setKey($key) {
    $this->key = $key;
    return $this;
  }

  public function getKey() {
    return $this->key;
  }

  public function setWorkflow(ArcanistWorkflow $workflow) {
    $this->workflow = $workflow;
    return $this;
  }

  public function getWorkflow() {
    return $this->workflow;
  }

  public function setDescription($description) {
    $this->description = $description;
    return $this;
  }

  public function getDescription() {
    return $this->description;
  }

  public function setQuery($query) {
    $this->query = $query;
    return $this;
  }

  public function getQuery() {
    return $this->query;
  }

  public function execute() {
    $workflow = $this->getWorkflow();
    if ($workflow) {
      $workflow_ok = $workflow->hasPrompt($this->getKey());
    } else {
      $workflow_ok = false;
    }

    if (!$workflow_ok) {
      throw new Exception(
        pht(
          'Prompt ("%s") is executing, but it is not properly bound to the '.
          'invoking workflow. You may have called "newPrompt()" to execute a '.
          'prompt instead of "getPrompt()". Use "newPrompt()" when defining '.
          'prompts and "getPrompt()" when executing them.',
          $this->getKey()));
    }

    $query = $this->getQuery();
    if (!strlen($query)) {
      throw new Exception(
        pht(
          'Prompt ("%s") has no query text!',
          $this->getKey()));
    }

    $options = '[y/N/?]';
    $default = 'n';

    $saved_response = $this->getSavedResponse();

    try {
      phutil_console_require_tty();
    } catch (PhutilConsoleStdinNotInteractiveException $ex) {
      // TOOLSETS: Clean this up to provide more details to the user about how
      // they can configure prompts to be answered.

      // Throw after echoing the prompt so the user has some idea what happened.
      echo $query."\n";
      throw $ex;
    }

    $stdin = fopen('php://stdin', 'r');
    if (!$stdin) {
      throw new Exception(pht('Failed to open stdin for reading.'));
    }

    // NOTE: We're making stdin nonblocking so that we can respond to signals
    // immediately. If we don't, and you ^C during a prompt, the program does
    // not handle the signal until fgets() returns. See also T13649.

    $guard = ArcanistNonblockingGuard::newForStream($stdin);

    echo "\n";

    $result = null;
    $is_saved = false;
    while (true) {
      if ($saved_response !== null) {
        $is_saved = true;

        $response = $saved_response;
        $saved_response = null;
      } else {
        echo tsprintf(
          '**<bg:cyan> %s </bg>** %s %s ',
          '>>>',
          $query,
          $options);

        $is_saved = false;

        if (!$guard->getIsNonblocking()) {
          $response = fgets($stdin);
        } else {
          while (true) {
            $read = array($stdin);
            $write = array();
            $except = array();

            $ok = @stream_select($read, $write, $except, 1);
            if ($ok === false) {
              // NOTE: We may be interrupted by a system call, particularly if
              // the window is resized while a prompt is shown and the terminal
              // sends SIGWINCH.

              // If we are, just continue below and try to read from stdin. If
              // we were interrupted, we should read nothing and continue
              // normally. If the pipe is broken, the read should fail.
            }

            $response = '';
            while (true) {
              $bytes = fread($stdin, 8192);
              if ($bytes === false) {
                throw new Exception(
                  pht('fread() from stdin failed with an error.'));
              }

              if (!strlen($bytes)) {
                break;
              }

              $response .= $bytes;
            }

            if (!strlen($response)) {
              continue;
            }

            break;
          }
        }

        $response = trim($response);
        if (!strlen($response)) {
          $response = $default;
        }
      }

      $save_scope = null;
      if (!$is_saved) {
        $matches = null;
        if (preg_match('(^(.*)([!*])\z)', $response, $matches)) {
          $response = $matches[1];

          if ($matches[2] === '*') {
            $save_scope = ArcanistConfigurationSource::SCOPE_USER;
          } else {
            $save_scope = ArcanistConfigurationSource::SCOPE_WORKING_COPY;
          }
        }
      }

      if (phutil_utf8_strtolower($response) == 'y') {
        $result = true;
        break;
      }

      if (phutil_utf8_strtolower($response) == 'n') {
        $result = false;
        break;
      }

      if (phutil_utf8_strtolower($response) == '?') {
        echo tsprintf(
          "\n<bg:green>** %s **</bg> **%s**\n\n",
          pht('PROMPT'),
          $this->getKey());

        echo tsprintf(
          "%s\n",
          $this->getDescription());

        echo tsprintf("\n");

        echo tsprintf(
          "%s\n",
          pht(
            'The default response to this prompt is "%s".',
            $default));

        echo tsprintf("\n");

        echo tsprintf(
          "%?\n",
          pht(
            'Use "*" after a response to save it in user configuration.'));

        echo tsprintf(
          "%?\n",
          pht(
            'Use "!" after a response to save it in working copy '.
            'configuration.'));

        echo tsprintf(
          "%?\n",
          pht(
            'Run "arc help prompts" for detailed help on configuring '.
            'responses.'));

        echo tsprintf("\n");

        continue;
      }
    }

    if ($save_scope !== null) {
      $this->saveResponse($save_scope, $response);
    }

    if ($is_saved) {
      echo tsprintf(
        "<bg:cyan>** %s **</bg> %s **<%s>**\n".
        "<bg:cyan>** %s **</bg> (%s)\n\n",
        '>>>',
        $query,
        $response,
        '>>>',
        pht(
          'Using saved response to prompt "%s".',
          $this->getKey()));
    }

    if (!$result) {
      throw new ArcanistUserAbortException();
    }
  }

  private function getSavedResponse() {
    $config_key = ArcanistArcConfigurationEngineExtension::KEY_PROMPTS;
    $workflow = $this->getWorkflow();

    $config = $workflow->getConfig($config_key);

    $prompt_key = $this->getKey();

    $prompt_response = null;
    foreach ($config as $response) {
      if ($response->getPrompt() === $prompt_key) {
        $prompt_response = $response;
      }
    }

    if ($prompt_response === null) {
      return null;
    }

    return $prompt_response->getResponse();
  }

  private function saveResponse($scope, $response_value) {
    $config_key = ArcanistArcConfigurationEngineExtension::KEY_PROMPTS;
    $workflow = $this->getWorkflow();

    echo tsprintf(
      "<bg:green>** %s **</bg> %s\n",
      pht('SAVE PROMPT'),
      pht(
        'Saving response "%s" to prompt "%s".',
        $response_value,
        $this->getKey()));

    $source_list = $workflow->getConfigurationSourceList();
    $source = $source_list->getWritableSourceFromScope($scope);

    $response_list = $source_list->getConfigFromScopes(
      $config_key,
      array($scope));

    foreach ($response_list as $key => $response) {
      if ($response->getPrompt() === $this->getKey()) {
        unset($response_list[$key]);
      }
    }

    if ($response_value !== null) {
      $response_list[] = id(new ArcanistPromptResponse())
        ->setPrompt($this->getKey())
        ->setResponse($response_value);
    }

    $option = $source_list->getConfigOption($config_key);
    $option->writeValue($source, $response_list);
  }

}
