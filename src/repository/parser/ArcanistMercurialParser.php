<?php

/**
 * Parses output from various "hg" commands into structured data. This class
 * provides low-level APIs for reading "hg" output.
 *
 * @task  parse Parsing "hg" Output
 */
final class ArcanistMercurialParser extends Phobject {


/* -(  Parsing "hg" Output  )------------------------------------------------ */


  /**
   * Parse the output of "hg status". This provides detailed information, you
   * can get less detailed information with @{method:parseMercurialStatus}. In
   * particular, this will parse copy sources as per "hg status -C".
   *
   * @param string The stdout from running an "hg status" command.
   * @return dict Map of paths to status dictionaries.
   * @task parse
   */
  public static function parseMercurialStatusDetails($stdout) {
    $result = array();

    $stdout = trim($stdout);
    if (!strlen($stdout)) {
      return $result;
    }

    $last_path = null;
    $lines = explode("\n", $stdout);
    foreach ($lines as $line) {
      $flags = 0;
      if ($line[1] !== ' ') {
        throw new Exception(
          pht(
            "Unparsable Mercurial status line '%s'.",
            $line));
      }
      $code = $line[0];
      $path = substr($line, 2);
      switch ($code) {
        case 'A':
          $flags |= ArcanistRepositoryAPI::FLAG_ADDED;
          break;
        case 'R':
          $flags |= ArcanistRepositoryAPI::FLAG_DELETED;
          break;
        case 'M':
          $flags |= ArcanistRepositoryAPI::FLAG_MODIFIED;
          break;
        case 'C':
          // This is "clean" and included only for completeness, these files
          // have not been changed.
          break;
        case '!':
          $flags |= ArcanistRepositoryAPI::FLAG_MISSING;
          break;
        case '?':
          $flags |= ArcanistRepositoryAPI::FLAG_UNTRACKED;
          break;
        case 'I':
          // This is "ignored" and included only for completeness.
          break;
        case ' ':
          // This shows the source of a file move, so update the last file we
          // parsed to set its source.
          if ($last_path === null) {
            throw new Exception(
              pht(
                "Unexpected copy source in %s, '%s'.",
                'hg status',
                $line));
          }
          $result[$last_path]['from'] = $path;
          continue 2;
        default:
          throw new Exception(pht("Unknown Mercurial status '%s'.", $code));
      }

      $result[$path] = array(
        'flags' => $flags,
        'from'  => null,
      );
      $last_path = $path;
    }

    return $result;
  }


  /**
   * Parse the output of "hg status". This provides only basic information, you
   * can get more detailed information by invoking
   * @{method:parseMercurialStatusDetails}.
   *
   * @param string The stdout from running an "hg status" command.
   * @return dict Map of paths to ArcanistRepositoryAPI status flags.
   * @task parse
   */
  public static function parseMercurialStatus($stdout) {
    $result = self::parseMercurialStatusDetails($stdout);
    return ipull($result, 'flags');
  }


  /**
   * Parse the output of "hg log". This also parses "hg outgoing", "hg parents",
   * and other similar commands. This assumes "--style default".
   *
   * @param string The stdout from running an "hg log" command.
   * @return list List of dictionaries with commit information.
   * @task parse
   */
  public static function parseMercurialLog($stdout) {
    $result = array();

    $stdout = trim($stdout);
    if (!strlen($stdout)) {
      return $result;
    }

    $chunks = explode("\n\n", $stdout);
    foreach ($chunks as $chunk) {
      $commit = array();
      $lines = explode("\n", $chunk);
      foreach ($lines as $line) {
        if (preg_match('/^(comparing with|searching for changes)/', $line)) {
          // These are sent to stdout when you run "hg outgoing" although the
          // format is otherwise identical to "hg log".
          continue;
        }

        if (preg_match('/^remote:/', $line)) {
          // This indicates remote error in "hg outgoing".
          continue;
        }

        list($name, $value) = explode(':', $line, 2);
        $value = trim($value);
        switch ($name) {
          case 'user':
            $commit['user'] = $value;
            break;
          case 'date':
            $commit['date'] = strtotime($value);
            break;
          case 'summary':
            $commit['summary'] = $value;
            break;
          case 'changeset':
            list($local, $rev) = explode(':', $value, 2);
            $commit['local'] = $local;
            $commit['rev'] = $rev;
            break;
          case 'parent':
            if (empty($commit['parents'])) {
              $commit['parents'] = array();
            }
            list($local, $rev) = explode(':', $value, 2);
            $commit['parents'][] = array(
              'local' => $local,
              'rev'   => $rev,
            );
            break;
          case 'branch':
            $commit['branch'] = $value;
            break;
          case 'tag':
            $commit['tag'] = $value;
            break;
          case 'bookmark':
            $commit['bookmark'] = $value;
            break;
          case 'obsolete':
          case 'instability':
            // These are extra fields added by the "evolve" extension even
            // if HGPLAIN=1 is set. See PHI502 and PHI718.
            break;
          default:
            throw new Exception(
              pht("Unknown Mercurial log field '%s'!", $name));
        }
      }
      $result[] = $commit;
    }

    return $result;
  }


  /**
   * Parse the output of "hg branches".
   *
   * @param string The stdout from running an "hg branches" command.
   * @return list A list of dictionaries with branch information.
   * @task parse
   */
  public static function parseMercurialBranches($stdout) {
    $stdout = rtrim($stdout, "\n");
    if (!strlen($stdout)) {
      // No branches; commonly, this occurs in a newly initialized repository.
      return array();
    }

    $lines = explode("\n", $stdout);

    $branches = array();
    foreach ($lines as $line) {
      $matches = null;

      // Output of "hg branches" normally looks like:
      //
      //  default                    15101:a21ccf4412d5
      //
      // ...but may also have human-readable cues like:
      //
      //  stable                     15095:ec222a29bdf0 (inactive)
      //
      // See the unit tests for more examples.
      $regexp = '/^(\S+(?:\s+\S+)*)\s+(\d+):([a-f0-9]+)(\s+\\(inactive\\))?$/';

      if (!preg_match($regexp, $line, $matches)) {
        throw new Exception(
          pht(
            "Failed to parse '%s' output: %s",
            'hg branches',
            $line));
      }
      $branches[$matches[1]] = array(
        'local'   => $matches[2],
        'rev'     => $matches[3],
      );
    }

    return $branches;
  }

}
