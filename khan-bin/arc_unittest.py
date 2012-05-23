#!/usr/bin/python

"""A test for the Khan Academy wrapper around arc.

This tests only the KA-specific features, like support for the --rr flag.
It mocks out all actual arc communication.
"""

import os
import unittest

# This is tricky since the script is called 'arc', not 'arc.py'.  The
# way we handle this is an 'exec' -- we use that instead of 'execfile'
# because it's easier to stub out '__main__' that way, which we need to
# do so we don't *run* arc when we 'import' it.  The downside
# is all of arc's symbols are imported into our namespace.  Oh well.
arc_contents = file(os.path.join(os.path.dirname(__file__), 'arc')).read()
arc_contents = arc_contents.replace('__main__', '__not_main__')
exec(arc_contents)


def FakeConduitCall(arc_root, conduit_name, json_input={}):
    """Returns a hard-coded value for any conduit-call we need."""
    if conduit_name == 'user.query':
        return [
            {
                "phid"     : "PHID-USER-00000000000000000000",
                "userName" : "csilvers",
                "realName" : "Craig Silverstein",
                "image"    : "",
                "uri"      : "https:\/\/example.com\/p\/csilvers\/"
                },
            {
                "phid"     : "PHID-USER-11111111111111111111",
                "userName" : "ben",
                "realName" : "Ben Bentastick",
                "image"    : "",
                "uri"      : "https:\/\/example.com\/p\/ben\/"
                },
            {
                "phid"     : "PHID-USER-22222222222222222222",
                "userName" : "echo",
                "realName" : "Ben Echoman",
                "image"    : "",
                "uri"      : "https:\/\/example.com\/p\/echo\/"
                },
            {
                "phid"     : "PHID-USER-33333333333333333333",
                "userName" : "toom",
                "realName" : "Toomany Bens",
                "image"    : "",
                "uri"      : "https:\/\/example.com\/p\/toom\/"
                },
            {
                "phid"     : "PHID-USER-44444444444444444444",
                "userName" : "Upper",
                "realName" : "Uppercase Username",
                "image"    : "",
                "uri"      : "https:\/\/example.com\/p\/Upper\/"
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


if __name__ == '__main__':
    unittest.main()
