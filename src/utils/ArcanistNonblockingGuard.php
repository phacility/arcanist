<?php

final class ArcanistNonblockingGuard
  extends Phobject {

  private $stream;
  private $didSetNonblocking;

  public static function newForStream($stream) {
    $guard = new self();
    $guard->stream = $stream;

    if (phutil_is_windows()) {

      // On Windows, we skip this because stdin can not be made nonblocking.

    } else if (!function_exists('pcntl_signal')) {

      // If we can't handle signals, we: can't reset the flag if we're
      // interrupted; but also don't benefit from setting it in the first
      // place since it's only relevant for handling interrupts during
      // prompts. So just skip this.

    } else {

      // See T13649. Note that the "blocked" key identifies whether the
      // stream is blocking or nonblocking, not whether it will block when
      // read or written.

      $metadata = stream_get_meta_data($stream);
      $is_blocking = idx($metadata, 'blocked');
      if ($is_blocking) {
        $ok = stream_set_blocking($stream, false);
        if (!$ok) {
          throw new Exception(pht('Unable to set stream nonblocking.'));
        }
        $guard->didSetNonblocking = true;
      }
    }

    return $guard;
  }

  public function getIsNonblocking() {
    return $this->didSetNonblocking;
  }

  public function __destruct() {
    if ($this->stream && $this->didSetNonblocking) {
      stream_set_blocking($this->stream, true);
    }

    $this->stream = null;
  }

}
