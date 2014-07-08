<?php

/**
 * API while running in the context of a commit hook.
 */
abstract class ArcanistHookAPI {
  abstract public function getCurrentFileData($path);
  abstract public function getUpstreamFileData($path);
}
