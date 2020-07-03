<?php

final class ArcanistArcConfigurationEngineExtension
  extends ArcanistConfigurationEngineExtension {

  const EXTENSIONKEY = 'arc';

  const KEY_ALIASES = 'aliases';
  const KEY_PROMPTS = 'prompts';

  public function newConfigurationOptions() {
    // TOOLSETS: Restore "load", and maybe this other stuff.

/*
      'load' => array(
        'type' => 'list',
        'legacy' => 'phutil_libraries',
        'help' => pht(
          'A list of paths to phutil libraries that should be loaded at '.
          'startup. This can be used to make classes available, like lint '.
          'or unit test engines.'),
        'default' => array(),
        'example' => '["/var/arc/customlib/src"]',
      ),
      'editor' => array(
        'type' => 'string',
        'help' => pht(
          'Command to use to invoke an interactive editor, like `%s` or `%s`. '.
          'This setting overrides the %s environmental variable.',
          'nano',
          'vim',
          'EDITOR'),
        'example' => '"nano"',
      ),
      'https.cabundle' => array(
        'type' => 'string',
        'help' => pht(
          "Path to a custom CA bundle file to be used for arcanist's cURL ".
          "calls. This is used primarily when your conduit endpoint is ".
          "behind HTTPS signed by your organization's internal CA."),
        'example' => 'support/yourca.pem',
      ),
      'browser' => array(
        'type' => 'string',
        'help' => pht('Command to use to invoke a web browser.'),
        'example' => '"gnome-www-browser"',
      ),
*/



    return array(
      id(new ArcanistStringConfigOption())
        ->setKey('base')
        ->setSummary(pht('Ruleset for selecting commit ranges.'))
        ->setHelp(
          pht(
            'Base commit ruleset to invoke when determining the start of a '.
            'commit range. See "Arcanist User Guide: Commit Ranges" for '.
            'details.'))
        ->setExamples(
          array(
            'arc:amended, arc:prompt',
          )),
      id(new ArcanistStringConfigOption())
        ->setKey('repository')
        ->setAliases(
          array(
            'repository.callsign',
          ))
        ->setSummary(pht('Repository for the current working copy.'))
        ->setHelp(
          pht(
            'Associate the working copy with a specific Phabricator '.
            'repository. Normally, Arcanist can figure this association '.
            'out on its own, but if your setup is unusual you can use '.
            'this option to tell it what the desired value is.'))
        ->setExamples(
          array(
            'libexample',
            'XYZ',
            'R123',
            '123',
          )),
      id(new ArcanistStringConfigOption())
        ->setKey('phabricator.uri')
        ->setAliases(
          array(
            'conduit_uri',
            'default',
          ))
        ->setSummary(pht('Phabricator install to connect to.'))
        ->setHelp(
          pht(
            'Associates this working copy with a specific installation of '.
            'Phabricator.'))
        ->setExamples(
          array(
            'https://phabricator.mycompany.com/',
          )),
      id(new ArcanistAliasesConfigOption())
        ->setKey(self::KEY_ALIASES)
        ->setDefaultValue(array())
        ->setSummary(pht('List of command aliases.'))
        ->setHelp(
          pht(
            'Configured command aliases. Use the "alias" workflow to define '.
            'aliases.')),
      id(new ArcanistPromptsConfigOption())
        ->setKey(self::KEY_PROMPTS)
        ->setDefaultValue(array())
        ->setSummary(pht('List of prompt responses.'))
        ->setHelp(
          pht(
            'Configured prompt aliases. Use the "prompts" workflow to '.
            'show prompts and responses.')),
      id(new ArcanistStringListConfigOption())
        ->setKey('arc.land.onto')
        ->setDefaultValue(array())
        ->setSummary(pht('Default list of "onto" refs for "arc land".'))
        ->setHelp(
          pht(
            'Specifies the default behavior when "arc land" is run with '.
            'no "--onto" flag.'))
        ->setExamples(
          array(
            '["master"]',
          )),
      id(new ArcanistStringListConfigOption())
        ->setKey('pager')
        ->setDefaultValue(array())
        ->setSummary(pht('Default pager command.'))
        ->setHelp(
          pht(
            'Specify the pager command to use when displaying '.
            'documentation.'))
        ->setExamples(
          array(
            '["less", "-R", "--"]',
          )),
      id(new ArcanistStringConfigOption())
        ->setKey('arc.land.onto-remote')
        ->setSummary(pht('Default list of "onto" remote for "arc land".'))
        ->setHelp(
          pht(
            'Specifies the default behavior when "arc land" is run with '.
            'no "--onto-remote" flag.'))
        ->setExamples(
          array(
            'origin',
          )),
      id(new ArcanistStringConfigOption())
        ->setKey('arc.land.strategy')
        ->setSummary(
          pht(
            'Configure a default merge strategy for "arc land".'))
        ->setHelp(
          pht(
            'Specifies the default behavior when "arc land" is run with '.
            'no "--strategy" flag.')),
    );
  }

}
