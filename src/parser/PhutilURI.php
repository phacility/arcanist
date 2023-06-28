<?php

/**
 * Structural representation of a URI.
 *
 * This class handles URIs of two types: standard URIs and Git URIs.
 *
 * Standard URIs look like `proto://user:pass@domain:port/path?query#fragment`.
 * Almost all URIs are in this form.
 *
 * Git URIs look like `user@host:path`. These URIs are used by Git and SCP
 * and have an implicit "ssh" protocol, no port, and interpret paths as
 * relative instead of absolute.
 */
final class PhutilURI extends Phobject {

  private $protocol;
  private $user;
  private $pass;
  private $domain;
  private $port;
  private $path;
  private $query = array();
  private $fragment;
  private $type;

  const TYPE_URI = 'uri';
  const TYPE_GIT = 'git';

  public function __construct($uri, $params = array()) {
    if ($uri instanceof PhutilURI) {
      $this->protocol = $uri->protocol;
      $this->user = $uri->user;
      $this->pass = $uri->pass;
      $this->domain = $uri->domain;
      $this->port = $uri->port;
      $this->path = $uri->path;
      $this->query = $uri->query;
      $this->fragment = $uri->fragment;
      $this->type = $uri->type;

      $this->initializeQueryParams(phutil_string_cast($uri), $params);

      return;
    }

    $uri = phutil_string_cast($uri);

    $type = self::TYPE_URI;

    // Reject ambiguous URIs outright. Different versions of different clients
    // parse these in different ways. See T12526 for discussion.
    if (preg_match('(^[^/:]*://[^/]*[#?].*:)', $uri)) {
      throw new Exception(
        pht(
          'Rejecting ambiguous URI "%s". This URI is not formatted or '.
          'encoded properly.',
          $uri));
    }

    $matches = null;
    if (preg_match('(^([^/:]*://[^/]*)(\\?.*)\z)', $uri, $matches)) {
      // If the URI is something like `idea://open?file=/path/to/file`, the
      // `parse_url()` function will parse `open?file=` as the host. This is
      // not the expected result. Break the URI into two pieces, stick a slash
      // in between them, parse that, then remove the path. See T6106.

      $parts = parse_url($matches[1].'/'.$matches[2]);
      unset($parts['path']);
    } else if ($this->isGitURIPattern($uri)) {
      // Handle Git/SCP URIs in the form "user@domain:relative/path".

      $user = '(?:(?P<user>[^/@]+)@)?';
      $host = '(?P<host>[^/:]+)';
      $path = ':(?P<path>.*)';

      $ok = preg_match('(^'.$user.$host.$path.'\z)', $uri, $matches);
      if (!$ok) {
        throw new Exception(
          pht(
            'Failed to parse URI "%s" as a Git URI.',
            $uri));
      }

      $parts = $matches;
      $parts['scheme'] = 'ssh';

      $type = self::TYPE_GIT;
    } else {
      $parts = parse_url($uri);
    }

    // The parse_url() call will accept URIs with leading whitespace, but many
    // other tools (like git) will not. See T4913 for a specific example. If
    // the input string has leading whitespace, fail the parse.
    if ($parts) {
      if (ltrim($uri) != $uri) {
        $parts = false;
      }
    }

    // NOTE: `parse_url()` is very liberal about host names; fail the parse if
    // the host looks like garbage. In particular, we do not allow hosts which
    // begin with "." or "-". See T12961 for a specific attack which relied on
    // hosts beginning with "-".
    if ($parts) {
      $host = idx($parts, 'host', '');
      if (strlen($host)) {
        if (!preg_match('/^[a-zA-Z0-9]+[a-zA-Z0-9\\.\\-]*\z/', $host)) {
          $parts = false;
        }
      }
    }

    if (!$parts) {
      $parts = array();
    }

    // stringyness is to preserve API compatibility and
    // allow the tests to continue passing
    $this->protocol = idx($parts, 'scheme', '');
    $this->user = rawurldecode(idx($parts, 'user', ''));
    $this->pass = rawurldecode(idx($parts, 'pass', ''));
    $this->domain = idx($parts, 'host', '');
    $this->port = (string)idx($parts, 'port', '');
    $this->path = idx($parts, 'path', '');
    $query = idx($parts, 'query');
    if ($query) {
      $pairs = id(new PhutilQueryStringParser())
        ->parseQueryStringToPairList($query);

      foreach ($pairs as $pair) {
        list($key, $value) = $pair;
        $this->appendQueryParam($key, $value);
      }
    }
    $this->fragment = idx($parts, 'fragment', '');

    $this->type = $type;

    $this->initializeQueryParams($uri, $params);
  }

  public function __toString() {
    $prefix = null;

    if ($this->isGitURI()) {
      $port = null;
    } else {
      $port = $this->port;
    }

    $domain = $this->domain;

    $user = $this->user;
    $pass = $this->pass;
    if (phutil_nonempty_string($user) && phutil_nonempty_string($pass)) {
      $auth = rawurlencode($user).':'.rawurlencode($pass).'@';
    } else if (phutil_nonempty_string($user)) {
      $auth = rawurlencode($user).'@';
    } else {
      $auth = null;
    }

    $protocol = $this->protocol;
    if ($this->isGitURI()) {
      $protocol = null;
    } else {
      if ($auth !== null) {
        $protocol = nonempty($this->protocol, 'http');
      }
    }

    $has_protocol = ($protocol !== null) && strlen($protocol);
    $has_auth = ($auth !== null);
    $has_domain = ($domain !== null) && strlen($domain);
    $has_port = ($port !== null) && strlen($port);

    if ($has_protocol || $has_auth || $has_domain) {
      if ($this->isGitURI()) {
        $prefix = "{$auth}{$domain}";
      } else {
        $prefix = "{$protocol}://{$auth}{$domain}";
      }

      if ($has_port) {
        $prefix .= ':'.$port;
      }
    }

    if ($this->query) {
      $query = '?'.phutil_build_http_querystring_from_pairs($this->query);
    } else {
      $query = null;
    }

    if (phutil_nonempty_string($this->getFragment())) {
      $fragment = '#'.$this->getFragment();
    } else {
      $fragment = null;
    }

    $path = $this->getPath();
    if ($this->isGitURI()) {
      if (strlen($path)) {
        $path = ':'.$path;
      }
    }

    return $prefix.$path.$query.$fragment;
  }

  /**
   * @deprecated
   */
  public function setQueryParam($key, $value) {
    // To set, we replace the first matching key with the new value, then
    // remove all other matching keys. This replaces the old value and retains
    // the parameter order.

    $is_null = ($value === null);

    // Typecheck and cast the key before we compare it to existing keys. This
    // raises an early exception if the key has a bad type.
    list($key) = phutil_http_parameter_pair($key, '');

    $found = false;
    foreach ($this->query as $list_key => $pair) {
      list($k, $v) = $pair;

      if ($k !== $key) {
        continue;
      }

      if ($found) {
        unset($this->query[$list_key]);
        continue;
      }

      $found = true;

      if ($is_null) {
        unset($this->query[$list_key]);
      } else {
        $this->insertQueryParam($key, $value, $list_key);
      }
    }

    $this->query = array_values($this->query);

    // If we didn't find an existing place to put it, add it to the end.
    if (!$found) {
      if (!$is_null) {
        $this->appendQueryParam($key, $value);
      }
    }

    return $this;
  }

  /**
   * @deprecated
   */
  public function setQueryParams(array $params) {
    $this->query = array();

    foreach ($params as $k => $v) {
      $this->appendQueryParam($k, $v);
    }

    return $this;
  }

  /**
   * @deprecated
   */
  public function getQueryParams() {
    $map = array();

    foreach ($this->query as $pair) {
      list($k, $v) = $pair;
      $map[$k] = $v;
    }

    return $map;
  }

  public function getQueryParamsAsMap() {
    $map = array();

    foreach ($this->query as $pair) {
      list($k, $v) = $pair;

      if (isset($map[$k])) {
        throw new Exception(
          pht(
            'Query parameters include a duplicate key ("%s") and can not be '.
            'nondestructively represented as a map.',
            $k));
      }

      $map[$k] = $v;
    }

    return $map;
  }

  public function getQueryParamsAsPairList() {
    return $this->query;
  }

  public function appendQueryParam($key, $value) {
    return $this->insertQueryParam($key, $value);
  }

  public function removeAllQueryParams() {
    $this->query = array();
    return $this;
  }

  public function removeQueryParam($remove_key) {
    list($remove_key) = phutil_http_parameter_pair($remove_key, '');

    foreach ($this->query as $idx => $pair) {
      list($key, $value) = $pair;

      if ($key !== $remove_key) {
        continue;
      }

      unset($this->query[$idx]);
    }

    $this->query = array_values($this->query);

    return $this;
  }

  public function replaceQueryParam($replace_key, $replace_value) {
    if ($replace_value === null) {
      throw new InvalidArgumentException(
        pht(
          'Value provided to "replaceQueryParam()" for key "%s" is NULL. '.
          'Use "removeQueryParam()" to remove a query parameter.',
          $replace_key));
    }

    $this->removeQueryParam($replace_key);
    $this->appendQueryParam($replace_key, $replace_value);
    return $this;
  }

  private function insertQueryParam($key, $value, $idx = null) {
    list($key, $value) = phutil_http_parameter_pair($key, $value);

    if ($idx === null) {
      $this->query[] = array($key, $value);
    } else {
      $this->query[$idx] = array($key, $value);
    }

    return $this;
  }

  private function initializeQueryParams($uri, array $params) {
    $have_params = array();
    foreach ($this->query as $pair) {
      list($key) = $pair;
      $have_params[$key] = true;
    }

    foreach ($params as $key => $value) {
      if (isset($have_params[$key])) {
        throw new InvalidArgumentException(
          pht(
            'You are trying to construct an ambiguous URI: query parameter '.
            '"%s" is present in both the string argument ("%s") and the map '.
            'argument.',
            $key,
            $uri));
      }

      if ($value === null) {
        continue;
      }

      $this->appendQueryParam($key, $value);
    }

    return $this;
  }

  public function setProtocol($protocol) {
    $this->protocol = $protocol;
    return $this;
  }

  public function getProtocol() {
    return $this->protocol;
  }

  public function setDomain($domain) {
    $this->domain = $domain;
    return $this;
  }

  public function getDomain() {
    return $this->domain;
  }

  public function setPort($port) {
    $this->port = $port;
    return $this;
  }
  public function getPort() {
    return $this->port;
  }

  public function getPortWithProtocolDefault() {
    static $default_ports = array(
      'http'  => '80',
      'https' => '443',
      'ssh'   => '22',
    );

    return nonempty(
      $this->getPort(),
      idx($default_ports, $this->getProtocol()),
      '');
  }

  public function setPath($path) {
    if ($this->isGitURI()) {
      // Git URIs use relative paths which do not need to begin with "/".
    } else {
      if ($this->domain && phutil_nonempty_string($path) && $path[0] !== '/') {
        $path = '/'.$path;
      }
    }

    $this->path = $path;
    return $this;
  }

  public function appendPath($path) {
    $first = strlen($path) ? $path[0] : null;
    $last  = strlen($this->path) ? $this->path[strlen($this->path) - 1] : null;

    if (!$this->path) {
      return $this->setPath($path);
    } else if ($first === '/' && $last === '/') {
      $path = substr($path, 1);
    } else if ($first !== '/' && $last !== '/') {
      $path = '/'.$path;
    }

    $this->path .= $path;
    return $this;
  }

  public function getPath() {
    return $this->path;
  }

  public function setFragment($fragment) {
    $this->fragment = $fragment;
    return $this;
  }

  public function getFragment() {
    return $this->fragment;
  }

  public function setUser($user) {
    $this->user = $user;
    return $this;
  }

  public function getUser() {
    return $this->user;
  }

  public function setPass($pass) {
    $this->pass = $pass;
    return $this;
  }

  public function getPass() {
    return $this->pass;
  }

  public function alter($key, $value) {
    $altered = clone $this;
    $altered->replaceQueryParam($key, $value);
    return $altered;
  }

  public function isGitURI() {
    return ($this->type == self::TYPE_GIT);
  }

  public function setType($type) {

    if ($type == self::TYPE_URI) {
      $path = $this->getPath();
      if (strlen($path) && ($path[0] !== '/')) {
        // Try to catch this here because we are not allowed to throw from
        // inside __toString() so we don't have a reasonable opportunity to
        // react properly if we catch it later.
        throw new Exception(
          pht(
            'Unable to convert URI "%s" into a standard URI because the '.
            'path is relative. Standard URIs can not represent relative '.
            'paths.',
            $this));
      }
    }

    $this->type = $type;
    return $this;
  }

  public function getType() {
    return $this->type;
  }

  private function isGitURIPattern($uri) {
    $matches = null;

    $ok = preg_match('(^(?P<head>[^/]+):(?P<last>(?!//).*)\z)', $uri, $matches);
    if (!$ok) {
      return false;
    }

    $head = $matches['head'];
    $last = $matches['last'];

    // If any part of this has spaces in it, it's not a Git URI. We fail here
    // so we fall back and don't fail more abruptly later.
    if (preg_match('(\s)', $head.$last)) {
      return false;
    }

    // If the second part only contains digits, assume we're looking at
    // casually specified "domain.com:123" URI, not a Git URI pointed at an
    // entirely numeric relative path.
    if (preg_match('(^\d+\z)', $last)) {
      return false;
    }

    // If the first part has a "." or an "@" in it, interpret it as a domain
    // or a "user@host" string.
    if (preg_match('([.@])', $head)) {
      return true;
    }

    // Otherwise, interpret the URI conservatively as a "javascript:"-style
    // URI. This means that "localhost:path" is parsed as a normal URI instead
    // of a Git URI, but we can't tell which the user intends and it's safer
    // to treat it as a normal URI.
    return false;
  }

}
