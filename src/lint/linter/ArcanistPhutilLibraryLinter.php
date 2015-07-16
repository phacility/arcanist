<?php

/**
 * Applies lint rules for Phutil libraries. We enforce three rules:
 *
 *   # If you use a symbol, it must be defined somewhere.
 *   # If you define a symbol, it must not duplicate another definition.
 *   # If you define a class or interface in a file, it MUST be the only symbol
 *     defined in that file.
 */
final class ArcanistPhutilLibraryLinter extends ArcanistLinter {

  const LINT_UNKNOWN_SYMBOL      = 1;
  const LINT_DUPLICATE_SYMBOL    = 2;
  const LINT_ONE_CLASS_PER_FILE  = 3;

  public function getInfoName() {
    return pht('Phutil Library Linter');
  }

  public function getInfoDescription() {
    return pht(
      'Make sure all the symbols used in a %s library are defined and known. '.
      'This linter is specific to PHP source in %s libraries.',
      'libphutil',
      'libphutil');
  }

  public function getLinterName() {
    return 'PHL';
  }

  public function getLinterConfigurationName() {
    return 'phutil-library';
  }

  public function getCacheGranularity() {
    return self::GRANULARITY_GLOBAL;
  }

  public function getLintNameMap() {
    return array(
      self::LINT_UNKNOWN_SYMBOL      => pht('Unknown Symbol'),
      self::LINT_DUPLICATE_SYMBOL    => pht('Duplicate Symbol'),
      self::LINT_ONE_CLASS_PER_FILE  => pht('One Class Per File'),
    );
  }

  public function getLinterPriority() {
    return 2.0;
  }

  public function willLintPaths(array $paths) {
    // NOTE: For now, we completely ignore paths and just lint every library in
    // its entirety. This is simpler and relatively fast because we don't do any
    // detailed checks and all the data we need for this comes out of module
    // caches.

    $bootloader = PhutilBootloader::getInstance();
    $libraries  = $bootloader->getAllLibraries();

    // Load the up-to-date map for each library, without loading the library
    // itself. This means lint results will accurately reflect the state of
    // the working copy.

    $symbols = array();

    foreach ($libraries as $library) {
      $root = phutil_get_library_root($library);

      try {
        $symbols[$library] = id(new PhutilLibraryMapBuilder($root))
          ->buildFileSymbolMap();
      } catch (XHPASTSyntaxErrorException $ex) {
        // If the library contains a syntax error then there isn't much that we
        // can do.
        continue;
      }
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
            pht(
              "File '%s' mixes function (%s) and class/interface (%s) ".
              "definitions in the same file. A file which declares a class ".
              "or an interface MUST declare nothing else.",
              $file,
              $function_list,
              $class_list));
        } else if (count($have_classes) > 1) {
          $class_list = implode(', ', array_keys($have_classes));
          $this->raiseLintInLibrary(
            $library,
            $file,
            end($have_classes),
            self::LINT_ONE_CLASS_PER_FILE,
            pht(
              "File '%s' declares more than one class or interface (%s). ".
              "A file which declares a class or interface MUST declare ".
              "nothing else.",
              $file,
              $class_list));
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
              pht(
                "Definition of %s '%s' in '%s' in library '%s' duplicates ".
                "prior definition in '%s' in library '%s'.",
                $type,
                $symbol,
                $file,
                $library,
                $osrc,
                $olib));
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

            $libphutil_root = dirname(phutil_get_library_root('phutil'));

            $this->raiseLintInLibrary(
              $library,
              $file,
              $offset,
              self::LINT_UNKNOWN_SYMBOL,
              pht(
                "Use of unknown %s '%s'. Common causes are:\n\n".
                "  - Your %s is out of date.\n".
                "    This is the most common cause.\n".
                "    Update this copy of libphutil: %s\n\n".
                "  - Some other library is out of date.\n".
                "    Update the library this symbol appears in.\n\n".
                "  - This symbol is misspelled.\n".
                "    Spell the symbol name correctly.\n".
                "    Symbol name spelling is case-sensitive.\n\n".
                "  - This symbol was added recently.\n".
                "    Run `%s` on the library it was added to.\n\n".
                "  - This symbol is external. Use `%s`.\n".
                "    Use `%s` to find usage examples of this directive.\n\n".
                "*** ALTHOUGH USUALLY EASY TO FIX, THIS IS A SERIOUS ERROR.\n".
                "*** THIS ERROR IS YOUR FAULT. YOU MUST RESOLVE IT.",
                $type,
                $symbol,
                'libphutil/',
                $libphutil_root,
                'arc liberate',
                '@phutil-external-symbol',
                'grep'));
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

}
