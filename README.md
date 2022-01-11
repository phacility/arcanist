# Eva's Arcanist fork
Arcanist is the command-line tool for [Phabricator](http://phabricator.org).
It allows you to interact with Phabricator installs to send code for review,
download patches, transfer files, view status, make API calls, and various other
things. You can read more in the [User Guide](https://secure.phabricator.com/book/phabricator/article/arcanist/)

For more information about Phabricator, see http://phabricator.org/.

This fork uses commit `c04f141ab0231e593a513356b3832a30f9404627` of Phaciliy's Arcanist, and commit `0a4487d37cd72b3b91ac332377f2b12d4e5a2543` of [libphutil](https://github.com/phacility/libphutil). This combination has seen less bugs than with Arcanist's most recent releases.

## Installation

#### Clone somewhere on your computer

```bash
git clone https://github.com/EvaCoop/arcanist.git
```

#### Assuming a Unix installation:
```bash
export PATH="$PATH:/somewhere/arcanist/bin/"
```

You now need to reset your terminal for the new changes to `.bashrc` to take effect.

If this doesn't work, you can manually edit `.bashrc`, adding this before the end of file `export PATH`
```
PATH=/<installation path>/arcanist/bin/:$PATH
```

## Project Setup
On your repo, if not present, create a `.arcconfig` file:
```bash
yourproject/ $ $EDITOR .arcconfig
yourproject/ $ cat .arcconfig
{
  "phabricator.uri" : "https://phabricator.eva.cab"
}
```
Install the certificate (follow the instructions):
```bash
arc install-certificate
```
## Usage @ Eva
#### Starting a task
Create a new ticket. Number of the task is T10 (it is written on phabricator on your task link).

`arc feature T10-api-stuff-key`

This creates a new git branch, checkout & links it to the Phabricator task!

`[ WORK MANY HOURS ..]`

#### Committing your work
```
git add -A
git commit -m "Fix T${PHABRICATOR_TASK_NUMBER} - Description of work"
```
 You can do many commits.


#### Sending changes for review

`arc diff`

Now a wild pokemon appears with many sections.

`Summary` : Summary of the task

`Test Plan` : Steps to reproduce the test to test the ticket for the reviewer

`Reviewers` : phabricator uid of the person that is reviewing (raphaelg)

`Subscribers`: phabricators uid of some other watchers that should read the code


When done entering the fields: `^O` to save, Enter, then `^X` to exit.

```
[The code is now sent to a reviewer that you have to wait]
```

#### Completing work
Some hours or days later, your code is either accepted or has some changes to do. 

You repeat the process of commiting and doing `arc diff` when changes are needed.

When the code is accepted, you do `arc land`! Your code is now merged with master. Congrats! 


## LICENSE

Arcanist is released under the Apache 2.0 license except as otherwise noted.