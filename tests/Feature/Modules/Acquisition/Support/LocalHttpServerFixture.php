<?php

namespace Tests\Feature\Modules\Acquisition\Support;

/**
 * Isolated local HTTP server for acquisition transport integration tests.
 * Binds only to 127.0.0.1 and never contacts the public internet.
 */
final class LocalHttpServerFixture
{
    /** @var resource|null */
    private $process = null;

    /** @var array<int, resource> */
    private array $pipes = [];

    private string $routerPath = '';

    private string $statePath = '';

    private string $logPath = '';

    private string $directory = '';

    private int $port = 0;

    private int $pid = 0;

    /**
     * @param  array<string, array{status?: int, headers?: array<string, string>, body?: string, close_after_headers?: bool}>  $routes
     */
    public static function start(array $routes): self
    {
        $server = new self;
        $server->boot($routes);

        return $server;
    }

    public function port(): int
    {
        return $this->port;
    }

    public function baseUrl(): string
    {
        return 'http://127.0.0.1:'.$this->port;
    }

    /** @return array<int, array<string, mixed>> */
    public function receivedRequests(): array
    {
        if (! is_file($this->logPath)) {
            return [];
        }

        $contents = @file_get_contents($this->logPath);

        if (! is_string($contents) || trim($contents) === '') {
            return [];
        }

        $requests = [];

        foreach (preg_split("/\n/", trim($contents)) ?: [] as $line) {
            if ($line === '') {
                continue;
            }

            $decoded = json_decode($line, true);

            if (is_array($decoded)) {
                $requests[] = $decoded;
            }
        }

        return $requests;
    }

    public function stop(): void
    {
        if (is_resource($this->process)) {
            foreach ($this->pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            $this->pipes = [];

            $status = @proc_get_status($this->process);
            $pid = is_array($status) ? (int) ($status['pid'] ?? 0) : $this->pid;

            if ($pid > 0) {
                if (function_exists('posix_kill')) {
                    @posix_kill($pid, SIGTERM);
                    usleep(50_000);

                    if (@posix_kill($pid, 0)) {
                        @posix_kill($pid, SIGKILL);
                    }
                } else {
                    @proc_terminate($this->process, SIGTERM);
                    usleep(50_000);
                    @proc_terminate($this->process, SIGKILL);
                }
            } else {
                @proc_terminate($this->process, SIGTERM);
            }

            $deadline = microtime(true) + 2.0;

            while (microtime(true) < $deadline) {
                $status = @proc_get_status($this->process);

                if (! is_array($status) || ($status['running'] ?? false) !== true) {
                    break;
                }

                usleep(20_000);
            }

            @proc_close($this->process);
            $this->process = null;
            $this->pid = 0;
        }

        foreach ([$this->routerPath, $this->statePath, $this->logPath] as $path) {
            if ($path !== '' && is_file($path)) {
                @unlink($path);
            }
        }

        if ($this->directory !== '' && is_dir($this->directory)) {
            foreach (['stdout.log', 'stderr.log'] as $log) {
                $path = $this->directory.'/'.$log;

                if (is_file($path)) {
                    @unlink($path);
                }
            }
            @rmdir($this->directory);
        }

        $this->directory = '';
        $this->routerPath = '';
        $this->statePath = '';
        $this->logPath = '';
    }

    public function __destruct()
    {
        $this->stop();
    }

    /**
     * @param  array<string, array{status?: int, headers?: array<string, string>, body?: string, close_after_headers?: bool}>  $routes
     */
    private function boot(array $routes): void
    {
        $this->port = $this->allocatePort();
        $this->directory = sys_get_temp_dir().'/cip-acq-http-'.bin2hex(random_bytes(8));

        if (! mkdir($this->directory, 0700) && ! is_dir($this->directory)) {
            throw new \RuntimeException('local_http_server_temp_dir_failed');
        }

        $this->statePath = $this->directory.'/routes.json';
        $this->logPath = $this->directory.'/requests.jsonl';
        $this->routerPath = $this->directory.'/router.php';
        $encodedRoutes = [];

        foreach ($routes as $path => $route) {
            $encoded = $route;
            $body = (string) ($route['body'] ?? '');

            if ($body !== '' && ! mb_check_encoding($body, 'UTF-8')) {
                $encoded['body_base64'] = base64_encode($body);
                unset($encoded['body']);
            }

            $encodedRoutes[$path] = $encoded;
        }

        file_put_contents($this->statePath, json_encode($encodedRoutes, JSON_THROW_ON_ERROR));
        file_put_contents($this->logPath, '');
        file_put_contents($this->routerPath, $this->routerSource($this->statePath, $this->logPath));

        // Array form avoids `sh -c` so the tracked PID is the PHP server itself.
        $command = [
            PHP_BINARY,
            '-n',
            '-d',
            'display_errors=0',
            '-d',
            'log_errors=0',
            '-S',
            '127.0.0.1:'.$this->port,
            $this->routerPath,
        ];
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', $this->directory.'/stdout.log', 'w'],
            2 => ['file', $this->directory.'/stderr.log', 'w'],
        ];
        $process = proc_open($command, $descriptors, $this->pipes, $this->directory);

        if (! is_resource($process)) {
            throw new \RuntimeException('local_http_server_start_failed');
        }

        $this->process = $process;
        $status = proc_get_status($process);
        $this->pid = is_array($status) ? (int) ($status['pid'] ?? 0) : 0;
        register_shutdown_function(function (): void {
            $this->stop();
        });
        $this->waitUntilReady();
    }

    private function allocatePort(): int
    {
        $socket = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);

        if ($socket === false) {
            throw new \RuntimeException('local_http_server_port_alloc_failed: '.$errstr);
        }

        $name = stream_socket_get_name($socket, false);
        fclose($socket);

        if (! is_string($name) || ! str_contains($name, ':')) {
            throw new \RuntimeException('local_http_server_port_parse_failed');
        }

        return (int) substr($name, strrpos($name, ':') + 1);
    }

    private function waitUntilReady(): void
    {
        $deadline = microtime(true) + 5.0;

        while (microtime(true) < $deadline) {
            $status = is_resource($this->process) ? proc_get_status($this->process) : false;

            if (! is_array($status) || ($status['running'] ?? false) !== true) {
                throw new \RuntimeException('local_http_server_exited_early');
            }

            $errno = 0;
            $errstr = '';
            $connection = @stream_socket_client(
                'tcp://127.0.0.1:'.$this->port,
                $errno,
                $errstr,
                0.2,
            );

            if (is_resource($connection)) {
                fclose($connection);

                return;
            }

            usleep(50_000);
        }

        throw new \RuntimeException('local_http_server_not_ready');
    }

    private function routerSource(string $statePath, string $logPath): string
    {
        $stateExport = var_export($statePath, true);
        $logExport = var_export($logPath, true);

        return <<<PHP
<?php
\$statePath = {$stateExport};
\$logPath = {$logExport};
\$routes = json_decode((string) file_get_contents(\$statePath), true);
\$path = parse_url(\$_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
\$headers = function_exists('getallheaders') ? getallheaders() : [];
\$record = json_encode([
    'method' => \$_SERVER['REQUEST_METHOD'] ?? 'GET',
    'path' => \$path,
    'host' => \$_SERVER['HTTP_HOST'] ?? '',
    'headers' => \$headers,
    'remote_addr' => \$_SERVER['REMOTE_ADDR'] ?? '',
], JSON_UNESCAPED_SLASHES);
@file_put_contents(\$logPath, \$record.PHP_EOL, FILE_APPEND);
\$route = is_array(\$routes) && isset(\$routes[\$path]) && is_array(\$routes[\$path])
    ? \$routes[\$path]
    : ['status' => 404, 'headers' => ['Content-Type' => 'text/plain'], 'body' => 'not_found'];
\$status = (int) (\$route['status'] ?? 200);
\$responseHeaders = is_array(\$route['headers'] ?? null) ? \$route['headers'] : [];
if (isset(\$route['body_base64']) && is_string(\$route['body_base64'])) {
    \$body = base64_decode(\$route['body_base64'], true);
    \$body = \$body === false ? '' : \$body;
} else {
    \$body = (string) (\$route['body'] ?? '');
}
http_response_code(\$status);
foreach (\$responseHeaders as \$name => \$value) {
    header(\$name.': '.\$value);
}
if (!empty(\$route['close_after_headers'])) {
    flush();
    exit;
}
echo \$body;
PHP;
    }
}
