<?php

/*
 * Copyright 2011 Facebook, Inc.
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

class ArcanistLintSeverity {

  const SEVERITY_ADVICE       = 'advice';
  const SEVERITY_WARNING      = 'warning';
  const SEVERITY_ERROR        = 'error';
  const SEVERITY_DISABLED     = 'disabled';

  public static function getStringForSeverity($severity_code) {
    static $map = array(
      self::SEVERITY_ADVICE   => 'Advice',
      self::SEVERITY_WARNING  => 'Warning',
      self::SEVERITY_ERROR    => 'Error',
      self::SEVERITY_DISABLED => 'Disabled',
    );

    if (!array_key_exists($severity_code, $map)) {
      throw new Exception("Unknown lint severity '{$severity_code}'!");
    }

    return $map[$severity_code];
  }

  public static function isAtLeastAsSevere(
    ArcanistLintMessage $message,
    $level) {

    static $map = array(
      self::SEVERITY_DISABLED => 10,
      self::SEVERITY_ADVICE   => 20,
      self::SEVERITY_WARNING  => 30,
      self::SEVERITY_ERROR    => 40,
    );

    $message_sev = $message->getSeverity();
    if (empty($map[$message_sev])) {
      return true;
    }

    return $map[$message_sev] >= idx($map, $level, 0);
  }


}
