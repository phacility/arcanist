<?php

final class ArcanistConsoleLintRendererTestCase
  extends PhutilTestCase {

  public function testRendering() {
    $midline_original = <<<EOTEXT
import apple;
import banana;
import cat;
import dog;
EOTEXT;

    $midline_replacement = <<<EOTEXT
import apple;
import banana;

import cat;
import dog;
EOTEXT;

    $remline_original = <<<EOTEXT
import apple;
import banana;


import cat;
import dog;
EOTEXT;

    $remline_replacement = <<<EOTEXT
import apple;
import banana;

import cat;
import dog;
EOTEXT;

    $map = array(
      'simple' => array(
        'line' => 1,
        'char' => 1,
        'original' => 'a',
        'replacement' => 'z',
      ),
      'inline' => array(
        'line' => 1,
        'char' => 7,
        'original' => 'cat',
        'replacement' => 'dog',
      ),

      // In this test, the original and replacement texts have a large
      // amount of overlap.
      'overlap' => array(
        'line' => 1,
        'char' => 1,
        'original' => 'tantawount',
        'replacement' => 'tantamount',
      ),

      'newline' => array(
        'line' => 6,
        'char' => 1,
        'original' => "\n",
        'replacement' => '',
      ),

      'addline' => array(
        'line' => 3,
        'char' => 1,
        'original' => '',
        'replacement' => "cherry\n",
      ),

      'addlinesuffix' => array(
        'line' => 2,
        'char' => 7,
        'original' => '',
        'replacement' => "\ncherry",
      ),

      'xml' => array(
        'line' => 3,
        'char' => 6,
        'original' => '',
        'replacement' => "\n",
      ),

      'caret' => array(
        'line' => 2,
        'char' => 13,
        'name' => 'Fruit Misinformation',
        'description' => 'Arguably untrue.',
      ),

      'original' => array(
        'line' => 1,
        'char' => 4,
        'original' => 'should of',
      ),

      'midline' => array(
        'line' => 1,
        'char' => 1,
        'original' => $midline_original,
        'replacement' => $midline_replacement,
      ),

      'remline' => array(
        'line' => 1,
        'char' => 1,
        'original' => $remline_original,
        'replacement' => $remline_replacement,
      ),

      'extrawhitespace' => array(
        'line' => 2,
        'char' => 1,
        'original' => "\n",
        'replacement' => '',
      ),

      'eofnewline' => array(
        'line' => 1,
        'char' => 7,
        'original' => '',
        'replacement' => "\n",
      ),

      'eofmultilinechar' => array(
        'line' => 5,
        'char' => 3,
        'original' => '',
        'replacement' => "\nX\nY\n",
      ),

      'eofmultilineline' => array(
        'line' => 6,
        'char' => 1,
        'original' => '',
        'replacement' => "\nX\nY\n",
      ),

      'rmmulti' => array(
        'line' => 2,
        'char' => 1,
        'original' => "\n",
        'replacement' => '',
      ),

      'rmmulti2' => array(
        'line' => 1,
        'char' => 2,
        'original' => "\n",
        'replacement' => '',
      ),

    );

    $defaults = array(
      'severity' => ArcanistLintSeverity::SEVERITY_WARNING,
      'name' => 'Lint Warning',
      'path' => 'path/to/example.c',
      'description' => 'Consider this.',
      'code' => 'WARN123',
    );

    foreach ($map as $key => $test_case) {
      $data = $this->readTestData("{$key}.txt");
      $expect = $this->readTestData("{$key}.expect");

      $test_case = $test_case + $defaults;

      $path = $test_case['path'];
      $severity = $test_case['severity'];
      $name = $test_case['name'];
      $description = $test_case['description'];
      $code = $test_case['code'];

      $line = $test_case['line'];
      $char = $test_case['char'];

      $original = idx($test_case, 'original');
      $replacement = idx($test_case, 'replacement');

      $message = id(new ArcanistLintMessage())
        ->setPath($path)
        ->setSeverity($severity)
        ->setName($name)
        ->setDescription($description)
        ->setCode($code)
        ->setLine($line)
        ->setChar($char)
        ->setOriginalText($original)
        ->setReplacementText($replacement);

      $result = id(new ArcanistLintResult())
        ->setPath($path)
        ->setData($data)
        ->addMessage($message);

      $renderer = id(new ArcanistConsoleLintRenderer())
        ->setTestableMode(true);

      try {
        PhutilConsoleFormatter::disableANSI(true);

        $tmp = new TempFile();
        $renderer->setOutputPath($tmp);
        $renderer->renderLintResult($result);
        $actual = Filesystem::readFile($tmp);
        unset($tmp);

        PhutilConsoleFormatter::disableANSI(false);
      } catch (Exception $ex) {
        PhutilConsoleFormatter::disableANSI(false);
        throw $ex;
      }

      $this->assertEqual(
        $expect,
        $actual,
        pht(
          'Lint rendering for "%s".',
          $key));
    }
  }

  private function readTestData($filename) {
    $path = dirname(__FILE__).'/data/'.$filename;
    $data = Filesystem::readFile($path);

    // If we find "~~~" at the end of the file, get rid of it and any whitespace
    // afterwards. This allows specifying data files with trailing empty
    // lines.
    $data = preg_replace('/~~~\s*\z/', '', $data);

    // Trim "~" off the ends of lines. This allows the "expect" file to test
    // for trailing whitespace without actually containing trailing
    // whitespace.
    $data = preg_replace('/~$/m', '', $data);

    return $data;
  }

}
