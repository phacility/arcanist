<?php

final class ArcanistFlagWorkflow extends ArcanistWorkflow {

  private static $colorMap = array(
    0 => 'red',     // Red
    1 => 'yellow',  // Orange
    2 => 'yellow',  // Yellow
    3 => 'green',   // Green
    4 => 'blue',    // Blue
    5 => 'magenta', // Pink
    6 => 'magenta', // Purple
    7 => 'default', // Checkered
  );

  private static $colorSpec = array(
    'red' => 0,
    'r' => 0,
    0 => 0,
    'orange' => 1,
    'o' => 1,
    1 => 1,
    'yellow' => 2,
    'y' => 2,
    2 => 2,
    'green' => 3,
    'g' => 3,
    3 => 3,
    'blue' => 4,
    'b' => 4,
    4 => 4,
    'pink' => 5,
    'p' => 5,
    5 => 5,
    'purple' => 6,
    'v' => 6,
    6 => 6,
    'checkered' => 7,
    'c' => 7,
    7 => 7,
  );

  public function getWorkflowName() {
    return 'flag';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **flag** [__object__ ...]
      **flag** __object__ --clear
      **flag** __object__ [--edit] [--color __color__] [--note __note__]
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          In the first form, list objects you've flagged. You can provide the
          names of one or more objects (Maniphest tasks T#\##, Differential
          revisions D###, Diffusion references rXXX???, or PHIDs PHID-XXX-???)
          to print only flags for those objects.

          In the second form, clear an existing flag on one object.

          In the third form, create or update a flag on one object. Color
          defaults to blue and note to empty, but if you omit both you must
          pass --edit.
EOTEXT
      );
  }

  public function getArguments() {
    return array(
      '*' => 'objects',
      'clear' => array(
        'help' => pht('Delete the flag on an object.'),
      ),
      'edit' => array(
        'help' => pht('Edit the flag on an object.'),
      ),
      'color' => array(
        'param' => 'color',
        'help' => pht('Set the color of a flag.'),
      ),
      'note' => array(
        'param' => 'note',
        'help' => pht('Set the note on a flag.'),
      ),
    );
  }

  public function requiresConduit() {
    return true;
  }

  public function requiresAuthentication() {
    return true;
  }

  private static function flagWasEdited($flag, $verb) {
    $color = idx(self::$colorMap, $flag['color'], 'cyan');
    $note = $flag['note'];
    if ($note) {
      // Make sure notes that are long or have line breaks in them or
      // whatever don't mess up the formatting.
      $note = implode(' ', preg_split('/\s+/', $note));
      $note = ' ('.
        id(new PhutilUTF8StringTruncator())
        ->setMaximumGlyphs(40)
        ->setTerminator('...')
        ->truncateString($note).
        ')';
    }
    echo phutil_console_format(
      "<fg:{$color}>%s</fg> flag%s $verb!\n",
      $flag['colorName'],
      $note);
  }

  public function run() {
    $conduit = $this->getConduit();
    $objects = $this->getArgument('objects', array());
    $phids = array();

    $clear = $this->getArgument('clear');
    $edit = $this->getArgument('edit');
    // I don't trust PHP to distinguish 0 (red) from null.
    $color = $this->getArgument('color', -1);
    $note = $this->getArgument('note');
    $editing = $edit || ($color != -1) || $note;

    if ($editing && $clear) {
      throw new ArcanistUsageException(
        pht("You can't both edit and clear a flag."));
    }
    if (($editing || $clear) && count($objects) != 1) {
      throw new ArcanistUsageException(pht('Specify exactly one object.'));
    }

    if (!empty($objects)) {
      // First off, convert the passed objects to PHIDs.
      $handles = $conduit->callMethodSynchronous(
        'phid.lookup',
        array(
          'names' => $objects,
        ));
      foreach ($objects as $object) {
        if (isset($handles[$object])) {
          $phids[$object] = $handles[$object]['phid'];
        } else {
          echo pht(
            "%s doesn't exist.\n",
            phutil_console_format('**%s**', $object));
        }
      }
      if (empty($phids)) {
        // flag.query treats an empty objectPHIDs parameter as "don't use this
        // constraint". However, if the user gives a list of objects but none
        // of them exist and have flags, we shouldn't dump the full list on
        // them after telling them that. Conveniently, we already told them,
        // so we can go quit now.
        return 0;
      }
    }

    if ($clear) {
      // All right, we're going to clear a flag. First clear it. Then tell the
      // user we cleared it. Step four: profit!
      $flag = $conduit->callMethodSynchronous(
        'flag.delete',
        array(
          'objectPHID' => head($phids),
        ));
      if (!$flag) {
        echo pht(
          "%s has no flag to clear.\n",
          phutil_console_format('**%s**', $object));
      } else {
        self::flagWasEdited($flag, 'deleted');
      }
    } else if ($editing) {
      // Let's set some flags. Just like Minesweeper, but less distracting.
      $flag_params = array(
        'objectPHID' => head($phids),
      );
      if (isset(self::$colorSpec[$color])) {
        $flag_params['color'] = self::$colorSpec[strtolower($color)];
      }
      if ($note) {
        $flag_params['note'] = $note;
      }
      $flag = $conduit->callMethodSynchronous(
        'flag.edit',
        $flag_params);
      self::flagWasEdited($flag, $flag['new'] ? 'created' : 'edited');
    } else {
      // Okay, list mode. Let's find the flags, which we didn't need to do
      // otherwise because Conduit does it for us.
      $flags = ipull(
        $this->getConduit()->callMethodSynchronous(
          'flag.query',
          array(
            'ownerPHIDs' => array($this->getUserPHID()),
            'objectPHIDs' => array_values($phids),
          )),
        null,
        'objectPHID');
      foreach ($phids as $object => $phid) {
        if (!isset($flags[$phid])) {
          echo pht(
            "%s has no flag.\n",
            phutil_console_format('**%s**', $object));
        }
      }

      if (empty($flags)) {
        // If the user passed no object names, then we should print the full
        // list, but it's empty, so tell the user they have no flags.
        // If the user passed object names, we already told them all their
        // objects are nonexistent or unflagged.
        if (empty($objects)) {
          echo pht('You have no flagged objects.')."\n";
        }
      } else {
        // Print ALL the flags. With fancy formatting. Because fancy formatting
        // is _cool_.
        $name_len = 1 + max(array_map('strlen', ipull($flags, 'colorName')));
        foreach ($flags as $flag) {
          $color = idx(self::$colorMap, $flag['color'], 'cyan');
          echo phutil_console_format(
            "[<fg:{$color}>%s</fg>] %s\n",
            str_pad($flag['colorName'], $name_len),
            $flag['handle']['fullname']);
          if ($flag['note']) {
            $note = phutil_console_wrap($flag['note'], $name_len + 3);
            echo rtrim($note)."\n";
          }
        }
      }
    }
  }

}
