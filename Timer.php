<?php
namespace time;

use function \pcntl_signal;
use function \pcntl_alarm;
use function \function_exists;
use function \is_callable;
/**
 * 基于pcntl时钟实现的定时器，请保证程序已安装pcntl扩展
 * 程序没有经过严格测试，请不要用于开发环境，主要借鉴workerman定时器
 * @see https://github.com/walkor/Workerman/blob/master/Timer.php
 */
class Timer extends \SplMinHeap
{
    /**
     * @var integer $timer_id 定时器id，每个id在任务列表中是唯一的
     */
    protected static $timer_id = 0;

    /**
     * @var array $tasks 任务列表
     */
    protected static $tasks = [];

    /**
     * @var object $self 当前类实例
     */
    protected static $self = NULL;

    /**
     * @var boolean $is_monitor 是否在允行调用通过pcntl_signal安装的处理器
     */
    protected static $is_monitor = false;

    public static function run()
    {
        if(!function_exists('pcntl_signal'))
        {
            throw new \RuntimeException('请安装pcntl扩展！');
        }
        pcntl_alarm(1);
        pcntl_signal(\SIGALRM, ['\time\Timer','signal_handle'],false);
    }

    /**
     * 时钟信号处理
     * @access public
     * @throws \Exception
     * @return void
     */
    public static function signal_handle()
    {
        self::exec();
        pcntl_alarm(1);
    }

    /**
     * 调用通过pcntl_signal安装的处理器
     * @access public
     * @return void
     */
    public static function monitor()
    {
        self::$is_monitor = true;
        while(true)
        {
            sleep(1); //由于pcntl_signal底层调用的ticks，这里为了性能，延时一段时间因为sleep也属于系统调用
            //故当信号来时不会阻塞
            pcntl_signal_dispatch();
        }
    }

    /**
     * 添加一个定时任务
     * @access protected
     * @param integer|float $delay 延时到多少秒后执行
     * @param callable $call 回调函数
     * @param mixed $argv 回调函数
     * @throw \RuntimeException
     * @return object
     */
    public static function add($delay, $call, array $argv = [])
    {
        if($delay <= 0)
        {
            throw new \RuntimeException('delay参数必须为一个大于0的数值');
        }

        if(!is_callable($call))
        {
            throw new \RuntimeException('call参数需要是一个可回调的方法');
        }

        $run_time = \time() + $delay;
        !isset(self::$tasks[$run_time]) && self::$tasks[$run_time] = [];

        self::$timer_id = ++self::$timer_id;
        self::$tasks[$run_time][self::$timer_id] = [$call, $argv, $delay];

        self::$self === NULL && self::$self = new self();
        return self::$self;
    }

    /**
     * 执行定时任务
     * @access protected
     * @throws \Exception
     * @return void
     */
    protected static function exec()
    {
        $current_time = \time();
        foreach(self::$tasks as $run_time => $task_argv)
        {
            if($current_time >= $run_time)
            {
                foreach($task_argv as $key => $value)
                {
                    try
                    {
                        call_user_func_array($value[0], $value[1]);
                    }
                    catch(\Exception $e)
                    {
                        throw new \Exception($e->getMessage());
                    }
                    unset(self::$tasks[$run_time]);
                }
            }
        }
    }
}

Timer::add(1, function(){
    print("执行成功!\n") . time();
})->run();

Timer::add(20, function(){
    print("执行成功1!\n") . time();
})->run();
Timer::monitor();
