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
  const LINT_NONCANONICAL_SYMBOL = 4;

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
      self::LINT_NONCANONICAL_SYMBOL => pht('Noncanonical Symbol'),
    );
  }

  public function getLinterPriority() {
    return 2.0;
  }

  public function willLintPaths(array $paths) {

    $libtype_map = array(
      'class' => 'class',
      'function' => 'function',
      'interface' => 'class',
      'class/interface' => 'class',
    );

    // NOTE: For now, we completely ignore paths and just lint every library in
    // its entirety. This is simpler and relatively fast because we don't do any
    // detailed checks and all the data we need for this comes out of module
    // caches.

    $bootloader = PhutilBootloader::getInstance();
    $libraries  = $bootloader->getAllLibraries();

    // Load all the builtin symbols first.
    $builtin_map = PhutilLibraryMapBuilder::newBuiltinMap();
    $builtin_map = $builtin_map['have'];

    $normal_symbols = array();
    $all_symbols = array();
    foreach ($builtin_map as $type => $builtin_symbols) {
      $libtype = $libtype_map[$type];
      foreach ($builtin_symbols as $builtin_symbol => $ignored) {
        $normal_symbol = $this->normalizeSymbol($builtin_symbol);
        $normal_symbols[$type][$normal_symbol] = $builtin_symbol;

        $all_symbols[$libtype][$builtin_symbol] = array(
          'library' => null,
          'file' => null,
          'offset' => null,
        );
      }
    }

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
      // function. While doing this, we also build a map of normalized symbol
      // names to original symbol names: we want a definition of "idx()" to
      // collide with a definition of "IdX()", and want to perform spelling
      // corrections later.

      foreach ($map as $file => $spec) {
        $have = idx($spec, 'have', array());
        foreach (array('class', 'function', 'interface') as $type) {
          $libtype = $libtype_map[$type];
          foreach (idx($have, $type, array()) as $symbol => $offset) {
            $normal_symbol = $this->normalizeSymbol($symbol);

            if (empty($normal_symbols[$libtype][$normal_symbol])) {
              $normal_symbols[$libtype][$normal_symbol] = $symbol;
              $all_symbols[$libtype][$symbol] = array(
                'library' => $library,
                'file'    => $file,
                'offset'  => $offset,
              );
              continue;
            }

            $old_symbol = $normal_symbols[$libtype][$normal_symbol];
            $old_src = $all_symbols[$libtype][$old_symbol]['file'];
            $old_lib = $all_symbols[$libtype][$old_symbol]['library'];

            // If these values are "null", it means that the symbol is a
            // builtin symbol provided by PHP or a PHP extension.

            if ($old_lib === null) {
              $message = pht(
                'Definition of symbol "%s" (of type "%s") in file "%s" in '.
                'library "%s" duplicates builtin definition of the same '.
                'symbol.',
                $symbol,
                $type,
                $file,
                $library);
            } else {
              $message = pht(
                'Definition of symbol "%s" (of type "%s") in file "%s" in '.
                'library "%s" duplicates prior definition in file "%s" in '.
                'library "%s".',
                $symbol,
                $type,
                $file,
                $library,
                $old_src,
                $old_lib);
            }

            $this->raiseLintInLibrary(
              $library,
              $file,
              $offset,
              self::LINT_DUPLICATE_SYMBOL,
              $message);
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
          $libtype = $libtype_map[$type];
          foreach (idx($need, $type, array()) as $symbol => $offset) {
            if (!empty($all_symbols[$libtype][$symbol])) {
              // Symbol is defined somewhere.
              continue;
            }

            $normal_symbol = $this->normalizeSymbol($symbol);
            if (!empty($normal_symbols[$libtype][$normal_symbol])) {
              $proper_symbol = $normal_symbols[$libtype][$normal_symbol];

              switch ($type) {
                case 'class':
                  $summary = pht(
                    'Class symbol "%s" should be written as "%s".',
                    $symbol,
                    $proper_symbol);
                  break;
                case 'function':
                  $summary = pht(
                    'Function symbol "%s" should be written as "%s".',
                    $symbol,
                    $proper_symbol);
                  break;
                case 'interface':
                  $summary = pht(
                    'Interface symbol "%s" should be written as "%s".',
                    $symbol,
                    $proper_symbol);
                  break;
                case 'class/interface':
                  $summary = pht(
                    'Class or interface symbol "%s" should be written as "%s".',
                    $symbol,
                    $proper_symbol);
                  break;
                default:
                  throw new Exception(
                    pht('Unknown symbol type "%s".', $type));
              }

              $this->raiseLintInLibrary(
                $library,
                $file,
                $offset,
                self::LINT_NONCANONICAL_SYMBOL,
                $summary,
                $symbol,
                $proper_symbol);

              continue;
            }

            $arcanist_root = dirname(phutil_get_library_root('arcanist'));

            switch ($type) {
              case 'class':
                $summary = pht(
                  'Use of unknown class symbol "%s".',
                  $symbol);
                break;
              case 'function':
                $summary = pht(
                  'Use of unknown function symbol "%s".',
                  $symbol);
                break;
              case 'interface':
                $summary = pht(
                  'Use of unknown interface symbol "%s".',
                  $symbol);
                break;
              case 'class/interface':
                $summary = pht(
                  'Use of unknown class or interface symbol "%s".',
                  $symbol);
                break;
            }

            $details = pht(
              "Common causes are:\n".
              "\n".
              "  - Your copy of Arcanist is out of date.\n".
              "    This is the most common cause.\n".
              "    Update this copy of Arcanist:\n".
              "\n".
              "      %s\n".
              "\n".
              "  - Some other library is out of date.\n".
              "    Update the library this symbol appears in.\n".
              "\n".
              "  - The symbol is misspelled.\n".
              "    Spell the symbol name correctly.\n".
              "\n".
              "  - You added the symbol recently, but have not updated\n".
              "    the symbol map for the library.\n".
              "    Run \"arc liberate\" in the library where the symbol is\n".
              "    defined.\n".
              "\n".
              "  - This symbol is defined in an external library.\n".
              "    Use \"@phutil-external-symbol\" to annotate it.\n".
              "    Use \"grep\" to find examples of usage.",
              $arcanist_root);

            $message = implode(
              "\n\n",
              array(
                $summary,
                $details,
              ));

            $this->raiseLintInLibrary(
              $library,
              $file,
              $offset,
              self::LINT_UNKNOWN_SYMBOL,
              $message);
          }
        }
      }
    }
  }

  private function raiseLintInLibrary(
    $library,
    $path,
    $offset,
    $code,
    $desc,
    $original = null,
    $replacement = null) {
    $root = phutil_get_library_root($library);

    $this->activePath = $root.'/'.$path;
    $this->raiseLintAtOffset($offset, $code, $desc, $original, $replacement);
  }

  public function lintPath($path) {
    return;
  }

  private function normalizeSymbol($symbol) {
    return phutil_utf8_strtolower($symbol);
  }

}
