<?php

/**
 * List available linters.
 */
final class ArcanistLintersWorkflow extends ArcanistWorkflow {

  public function getWorkflowName() {
    return 'linters';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **linters** [__options__]
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(pht(<<<EOTEXT
          Supports: cli
          List the available and configured linters, with information about
          what they do and which versions are installed.
EOTEXT
      ));
  }

  public function getArguments() {
    return array(
      'verbose' => array(
        'help' => pht('Show detailed information, including options.'),
      ),
    );
  }

  public function run() {
    $console = PhutilConsole::getConsole();

    $linters = id(new PhutilSymbolLoader())
      ->setAncestorClass('ArcanistLinter')
      ->loadObjects();

    try {
      $built = $this->newLintEngine()->buildLinters();
    } catch (ArcanistNoEngineException $ex) {
      $built = array();
    }

    // Note that an engine can emit multiple linters of the same class to run
    // different rulesets on different groups of files, so these linters do not
    // necessarily have unique classes or types.
    $groups = array();
    foreach ($built as $linter) {
      $groups[get_class($linter)][] = $linter;
    }

    $linter_info = array();
    foreach ($linters as $key => $linter) {
      $installed = idx($groups, $key, array());
      $exception = null;

      if ($installed) {
        $status = 'configured';
        try {
          $version = head($installed)->getVersion();
        } catch (Exception $ex) {
          $status = 'error';
          $exception = $ex;
        }
      } else {
        $status = 'available';
        $version = null;
      }

      $linter_info[$key] = array(
        'short' => $linter->getLinterConfigurationName(),
        'class' => get_class($linter),
        'status' => $status,
        'version' => $version,
        'name' => $linter->getInfoName(),
        'uri' => $linter->getInfoURI(),
        'description' => $linter->getInfoDescription(),
        'exception' => $exception,
        'options' => $linter->getLinterConfigurationOptions(),
      );
    }

    $linter_info = isort($linter_info, 'short');

    $status_map = $this->getStatusMap();
    $pad = '    ';

    $color_map = array(
      'configured' => 'green',
      'available' => 'yellow',
      'error' => 'red',
    );

    foreach ($linter_info as $key => $linter) {
      $status = $linter['status'];
      $color = $color_map[$status];
      $text = $status_map[$status];
      $print_tail = false;

      $console->writeOut(
        "<bg:".$color.">** %s **</bg> **%s** (%s)\n",
        $text,
        nonempty($linter['short'], '-'),
        $linter['name']);

      if ($linter['exception']) {
        $console->writeOut(
          "\n%s**%s**\n%s\n",
          $pad,
          get_class($linter['exception']),
          phutil_console_wrap(
            $linter['exception']->getMessage(),
            strlen($pad)));
        $print_tail = true;
      }

      $version = $linter['version'];
      $uri = $linter['uri'];
      if ($version || $uri) {
        $console->writeOut("\n");
        $print_tail = true;
      }

      if ($version) {
        $console->writeOut("%s%s **%s**\n", $pad, pht('Version'), $version);
      }

      if ($uri) {
        $console->writeOut("%s__%s__\n", $pad, $linter['uri']);
      }

      $description = $linter['description'];
      if ($description) {
        $console->writeOut(
          "\n%s\n",
          phutil_console_wrap($linter['description'], strlen($pad)));
        $print_tail = true;
      }

      $options = $linter['options'];
      if ($options && $this->getArgument('verbose')) {
        $console->writeOut(
          "\n%s**%s**\n\n",
          $pad,
          pht('Configuration Options'));

        $last_option = last_key($options);
        foreach ($options as $option => $option_spec) {
          $console->writeOut(
            "%s__%s__ (%s)\n",
            $pad,
            $option,
            $option_spec['type']);

          $console->writeOut(
            "%s\n",
            phutil_console_wrap(
              $option_spec['help'],
              strlen($pad) + 2));

          if ($option != $last_option) {
            $console->writeOut("\n");
          }
        }
        $print_tail = true;
      }

      if ($print_tail) {
        $console->writeOut("\n");
      }
    }

    if (!$this->getArgument('verbose')) {
      $console->writeOut(
        "%s\n",
        pht('(Run `%s` for more details.)', 'arc linters --verbose'));
    }
  }


  /**
   * Get human-readable linter statuses, padded to fixed width.
   *
   * @return map<string, string> Human-readable linter status names.
   */
  private function getStatusMap() {
    $text_map = array(
      'configured' => pht('CONFIGURED'),
      'available'  => pht('AVAILABLE'),
      'error'      => pht('ERROR'),
    );

    $sizes = array();
    foreach ($text_map as $key => $string) {
      $sizes[$key] = phutil_utf8_console_strlen($string);
    }

    $longest = max($sizes);
    foreach ($text_map as $key => $string) {
      if ($sizes[$key] < $longest) {
        $text_map[$key] .= str_repeat(' ', $longest - $sizes[$key]);
      }
    }

    $text_map['padding'] = str_repeat(' ', $longest);

    return $text_map;
  }

}
