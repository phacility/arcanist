<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Lists dependencies and requirements for a module.
 *
 * @group module
 */
final class PhutilModuleRequirements {

  protected $builtins = array(
    'class'       => array(),
    'interface'   => array(),
    'function'    => array(),
  );

  protected $requires = array(
    'class'       => array(),
    'interface'   => array(),
    'function'    => array(),
    'source'      => array(),
    'module'      => array(),
  );

  protected $declares = array(
    'class'       => array(),
    'interface'   => array(),
    'function'    => array(),
    'source'      => array(),
  );

  protected $chain = array(
  );

  protected $currentFile;
  protected $messages = array(
  );

  public function setCurrentFile($current_file) {
    $this->currentFile = $current_file;
    return $this;
  }

  protected function getCurrentFile() {
    return $this->currentFile;
  }

  protected function getWhere(XHPASTNode $where) {
    return $this->getCurrentFile().':'.$where->getOffset();
  }

  public function addClassDeclaration(XHPASTNode $where, $name) {
    return $this->addDeclaration('class', $where, $name);
  }

  public function addFunctionDeclaration(XHPASTNode $where, $name) {
    return $this->addDeclaration('function', $where, $name);
  }

  public function addInterfaceDeclaration(XHPASTNode $where, $name) {
    return $this->addDeclaration('interface', $where, $name);
  }

  public function addSourceDeclaration($name) {
    $this->declares['source'][$name] = true;
    return $this;
  }

  protected function addDeclaration($type, XHPASTNode $where, $name) {
    $this->declares[$type][$name] = $this->getWhere($where);
    return $this;
  }

  protected function addDependency($type, XHPASTNode $where, $name) {
    if (isset($this->builtins[$type][$name])) {
      return $this;
    }
    if (empty($this->requires[$type][$name])) {
      $this->requires[$type][$name] = array();
    }
    $this->requires[$type][$name][] = $this->getWhere($where);
    return $this;
  }

  public function addClassDependency($child, XHPASTNode $where, $name) {
    if ($child !== null) {
      if (empty($this->builtins['class'][$name])) {
        $this->chain['class'][$child] = $name;
      }
    }
    return $this->addDependency('class', $where, $name);
  }

  public function addFunctionDependency(XHPASTNode $where, $name) {
    return $this->addDependency('function', $where, $name);
  }

  public function addInterfaceDependency($child, XHPASTNode $where, $name) {
    if ($child !== null) {
      if (empty($this->builtins['interface'][$name])) {
        $this->chain['interface'][$child][] = $name;
      }
    }
    return $this->addDependency('interface', $where, $name);
  }

  public function addSourceDependency(XHPASTNode $where, $name) {
    return $this->addDependency('source', $where, $name);
  }

  public function addModuleDependency(XHPASTNode $where, $name) {
    return $this->addDependency('module', $where, $name);
  }

  public function addBuiltins(array $builtins) {
    foreach ($builtins as $type => $symbol_set) {
      $this->builtins[$type] += $symbol_set;
    }
    return $this;
  }

  public function addRawLint($code, $message) {
    $this->messages[] = array(
      null,
      null,
      $code,
      $message);
    return $this;
  }

  public function addLint(XHPASTNode $where, $text, $code, $message) {
    $this->messages[] = array(
      $this->getWhere($where),
      $text,
      $code,
      $message);
    return $this;
  }

  public function toDictionary() {

    // Remove all dependencies on things which we declare since they're never
    // useful and guaranteed to be satisfied.
    foreach ($this->declares as $type => $things) {
      if ($type == 'source') {
        // Source is treated specially since we only reconcile it locally.
        continue;
      }
      foreach ($things as $name => $where) {
        unset($this->requires[$type][$name]);
      }
    }

    return array(
      'declares' => $this->declares,
      'requires' => $this->requires,
      'chain'    => $this->chain,
      'messages' => $this->messages,
    );
  }

}
