<?php

final class PhutilCommandString extends Phobject {

  private $argv;
  private $escapingMode = false;

  const MODE_DEFAULT = 'default';
  const MODE_LINUX = 'linux';
  const MODE_WINDOWS = 'windows';
  const MODE_POWERSHELL = 'powershell';

  public function __construct(array $argv) {
    $this->argv = $argv;

    $this->escapingMode = self::MODE_DEFAULT;

    // This makes sure we throw immediately if there are errors in the
    // parameters.
    $this->getMaskedString();
  }

  public function __toString() {
    return $this->getMaskedString();
  }

  public function getUnmaskedString() {
    return $this->renderString(true);
  }

  public function getMaskedString() {
    return $this->renderString(false);
  }

  public function setEscapingMode($escaping_mode) {
    $this->escapingMode = $escaping_mode;
    return $this;
  }

  private function renderString($unmasked) {
    return xsprintf(
      'xsprintf_command',
      array(
        'unmasked' => $unmasked,
        'mode' => $this->escapingMode,
      ),
      $this->argv);
  }

  public static function escapeArgument($value, $mode) {
    if ($mode === self::MODE_DEFAULT) {
      if (phutil_is_windows()) {
        $mode = self::MODE_WINDOWS;
      } else {
        $mode = self::MODE_LINUX;
      }
    }

    switch ($mode) {
      case self::MODE_LINUX:
        return self::escapeLinux($value);
      case self::MODE_WINDOWS:
        return self::escapeWindows($value);
      case self::MODE_POWERSHELL:
        return self::escapePowershell($value);
      default:
        throw new Exception(pht('Unknown escaping mode!'));
    }
  }

  private static function escapePowershell($value) {
    // These escape sequences are from http://ss64.com/ps/syntax-esc.html

    // Replace backticks first.
    $value = str_replace('`', '``', $value);

    // Now replace other required notations.
    $value = str_replace("\0", '`0', $value);
    $value = str_replace(chr(7), '`a', $value);
    $value = str_replace(chr(8), '`b', $value);
    $value = str_replace("\f", '`f', $value);
    $value = str_replace("\n", '`n', $value);
    $value = str_replace("\r", '`r', $value);
    $value = str_replace("\t", '`t', $value);
    $value = str_replace("\v", '`v', $value);
    $value = str_replace('#', '`#', $value);
    $value = str_replace("'", '`\'', $value);
    $value = str_replace('"', '`"', $value);

    // The rule on dollar signs is mentioned further down the page, and
    // they only need to be escaped when using double quotes (which we are).
    $value = str_replace('$', '`$', $value);

    return '"'.$value.'"';
  }

  private static function escapeLinux($value) {
    if (strpos($value, "\0") !== false) {
      throw new Exception(
        pht(
          'Command string argument includes a NULL byte. This byte can not '.
          'be safely escaped in command line arguments in Linux '.
          'environments.'));
    }

    // If the argument is nonempty and contains only common printable
    // characters, we do not need to escape it. This makes debugging
    // workflows a little more user-friendly by making command output
    // more readable.
    if (preg_match('(^[a-zA-Z0-9:/@._+-]+\z)', $value)) {
      return $value;
    }

    return escapeshellarg($value);
  }

  private static function escapeWindows($value) {
    if (strpos($value, "\0") !== false) {
      throw new Exception(
        pht(
          'Command string argument includes a NULL byte. This byte can not '.
          'be safely escaped in command line arguments in Windows '.
          'environments.'));
    }

    if (!phutil_is_utf8($value)) {
      throw new Exception(
        pht(
          'Command string argument includes text which is not valid UTF-8. '.
          'This library can not safely escape this sequence in command '.
          'line arguments in Windows environments.'));
    }

    $has_backslash = (strpos($value, '\\') !== false);
    $has_space = (strpos($value, ' ') !== false);
    $has_quote = (strpos($value, '"') !== false);
    $is_empty = (strlen($value) === 0);

    // If a backslash appears before another backslash, a double quote, or
    // the end of the argument, we must escape it. Otherwise, we must leave
    // it unescaped.

    if ($has_backslash) {
      $value_v = preg_split('//', $value, -1, PREG_SPLIT_NO_EMPTY);
      $len = count($value_v);
      for ($ii = 0; $ii < $len; $ii++) {
        if ($value_v[$ii] === '\\') {
          if ($ii + 1 < $len) {
            $next = $value_v[$ii + 1];
          } else {
            $next = null;
          }

          if ($next === '"' || $next === '\\' || $next === null) {
            $value_v[$ii] = '\\\\';
          }
        }
      }
      $value = implode('', $value_v);
    }

    // Then, escape double quotes by prefixing them with backslashes.
    if ($has_quote || $has_space || $has_backslash || $is_empty) {
      $value = addcslashes($value, '"');
      $value = '"'.$value.'"';
    }

    return $value;
  }

}
