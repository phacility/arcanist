<?php

final class ArcanistConsoleLintRendererTestCase
  extends PhutilTestCase {

  public function testRendering() {
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

      $renderer = new ArcanistConsoleLintRenderer();

      try {
        PhutilConsoleFormatter::disableANSI(true);
        $actual = $renderer->renderLintResult($result);
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
    return Filesystem::readFile($path);
  }

}
