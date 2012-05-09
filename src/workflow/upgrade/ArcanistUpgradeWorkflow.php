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
 * Upgrade arcanist itself.
 *
 * @group workflow
 */
final class ArcanistUpgradeWorkflow extends ArcanistBaseWorkflow {

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **upgrade**
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          Supports: cli
          Upgrade arc to the latest version.
EOTEXT
      );
  }

  public function getArguments() {
    return array();
  }

  public function run() {
    echo "Upgrading arc...\n";
    $root = dirname(phutil_get_library_root('arcanist'));

    if (!Filesystem::pathExists($root.'/.git')) {
      throw new ArcanistUsageException(
        "arc must be in its git working copy to be automatically upgraded. ".
        "This copy of arc (in '{$root}') is not in a git working copy.");
    }

    $working_copy = ArcanistWorkingCopyIdentity::newFromPath($root);

    $repository_api = ArcanistRepositoryAPI::newAPIFromWorkingCopyIdentity(
      $working_copy);
    $this->setRepositoryAPI($repository_api);

    // Require no local changes.
    $this->requireCleanWorkingCopy();

    // Require arc be on master.
    $branch_name = $repository_api->getBranchName();
    if ($branch_name != 'master') {
      throw new ArcanistUsageException(
        "arc must be on branch 'master' to be automatically upgraded. ".
        "This copy of arc (in '{$root}') is on branch '{$branch_name}'.");
    }

    chdir($root);
    try {
      phutil_passthru('git pull --rebase');
    } catch (Exception $ex) {
      phutil_passthru('git rebase --abort');
      throw $ex;
    }

    echo phutil_console_wrap(
      phutil_console_format(
        "**Updated!** Your copy of arc is now up to date.\n"));

    return 0;
  }

}
