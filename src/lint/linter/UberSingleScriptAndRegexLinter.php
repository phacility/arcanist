<?php

/**
 * Simple linter to run a script ONCE for all matching files, and turn the
 *
 * This acceptes the same configuration parameters and the same regexp patterns
 * as ArcanistScriptAndRegexLinter; the only (intended) difference is that this
 * linter runs a single command, whereas script-and-regex runs one command per
 * changed file.
 *
 * Configure this linter by setting these keys in your .arclint section:
 *
 *  - `uber-single-script-and-regex.script` Script command to run. This can be
 *    the path to a linter script, but may also include flags or use shell
 *    features (see below for examples).
 *  - `uber-single-script-and-regex.regex` The regex to process output with. This
 *    regex uses named capturing groups (detailed below) to interpret output.
 *
 * Everything following in this comment should be identical to
 * ArcanistScriptAndRegexLinter.
 *
 * The script will be invoked from the project root, so you can specify a
 * relative path like `scripts/lint.sh` or an absolute path like
 * `/opt/lint/lint.sh`.
 *
 * This linter is necessarily more limited in its capabilities than a normal
 * linter which can perform custom processing, but may be somewhat simpler to
 * configure.
 *
 * == Script... ==
 *
 * The script will be invoked once for each file that is to be linted, with
 * the file passed as the first argument. The file may begin with a "-"; ensure
 * your script will not interpret such files as flags (perhaps by ending your
 * script configuration with "--", if its argument parser supports that).
 *
 * Note that when run via `arc diff`, the list of files to be linted includes
 * deleted files and files that were moved away by the change. The linter should
 * not assume the path it is given exists, and it is not an error for the
 * linter to be invoked with paths which are no longer there. (Every affected
 * path is subject to lint because some linters may raise errors in other files
 * when a file is removed, or raise an error about its removal.)
 *
 * The script should emit lint messages to stdout, which will be parsed with
 * the provided regex.
 *
 * For example, you might use a configuration like this:
 *
 *   /opt/lint/lint.sh --flag value --other-flag --
 *
 * stderr is ignored. If you have a script which writes messages to stderr,
 * you can redirect stderr to stdout by using a configuration like this:
 *
 *   sh -c '/opt/lint/lint.sh "$0" 2>&1'
 *
 * The return code of the script must be 0, or an exception will be raised
 * reporting that the linter failed. If you have a script which exits nonzero
 * under normal circumstances, you can force it to always exit 0 by using a
 * configuration like this:
 *
 *   sh -c '/opt/lint/lint.sh "$0" || true'
 *
 * Multiple instances of the script will be run in parallel if there are
 * multiple files to be linted, so they should not use any unique resources.
 * For instance, this configuration would not work properly, because several
 * processes may attempt to write to the file at the same time:
 *
 *   COUNTEREXAMPLE
 *   sh -c '/opt/lint/lint.sh --output /tmp/lint.out "$0" && cat /tmp/lint.out'
 *
 * There are necessary limits to how gracefully this linter can deal with
 * edge cases, because it is just a script and a regex. If you need to do
 * things that this linter can't handle, you can write a phutil linter and move
 * the logic to handle those cases into PHP. PHP is a better general-purpose
 * programming language than regular expressions are, if only by a small margin.
 *
 * == ...and Regex ==
 *
 * The regex must be a valid PHP PCRE regex, including delimiters and flags.
 *
 * The regex will be matched against the entire output of the script, so it
 * should generally be in this form if messages are one-per-line:
 *
 *   /^...$/m
 *
 * The regex should capture these named patterns with `(?P<name>...)`:
 *
 *   - `message` (required) Text describing the lint message. For example,
 *     "This is a syntax error.".
 *   - `name` (optional) Text summarizing the lint message. For example,
 *     "Syntax Error".
 *   - `severity` (optional) The word "error", "warning", "autofix", "advice",
 *     or "disabled", in any combination of upper and lower case. Instead, you
 *     may match groups called `error`, `warning`, `advice`, `autofix`, or
 *     `disabled`. These allow you to match output formats like "E123" and
 *     "W123" to indicate errors and warnings, even though the word "error" is
 *     not present in the output. If no severity capturing group is present,
 *     messages are raised with "error" severity. If multiple severity capturing
 *     groups are present, messages are raised with the highest captured
 *     severity. Capturing groups like `error` supersede the `severity`
 *     capturing group.
 *   - `error` (optional) Match some nonempty substring to indicate that this
 *     message has "error" severity.
 *   - `warning` (optional) Match some nonempty substring to indicate that this
 *     message has "warning" severity.
 *   - `advice` (optional) Match some nonempty substring to indicate that this
 *     message has "advice" severity.
 *   - `autofix` (optional) Match some nonempty substring to indicate that this
 *     message has "autofix" severity.
 *   - `disabled` (optional) Match some nonempty substring to indicate that this
 *     message has "disabled" severity.
 *   - `file` (optional) The name of the file to raise the lint message in. If
 *     not specified, defaults to the linted file. It is generally not necessary
 *     to capture this unless the linter can raise messages in files other than
 *     the one it is linting.
 *   - `line` (optional) The line number of the message. If no text is
 *     captured, the message is assumed to affect the entire file.
 *   - `char` (optional) The character offset of the message.
 *   - `offset` (optional) The byte offset of the message. If captured, this
 *     supersedes `line` and `char`.
 *   - `original` (optional) The text the message affects.
 *   - `replacement` (optional) The text that the range captured by `original`
 *     should be automatically replaced by to resolve the message.
 *   - `code` (optional) A short error type identifier which can be used
 *     elsewhere to configure handling of specific types of messages. For
 *     example, "EXAMPLE1", "EXAMPLE2", etc., where each code identifies a
 *     class of message like "syntax error", "missing whitespace", etc. This
 *     allows configuration to later change the severity of all whitespace
 *     messages, for example.
 *   - `ignore` (optional) Match some nonempty substring to ignore the match.
 *     You can use this if your linter sometimes emits text like "No lint
 *     errors".
 *   - `stop` (optional) Match some nonempty substring to stop processing input.
 *     Remaining matches for this file will be discarded, but linting will
 *     continue with other linters and other files.
 *   - `halt` (optional) Match some nonempty substring to halt all linting of
 *     this file by any linter. Linting will continue with other files.
 *   - `throw` (optional) Match some nonempty substring to throw an error, which
 *     will stop `arc` completely. You can use this to fail abruptly if you
 *     encounter unexpected output. All processing will abort.
 *
 * Numbered capturing groups are ignored.
 *
 * For example, if your lint script's output looks like this:
 *
 *   error:13 Too many goats!
 *   warning:22 Not enough boats.
 *
 * ...you could use this regex to parse it:
 *
 *   /^(?P<severity>warning|error):(?P<line>\d+) (?P<message>.*)$/m
 *
 * The simplest valid regex for line-oriented output is something like this:
 *
 *   /^(?P<message>.*)$/m
 *
 * @task  lint        Linting
 * @task  linterinfo  Linter Information
 * @task  parse       Parsing Output
 * @task  config      Validating Configuration
 */
final class UberSingleScriptAndRegexLinter extends ArcanistLinter {

  private $script = null;
  private $regex = null;

  public function getInfoName() {
    return pht('Single Script and Regex');
  }

  public function getInfoDescription() {
    return pht(
      'Run an external script with all files as input, then parse its output '.
      'with a regular expression. This is a generic binding that can be used '.
      'to run custom lint scripts.');
  }

/* -(  Linting  )------------------------------------------------------------ */

  /**
   * Run the regex on the output of the script.
   *
   * @task lint
   */
  public function didLintPaths(array $paths) {
    $root = $this->getProjectRoot();
    $future = new ExecFuture('%C %s', $this->script, implode(' ', $paths));
    $future->setCWD($root);
    list($output) = $future->resolvex();

    if (!strlen($output)) {
      // No output, but it exited 0, so just move on.
      return;
    }

    $matches = null;
    if (!preg_match_all($this->regex, $output, $matches, PREG_SET_ORDER)) {
      // Output with no matches. This might be a configuration error, but more
      // likely it's something like "No lint errors." and the user just hasn't
      // written a sufficiently powerful/ridiculous regexp to capture it into an
      // 'ignore' group. Don't make them figure this out; advanced users can
      // capture 'throw' to handle this case.
      return;
    }

    foreach ($matches as $match) {
      if (!empty($match['throw'])) {
        $throw = $match['throw'];
        throw new ArcanistUsageException(
          pht(
            "%s: configuration captured a '%s' named capturing group, ".
            "'%s'. Script output:\n%s",
            __CLASS__,
            'throw',
            $throw,
            $output));
      }

      if (!empty($match['halt'])) {
        $this->stopAllLinters();
        break;
      }

      if (!empty($match['stop'])) {
        break;
      }

      if (!empty($match['ignore'])) {
        continue;
      }

      $path = idx($match, 'file');
      if (strlen($path)) {
          list($line, $char) = $this->getMatchLineAndChar($match, $path);
      } else {
          $line = null;
          $char = null;
      }

      $dict = array(
        'path'        => $path,
        'line'        => $line,
        'char'        => $char,
        'code'        => idx($match, 'code', $this->getLinterName()),
        'severity'    => $this->getMatchSeverity($match),
        'name'        => idx($match, 'name', 'Lint'),
        'description' => idx($match, 'message', pht('Undefined Lint Message')),
      );

      $original = idx($match, 'original');
      if ($original !== null) {
        $dict['original'] = $original;
      }

      $replacement = idx($match, 'replacement');
      if ($replacement !== null) {
        $dict['replacement'] = $replacement;
      }

      $lint = ArcanistLintMessage::newFromDictionary($dict);
      $this->addLintMessage($lint);
    }
  }


/* -(  Linter Information  )------------------------------------------------- */

  /**
   * Return the short name of the linter.
   *
   * @return string Short linter identifier.
   *
   * @task linterinfo
   */
  public function getLinterName() {
    return 'USS&RX';
  }

  public function getLinterConfigurationName() {
    return 'uber-single-script-and-regex';
  }

  public function getLinterConfigurationOptions() {
    // These fields are optional only to avoid breaking things.
    $options = array(
      'uber-single-script-and-regex.script' => array(
        'type' => 'string',
        'help' => pht('Script to execute.'),
      ),
      'uber-single-script-and-regex.regex' => array(
        'type' => 'regex',
        'help' => pht('The regex to process output with.'),
      ),
    );

    return $options + parent::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value) {
    switch ($key) {
      case 'uber-single-script-and-regex.script':
        $this->script = $value;
        return;
      case 'uber-single-script-and-regex.regex':
        $this->regex = $value;
        return;
    }

    return parent::setLinterConfigurationValue($key, $value);
  }

/* -(  Parsing Output  )----------------------------------------------------- */

  /**
   * Get the line and character of the message from the regex match.
   *
   * @param dict Captured groups from regex.
   * @return pair<int|null,int|null> Line and character of the message.
   *
   * @task parse
   */
  private function getMatchLineAndChar(array $match, $path) {
    if (!empty($match['offset'])) {
      list($line, $char) = $this->getEngine()->getLineAndCharFromOffset(
        idx($match, 'file', $path),
        $match['offset']);
      return array($line + 1, $char + 1);
    }

    $line = idx($match, 'line');
    if (strlen($line)) {
      $line = (int)$line;
      if (!$line) {
        $line = 1;
      }
    } else {
      $line = null;
    }

    $char = idx($match, 'char');
    if ($char) {
      $char = (int)$char;
    } else {
      $char = null;
    }

    return array($line, $char);
  }

  /**
   * Map the regex matching groups to a message severity. We look for either
   * a nonempty severity name group like 'error', or a group called 'severity'
   * with a valid name.
   *
   * @param dict Captured groups from regex.
   * @return const  @{class:ArcanistLintSeverity} constant.
   *
   * @task parse
   */
  private function getMatchSeverity(array $match) {
    $map = array(
      'error'    => ArcanistLintSeverity::SEVERITY_ERROR,
      'warning'  => ArcanistLintSeverity::SEVERITY_WARNING,
      'autofix'  => ArcanistLintSeverity::SEVERITY_AUTOFIX,
      'advice'   => ArcanistLintSeverity::SEVERITY_ADVICE,
      'disabled' => ArcanistLintSeverity::SEVERITY_DISABLED,
    );

    $severity_name = strtolower(idx($match, 'severity'));

    foreach ($map as $name => $severity) {
      if (!empty($match[$name])) {
        return $severity;
      } else if ($severity_name == $name) {
        return $severity;
      }
    }

    return ArcanistLintSeverity::SEVERITY_ERROR;
  }

}
