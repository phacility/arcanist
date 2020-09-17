<?php

/**
 * Thrown when revision state is blocking next steps.
 */
final class ArcanistRevisionStatusException extends ArcanistUsageException {

  public function __construct($read_more_url) {
    parent::__construct(
      pht("Rejected: You should never land revision without review. If you know what you are doing and still want to land, use `FORCE_LAND=__reasson__` in revisions summary. Read more: {$read_more_url}")
    );
  }

}
