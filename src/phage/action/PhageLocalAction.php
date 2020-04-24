<?php

final class PhageLocalAction
  extends PhageAgentAction {

  protected function newAgentFuture(PhutilCommandString $command) {
    $arcanist_src = phutil_get_library_root('arcanist');
    $bin_dir = Filesystem::concatenatePaths(
      array(
        dirname($arcanist_src),
        'bin',
      ));

    $future = id(new ExecFuture('%s exec -- %C', './phage', $command))
      ->setCWD($bin_dir);

    return $future;
  }

}
