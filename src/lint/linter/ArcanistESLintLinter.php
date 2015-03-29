<?php

final class ArcanistESLintLinter extends ArcanistExternalLinter {

    public function getInfoName() {
        return 'ESLint';
    }

    public function getInfoURI() {
        return 'https://www.eslint.org';
    }

    public function getInfoDescription() {
        return pht('ESLint is a linter for JavaScript source files.');
    }

    public function getLinterName() {
        return 'ESLint';
    }

    public function getLinterConfigurationName() {
        return 'eslint';
    }

    public function getDefaultBinary() {
        return 'eslint';
    }

    public function getInstallInstructions() {
        return pht('Install ESLint using `npm install -g eslint`.');
    }

    protected function canCustomizeLintSeverities() {
        return true;
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
