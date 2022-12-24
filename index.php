<?php
include_once "vendor/autoload.php";
use Swoole\Coroutine\Http\Client;
use function Swoole\Coroutine\run;


run(function () {
    $clientTool = new \GuzzleHttp\Client([
        'base_uri'=>'https://k8s-local:6443',
        'verify' => false,
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . file_get_contents('/etc/token'),
        ],
        'timeout' => 5
    ]);

    $connectionKey = 'Vra6hI9KWjQ5RfcLNYB4DEPgtx32Zi7p';
    $client = new Client('127.0.0.1', 9501);
    $client->setHeaders([
        'Host' => 'localhost',
        'User-Agent' => 'Chrome/49.0.2587.3',
    ]);
    $ret = $client->upgrade("/?connection_key={$connectionKey}");
    if ($ret) {
        echo "开始监听任务\n";
        while(true) {
            /**
             * @var \Swoole\WebSocket\Frame $message
             */
            if(($message = $client->recv(3)) instanceof \Swoole\WebSocket\Frame){
                $request = json_decode($message->data,true);
                echo "开始处理任务:{$request['id']}\n";
                try{
                    $response = $clientTool->request($request['method'],$request['uri'],json_decode($request['options'],true));
                    $message = '';
                }catch (\GuzzleHttp\Exception\ClientException $clientException){
                    $message = $clientException->getMessage();
                    $response = $clientException->getResponse();
                }
                $client->push(json_encode([
                    'connection_key'=>$connectionKey,
                    'request_id'=>$request['id'],
                    'response_message'=>$message,
                    'response_code'=>$response->getStatusCode(),
                    'response_content'=>$response->getBody()->getContents(),
                    'response_headers'=>$response->getHeaders()
                ]));
            }
            \Swoole\Coroutine::sleep(0.1);
        }
    }
});