<?php

/**
 * Executes `./arc-post-push` after diff was created.
 *
 * Usage:
 *  .arcconfig:
 *    ...
 *    "events.listeners": ["UberArcPostPushListener"],
 *    ...
 */
class UberArcPostPushListener extends PhutilEventListener {

  const SCRIPT = './arc-post-push';

  public function register() {
    $this->listen(ArcanistEventType::TYPE_LAND_DIDPUSHREVISION);
  }

  public function handleEvent(PhutilEvent $event) {
    $script = self::SCRIPT;

    if (!file_exists($script)) {
      throw new Exception(pht('%s does not exist.', $script));
    }
    if (!is_executable($script)) {
      throw new Exception(pht('%s is not executable.', $script));
    }

    $err = phutil_passthru('%C', $script);

    if ($err) {
      throw new Exception(pht('%s exited with non-zero status: %d.',
        $script,
        $err));
    }
  }
}
