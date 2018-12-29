<?php
/**
 * Class Demon
 */
class dae {
    private $job;
    const PROCESS_NAME = 'ddns';
    const MAXCONN = 2048;
    const PIDNAME = __CLASS__;
    const uid = 80;
    const gid = 80;
    protected $pool = NULL;
    public $pidfile;
    public $stop = false;

    public function __construct() {
        $this->pidfile = __DIR__ . '/' . self::PIDNAME . '.pid';
    }

    private function daemon() {
        if (file_exists($this->pidfile)) {
            echo "The file $this->pidfile exists.\n";
            exit();
        }
        $pid = pcntl_fork();
        if ($pid == -1) {
            die('could not fork');
        } else if ($pid) {
            // we are the parent
            exit($pid);
        } else {
            // we are the child
            file_put_contents($this->pidfile, getmypid());
            posix_setuid(self::uid);
            posix_setgid(self::gid);
            cli_set_process_title(self::PROCESS_NAME);
            pcntl_signal(SIGHUP, [$this, 'signoH']);
            pcntl_signal(SIGTERM, [$this, 'signoH']);
            pcntl_signal(SIGCHLD, [$this, 'signoH']);
            pcntl_signal(SIGQUIT, [$this, 'signoH']);
            pcntl_signal(SIGINT, [$this, 'signoH']);
            pcntl_signal(SIGUSR1, [$this, 'signoH']);
            return (getmypid());
        }
    }

    private function run() {
        do {
            pcntl_signal_dispatch();
            if ($this->stop) {
                break;
            }
            call_user_func_array($this->job['function'],$this->job['param']);
            //echo "I am alive" . mt_rand(0,20) . "...\n";
            sleep(5);
        } while (true);
        echo ("进程退出\n");
    }
    public function restart() {
        $this->stop();
        $this->start();
        print "重启成功！\n";
    }

    public function setJob(array $job){
        $this->job['function']=$job['function'];
        $this->job['param']=$job['param'];
    }

    private function start() {
        $pid = $this->daemon();
        $this->run();
    }

    private function stop() {
        if (file_exists($this->pidfile)) {
            $pid = file_get_contents($this->pidfile);
            posix_kill($pid, SIGKILL);
            unlink($this->pidfile);
        }
    }

    private function help($proc) {
        printf("%s start | stop | restart | stat | help \n", $proc);
    }

    public function main($argv) {
        if (count($argv) < 2) {
            printf("please input help parameter\n");
            exit();
        }
        if ($argv[1] === 'stop') {
            $this->stop();
        } else if ($argv[1] === 'start') {
            $this->start();
        } else if ($argv[1] === 'restart') {
            $this->restart();
        } else if ($argv[1] === 'stat') {
            if (is_file($this->pidfile)) {
                posix_kill(file_get_contents($this->pidfile), SIGHUP);
            } else {
                print "\n_______程序没有启动________\n";
            }
        } else {
            $this->help("command list :");
        }
    }

    public function handle($instance, $channelName, $message) {
        file_put_contents(__DIR__ . "/$channelName.txt", $message . "\n", FILE_APPEND);
    }

    public function signoH($signo) {
        switch ($signo) {
            case SIGHUP :
                print "\n___________运行状态___________\n";
                print "NAME : " . self::PROCESS_NAME . "\n";
                print "PID : " . file_get_contents($this->pidfile) . "\n";
                print "________________________________\n";
                break;
            case SIGTERM:
                posix_kill(file_get_contents($this->pidfile), 9);
                break;
            default :
                print "\n________________________________\n";
                print "呀！～有人想杀掉我！\n";
                print "________________________________\n";
        }
    }
}