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
 * @group workflow
 */
final class ArcanistAnoidWorkflow extends ArcanistBaseWorkflow {

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **anoid**
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          There's only one way to find out...
EOTEXT
      );
  }

  public function run() {
    phutil_passthru(
      dirname(phutil_get_library_root('arcanist')) . '/scripts/breakout.py'
    );
  }

}
