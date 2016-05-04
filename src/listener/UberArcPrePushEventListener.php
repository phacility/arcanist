<?php

/**
 * Executes `./arc-pre-push` during `arc land` just before push.
 *
 * If `./arc-pre-push` exits with non-zero status `arc land` is aborted.
 *
 * Usage:
 *  .arcconfig:
 *    ...
 *    "events.listeners": ["UberArcPrePushEventListener"],
 *    ...
 */
class UberArcPrePushEventListener extends PhutilEventListener {

  const SCRIPT = './arc-pre-push';

  public function register() {
    $this->listen(ArcanistEventType::TYPE_LAND_WILLPUSHREVISION);
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
