<?php


namespace Command;


use GuzzleHttp\Psr7\Uri;
use Service\ResultMessage;
use Service\TaskMessage;
use Swoole\Coroutine;
use Swoole\Coroutine\Http\Client;
use Psr\Log\LogLevel;
use Swoole\Http\Status;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use function Swoole\Coroutine\run;

/**
 * Class WatchProxyCommand
 * @package Command
 */
class WatchProxyCommand extends Command
{
    /**
     * @var ConsoleLogger
     */
    public ConsoleLogger $logger;
    /**
     * 配置
     * @var array
     */
    protected array $config = [
        'proxy-scheme' => 'wss',
        'proxy-host' => 'tash.itxiao6.top',
        'proxy-port' => 443,
        'proxy-connection-key' => '',
        'local-kube-host' => 'kubernetes.docker.internal',
        'local-kube-scheme' => 'https',
        'local-kube-port' => 6443,
        'local-kube-token' => '',
    ];
    /**
     * 重试次数
     * @var int
     */
    protected int $retry = 0;
    /**
     * 代理通信客户端
     * @var Client
     */
    protected Client $proxyClient;
    /**
     * KubeApi客户端
     * @var \GuzzleHttp\Client
     */
    protected \GuzzleHttp\Client $kubeApiClient;
    /**
     * K8S终端客户端列表
     * @var Client[]
     */
    protected array $terminalConnection;

    public function __construct()
    {
        /**
         * ConnectionKey
         */
        $this->config['proxy-connection-key'] = file_get_contents('/etc/connectionKey');
        /**
         * WebSocket Server
         */
        $socket = new Uri(file_get_contents('/etc/gateway'));
        $this->config['proxy-scheme'] = $socket->getScheme();
        $this->config['proxy-host'] = $socket->getHost();
        $this->config['proxy-port'] = ($socket->getPort()==null&&$socket->getScheme()=='wss')?443:($socket->getPort()==null?80:$socket->getPort());
        /**
         * 本地KubeServer
         */
        $base_uri = new Uri(file_get_contents('/etc/base_uri'));
        $this->config['local-kube-host'] = $base_uri->getHost();
        $this->config['local-kube-port'] = $base_uri->getPort();
        $this->config['local-kube-scheme'] = $base_uri->getScheme();
        /**
         * Token授权
         */
        $this->config['local-kube-token'] = file_get_contents('/etc/token');
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
        $this->logger = new ConsoleLogger($output, [
            LogLevel::NOTICE => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::INFO => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::DEBUG => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::WARNING => OutputInterface::VERBOSITY_NORMAL,
        ]);
        $this->kubeApiClient = new \GuzzleHttp\Client([
            'base_uri' => "{$this->config['local-kube-scheme']}://{$this->config['local-kube-host']}:{$this->config['local-kube-port']}",
            'verify' => false,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->config['local-kube-token'],
            ],
            'timeout' => 5
        ]);
        $this->exec();
        return 0;
    }

    /**
     * 发起连接
     */
    public function connection()
    {
        /**
         * 连接代理服务
         */
        $this->proxyClient = new Client($this->config['proxy-host'], $this->config['proxy-port'],$this->config['proxy-scheme'] == 'wss');
        try {
            /**
             * 升级协议
             */
            $this->checkRet($this->proxyClient->upgrade('/?' . http_build_query([
                    'type' => 'kube-proxy',
                    'connection_key' => $this->config['proxy-connection-key']
                ])), $this->proxyClient);
            /**
             * 日志输出
             */
            $this->logger->info(date('Y-m-d H:i:s') . ',代理进程连接成功。');
        } catch (\Throwable $throwable) {
            $this->logger->error(date('Y-m-d H:i:s') . ',连接失败。');
            if ($this->retry >= 30) {
                $this->retry = 0;
            }
            $this->retry++;
            $this->logger->info(date('Y-m-d H:i:s') . ",{$this->retry}秒后 重新连接");
            sleep($this->retry);
            $this->connection();
        }
    }

    protected function exec()
    {
        run(function () {
            \Swoole\Runtime::enableCoroutine($flags = SWOOLE_HOOK_ALL);
            /**
             * 发起连接
             */
            $this->connection();
            Coroutine::create(function () {
                while (true) {
                    /**
                     * 监听状态
                     */
                    if (!$this->proxyClient->connected) {
                        $this->logger->error(date('Y-m-d H:i:s') . ",连接关闭");
                        $this->connection();
                    }
                    /**
                     * 监听消息
                     */
                    $message = $this->proxyClient->recv();
                    if (!($message instanceof \Swoole\WebSocket\Frame)) {
                        continue;
                    }
                    /**
                     * 序列化任务消息
                     */
                    $message = new TaskMessage(unserialize($message->data));
                    switch (true) {
                        case $message->getTaskType() == 'kube-api':
                            $this->logger->info(date('Y-m-d H:i:s') . ",收到任务:请求接口,{$message->getBody()['uri']}");
                            try {
                                $response = $this->kubeApiClient->request($message->getBody()['method'], $message->getBody()['uri'], $message->getBody()['options']);
                                $responseMessage = 'SUCCESS';
                            } catch (\GuzzleHttp\Exception\ClientException $clientException) {
                                $response = $clientException->getResponse();
                                $responseMessage = $clientException->getMessage();
                            }
                            $this->proxyClient->push((new ResultMessage())->setType('api-result')->setMessageId($message->getMessageId())->setBody([
                                'code' => $response->getStatusCode(),
                                'headers' => $response->getHeaders(),
                                'content' => $response->getBody()->getContents(),
                                'message' => $responseMessage
                            ])->setConnectionKey($this->config['proxy-connection-key'])->toString());
                            break;
                        case $message->getTaskType() == 'input-terminal':
//                             $this->logger->info(date('Y-m-d H:i:s').',终端输入:'."\x00".base64_decode($message->getBody()['content']));
                            $this->terminalConnection[$message->getMessageId()]->push("\x00" . base64_decode($message->getBody()['content']));
                            break;
                        case $message->getTaskType() == 'close-terminal':
                            if (!isset($this->terminalConnection[$message->getMessageId()])) {
                                break;
                            }
                            $this->logger->info(date('Y-m-d H:i:s') . ',关闭连接:' . $message->getMessageId());
                            $this->terminalConnection[$message->getMessageId()]->close();
                            unset($this->terminalConnection[$message->getMessageId()]);
                            break;
                        case $message->getTaskType() == 'connection-terminal':
                            /**
                             * 创建连接
                             */
                            $this->terminalConnection[$message->getMessageId()] = new Client($this->config['local-kube-host'], $this->config['local-kube-port'], true);
                            /**
                             * 授权
                             */
                            $this->terminalConnection[$message->getMessageId()]->setHeaders([
                                'authorization' => "Bearer {$this->config['local-kube-token']}"
                            ]);
                            /**
                             * 拼接参数
                             */
                            $query = [
                                'container' => $message->getBody()['container'],
                                'command' => $message->getBody()['command'],
                                'pretty' => $message->getBody()['pretty'],
                                'stdin' => $message->getBody()['stdin'],
                                'stdout' => $message->getBody()['stdout'],
                                'stderr' => $message->getBody()['stderr'],
                                'tty' => $message->getBody()['tty'],
                            ];
                            /**
                             * 拼接链接
                             */
                            $uri = "/api/v1/namespaces/{$message->getBody()['namespace']}/pods/{$message->getBody()['pods']}/exec?" . http_build_query($query);
                            try{
                                /**
                                 * 升级连接
                                 */
                                $this->checkRet($this->terminalConnection[$message->getMessageId()]->upgrade($uri), $this->terminalConnection[$message->getMessageId()]);
                            }catch (\Throwable $throwable){
                                unset($this->terminalConnection[$message->getMessageId()]);
                                // 通知服务端 断开连接
                                $this->proxyClient->push((new ResultMessage())->
                                setType('terminal-close')->
                                setBody([])->
                                setConnectionKey($this->config['proxy-connection-key'])->
                                setMessageId($message->getMessageId())->
                                toString());
                                break;
                            }
                            /**
                             * 打印日志
                             */
                            $this->logger->info(date('Y-m-d H:i:s') . ",连接成功:{$message->getMessageId()}");

                            Coroutine::create(function () use ($message) {
                                while (true) {
                                    /**
                                     * 监听连接状态
                                     */
                                    if (!$this->terminalConnection[$message->getMessageId()]->connected) {
                                        unset($this->terminalConnection[$message->getMessageId()]);
                                        // 通知服务端 断开连接
                                        $this->proxyClient->push((new ResultMessage())->
                                        setType('terminal-close')->
                                        setBody([])->
                                        setConnectionKey($this->config['proxy-connection-key'])->
                                        setMessageId($message->getMessageId())->
                                        toString());
                                        break;
                                    }
                                    /**
                                     * 监听消息
                                     */
                                    $terminalMessage = $this->terminalConnection[$message->getMessageId()]->recv(1);
                                    if (!($terminalMessage instanceof \Swoole\WebSocket\Frame)) {
                                        continue;
                                    }
//                                     $this->logger->info(date('Y-m-d H:i:s').',终端反馈:'.$terminalMessage->data);
                                    // 通知CNPP服务端
                                    $this->proxyClient->push((new ResultMessage())->setType('terminal-result')->setBody([
                                        'content' => base64_encode($terminalMessage->data)
                                    ])->setConnectionKey($this->config['proxy-connection-key'])->setMessageId($message->getMessageId())->toString());
                                }
                            });
                            break;
                        default:
                            $this->logger->error(date('Y-m-d H:i:s') . ",任务消息错误:{$message->getTaskType()}");
                            break;
                    }
                }
            });
            Coroutine::wait();
            return 0;
        });
    }

    /**
     * @param $ret
     * @param $client
     * @throws \Exception
     */
    public function checkRet($ret, $client)
    {
        if (!$ret) {
            if ($this->proxyClient->errCode !== 0) {
                $errCode = $client->errCode;
                $errMsg = $client->errMsg;
            } else {
                $errCode = $client->statusCode;
                $errMsg = Status::getReasonPhrase($errCode);
            }
            throw new \Exception("Websocket upgrade failed by ['{$errMsg}'].{$errCode}");
        }
    }
}