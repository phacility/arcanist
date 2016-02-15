<?php

final class UberRevertPlanTestEngine extends ArcanistUnitTestEngine {

    public function run() {
        return $this->checkNonEmptyRevertPlan();
    }

    // Checks the git commit log and the arcanist
    // message cache for a revert plan.
    private function checkNonEmptyRevertPlan() {
        $result = new ArcanistUnitTestResult();

        $lines = implode(' ', $this->getMessage());
        $revert_plan_exists = preg_match('/\sRevert Plan:/', $lines);

        if (!$revert_plan_exists) {
            $result->setResult(ArcanistUnitTestResult::RESULT_FAIL);
            $result->setName('Revert Plan not found!
            (See http://t.uber.com/revert for more info)');
            return array($result);
        }

        $revert_plan_empty = preg_match('/\sRevert Plan:\s*?$/', $lines);
        if ($revert_plan_empty) {
            $result->setResult(ArcanistUnitTestResult::RESULT_FAIL);
            $result->setName('Revert Plan cannot be empty!
            (See http://t.uber.com/revert for more info)');
            return array($result);
        }

        $result->setResult(ArcanistUnitTestResult::RESULT_PASS);
        $result->setName('Revert Plan found!');
        return array($result);
    }

    private function getMessage() {
        $message_file = '.git/arc/create-message';

        $output = [];
        $return_code = 0;

        exec('git log `git merge-base origin/master HEAD`^..HEAD --oneline',
            $output, $return_code);

        $commit = count($output) - 1;
        exec("git log -n {$commit}", $output, $return_code);

        if (file_exists($message_file)) {
            $message = file_get_contents('.git/arc/create-message');
            array_push($output, $message);
        }

        return $output;
    }
}

?>
