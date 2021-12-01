from __future__ import absolute_import
import sys

is_python_3 = sys.version_info[0] >= 3

if is_python_3:
  def arc_items(dict):
    return dict.items()
else:
  def arc_items(dict):
    return dict.iteritems()

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
)

_ = i18n._
cmdtable = {}

# Older veresions of Mercurial (~4.7) moved the command function and the
# remoteopts object to different modules. Using try/except here to attempt
# allowing this module to load properly, despite whether individual commands
# will work properly on older versions of Mercurial or not.
# https://phab.mercurial-scm.org/rHG46ba2cdda476ac53a8a8f50e4d9435d88267db60
# https://phab.mercurial-scm.org/rHG04baab18d60a5c833ab3190506147e01b3c6d12c
try:
  from mercurial import registrar
  command = registrar.command(cmdtable)
except:
  command = cmdutil.command(cmdtable)

try:
  if "remoteopts" in cmdutil:
    remoteopts = cmdutil.remoteopts
except:
  from mercurial import commands
  remoteopts = commands.remoteopts

@command(
  b'arc-amend',
  [
    (b'l',
      b'logfile',
      b'',
      _(b'read commit message from file'),
      _(b'FILE')),
    (b'm',
      b'message',
      b'',
      _(b'use text as commit message'),
      _(b'TEXT')),
    (b'u',
      b'user',
      b'',
      _(b'record the specified user as committer'),
      _(b'USER')),
    (b'd',
      b'date',
      b'',
      _(b'record the specified date as commit date'),
      _(b'DATE')),
    (b'A',
      b'addremove',
      False,
      _(b'mark new/missing files as added/removed before committing')),
    (b'n',
      b'note',
      b'',
      _(b'store a note on amend'),
      _(b'TEXT')),
  ],
  _(b'[OPTION]'))
def amend(ui, repo, source=None, **opts):
  """amend

  Uses Mercurial internal API to amend changes to a non-head commit.

  (This is an Arcanist extension to Mercurial.)

  Returns 0 if amending succeeds, 1 otherwise.
  """

  # The option keys seem to come in as 'str' type but the cmdutil.amend() code
  # expects them as binary. To account for both Python 2 and Python 3
  # compatibility, insert the value under both 'str' and binary type.
  newopts = {}
  for key in opts:
    val = opts.get(key)
    newopts[key] = val
    if isinstance(key, str):
      newkey = key.encode('UTF-8')
      newopts[newkey] = val

  orig = repo[b'.']
  extra = {}
  pats = []
  cmdutil.amend(ui, repo, orig, extra, pats, newopts)

  """
  # This will allow running amend on older versions of Mercurial, ~3.5, however
  # the behavior on those versions will squash child commits of the working
  # directory into the amended commit which is undesired.
  try:
    cmdutil.amend(ui, repo, orig, extra, pats, newopts)
  except:
    def commitfunc(ui, repo, message, match, opts):
      return repo.commit(
        message,
        opts.get('user') or orig.user(),
        opts.get('date') or orig.date(),
        match,
        extra=extra)
    cmdutil.amend(ui, repo, commitfunc, orig, extra, pats, newopts)
  """

  return 0

@command(
  b'arc-ls-markers',
  [
    (b'',
      b'output',
      b'',
    _(b'file to output refs to'),
    _(b'FILE')),
  ] + remoteopts,
  _(b'[--output FILENAME] [SOURCE]'))
def lsmarkers(ui, repo, source=None, **opts):
  """list markers

  Show the current branch heads and bookmarks in the local working copy, or
  a specified path/URL.

  Markers are printed to stdout in JSON.

  (This is an Arcanist extension to Mercurial.)

  Returns 0 if listing the markers succeeds, 1 otherwise.
  """

  if source is None:
    markers = localmarkers(ui, repo)
  else:
    markers = remotemarkers(ui, repo, source, opts)

  for m in markers:
    if m['name'] != None:
      m['name'] = m['name'].decode('utf-8')

    if m['node'] != None:
      m['node'] = m['node'].decode('utf-8')

    if m['description'] != None:
      m['description'] = m['description'].decode('utf-8')

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
    json_data = json.dumps(markers, **json_opts)
    print(json_data)

  return 0

def localmarkers(ui, repo):
  markers = []

  active_node = repo[b'.'].node()
  all_heads = set(repo.heads())
  current_name = repo.dirstate.branch()

  branch_list = repo.branchmap().iterbranches()
  for branch_name, branch_heads, tip_node, is_closed in branch_list:
    for head_node in branch_heads:

      is_active = False
      if branch_name == current_name:
        if head_node == active_node:
          is_active = True

      is_tip = (head_node == tip_node)

      if is_closed:
        head_closed = True
      else:
        head_closed = bool(head_node not in all_heads)

      description = repo[head_node].description()

      markers.append({
        'type': 'branch',
        'name': branch_name,
        'node': node.hex(head_node),
        'isActive': is_active,
        'isClosed': head_closed,
        'isTip': is_tip,
        'description': description,
      })

  bookmarks = repo._bookmarks
  active_bookmark = repo._activebookmark

  for bookmark_name, bookmark_node in arc_items(bookmarks):
    is_active = (active_bookmark == bookmark_name)
    description = repo[bookmark_node].description()

    markers.append({
      'type': 'bookmark',
      'name': bookmark_name,
      'node': node.hex(bookmark_node),
      'isActive': is_active,
      'description': description,
    })

  # Add virtual markers for the current commit state and current branch state
  # so callers can figure out exactly where we are.

  # Common cases where this matters include:

  # You run "hg update 123" to update to an older revision. Your working
  # copy commit will not be a branch head or a bookmark.

  # You run "hg branch X" to create a new branch, but have not made any commits
  # yet. Your working copy branch will not be reflected in any commits.

  markers.append({
    'type': 'branch-state',
    'name': current_name,
    'node': None,
    'isActive': True,
    'isClosed': False,
    'isTip': False,
    'description': None,
  })

  markers.append({
    'type': 'commit-state',
    'name': None,
    'node': node.hex(active_node),
    'isActive': True,
    'isClosed': False,
    'isTip': False,
    'description': repo[b'.'].description(),
  })

  return markers

def remotemarkers(ui, repo, source, opts):
  # Disable status output from fetching a remote.
  ui.quiet = True

  markers = []

  source, branches = hg.parseurl(ui.expandpath(source))
  remote = hg.peer(repo, opts, source)

  with remote.commandexecutor() as e:
    branchmap = e.callcommand(b'branchmap', {}).result()

  for branch_name in branchmap:
    for branch_node in branchmap[branch_name]:
      markers.append({
        'type': 'branch',
        'name': branch_name,
        'node': node.hex(branch_node),
        'description': None,
      })

  with remote.commandexecutor() as e:
    remotemarks = bookmarks.unhexlifybookmarks(e.callcommand(b'listkeys', {
      b'namespace': b'bookmarks',
    }).result())

  for mark in remotemarks:
    markers.append({
      'type': 'bookmark',
      'name': mark,
      'node': node.hex(remotemarks[mark]),
      'description': None,
    })

  return markers
