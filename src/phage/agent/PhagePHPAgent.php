<?php

final class PhagePHPAgent extends Phobject {

  private $stdin;
  private $master;
  private $futurePool;

  public function __construct($stdin) {
    $this->stdin = $stdin;
  }

  public function execute() {
    $future_pool = $this->getFuturePool();

    while (true) {
      if ($future_pool->hasFutures()) {
        while ($future_pool->hasFutures()) {
          $future = $future_pool->resolve();

          if ($future === null) {
            foreach ($future_pool->getFutures() as $read_future) {
              $this->readFuture($read_future);
            }
            break;
          }

          $this->resolveFuture($future);
        }
      } else {
        PhutilChannel::waitForAny(array($this->getMaster()));
      }

      $this->processInput();
    }
  }

  private function getFuturePool() {
    if (!$this->futurePool) {
      $this->futurePool = $this->newFuturePool();
    }
    return $this->futurePool;
  }

  private function newFuturePool() {
    $future_pool = new FuturePool();

    $future_pool->getIteratorTemplate()
      ->setUpdateInterval(0.050);

    return $future_pool;
  }

  private function getMaster() {
    if (!$this->master) {
      $raw_channel = new PhutilSocketChannel(
        $this->stdin,
        fopen('php://stdout', 'w'));

      $json_channel = new PhutilJSONProtocolChannel($raw_channel);
      $this->master = $json_channel;
    }

    return $this->master;
  }

  private function processInput() {
    $channel = $this->getMaster();

    $open = $channel->update();
    if (!$open) {
      throw new Exception(pht('Channel closed!'));
    }

    while (true) {
      $command = $channel->read();
      if ($command === null) {
        break;
      }
      $this->processCommand($command);
    }
  }

  private function processCommand(array $spec) {
    switch ($spec['type']) {
      case 'EXEC':
        $key = $spec['key'];
        $cmd = $spec['command'];

        $future = new ExecFuture('%C', $cmd);

        $timeout = $spec['timeout'];
        if ($timeout) {
          $future->setTimeout(ceil($timeout));
        }

        $future->setFutureKey($key);

        $this->getFuturePool()
          ->addFuture($future);
        break;
      case 'EXIT':
        $this->terminateAgent();
        break;
    }
  }

  private function readFuture(ExecFuture $future) {
    $master = $this->getMaster();
    $key = $future->getFutureKey();

    list($stdout, $stderr) = $future->read();
    $future->discardBuffers();

    if (strlen($stdout)) {
      $master->write(
        array(
          'type' => 'TEXT',
          'key' => $key,
          'kind' => 'stdout',
          'text' => $stdout,
        ));
    }

    if (strlen($stderr)) {
      $master->write(
        array(
          'type' => 'TEXT',
          'key' => $key,
          'kind' => 'stderr',
          'text' => $stderr,
        ));
    }
  }

  private function resolveFuture(ExecFuture $future) {
    $key = $future->getFutureKey();
    $result = $future->resolve();
    $master = $this->getMaster();

    $master->write(
      array(
        'type'    => 'RSLV',
        'key'     => $key,
        'err'     => $result[0],
        'stdout'  => $result[1],
        'stderr'  => $result[2],
        'timeout' => (bool)$future->getWasKilledByTimeout(),
      ));
  }

  public function __destruct() {
    $this->terminateAgent();
  }

  private function terminateAgent() {
    $pool = $this->getFuturePool();

    foreach ($pool->getFutures() as $future) {
      $future->resolveKill();
    }

    exit(0);
  }

}
