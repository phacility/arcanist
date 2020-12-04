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
      id(new ArcanistStringConfigOption())
        ->setKey('arc.land.notaccepted.message')
        ->setDefaultValue(
          pht(
            'Rejected: You should never land revision without review. '.
            'If you know what you are doing and still want to land, add a '.
            '`FORCE_LAND=<reason>` line to the revision summary and it will be audited.'))
        ->setSummary(
          pht(
            'Error message when attempting to land a non-accepted revision.')),
      id(new ArcanistStringConfigOption())
        ->setKey('arc.land.buildfailures.message')
        ->setDefaultValue(
          pht(
            'Rejected: You should not land revisions with failed or ongoing builds. '.
            'If you know what you are doing and still want to land, add a '.
            '`ALLOW_FAILED_TESTS=<reason>` line to the revision summary and it will be audited.'))
        ->setSummary(
          pht(
            'Error message when attempting to land a revision with failed builds.')),
      id(new ArcanistBoolConfigOption())
        ->setKey('phlq')
        ->setDefaultValue(false)
        ->setSummary(pht('PHLQ install to connect to.'))
        ->setHelp(
          pht(
            'Use PHLQ to land changes')),
      id(new ArcanistBoolConfigOption())
        ->setKey('is.phlq')
        ->setDefaultValue(false)
        ->setSummary(pht('Internal use only'))
        ->setHelp(
          pht(
            'Internal use only')),
      id(new ArcanistStringConfigOption())
        ->setKey('phlq.uri')
        ->setAliases(
          array(
            'phlq_uri',
          ))
        ->setSummary(pht('PHLQ instance uri.'))
        ->setHelp(
          pht(
            'Associates this working copy with a specific installation of '.
            'PHLQ.'))
        ->setExamples(
          array(
            'https://phlq/',
          )),
      id(new ArcanistStringListConfigOption())
        ->setKey('forceable.build.plan.phids')
        ->setDefaultValue(array())
        ->setAliases(
          array(
            'forceable_build_plan_phids',
          ))
        ->setSummary(
          pht(
            'List PHIDs of forceable build plans which can be landed with failing status with '.
            'a `ALLOW_FAILED_TESTS=<reason>` line in revision summary and it will be audited.'))
        ->setExamples(
          array(
            ["PHID-ABCD-abcdefghijklmnopqrst"],
          )),
      id(new ArcanistStringListConfigOption())
        ->setKey('non-blocking.build.plan.phids')
        ->setDefaultValue(array())
        ->setAliases(
          array(
            'non_blocking_build_plan_phids',
          ))
        ->setSummary(
          pht(
            'List PHIDs of build plans which can be landed with failing '.
            'status.'))
        ->setExamples(
          array(
            ["PHID-ABCD-abcdefghijklmnopqrst"],
          )),
      id(new ArcanistStringListConfigOption())
        ->setKey('lint.build.plan.phids')
        ->setDefaultValue(array())
        ->setAliases(
          array(
            'lint_build_plan_phids',
          ))
        ->setSummary(
          pht(
            'List PHIDs of lint build plans which can be landed with failing '.
            'status.'))
        ->setExamples(
          array(
            ["PHID-ABCD-abcdefghijklmnopqrst"],
          )),
      id(new ArcanistStringConfigOption())
        ->setKey('arc.upgrade.message')
        ->setDefaultValue(
          pht('Arc is managed by your employer.'))
        ->setSummary(
          pht('Message shown when arc upgrade command is called.')),
    );
  }

}
