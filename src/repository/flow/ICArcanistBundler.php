<?php

final class ICArcanistBundler extends Phobject {

  private static $cache;

  private static function getCache() {
    if (!self::$cache) {
      self::$cache = ic_standard_cache(
        ic_blob_cache('bundler'),
        null,
        256,
        false);
    }
    return self::$cache;
  }

  public static function newFromDiff($diff) {
    $md5 = md5($diff);
    $cache_key = "diff-{$md5}-bundle";
    if ($existing = self::readBundleFromCache($cache_key)) {
      return $existing;
    }
    $profiler = PhutilServiceProfiler::getInstance();
    $id = $profiler->beginServiceCall(array(
      'type' => 'bundler-diff',
    ));
    $bundle = ArcanistBundle::newFromDiff($diff);
    $changes = $bundle->getChanges();
    ksort($changes);
    $bundle = ArcanistBundle::newFromChanges($changes);
    $profiler->endServiceCall($id, array());
    self::writeBundleToCache($cache_key, $bundle);
    return $bundle;
  }

  public static function getUnifiedDiff($diff) {
    $md5 = md5($diff);
    $cache_key = "diff-{$md5}-unified";
    $cache = self::getCache();
    if ($existing = $cache->getKey($cache_key)) {
      return $existing;
    }
    $bundle = self::newFromDiff($diff);
    $unified = $bundle->toUnifiedDiff();
    $cache->setKey($cache_key, $unified);
    return $unified;
  }

  private static function writeBundleToCache($key, ArcanistBundle $bundle) {
    $profiler = PhutilServiceProfiler::getInstance();
    $id = $profiler->beginServiceCall(array(
      'type' => 'bundler-write',
    ));
    $changes = $bundle->getChanges();
    ksort($changes);
    $change_list = mpull($changes, 'toDictionary');
    self::getCache()->setKey($key, serialize($change_list));
    $profiler->endServiceCall($id, array());
  }

  private static function readBundleFromCache($key) {
    $profiler = PhutilServiceProfiler::getInstance();
    $id = $profiler->beginServiceCall(array(
      'type' => 'bundler-read',
    ));
    $change_list = self::getCache()->getKey($key);
    if (!$change_list) {
      $profiler->endServiceCall($id, array());
      return null;
    }
    $change_list = unserialize($change_list);
    $changes = array();
    foreach ($change_list as $change) {
      $changes[] = ArcanistDiffChange::newFromDictionary($change);
    }
    $bundle = ArcanistBundle::newFromChanges($changes);
    $profiler->endServiceCall($id, array());
    return $bundle;
  }

}
