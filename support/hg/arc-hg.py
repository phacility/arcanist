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
  registrar,
)

_ = i18n._
cmdtable = {}
command = registrar.command(cmdtable)

@command(
  b'arc-ls-markers',
  [(b'', b'output', b'',
    _(b'file to output refs to'), _(b'FILE')),
  ] + cmdutil.remoteopts,
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
  saw_current = False
  saw_active = False

  branch_list = repo.branchmap().iterbranches()
  for branch_name, branch_heads, tip_node, is_closed in branch_list:
    for head_node in branch_heads:
      is_active = (head_node == active_node)
      is_tip = (head_node == tip_node)
      is_current = (branch_name == current_name)

      if is_current:
        saw_current = True

      if is_active:
        saw_active = True

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
        'isCurrent': is_current,
        'description': description,
      })

  # If the current branch (selected with "hg branch X") is not reflected in
  # the list of heads we selected, add a virtual head for it so callers get
  # a complete picture of repository marker state.

  if not saw_current:
    markers.append({
      'type': 'branch',
      'name': current_name,
      'node': None,
      'isActive': False,
      'isClosed': False,
      'isTip': False,
      'isCurrent': True,
      'description': None,
    })

  bookmarks = repo._bookmarks
  active_bookmark = repo._activebookmark

  for bookmark_name, bookmark_node in arc_items(bookmarks):
    is_active = (active_bookmark == bookmark_name)
    description = repo[bookmark_node].description()

    if is_active:
      saw_active = True

    markers.append({
      'type': 'bookmark',
      'name': bookmark_name,
      'node': node.hex(bookmark_node),
      'isActive': is_active,
      'description': description,
    })

  # If the current working copy state is not the head of a branch and there is
  # also no active bookmark, add a virtual marker for it so callers can figure
  # out exactly where we are.

  if not saw_active:
    markers.append({
      'type': 'commit',
      'name': None,
      'node': node.hex(active_node),
      'isActive': False,
      'isClosed': False,
      'isTip': False,
      'isCurrent': True,
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
