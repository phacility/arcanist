#!/usr/bin/env php
<?php

/*
 * Copyright 2011 Facebook, Inc.
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

$builtin_classes    = get_declared_classes();
$builtin_interfaces = get_declared_interfaces();
$builtin_functions  = get_defined_functions();
$builtin_functions  = $builtin_functions['internal'];

$builtin = array(
  'class'     => array_fill_keys($builtin_classes, true) + array(
    'PhutilBootloader' => true,
  ),
  'function'  => array_filter(
    array(
      'empty' => true,
      'isset' => true,
      'echo'  => true,
      'print' => true,
      'exit'  => true,
      'die'   => true,
      'phutil_load_library' => true,

      // HPHP/i defines these functions as 'internal', but they are NOT
      // builtins and do not exist in vanilla PHP. Make sure we don't mark them
      // as builtin since we need to add dependencies for them.
      'idx'   => false,
      'id'    => false,
    ) + array_fill_keys($builtin_functions, true)),
  'interface' => array_fill_keys($builtin_interfaces, true),
);

require_once dirname(__FILE__).'/__init_script__.php';

if ($argc != 2) {
  $self = basename($argv[0]);
  echo "usage: {$self} <module>\n";
  exit(1);
}

phutil_require_module('phutil', 'filesystem');
$dir = Filesystem::resolvePath($argv[1]);

phutil_require_module('phutil', 'parser/xhpast/bin');
phutil_require_module('phutil', 'parser/xhpast/api/tree');

phutil_require_module('arcanist', 'lint/linter/phutilmodule');
phutil_require_module('arcanist', 'lint/message');
phutil_require_module('arcanist', 'parser/phutilmodule');


$data = array();
$futures = array();
foreach (Filesystem::listDirectory($dir, $hidden_files = false) as $file) {
  if (!preg_match('/.php$/', $file)) {
    continue;
  }
  $data[$file] = Filesystem::readFile($dir.'/'.$file);
  $futures[$file] = xhpast_get_parser_future($data[$file]);
}


$requirements = new PhutilModuleRequirements();
$requirements->addBuiltins($builtin);

$has_init = false;
$has_files = false;
foreach (Futures($futures) as $file => $future) {

  try {
    $tree = XHPASTTree::newFromDataAndResolvedExecFuture(
      $data[$file],
      $future->resolve());
  } catch (XHPASTSyntaxErrorException $ex) {
    echo "Syntax Error! In '{$file}': ".$ex->getMessage()."\n";
    exit(1);
  }

  $root = $tree->getRootNode();
  $requirements->setCurrentFile($file);

  if ($file == '__init__.php') {
    $has_init = true;
    $calls = $root->selectDescendantsOfType('n_FUNCTION_CALL');
    foreach ($calls as $call) {
      $name = $call->getChildByIndex(0);
      $call_name = $name->getConcreteString();
      if ($call_name == 'phutil_require_source') {
        $params = $call->getChildByIndex(1)->getChildren();
        if (count($params) !== 1) {
          $requirements->addLint(
            $call,
            $call->getConcreteString(),
            ArcanistPhutilModuleLinter::LINT_ANALYZER_SIGNATURE,
            "Call to phutil_require_source() must have exactly one argument.");
          continue;
        }
        $param = reset($params);
        $value = $param->getStringLiteralValue();
        if ($value === null) {
          $requirements->addLint(
            $param,
            $param->getConcreteString(),
            ArcanistPhutilModuleLinter::LINT_ANALYZER_SIGNATURE,
            "phutil_require_source() parameter must be a string literal.");
          continue;
        }
        $requirements->addSourceDependency($name, $value);
      } else if ($call_name == 'phutil_require_module') {
        analyze_phutil_require_module($call, $requirements);
      }
    }
  } else {
    $has_files = true;

    $requirements->addSourceDeclaration(basename($file));

    // Function uses:
    //  - Explicit call
    //  TODO?: String literal in ReflectionFunction().

    $calls = $root->selectDescendantsOfType('n_FUNCTION_CALL');
    foreach ($calls as $call) {
      $name = $call->getChildByIndex(0);
      if ($name->getTypeName() == 'n_VARIABLE' ||
          $name->getTypeName() == 'n_VARIABLE_VARIABLE') {
        $requirements->addLint(
          $name,
          $name->getConcreteString(),
          ArcanistPhutilModuleLinter::LINT_ANALYZER_DYNAMIC,
          "Use of variable function calls prevents dependencies from being ".
          "checked statically. This module may have undetectable errors.");
        continue;
      }
      if ($name->getTypeName() == 'n_CLASS_STATIC_ACCESS') {
        // We'll pick this up later.
        continue;
      }

      $call_name = $name->getConcreteString();
      if ($call_name == 'phutil_require_module') {
        analyze_phutil_require_module($call, $requirements);
      } else if ($call_name == 'call_user_func' ||
                 $call_name == 'call_user_func_array') {
        $params = $call->getChildByIndex(1)->getChildren();
        if (count($params) == 0) {
          $requirements->addLint(
            $call,
            $call->getConcreteString(),
            ArcanistPhutilModuleLinter::LINT_ANALYZER_SIGNATURE,
            "Call to {$call_name}() must have at least one argument.");
        }
        $symbol = array_shift($params);
        $symbol_value = $symbol->getStringLiteralValue();
        if ($symbol_value) {
          $requirements->addFunctionDependency(
            $symbol,
            $symbol_value);
        } else {
          $requirements->addLint(
            $symbol,
            $symbol->getConcreteString(),
            ArcanistPhutilModuleLinter::LINT_ANALYZER_DYNAMIC,
            "Use of variable arguments to {$call_name} prevents dependencies ".
            "from being checked statically. This module may have undetectable ".
            "errors.");
        }
      } else {
        $requirements->addFunctionDependency(
          $name,
          $name->getConcreteString());
      }
    }

    $functions = $root->selectDescendantsOfType('n_FUNCTION_DECLARATION');
    foreach ($functions as $function) {
      $name = $function->getChildByIndex(2);
      $requirements->addFunctionDeclaration(
        $name,
        $name->getConcreteString());
    }


    // Class uses:
    //  - new
    //  - extends (in class declaration)
    //  - Static method call
    //  - Static property access
    //  - Constant use
    //  TODO?: String literal in ReflectionClass().
    //  TODO?: String literal in array literal in call_user_func /
    //         call_user_func_array().

    // TODO: Raise a soft warning for use of an unknown class in:
    //  - Typehints
    //  - instanceof
    //  - catch

    $classes = $root->selectDescendantsOfType('n_CLASS_DECLARATION');
    foreach ($classes as $class) {
      $class_name = $class->getChildByIndex(1);
      $requirements->addClassDeclaration(
        $class_name,
        $class_name->getConcreteString());
      $extends = $class->getChildByIndex(2);
      foreach ($extends->selectDescendantsOfType('n_CLASS_NAME') as $parent) {
        $requirements->addClassDependency(
          $class_name->getConcreteString(),
          $parent,
          $parent->getConcreteString());
      }
      $implements = $class->getChildByIndex(3);
      $interfaces = $implements->selectDescendantsOfType('n_CLASS_NAME');
      foreach ($interfaces as $interface) {
        $requirements->addInterfaceDependency(
          $class_name->getConcreteString(),
          $interface,
          $interface->getConcreteString());
      }
    }

    if (count($classes) > 1) {
      foreach ($classes as $class) {
        $class_name = $class->getChildByIndex(1);
        $class_string = $class_name->getConcreteString();
        $requirements->addLint(
          $class_name,
          $class_string,
          ArcanistPhutilModuleLinter::LINT_ANALYZER_MULTIPLE_CLASSES,
          "This file declares more than one class. Declare only one class per ".
          "file.");
        break;
      }
    }

    $uses_of_new = $root->selectDescendantsOfType('n_NEW');
    foreach ($uses_of_new as $new_operator) {
      $name = $new_operator->getChildByIndex(0);
      if ($name->getTypeName() == 'n_VARIABLE' ||
          $name->getTypeName() == 'n_VARIABLE_VARIABLE') {
        $requirements->addLint(
          $name,
          $name->getConcreteString(),
          ArcanistPhutilModuleLinter::LINT_ANALYZER_DYNAMIC,
          "Use of variable class instantiation prevents dependencies from ".
          "being checked statically. This module may have undetectable ".
          "errors.");
        continue;
      }
      $requirements->addClassDependency(
        null,
        $name,
        $name->getConcreteString());
    }

    $static_uses = $root->selectDescendantsOfType('n_CLASS_STATIC_ACCESS');
    foreach ($static_uses as $static_use) {
      $name = $static_use->getChildByIndex(0);
      if ($name->getTypeName() != 'n_CLASS_NAME') {
        echo "WARNING UNLINTABLE\n";
        continue;
      }
      $name_concrete = $name->getConcreteString();
      $magic_names = array(
        'static' => true,
        'parent' => true,
        'self'   => true,
      );
      if (isset($magic_names[$name_concrete])) {
        continue;
      }
      $requirements->addClassDependency(
        null,
        $name,
        $name_concrete);
    }

    // Interface uses:
    //  - implements
    //  - extends (in interface declaration)

    $interfaces = $root->selectDescendantsOfType('n_INTERFACE_DECLARATION');
    foreach ($interfaces as $interface) {
      $interface_name = $interface->getChildByIndex(1);
      $requirements->addInterfaceDeclaration(
        $interface_name,
        $interface_name->getConcreteString());
      $extends = $interface->getChildByIndex(2);
      foreach ($extends->selectDescendantsOfType('n_CLASS_NAME') as $parent) {
        $requirements->addInterfaceDependency(
          $class_name->getConcreteString(),
          $parent,
          $parent->getConcreteString());
      }
    }

  }
}

if (!$has_init && $has_files) {
  $requirements->addRawLint(
    ArcanistPhutilModuleLinter::LINT_ANALYZER_NO_INIT,
    "Create an __init__.php file in this module.");
}

echo json_encode($requirements->toDictionary());

/**
 * Parses meaning from calls to phutil_require_module() in __init__.php files.
 *
 * @group module
 */
function analyze_phutil_require_module(
  XHPASTNode $call,
  PhutilModuleRequirements $requirements) {

  $name = $call->getChildByIndex(0);
  $params = $call->getChildByIndex(1)->getChildren();
  if (count($params) !== 2) {
    $requirements->addLint(
      $call,
      $call->getConcreteString(),
      ArcanistPhutilModuleLinter::LINT_ANALYZER_SIGNATURE,
      "Call to phutil_require_module() must have exactly two arguments.");
    return;
  }

  $module_param = array_pop($params);
  $library_param = array_pop($params);

  $library_value = $library_param->getStringLiteralValue();
  if ($library_value === null) {
    $requirements->addLint(
      $library_param,
      $library_param->getConcreteString(),
      ArcanistPhutilModuleLinter::LINT_ANALYZER_SIGNATURE,
      "phutil_require_module() parameters must be string literals.");
    return;
  }

  $module_value = $module_param->getStringLiteralValue();
  if ($module_value === null) {
    $requirements->addLint(
      $module_param,
      $module_param->getConcreteString(),
      ArcanistPhutilModuleLinter::LINT_ANALYZER_SIGNATURE,
      "phutil_require_module() parameters must be string literals.");
    return;
  }

  $requirements->addModuleDependency(
    $name,
    $library_value.':'.$module_value);
}
