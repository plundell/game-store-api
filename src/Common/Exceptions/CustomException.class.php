<?php

declare(strict_types=1);

namespace App\Common\Exceptions;

use function PHPUnit\Framework\isNull;

/**
 * An extension of the standard \Exception class which provides
 * features like keeping track of rethrowing and handling
 */
class CustomException extends \Exception
{
    protected $rethrowLocations = [];
    protected ?\Throwable $causedBy = null;
    protected $extra = [];
    protected $codeString = '';


    /**
     * If the first argument is an instance of \Throwable, it will be wrapped,
     * and the message, trace, and code of that exception will be used.
     * If it's a string, it will be used as the message for a new exception.
     *
     * Any additional arguments will be passed to {@see App\Common\Exceptions\CustomException::addExtra()}
     *
     * @param string|\Throwable $message
     * @param mixed ...$extra
     */
    public function __construct($message = "", ...$extra)
    {
        if ($message instanceof \Throwable) {
            //Use their message, then steal their trace as well
            parent::__construct($message->getMessage());
            if ($message->getCode()) { //only set if it's actually set
                $this->setCode($message->getCode());
            }
            $this->setLockedProp('trace', $message->getTrace());
        } else {
            parent::__construct($message);
            //TODO: how do we handle 
        }
        foreach ($extra as $x) {
            $this->addExtra($x);
        }
    }

    public static function gettype(&$var): string
    {
        if (is_null($var)) return 'NULL';
        if (is_nan($var)) return 'NaN';
        ob_start();
        var_dump($var);
        $str = ob_get_clean() ?: '';
        $rm = __FILE__ . ":" . (string)(__LINE__ - 2) . ":\n";
        $str = str_replace($rm, '', $str);
        return is_string($str) ? $str : 'unknown';
    }

    public static function wrongtype(string $expected,  &$got): string
    {
        return "Expected $expected, got:\n" . self::gettype($got);
    }



    public function setCode(mixed $code)
    {
        if ($this->codeString) {
            error_log("Code already set ({$this->codeString}), cannot change to: $code");
            return;
        }
        if (is_string($code)) {
            if (is_numeric($code)) {
                $int = (int) $code;
            } else {
                $int = -1;
            }
        } else if (is_int($code)) {
            $int = $code;
            $code = (string) $code;
        } else {
            error_log("Code cannot be set to: " . (string)$code);
            return;
        }
        $this->codeString = $code;
        $this->setLockedProp('code', $int);
    }

    public function addExtra($x)
    {
        if ($x instanceof \Throwable && !$this->getPrevious()) {
            $this->setPreviousException($x);
        } else {
            $this->extra[] = $x;
            var_dump($this->extra);
        }
    }

    private function setLockedProp($prop, $value)
    {
        $reflection = new \ReflectionClass($this);
        $p = $reflection->getProperty($prop);
        $p->setAccessible(true);
        $p->setValue($this, $value);
        $p->setAccessible(false);
    }
    private function getLockedProp($prop): mixed
    {
        $reflection = new \ReflectionClass($this);
        $p = $reflection->getProperty($prop);
        $p->setAccessible(true);
        $value = $p->getValue($this);
        $p->setAccessible(false);
        return $value;
    }


    public function setPreviousException(\Throwable $prev)
    {
        if (!($prev instanceof \Throwable)) {
            error_log("Previous exception must be an instance of Throwable");
        } elseif ($this->getPrevious()) {
            error_log("Previous exception already set");
        } else {
            $this->setLockedProp('previous', $prev);
        }
    }


    protected function addRethrowLocation()
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        foreach ($trace as $entry) {
            if (!isset($entry['class']) || strpos($entry['class'], __CLASS__) === false) {
                $this->rethrowLocations[] = $entry['file'] . ':' . $entry['line'];
                break;
            }
        }
    }

    public function getCombinedTraceAsString(): string
    {
        $trace = "\nStack trace:\n";
        $n = 0;
        $indent = function () use (&$n) {
            $n++;
            return str_repeat("  ", $n);
        };
        for ($i = count($this->rethrowLocations) - 1; $i >= 0; $i--) {
            $trace .= $indent() . "Rethrown at: " . $this->rethrowLocations[$i] . "\n";
            $indent++;
        }

        $stack = explode("\n", parent::getTraceAsString()) ?: [];
        $prev = parent::getPrevious();
        $prevStack = $prev ? (explode("\n", $prev->getTraceAsString()) ?: []) : [];
        //strip the end of ours if it appears in theirs
        $stack = array_diff($stack, $prevStack);

        //Add our stack to trace
        $trace .= implode("\n" . $indent(), $stack) . "\n";
        $indent++;

        //Add possible prev stack
        if ($prev) {
            $trace .= implode("\n" . $indent(), $prevStack) . "\n";
            $indent++;
        }
        return $trace;
    }
    public function getExtendedMessage(): string
    {
        //Start with the message
        $str = parent::getMessage();
        //Add any extra info
        foreach ($this->extra as $x) {
            if (!is_string($x)) {
                $x = self::gettype($x);
            }
            $str .= "\n" . $x;
        }

        //If it was caused by a previous error, add that too. 
        if ($this->causedBy) {
            $str .= "\n" . str_replace("\n", "\n\t", $this->causedBy->getMessage());
        }
        return $str;
    }
    public function __toString()
    {
        //Start with the extended
        $str = $this->getExtendedMessage();

        //Add the combined stacktrace which includes rethrown locations and 
        //the stack trace of the previous exception
        $str .= "\n" . $this->getCombinedTraceAsString();

        return $str;
    }
}
