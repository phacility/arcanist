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
        return pht('Install ESLint using `%s`.', 'npm install -g eslint');
    }

    public function getMandatoryFlags() {
        $options = array();

        $options[] = '--format=stylish';

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
        $lines = phutil_split_lines($stdout, false);

        $messages = array();
        foreach ($lines as $line) {
            // Clean up nasty ESLint output
            $clean_line = $output = preg_replace('!\s+!', ' ', $line);
            $parts = explode(' ', ltrim($clean_line));

            if (isset($parts[1]) && ($parts[1] === 'error' || $parts[1] === 'warning')) {
                $severity = $parts[1] === 'error' ?
                    ArcanistLintSeverity::SEVERITY_ERROR :
                    ArcanistLintSeverity::SEVERITY_WARNING;

                $message = new ArcanistLintMessage();
                $message->setPath($path);
                $message->setLine($parts[0]);
                $message->setCode($this->getLinterName());
                $message->setDescription(implode(' ', $parts));
                $message->setSeverity($severity);

                $messages[] = $message;
            }
        }

        return $messages;
    }

}
