<?php

declare(strict_types=1);

namespace SunProxy\SunProxyAPI;

use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\Thread;

use pocketmine\utils\Binary;
use Threaded;
use function socket_create;

use const AF_INET;
use const SOL_TCP;
use const SOCK_STREAM;

class SockThread extends Thread
{

    /**
     * @var string $ip - the ip of the given sun server
     */
    private string $ip;

    /**
     * @var int $port - the port that the tcp listener is listening on.
     */
    private int $port;

    /**
     * @var string $key - the key needed to login into the server.
     */
    private string $key;

    /**
     * @var Threaded<string> $in - the buffer on the recieving side or into the client.
     */
    private Threaded $in;

    /**
     * @var Threaded<string> $out - the outwards buffer going out to the server.
     */
    private Threaded $out;

    /**
     * @var bool $running - represents wether the thread / client is running.
     */
    private bool $running;


    public function __construct(string $ip, int $port, string $key)
    {
        $this->ip = $ip;
        $this->port = $port;
        $this->key = $key;
        $this->in = new Threaded();
        $this->out = new Threaded();
    }

    public function run()
    {
        //Connect to socket.
        try {
            $fd = $this->connect();
            while ($this->running) {
                while (($send = $this->out->shift()) !== null) {
                    $length = strlen($send);
                    $wrote = @socket_write($fd, Binary::writeLInt($length) . $send, 4 + $length);
                    if ($wrote !== 4 + $length) {
                        socket_close($fd);
                        $fd = $this->connect();
                    }
                }
            }
        } catch (TCPSocketException $ex) {
            return;
        }
    }

    /**
     * @return false|resource|\Socket
     * @throws TCPSocketException
     */
    public function connect() {
        $fd = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$fd) {
            throw new TCPSocketException(socket_strerror(socket_last_error()));
        }
        $succ = socket_connect($fd, $this->ip, $this->port);
        if (!$succ) {
            throw new TCPSocketException(socket_strerror(socket_last_error()));
        }
        socket_set_nonblock($fd);
        return $fd;
    }

    public function start(int $options = PTHREADS_INHERIT_NONE)
    {
        $this->running = true;
        return parent::start($options); // TODO: Change the autogenerated stub
    }

    public function quit(): void
    {
        $this->running = false;
        parent::quit();
    }

    public function SendPacket(DataPacket $pk) {
        //Encode packet
        $pk->encode();
        $this->out[] = $pk->getBuffer();
    }

    public function RecieveBuff(): ?string {
        return $this->out->shift();
    }
}