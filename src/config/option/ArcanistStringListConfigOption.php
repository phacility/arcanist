<?php

final class ArcanistStringListConfigOption
  extends ArcanistListConfigOption {

  public function getType() {
    return 'list<string>';
  }

  protected function validateListItem($idx, $item) {
    if (!is_string($item)) {
      throw new PhutilArgumentUsageException(
        pht(
          'Expected a string (at index "%s"), found "%s".',
          $idx,
          phutil_describe_type($item)));
    }
  }

}
