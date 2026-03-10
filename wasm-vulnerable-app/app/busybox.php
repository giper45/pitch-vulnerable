<?php
// busybox.php - A simulated Linux environment for WASI PHP Honeypot

// Set working directory to the app folder
if (!defined('BUSYBOX_WORKDIR_SET')) {
    chdir(__DIR__);
    define('BUSYBOX_WORKDIR_SET', true);
}

/**
 * Parse command line arguments respecting quotes
 */
function parse_command_args($command) {
    $args = [];
    $current = '';
    $in_quote = null;
    $len = strlen($command);
    
    for ($i = 0; $i < $len; $i++) {
        $char = $command[$i];
        
        if ($in_quote) {
            if ($char === $in_quote) {
                $in_quote = null;
            } else {
                $current .= $char;
            }
        } else {
            if ($char === '"' || $char === "'") {
                $in_quote = $char;
            } elseif ($char === ' ') {
                if ($current !== '') {
                    $args[] = $current;
                    $current = '';
                }
            } else {
                $current .= $char;
            }
        }
    }
    
    if ($current !== '') {
        $args[] = $current;
    }
    
    return $args;
}

/**
 * Custom fnmatch implementation for WASM environment
 * Converts shell wildcard pattern to regex and matches
 */
function custom_fnmatch($pattern, $string) {
    // Convert shell pattern to regex
    $regex = preg_quote($pattern, '/');
    $regex = str_replace('\*', '.*', $regex);
    $regex = str_replace('\?', '.', $regex);
    return preg_match('/^' . $regex . '$/i', $string) === 1;
}

/**
 * Analyze command for reverse shell payloads
 * Returns array with detected payload info or false
 */
function analyze_payload($command) {
    $ip_regex = '([0-9]{1,3}(?:\.[0-9]{1,3}){3})';

    // 1. BASH: /dev/tcp/IP/PORT or /dev/udp/IP/PORT
    if (preg_match('/\/dev\/(?:tcp|udp)\/' . $ip_regex . '\/([0-9]+)/', $command, $m)) {
        return ['type' => 'Bash /dev/tcp', 'ip' => $m[1], 'port' => $m[2]];
    }
    
    // 2. SCRIPTING (Python, Perl, PHP, Ruby): ("IP", PORT)
    if (preg_match('/(["\'])' . $ip_regex . '\1[^\d]{1,15}(["\']?)([0-9]{2,5})\3/', $command, $m)) {
        return ['type' => 'Scripting (Py/Perl/Ruby/PHP)', 'ip' => $m[2], 'port' => $m[4]];
    }

    // 3. SOCKET/SOCAT: IP:PORT
    if (preg_match('/(?:tcp:|udp:|["\'\s])' . $ip_regex . ':([0-9]{2,5})(?:["\'\s]|$)/i', $command, $m)) {
        return ['type' => 'Socket/Socat', 'ip' => $m[1], 'port' => $m[2]];
    }

    // 4. NETCAT: nc/ncat IP PORT
    if (preg_match('/(?:nc|ncat)\b.*?(?:\s+)' . $ip_regex . '\s+([0-9]{2,5})\b/i', $command, $m)) {
        return ['type' => 'Netcat', 'ip' => $m[1], 'port' => $m[2]];
    }

    return false;
}

function fake_system($command) {
    $command = trim($command);
    
    // HONEYPOT: Detect reverse shell attempts
    $payload_analysis = analyze_payload($command);
    if ($payload_analysis) {
        // Log the attack
        $log_entry = "[" . date('Y-m-d H:i:s') . "] REVERSE SHELL DETECTED!\n";
        $log_entry .= "Type: " . $payload_analysis['type'] . "\n";
        $log_entry .= "Target IP: " . $payload_analysis['ip'] . "\n";
        $log_entry .= "Target Port: " . $payload_analysis['port'] . "\n";
        $log_entry .= "Command: " . $command . "\n";
        $log_entry .= "---\n";
        @file_put_contents(__DIR__ . '/../fake_logs/attacks.log', $log_entry, FILE_APPEND);
        
        // Set globals for reverse shell handler
        $GLOBALS['rev_ip'] = $payload_analysis['ip'];
        $GLOBALS['rev_port'] = $payload_analysis['port'];
        
        // Execute fake reverse shell
        ob_start();
        include(__DIR__ . '/revshell.php');
        return ob_get_clean();
    }
    
    if (strpos($command, ';') !== false) {
        $commands = explode(';', $command);
        $full_output = "";
        foreach ($commands as $cmd) {
            $full_output .= fake_system($cmd);
        }
        return $full_output;
    }
    if (empty($command)) return "";

    // Parse command with quote support
    $parsed = parse_command_args($command);
    $bin = strtolower($parsed[0]);
    $args = array_slice($parsed, 1);
    $output = "";

    try {
        switch ($bin) {
            case 'ls':
                $target = isset($args[0]) ? $args[0] : '.';
                // Ignore flags like -la for simplicity in this example
                if (in_array('-la', $args) || in_array('-l', $args)) {
                    $target = isset($args[1]) ? $args[1] : '.';
                }
                
                if (is_dir($target)) {
                    $files = scandir($target);
                    foreach ($files as $f) {
                        $is_dir = is_dir("$target/$f") ? 'd' : '-';
                        $size = str_pad(filesize("$target/$f"), 6, " ", STR_PAD_LEFT);
                        $output .= "$is_dir rwxr-xr-x $size $f\n";
                    }
                } else {
                    $output .= "ls: cannot access '$target': No such file or directory\n";
                }
                break;

            case 'cat':
                $target = isset($args[0]) ? $args[0] : '';
                if (is_file($target)) {
                    $output .= file_get_contents($target) . "\n";
                } else {
                    $output .= "cat: $target: No such file or directory\n";
                }
                break;

            case 'whoami':
                $output .= "www-data\n";
                break;

            case 'id':
                $output .= "uid=33(www-data) gid=33(www-data) groups=33(www-data)\n";
                break;

            case 'uname':
                if (in_array('-a', $args)) {
                    $output .= "Linux wasmer-server 5.15.0-101-generic #111-Ubuntu SMP Tue Mar 5 10:22:33 UTC 2024 x86_64 GNU/Linux\n";
                } else {
                    $output .= "Linux\n";
                }
                break;

            case 'echo':
                $output .= implode(' ', $args) . "\n";
                break;


            case 'ping':
                $target = '127.0.0.1';
                $count = 3;
                $port = 80;
                
                // Parse arguments for target and -c (count)
                foreach ($args as $i => $arg) {
                    if ($arg === '-c' && isset($args[$i+1])) {
                        $count = intval($args[$i+1]);
                    } elseif (preg_match('/^[a-zA-Z0-9.-]+$/', $arg) && $arg !== '-c' && !is_numeric($arg)) {
                        $target = $arg;
                    }
                }

                // TCP Ping (since ICMP doesn't work in WASM sandbox)
                $output .= "PING $target ($target) port $port\n";
                
                // Perform TCP pings
                $success = 0;
                $total_time = 0;
                for ($seq = 1; $seq <= $count; $seq++) {
                    $start = microtime(true);
                    $fp = @fsockopen($target, $port, $errno, $errstr, 2);
                    $elapsed = (microtime(true) - $start) * 1000; // Convert to ms
                    
                    if ($fp) {
                        fclose($fp);
                        $success++;
                        $total_time += $elapsed;
                        $output .= "Connected to $target port $port seq=$seq time=" . number_format($elapsed, 1) . "ms\n";
                    } else {
                        $output .= "No response from $target port $port seq=$seq\n";
                    }
                }
                
                // Statistics
                $packet_loss = intval(((($count - $success) / $count) * 100));
                $output .= "\n--- $target ping statistics ---\n";
                $output .= "$count packets transmitted, $success received, {$packet_loss}% packet loss";
                if ($success > 0) {
                    $avg_time = $total_time / $success;
                    $output .= ", time " . intval($total_time) . "ms";
                    $output .= "\nrtt min/avg/max = " . number_format($total_time/$success, 2) . "/" . number_format($avg_time, 2) . "/" . number_format($total_time/$success, 2) . " ms";
                }
                $output .= "\n";
                break;

            case 'wget':
                if (empty($args[0])) {
                    $output .= "wget: missing URL argument\n";
                    break;
                }
                
                $url = $args[0];
                $output_file = null;
                $timeout = 10;
                
                // Parse options
                for ($i = 1; $i < count($args); $i++) {
                    if ($args[$i] === '-O' && isset($args[$i+1])) {
                        $output_file = $args[$i+1];
                        $i++;
                    }
                }
                
                // Validate URL
                if (!filter_var($url, FILTER_VALIDATE_URL)) {
                    $output .= "wget: Invalid URL '$url'\n";
                    break;
                }
                
                $output .= "--" . date('Y-m-d H:i:s') . "--  $url\n";
                
                try {
                    $context = stream_context_create([
                        'http' => [
                            'method' => 'GET',
                            'timeout' => $timeout,
                            'user_agent' => 'Wget/1.21.1'
                        ]
                    ]);
                    
                    $content = @file_get_contents($url, false, $context);
                    
                    if ($content === false) {
                        $output .= "wget: unable to resolve host address\n";
                    } else {
                        $size = strlen($content);
                        if ($output_file) {
                            file_put_contents($output_file, $content);
                            $output .= "Saving to: '$output_file'\n";
                            $output .= "'$url' saved [$size/$size]\n";
                        } else {
                            // Extract filename from URL
                            $filename = basename(parse_url($url, PHP_URL_PATH));
                            if (empty($filename)) $filename = 'index.html';
                            file_put_contents($filename, $content);
                            $output .= "Saving to: '$filename'\n";
                            $output .= "'$url' saved [$size/$size]\n";
                        }
                    }
                } catch (Exception $e) {
                    $output .= "wget: " . $e->getMessage() . "\n";
                }
                break;

            case 'curl':
                if (empty($args[0])) {
                    $output .= "curl: no URL specified!\n";
                    break;
                }
                
                $url = $args[0];
                
                if (!filter_var($url, FILTER_VALIDATE_URL)) {
                    $output .= "curl: (6) Could not resolve host\n";
                    break;
                }
                
                try {
                    $context = stream_context_create([
                        'http' => [
                            'method' => 'GET',
                            'timeout' => 10,
                            'user_agent' => 'curl/7.85.0'
                        ]
                    ]);
                    
                    $content = @file_get_contents($url, false, $context);
                    if ($content !== false) {
                        $output .= $content;
                    } else {
                        $output .= "curl: (6) Could not resolve host\n";
                    }
                } catch (Exception $e) {
                    $output .= "curl: (" . $e->getCode() . ") " . $e->getMessage() . "\n";
                }
                break;

            case 'pwd':
                $output .= getcwd() . "\n";
                break;

            case 'cd':
                if (empty($args[0])) {
                    chdir(getenv('HOME') ?: '/root');
                } else {
                    if (!chdir($args[0])) {
                        $output .= "cd: " . $args[0] . ": No such file or directory\n";
                    }
                }
                break;

            case 'mkdir':
                if (empty($args[0])) {
                    $output .= "mkdir: missing operand\n";
                    break;
                }
                $recursive = in_array('-p', $args);
                $dir = $args[0];
                if (!@mkdir($dir, 0755, $recursive)) {
                    $output .= "mkdir: cannot create directory '$dir': Permission denied\n";
                }
                break;

            case 'rm':
                if (empty($args[0])) {
                    $output .= "rm: missing operand\n";
                    break;
                }
                $force = in_array('-f', $args);
                $recursive = in_array('-r', $args) || in_array('-rf', $args);
                $target = $args[0];
                
                if (is_dir($target) && !$recursive) {
                    $output .= "rm: cannot remove '$target': Is a directory\n";
                } else {
                    if (!@unlink($target)) {
                        if (!$force) {
                            $output .= "rm: cannot remove '$target': No such file or directory\n";
                        }
                    }
                }
                break;

            case 'touch':
                if (empty($args[0])) {
                    $output .= "touch: missing file operand\n";
                    break;
                }
                $file = $args[0];
                if (@touch($file)) {
                    // Success
                } else {
                    $output .= "touch: cannot touch '$file': Permission denied\n";
                }
                break;

            case 'cp':
                if (count($args) < 2) {
                    $output .= "cp: missing file operand\n";
                    break;
                }
                $src = $args[0];
                $dst = $args[1];
                if (!is_file($src)) {
                    $output .= "cp: cannot stat '$src': No such file or directory\n";
                } else {
                    if (!@copy($src, $dst)) {
                        $output .= "cp: cannot copy '$src' to '$dst': Permission denied\n";
                    }
                }
                break;

            case 'mv':
                if (count($args) < 2) {
                    $output .= "mv: missing operand\n";
                    break;
                }
                $src = $args[0];
                $dst = $args[1];
                if (!file_exists($src)) {
                    $output .= "mv: cannot stat '$src': No such file or directory\n";
                } else {
                    if (!@rename($src, $dst)) {
                        $output .= "mv: cannot move '$src' to '$dst': Permission denied\n";
                    }
                }
                break;

            case 'head':
                if (empty($args[0])) {
                    $output .= "head: missing file operand\n";
                    break;
                }
                $file = $args[0];
                $lines = 10;
                if (in_array('-n', $args)) {
                    $idx = array_search('-n', $args);
                    if (isset($args[$idx + 1])) {
                        $lines = intval($args[$idx + 1]);
                    }
                }
                
                if (is_file($file)) {
                    $content = file($file, FILE_IGNORE_NEW_LINES);
                    $output .= implode("\n", array_slice($content, 0, $lines)) . "\n";
                } else {
                    $output .= "head: cannot open '$file' for reading: No such file or directory\n";
                }
                break;

            case 'tail':
                if (empty($args[0])) {
                    $output .= "tail: missing file operand\n";
                    break;
                }
                $file = $args[0];
                $lines = 10;
                if (in_array('-n', $args)) {
                    $idx = array_search('-n', $args);
                    if (isset($args[$idx + 1])) {
                        $lines = intval($args[$idx + 1]);
                    }
                }
                
                if (is_file($file)) {
                    $content = file($file, FILE_IGNORE_NEW_LINES);
                    $output .= implode("\n", array_slice($content, -$lines)) . "\n";
                } else {
                    $output .= "tail: cannot open '$file' for reading: No such file or directory\n";
                }
                break;

            case 'wc':
                if (empty($args[0])) {
                    $output .= "wc: missing file operand\n";
                    break;
                }
                $file = $args[0];
                if (is_file($file)) {
                    $lines = count(file($file)) - 1;
                    $words = str_word_count(file_get_contents($file));
                    $bytes = filesize($file);
                    $output .= "  " . str_pad($lines, 8) . str_pad($words, 8) . str_pad($bytes, 8) . " $file\n";
                } else {
                    $output .= "wc: cannot open '$file': No such file or directory\n";
                }
                break;

            case 'grep':
                if (count($args) < 2) {
                    $output .= "grep: missing operand\n";
                    break;
                }
                $pattern = $args[0];
                $file = $args[1];
                $case_insensitive = in_array('-i', $args);
                
                if (is_file($file)) {
                    $lines = file($file, FILE_IGNORE_NEW_LINES);
                    $regex_pattern = '/' . preg_quote($pattern) . '/';
                    if ($case_insensitive) $regex_pattern .= 'i';
                    
                    foreach ($lines as $line) {
                        if (preg_match($regex_pattern, $line)) {
                            $output .= $line . "\n";
                        }
                    }
                } else {
                    $output .= "grep: cannot open '$file': No such file or directory\n";
                }
                break;

            case 'find':
                if (empty($args[0])) {
                    $output .= "find: missing path operand\n";
                    break;
                }
                $path = $args[0];
                $name_pattern = null;
                
                if (in_array('-name', $args)) {
                    $idx = array_search('-name', $args);
                    if (isset($args[$idx + 1])) {
                        $name_pattern = $args[$idx + 1];
                    }
                }
                
                if (is_dir($path)) {
                    try {
                        $iterator = new RecursiveIteratorIterator(
                            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
                            RecursiveIteratorIterator::CATCH_GET_CHILD
                        );
                        foreach ($iterator as $file) {
                            $filename = $file->getFilename();
                            if ($name_pattern === null || custom_fnmatch($name_pattern, $filename)) {
                                $output .= $file->getPathname() . "\n";
                            }
                        }
                    } catch (Exception $e) {
                        $output .= "find: error reading directory: " . $e->getMessage() . "\n";
                    }
                } else {
                    $output .= "find: '" . $path . "': No such file or directory\n";
                }
                break;

            case 'sort':
                if (empty($args[0])) {
                    $output .= "sort: missing file operand\n";
                    break;
                }
                $file = $args[0];
                if (is_file($file)) {
                    $lines = file($file, FILE_IGNORE_NEW_LINES);
                    sort($lines);
                    $output .= implode("\n", $lines) . "\n";
                } else {
                    $output .= "sort: cannot open '$file': No such file or directory\n";
                }
                break;

            case 'uniq':
                if (empty($args[0])) {
                    $output .= "uniq: missing file operand\n";
                    break;
                }
                $file = $args[0];
                if (is_file($file)) {
                    $lines = file($file, FILE_IGNORE_NEW_LINES);
                    $unique = array_unique($lines);
                    $output .= implode("\n", $unique) . "\n";
                } else {
                    $output .= "uniq: cannot open '$file': No such file or directory\n";
                }
                break;

            case 'date':
                $format = in_array('+%Y-%m-%d', $args) ? 'Y-m-d' : 'D M d H:i:s T Y';
                if (in_array('+%Y-%m-%d %H:%M:%S', $args)) {
                    $format = 'Y-m-d H:i:s';
                }
                $output .= date($format) . "\n";
                break;

            case 'hostname':
                $output .= "wasmer-server\n";
                break;

            case 'df':
                $disk_free = disk_free_space('/');
                $disk_total = disk_total_space('/');
                $used = $disk_total - $disk_free;
                $percent = $disk_total > 0 ? intval(($used / $disk_total) * 100) : 0;
                $output .= "Filesystem     Size  Used Avail Use% Mounted on\n";
                $output .= "/dev/sda1      10G  2.1G  7.9G  " . str_pad($percent . "%", 4) . " /\n";
                break;

            case 'ps':
                $output .= "  PID TTY      STAT   TIME COMMAND\n";
                $output .= "    1 ?        Ss     0:00 /sbin/init\n";
                $output .= "   45 ?        Ss     0:00 /usr/sbin/sshd\n";
                $output .= "  123 ?        S      0:00 /usr/sbin/apache2\n";
                $output .= "  456 ?        S      0:00 php-fpm: pool www\n";
                break;

            case 'uptime':
                $uptime_seconds = time();
                $days = intval($uptime_seconds / 86400);
                $hours = intval(($uptime_seconds % 86400) / 3600);
                $minutes = intval(($uptime_seconds % 3600) / 60);
                $load = "0.15, 0.10, 0.09";
                $output .= " " . date('H:i:s') . " up $days days, $hours:$minutes, 1 user, load average: $load\n";
                break;

            case 'netstat':
                if (in_array('-i', $args)) {
                    $output .= "Kernel Interface table\n";
                    $output .= "Iface   MTU Met   RX-OK RX-ERR RX-DRP RX-OVR    TX-OK TX-ERR TX-DRP TX-OVR Flg\n";
                    $output .= "eth0   1500   0   12345      0      0      0    98765      0      0      0 BMRU\n";
                    $output .= "lo    65536   0       0      0      0      0        0      0      0      0 LRU\n";
                } else {
                    $output .= "Active Internet connections (servers and established)\n";
                    $output .= "Proto Recv-Q Send-Q Local Address           Foreign Address         State\n";
                    $output .= "tcp        0      0 127.0.0.1:3306         0.0.0.0:*               LISTEN\n";
                    $output .= "tcp        0      0 0.0.0.0:80             0.0.0.0:*               LISTEN\n";
                    $output .= "tcp        0      0 0.0.0.0:443            0.0.0.0:*               LISTEN\n";
                }
                break;

            case 'env':
                foreach ($_ENV as $key => $value) {
                    $output .= "$key=$value\n";
                }
                break;

            default:
                $output .= "sh: 1: $bin: not found\n";
                break;
        }
    } catch (Throwable $e) {
        $output .= "$e sh: 1: $bin: segmentation fault\n";
    }

    echo $output;
    return $output;
}

// Create aliases for other native PHP execution functions
function fake_shell_exec($cmd) { 
    ob_start(); 
    fake_system($cmd); 
    return ob_get_clean(); 
}

function fake_exec($cmd, &$output_array=null, &$result_code=null) { 
    $res = fake_shell_exec($cmd); 
    if ($output_array !== null) {
        $output_array = explode("\n", trim($res));
    }
    if ($result_code !== null) {
        $result_code = 0;
    }
    return rtrim($res);
}

function fake_passthru($cmd) {
    return fake_system($cmd);
}
?>