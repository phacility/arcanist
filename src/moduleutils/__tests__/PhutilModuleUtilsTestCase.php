<?php

final class PhutilModuleUtilsTestCase extends PhutilTestCase {

  public function testGetCurrentLibraryName() {
    $this->assertEqual('arcanist', phutil_get_current_library_name());
  }

}
