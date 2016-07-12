<?php

/**
 * Enforces basic spelling. Spelling inside code is actually pretty hard to
 * get right without false positives. I take a conservative approach and just
 * use a blacklisted set of words that are commonly spelled incorrectly.
 */
final class ArcanistSpellingLinter extends ArcanistLinter {

  const LINT_SPELLING_EXACT   = 1;
  const LINT_SPELLING_PARTIAL = 2;

  private $dictionaries     = array();
  private $exactWordRules   = array();
  private $partialWordRules = array();

  public function getInfoName() {
    return pht('Spellchecker');
  }

  public function getInfoDescription() {
    return pht('Detects common misspellings of English words.');
  }

  public function getLinterName() {
    return 'SPELL';
  }

  public function getLinterConfigurationName() {
    return 'spelling';
  }

  public function getLinterConfigurationOptions() {
    $options = array(
      'spelling.dictionaries' => array(
        'type' => 'optional list<string>',
        'help' => pht('Pass in custom dictionaries.'),
      ),
    );

    return $options + parent::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value) {
    switch ($key) {
      case 'spelling.dictionaries':
        foreach ($value as $dictionary) {
          $this->loadDictionary($dictionary);
        }
        return;
    }

    return parent::setLinterConfigurationValue($key, $value);
  }

  public function loadDictionary($path) {
    $root = $this->getProjectRoot();
    $path = Filesystem::resolvePath($path, $root);

    $dict = phutil_json_decode(Filesystem::readFile($path));
    PhutilTypeSpec::checkMap(
      $dict,
      array(
        'rules' => 'map<string, map<string, string>>',
      ));
    $rules = $dict['rules'];

    $this->dictionaries[] = $path;
    $this->exactWordRules = array_merge(
      $this->exactWordRules,
      idx($rules, 'exact', array()));
    $this->partialWordRules = array_merge(
      $this->partialWordRules,
      idx($rules, 'partial', array()));
  }

  public function addExactWordRule($misspelling, $correction) {
    $this->exactWordRules = array_merge(
      $this->exactWordRules,
      array($misspelling => $correction));
    return $this;
  }

  public function addPartialWordRule($misspelling, $correction) {
    $this->partialWordRules = array_merge(
      $this->partialWordRules,
      array($misspelling => $correction));
    return $this;
  }

  public function getLintSeverityMap() {
    return array(
      self::LINT_SPELLING_EXACT   => ArcanistLintSeverity::SEVERITY_WARNING,
      self::LINT_SPELLING_PARTIAL => ArcanistLintSeverity::SEVERITY_WARNING,
    );
  }

  public function getLintNameMap() {
    return array(
      self::LINT_SPELLING_EXACT   => pht('Possible Spelling Mistake'),
      self::LINT_SPELLING_PARTIAL => pht('Possible Spelling Mistake'),
    );
  }

  public function lintPath($path) {
    // TODO: This is a bit hacky. If no dictionaries were specified, then add
    // the default dictionary.
    if (!$this->dictionaries) {
      $root = dirname(phutil_get_library_root('arcanist'));
      $this->loadDictionary($root.'/resources/spelling/english.json');
    }

    foreach ($this->exactWordRules as $misspelling => $correction) {
      $this->checkExactWord($path, $misspelling, $correction);
    }

    foreach ($this->partialWordRules as $misspelling => $correction) {
      $this->checkPartialWord($path, $misspelling, $correction);
    }
  }

  private function checkExactWord($path, $word, $correction) {
    $text = $this->getData($path);
    $matches = array();
    $num_matches = preg_match_all(
      '#\b'.preg_quote($word, '#').'\b#i',
      $text,
      $matches,
      PREG_OFFSET_CAPTURE);
    if (!$num_matches) {
      return;
    }
    foreach ($matches[0] as $match) {
      $original = $match[0];
      $replacement = self::fixLetterCase($correction, $original);
      $this->raiseLintAtOffset(
        $match[1],
        self::LINT_SPELLING_EXACT,
        pht(
          "Possible spelling error. You wrote '%s', but did you mean '%s'?",
          $word,
          $correction),
        $original,
        $replacement);
    }
  }

  private function checkPartialWord($path, $word, $correction) {
    $text = $this->getData($path);
    $pos = 0;
    while ($pos < strlen($text)) {
      $next = stripos($text, $word, $pos);
      if ($next === false) {
        return;
      }
      $original = substr($text, $next, strlen($word));
      $replacement = self::fixLetterCase($correction, $original);
      $this->raiseLintAtOffset(
        $next,
        self::LINT_SPELLING_PARTIAL,
        pht(
          "Possible spelling error. You wrote '%s', but did you mean '%s'?",
          $word,
          $correction),
        $original,
        $replacement);
      $pos = $next + 1;
    }
  }

  public static function fixLetterCase($string, $case) {
    switch ($case) {
      case strtolower($case):
        return strtolower($string);
      case strtoupper($case):
        return strtoupper($string);
      case ucwords(strtolower($case)):
        return ucwords(strtolower($string));
      default:
        return null;
    }
  }

}
