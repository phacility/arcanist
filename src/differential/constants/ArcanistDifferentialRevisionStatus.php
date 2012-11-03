<?php

final class ArcanistDifferentialRevisionStatus {

  const NEEDS_REVIEW      = 0;
  const NEEDS_REVISION    = 1;
  const ACCEPTED          = 2;
  const CLOSED            = 3;
  const ABANDONED         = 4;

  public static function getNameForRevisionStatus($status) {
    static $map = array(
      self::NEEDS_REVIEW      => 'Needs Review',
      self::NEEDS_REVISION    => 'Needs Revision',
      self::ACCEPTED          => 'Accepted',
      self::CLOSED            => 'Closed',
      self::ABANDONED         => 'Abandoned',
    );

    return idx($map, coalesce($status, '?'), 'Unknown');
  }

}
