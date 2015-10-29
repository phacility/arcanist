<?php

final class UberTestPlanTestEngine extends ArcanistUnitTestEngine {

    public function run() {
        return $this->checkNonEmptyTestPlan();
    }

    // Checks the git commit log and the arcanist message cache for a test plan
    private function checkNonEmptyTestPlan() {
        $result = new ArcanistUnitTestResult();
        $result->setName("Test Plan");

        $lines = join(' ', $this->getMessage());
        $testPlanExists = preg_match('/\sTest Plan:/', $lines);

        if (!$testPlanExists) {
            $result->setResult(ArcanistUnitTestResult::RESULT_FAIL);
            print 'Test Plan not found!';
            return array($result);
        }

        $testPlanEmpty = preg_match('/\sTest Plan:\s*?Reviewers:/', $lines);
        if ($testPlanEmpty) {
            $result->setResult(ArcanistUnitTestResult::RESULT_FAIL);
            print 'Test Plan cannot be empty!';
            return array($result);
        }

        $result->setResult(ArcanistUnitTestResult::RESULT_PASS);
        print 'Test Plan found!';
        return array($result);
    }

    private function getMessage() {
        $message_file = ".git/arc/create-message";
        exec('git log `git merge-base origin/master HEAD`^..HEAD --oneline', $output, $return_code);
        $commit = count($output) - 1;
        exec("git log -n {$commit}", $output, $return_code);

        if (file_exists($message_file)) {
            $message = file_get_contents(".git/arc/create-message");
            array_push($output, $message);
        }

        return $output;
    }

}

?>
