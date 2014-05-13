<?php

/**
 * Shows lint messages to the user.
 *
 * @group lint
 */
final class ArcanistLintCheckstyleXMLRenderer extends ArcanistLintRenderer {

  private $writer;

  public function __construct() {
    $this->writer = new XMLWriter();
    $this->writer->openMemory();
    $this->writer->setIndent(true);
    $this->writer->setIndentString('  ');

    $this->writer->startDocument('1.0', 'UTF-8');
    $this->writer->startElement('checkstyle');
    $this->writer->writeAttribute('version', '4.3');
  }

  public function renderLintResult(ArcanistLintResult $result) {
    $this->writer->startElement('file');
    $this->writer->writeAttribute('name', $result->getPath());

    foreach ($result->getMessages() as $message) {
      $this->writer->startElement('error');

      $this->writer->writeAttribute('line', $message->getLine());
      $this->writer->writeAttribute('column', $message->getChar());
      $this->writer->writeAttribute('severity',
        ArcanistLintSeverity::getStringForSeverity($message->getSeverity()));
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
}
