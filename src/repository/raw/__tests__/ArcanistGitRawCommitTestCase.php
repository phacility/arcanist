<?php

final class ArcanistGitRawCommitTestCase
  extends PhutilTestCase {

  public function testGitRawCommitParser() {
    $cases = array(
      array(
        'name' => 'empty',
        'blob' => array(
          'tree fcfd0454eac6a28c729aa6bf7d38a5f1efc5cc5d',
          '',
          '',
        ),
        'tree' => 'fcfd0454eac6a28c729aa6bf7d38a5f1efc5cc5d',
      ),
      array(
        'name' => 'parents',
        'blob' => array(
          'tree 63ece8fd5a8283f1da2c14735d059669a09ba628',
          'parent 4aebaaf60895c3f3dd32a8cadff00db2c8f74899',
          'parent 0da1a2e17d921dc27ce9afa76b123cb4c8b73b17',
          'author alice',
          'committer alice',
          '',
          'Quack quack quack.',
          '',
        ),
        'tree' => '63ece8fd5a8283f1da2c14735d059669a09ba628',
        'parents' => array(
          '4aebaaf60895c3f3dd32a8cadff00db2c8f74899',
          '0da1a2e17d921dc27ce9afa76b123cb4c8b73b17',
        ),
        'author' => 'alice',
        'committer' => 'alice',
        'message' => "Quack quack quack.\n",
      ),
    );

    foreach ($cases as $case) {
      $name = $case['name'];
      $blob = $case['blob'];

      if (is_array($blob)) {
        $blob = implode("\n", $blob);
      }

      $raw = ArcanistGitRawCommit::newFromRawBlob($blob);
      $out = $raw->getRawBlob();

      $this->assertEqual(
        $blob,
        $out,
        pht(
          'Expected read + write to produce the original raw Git commit '.
          'blob in case "%s".',
          $name));

      $tree = idx($case, 'tree');
      $this->assertEqual(
        $tree,
        $raw->getTreeHash(),
        pht('Tree hashes in case "%s".', $name));

      $parents = idx($case, 'parents', array());
      $this->assertEqual(
        $parents,
        $raw->getParents(),
        pht('Parents in case "%s".', $name));

      $author = idx($case, 'author');
      $this->assertEqual(
        $author,
        $raw->getRawAuthor(),
        pht('Authors in case "%s".', $name));

      $committer = idx($case, 'committer');
      $this->assertEqual(
        $committer,
        $raw->getRawCommitter(),
        pht('Committer in case "%s".', $name));

      $message = idx($case, 'message', '');
      $this->assertEqual(
        $message,
        $raw->getMessage(),
        pht('Message in case "%s".', $name));
    }
  }

}
