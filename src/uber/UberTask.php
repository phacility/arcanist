<?php

// class which encapsulates complexity of getting jira issue
final class UberTask extends Phobject {
  private $console;
  private $workflow;
  private $jql;
  private $url;

  const URL = 'https://arcanist-the-service.uberinternal.com/';
  const JIRA_CREATE_IN_URL = 'https://t3.uberinternal.com/secure/CreateIssueDetails!init.jspa?pid=%s&issuetype=10002&assignee=%s&summary=%s&description=%s';
  const JIRA_CREATE_URL = 'https://t3.uberinternal.com/secure/CreateIssue!default.jspa';
  const TASK_MSG = 'https://t3.uberinternal.com/browse/%s | %s';
  const REFRESH_MSG = 'Refresh task list';
  const SKIP_MSG = 'Skip issue attachment';
  const CREATE_IN_PROJ_MSG = 'Create new task in %s';
  const CREATE_MSG = 'Create new task';

  public function __construct(ArcanistWorkflow $workflow, $jql = '', $url = self::URL) {
    $this->console = PhutilConsole::getConsole();
    $this->workflow = $workflow;
    $this->jql = $jql;
    $this->url = $url;
  }

  public function getIssues() {
    $usso = new UberUSSO();
    $hostname = parse_url($this->url, PHP_URL_HOST);
    $token = $usso->maybeUseUSSOToken($hostname);
    if (!$token) {
      $token = $usso->getUSSOToken($hostname);
    }
    $payload = '{}';
    if ($this->jql) {
      $payload = json_encode(array('jql' => $this->jql));
    }
    $future = id(new HTTPSFuture($this->url, $payload))
      ->setFollowLocation(false)
      ->setMethod('POST')
      ->addHeader('Authorization', "Bearer ${token}")
      ->addHeader('Rpc-Caller', 'arcanist')
      ->addHeader('Rpc-Encoding', 'json')
      ->addHeader('Rpc-Procedure', 'ArcanistTheService::getIssues');
    list($body, $headers) = $future->resolvex();
    if (empty($body)) {
      return array();
    }
    $issues = phutil_json_decode($body);
    return idx($issues, 'issues', array());
  }

  public static function getJiraCreateIssueLink(
    $project_pid,
    $assignee,
    $summary,
    $description) {

    return sprintf(self::JIRA_CREATE_IN_URL,
                   urlencode($project_pid),
                   urlencode($assignee),
                   urlencode($summary),
                   urlencode($description));
  }

  public function getConduit() {
    return $this->workflow->getConduit();
  }

  public function openURIsInBrowser($uris) {
    return $this->workflow->openURIsInBrowser($uris);
  }

  public static function getTasksAndProjects($issues = array()) {
    $tasks = array();
    $projects = array();

    foreach ($issues as $issue) {
      $pkey = $issue['project']['key'];
      if (!isset($projects[$pkey])) {
        $projects[$pkey] = array(
          'id' => $issue['project']['id'],
          'tasks' => 0,
        );
      }
      $projects[$pkey]['tasks']++;
      $tasks[] = array('key' => $issue['key'], 'summary' => $issue['summary']);
    }
    return array($tasks, $projects);
  }

  public function getJiraIssuesForAttachment($message) {
    while (true) {
      $this->console->writeOut(pht('Fetching issues from jira, patience please.')."\n");
      $issues = array();
      try {
        $issues = $this->getIssues();
      } catch (Throwable $e) {
        $this->console->writeOut(pht("Something is wrong with jira, skipping...\n\n"));
        return array();
      }
      $for_search = array();

      list($tasks, $projects) = UberTask::getTasksAndProjects($issues);
      // add tasks to search list
      foreach ($tasks as $task) {
        $for_search[] = sprintf(self::TASK_MSG, $task['key'], $task['summary']);
      }
      // add refresh message
      $for_search[] = self::REFRESH_MSG;
      // need for way out in case user doesn't try using ESC/Ctrl+c/Ctrl+d
      $for_search[] = self::SKIP_MSG;
      // general jira task creation
      $for_search[] = self::CREATE_MSG;
      // get top 3 projects to display
      uasort($projects,
        function ($v1, $v2) {
          return $v2['tasks'] - $v1['tasks'];
      });
      $projects = array_slice($projects, 0, 3);
      // attach create task in project XXX to the list
      foreach ($projects as $project => $v) {
        $for_search[] = sprintf(self::CREATE_IN_PROJ_MSG, $project);
      }

      // prompt user to choose from menu
      $fzf = id(new UberFZF())
        ->requireFZF()
        ->setMulti(50)
        ->setHeader('Select issue to attach to Differential Revision '.
                    '(use tab for multiple selection)');
      $result = $fzf->fuzzyChoosePrompt($for_search);

      $issues = array();
      $project_urls = array();
      foreach ($result as $line) {
        // restart whole outer loop
        if (trim($line) == self::REFRESH_MSG) {
          continue 2;
        }
        if (trim($line) == self::SKIP_MSG) {
          return;
        }
        if (trim($line) == self::CREATE_MSG) {
          $this->openURIsInBrowser(array(UberTask::JIRA_CREATE_URL));
          if (phutil_console_confirm('Do you want to refresh task list?',
                                     $default_no = false)) {
            continue 2;
          }
          return;
        }
        // fetch chosen tasks
        list($issue) = sscanf($line, self::TASK_MSG);
        if ($issue) {
          $issues[] = $issue;
        }
        // fetch projects where user want to create task
        list($project) = sscanf($line, self::CREATE_IN_PROJ_MSG);
        if ($project) {
          static $email = null;
          if (!$email) {
            $result = $this->getConduit()->callMethodSynchronous(
              'user.whoami',
               array());
            $email = $result['primaryEmail'];
          }
          $summary = $message->getFieldValue('title');
          $description = $message->getFieldValue('summary');
          $url = UberTask::getJiraCreateIssueLink(
            $projects[$project]['id'],
            $email,
            // adding noise to make sure engineer puts some effort and
            // intent into issue creation
            '[AutoCreate] '.$summary,
            "Autocreated task description:\n".$description);
          $project_urls[] = $url;
        }
      }
      if (!empty($project_urls)) {
        $this->openURIsInBrowser($project_urls);
        if (phutil_console_confirm('Do you want to refresh task list?',
                                   $default_no = false)) {
          continue;
        }
      }
      if (!empty($issues)) {
        return $issues;
      }
    }
  }
}
