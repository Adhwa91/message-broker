<?php

require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RequestDiskUsage
{
    private $connection;
    private $channel;
    private $callback_queue;
    private $response;
    private $corr_id;

    public function __construct()
    {
        $this->connection = new AMQPStreamConnection(
            '192.168.56.1',
            5672,
            'adhwa',
            '123456'
        );
        $this->channel              = $this->connection->channel();
        list($this->callback_queue) = $this->channel->queue_declare(
            "",
            false,
            false,
            true,
            false
        );
        $this->channel->basic_consume(
            $this->callback_queue,
            '',
            false,
            true,
            false,
            false,
            array(
                $this,
                'onResponse',
            )
        );
    }

    public function onResponse($rep)
    {
        if ($rep->get('correlation_id') == $this->corr_id) {
            $this->response = $rep->body;
        }
    }

    public function call($n)
    {
        $this->response = null;
        $this->corr_id  = uniqid();

        $msg = new AMQPMessage(
            (string) $n,
            array(
                'correlation_id' => $this->corr_id,
                'reply_to'       => $this->callback_queue,
            )
        );
        $this->channel->basic_publish($msg, '', 'rpc_queue');
        while (!$this->response) {
            $this->channel->wait();
        }
        return $this->response;
    }
}

$diskusage_rpc = new RequestDiskUsage();
$response1     = $diskusage_rpc->call('free');
$response2     = $diskusage_rpc->call('available');
$response3     = $diskusage_rpc->call('size');
$response4     = $diskusage_rpc->call('used');
$response5     = $diskusage_rpc->call('usage');

echo " [.] Free: ", $response1, " MB \n [.] Available: ", $response2, " MB \n [.] Size: ", $response3, " MB \n [.] Used: ", $response4, " MB \n [.] Usage: ", $response5, " % \n";
