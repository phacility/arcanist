<?php

/**
 * Channel to a Mercurial "cmdserver" client. For a detailed description of the
 * "cmdserver" protocol, see @{class:ArcanistHgServerChannel}. This channel
 * implements the other half of the protocol: it decodes messages from the
 * client and encodes messages from the server.
 *
 * Because the proxy server speaks the exact same protocol that Mercurial
 * does and fully decodes both sides of the protocol, we need this half of the
 * decode/encode to talk to clients. Without it, we wouldn't be able to
 * determine when a client request had completed and was ready for transmission
 * to the Mercurial server.
 *
 * (Technically, we could get away without re-encoding messages from the
 * server, but the serialization is not complicated and having a general
 * implementation of encoded/decode for both the client and server dialects
 * seemed useful.)
 *
 * @task protocol Protocol Implementation
 */
final class ArcanistHgClientChannel extends PhutilProtocolChannel {

  const MODE_COMMAND    = 'command';
  const MODE_LENGTH     = 'length';
  const MODE_ARGUMENTS  = 'arguments';

  private $command;
  private $byteLengthOfNextChunk;

  private $buf = '';
  private $mode = self::MODE_COMMAND;


/* -(  Protocol Implementation  )-------------------------------------------- */


  /**
   * Encode a message for transmission to the client. The message should be
   * a pair with the channel name and the a block of data, like this:
   *
   *   array('o', '<some data...>');
   *
   * We encode it like this:
   *
   *   o
   *   1234                # Length, as a 4-byte unsigned long.
   *   <data: 1234 bytes>
   *
   * For a detailed description of the cmdserver protocol, see
   * @{class:ArcanistHgServerChannel}.
   *
   * @param pair<string,string> The <channel, data> pair to encode.
   * @return string Encoded string for transmission to the client.
   *
   * @task protocol
   */
  protected function encodeMessage($argv) {
    if (!is_array($argv) || count($argv) !== 2) {
      throw new Exception(pht('Message should be %s.', '<channel, data>'));
    }

    $channel = head($argv);
    $data    = last($argv);

    $len = strlen($data);
    $len = pack('N', $len);

    return "{$channel}{$len}{$data}";
  }


  /**
   * Decode a message received from the client. The message looks like this:
   *
   *   runcommand\n
   *   8                   # Length, as a 4-byte unsigned long.
   *   log\0
   *   -l\0
   *   5
   *
   * We decode it into a list in PHP, which looks like this:
   *
   *   array(
   *     'runcommand',
   *     'log',
   *     '-l',
   *     '5',
   *   );
   *
   * @param string Bytes from the server.
   * @return list<list<string>> Zero or more complete commands.
   *
   * @task protocol
   */
  protected function decodeStream($data) {
    $this->buf .= $data;

    // The first part is terminated by "\n", so we don't always know how many
    // bytes we need to look for. This makes parsing a bit of a pain.

    $messages = array();

    do {
      $continue_parsing = false;

      switch ($this->mode) {
        case self::MODE_COMMAND:
          // We're looking for "\n", which indicates the end of the command
          // name, like "runcommand". Next, we'll expect a length.

          $pos = strpos($this->buf, "\n");
          if ($pos === false) {
            break;
          }

          $this->command = substr($this->buf, 0, $pos);
          $this->buf = substr($this->buf, $pos + 1);
          $this->mode = self::MODE_LENGTH;

          $continue_parsing = true;
          break;
        case self::MODE_LENGTH:
          // We're looking for a byte length, as a 4-byte big-endian unsigned
          // integer. Next, we'll expect that many bytes of data.

          if (strlen($this->buf) < 4) {
            break;
          }

          $len = substr($this->buf, 0, 4);
          $len = unpack('N', $len);
          $len = head($len);

          $this->buf = substr($this->buf, 4);

          $this->mode = self::MODE_ARGUMENTS;
          $this->byteLengthOfNextChunk = $len;

          $continue_parsing = true;
          break;
        case self::MODE_ARGUMENTS:
          // We're looking for the data itself, which is a block of bytes
          // of the given length. These are arguments delimited by "\0". Next
          // we'll expect another command.

          if (strlen($this->buf) < $this->byteLengthOfNextChunk) {
            break;
          }

          $data = substr($this->buf, 0, $this->byteLengthOfNextChunk);
          $this->buf = substr($this->buf, $this->byteLengthOfNextChunk);

          $message = array_merge(array($this->command), explode("\0", $data));

          $this->mode = self::MODE_COMMAND;
          $this->command = null;
          $this->byteLengthOfNextChunk = null;

          $messages[] = $message;

          $continue_parsing = true;
          break;
      }
    } while ($continue_parsing);

    return $messages;
  }

}
