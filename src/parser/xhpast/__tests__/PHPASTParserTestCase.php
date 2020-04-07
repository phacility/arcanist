<?php

final class PHPASTParserTestCase extends PhutilTestCase {

  public function testParser() {
    if (!PhutilXHPASTBinary::isAvailable()) {
      try {
        PhutilXHPASTBinary::build();
      } catch (Exception $ex) {
        $this->assertSkipped(
          pht('%s is not built or not up to date.', 'xhpast'));
      }
    }

    $dir = dirname(__FILE__).'/data/';
    foreach (Filesystem::listDirectory($dir) as $file) {
      if (preg_match('/\.test$/', $file)) {
        $this->executeParserTest($file, $dir.$file);
      }
    }
  }

  private function executeParserTest($name, $file) {
    $contents = Filesystem::readFile($file);
    $contents = preg_split('/^~{4,}\n/m', $contents);

    if (count($contents) < 2) {
      throw new Exception(
        pht(
          "Expected '%s' separating test case and results.",
          '~~~~~~~~~~'));
    }

    list($data, $options, $expect) = array_merge($contents, array(null));

    $options = id(new PhutilSimpleOptions())->parse($options);

    $type = null;
    foreach ($options as $key => $value) {
      switch ($key) {
        case 'pass':
        case 'fail-syntax':
        case 'fail-parse':
          if ($type !== null) {
            throw new Exception(
              pht(
                'Test file "%s" unexpectedly specifies multiple expected '.
                'test outcomes.',
                $name));
          }
          $type = $key;
          break;
        case 'comment':
          // Human readable comment providing test case information.
          break;
        case 'rtrim':
          // Allows construction of tests which rely on EOF without newlines.
          $data = rtrim($data);
          break;
        default:
          throw new Exception(
            pht(
              'Test file "%s" has unknown option "%s" in its options '.
              'string.',
              $name,
              $key));
      }
    }

    if ($type === null) {
      throw new Exception(
        pht(
          'Test file "%s" does not specify a test result (like "pass") in '.
          'its options string.',
          $name));
    }

    $future = PhutilXHPASTBinary::getParserFuture($data);
    list($err, $stdout, $stderr) = $future->resolve();

    switch ($type) {
      case 'pass':
        $this->assertEqual(0, $err, pht('Exit code for "%s".', $name));

        if (!strlen($expect)) {
          // If there's no "expect" data in the test case, that's OK.
          break;
        }

        try {
          $stdout = phutil_json_decode($stdout);
        } catch (PhutilJSONParserException $ex) {
          throw new PhutilProxyException(
            pht(
              'Output for test file "%s" is not valid JSON.',
              $name),
            $ex);
        }

        $stdout_nice = $this->newReadableAST($stdout, $data);

        $this->assertEqual(
          $expect,
          $stdout_nice,
          pht('Parser output for "%s".', $name));
        break;
      case 'fail-syntax':
        $this->assertEqual(1, $err, pht('Exit code for "%s".', $name));
        $this->assertTrue(
          (bool)preg_match('/syntax error/', $stderr),
          pht('Expect "syntax error" in stderr or "%s".', $name));
        break;
      default:
        throw new Exception(
          pht(
            'Unknown PHPAST parser test type "%s"!',
            $type));
    }
  }

  /**
   * Build a human-readable, stable, relatively diff-able string representing
   * an AST (both the node tree itself and the accompanying token stream) for
   * use in unit tests.
   */
  private function newReadableAST(array $data, $source) {
    $tree = new XHPASTTree($data['tree'], $data['stream'], $source);

    $root = $tree->getRootNode();

    $depth = 0;
    $list = $this->newReadableTreeLines($root, $depth);

    return implode('', $list);
  }

  private function newReadableTreeLines(AASTNode $node, $depth) {
    $out = array();

    try {
      $type_name = $node->getTypeName();
    } catch (Exception $ex) {
      $type_name = sprintf('<INVALID TYPE "%s">', $node->getTypeID());
    }

    $out[] = $this->newBlock($depth, '*', $type_name);

    $tokens = $node->getTokens();

    if ($tokens) {
      $l = head_key($tokens);
      $r = last_key($tokens);
    } else {
      $l = null;
      $r = null;
    }

    $items = array();

    $child_token_map = array();

    $children = $node->getChildren();
    foreach ($children as $child) {
      $child_tokens = $child->getTokens();

      if ($child_tokens) {
        $child_l = head_key($child_tokens);
        $child_r = last_key($child_tokens);
      } else {
        $child_l = null;
        $child_r = null;
      }

      if ($l !== null) {
        for ($ii = $l; $ii < $child_l; $ii++) {
          $items[] = $tokens[$ii];
        }
      }

      $items[] = $child;

      if ($child_r !== null) {
        // NOTE: In some cases, child nodes do not appear in token order.
        // That is, the 4th child of a node may use tokens that appear
        // between children 2 and 3. Ideally, we wouldn't have cases of
        // this and wouldn't have a positional AST.

        // Work around this by: never moving the token cursor backwards; and
        // explicitly preventing tokens appearing in any child from being
        // printed at top level.

        for ($ii = $child_l; $ii <= $child_r; $ii++) {
          if (!isset($tokens[$ii])) {
            continue;
          }
          $child_token_map[$tokens[$ii]->getTokenID()] = true;
        }

        $l = max($l, $child_r + 1);
      } else {
        $l = null;
      }
    }

    if ($l !== null) {
      for ($ii = $l; $ii <= $r; $ii++) {
        $items[] = $tokens[$ii];
      }
    }

    // See above. If we have tokens in the list which are part of a
    // child node that appears later, remove them now.
    foreach ($items as $key => $item) {
      if ($item instanceof AASTToken) {
        $token = $item;
        $token_id = $token->getTokenID();

        if (isset($child_token_map[$token_id])) {
          unset($items[$key]);
        }
      }
    }

    foreach ($items as $item) {
      if ($item instanceof AASTNode) {
        $lines = $this->newReadableTreeLines($item, $depth + 1);
        foreach ($lines as $line) {
          $out[] = $line;
        }
      } else {
        $token_value = $item->getValue();

        $out[] = $this->newBlock($depth + 1, '>', $token_value);
      }
    }

    return $out;
  }

  private function newBlock($depth, $type, $text) {
    $output_width = 80;
    $usable_width = ($output_width - $depth - 2);

    $must_escape = false;

    // We must escape the text if it isn't just simple printable characters.
    if (preg_match('/[ \\\\\\r\\n\\t\\"]/', $text)) {
      $must_escape = true;
    }

    // We must escape the text if it has trailing whitespace.
    if (preg_match('/ \z/', $text)) {
      $must_escape = true;
    }

    // We must escape the text if it won't fit on a single line.
    if (strlen($text) > $usable_width) {
      $must_escape = true;
    }

    if (!$must_escape) {
      $lines = array($text);
    } else {
      $vector = phutil_utf8v_combined($text);

      $escape_map = array(
        "\r" => '\\r',
        "\n" => '\\n',
        "\t" => '\\t',
        '"' => '\\"',
        '\\' => '\\',
      );

      $escaped = array();
      foreach ($vector as $key => $word) {
        if (isset($escape_map[$word])) {
          $vector[$key] = $escape_map[$word];
        }
      }


      $line_l = '"';
      $line_r = '"';

      $max_width = ($usable_width - strlen($line_l) - strlen($line_r));

      $line = '';
      $len = 0;

      $lines = array();
      foreach ($vector as $word) {
        $word_length = phutil_utf8_console_strlen($word);

        if ($len + $word_length > $max_width) {
          $lines[] = $line_l.$line.$line_r;

          $line = '';
          $len = 0;
        }

        $line .= $word;
        $len += $word_length;
      }

      $lines[] = $line_l.$line.$line_r;
    }

    $is_first = true;
    $indent = str_repeat(' ', $depth);

    $output = array();
    foreach ($lines as $line) {
      if ($is_first) {
        $marker = $type;
        $is_first = false;
      } else {
        $marker = '.';
      }

      $output[] = sprintf(
        "%s%s %s\n",
        $indent,
        $marker,
        $line);
    }

    return implode('', $output);
  }

}
