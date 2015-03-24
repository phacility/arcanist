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
      '%d AFFECTED FILE(S)' => array('AFFECTED FILE', 'AFFECTED FILES'),
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

      '(To ignore these %s change(s), add them to ".git/info/exclude".)' =>
      array(
        '(To ignore this change, add it to ".git/info/exclude".)',
        '(To ignore these changes, add them to ".git/info/exclude".)',
      ),

      '(To ignore these %s change(s), add them to "svn:ignore".)' => array(
        '(To ignore this change, add it to "svn:ignore".)',
        '(To ignore these changes, add them to "svn:ignore".)',
      ),

      '(To ignore these %s change(s), add them to ".hgignore".)' => array(
        '(To ignore this change, add it to ".hgignore".)',
        '(To ignore these changes, add them to ".hgignore".)',
      ),

      '%s line(s)' => array('line', 'lines'),

      '%d test(s)' => array('%d test', '%d tests'),

      '%d assertion(s) passed.' => array(
        '%d assertion passed.',
        '%d assertions passed.',
      ),
    );
  }

}
