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
            $clientTool = new \GuzzleHttp\Client([
                'base_uri'=>file_get_contents('/etc/base_uri'),
                'verify' => false,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . file_get_contents('/etc/token'),
                ],
                'timeout' => 5
            ]);
            $query = [
                'connection_key' => file_get_contents('/etc/connectionKey')
            ];
            $webSocket = new WebSocket(file_get_contents('/etc/gateway').'/?' . http_build_query($query),true);
            $webSocket->onOpen(function(){
                echo "连接成功\n";
            })->onMessage(function(\Swlib\Saber\WebSocket $webSocket,\Swlib\Saber\WebSocketFrame $result) use($query,$clientTool){
                $message = unserialize($result->data);
                echo "执行任务:{$message['method']} {$message['uri']} 消息ID:{$message['MessageId']}\n";
                try{
                    $response = $clientTool->request($message['method'],$message['uri'],$message['options']);
                    $responseMessage = 'SUCCESS';
                }catch (\GuzzleHttp\Exception\ClientException $clientException){
                    $response = $clientException->getResponse();
                    $responseMessage = $clientException->getMessage();
                }
                $webSocket->push(serialize([
                    'MessageId'=>$message['MessageId'],
                    'connection_key'=>$query['connection_key'],
                    'response'=>[
                        'code'=>$response->getStatusCode(),
                        'headers'=>$response->getHeaders(),
                        'content'=>$response->getBody()->getContents(),
                        'message'=>$responseMessage
                    ]
                ]));
            })->onClose(function(){
                echo "连接关闭\n";
            })->connection();
        });
    }
}