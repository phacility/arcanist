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
 * Server which @{class:ArcanistHgProxyClient} clients connect to. This
 * server binds to a Mercurial working copy and creates a Mercurial process and
 * a unix domain socket in that working copy. It listens for connections on
 * the socket, reads commands from them, and forwards their requests to the
 * Mercurial process. It then returns responses to the original clients.
 *
 * Note that this server understands the underlying protocol and completely
 * decodes messages from both the client and server before re-encoding them
 * and relaying them to their final destinations. It must do this (at least
 * in part) to determine where messages begin and end. Additionally, this proxy
 * sends and receives the Mercurial cmdserver protocol exactly, without
 * any extensions or sneakiness.
 *
 * The advantage of this mechanism is that it avoids the overhead of starting
 * a Mercurial process for each Mercurial command, which can exceed 100ms per
 * invocation. This server can also accept connections from multiple clients
 * and serve them from a single Mercurial server process.
 *
 * @task construct  Construction
 * @task server     Serving Requests
 * @task client     Managing Clients
 * @task hg         Managing Mercurial
 * @task internal   Internals
 */
final class ArcanistHgProxyServer {

  private $workingCopy;
  private $socket;
  private $hello;


/* -(  Construction  )------------------------------------------------------- */


  /**
   * Build a new server. This server is bound to a working copy. The server
   * is inactive until you @{method:start} it.
   *
   * @param string Path to a Mercurial working copy.
   *
   * @task construct
   */
  public function __construct($working_copy) {
    $this->workingCopy = Filesystem::resolvePath($working_copy);
  }


/* -(  Serving Requests  )--------------------------------------------------- */


  /**
   * Start the server. This method does not return.
   *
   * @return never
   *
   * @task server
   */
  public function start() {

    // Create the unix domain socket in the working copy to listen for clients.
    $socket = $this->startWorkingCopySocket();
    $this->socket = $socket;

    // TODO: Daemonize here.

    // Start the Mercurial process which we'll forward client requests to.
    $hg = $this->startMercurialProcess();
    $clients = array();

    $this->log(null, 'Listening');
    while (true) {
      // Wait for activity on any active clients, the Mercurial process, or
      // the listening socket where new clients connect.
      PhutilChannel::waitForAny(
        array_merge($clients, array($hg)),
        array(
          'read'    => array($socket),
          'except'  => array($socket),
        ));

      if (!$hg->update()) {
        throw new Exception("Server exited unexpectedly!");
      }

      // Accept any new clients.
      while ($client = $this->acceptNewClient($socket)) {
        $clients[] = $client;
        $key = last_key($clients);
        $client->setName($key);

        $this->log($client, 'Connected');
      }

      // Update all the active clients.
      foreach ($clients as $key => $client) {
        $ok = $this->updateClient($client, $hg);
        if (!$ok) {
          $this->log($client, 'Disconnected');
          unset($clients[$key]);
        }
      }
    }
  }


  /**
   * Update one client, processing any commands it has sent us. We fully
   * process all commands we've received here before returning to the main
   * server loop.
   *
   * @param ArcanistHgClientChannel The client to update.
   * @param ArcanistHgServerChannel The Mercurial server.
   *
   * @task server
   */
  private function updateClient(
    ArcanistHgClientChannel $client,
    ArcanistHgServerChannel $hg) {

    if (!$client->update()) {
      // Client has disconnected, don't bother proceeding.
      return false;
    }

    // Read a command from the client if one is available. Note that we stop
    // updating other clients or accepting new connections while processing a
    // command, since there isn't much we can do with them until the server
    // finishes executing this command.
    $message = $client->read();
    if (!$message) {
      return true;
    }

    $this->log($client, '$ '.$message[0].' '.$message[1]);
    $t_start = microtime(true);

    // Forward the command to the server.
    $hg->write($message);

    while (true) {
      PhutilChannel::waitForAny(array($client, $hg));

      if (!$client->update() || !$hg->update()) {
        // If either the client or server has exited, bail.
        return false;
      }

      $response = $hg->read();
      if (!$response) {
        continue;
      }

      // Forward the response back to the client.
      $client->write($response);

      // If the response was on the 'r'esult channel, it indicates the end
      // of the command output. We can process the next command (if any
      // remain) or go back to accepting new connections and servicing
      // other clients.
      if ($response[0] == 'r') {
        // Update the client immediately to try to get the bytes on the wire
        // as quickly as possible. This gives us slightly more throughput.
        $client->update();
        break;
      }
    }

    // Log the elapsed time.
    $t_end = microtime(true);
    $t = 1000000 * ($t_end - $t_start);
    $this->log($client, '< '.number_format($t, 0).'us');

    return true;
  }


/* -(  Managing Clients  )--------------------------------------------------- */


  /**
   * @task client
   */
  public static function getPathToSocket($working_copy) {
    return $working_copy.'/.hg/hgdaemon-socket';
  }


  /**
   * @task client
   */
  private function startWorkingCopySocket() {
    $errno = null;
    $errstr = null;

    $socket_path = self::getPathToSocket($this->workingCopy);
    $socket_uri  = 'unix://'.$socket_path;

    $socket = @stream_socket_server($socket_uri, $errno, $errstr);
    if ($errno || !$socket) {
      Filesystem::remove($socket_path);
      $socket = @stream_socket_server($socket_uri, $errno, $errstr);
    }

    if ($errno || !$socket) {
      throw new Exception(
        "Unable to start socket! Error #{$errno}: {$errstr}");
    }

    $ok = stream_set_blocking($socket, 0);
    if ($ok === false) {
      throw new Exception("Unable to set socket nonblocking!");
    }

    return $socket;
  }


  /**
   * @task client
   */
  private function acceptNewClient($socket) {
    // NOTE: stream_socket_accept() always blocks, even when the socket has
    // been set nonblocking.
    $new_client = @stream_socket_accept($socket, $timeout = 0);
    if (!$new_client) {
      return;
    }

    $channel = new PhutilSocketChannel($new_client);
    $client = new ArcanistHgClientChannel($channel);

    $client->write($this->hello);

    return $client;
  }


/* -(  Managing Mercurial  )------------------------------------------------- */


  /**
   * Starts a Mercurial process which can actually handle requests.
   *
   * @return ArcanistHgServerChannel  Channel to the Mercurial server.
   * @task hg
   */
  private function startMercurialProcess() {
    // NOTE: "cmdserver.log=-" makes Mercurial use the 'd'ebug channel for
    // log messages.

    $command = 'HGPLAIN=1 hg --config cmdserver.log=- serve --cmdserver pipe';

    $future = new ExecFuture($command);
    $future->setCWD($this->workingCopy);

    $channel = new PhutilExecChannel($future);
    $hg = new ArcanistHgServerChannel($channel);

    // The server sends a "hello" message with capability and encoding
    // information. Save it and forward it to clients when they connect.
    $this->hello = $hg->waitForMessage();

    return $hg;
  }


/* -(  Internals  )---------------------------------------------------------- */


  /**
   * Close and remove the unix domain socket in the working copy.
   *
   * @task internal
   */
  public function __destruct() {
    if ($this->socket) {
      @stream_socket_shutdown($this->socket);
      @fclose($this->socket);
      Filesystem::remove(self::getPathToSocket($this->workingCopy));
      $this->socket = null;
    }
  }

  private function log($client, $message) {
    if ($client) {
      $message = '[Client '.$client->getName().'] '.$message;
    } else {
      $message = '[Server] '.$message;
    }
    echo $message."\n";
  }

}
