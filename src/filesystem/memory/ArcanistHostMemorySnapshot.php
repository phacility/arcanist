<?php

final class ArcanistHostMemorySnapshot
  extends Phobject {

  private $memorySnapshot;

  public static function newFromRawMeminfo($meminfo_source, $meminfo_raw) {
    $snapshot = new self();

    $snapshot->memorySnapshot = $snapshot->readMeminfoSnapshot(
      $meminfo_source,
      $meminfo_raw);

    return $snapshot;
  }

  public function getTotalSwapBytes() {
    $info = $this->getMemorySnapshot();
    return $info['swap.total'];
  }

  private function getMemorySnapshot() {
    if ($this->memorySnapshot === null) {
      $this->memorySnapshot = $this->newMemorySnapshot();
    }

    return $this->memorySnapshot;
  }

  private function newMemorySnapshot() {
    $meminfo_source = '/proc/meminfo';
    list($meminfo_raw) = execx('cat %s', $meminfo_source);
    return $this->readMeminfoSnapshot($meminfo_source, $meminfo_raw);
  }

  private function readMeminfoSnapshot($meminfo_source, $meminfo_raw) {
    $meminfo_pattern = '/^([^:]+):\s+(\S+)(?:\s+(kB))?\z/';

    $meminfo_map = array();

    $meminfo_lines = phutil_split_lines($meminfo_raw, false);
    foreach ($meminfo_lines as $meminfo_line) {
      $meminfo_parts = phutil_preg_match($meminfo_pattern, $meminfo_line);

      if (!$meminfo_parts) {
        throw new Exception(
          pht(
            'Unable to parse line in meminfo source "%s": "%s".',
            $meminfo_source,
            $meminfo_line));
      }

      $meminfo_key = $meminfo_parts[1];
      $meminfo_value = $meminfo_parts[2];
      $meminfo_unit = idx($meminfo_parts, 3);

      if (isset($meminfo_map[$meminfo_key])) {
        throw new Exception(
          pht(
            'Encountered duplicate meminfo key "%s" in meminfo source "%s".',
            $meminfo_key,
            $meminfo_source));
      }

      $meminfo_map[$meminfo_key] = array(
        'value' => $meminfo_value,
        'unit' => $meminfo_unit,
      );
    }

    $swap_total_bytes = $this->readMeminfoBytes(
      $meminfo_source,
      $meminfo_map,
      'SwapTotal');

    return array(
      'swap.total' => $swap_total_bytes,
    );
  }

  private function readMeminfoBytes(
    $meminfo_source,
    $meminfo_map,
    $meminfo_key) {

    $meminfo_integer = $this->readMeminfoIntegerValue(
      $meminfo_source,
      $meminfo_map,
      $meminfo_key);

    $meminfo_unit = $meminfo_map[$meminfo_key]['unit'];
    if ($meminfo_unit === null) {
      throw new Exception(
        pht(
          'Expected to find a byte unit for meminfo key "%s" in meminfo '.
          'source "%s", found no unit.',
          $meminfo_key,
          $meminfo_source));
    }

    if ($meminfo_unit !== 'kB') {
      throw new Exception(
        pht(
          'Expected unit for meminfo key "%s" in meminfo source "%s" '.
          'to be "kB", found "%s".',
          $meminfo_key,
          $meminfo_source,
          $meminfo_unit));
    }

    $meminfo_bytes = ($meminfo_integer * 1024);

    return $meminfo_bytes;
  }

  private function readMeminfoIntegerValue(
    $meminfo_source,
    $meminfo_map,
    $meminfo_key) {

    $meminfo_value = $this->readMeminfoValue(
      $meminfo_source,
      $meminfo_map,
      $meminfo_key);

    if (!phutil_preg_match('/^\d+\z/', $meminfo_value)) {
      throw new Exception(
        pht(
          'Expected to find an integer value for meminfo key "%s" in '.
          'meminfo source "%s", found "%s".',
          $meminfo_key,
          $meminfo_source,
          $meminfo_value));
    }

    return (int)$meminfo_value;
  }

  private function readMeminfoValue(
    $meminfo_source,
    $meminfo_map,
    $meminfo_key) {

    if (!isset($meminfo_map[$meminfo_key])) {
      throw new Exception(
        pht(
          'Expected to find meminfo key "%s" in meminfo source "%s".',
          $meminfo_key,
          $meminfo_source));
    }

    return $meminfo_map[$meminfo_key]['value'];
  }

}
