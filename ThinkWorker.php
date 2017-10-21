<?php
/**
 * This file is part of workerman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace Workerman;

require_once __DIR__.'/Lib/Constants.php';

/**
 * ThinkWorker class
 * A container for listening ports
 */
class ThinkWorker extends Worker
{

    /**
     * Parse command.
     * php index.php ControllerName/MethodName start [-d] | stop | restart | reload | status
     *
     * @return void
     */
    protected static function parseCommand()
    {
        global $argv;
        // Check argv;
        $start_file = $argv[0];
        $available_commands = [
            'start',
            'stop',
            'restart',
            'reload',
            'status',
            'connections',
        ];
        if (!isset($argv[2]) || !in_array($argv[2], $available_commands)) {
            exit("Usage: php yourfile.php {".implode('|', $available_commands)."}\n");
        }

        // Get command.
        $command = trim($argv[2]);
        $command2 = isset($argv[3]) ? $argv[3] : '';

        // Start command.
        $mode = '';
        if ($command === 'start') {
            if ($command2 === '-d' || Worker::$daemonize) {
                $mode = 'in DAEMON mode';
            } else {
                $mode = 'in DEBUG mode';
            }
        }
        self::log("Workerman[$start_file] $command $mode");

        // Get master process PID.
        $master_pid = is_file(self::$pidFile) ? file_get_contents(self::$pidFile) : 0;
        $master_is_alive = $master_pid && @posix_kill($master_pid, 0) && posix_getpid() != $master_pid;
        // Master is still alive?
        if ($master_is_alive) {
            if ($command === 'start') {
                self::log("Workerman[$start_file] already running");
                exit;
            }
        } elseif ($command !== 'start' && $command !== 'restart') {
            self::log("Workerman[$start_file] not run");
            exit;
        }

        // execute command.
        switch ($command) {
            case 'start':
                if ($command2 === '-d') {
                    Worker::$daemonize = true;
                }
                break;
            case 'status':
                while (1) {
                    if (is_file(self::$_statisticsFile)) {
                        @unlink(self::$_statisticsFile);
                    }
                    // Master process will send SIGUSR2 signal to all child processes.
                    posix_kill($master_pid, SIGUSR2);
                    // Sleep 1 second.
                    sleep(1);
                    // Clear terminal.
                    echo chr(27).chr(91).chr(72).chr(27).chr(91).chr(50).chr(74);
                    // Echo status data.
                    echo self::formatStatusData();
                }
                exit(0);
            case 'connections':
                if (is_file(self::$_statisticsFile)) {
                    @unlink(self::$_statisticsFile);
                }
                // Master process will send SIGIO signal to all child processes.
                posix_kill($master_pid, SIGIO);
                // Waiting amoment.
                usleep(500000);
                // Display statisitcs data from a disk file.
                @readfile(self::$_statisticsFile);
                exit(0);
            case 'restart':
            case 'stop':
                self::log("Workerman[$start_file] is stoping ...");
                // Send stop signal to master process.
                $master_pid && posix_kill($master_pid, SIGINT);
                // Timeout.
                $timeout = 5;
                $start_time = time();
                // Check master process is still alive?
                while (1) {
                    $master_is_alive = $master_pid && posix_kill($master_pid, 0);
                    if ($master_is_alive) {
                        // Timeout?
                        if (time() - $start_time >= $timeout) {
                            self::log("Workerman[$start_file] stop fail");
                            exit;
                        }
                        // Waiting amoment.
                        usleep(10000);
                        continue;
                    }
                    // Stop success.
                    self::log("Workerman[$start_file] stop success");
                    if ($command === 'stop') {
                        exit(0);
                    }
                    if ($command2 === '-d') {
                        Worker::$daemonize = true;
                    }
                    break;
                }
                break;
            case 'reload':
                posix_kill($master_pid, SIGUSR1);
                self::log("Workerman[$start_file] reload");
                exit;
            default :
                exit("Usage: php yourfile.php {".implode('|', $available_commands)."}\n");
        }
    }

}
