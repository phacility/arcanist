<?php

final class ArcanistRepositoryURINormalizerTestCase
  extends PhutilTestCase {

  public function testGitURINormalizer() {
    $cases = array(
      'ssh://user@domain.com/path.git' => 'path',
      'https://user@domain.com/path.git' => 'path',
      'git@domain.com:path.git' => 'path',
      'ssh://user@gitserv002.com/path.git' => 'path',
      'ssh://htaft@domain.com/path.git' => 'path',
      'ssh://user@domain.com/bananas.git' => 'bananas',
      'git@domain.com:bananas.git' => 'bananas',
      'user@domain.com:path/repo' => 'path/repo',
      'user@domain.com:path/repo/' => 'path/repo',
      'file:///path/to/local/repo.git' => 'path/to/local/repo',
      '/path/to/local/repo.git' => 'path/to/local/repo',
      'ssh://something.com/diffusion/X/anything.git' => 'diffusion/X',
      'ssh://something.com/diffusion/X/' => 'diffusion/X',
    );

    $type_git = ArcanistRepositoryURINormalizer::TYPE_GIT;

    foreach ($cases as $input => $expect) {
      $normal = new ArcanistRepositoryURINormalizer($type_git, $input);
      $this->assertEqual(
        $expect,
        $normal->getNormalizedPath(),
        pht('Normalized Git path for "%s".', $input));
    }
  }

  public function testDomainURINormalizer() {
    $base_domain = 'base.phabricator.example.com';
    $ssh_domain = 'ssh.phabricator.example.com';

    $domain_map = array(
      '<base-uri>' => $base_domain,
      '<ssh-host>' => $ssh_domain,
    );

    $cases = array(
      '/' => '<void>',
      '/path/to/local/repo.git' => '<void>',
      'ssh://user@domain.com/path.git' => 'domain.com',
      'ssh://user@DOMAIN.COM/path.git' => 'domain.com',
      'http://'.$base_domain.'/diffusion/X/' => '<base-uri>',
      'ssh://'.$ssh_domain.'/diffusion/X/' => '<ssh-host>',
      'git@'.$ssh_domain.':bananas.git' => '<ssh-host>',
    );

    $type_git = ArcanistRepositoryURINormalizer::TYPE_GIT;

    foreach ($cases as $input => $expect) {
      $normalizer = new ArcanistRepositoryURINormalizer($type_git, $input);

      $normalizer->setDomainMap($domain_map);

      $this->assertEqual(
        $expect,
        $normalizer->getNormalizedDomain(),
        pht('Normalized domain for "%s".', $input));
    }
  }

  public function testSVNURINormalizer() {
    $cases = array(
      'file:///path/to/repo' => 'path/to/repo',
      'file:///path/to/repo/' => 'path/to/repo',
    );

    $type_svn = ArcanistRepositoryURINormalizer::TYPE_SVN;

    foreach ($cases as $input => $expect) {
      $normal = new ArcanistRepositoryURINormalizer($type_svn, $input);
      $this->assertEqual(
        $expect,
        $normal->getNormalizedPath(),
        pht('Normalized SVN path for "%s".', $input));
    }
  }

}
