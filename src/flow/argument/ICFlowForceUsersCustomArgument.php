<?php

final class ICFlowForceUsersCustomArgument extends ICCustomArcanistArgument {

  public function supportsCommand($command) {
    return $command === 'flow';
  }

  public function getConfigurationKey() {
    return 'force-flow';
  }
  
  public function getArguments() {
    return array();
  }

  public function willRunWorkflow($command, ArcanistWorkflow $workflow) {
    $user_role_config = new ICArcanistUserRoles();
    $conduit = $workflow->getConduit();
    $user_projects = $conduit->callMethodSynchronous('project.search', array(
      'constraints' => array(
        'members' => array(
          $workflow->getUserPHID(),
        ),
        'slugs' => $user_role_config->getAllPossibleProjectSlugs(),
      ),
    ));
    $user_project_slugs = array();
    foreach (idx($user_projects, 'data', array()) as $project) {
      $user_project_slugs[] = idxv($project, array('fields', 'slug'));
    }
    $user_project_slugs = array_filter($user_project_slugs);

    if ($user_role_config->projectsContainRole($user_project_slugs, 'asdf-forced')) {
      throw new ArcanistUsageException(
        phutil_console_format(
          "\n\n".
          "Please use **asdf branch** instead of **arc flow**.\n\n".
          "You can learn more about **asdf** via **asdf help**.\n\n".
          ICAsciiArtLibrary::cat3()));
    }
  }

}
