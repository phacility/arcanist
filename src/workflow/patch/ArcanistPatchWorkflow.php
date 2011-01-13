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

final class ArcanistPatchWorkflow extends ArcanistBaseWorkflow {

  const SOURCE_BUNDLE     = 'bundle';
  const SOURCE_PATCH      = 'patch';
  const SOURCE_REVISION   = 'revision';
  const SOURCE_DIFF       = 'diff';

  private $source;
  private $sourceParam;

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
      **patch** __source__
          Supports: git, svn
          Apply the changes in a Differential revision, patchfile, or arc
          bundle to the working copy.
EOTEXT
      );
  }

  public function getArguments() {
    return array(
      'revision' => array(
        'param' => 'revision_id',
        'help' =>
          "Apply changes from a Differential revision, using the most recent ".
          "diff that has been attached to it.",
      ),
      'diff' => array(
        'param' => 'diff_id',
        'help' =>
          "Apply changes from a Differential diff. Normally you want to use ".
          "--revision to get the most recent changes, but you can ".
          "specifically apply an out-of-date diff or a diff which was never ".
          "attached to a revision by using this flag.",
      ),
      'arcbundle' => array(
        'param' => 'bundlefile',
        'help' =>
          "Apply changes from an arc bundle generated with 'arc export'.",
      ),
      'patch' => array(
        'param' => 'patchfile',
        'help' =>
          "Apply changes from a git patchfile or unified patchfile.",
      ),
    );
  }

  protected function didParseArguments() {
    $source = null;
    $requested = 0;
    if ($this->getArgument('revision')) {
      $source = self::SOURCE_REVISION;
      $requested++;
    }
    if ($this->getArgument('diff')) {
      $source = self::SOURCE_DIFF;
      $requested++;
    }
    if ($this->getArgument('arcbundle')) {
      $source = self::SOURCE_BUNDLE;
      $requested++;
    }
    if ($this->getArgument('patch')) {
      $source = self::SOURCE_PATCH;
      $requested++;
    }

    if ($requested === 0) {
      throw new ArcanistUsageException(
        "Specify one of '--revision <revision_id>' (to select the current ".
        "changes attached to a Differential revision), '--diff <diff_id>' ".
        "(to select a specific, out-of-date diff or a diff which is not ".
        "attached to a revision), '--arcbundle <file>' or '--patch <file>' ".
        "to choose a patch source.");
    } else if ($requested > 1) {
      throw new ArcanistUsageException(
        "Options '--revision', '--diff', '--arcbundle' and '--patch' are ".
        "not compatible. Choose exactly one patch source.");
    }

    $this->source = $source;
    $this->sourceParam = $this->getArgument($source);
  }

  public function requiresConduit() {
    return ($this->getSource() == self::SOURCE_REVISION) ||
           ($this->getSource() == self::SOURCE_DIFF);
  }

  public function requiresAuthentication() {
    return $this->requiresConduit();
  }

  public function requiresRepositoryAPI() {
    return true;
  }

  public function requiresWorkingCopy() {
    return true;
  }

  private function getSource() {
    return $this->source;
  }

  private function getSourceParam() {
    return $this->sourceParam;
  }

  public function run() {

    $source = $this->getSource();
    $param = $this->getSourceParam();
    switch ($source) {
      case self::SOURCE_PATCH:
        if ($param == '-') {
          $patch = @file_get_contents('php://stdin');
          if (!strlen($patch)) {
            throw new ArcanistUsageException(
              "Failed to read patch from stdin!");
          }
        } else {
          $patch = Filesystem::readFile($param);
        }
        $bundle = ArcanistBundle::newFromDiff($patch);
        break;
      case self::SOURCE_BUNDLE:
        $path = $this->getArgument('arcbundle');
        $bundle = ArcanistBundle::newFromArcBundle($path);
        break;
      case self::SOURCE_REVISION:
        $bundle = $this->loadRevisionBundleFromConduit(
          $this->getConduit(),
          $param);
        break;
      case self::SOURCE_DIFF:
        $bundle = $this->loadDiffBundleFromConduit(
          $this->getConduit(),
          $param);
        break;
    }

    $repository_api = $this->getRepositoryAPI();
    if ($repository_api instanceof ArcanistSubversionAPI) {
      $patch_err = 0;

      $copies = array();
      $deletes = array();
      $patches = array();
      $propset = array();
      $adds = array();

      $changes = $bundle->getChanges();
      foreach ($changes as $change) {
        $type = $change->getType();
        $should_patch = true;
        switch ($type) {
          case ArcanistDiffChangeType::TYPE_MOVE_AWAY:
          case ArcanistDiffChangeType::TYPE_MULTICOPY:
          case ArcanistDiffChangeType::TYPE_DELETE:
            $path = $change->getCurrentPath();
            $fpath = $repository_api->getPath($path);
            if (!@file_exists($fpath)) {
              $this->confirm(
                "Patch deletes file '{$path}', but the file does not exist in ".
                "the working copy. Continue anyway?");
            } else {
              $deletes[] = $change->getCurrentPath();
            }
            $should_patch = false;
            break;
          case ArcanistDiffChangeType::TYPE_COPY_HERE:
          case ArcanistDiffChangeType::TYPE_MOVE_HERE:
            $path = $change->getOldPath();
            $fpath = $repository_api->getPath($path);
            if (!@file_exists($fpath)) {
              $cpath = $change->getCurrentPath();
              if ($type == ArcanistDiffChangeType::TYPE_COPY_HERE) {
                $verbs = 'copies';
              } else {
                $verbs = 'moves';
              }
              $this->confirm(
                "Patch {$verbs} '{$path}' to '{$cpath}', but source path ".
                "does not exist in the working copy. Continue anyway?");
            } else {
              $copies[] = array(
                $change->getOldPath(),
                $change->getCurrentPath());
            }
            break;
          case ArcanistDiffChangeType::TYPE_ADD:
            $adds[] = $change->getCurrentPath();
            break;
        }
        if ($should_patch) {
          if ($change->getHunks()) {
            $cbundle = ArcanistBundle::newFromChanges(array($change));
            $patches[$change->getCurrentPath()] = $cbundle->toUnifiedDiff();
          }
          $prop_old = $change->getOldProperties();
          $prop_new = $change->getNewProperties();
          $props = $prop_old + $prop_new;
          foreach ($props as $key => $ignored) {
            if (idx($prop_old, $key) !== idx($prop_new, $key)) {
              $propset[$change->getCurrentPath()][$key] = idx($prop_new[$key]);
            }
          }
        }
      }

      foreach ($copies as $copy) {
        list($src, $dst) = $copy;
        passthru(
          csprintf(
            '(cd %s; svn cp %s %s)',
            $repository_api->getPath(),
            $src,
            $dst));
      }

      foreach ($deletes as $delete) {
        passthru(
          csprintf(
            '(cd %s; svn rm %s)',
            $repository_api->getPath(),
            $delete));
      }

      foreach ($patches as $path => $patch) {
        $tmp = new TempFile();
        Filesystem::writeFile($tmp, $patch);
        $err = null;
        passthru(
          csprintf(
            '(cd %s; patch -p0 < %s)',
            $repository_api->getPath(),
            $tmp),
          $err);
        if ($err) {
          $patch_err = max($patch_err, $err);
        }
      }

      foreach ($adds as $add) {
        passthru(
          csprintf(
            '(cd %s; svn add %s)',
            $repository_api->getPath(),
            $add));
      }

      foreach ($propset as $path => $changes) {
        foreach ($change as $prop => $value) {
          // TODO: Probably need to handle svn:executable specially here by
          // doing chmod +x or -x.
          if ($value === null) {
            passthru(
              csprintf(
                '(cd %s; svn propdel %s %s)',
                $repository_api->getPath(),
                $prop,
                $path));
          } else {
            passthru(
              csprintf(
                '(cd %s; svn propset %s %s %s)',
                $repository_api->getPath(),
                $prop,
                $value,
                $path));
          }
        }
      }

      if ($patch_err == 0) {
        echo phutil_console_format(
          "<bg:green>** OKAY **</bg> Successfully applied patch to the ".
          "working copy.\n");
      } else {
        echo phutil_console_format(
          "\n\n<bg:yellow>** WARNING **</bg> Some hunks could not be applied ".
          "cleanly by the unix 'patch' utility. Your working copy may be ".
          "different from the revision's base, or you may be in the wrong ".
          "subdirectory. You can export the raw patch file using ".
          "'arc export --unified', and then try to apply it by fiddling with ".
          "options to 'patch' (particularly, -p), or manually. The output ".
          "above, from 'patch', may be helpful in figuring out what went ".
          "wrong.\n");
      }

      return $patch_err;
    } else {
      $future = new ExecFuture(
        '(cd %s; git apply --index)',
        $repository_api->getPath());
      $future->write($bundle->toGitPatch());
      $future->resolvex();

      echo phutil_console_format(
        "<bg:green>** OKAY **</bg> Successfully applied patch.\n");
    }

    return 0;
  }
}
