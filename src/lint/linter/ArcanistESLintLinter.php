<?php

final class ArcanistESLintLinter extends ArcanistExternalLinter {

    private $eslintenv;
    private $eslintconfig;
    private $eslintignore;

    public function getInfoName() {
        return 'ESLint';
    }

    public function getInfoURI() {
        return 'https://www.eslint.org';
    }

    public function getRuleDocumentationURI($ruleId) {
        return $this->getInfoURI().'/docs/rules/'.$ruleId;
    }

    public function getInfoDescription() {
        return pht('ESLint is a linter for JavaScript source files.');
    }

    public function getVersion() {
        $output = exec('eslint --version');

        if (strpos($output, 'command not found') !== false) {
            return false;
        }

        return $output;
    }

    public function getLinterName() {
        return 'ESLINT';
    }

    public function getLinterConfigurationName() {
        return 'eslint';
    }

    public function getDefaultBinary() {
        return 'eslint';
    }

    public function getInstallInstructions() {
        return pht('Install ESLint using `%s`.', 'npm install -g eslint eslint-plugin-react');
    }

    public function getMandatoryFlags() {
        $options = array();

        $options[] = '--format='.dirname(realpath(__FILE__)).'/eslintJsonFormat.js';

        if ($this->eslintenv) {
            $options[] = '--env='.$this->eslintenv;
        }

        if ($this->eslintconfig) {
            $options[] = '--config='.$this->eslintconfig;
        }

        if ($this->eslintignore) {
            $options[] = '--ignore-path='.$this->eslintignore;
        }

        return $options;
    }

    public function getLinterConfigurationOptions() {
        $options = array(
            'eslint.eslintenv' => array(
                'type' => 'optional string',
                'help' => pht('enables specific environments.'),
            ),
            'eslint.eslintconfig' => array(
                'type' => 'optional string',
                'help' => pht('config file to use. the default is .eslint.'),
            ),
            'eslint.eslintignore' => array(
                'type' => 'optional string',
                'help' => pht('ignre file to use. the default is .eslintignore.'),
            ),
        );
        return $options + parent::getLinterConfigurationOptions();
    }

    public function getLintSeverityMap() {
        return array(
            2 => ArcanistLintSeverity::SEVERITY_ERROR,
            1 => ArcanistLintSeverity::SEVERITY_WARNING
        );
    }

    public function setLinterConfigurationValue($key, $value) {

        switch ($key) {
            case 'eslint.eslintenv':
                $this->eslintenv = $value;
                return;
            case 'eslint.eslintconfig':
                $this->eslintconfig = $value;
                return;
            case 'eslint.eslintignore':
                $this->eslintignore = $value;
                return;
        }

        return parent::setLinterConfigurationValue($key, $value);
    }

    protected function parseLinterOutput($path, $err, $stdout, $stderr) {
        try {
            $json = phutil_json_decode($stdout);
            // Since arc only lints one file at at time, we only need the first result
            $results = idx(idx($json, 'results')[0], 'messages');
        } catch (PhutilJSONParserException $ex) {
            // Something went wrong and we can't decode the output. Exit abnormally.
            throw new PhutilProxyException(
                pht('ESLint returned unparseable output.'),
                $ex);
        }
        $messages = array();
        foreach ($results as $result) {
            $ruleId = idx($result, 'ruleId');
            $description = idx($result, 'message')."\r\nSee documentation at ".$this->getRuleDocumentationURI($ruleId);
            $message = new ArcanistLintMessage();
            $message->setChar(idx($result, 'column'));
            $message->setCode($ruleId);
            $message->setDescription($description);
            $message->setLine(idx($result, 'line'));
            $message->setName('ESLint.'.$ruleId);
            $message->setOriginalText(ltrim(idx($result, 'source')));
            $message->setPath($path);
            $message->setSeverity($this->getLintMessageSeverity(idx($result, 'severity')));

            $messages[] = $message;
        }

        return $messages;
    }

}
