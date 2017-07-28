<?php

/**
 * This linter invokes checkstyle for verifying code style standards.
 */
class UberCheckstyleLinter2 extends ArcanistFutureLinter {

    private $checkstyleJar = null;
    private $checkstyleConfig = null;

    public function getInfoName() {
        return 'Java checkstyle linter';
    }

    public function getInfoURI() {
        return 'http://checkstyle.sourceforge.net';
    }

    public function getInfoDescription() {
        return 'Use checkstyle to perform static analysis on Java code';
    }

    public function getLinterName() {
        return 'UberCheckstyle';
    }

    public function getLinterConfigurationName() {
        return 'uber-checkstyle-v2';
    }

    public function getLinterConfigurationOptions() {
        $options = array(
            'checkstyle.jar' => array(
                'type' => 'string',
                'help' => pht('Checkstyle jar [from https://sourceforge.net/projects/checkstyle/files/checkstyle/]'),
            ),
            'checkstyle.config' => array(
                'type' => 'string',
                'help' => pht('Checkstyle configuration file]'),
            ),
        );
        return $options + parent::getLinterConfigurationOptions();
    }

    public function setLinterConfigurationValue($key, $value) {
        switch ($key) {
            case 'checkstyle.jar':
                $this->checkstyleJar = $value;
                return;
            case 'checkstyle.config':
                $this->checkstyleConfig = $value;
                return;
        }
        return parent::setLinterConfigurationValue($key, $value);
    }

    /**
     * Check that the checkstyle libraries are installed.
     * @return void
     */
    final public function checkConfiguration() {

        if (!Filesystem::binaryExists("java")) {
            throw new ArcanistMissingLinterException(
                pht('Java is not installed', get_class($this)));
        }
        if (!Filesystem::pathExists($this->checkstyleJar)) {
            throw new ArcanistMissingLinterException(
                sprintf(
                    "%s\n%s",
                    pht(
                        'Unable to locate jar "%s" to run linter %s. You may need '.
                        'to install the script, or adjust your linter configuration.',
                        $this->checkstyleJar,
                        get_class($this)),
                    pht('
                        TO INSTALL: 
                           1) Download checkstyle jar from https://sourceforge.net/projects/checkstyle/files/checkstyle/
                           2) Set `checkstyle.jar` in `.arclint` to the location of the jar.'
                        )));
        }
    }

    protected function didResolveLinterFutures(array $futures) {
        return;
    }


    final protected function buildFutures(array $paths) {
        $this->checkConfiguration();
        $futures = array();
        // Call checkstyle in batches of 500
        $chunks = array_chunk($paths, 500);

        foreach ($chunks as $chunk) {
            $command = sprintf('java -jar %s -f xml -c %s %s',
                $this->checkstyleJar,
                $this->checkstyleConfig,
                implode(' ', $chunk));

            $future = new ExecFuture($command);
            $future->setCWD($this->getProjectRoot());

            foreach ($chunk as $path) {
                $futures[$path] = $future;
            }
        }

        return $futures;
    }

    final protected function resolveFuture($path, Future $future) {
        list($err, $stdout, $stderr) = $future->resolve();

        $messages = $this->parseLinterOutput($path, $err, $stdout, $stderr);

        foreach ($messages as $message) {
            $this->addLintMessage($message);
        }
    }

    protected function parseLinterOutput($path, $err, $stdout, $stderr) {
        // Strip out last line of Checkstyle XML output [see: https://github.com/checkstyle/checkstyle/issues/1018]
        $stdout = substr($stdout, 0, strrpos($stdout, "Checkstyle ends"));
        $dom = new DOMDocument();
        @$dom->loadXML($stdout);
        $files = $dom->getElementsByTagName('file');

        $messages = array();

        foreach ($files as $file) {
            $errors = $file->getElementsByTagName('error');
            $name = $file->getAttribute('name');
            $arcPath = ltrim(str_replace(getcwd(), '', $name), '/');

            if ($arcPath != $path ) {
                continue;
            }

            $changedLines = $this->getEngine()->getPathChangedLines($path);
            if ($changedLines) {
                $changedLines = array_keys($changedLines);
            } else {
                $changedLines = array();
            }

            foreach ($errors as $error) {

                $source = idx(array_slice(explode('.', $error->getAttribute('source')), -1), 0);
                $line = $error->getAttribute('line');

                // Do not fail for errors outside changed lines
                $errorTypes = array(
                    'JavadocTypeCheck',
                    'JavadocMethodCheck',
                    'RegexpSinglelineCheck'
                );
                if (in_array($source, $errorTypes) && !in_array($line, $changedLines)) {
                    continue;
                }

                $message = id(new ArcanistLintMessage())
                    ->setPath($path)
                    ->setLine($line)
                    ->setCode($this->getLinterName())
                    ->setName($source);

                // checkstyle's XMLLogger escapes these five characters
                $description = $error->getAttribute('message');
                $description = str_replace(
                    ['&lt;', '&gt;', '&apos;', '&quot;', '&amp;'],
                    ['<', '>', '\'', '"', '&'],
                    $description);
                $message->setDescription($description);

                $column = $error->getAttribute('column');
                if ($column) {
                    $message->setChar($column);
                }

                $severity = $error->getAttribute('severity');
                switch ($severity) {
                    case 'error':
                        $message->setSeverity(ArcanistLintSeverity::SEVERITY_ERROR);
                        break;
                    case 'info':
                        $message->setSeverity(ArcanistLintSeverity::SEVERITY_ADVICE);
                        break;
                    case 'ignore':
                        $message->setSeverity(ArcanistLintSeverity::SEVERITY_DISABLED);
                        break;
                    case 'warning':
                    default:
                        $message->setSeverity(ArcanistLintSeverity::SEVERITY_WARNING);
                        break;
                }

                $this->addAutofixes($message, $source, $line, $path);
                $messages[] = $message;
            }
        }
        return $messages;
    }

    private function addAutofixes($message, $source, $line, $path) {
        // We only autofix these specific classes of problems
        if (!in_array($source, ["UnusedImportsCheck", "RegexpMultilineCheck"])) {
            return;
        }

        $file = new SplFileObject($path);
        $origLines = '';
        if ($source == "UnusedImportsCheck") {
            $file->seek($line-1); // arcanist line number starts with 1, but file starts with 0
            $origLines = $file->current();
        } else if ($source == "RegexpMultilineCheck") {
            // checkstyle reports line before blank line
            // let's set message to point at the first blank line
            $message->setLine($line+1);
            // seek to line *after* first blank line
            $curLine = $line+1;
            $file->seek($curLine);
            // collect all the blank lines we want to delete
            while ($file->current() == "\n") {
                $origLines = $origLines . "\n";
                $curLine = $curLine + 1;
                $file->seek($curLine);
            }
        }
        $message->setChar(null);
        $message->setOriginalText($origLines);
        $message->setReplacementText('');
    }
}
