<?php


namespace Service;

/**
 * 结果消息
 * Class ResultMessage
 * @package Service
 */
class ResultMessage
{
    /**
     * @var string
     */
    protected string $type = '';
    /**
     * @var array
     */
    protected array $body;
    /**
     * @var string
     */
    protected string $messageId;
    /**
     * @var string
     */
    protected string $connectionKey;

    public function __construct()
    {
    }

    /**
     * @param string $type
     * @return $this
     */
    public function setType(string $type)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @param string $messageId
     * @return $this
     */
    public function setMessageId(string $messageId)
    {
        $this->messageId = $messageId;
        return $this;
    }

    /**
     * @param string $connectionKey
     * @return $this
     */
    public function setConnectionKey(string $connectionKey): ResultMessage
    {
        $this->connectionKey = $connectionKey;
        return $this;
    }

    /**
     * @param array $body
     * @return $this
     */
    public function setBody(array $body)
    {
        $this->body = $body;
        return $this;
    }

    /**
     * @param string $type
     * @return string
     */
    public function toString(string $type = 'serialize')
    {
        $data = [
            'type' => $this->type,
            'body' => $this->body,
            'connectionKey' => $this->connectionKey,
            'messageId' => $this->messageId
        ];
        switch (true){
            case $type=='serialize':
                return 'serialize.' . serialize($data);
            case $type=='json':
                return 'json.' . json_encode($data);
            default:
                return 'json.' . json_encode($data);
        }
    }
}