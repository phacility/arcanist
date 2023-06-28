<?php

abstract class ArcanistConfigurationEngineExtension
  extends Phobject {

  final public function getExtensionKey() {
    return $this->getPhobjectClassConstant('EXTENSIONKEY');
  }

}
