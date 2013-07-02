<?php

/**
 * Displays User Tasks
 *
 * @group workflow
 */
final class ArcanistTasksWorkflow extends ArcanistBaseWorkflow {

  private $tasks;

  public function getWorkflowName() {
    return 'tasks';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **tasks** [__options__]
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
        View all assigned tasks.
EOTEXT
      );
  }

  public function requiresConduit() {
    return true;
  }

  public function requiresRepositoryAPI() {
    return false;
  }

  public function requiresAuthentication() {
    return true;
  }


  public function getArguments() {
    return array(
      'status' => array(
        'param' => 'task_status',
        'help' => "Show tasks that or open or closed, default is open.",
      ),
      'owner' => array(
        'param' => 'username',
        'paramtype' => 'username',
        'help' =>
          "Only show tasks assigned to the given username, ".
            "also accepts @all to show all, default is you.",
        'conflict' => array(
          "unassigned" => "--owner suppresses unassigned",
        ),
      ),
      'order' => array(
        'param' => 'task_order',
        'help' =>
          "Arrange tasks based on priority, created, or modified, ".
            "default is priority.",
      ),
      'limit' => array(
        'param' => 'n',
        'paramtype' => 'int',
        'help' => "Limit the amount of tasks outputted, default is all.",
      ),
      'unassigned' => array(
        'help' => "Only show tasks that are not assigned (upforgrabs).",
      )
    );
  }

  public function run() {
    $output = array();

    $status     = $this->getArgument('status');
    $owner      = $this->getArgument('owner');
    $order      = $this->getArgument('order');
    $limit      = $this->getArgument('limit');
    $unassigned = $this->getArgument('unassigned');

    if ($owner) {
      $owner_phid = $this->findOwnerPhid($owner);
    } elseif ($unassigned) {
      $owner_phid = null;
    } else {
      $owner_phid = $this->getUserPHID();
    }

    $this->tasks = $this->loadManiphestTasks(
      ($status == 'all' ? 'any' : $status),
      $owner_phid,
      $order,
      $limit);

    if (!$this->tasks) {
      echo "No tasks found.\n";
      return 0;
    }

    $task_rows = array();
    foreach ($this->tasks as $task) {
      $output = array();

      // Render the "T123" column.
      $task_id = "T".$task['id'];
      $formatted_task_id = phutil_console_format(
        '**%s**',
        $task_id);
      $output['id'] = array(
        'text' => $formatted_task_id,
        'len'  => phutil_utf8_console_strlen($task_id),
      );

      // Render the "Title" column.
      $formatted_title = rtrim($task['title']);
      $output['title'] = array(
        'text' => $formatted_title,
        'len'  => phutil_utf8_console_strlen($formatted_title),
      );

      // Render the "Priority" column.
      switch ($task['priority']) {
        case 'Needs Triage':
          $color = 'magenta';
        break;
        case 'Unbreak Now!':
          $color = 'red';
        break;
        case 'High':
          $color = 'yellow';
        break;
        case 'Normal':
          $color = 'green';
        break;
        case 'Low':
          $color = 'blue';
        break;
        case 'Wishlist':
          $color = 'cyan';
        break;
        default:
          $color = 'white';
        break;
      }
      $formatted_priority = phutil_console_format(
        "<bg:{$color}> </bg> %s",
        $task['priority']);
      $output['priority'] = array(
        'text'  => $formatted_priority,
        'len'   => phutil_utf8_console_strlen($task['priority']) + 2,
      );

      // Render the "Status" column.
      if ($task['status']) {
        $status_text = 'Closed';
        $status_color = 'red';
      } else {
        $status_text = 'Open';
        $status_color = 'green';
      }
      $formatted_status = phutil_console_format(
        "<bg:{$status_color}> </bg> %s",
        $status_text);
      $output['status'] = array(
        'text'  => $formatted_status,
        'len'   => phutil_utf8_console_strlen('status') + 2,
      );

      $task_rows[] = $output;
    }

    // Find the longest string in each column.
    $col_size = array();
    foreach ($task_rows as $row) {
      foreach ($row as $key => $col) {
        if (empty($col_size[$key])) {
          $col_size[$key] = 0;
        }
        $col_size[$key] = max($col_size[$key], $col['len']);
      }
    }

    // Determine the terminal width. If we can't figure it out, assume 80.
    $width = nonempty(phutil_console_get_terminal_width(), 80);


    // We're going to clip the titles so they'll all fit in one line on the
    // terminal. Figure out where to clip them.
    $padding_between_columns = 4;
    $clip_title_at = max(
      // Always show at least a little bit of text even if it will make the
      // UI wrap, since it's useless if we don't show anything.
      16,
      $width -
        ($col_size['id'] + $col_size['priority'] + $col_size['status'] +
        ($padding_between_columns * 3)));
    $col_size['title'] = min($col_size['title'], $clip_title_at);

    foreach ($task_rows as $key => $cols) {
      $new_title = phutil_utf8_shorten($cols['title']['text'], $clip_title_at);

      $task_rows[$key]['title']['len'] = phutil_utf8_console_strlen($new_title);
      $task_rows[$key]['title']['text'] = $new_title;
    }

    $table = array();
    foreach ($task_rows as $row) {
      $trow = array();
      foreach ($row as $col => $cell) {
        $text = $cell['text'];
        $pad_len = $col_size[$col] - $cell['len'];
        if ($pad_len) {
          $text .= str_repeat(' ', $pad_len);
        }
        $trow[] = $text;
      }
      $table[] = implode(str_repeat(' ', $padding_between_columns), $trow);
    }
    $table = implode("\n", $table)."\n";

    echo $table;
  }

  private function findOwnerPHID($owner) {
    $conduit = $this->getConduit();

    $owner_phid = $conduit->callMethodSynchronous(
      'user.find',
      array(
        'aliases' => array($owner),
      ));

    return idx($owner_phid, $owner);
  }

  private function loadManiphestTasks($status, $owner_phid, $order, $limit) {
    $conduit = $this->getConduit();

    $find_params = array();
    if ($owner_phid !== null) {
      $find_params['ownerPHIDs'] = array($owner_phid);
    }

    if ($limit !== false) {
      $find_params['limit'] = $limit;
    }

    $find_params['order'] = ($order ? "order-".$order : "order-priority");
    $find_params['status'] = ($status ? "status-".$status : "status-open");

    $tasks = $conduit->callMethodSynchronous(
      'maniphest.query',
      $find_params);

    return $tasks;
  }

}
