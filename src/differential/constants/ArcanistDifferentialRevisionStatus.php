<?php

final class ArcanistDifferentialRevisionStatus extends Phobject {

  const NEEDS_REVIEW      = 0;
  const NEEDS_REVISION    = 1;
  const ACCEPTED          = 2;
  const CLOSED            = 3;
  const ABANDONED         = 4;
  const CHANGES_PLANNED   = 5;
  const IN_PREPARATION    = 6;

  public static function getNameForRevisionStatus($status) {
    $map = array(
      self::NEEDS_REVIEW      => pht('Needs Review'),
      self::NEEDS_REVISION    => pht('Needs Revision'),
      self::ACCEPTED          => pht('Accepted'),
      self::CLOSED            => pht('Closed'),
      self::ABANDONED         => pht('Abandoned'),
      self::CHANGES_PLANNED   => pht('Changes Planned'),
      self::IN_PREPARATION    => pht('In Preparation'),
    );

    return idx($map, coalesce($status, '?'), pht('Unknown'));
  }

}
