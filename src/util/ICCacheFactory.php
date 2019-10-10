<?php

final class ICCacheFactory extends Phobject {

  private $caches = array();
  private $namespace;
  private $enableProfiler = true;

  public function addCaches(array $caches) {
    $this->caches = array_mergev(array($this->caches, $caches));
    return $this;
  }

  public function setNamespace($namespace) {
    $this->namespace = $namespace;
    return $this;
  }

  public function setEnableProfiler($enable) {
    $this->enableProfiler = $enable;
    return $this;
  }

  public function createStack() {
    $caches = $this->addNamespaceToCaches($this->caches);
    if ($this->enableProfiler) {
      $caches = $this->addProfilerToCaches($caches);
    }
    return (new PhutilKeyValueCacheStack())
      ->setCaches($caches);
  }

  private function addProfilerToCaches(array $caches) {
    foreach ($caches as $key => $cache) {
      $pcache = new PhutilKeyValueCacheProfiler($cache);
      $pcache->setProfiler(PhutilServiceProfiler::getInstance());
      $caches[$key] = $pcache;
    }

    return $caches;
  }

  private function addNamespaceToCaches(array $caches) {
    if (!$this->namespace) {
      return $caches;
    }

    foreach ($caches as $key => $cache) {
      $ncache = new PhutilKeyValueCacheNamespace($cache);
      $ncache->setNamespace($this->namespace);
      $caches[$key] = $ncache;
    }

    return $caches;
  }

}
