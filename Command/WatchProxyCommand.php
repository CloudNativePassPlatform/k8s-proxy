<?php


namespace Command;


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
    /**
     * @var \Swlib\Saber\WebSocket
     */
    public \Swlib\Saber\WebSocket $websocket;
    /**
     * @var int
     */
    public int $retry = 0;

    public function __construct()
    {
        parent::__construct('WatchProxy');
        $this->setDescription("启动代理进程");
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        run(function () {
            $this->connection();
        });
        return 0;
    }

    protected function connection()
    {
        $query = [
            'connection_key' => 'CjO8kVIgTDMfwSh7oNuF13cYanmGPrzB'
        ];
        try {
            $this->websocket = new \Swlib\Saber\WebSocket(new \Swlib\Http\Uri('ws://127.0.0.1:9501/?' . http_build_query($query)));
        } catch (\Throwable $connectException) {
            $this->retry += 1;
            echo "连接失败，{$this->retry}秒后重试\n";
            sleep($this->retry);
            $this->connection();
        }
        $this->retry = 0;
        echo "连接成功\n";
        while (true) {
            if (!$this->websocket->client->connected) {
                $this->onClose();
                break;
            }
            $result = $this->websocket->recv();
            if ($result instanceof \Swlib\Saber\WebSocketFrame) {
                \Swoole\Coroutine::create(function () use ($result) {
                    $this->onMessage($result);
                });
            }
            usleep(50000);
        }
    }

    protected function onClose()
    {
        $this->connection();
        echo "连接关闭\n";
    }

    protected function onMessage(\Swlib\Saber\WebSocketFrame $frame)
    {
        echo "接收到消息:{$frame->data}\n";
    }
}