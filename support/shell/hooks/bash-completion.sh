SCRIPTDIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" > /dev/null && pwd )"
GENERATED_RULES_FILE="${SCRIPTDIR}/../rules/bash-rules.sh"

# Try to generate the shell completion rules if they do not yet exist.
if [ ! -f "${GENERATED_RULES_FILE}" ]; then
  arc shell-complete --generate >/dev/null 2>/dev/null
fi;

# Source the shell completion rules.
source "${GENERATED_RULES_FILE}"
