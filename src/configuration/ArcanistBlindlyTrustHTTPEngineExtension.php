<?php

final class ArcanistBlindlyTrustHTTPEngineExtension
  extends PhutilHTTPEngineExtension {

  const EXTENSIONKEY = 'arc.https.blind';

  private $domains = array();

  public function setDomains(array $domains) {
    foreach ($domains as $domain) {
      $this->domains[phutil_utf8_strtolower($domain)] = true;
    }
    return $this;
  }

  public function getExtensionName() {
    return pht('Arcanist HTTPS Trusted Domains');
  }

  public function shouldTrustAnySSLAuthorityForURI(PhutilURI $uri) {
    $domain = $uri->getDomain();
    $domain = phutil_utf8_strtolower($domain);
    return isset($this->domains[$domain]);
  }

}
