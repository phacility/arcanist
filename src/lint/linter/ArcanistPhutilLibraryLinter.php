<?php

/**
 * Applies lint rules for Phutil libraries. We enforce three rules:
 *
 *   # If you use a symbol, it must be defined somewhere.
 *   # If you define a symbol, it must not duplicate another definition.
 *   # If you define a class or interface in a file, it MUST be the only symbol
 *     defined in that file.
 *
 * @group linter
 */
final class ArcanistPhutilLibraryLinter extends ArcanistLinter {

  const LINT_UNKNOWN_SYMBOL               = 1;
  const LINT_DUPLICATE_SYMBOL             = 2;
  const LINT_ONE_CLASS_PER_FILE           = 3;

  public function getLintNameMap() {
    return array(
      self::LINT_UNKNOWN_SYMBOL         => 'Unknown Symbol',
      self::LINT_DUPLICATE_SYMBOL       => 'Duplicate Symbol',
      self::LINT_ONE_CLASS_PER_FILE     => 'One Class Per File',
    );
  }

  public function getLinterName() {
    return 'PHL';
  }

  public function getLintSeverityMap() {
    return array();
  }

  public function willLintPaths(array $paths) {
    if (!xhpast_is_available()) {
      throw new Exception(xhpast_get_build_instructions());
    }

    // NOTE: For now, we completely ignore paths and just lint every library in
    // its entirety. This is simpler and relatively fast because we don't do any
    // detailed checks and all the data we need for this comes out of module
    // caches.

    $bootloader = PhutilBootloader::getInstance();
    $libs = $bootloader->getAllLibraries();

    // Load the up-to-date map for each library, without loading the library
    // itself. This means lint results will accurately reflect the state of
    // the working copy.

    $arc_root = dirname(phutil_get_library_root('arcanist'));
    $bin = "{$arc_root}/scripts/phutil_rebuild_map.php";

    $symbols = array();
    foreach ($libs as $lib) {
      // Do these one at a time since they individually fanout to saturate
      // available system resources.
      $future = new ExecFuture(
        'php %s --show --quiet --ugly -- %s',
        $bin,
        phutil_get_library_root($lib));
      $symbols[$lib] = $future->resolveJSON();
    }

    $all_symbols = array();
    foreach ($symbols as $library => $map) {
      // Check for files which declare more than one class/interface in the same
      // file, or mix function definitions with class/interface definitions. We
      // must isolate autoloadable symbols to one per file so the autoloader
      // can't end up in an unresolvable cycle.
      foreach ($map as $file => $spec) {
        $have = idx($spec, 'have', array());

        $have_classes =
          idx($have, 'class', array()) +
          idx($have, 'interface', array());
        $have_functions = idx($have, 'function');

        if ($have_functions && $have_classes) {
          $function_list = implode(', ', array_keys($have_functions));
          $class_list = implode(', ', array_keys($have_classes));
          $this->raiseLintInLibrary(
            $library,
            $file,
            end($have_functions),
            self::LINT_ONE_CLASS_PER_FILE,
            "File '{$file}' mixes function ({$function_list}) and ".
            "class/interface ({$class_list}) definitions in the same file. ".
            "A file which declares a class or an interface MUST ".
            "declare nothing else.");
        } else if (count($have_classes) > 1) {
          $class_list = implode(', ', array_keys($have_classes));
          $this->raiseLintInLibrary(
            $library,
            $file,
            end($have_classes),
            self::LINT_ONE_CLASS_PER_FILE,
            "File '{$file}' declares more than one class or interface ".
            "({$class_list}). A file which declares a class or interface MUST ".
            "declare nothing else.");
        }
      }

      // Check for duplicate symbols: two files providing the same class or
      // function.
      foreach ($map as $file => $spec) {
        $have = idx($spec, 'have', array());
        foreach (array('class', 'function', 'interface') as $type) {
          $libtype = ($type == 'interface') ? 'class' : $type;
          foreach (idx($have, $type, array()) as $symbol => $offset) {
            if (empty($all_symbols[$libtype][$symbol])) {
              $all_symbols[$libtype][$symbol] = array(
                'library' => $library,
                'file'    => $file,
                'offset'  => $offset,
              );
              continue;
            }

            $osrc = $all_symbols[$libtype][$symbol]['file'];
            $olib = $all_symbols[$libtype][$symbol]['library'];

            $this->raiseLintInLibrary(
              $library,
              $file,
              $offset,
              self::LINT_DUPLICATE_SYMBOL,
              "Definition of {$type} '{$symbol}' in '{$file}' in library ".
              "'{$library}' duplicates prior definition in '{$osrc}' in ".
              "library '{$olib}'.");
          }
        }
      }
    }

    $types = array('class', 'function', 'interface', 'class/interface');
    foreach ($symbols as $library => $map) {
      // Check for unknown symbols: uses of classes, functions or interfaces
      // which are not defined anywhere. We reference the list of all symbols
      // we built up earlier.
      foreach ($map as $file => $spec) {
        $need = idx($spec, 'need', array());
        foreach ($types as $type) {
          $libtype = $type;
          if ($type == 'interface' || $type == 'class/interface') {
            $libtype = 'class';
          }
          foreach (idx($need, $type, array()) as $symbol => $offset) {
            if (!empty($all_symbols[$libtype][$symbol])) {
              // Symbol is defined somewhere.
              continue;
            }

            $this->raiseLintInLibrary(
              $library,
              $file,
              $offset,
              self::LINT_UNKNOWN_SYMBOL,
              "Use of unknown {$type} '{$symbol}'. This symbol is not defined ".
              "in any loaded phutil library. It might be misspelled, or it ".
              "may have been added recently. Make sure libphutil and other ".
              "libraries are up to date.");
          }
        }
      }
    }
  }

  private function raiseLintInLibrary($library, $path, $offset, $code, $desc) {
    $root = phutil_get_library_root($library);

    $this->activePath = $root.'/'.$path;
    $this->raiseLintAtOffset($offset, $code, $desc);
  }

  public function lintPath($path) {
    return;
  }

  public function getCacheGranularity() {
    return self::GRANULARITY_GLOBAL;
  }

}
