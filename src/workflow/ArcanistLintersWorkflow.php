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
      **linters** [__options__] [__name__]
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(pht(<<<EOTEXT
          Supports: cli
          List the available and configured linters, with information about
          what they do and which versions are installed.

          if __name__ is provided, the linter with that name will be displayed.
EOTEXT
      ));
  }

  public function getArguments() {
    return array(
      'verbose' => array(
        'help' => pht('Show detailed information, including options.'),
      ),
      'search' => array(
        'param' => 'search',
        'repeat' => true,
        'help' => pht(
          'Search for linters. Search is case-insensitive, and is performed '.
          'against name and description of each linter.'),
      ),
      '*' => 'exact',
    );
  }

  public function run() {
    $console = PhutilConsole::getConsole();

    $linters = id(new PhutilClassMapQuery())
      ->setAncestorClass('ArcanistLinter')
      ->execute();

    try {
      $built = $this->newLintEngine()->buildLinters();
    } catch (ArcanistNoEngineException $ex) {
      $built = array();
    }

    $linter_info = $this->getLintersInfo($linters, $built);

    $status_map = $this->getStatusMap();
    $pad = '    ';

    $color_map = array(
      'configured' => 'green',
      'available' => 'yellow',
      'error' => 'red',
    );

    $is_verbose = $this->getArgument('verbose');
    $exact = $this->getArgument('exact');
    $search_terms = $this->getArgument('search');

    if ($exact && $search_terms) {
      throw new ArcanistUsageException(
        'Specify either search expression or exact name');
    }

    if ($exact) {
      $linter_info = $this->findExactNames($linter_info, $exact);

      if (!$linter_info) {
        $console->writeOut(
          "%s\n",
          pht(
            'No match found. Try `%s %s` to search for a linter.',
            'arc linters --search',
            $exact[0]));
        return;
      }
      $is_verbose = true;
    }

    if ($search_terms) {
      $linter_info = $this->filterByNames($linter_info, $search_terms);
    }


    foreach ($linter_info as $key => $linter) {
      $status = $linter['status'];
      $color = $color_map[$status];
      $text = $status_map[$status];
      $print_tail = false;

      $console->writeOut(
        "<bg:".$color.">** %s **</bg> **%s** (%s)\n",
        $text,
        nonempty($linter['name'], '-'),
        $linter['short']);

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

      if ($is_verbose) {
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
        if ($options) {
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

        $additional = $linter['additional'];
        foreach ($additional as $title => $body) {
          $console->writeOut(
            "\n%s**%s**\n\n",
            $pad,
            $title);

          // TODO: This should maybe use `tsprintf`.
          // See some discussion in D14563.
          echo $body;
        }

        if ($print_tail) {
          $console->writeOut("\n");
        }
      }
    }

    if (!$is_verbose) {
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

  private function getLintersInfo(array $linters, array $built) {
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
        'name' => $linter->getLinterConfigurationName(),
        'class' => get_class($linter),
        'status' => $status,
        'version' => $version,
        'short' => $linter->getInfoName(),
        'uri' => $linter->getInfoURI(),
        'description' => $linter->getInfoDescription(),
        'exception' => $exception,
        'options' => $linter->getLinterConfigurationOptions(),
        'additional' => $linter->getAdditionalInformation(),
      );
    }

    return isort($linter_info, 'short');
  }

  private function filterByNames(array $linters, array $search_terms) {
    $filtered = array();

    foreach ($linters as $key => $linter) {
      $name = $linter['name'];
      $short = $linter['short'];
      $description = $linter['description'];
      foreach ($search_terms as $term) {
        if (stripos($name, $term) !== false ||
            stripos($short, $term) !== false ||
            stripos($description, $term) !== false) {
          $filtered[$key] = $linter;
        }
      }
    }
    return $filtered;
  }

  private function findExactNames(array $linters, array $names) {
    $filtered = array();

    foreach ($linters as $key => $linter) {
      $name = $linter['name'];

      foreach ($names as $term) {
        if (strcasecmp($name, $term) == 0) {
          $filtered[$key] = $linter;
        }
      }
    }
    return $filtered;
  }

}
