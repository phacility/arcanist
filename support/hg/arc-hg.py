from __future__ import absolute_import

import os
import json

from mercurial import (
  cmdutil,
  bookmarks,
  bundlerepo,
  error,
  hg,
  i18n,
  node,
  registrar,
)

_ = i18n._
cmdtable = {}
command = registrar.command(cmdtable)

@command(
  "arc-ls-remote",
  [('', 'output', '',
    _('file to output refs to'), _('FILE')),
  ] + cmdutil.remoteopts,
  _('[--output FILENAME] [SOURCE]'))
def lsremote(ui, repo, source="default", **opts):
  """list markers in a remote

  Show the current branch heads and bookmarks in a specified path/URL or the
  default pull location.

  Markers are printed to stdout in JSON.

  (This is an Arcanist extension to Mercurial.)

  Returns 0 if listing the markers succeeds, 1 otherwise.
  """

  # Disable status output from fetching a remote.
  ui.quiet = True

  source, branches = hg.parseurl(ui.expandpath(source))
  remote = hg.peer(repo, opts, source)

  markers = []

  bundle, remotebranches, cleanup = bundlerepo.getremotechanges(
    ui,
    repo,
    remote)

  try:
    for n in remotebranches:
      ctx = bundle[n]
      markers.append({
        'type': 'branch',
        'name': ctx.branch(),
        'node': node.hex(ctx.node()),
      })
  finally:
    cleanup()

  with remote.commandexecutor() as e:
    remotemarks = bookmarks.unhexlifybookmarks(e.callcommand('listkeys', {
        'namespace': 'bookmarks',
    }).result())

  for mark in remotemarks:
    markers.append({
      'type': 'bookmark',
      'name': mark,
      'node': node.hex(remotemarks[mark]),
    })

  json_opts = {
    'indent': 2,
    'sort_keys': True,
  }

  output_file = opts.get('output')
  if output_file:
    if os.path.exists(output_file):
      raise error.Abort(_('File "%s" already exists.' % output_file))
    with open(output_file, 'w+') as f:
      json.dump(markers, f, **json_opts)
  else:
    print json.dumps(markers, output_file, **json_opts)

  return 0
