#!/usr/bin/python

"""A test for the Khan Academy wrapper around arc.

This tests only the KA-specific features, like support for the --rr flag.
It mocks out all actual arc communication.
"""

import cStringIO
import copy
import json
import os
import subprocess
import sys
import tempfile
import unittest

# This is tricky since the script is called 'arc', not 'arc.py'.  The
# way we handle this is an 'exec' -- we use that instead of 'execfile'
# because it's easier to stub out '__main__' that way, which we need to
# do so we don't *run* arc when we 'import' it.  The downside
# is all of arc's symbols are imported into our namespace.  Oh well.
g_arc_root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
arc_contents = open(os.path.join(g_arc_root, 'khan-bin', 'arc')).read()
arc_contents = arc_contents.replace('__main__', '__not_main__')
exec(arc_contents)
# These are just to quiet lint.  They also document what we imported
_conduit_call = _conduit_call                   # @Nolint
NotGitError = NotGitError                       # @Nolint
_git_call = _git_call                           # @Nolint
_get_user_info = _get_user_info                 # @Nolint
_pick_a_number = _pick_a_number                 # @Nolint
normalize_usernames = normalize_usernames       # @Nolint
normalize_rr_flags = normalize_rr_flags         # @Nolint
add_onto_for_arc_land = add_onto_for_arc_land   # @Nolint
_update_arcrc = _update_arcrc                   # @Nolint


def FakeConduitCall(arc_root, conduit_name, json_input={}):
    """Returns a hard-coded value for any conduit-call we need."""
    if conduit_name == 'user.query':
        return [
            {
                "phid": "PHID-USER-00000000000000000000",
                "userName": "csilvers",
                "realName": "Craig Silverstein",
                "image": "",
                "uri": "https:\/\/example.com\/p\/csilvers\/",
                "roles": [
                  "admin"
                  ],
                },
            {
                "phid": "PHID-USER-11111111111111111111",
                "userName": "ben",
                "realName": "Ben Bentastick",
                "image": "",
                "uri": "https:\/\/example.com\/p\/ben\/",
                "roles": [],
                },
            {
                "phid": "PHID-USER-22222222222222222222",
                "userName": "echo",
                "realName": "Ben Echoman",
                "image": "",
                "uri": "https:\/\/example.com\/p\/echo\/",
                "roles": [],
                },
            {
                "phid": "PHID-USER-33333333333333333333",
                "userName": "toom",
                "realName": "Toomany Bens",
                "image": "",
                "uri": "https:\/\/example.com\/p\/toom\/",
                "roles": [],
                },
            {
                "phid": "PHID-USER-44444444444444444444",
                "userName": "Upper",
                "realName": "Uppercase Username",
                "image": "",
                "uri": "https:\/\/example.com\/p\/Upper\/",
                "roles": [],
                },
            {
                "phid": "PHID-USER-55555555555555555555",
                "userName": "admin1",
                "realName": "Enabled Admin",
                "image": "",
                "uri": "https:\/\/example.com\/p\/admin1\/",
                "roles": [
                  "admin",
                  ],
                },
            {
                "phid": "PHID-USER-66666666666666666666",
                "userName": "admin2",
                "realName": "Disabled Admin",
                "image": "",
                "uri": "https:\/\/example.com\/p\/admin2\/",
                "roles": [
                  "admin",
                  "disabled"
                  ],
                },
            ]
    raise NameError('Unexpected conduit_name %s' % conduit_name)


_conduit_call = FakeConduitCall

# We can't test pick_a_number, since it has raw_input.  We just always
# pick the largest numbers.
_pick_a_number = lambda prompt, max_number: max_number


class GetUserInfoTestCase(unittest.TestCase):
    def test_get_user_info(self):
        expected = [('csilvers', 'Craig Silverstein'),
                    ('ben', 'Ben Bentastick'),
                    ('echo', 'Ben Echoman'),
                    ('toom', 'Toomany Bens'),
                    ('Upper', 'Uppercase Username'),
                    ('admin1', 'Enabled Admin'),
                    ]
        actual = _get_user_info('')
        self.assertEqual(expected, actual)


class NormalizeUsernamesTestCase(unittest.TestCase):
    def test_exact_username_match(self):
        actual = normalize_usernames('', ['csilvers'])
        self.assertEqual(['csilvers'], actual)

    def test_partial_username_match(self):
        actual = normalize_usernames('', ['silver'])
        self.assertEqual(['csilvers'], actual)

    def test_exact_realname_match(self):
        actual = normalize_usernames('', ['Craig Silverstein'])
        self.assertEqual(['csilvers'], actual)

    def test_partial_realname_match(self):
        actual = normalize_usernames('', ['stein'])
        self.assertEqual(['csilvers'], actual)

    def test_case_insensitivity(self):
        actual = normalize_usernames('', ['upper'])
        self.assertEqual(['Upper'], actual)
        actual = normalize_usernames('', ['uppercase'])
        self.assertEqual(['Upper'], actual)

    def test_multiple_username_match(self):
        actual = normalize_usernames('', ['ben'])
        # Our pick-a-number mock always chooses the largest number,
        # which picks the username that comes last in sort order.
        self.assertEqual(['toom'], actual)

    def test_no_username_match(self):
        self.assertRaises(NameError, normalize_usernames, '', ['nobody'])

    def test_empty_username_match(self):
        actual = normalize_usernames('', [''])
        self.assertEqual(['toom'], actual)

    def test_multiple_inputs(self):
        actual = normalize_usernames('', ['csilvers', 'ben'])
        self.assertEqual(['csilvers', 'toom'], actual)

    def test_order_is_preserved(self):
        actual = normalize_usernames('', ['csilvers', 'ben'])
        self.assertEqual(['csilvers', 'toom'], actual)
        actual = normalize_usernames('', ['ben', 'csilvers'])
        self.assertEqual(['toom', 'csilvers'], actual)

    def test_does_not_remove_duplicates(self):
        actual = normalize_usernames('', ['echoman', 'chom'])
        self.assertEqual(['echo', 'echo'], actual)

    def test_ignores_disabled_users(self):
        actual = normalize_usernames('', ['admin'])
        self.assertEqual(['admin1'], actual)

    def test_ignores_disabled_users_no_results(self):
        self.assertRaises(NameError, normalize_usernames, '', ['disabled'])


class NormalizeRRFlagsTestCase(unittest.TestCase):
    def test_one_flag_one_username(self):
        actual = normalize_rr_flags('', ['--rr=csilvers'])
        self.assertEqual(['--reviewers', 'csilvers'], actual)

    def test_one_flag_many_usernames(self):
        actual = normalize_rr_flags('', ['--rr=ben'])
        self.assertEqual(['--reviewers', 'toom'], actual)

    def test_one_flag_surrounded_by_others(self):
        actual = normalize_rr_flags('', ['diff', '--rr=ben', '--verbatim'])
        self.assertEqual(['diff', '--reviewers', 'toom', '--verbatim'], actual)

    def test_no_flags(self):
        actual = normalize_rr_flags('', ['diff', '--verbatim'])
        self.assertEqual(['diff', '--verbatim'], actual)

    def test_one_flag_with_commas(self):
        actual = normalize_rr_flags('', ['--rr=csilvers,ben'])
        self.assertEqual(['--reviewers', 'csilvers,toom'], actual)

    def test_one_flag_with_commas_preserves_order(self):
        actual = normalize_rr_flags('', ['--rr=csilvers,ben'])
        self.assertEqual(['--reviewers', 'csilvers,toom'], actual)
        actual = normalize_rr_flags('', ['--rr=ben,csilvers'])
        self.assertEqual(['--reviewers', 'toom,csilvers'], actual)

    def test_two_flags_one_username_each(self):
        actual = normalize_rr_flags('', ['--rr=csilvers', '--rr=echoman'])
        self.assertEqual(['--reviewers', 'csilvers,echo'], actual)

    def test_two_flags_ambiguous_usernames(self):
        actual = normalize_rr_flags('', ['--rr=csilvers', '--rr=ben'])
        self.assertEqual(['--reviewers', 'csilvers,toom'], actual)

    def test_two_flags_and_commas(self):
        actual = normalize_rr_flags('', ['--rr=csilvers,echoman', '--rr=ben'])
        self.assertEqual(['--reviewers', 'csilvers,echo,toom'], actual)

    def test_two_flags_and_commas_preserves_order(self):
        actual = normalize_rr_flags('', ['--rr=csilvers,echoman', '--rr=ben'])
        self.assertEqual(['--reviewers', 'csilvers,echo,toom'], actual)
        actual = normalize_rr_flags('', ['--rr=ben', '--rr=csilvers,echoman'])
        self.assertEqual(['--reviewers', 'toom,csilvers,echo'], actual)

    def test_space_instead_of_equals(self):
        actual = normalize_rr_flags('', ['--rr', 'csilvers,echoman',
                                         '--rr', 'ben'])
        self.assertEqual(['--reviewers', 'csilvers,echo,toom'], actual)

    def test_inserting_as_first_flag(self):
        actual = normalize_rr_flags('', ['diff', '--verbatim',
                                         '--rr', 'csilvers,echoman',
                                         '--dry_run',
                                         '--rr', 'ben'])
        self.assertEqual(['diff', '--reviewers', 'csilvers,echo,toom',
                          '--verbatim', '--dry_run'],
                         actual)


class ArcLandTestCase(unittest.TestCase):
    def setUp(self):
        self._orig_git_call = _git_call
        super(ArcLandTestCase, self).setUp()

    def tearDown(self):
        global _git_call
        super(ArcLandTestCase, self).tearDown()
        _git_call = self._orig_git_call

    def set_git_retval(self, retval):
        global _git_call
        _git_call = lambda *args, **kwargs: retval

    def set_git_raises(self, exception_type, *args, **kwargs):
        def raise_error():
            raise exception_type(*args, **kwargs)

        global _git_call
        _git_call = lambda *args, **kwargs: raise_error()

    def test_feature_branch(self):
        self.set_git_retval('master')
        self.assertEqual(['land', '--onto', 'master'],
                         add_onto_for_arc_land(['land']))
        
        self.set_git_retval('some_other_branch')
        self.assertEqual(['land', '--onto', 'some_other_branch'],
                         add_onto_for_arc_land(['land']))

    def test_remote_branch(self):
        self.set_git_retval('origin/master')
        with self.assertRaises(RuntimeError):
            add_onto_for_arc_land(['land'])

    def test_nontracking_branch(self):
        self.set_git_raises(subprocess.CalledProcessError, None, None)
        with self.assertRaises(RuntimeError):
            add_onto_for_arc_land(['land'])

    def test_not_a_git_repo(self):
        self.set_git_raises(NotGitError)
        self.assertEqual(['land'], add_onto_for_arc_land(['land']))

    def test_already_has_onto(self):
        self.set_git_retval('unused_branch')
        self.assertEqual(['land', '--onto', 'master'],
                         add_onto_for_arc_land(['land', '--onto', 'master']))
        self.assertEqual(['land', '--onto=master'],
                         add_onto_for_arc_land(['land', '--onto=master']))


class UpdateArcrcTest(unittest.TestCase):
    def setUp(self):
        self.start_json = {'a': {'b': {'c': [1, 2, 3]}, 'd': 'hello'},
                           'b': 'top-level b',
                           'stays': {'under': 'stays'},
                           'some': {'stay': 'immutable', 'rest': 'mutable'},
                           'list': ['top-level list', 4, 5, {'m': 1}],
                           'khan': {'do_not_auto_update':
                                    ['stays', 'some/stay']},
                           }
        (fd, filename1) = tempfile.mkstemp(prefix='arc_unittest_arcrc')
        os.write(fd, json.dumps(self.start_json))
        os.close(fd)
        self.arcrc = filename1

        (fd, filename2) = tempfile.mkstemp(prefix='arc_unittest_khan_arcrc')
        os.close(fd)
        self.default_arcrc = filename2

        # We want to test the stderr output as well.
        self.old_stderr = sys.stderr
        sys.stderr = cStringIO.StringIO()

    def tearDown(self):
        sys.stderr = self.old_stderr
        if os.path.exists(self.arcrc):
            os.unlink(self.arcrc)
        if os.path.exists(self.default_arcrc):
            os.unlink(self.default_arcrc)

    def _update_with_this_default(self, default_dict):
        """Write the dict as json to the .arcrc, and calls update_arcrc."""
        f = open(self.default_arcrc, 'w')
        json.dump(default_dict, f)
        f.close()
        _update_arcrc(g_arc_root,
                      user_arcrc_file=self.arcrc,
                      default_arcrc_file=self.default_arcrc,
                      create_bak=False)
        return json.load(open(self.arcrc))

    def _arcrc_was_updated(self):
        """Return true if the test modified the .arcrc file, false else."""
        # We can tell because when we write the arcrc file in
        # _update_dict, there's no indent= argument, so it's all on
        # one line.  But _update_arcrc uses indent=, so it's on many
        # lines.  If the file is now on many lines, _update_arcrc did
        # some updating.
        content = open(self.arcrc).read()
        return content.count('\n') > 1

    def assert_in_stderr(self, *args):
        """Assert that all arg occurred somewhere in the stderr output."""
        stderr_output = sys.stderr.getvalue()
        for s in args:
            self.assertTrue(s in stderr_output, (s, stderr_output))

    def assert_stderr_is_empty(self):
        stderr_output = sys.stderr.getvalue()
        self.assertFalse(stderr_output)   # should be no output

    def test_update_nonexistent_file(self):
        os.unlink(self.arcrc)
        self._update_with_this_default({'a': 1, 'b': 2})

        expected = {'a': 1, 'b': 2}
        actual = json.load(open(self.arcrc))
        self.assertEqual(expected, actual)
        self.assertTrue(self._arcrc_was_updated())
        self.assert_in_stderr(':a ', '  WAS: <empty>\n  NOW: 1\n')
        self.assert_in_stderr(':b ', '  WAS: <empty>\n  NOW: 2\n')

    def test_update_with_same_default(self):
        """When the update is the same as the original, it's a noop."""
        expected = self.start_json
        actual = self._update_with_this_default(self.start_json)
        self.assertEqual(expected, actual)
        self.assertFalse(self._arcrc_was_updated())
        self.assert_stderr_is_empty()

    def test_simple_addition(self):
        expected = copy.deepcopy(self.start_json)
        expected['new'] = 'all new'
        actual = self._update_with_this_default({'new': 'all new'})
        self.assertEqual(expected, actual)
        self.assertTrue(self._arcrc_was_updated())
        self.assert_in_stderr(':new ', '  WAS: <empty>\n  NOW: all new\n')

    def test_simple_change(self):
        expected = copy.deepcopy(self.start_json)
        expected['b'] = 'changed'
        actual = self._update_with_this_default({'b': 'changed'})
        self.assertEqual(expected, actual)
        self.assertTrue(self._arcrc_was_updated())
        self.assert_in_stderr(':b ', '  WAS: top-level b\n  NOW: changed\n')

    def test_deep_addition(self):
        expected = copy.deepcopy(self.start_json)
        expected['a']['newb'] = 'new'
        actual = self._update_with_this_default({'a': {'newb': 'new'}})
        self.assertEqual(expected, actual)
        self.assertTrue(self._arcrc_was_updated())
        self.assert_in_stderr(':a/newb ', '  WAS: <empty>\n  NOW: new\n')

    def test_deep_change(self):
        expected = copy.deepcopy(self.start_json)
        expected['a']['b'] = 'new'
        actual = self._update_with_this_default({'a': {'b': 'new'}})
        self.assertEqual(expected, actual)
        self.assertTrue(self._arcrc_was_updated())
        self.assert_in_stderr(':a/b ',
                              "  WAS: {u'c': [1, 2, 3]}\n  NOW: new\n")

    def test_dict_to_notdict(self):
        expected = copy.deepcopy(self.start_json)
        expected['a'] = 'no longer a dict'
        actual = self._update_with_this_default({'a': 'no longer a dict'})
        self.assertEqual(expected, actual)
        self.assertTrue(self._arcrc_was_updated())
        old_a = "{u'b': {u'c': [1, 2, 3]}, u'd': u'hello'}"
        self.assert_in_stderr(':a ',
                              '  WAS: %s\n  NOW: no longer a dict\n' % old_a)

    def test_notdict_to_dict(self):
        expected = copy.deepcopy(self.start_json)
        expected['a']['b']['c'] = {'1': 2, '3': 4}
        actual = self._update_with_this_default(
            {'a': {'b': {'c': {'1': 2, '3': 4}}}})
        self.assertEqual(expected, actual)
        self.assertTrue(self._arcrc_was_updated())
        self.assert_in_stderr(':a/b/c ',
                              "  WAS: [1, 2, 3]\n  NOW: {u'1': 2, u'3': 4}\n")

    def test_nochange(self):
        expected = copy.deepcopy(self.start_json)
        actual = self._update_with_this_default({'b': 'top-level b'})
        self.assertEqual(expected, actual)
        self.assertFalse(self._arcrc_was_updated())
        self.assert_stderr_is_empty()

    def test_no_auto_update(self):
        expected = self.start_json
        actual = self._update_with_this_default({'stays': 'changes'})
        self.assertEqual(expected, actual)
        self.assertFalse(self._arcrc_was_updated())
        self.assert_stderr_is_empty()

    def test_nested_no_auto_update(self):
        expected = self.start_json
        actual = self._update_with_this_default({'some': {'stay': 'changes'}})
        self.assertEqual(expected, actual)
        self.assertFalse(self._arcrc_was_updated())
        self.assert_stderr_is_empty()

    def test_nested_mix_auto_update_and_not(self):
        expected = copy.deepcopy(self.start_json)
        expected['some']['rest'] = 'change'
        expected['some']['new'] = 'hi'
        actual = self._update_with_this_default({'some': {'stay': 'changes',
                                                          'rest': 'change',
                                                          'new': 'hi'}})
        self.assertEqual(expected, actual)
        self.assertTrue(self._arcrc_was_updated())
        self.assert_in_stderr(':some/rest ',
                              '  WAS: mutable\n  NOW: change\n')
        self.assert_in_stderr(':some/new ',
                              '  WAS: <empty>\n  NOW: hi\n')


if __name__ == '__main__':
    unittest.main()
