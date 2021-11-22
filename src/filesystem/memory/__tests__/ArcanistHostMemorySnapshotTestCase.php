<?php

final class ArcanistHostMemorySnapshotTestCase
  extends PhutilTestCase {

  public function testSnapshotSwapTotalBytes() {
    $test_cases = array(
      'meminfo_swap_normal.txt' => 4294963200,
      'meminfo_swap_zero.txt' => 0,
      'meminfo_swap_missing.txt' => false,
      'meminfo_swap_invalid.txt' => false,
      'meminfo_swap_badunits.txt' => false,
      'meminfo_swap_duplicate.txt' => false,
    );

    $test_dir = dirname(__FILE__).'/data/';

    foreach ($test_cases as $test_file => $expect) {
      $test_data = Filesystem::readFile($test_dir.$test_file);

      $caught = null;
      $actual = null;
      try {
        $snapshot = ArcanistHostMemorySnapshot::newFromRawMeminfo(
          $test_file,
          $test_data);
        $actual = $snapshot->getTotalSwapBytes();
      } catch (Exception $ex) {
        if ($expect === false) {
          $caught = $ex;
        } else {
          throw $ex;
        }
      } catch (Throwable $ex) {
        throw $ex;
      }

      if ($expect === false) {
        $this->assertTrue(
          ($caught instanceof Exception),
          pht('Expected exception for "%s".', $test_file));
      } else {
        $this->assertEqual(
          $expect,
          $actual,
          pht('Result for "%s".', $test_file));
      }
    }
  }

}
