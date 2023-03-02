<?php


namespace Service;

use Swoole\Coroutine;

/**
 * Class WebSocket
 * @package Service
 */
class WebSocket
{
    /**
     * @var \Swlib\Saber\WebSocket
     */
    protected \Swlib\Saber\WebSocket $websocket;
    /**
     * 重试次数
     * @var int
     */
    protected int $retry = 0;
    /**
     * @var string
     */
    protected string $uri;
    /**
     * 自动重连
     * @var bool
     */
    protected bool $autoReconnection = true;
    /**
     * @var
     */
    protected $openEvent;
    /**
     * @var
     */
    protected $messageEvent;
    /**
     * @var
     */
    protected $closeEvent;

    /**
     * 构造方法
     * WebSocket constructor.
     * @param string $uri
     * @param bool $autoReconnection
     */
    public function __construct(string $uri, bool $autoReconnection = true)
    {
        $this->uri = $uri;
        $this->autoReconnection = $autoReconnection;
    }

    /**
     * 发起连接
     */
    public function connection()
    {
        try {
            $this->websocket = new \Swlib\Saber\WebSocket(new \Swlib\Http\Uri($this->uri));
        } catch (\Throwable $connectException) {
            $this->retry += 1;
            echo "连接失败，{$this->retry}秒后重试\n";
            sleep($this->retry);
            $this->connection();
        }
        $this->retry = 0;
        Coroutine::create($this->openEvent, [$this->websocket]);
        while (true) {
            if (!$this->websocket->client->connected) {
                Coroutine::create($this->closeEvent, [$this->websocket]);
                if ($this->autoReconnection) {
                    $this->connection();
                }
                break;
            }
            $result = $this->websocket->recv();
            if ($result instanceof \Swlib\Saber\WebSocketFrame) {
                \Swoole\Coroutine::create($this->messageEvent, $this->websocket, $result);
            }
            usleep(50000);
        }
    }

    /**
     * @param $callback
     * @return $this
     */
    public function onOpen($callback): self
    {
        $this->openEvent = $callback;
        return $this;
    }

    /**
     * @param $callback
     * @return $this
     */
    public function onClose($callback): self
    {
        $this->closeEvent = $callback;
        return $this;
    }

    /**
     * @param $callback
     * @return $this
     */
    public function onMessage($callback): self
    {
        $this->messageEvent = $callback;
        return $this;
    }
}