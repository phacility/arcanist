_arcanist_complete_{{{BIN}}} ()
{
  COMPREPLY=()

  RESULT=$(echo | {{{BIN}}} shell-complete \
    --current ${COMP_CWORD} \
    -- \
    "${COMP_WORDS[@]}" \
    2>/dev/null)

  if [ $? -ne 0 ]; then
    return $?
  fi

  if [ "$RESULT" == "<compgen:file>" ]; then
    RESULT=$( compgen -A file -- ${COMP_WORDS[COMP_CWORD]} )
  fi

  local IFS=$'\n'
  COMPREPLY=( $RESULT )
}
complete -F _arcanist_complete_{{{BIN}}} -o filenames {{{BIN}}}
