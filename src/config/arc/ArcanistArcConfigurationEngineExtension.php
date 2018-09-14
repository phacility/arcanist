<?php

final class ArcanistArcConfigurationEngineExtension
  extends ArcanistConfigurationEngineExtension {

  const EXTENSIONKEY = 'arc';

  const KEY_ALIASES = 'aliases';

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

      'arc.feature.start.default' => array(
        'type' => 'string',
        'help' => pht(
          'The name of the default branch to create the new feature branch '.
          'off of.'),
        'example' => '"develop"',
      ),
      'arc.land.onto.default' => array(
        'type' => 'string',
        'help' => pht(
          'The name of the default branch to land changes onto when '.
          '`%s` is run.',
          'arc land'),
        'example' => '"develop"',
      ),

      'arc.autostash' => array(
        'type' => 'bool',
        'help' => pht(
          'Whether %s should permit the automatic stashing of changes in the '.
          'working directory when requiring a clean working copy. This option '.
          'should only be used when users understand how to restore their '.
          'working directory from the local stash if an Arcanist operation '.
          'causes an unrecoverable error.',
          'arc'),
        'default' => false,
        'example' => 'false',
      ),

      'history.immutable' => array(
        'type' => 'bool',
        'legacy' => 'immutable_history',
        'help' => pht(
          'If true, %s will never change repository history (e.g., through '.
          'amending or rebasing). Defaults to true in Mercurial and false in '.
          'Git. This setting has no effect in Subversion.',
          'arc'),
        'example' => 'false',
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
      'https.blindly-trust-domains' => array(
        'type' => 'list',
        'help' => pht(
          'List of domains to blindly trust SSL certificates for. '.
          'Disables peer verification.'),
        'default' => array(),
        'example' => '["secure.mycompany.com"]',
      ),
      'browser' => array(
        'type' => 'string',
        'help' => pht('Command to use to invoke a web browser.'),
        'example' => '"gnome-www-browser"',
      ),
      'http.basicauth.user' => array(
        'type' => 'string',
        'help' => pht('Username to use for basic auth over HTTP transports.'),
        'example' => '"bob"',
      ),
      'http.basicauth.pass' => array(
        'type' => 'string',
        'help' => pht('Password to use for basic auth over HTTP transports.'),
        'example' => '"bobhasasecret"',
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
            'repository. Normally, `arc` can figure this association out on '.
            'its own, but if your setup is unusual you can use this option '.
            'to tell it what the desired value is.'))
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
    );
  }

}
