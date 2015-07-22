<?php

/**
 * Shows lint messages to the user.
 */
final class ArcanistCheckstyleXMLLintRenderer extends ArcanistLintRenderer {

  private $writer;

  public function __construct() {
    $this->writer = new XMLWriter();
    $this->writer->openMemory();
    $this->writer->setIndent(true);
    $this->writer->setIndentString('  ');
  }

  public function renderPreamble() {
    $this->writer->startDocument('1.0', 'UTF-8');
    $this->writer->startElement('checkstyle');
    $this->writer->writeAttribute('version', '4.3');
    return $this->writer->flush();
  }

  public function renderLintResult(ArcanistLintResult $result) {
    $this->writer->startElement('file');
    $this->writer->writeAttribute('name', $result->getPath());

    foreach ($result->getMessages() as $message) {
      $this->writer->startElement('error');

      $this->writer->writeAttribute('line', $message->getLine());
      $this->writer->writeAttribute('column', $message->getChar());
      $this->writer->writeAttribute('severity',
        $this->getStringForSeverity($message->getSeverity()));
      $this->writer->writeAttribute('message', $message->getDescription());
      $this->writer->writeAttribute('source', $message->getCode());

      $this->writer->endElement();
    }

    $this->writer->endElement();
    return $this->writer->flush();
  }

  public function renderOkayResult() {
    return '';
  }

  public function renderPostamble() {
    $this->writer->endElement();
    $this->writer->endDocument();
    return $this->writer->flush();
  }

  private function getStringForSeverity($severity) {
    switch ($severity) {
      case ArcanistLintSeverity::SEVERITY_ADVICE:
        return 'info';
      case ArcanistLintSeverity::SEVERITY_AUTOFIX:
        return 'info';
      case ArcanistLintSeverity::SEVERITY_WARNING:
        return 'warning';
      case ArcanistLintSeverity::SEVERITY_ERROR:
        return 'error';
      case ArcanistLintSeverity::SEVERITY_DISABLED:
        return 'ignore';
    }
  }

}
