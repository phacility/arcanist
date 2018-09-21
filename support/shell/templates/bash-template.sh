_arcanist_complete_{{{BIN}}} ()
{
  COMPREPLY=()

  CUR="${COMP_WORDS[COMP_CWORD]}"
  OPTS=$(echo | {{{BIN}}} shell-complete --current ${COMP_CWORD} -- ${COMP_WORDS[@]} 2>/dev/null)

  if [ $? -ne 0 ]; then
    return $?
  fi

  if [ "$OPTS" = "FILE" ]; then
    COMPREPLY=( $(compgen -f -- ${CUR}) )
    return 0
  fi

  if [ "$OPTS" = "ARGUMENT" ]; then
    return 0
  fi

  COMPREPLY=( $(compgen -W "${OPTS}" -- ${CUR}) )
}
complete -F _arcanist_complete_{{{BIN}}} -o filenames {{{BIN}}}
