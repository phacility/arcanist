<?php

final class ArcanistCheckstyleXMLLintRenderer extends ArcanistLintRenderer {

  const RENDERERKEY = 'xml';

  private $writer;

  private function getWriter() {
    if (!$this->writer) {
      $xml_extension = 'XMLWriter';

      if (!extension_loaded($xml_extension)) {
        throw new Exception(
          pht(
            'Lint can not be output into "%s" format because the PHP "%s" '.
            'extension is not installed. Install the extension or choose a '.
            'different output format.',
            self::RENDERERKEY,
            $xml_extension));
      }

      $writer = new XMLWriter();
      $writer->openMemory();
      $writer->setIndent(true);
      $writer->setIndentString('  ');

      $this->writer = $writer;
    }

    return $this->writer;
  }

  public function willRenderResults() {
    $writer = $this->getWriter();

    $writer->startDocument('1.0', 'UTF-8');
    $writer->startElement('checkstyle');
    $writer->writeAttribute('version', '4.3');
    $this->writeOut($writer->flush());
  }

  public function renderLintResult(ArcanistLintResult $result) {
    $writer = $this->getWriter();

    $writer->startElement('file');
    $writer->writeAttribute('name', $result->getPath());

    foreach ($result->getMessages() as $message) {
      $writer->startElement('error');

      $writer->writeAttribute('line', $message->getLine());
      $writer->writeAttribute('column', $message->getChar());
      $writer->writeAttribute('severity',
        $this->getStringForSeverity($message->getSeverity()));
      $writer->writeAttribute('message', $message->getDescription());
      $writer->writeAttribute('source', $message->getCode());

      $writer->endElement();
    }

    $writer->endElement();
    $this->writeOut($writer->flush());
  }

  public function didRenderResults() {
    $writer = $this->getWriter();

    $writer->endElement();
    $writer->endDocument();
    $this->writeOut($writer->flush());
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
