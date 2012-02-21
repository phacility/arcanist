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
 * Deprecated.
 *
 * TODO: Remove soon, once git users have had a chance to see the "use land
 * instead" message.
 *
 * @group workflow
 */
final class ArcanistMergeWorkflow extends ArcanistBaseWorkflow {

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
      **merge**
          Deprecated.

EOTEXT
      );
  }

  public function requiresRepositoryAPI() {
    return true;
  }

  public function getArguments() {
    return array(
      '*' => 'ignored',
    );
  }

  public function run() {
    $repository_api = $this->getRepositoryAPI();

    if ($repository_api instanceof ArcanistGitAPI) {
      throw new ArcanistUsageException(
        "'arc merge' no longer supports git. Use ".
        "'arc land --keep-branch --hold --merge <feature_branch>' instead.");
    }

    throw new ArcanistUsageException('arc merge is no longer supported.');
  }

}
