<?php

/**
 * Very basic unit test engine which runs tests using a script from
 * configuration and expects an XUnit compatible result.
 *
 * @group unit
 */
class GenericXUnitTestEngine extends ArcanistBaseUnitTestEngine {
    public function run() {
        $results = $this->runTests();

        return $results;
    }

    private function runTests() {
        $root = $this->getWorkingCopy()->getProjectRoot();
        $script = $this->getConfiguredScript();
        $path = $this->getConfiguredTestResultPath();

        foreach (glob($path."/*.xml") as $filename) {
            // Remove existing files so we cannot report old results
            $this->unlink($filename);
        }

        $future = new ExecFuture('%C %s', $script, $path);
        $future->setCWD($root);
        try {
            $future->resolvex();
        } catch(CommandException $exc) {
            if ($exc->getError() != 0) {
                throw $exc;
            }
        }

        return $this->parseTestResults($path);
    }
    
    public function parseTestResults($path) {
        $results = array();
        
        foreach (glob($path."/*.xml") as $filename) {
            $parser = new ArcanistXUnitTestResultParser();
            $results[] = $parser->parseTestResults(
                Filesystem::readFile($filename));
        }
        
        return array_mergev($results);
    }

    private function unlink($filepath) {
        if (file_exists($filepath)) {
            unlink($filepath);
        }
    }

    /**
     * Load, validate, and return the "script" configuration.
     *
     * @return string The shell command fragment to use to run the unit tests.
     *
     * @task config
     */
     private function getConfiguredScript() {
        $key = 'unit.genericxunit.script';
        $config = $this->getConfigurationManager()
         ->getConfigFromAnySource($key);

        if (!$config) {
            throw new ArcanistUsageException(
            "GenericXunitTestEngine: ".
            "You must configure '{$key}' to point to a script to execute.");
        }

        // NOTE: No additional validation since the "script" can be some random
        // shell command and/or include flags, so it does not need to point to some
        // file on disk.

        return $config;
    }
    
    private function getConfiguredTestResultPath() {
        $key = 'unit.genericxunit.result_path';
        $config = $this->getConfigurationManager()
         ->getConfigFromAnySource($key);

        if (!$config) {
            throw new ArcanistUsageException(
            "GenericXunitTestEngine: ".
            "You must configure '{$key}' to point to a path.");
        }

        return $config;
    }
}
