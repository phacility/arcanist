<?php

final class ArcanistArcToolset extends ArcanistToolset {

  const TOOLSETKEY = 'arc';

  public function getToolsetArguments() {
    return array(
      array(
        'name' => 'conduit-uri',
        'param' => 'uri',
        'help' => pht('Connect to Phabricator install specified by __uri__.'),
      ),
      array(
        'name' => 'conduit-token',
        'param' => 'token',
        'help' => pht('Use a specific authentication token.'),
      ),
    );
  }

}
