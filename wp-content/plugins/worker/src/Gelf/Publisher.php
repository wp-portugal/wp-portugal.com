<?php

class Gelf_Publisher
{
    const CHUNK_SIZE_WAN = 1420;

    const CHUNK_SIZE_LAN = 8154;

    const GRAYLOG2_DEFAULT_PORT = 12201;

    const GRAYLOG2_PROTOCOL_VERSION = '1.0';

    /**
     * @var string
     */
    protected $hostname;

    /**
     * @var int
     */
    protected $port;

    /**
     * @var int|null
     */
    protected $fallbackPort;

    /**
     * @var int
     */
    protected $chunkSize;

    /**
     * @var resource|null
     */
    protected $streamSocketClient = null;

    /**
     * @var bool
     */
    private static $brokenSocket = false;

    /**
     * Creates a new publisher that sends errors to a Graylog2 server via UDP
     *
     * @throws InvalidArgumentException
     *
     * @param string       $hostname
     * @param integer      $port
     * @param integer|null $fallbackPort
     * @param integer      $chunkSize
     */
    public function __construct($hostname, $port = null, $fallbackPort = null, $chunkSize = null)
    {
        // Check whether the parameters are set correctly
        if (!$hostname) {
            throw new InvalidArgumentException('$hostname must be set');
        }

        if ($port === null) {
            $port = self::GRAYLOG2_DEFAULT_PORT;
        } elseif (!is_numeric($port)) {
            throw new InvalidArgumentException('$port must be an integer');
        }

        if ($fallbackPort !== null && !is_numeric($fallbackPort)) {
            throw new InvalidArgumentException('$fallbackPort must be an integer');
        }

        if ($chunkSize === null) {
            $chunkSize = self::CHUNK_SIZE_WAN;
        } elseif (!is_numeric($chunkSize)) {
            throw new InvalidArgumentException('$chunkSize must be an integer');
        }

        $this->hostname     = $hostname;
        $this->port         = $port;
        $this->fallbackPort = $fallbackPort;
        $this->chunkSize    = $chunkSize;
    }

    /**
     * Publishes a Gelf_Message, returns false if an error occurred during write.
     *
     * @throws UnexpectedValueException
     *
     * @param Gelf_Message $message
     *
     * @return boolean
     */
    public function publish(Gelf_Message $message)
    {
        if (self::$brokenSocket) {
            return false;
        }
        // Check if required message parameters are set
        if (!$message->getShortMessage() || !$message->getHost()) {
            throw new UnexpectedValueException(
                'Missing required data parameter: "version", "short_message" and "host" are required.'
            );
        }

        // Set Graylog protocol version
        $message->setVersion(self::GRAYLOG2_PROTOCOL_VERSION);

        // Encode the message as json string and compress it using gzip
        $preparedMessage = $this->getPreparedMessage($message);

        // Infinite-loop break.
        self::$brokenSocket = true;
        // Open a connection to GrayLog server.
        $socket = $this->getSocketConnection();

        if (!$socket) {
            return false;
        }
        self::$brokenSocket = false;

        // Several udp writes are required to publish the message
        if ($this->isMessageSizeGreaterChunkSize($preparedMessage)) {
            // A unique id which consists of the microtime and a random value
            $messageId = $this->getMessageId();

            // Split the message into chunks.
            $messageChunks      = $this->getMessageChunks($preparedMessage);
            $messageChunksCount = count($messageChunks);

            // Send chunks to GrayLog server.
            foreach (array_values($messageChunks) as $messageChunkIndex => $messageChunk) {
                $bytesWritten = $this->writeMessageChunkToSocket(
                    $socket,
                    $messageId,
                    $messageChunk,
                    $messageChunkIndex,
                    $messageChunksCount
                );

                if (false === $bytesWritten) {
                    // Abort due to write error
                    return false;
                }
            }
        } else {
            // A single write is enough to get the message published
            if (false === $this->writeMessageToSocket($socket, $preparedMessage)) {
                // Abort due to write error
                return false;
            }
        }

        // This increases stability a lot if messages are sent in a loop
        // A value of 20 means 0.02 ms
        usleep(20);

        // Message successful sent
        return true;
    }

    /**
     * @param Gelf_Message $message
     *
     * @return string
     */
    protected function getPreparedMessage(Gelf_Message $message)
    {
        return gzcompress(json_encode($message->toArray()));
    }

    /**
     * @return resource|false
     */
    protected function getSocketConnection()
    {
        if (!$this->streamSocketClient) {
            $hostname                 = gethostbyname($this->hostname);
            $this->streamSocketClient = stream_socket_client(sprintf('udp://%s:%d', $hostname, $this->port));
            if ($this->streamSocketClient === false && $this->fallbackPort) {
                $this->streamSocketClient = stream_socket_client(sprintf('tcp://%s:%d', $hostname, $this->fallbackPort));
            }
        }

        return $this->streamSocketClient;
    }

    /**
     * @param string $preparedMessage
     *
     * @return boolean
     */
    protected function isMessageSizeGreaterChunkSize($preparedMessage)
    {
        return (strlen($preparedMessage) > $this->chunkSize);
    }

    /**
     * @return float
     */
    protected function getMessageId()
    {
        return (float)(microtime(true).mt_rand(0, 10000));
    }

    /**
     * @param string $preparedMessage
     *
     * @return array
     */
    protected function getMessageChunks($preparedMessage)
    {
        return str_split($preparedMessage, $this->chunkSize);
    }

    /**
     * @param float   $messageId
     * @param string  $data
     * @param integer $sequence
     * @param integer $sequenceSize
     *
     * @throws InvalidArgumentException
     * @return string
     */
    protected function prependChunkInformation($messageId, $data, $sequence, $sequenceSize)
    {
        if (!is_string($data) || $data === '') {
            throw new InvalidArgumentException('Data must be a string and not be empty.');
        }

        if (!is_integer($sequence) || !is_integer($sequenceSize)) {
            throw new InvalidArgumentException('Sequence number and size must be integer.');
        }

        if ($sequenceSize <= 0) {
            throw new InvalidArgumentException('Sequence size must be greater than 0.');
        }

        if ($sequence > $sequenceSize) {
            throw new InvalidArgumentException('Sequence size must be greater than sequence number.');
        }

        return pack('CC', 30, 15).substr(md5($messageId, true), 0, 8).pack('CC', $sequence, $sequenceSize).$data;
    }

    /**
     * @param resource $socket
     * @param float    $messageId
     * @param string   $messageChunk
     * @param integer  $messageChunkIndex
     * @param integer  $messageChunksCount
     *
     * @return integer|boolean
     */
    protected function writeMessageChunkToSocket($socket, $messageId, $messageChunk, $messageChunkIndex, $messageChunksCount)
    {
        return fwrite(
            $socket,
            $this->prependChunkInformation($messageId, $messageChunk, $messageChunkIndex, $messageChunksCount)
        );
    }

    /**
     * @param resource $socket
     * @param string   $preparedMessage
     *
     * @return integer|boolean
     */
    protected function writeMessageToSocket($socket, $preparedMessage)
    {
        return fwrite($socket, $preparedMessage);
    }
}
