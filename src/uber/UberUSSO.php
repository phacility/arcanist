<?php

// class implements couple of helpers needed to work with uSSO
final class UberUSSO extends Phobject {
  // usso itself is somewhat sluggish, takes 1 second to return cached token
  const USSO_CACHE_TIMEOUT = 600;

  public function enhanceConduitClient(
    $conduit,
    HTTPFutureHTTPResponseStatus $status = null) {

    $tkn = null;
    if ($status != null) {
      // if ARC_USSO_TOKEN is set (service most like) we should not try to use
      // usso/ussh stuff
      if (!getenv('ARC_USSO_TOKEN')) {
        if (($status->getStatusCode() == 401) &&
            !empty($status->getExcerpt())) {
          $msg = json_decode($status->getExcerpt(), true);
          if (!empty($msg) && idx($msg, 'error', false) !== false &&
              idx($msg, 'code', false) !== false) {
            // sounds like usso enabled endpoint
            $tkn = $this->getUSSOToken($conduit->getHost());
          }
        }
      }
    } else if (getenv('ARC_USSO_TOKEN')) {
      $tkn = getenv('ARC_USSO_TOKEN');
    } else {
      $tkn = self::maybeUseUSSOToken($conduit->getHost());
    }
    if ($tkn !== null) {
      $conduit->setHeader('Authorization', 'Bearer '.$tkn);
      return true;
    }
    return false;
  }

  public function maybeUseUSSOToken($domain) {
    $cache = self::getUSSOCacheFilename($domain);
    try {
      $data = @json_decode(file_get_contents($cache), true);
      if (is_array($data)) {
        if (idx($data, 'createdAt', 0) + self::USSO_CACHE_TIMEOUT > time()) {
          return idx($data, 'token', false);
        }
      }
    } catch (Exception $e) {
      // it is ok to fail here, we will fallback to `usso`
    }
    return null;
  }

  public function getUSSOToken($domain) {
    // check ussh and if necessary ask to auth
    list($e, $stdin, $stderr) = exec_manual('ussh');
    $error_msg = '`ussh` binary is missing or it cannot be executed. Please '.
      'read http://t.uber.com/phabricator-usso';

    if ($e != 0) {
      $e = phutil_passthru('ussh');
      if ($e != 0) {
        throw new ArcanistUsageException($error_msg);
      }
    }
    // try fetching usso token
    list($e, $stdin, $stderr) = exec_manual('usso -ussh %s', $domain);
    if ($e != 0) {
      $e = phutil_passthru('usso -ussh %s', $domain);
      if ($e != 0) {
        throw new ArcanistUsageException($error_msg);
      }
    }
    // try actually fetching token
    list($e, $stdin, $stderr) = exec_manual('usso -ussh %s -print', $domain);
    if ($e != 0) {
      throw new ArcanistUsageException($stdin."\n".$stderr);
    }
    $token = trim($stdin);
    // try creating file with 0600 permissions
    $old = umask(0077);
    try {
      $cache = self::getUSSOCacheFilename($domain);
      file_put_contents($cache,
                        json_encode(array(
                                          'createdAt' => time(),
                                          'token' => $token,
                        )));
      chmod($cache, 0600);
    } catch (Exception $e) {
      // it is ok to fail here, we will fallback to `usso`
    }
    umask($old);
    return trim($stdin);
  }

  private static function getUSSOCacheFilename($domain) {
    return implode(DIRECTORY_SEPARATOR,
                   array(
                         sys_get_temp_dir(),
                         sprintf('usso-token-cache-%s.json', md5($domain)),
                   ));
  }
}
