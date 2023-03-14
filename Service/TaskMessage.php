<?php


namespace Service;

/**
 * 任务消息
 * Class TaskMessage
 * @package Service
 */
class TaskMessage
{
    /**
     * @var string
     */
    protected $task = '';
    /**
     * @var array
     */
    protected $body = [];
    /**
     * 消息ID
     * @var string
     */
    protected $messageId = '';

    /**
     * TaskMessage constructor.
     * @param array $message
     */
    public function __construct(array $message)
    {
        $this->task = $message['task'];
        $this->body = $message['body'];
        $this->messageId = $message['messageId'];
    }

    /**
     * @return mixed|string
     */
    public function getTaskType()
    {
        return $this->task;
    }

    /**
     * @return mixed|string
     */
    public function getMessageId()
    {
        return $this->messageId;
    }

    /**
     * @return array|mixed
     */
    public function getBody()
    {
        return $this->body;
    }
}