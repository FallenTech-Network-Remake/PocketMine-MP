<?php

declare(strict_types=1);

// Execute the production crash handler and loop with isolated I/O dependencies.
$source = file_get_contents(__DIR__ . '/../../src/Server.php');
$method = static function(string $name) use ($source): string {
    preg_match('/\t(?:public|private) function ' . $name . '\([^\n]+\n.*?^\t}/ms', $source, $match);
    return str_replace('private function', 'public function', $match[0]);
};
class KnownTranslationFactory {
    public static function __callStatic(string $name, array $args): string { return $name; }
}
class Utils {
    public static function printableTraceWithMetadata(array $trace): array { return []; }
}
class Filesystem {
    public static function cleanPath(string $path): string { return $path; }
}
class Yml { const AUTO_REPORT_ENABLED = 'report'; }
class CrashDump {
    public function __construct($server, $plugins) {
        if ($server->failDump) throw new RuntimeException('dump failure');
    }
}
class CrashDumpEvent {
    public static array $paths = [];
    public static bool $fail = false;
    public function __construct(private string $path) {}
    public function call(): void {
        if (!is_file($this->path)) throw new RuntimeException('Event fired before write');
        self::$paths[] = $this->path;
        if (self::$fail) throw new RuntimeException('listener failure');
    }
}
class QuietLogger {
    public array $errors = [];
    public function logException(Throwable $e): void { $this->errors[] = $e; }
    public function __call(string $name, array $args): string { return ''; }
}
class Config { public function getPropertyBool($key, $default): bool { return false; } }
class Sleeper {
    public bool $fail = false;
    public function sleepUntil(float $time): void {
        if ($this->fail) { $this->fail = false; throw new RuntimeException('packet failure'); }
    }
}
eval('class CrashHarness {
    public bool $isRunning = true, $failDump = false;
    public float $nextTick = 0, $lastCrashReportTime = 0;
    public string $lastCrashFingerprint = "";
    public int $ticks = 0, $reports = 0;
    public const TARGET_SECONDS_PER_TICK = 0.001;
    public $logger, $language, $configGroup, $tickSleeper;
    public array $files = [];
    public function __construct() {
        $this->logger = $this->language = new QuietLogger();
        $this->configGroup = new Config(); $this->tickSleeper = new Sleeper();
    }
    private function writeCrashDumpFile($dump): string {
        $path = tempnam(sys_get_temp_dir(), "crash-test-");
        file_put_contents($path, "test dump"); $this->files[] = $path; return $path;
    }
    public function exceptionHandler(Throwable $e): void { ++$this->reports; $this->crashDump(); }
    private function tick(): void {
        ++$this->ticks;
        if ($this->ticks === 1) throw new RuntimeException("tick failure");
        if ($this->ticks === 3) $this->isRunning = false;
    }
' . $method('crashDump') . $method('tickProcessor') . str_replace('function exceptionHandler(', 'function reportException(', $method('exceptionHandler')) . '}');
$checks = 0;
$check = static function(bool $ok, string $message) use (&$checks): void {
    ++$checks; if (!$ok) throw new RuntimeException($message);
};
$server = new CrashHarness();
$errorReporting = ini_get('error_reporting');
$memoryLimit = ini_get('memory_limit');
try {
    $server->crashDump();
    $check($server->isRunning, 'Dump must not stop server');
    $check(CrashDumpEvent::$paths === $server->files, 'Exact written file is reported');
    $check(ini_get('error_reporting') === $errorReporting && ini_get('memory_limit') === $memoryLimit, 'INI settings restored');
    CrashDumpEvent::$fail = true;
    $server->crashDump();
    $check(count($server->logger->errors) === 1 && $server->isRunning, 'Reporter failure does not kill server');
    CrashDumpEvent::$fail = false;
    $server->failDump = true;
    $server->crashDump();
    $check(count($server->logger->errors) === 2 && ini_get('error_reporting') === $errorReporting, 'Failed dump restores error handling');
    $server->failDump = false;
    $before = count($server->files);
    $error = new RuntimeException('persistent failure');
    $server->reportException($error);
    $server->reportException($error);
    $check(count($server->files) === $before + 1, 'Repeated exception is reported once per minute');
    $server->lastCrashReportTime -= 61;
    $server->reportException($error);
    $check(count($server->files) === $before + 2, 'Persistent failure is reported again after cooldown');
    $server->tickSleeper->fail = true;
    $server->tickProcessor();
    $check($server->ticks === 3 && $server->reports === 2, 'Tick and packet failures report and allow later ticks');
    $before = count($server->files);
    $server->crashDump();
    $check(count($server->files) === $before, 'Stopped server does not create another dump');
} finally {
    foreach ($server->files as $path) unlink($path);
}
echo "$checks crash recovery checks passed.\n";
