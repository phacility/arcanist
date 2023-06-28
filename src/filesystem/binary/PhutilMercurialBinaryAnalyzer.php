<?php

final class PhutilMercurialBinaryAnalyzer
  extends PhutilBinaryAnalyzer {

  const BINARY = 'hg';

  const CAPABILITY_FILES = 'files';
  const CAPABILITY_INJECTION = 'injection';
  const CAPABILITY_TEMPLATE_PNODE = 'template_pnode';
  const CAPABILTIY_ANNOTATE_TEMPLATES = 'annotate_templates';

  protected function newBinaryVersion() {
    $future = id(new ExecFuture('hg --version --quiet'))
      ->setEnv(
        array(
          'HGPLAIN' => 1,
        ));

    list($err, $stdout) = $future->resolve();

    if ($err) {
      return null;
    }

    return self::parseMercurialBinaryVersion($stdout);
  }

  public static function parseMercurialBinaryVersion($stdout) {
    // NOTE: At least on OSX, recent versions of Mercurial report this
    // string in this format:
    //
    //   Mercurial Distributed SCM (version 3.1.1+20140916)

    $matches = null;
    $pattern = '/^Mercurial Distributed SCM \(version ([\d.]+)/m';
    if (preg_match($pattern, $stdout, $matches)) {
      return $matches[1];
    }

    return null;
  }

  /**
   * The `locate` command is deprecated as of Mercurial 3.2, to be replaced
   * with `files` command, which supports most of the same arguments. This
   * determines whether the new `files` command should be used instead of
   * the `locate` command.
   *
   * @return boolean  True if the version of Mercurial is new enough to support
   *   the `files` command, or false if otherwise.
   */
  public function isMercurialFilesCommandAvailable() {
    return self::versionHasCapability(
      $this->requireBinaryVersion(),
      self::CAPABILITY_FILES);
  }

  public function isMercurialVulnerableToInjection() {
    return self::versionHasCapability(
      $this->requireBinaryVersion(),
      self::CAPABILITY_INJECTION);
  }

  /**
   * When using `--template` the format for accessing individual parents
   * changed from `{p1node}` to `{p1.node}` in Mercurial 4.9.
   *
   * @return boolean  True if the version of Mercurial is new enough to support
   *   the `{p1.node}` format in templates, or false if otherwise.
   */
  public function isMercurialTemplatePnodeAvailable() {
    return self::versionHasCapability(
      $this->requireBinaryVersion(),
      self::CAPABILITY_TEMPLATE_PNODE);
  }

  /**
   * The `hg annotate` command did not accept the `--template` argument until
   * version 4.6. It appears to function in version 4.5 however it's not
   * documented and wasn't announced until the 4.6 release.
   *
   * @return boolean  True if the version of Mercurial is new enough to support
   *   the `--template` option when using `hg annotate`, or false if otherwise.
   */
  public function isMercurialAnnotateTemplatesAvailable() {
    return self::versionHasCapability(
      $this->requireBinaryVersion(),
      self::CAPABILTIY_ANNOTATE_TEMPLATES);
  }


  public static function versionHasCapability(
    $mercurial_version,
    $capability) {

    switch ($capability) {
      case self::CAPABILITY_FILES:
        return version_compare($mercurial_version, '3.2', '>=');
      case self::CAPABILITY_INJECTION:
        return version_compare($mercurial_version, '3.2.4', '<');
      case self::CAPABILITY_TEMPLATE_PNODE:
        return version_compare($mercurial_version, '4.9', '>=');
      case self::CAPABILTIY_ANNOTATE_TEMPLATES:
        return version_compare($mercurial_version, '4.6', '>=');
      default:
        throw new Exception(
          pht(
            'Unknown Mercurial capability "%s".',
            $capability));
    }

  }


}
