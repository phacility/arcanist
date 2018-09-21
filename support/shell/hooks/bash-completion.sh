SCRIPTDIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" > /dev/null && pwd )"

# Try to generate the shell completion rules if they do not yet exist.
if [ ! -f "${SCRIPTDIR}/bash-rules.sh" ]; then
  arc shell-complete --generate >/dev/null 2>/dev/null
fi;

# Source the shell completion rules.
source "${SCRIPTDIR}/../rules/bash-rules.sh"
