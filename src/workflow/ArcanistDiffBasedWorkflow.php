<?php

/**
 * Sends changes from your working copy to Differential for code review.
 *
 * @task lintunit   Lint and Unit Tests
 * @task message    Commit and Update Messages
 * @task diffspec   Diff Specification
 * @task diffprop   Diff Properties
 */
abstract class ArcanistDiffBasedWorkflow extends ArcanistWorkflow {

    const SUCCESS = 'success';
    const VALIDATION_OK = 'ok';
    const STAGING_PUSHED = 'pushed';
    const STAGING_USER_SKIP = 'user.skip';
    const STAGING_DIFF_RAW = 'diff.raw';
    const STAGING_REPOSITORY_UNKNOWN = 'repository.unknown';
    const STAGING_REPOSITORY_UNAVAILABLE = 'repository.unavailable';
    const STAGING_REPOSITORY_UNSUPPORTED = 'repository.unsupported';
    const STAGING_REPOSITORY_UNCONFIGURED = 'repository.unconfigured';
    const STAGING_CLIENT_UNSUPPORTED = 'client.unsupported';

    protected function validateStagingSetup() {
        if ($this->getArgument('skip-staging')) {
            $this->writeInfo(
                pht('SKIP STAGING'),
                pht('Flag --skip-staging was specified.'));
            return array(false, self::STAGING_USER_SKIP, null, null);
        }

        if ($this->isRawDiffSource()) {
            $this->writeInfo(
                pht('SKIP STAGING'),
                pht('Raw changes can not be pushed to a staging area.'));
            return array(false, self::STAGING_DIFF_RAW, null, null);
        }

        if (!$this->getRepositoryPHID()) {
            $this->writeInfo(
                pht('SKIP STAGING'),
                pht('Unable to determine repository for this change.'));
            return array(false, self::STAGING_REPOSITORY_UNKNOWN, null, null);
        }

        $staging = $this->getRepositoryStagingConfiguration();
        if ($staging === null) {
            $this->writeInfo(
                pht('SKIP STAGING'),
                pht('The server does not support staging areas.'));
            return array(false, self::STAGING_REPOSITORY_UNAVAILABLE, null, null);
        }

        $supported = idx($staging, 'supported');
        if (!$supported) {
            $this->writeInfo(
                pht('SKIP STAGING'),
                pht('Phabricator does not support staging areas for this repository.'));
            return array(false, self::STAGING_REPOSITORY_UNSUPPORTED, null, null);
        }

        $staging_uri = idx($staging, 'uri');
        if (!$staging_uri) {
            $this->writeInfo(
                pht('SKIP STAGING'),
                pht('No staging area is configured for this repository.'));
            return array(false, self::STAGING_REPOSITORY_UNCONFIGURED, null, null);
        } else if ($this->getConfigFromAnySource('uber.diff.staging.uri.replace')) {
            $remote_name = $this->getRemoteName($staging_uri);
            if (strlen($remote_name) > 0) {
                $staging_uri = $remote_name;
            }
        }

        $api = $this->getRepositoryAPI();
        if (!($api instanceof ArcanistGitAPI)) {
            $this->writeInfo(
                pht('SKIP STAGING'),
                pht('This client version does not support staging this repository.'));
            return array(false, self::STAGING_CLIENT_UNSUPPORTED, null, null);
        }
        return array(true, self::VALIDATION_OK, $staging, $staging_uri);
    }

    public function isRawDiffSource() {
        return $this->getArgument('raw') || $this->getArgument('raw-command');
    }

    /**
     * Finds the git remote name for given git remote url.
     *
     * @return string Remote name if there is any, or empty string otherwise.
      */
    protected function getRemoteName($remote_url) {
      list($git_remote_stdout, $git_remote_stderr) = execx('git remote -v');
      $git_remote_list = explode("\n", $git_remote_stdout);

      foreach ($git_remote_list as $key => $line) {
        if (strlen($line) > 0) {
          // Every line has the following format:
          // <remote_name>TAB<remote_url>SPACE([fetch|push|...])
          $pat = '/(?P<remote_name>[^\s]+)\s+(?P<remote_url>[^\s]+)\s+([^\s]+)/';
          $matches = array();
          preg_match($pat, $line, $matches);
          if (array_key_exists('remote_url', $matches) &&
            $matches['remote_url'] == $remote_url) {
            return $matches['remote_name'];
          }
        }
      }
      return '';
    }
}
