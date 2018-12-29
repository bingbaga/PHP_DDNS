<?php
/**
 * Created by PhpStorm.
 * User: david
 * Date: 2018/11/25
 * Time: 18:08
 */
class daemon{

    private $info_dir="/tmp";
    private $pid_file="";
    private $terminate=false; //是否中断
    private $workers_count=0;
    private $gc_enabled=null;
    private $workers_max=8; //最多运行8个进程
    private $jobs;

    public function __construct($is_sington=false,$user='nobody',$output="/dev/null"){

        $this->is_sington=$is_sington; //是否单例运行，单例运行会在tmp目录下建立一个唯一的PID
        $this->user=$user;//设置运行的用户 默认情况下nobody
        $this->output=$output; //设置输出的地方
        $this->checkPcntl();
    }
    //检查环境是否支持pcntl支持
    public function checkPcntl(){
        if ( ! function_exists('pcntl_signal_dispatch')) {
            // PHP < 5.3 uses ticks to handle signals instead of pcntl_signal_dispatch
            // call sighandler only every 10 ticks
            declare(ticks = 10);
        }

        // Make sure PHP has support for pcntl
        if ( ! function_exists('pcntl_signal')) {
            $message = 'PHP does not appear to be compiled with the PCNTL extension.  This is neccesary for daemonization';
            $this->_log($message);
            throw new Exception($message);
        }
        //信号处理
        pcntl_signal(SIGTERM, array(__CLASS__, "signalHandler"),false);
        pcntl_signal(SIGINT, array(__CLASS__, "signalHandler"),false);
        pcntl_signal(SIGQUIT, array(__CLASS__, "signalHandler"),false);

        // Enable PHP 5.3 garbage collection
        if (function_exists('gc_enable'))
        {
            gc_enable();
            $this->gc_enabled = gc_enabled();
        }
    }

    // daemon化程序
    public function daemonize(){

        global $stdin, $stdout, $stderr;
        global $argv;

        set_time_limit(0);

        // 只允许在cli下面运行
        if (php_sapi_name() != "cli"){
            die("only run in command line mode\n");
        }

        // 只能单例运行
        if ($this->is_sington==true){

            $this->pid_file = $this->info_dir . "/" .__CLASS__ . "_" . substr(basename($argv[0]), 0, -4) . ".pid";
            $this->checkPidfile();
        }

        umask(0); //把文件掩码清0

        if (pcntl_fork() != 0){ //是父进程，父进程退出
            exit();
        }

        posix_setsid();//设置新会话组长，脱离终端

        if (pcntl_fork() != 0){ //是第一子进程，结束第一子进程
            exit();
        }

        chdir("/"); //改变工作目录

        $this->setUser($this->user) or die("cannot change owner");

        //关闭打开的文件描述符
        fclose(STDIN);
        fclose(STDOUT);
        fclose(STDERR);

        $stdin  = fopen($this->output, 'r');
        $stdout = fopen($this->output, 'a');
        $stderr = fopen($this->output, 'a');

        if ($this->is_sington==true){
            $this->createPidfile();
        }

    }
    //--检测pid是否已经存在
    public function checkPidfile(){

        if (!file_exists($this->pid_file)){
            return true;
        }
        $pid = file_get_contents($this->pid_file);
        $pid = intval($pid);
        if ($pid > 0 && posix_kill($pid, 0)){
            $this->_log("the daemon process is already started");
        }
        else {
            $this->_log("the daemon proces end abnormally, please check pidfile " . $this->pid_file);
        }
        exit(1);

    }
    //----创建pid
    public function createPidfile(){

        if (!is_dir($this->info_dir)){
            mkdir($this->info_dir);
        }
        $fp = fopen($this->pid_file, 'w') or die("cannot create pid file");
        fwrite($fp, posix_getpid());
        fclose($fp);
        $this->_log("create pid file " . $this->pid_file);
    }

    //设置运行的用户
    public function setUser($name){

        $result = false;
        if (empty($name)){
            return true;
        }
        $user = posix_getpwnam($name);
        if ($user) {
            $uid = $user['uid'];
            $gid = $user['gid'];
            $result = posix_setuid($uid);
            posix_setgid($gid);
        }
        return $result;

    }
    //信号处理函数
    public function signalHandler($signo){

        switch($signo){

            //用户自定义信号
            case SIGUSR1: //busy
                if ($this->workers_count < $this->workers_max){
                    $pid = pcntl_fork();
                    if ($pid > 0){
                        $this->workers_count ++;
                    }
                }
                break;
            //子进程结束信号
            case SIGCHLD:
                while(($pid=pcntl_waitpid(-1, $status, WNOHANG)) > 0){
                    $this->workers_count --;
                }
                break;
            //中断进程
            case SIGTERM:
            case SIGHUP:
            case SIGQUIT:

                $this->terminate = true;
                break;
            default:
                return false;
        }

    }
    /**
     *开始开启进程
     *$count 准备开启的进程数
     */
    public function start($count=1){
        $this->_log("daemon process is running now");
        pcntl_signal(SIGCHLD, array(__CLASS__, "signalHandler"),false); // if worker die, minus children num
        while (true) {
            if (function_exists('pcntl_signal_dispatch')){

                pcntl_signal_dispatch();
            }

            if ($this->terminate){
                break;
            }
            $pid=-1;
            if($this->workers_count<$count){

                $pid=pcntl_fork();
            }

            if($pid>0){

                $this->workers_count++;

            }elseif($pid==0){

                // 这个符号表示恢复系统对信号的默认处理
                pcntl_signal(SIGTERM, SIG_DFL);
                pcntl_signal(SIGCHLD, SIG_DFL);
                if(!empty($this->jobs)){
                    while(true){
                        if(!empty($this->jobs['argv'])){
                            call_user_func($this->jobs['function'],$this->jobs['argv']);
                        }else{
                            call_user_func($this->jobs['function']);
                        }
                        sleep(5);
                    }
                    exit();

                }
                return;

            }else{

                sleep(5);
            }


        }

        $this->mainQuit();
        exit(0);

    }

    //整个进程退出
    public function mainQuit(){

        if (file_exists($this->pid_file)){
            unlink($this->pid_file);
            $this->_log("delete pid file " . $this->pid_file);
        }
        $this->_log("daemon process exit now");
        posix_kill(0, SIGKILL);
        exit(0);
    }

    // 添加工作实例，目前只支持单个job工作
    public function setJobs($jobs=array()){


        if(!isset($jobs['argv'])||empty($jobs['argv'])){

            $jobs['argv']="";

        }

        if(!isset($jobs['function'])||empty($jobs['function'])){

            $this->log("你必须添加运行的函数！");
        }

        $this->jobs=$jobs;

    }
    //日志处理
    private  function _log($message){
        file_put_contents("ddns.log", $message, FILE_APPEND);
        //printf("%s\t%d\t%d\t%s\n", date("c"), posix_getpid(), posix_getppid(), $message);
    }

}