<?php

final class ArcanistUSEnglishTranslation extends PhutilTranslation {

  public function getLocaleCode() {
    return 'en_US';
  }

  protected function getTranslations() {
    return array(
      '%s locally modified path(s) are not included in this revision:' => array(
        'A locally modified path is not included in this revision:',
        'Locally modified paths are not included in this revision:',
      ),
      'These %s path(s) will NOT be committed. Commit this revision '.
      'anyway?' => array(
        'This path will NOT be committed. Commit this revision anyway?',
        'These paths will NOT be committed. Commit this revision anyway?',
      ),
      'Revision includes changes to %s path(s) that do not exist:' => array(
        'Revision includes changes to a path that does not exist:',
        'Revision includes changes to paths that do not exist:',
      ),

      'This diff includes %s file(s) which are not valid UTF-8 (they contain '.
      'invalid byte sequences). You can either stop this workflow and fix '.
      'these files, or continue. If you continue, these files will be '.
      'marked as binary.' => array(
        'This diff includes a file which is not valid UTF-8 (it has invalid '.
          'byte sequences). You can either stop this workflow and fix it, or '.
          'continue. If you continue, this file will be marked as binary.',
        'This diff includes files which are not valid UTF-8 (they contain '.
          'invalid byte sequences). You can either stop this workflow and fix '.
          'these files, or continue. If you continue, these files will be '.
          'marked as binary.',
      ),
      '%s AFFECTED FILE(S)' => array('AFFECTED FILE', 'AFFECTED FILES'),
      'Do you want to mark these %s file(s) as binary and continue?' => array(
        'Do you want to mark this file as binary and continue?',
        'Do you want to mark these files as binary and continue?',
      ),

      'Do you want to amend these %s change(s) to the current commit?' => array(
        'Do you want to amend this change to the current commit?',
        'Do you want to amend these changes to the current commit?',
      ),

      'Do you want to create a new commit with these %s change(s)?' => array(
        'Do you want to create a new commit with this change?',
        'Do you want to create a new commit with these changes?',
      ),

      '(To ignore these %s change(s), add them to "%s".)' => array(
        '(To ignore this change, add it to "%2$s".)',
        '(To ignore these changes, add them to "%2$s".)',
      ),

      '%s line(s)' => array('line', 'lines'),

      '%s assertion(s) passed.' => array(
        '%s assertion passed.',
        '%s assertions passed.',
      ),

      'Ignore these %s untracked file(s) and continue?' => array(
        'Ignore this untracked file and continue?',
        'Ignore these untracked files and continue?',
      ),

      '%s submodule(s) have uncommitted or untracked changes:' => array(
        'A submodule has uncommitted or untracked changes:',
        'Submodules have uncommitted or untracked changes:',
      ),

      'Ignore the changes to these %s submodule(s) and continue?' => array(
        'Ignore the changes to this submodule and continue?',
        'Ignore the changes to these submodules and continue?',
      ),

      'These %s commit(s) will be landed:' => array(
        'This commit will be landed:',
        'These commits will be landed:',
      ),

      'Updated %s librarie(s).' => array(
        'Updated library.',
        'Updated %s libraries.',
      ),
    );
  }

}
