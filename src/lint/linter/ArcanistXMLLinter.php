<?php

/**
 * A linter which uses [[http://php.net/simplexml | SimpleXML]] to detect
 * errors and potential problems in XML files.
 */
final class ArcanistXMLLinter extends ArcanistLinter {

  public function getInfoName() {
    return pht('SimpleXML Linter');
  }

  public function getInfoDescription() {
    return pht('Uses SimpleXML to detect formatting errors in XML files.');
  }

  public function getLinterName() {
    return 'XML';
  }

  public function getLinterConfigurationName() {
    return 'xml';
  }

  public function canRun() {
    return extension_loaded('libxml') && extension_loaded('simplexml');
  }

  public function getCacheVersion() {
    return LIBXML_VERSION;
  }

  public function lintPath($path) {
    libxml_use_internal_errors(true);
    libxml_clear_errors();

    if (simplexml_load_string($this->getData($path))) {
      // XML appears to be valid.
      return;
    }

    foreach (libxml_get_errors() as $error) {
      $message = id(new ArcanistLintMessage())
        ->setPath($path)
        ->setLine($error->line)
        ->setChar($error->column ? $error->column : null)
        ->setCode($this->getLintMessageFullCode($error->code))
        ->setName(pht('LibXML Error'))
        ->setDescription(trim($error->message));

      switch ($error->level) {
        case LIBXML_ERR_NONE:
          $message->setSeverity(ArcanistLintSeverity::SEVERITY_DISABLED);
          break;

        case LIBXML_ERR_WARNING:
          $message->setSeverity(ArcanistLintSeverity::SEVERITY_WARNING);
          break;

        case LIBXML_ERR_ERROR:
        case LIBXML_ERR_FATAL:
          $message->setSeverity(ArcanistLintSeverity::SEVERITY_ERROR);
          break;

        default:
          $message->setSeverity(ArcanistLintSeverity::SEVERITY_ADVICE);
          break;
      }

      $this->addLintMessage($message);
    }
  }

}
