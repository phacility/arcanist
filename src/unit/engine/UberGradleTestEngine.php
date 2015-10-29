<?php

final class UberGradleTestEngine extends ArcanistUnitTestEngine {

    public function run() {
        $config = $this->getConfigurationManager();
        $tasks = implode(' ', $config->getConfigFromAnySource('unit.engine.gradle.tasks'));
        return $this->runCommand("./gradlew $tasks");
    }

    private function runCommand($command) {
        exec($command, $output, $return_code);

        $result = new ArcanistUnitTestResult();
        $result->setName($command);
        $result->setResult($return_code == 0 ? ArcanistUnitTestResult::RESULT_PASS : ArcanistUnitTestResult::RESULT_FAIL);

        return array($result);
    }

}

?>
