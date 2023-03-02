<?php


namespace Command;


use Service\WebSocket;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function Swoole\Coroutine\run;

/**
 * Class WatchProxyCommand
 * @package Command
 */
class WatchProxyCommand extends Command
{

    public function __construct()
    {
        parent::__construct('WatchProxy');
        $this->setDescription("启动代理进程");
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->connection();
        return 0;
    }

    protected function connection()
    {
        run(function (){
            $query = [
                'connection_key' => '8RIlcwjdnL3gso5hxrKizGWpXCfNtY2F'
            ];
            $webSocket = new WebSocket('ws://127.0.0.1:9501/?' . http_build_query($query),true);
            $webSocket->onOpen(function(){
                echo "连接成功\n";
            })->onMessage(function($result){
                echo "收到消息\n";
                dump($result);
            })->onClose(function(){
                echo "连接关闭\n";
            })->connection();
        });
    }
}