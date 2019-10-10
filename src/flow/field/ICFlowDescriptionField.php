<?php

final class ICFlowDescriptionField extends ICFlowField {

  public function getFieldKey() {
    return 'description';
  }

  public function getSummary() {
    return pht('The title of the commit at HEAD for the branch.');
  }

  public function getDefaultFieldOrder() {
    return 4;
  }

  public function validateOption($option, $value) {
    if ($option !== 'length') {
      return parent::validateOption($option, $value);
    }
    $value = (int)$value;
    if ($value < 1 || $value > 80) {
      throw new Exception(
        pht('Description length must be between 1 and 80 characters.'));
    }
    return $value;
  }

  protected function getOptions() {
    return array_merge(parent::getOptions(), array(
      'length' => array(
        'summary' => pht(
          'Truncate display of descriptions exceeding this many characters. '.
          'Must be between 1 and 80, defaults to 50.'),
        'default' => 50,
      ),
    ));
  }

  protected function renderValues(array $values) {
    return (new PhutilUTF8StringTruncator())
      ->setMaximumGlyphs($this->getOptionValue('length'))
      ->truncateString(idx($values, 'description'));
  }

  public function getValues(ICFlowFeature $feature) {
    return array('description' => $feature->getHead()->getSubject());
  }

}
