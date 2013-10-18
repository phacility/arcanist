<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Browse file in Diffusion.
 *
 * @group workflow
 */
final class ArcanistBrowseWorkflow extends ArcanistBaseWorkflow {

  public function getWorkflowName() {
    return 'browse';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **browse** [__options__] __path__
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          Supports: git
          Browse file in Diffusion (Web interface).

          Set the 'browser' value using 'arc set-config' to select a browser. If
          no browser is set, the command will try to guess which browser to use.
EOTEXT
      );
  }

  public function getArguments() {
    return array(
      'branch' => array(
        'param' => 'branch_name',
        'help' =>
          "Select branch name to view (On server). Defaults to 'master'."
      ),
      '*' => 'paths',
    );
  }

  public function requiresWorkingCopy() {
    return true;
  }

  public function requiresConduit() {
    return true;
  }

  public function requiresAuthentication() {
    return true;
  }

  public function requiresRepositoryAPI() {
    return true;
  }

  public function run() {
    $repository_api = $this->getRepositoryAPI();
    $project_root = $this->getWorkingCopy()->getProjectRoot();

    $in_paths = $this->getArgument('paths');
    $paths = array();
    foreach ($in_paths as $key => $path) {
      $path = preg_replace('/:([0-9]+)$/', '$\1', $path);
      $full_path = Filesystem::resolvePath($path);

      $paths[$key] = Filesystem::readablePath(
        $full_path,
        $project_root);
    }

    if (!$paths) {
      throw new ArcanistUsageException("Specify a path to browse.");
    }

    $base_uri = $this->getBaseURI();
    $browser = $this->getBrowserCommand();

    foreach ($paths as $path) {
      $ret_code = phutil_passthru("%s %s", $browser, $base_uri . $path);
      if ($ret_code) {
        throw new ArcanistUsageException(
          "It seems we failed to open the browser; perhaps you should try to ".
          "set the 'browser' config option. The command we tried to use was: ".
          $browser);
      }
    }

    return 0;
  }

  private function getBaseURI() {
    $conduit = $this->getConduit();
    $project_id = $this->getWorkingCopy()->getProjectID();
    $project_info = $this->getConduit()->callMethodSynchronous(
      'arcanist.projectinfo',
      array(
        'name' => $project_id,
      ));

    $repo_info = $project_info['repository'];
    $branch = $this->getArgument('branch', 'master');

    return $repo_info['uri'].'browse/'.$branch.'/';
  }

  private function getBrowserCommand() {
    $config = $this->getConfigFromAnySource('browser');
    if ($config) {
      return $config;
    }

    if (phutil_is_windows()) {
      return "start";
    }

    $candidates = array("sensible-browser", "xdg-open", "open");
    // on many Linuxes, "open" exists and is not the right program.

    foreach ($candidates as $cmd) {
      list($ret_code) = exec_manual("which %s", $cmd);
      if ($ret_code == 0) {
        return $cmd;
      }
    }

    throw new ArcanistUsageException(
      "Could not find a browser to run; Try setting the 'browser' option " .
      "using arc set-config.");
  }
}
