<?php

final class ArcanistLookWorkflow
  extends ArcanistArcWorkflow {

  public function getWorkflowName() {
    return 'look';
  }

  public function getWorkflowInformation() {
    $help = pht(<<<EOTEXT
Look around, or look at a specific __thing__.
EOTEXT
      );

    return $this->newWorkflowInformation()
      ->setSynopsis(
        pht('You stand in the middle of a small clearing.'))
      ->addExample('**look**')
      ->addExample('**look** [options] -- __thing__')
      ->setHelp($help);
  }

  public function getWorkflowArguments() {
    return array(
      $this->newWorkflowArgument('argv')
        ->setWildcard(true),
    );
  }

  public function runWorkflow() {
    echo tsprintf(
      "%!\n\n",
      pht(
        'Arcventure'));

    $argv = $this->getArgument('argv');

    if ($argv) {
      if ($argv === array('remotes')) {
        return $this->lookRemotes();
      }

      if ($argv === array('published')) {
        return $this->lookPublished();
      }

      echo tsprintf(
        "%s\n",
        pht(
          'You do not see "%s" anywhere.',
          implode(' ', $argv)));

      return 1;
    }

    echo tsprintf(
      "%W\n\n",
      pht(
        'You stand in the middle of a small clearing in the woods.'));

    $now = time();
    $hour = (int)date('G', $now);

    if ($hour >= 5 && $hour <= 7) {
      $time = pht(
        'It is early morning. Glimses of sunlight peek through the trees '.
        'and you hear the faint sound of birds overhead.');
    } else if ($hour >= 8 && $hour <= 10) {
      $time = pht(
        'It is morning. The sun is high in the sky to the east and you hear '.
        'birds all around you. A gentle breeze rustles the leaves overhead.');
    } else if ($hour >= 11 && $hour <= 13) {
      $time = pht(
        'It is midday. The sun is high overhead and the air is still. It is '.
        'very warm. You hear the cry of a hawk high overhead and far in the '.
        'distance.');
    } else if ($hour >= 14 && $hour <= 16) {
      $time = pht(
        'It is afternoon. The air has changed and it feels as though it '.
        'may rain. You hear a squirrel chittering high overhead.');
    } else if ($hour >= 17 && $hour <= 19) {
      $time = pht(
        'It is nearly dusk. The wind has picked up and the trees around you '.
        'sway and rustle.');
    } else if ($hour >= 21 && $hour <= 23) {
      $time = pht(
        'It is late in the evening. The air is cool and still, and filled '.
        'with the sound of crickets.');
    } else {
      $phase = new PhutilLunarPhase($now);
      if ($phase->isNew()) {
        $time = pht(
          'Night has fallen, and the thin sliver of moon overhead offers '.
          'no comfort. It is almost pitch black. The night is bitter '.
          'cold. It will be difficult to look around in these conditions.');
      } else if ($phase->isFull()) {
        $time = pht(
          'Night has fallen, but your surroundings are illuminated by the '.
          'silvery glow of a full moon overhead. The night is cool and '.
          'the air is crisp. The trees are calm.');
      } else if ($phase->isWaxing()) {
        $time = pht(
          'Night has fallen. The moon overhead is waxing, and provides '.
          'just enough light that you can make out your surroundings. It '.
          'is quite cold.');
      } else if ($phase->isWaning()) {
        $time = pht(
          'Night has fallen. The moon overhead is waning. You can barely '.
          'make out your surroundings. It is very cold.');
      }
    }

    echo tsprintf(
      "%W\n\n",
      $time);

    echo tsprintf(
      "%W\n\n",
      pht(
        'Several small trails and footpaths cross here, twisting away from '.
        'you among the trees.'));

    echo tsprintf(
      pht("Just ahead to the north, you can see **remotes**.\n"));

    return 0;
  }

  private function lookRemotes() {
    echo tsprintf(
      "%W\n\n",
      pht(
        'You follow a wide, straight path to the north and arrive in a '.
        'grove of fruit trees after a few minutes of walking. The grass '.
        'underfoot is thick and small insects flit through the air.'));

    echo tsprintf(
      "%W\n\n",
      pht(
        'At the far edge of the grove, you see remotes:'));

    $api = $this->getRepositoryAPI();

    $remotes = $api->newRemoteRefQuery()
      ->execute();

    $this->loadHardpoints(
      $remotes,
      ArcanistRemoteRef::HARDPOINT_REPOSITORYREFS);

    foreach ($remotes as $remote) {

      $view = $remote->newRefView();

      $push_uri = $remote->getPushURI();
      if ($push_uri === null) {
        $push_uri = '-';
      }

      $view->appendLine(
        pht(
          'Push URI: %s',
          $push_uri));

      $push_repository = $remote->getPushRepositoryRef();
      if ($push_repository) {
        $push_display = $push_repository->getDisplayName();
      } else {
        $push_display = '-';
      }

      $view->appendLine(
        pht(
          'Push Repository: %s',
          $push_display));

      $fetch_uri = $remote->getFetchURI();
      if ($fetch_uri === null) {
        $fetch_uri = '-';
      }

      $view->appendLine(
        pht(
          'Fetch URI: %s',
          $fetch_uri));

      $fetch_repository = $remote->getFetchRepositoryRef();
      if ($fetch_repository) {
        $fetch_display = $fetch_repository->getDisplayName();
      } else {
        $fetch_display = '-';
      }

      $view->appendLine(
        pht(
          'Fetch Repository: %s',
          $fetch_display));

      echo tsprintf('%s', $view);
    }

    echo tsprintf("\n");
    echo tsprintf(
      pht(
        "Across the grove, a stream flows north toward ".
        "**published** commits.\n"));
  }

  private function lookPublished() {
    echo tsprintf(
      "%W\n\n",
      pht(
        'You walk along the narrow bank of the stream as it winds lazily '.
        'downhill and turns east, gradually widening into a river.'));

    $api = $this->getRepositoryAPI();

    $published = $api->getPublishedCommitHashes();

    if ($published) {
      echo tsprintf(
        "%W\n\n",
        pht(
          'Floating on the water, you see published commits:'));

      foreach ($published as $hash) {
        echo tsprintf(
          "%s\n",
          $hash);
      }

      echo tsprintf(
        "\n%W\n",
        pht(
          'They river bubbles peacefully.'));
    } else {
      echo tsprintf(
        "%W\n",
        pht(
          'The river bubbles quietly, but you do not see any published '.
          'commits anywhere.'));
    }
  }

}
