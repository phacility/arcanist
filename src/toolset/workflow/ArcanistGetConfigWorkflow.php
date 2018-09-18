<?php

/**
 * Read configuration settings.
 */
final class ArcanistGetConfigWorkflow
  extends ArcanistWorkflow {

  public function getWorkflowName() {
    return 'get-config';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **get-config** [__options__] -- [__name__ ...]
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          Supports: cli
          Reads an arc configuration option. With no argument, reads all
          options.

          With __--verbose__, shows detailed information about one or more
          options.
EOTEXT
      );
  }

  public function getArguments() {
    return array(
      'verbose' => array(
        'help' => pht('Show detailed information about options.'),
      ),
      '*' => 'argv',
    );
  }

  public function runWorkflow() {
    $argv = $this->getArgument('argv');
    $is_verbose = $this->getArgument('verbose');

    $source_list = $this->getConfigurationSourceList();
    $config_engine = $this->getConfigurationEngine();

    $options_map = $config_engine->newConfigOptionsMap();

    $all_keys = array();
    $alias_map = array();
    foreach ($options_map as $key => $config_option) {
      $all_keys[$key] = $key;
      foreach ($config_option->getAliases() as $alias) {
        $alias_map[$alias] = $key;
      }
    }

    foreach ($source_list->getSources() as $source) {
      foreach ($source->getAllKeys() as $key) {
        $all_keys[$key] = $key;
      }
    }

    ksort($all_keys);

    $defaults_map = $config_engine->newDefaults();

    foreach ($all_keys as $key) {
      $option = idx($options_map, $key);

      if ($option) {
        $option_summary = $option->getSummary();
        $option_help = $option->getHelp();
      } else {
        $option_summary = pht('(This option is unrecognized.)');
        $option_help = $option_summary;
      }

      if ($option) {
        $formatter = $option;
      } else {
        $formatter = new ArcanistWildConfigOption();
      }

      if (!$is_verbose) {
        echo tsprintf(
          "**%s**\n%R\n\n",
          $key,
          $option_summary);
      } else {
        echo tsprintf(
          "**%s**\n\n%R\n\n",
          $key,
          $option_help);
      }

      // NOTE: We can only get configuration from a SourceList if the option is
      // a recognized option, so skip this part if the option isn't known.
      if ($option) {
        $value = $source_list->getConfig($key);
        $display_value = $formatter->getDisplayValueFromValue($value);

        echo tsprintf("%s: %s\n", pht('Value'), $display_value);

        $default_value = idx($defaults_map, $key);
        $display_default = $formatter->getDisplayValueFromValue($value);

        echo tsprintf("%s: %s\n", pht('Default'), $display_default);
      }

      foreach ($source_list->getSources() as $source) {
        if ($source->hasValueForKey($key)) {
          $source_value = $source->getValueForKey($key);
          $source_value = $formatter->getValueFromStorageValue($source_value);
          $source_display = $formatter->getDisplayValueFromValue($source_value);
        } else {
          $source_display = pht('-');
        }

        echo tsprintf(
          "%s: %s\n",
          $source->getSourceDisplayName(),
          $source_display);
      }
    }

    // if (!$verbose) {
    //   $console->writeOut(
    //     "(%s)\n",
    //     pht('Run with %s for more details.', '--verbose'));
    // }

    return 0;
  }

}
