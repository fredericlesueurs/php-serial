<?php
declare(strict_types=1);

namespace FredericLesueurs\PhpSerial;

use Fredericlesueurs\PhpSerial\Exceptions\ClosingException;
use FredericLesueurs\PhpSerial\Exceptions\DeviceAlreadyOpenedException;
use FredericLesueurs\PhpSerial\Exceptions\InvalidBaudRateException;
use FredericLesueurs\PhpSerial\Exceptions\InvalidCharacterLengthException;
use FredericLesueurs\PhpSerial\Exceptions\InvalidFlowControlException;
use FredericLesueurs\PhpSerial\Exceptions\InvalidOpeningMode;
use Fredericlesueurs\PhpSerial\Exceptions\InvalidParityException;
use FredericLesueurs\PhpSerial\Exceptions\InvalidSerialPortException;
use FredericLesueurs\PhpSerial\Exceptions\OpenSerialException;
use Fredericlesueurs\PhpSerial\Exceptions\SerialNotOpenedException;
use FredericLesueurs\PhpSerial\Exceptions\UnsupportedOperatingSystemException;
use Fredericlesueurs\PhpSerial\Exceptions\WriteException;

class SerialPort
{
    private const LINUX = 'LINUX';
    private const OSX = 'OSX';

    private const VALID_BAUDS = [
        110,
        150,
        300,
        600,
        1200,
        2400,
        4800,
        9600,
        19200,
        38400,
        57600,
        115200,
    ];

    private const VALID_PARITY = [
        'none' => '-parenb',
        'odd'  => 'parenb parodd',
        'even' => 'parenb -parodd',
    ];

    private const VALID_FLOW_CONTROL = [
        'none'     => 'clocal -crtscts -ixon -ixoff',
        'rts/cts'  => '-clocal crtscts -ixon -ixoff',
        'xon/xoff' => '-clocal -crtscts ixon ixoff',
    ];
    
    private string $os;

    private ?string $device = null;

    private bool $opened = false;

    /** @var resource $stream */
    private $stream;

    private int $timeout;

    /**
     * @param string $device
     * @param SerialPortOptions|null $serialPortOptions
     * @throws InvalidBaudRateException
     * @throws InvalidParityException
     * @throws InvalidSerialPortException
     * @throws UnsupportedOperatingSystemException
     * @throws InvalidCharacterLengthException
     * @throws InvalidFlowControlException
     */
    public function __construct(string $device, ?SerialPortOptions $serialPortOptions = null)
    {
        $this->init();
        $this->setDevice($device);
        if ($serialPortOptions !== null) {
            if ($serialPortOptions->getTimeout() !== null) {
                $this->timeout = $serialPortOptions->getTimeout();
            }

            if ($serialPortOptions->getBaudRate() !== null) {
                $this->confBaudRate($serialPortOptions->getBaudRate());
            }

            if ($serialPortOptions->getParity() !== null) {
                $this->confParity($serialPortOptions->getParity());
            }

            if ($serialPortOptions->getCharacterLength() !== null) {
                $this->confCharacterLength($serialPortOptions->getCharacterLength());
            }
            
            if ($serialPortOptions->getStopBits() !== null) {
                $this->confStopBits($serialPortOptions->getStopBits());
            }

            if ($serialPortOptions->getFlowMode() !== null) {
                $this->confFlowControl($serialPortOptions->getFlowMode());
            }
        }
    }

    /**
     * @throws DeviceAlreadyOpenedException
     * @throws InvalidOpeningMode
     * @throws OpenSerialException
     */
    public function open(string $mode = 'r+b'): void
    {
        if ($this->opened) {
            throw new DeviceAlreadyOpenedException('This device has already opened');
        }

        if (!preg_match("/^[raw]\+?b?$/", $mode)) {
            throw new InvalidOpeningMode(sprintf('Invalid opening mode : %s. Use fopen() modes.', $mode));
        }

        $stream = @fopen($this->device, $mode);

        if (!$stream) {
            throw new OpenSerialException('Unable to open the device');
        }

        $this->stream = $stream;
        stream_set_blocking($this->stream, false);
        stream_set_timeout($this->stream, $this->timeout);

        $this->opened = true;
    }

    /**
     * @throws WriteException
     * @throws SerialNotOpenedException
     */
    public function write(string $str, float $waitForReply = 0.1): void
    {
        if (!$this->opened) {
            throw new SerialNotOpenedException('Device must be opened');
        }

        $write = fwrite($this->stream, $str);

        if (!$write) {
            throw new WriteException('Error while writing on serial');
        }

        usleep((int) ($waitForReply * 1000000));
    }

    /**
     * @throws SerialNotOpenedException
     */
    public function read(int $count = 0)
    {
        if (!$this->opened) {
            throw new SerialNotOpenedException('Device must be opened');
        }

        // Behavior in OSX isn't to wait for new data to recover, but just
        // grabs what's there!
        // Doesn't always work perfectly for me in OSX
        $content = '';
        $i = 0;

        if ($count !== 0) {
            do {
                if ($i > $count) {
                    $content .= fread($this->stream, ($count - $i));
                } else {
                    $content .= fread($this->stream, 256);
                }
            } while (($i += 128) === strlen($content));
        } else {
            do {
                $content .= fread($this->stream, 256);
            } while (($i += 256) === strlen($content));
        }

        return $content;
    }

    /**
     * @throws ClosingException
     */
    public function close(): void
    {
        $close = fclose($this->stream);

        if (!$close && $this->opened) {
            throw new ClosingException('Unable to close the device');
        }

        $this->device = null;
        $this->opened = false;
    }

    /**
     * @throws UnsupportedOperatingSystemException
     */
    private function init(): void
    {
        $systemName = php_uname();

        switch (true) {
            case strpos($systemName, 'Linux') === 0:
                $this->os = self::LINUX;
                break;
            case strpos($systemName, 'Darwin') === 0:
                $this->os = self::OSX;
                break;
            default:
                throw new UnsupportedOperatingSystemException('Host OS is neither osx, linux, unable to run.');
        }
    }

    /**
     * @throws InvalidSerialPortException
     * @throws UnsupportedOperatingSystemException
     */
    private function setDevice(string $device): void
    {
        switch ($this->os) {
            case self::LINUX:
                if (preg_match('/^\/dev\/ttyS\d+$/', $device) !== 1) {
                    throw new InvalidSerialPortException('The device name does not match the expected format for the operating system');
                }

                if ($this->exec(sprintf('stty -F %s', $device)) !== 0) {
                    throw new InvalidSerialPortException('Specified serial port is not valid.');
                }

                $this->device = $device;

                break;
            case self::OSX:
                if (preg_match('/^\/dev\/tty\..+$/', $device) !== 1) {
                    throw new InvalidSerialPortException('The device name does not match the expected format for the operating system');
                }

                if ($this->exec(sprintf('stty -f %s', $device)) !== 0) {
                    throw new InvalidSerialPortException('Specified serial port is not valid.');
                }

                $this->device = $device;

                break;
            default:
                throw new UnsupportedOperatingSystemException('Host OS is neither osx, linux, unable to run.');
        }
    }

    /**
     * @throws InvalidBaudRateException
     * @throws UnsupportedOperatingSystemException
     */
    private function confBaudRate(int $rate): void
    {
        if (!in_array($rate, self::VALID_BAUDS, true)) {
            throw new InvalidBaudRateException('Unable to set baud rate: '.$rate);
        }

        switch ($this->os) {
            case self::LINUX:
                if ($this->exec(sprintf('stty -F %s %s', $this->device, $rate)) !== 0) {
                    throw new InvalidBaudRateException('Specified baud rate is not valid.');
                }

                break;
            case self::OSX:
                if ($this->exec(sprintf('stty -f %s %s', $this->device, $rate)) !== 0) {
                    throw new InvalidBaudRateException('Specified baud rate is not valid.');
                }

                break;
            default:
                throw new UnsupportedOperatingSystemException('Host OS is neither osx, linux, unable to run.');
        }
    }

    /**
     * @throws InvalidParityException
     * @throws UnsupportedOperatingSystemException
     */
    private function confParity(string $parity = 'none'): void
    {
        if (!in_array($parity, self::VALID_PARITY, true)) {
            throw new InvalidParityException('Specified parity value is not valid.');
        }

        switch ($this->os) {
            case self::LINUX:
                if ($this->exec(sprintf('stty -F %s %s', $this->device, self::VALID_PARITY[$parity])) !== 0) {
                    throw new InvalidParityException('Specified parity value is not valid.');
                }

                break;
            case self::OSX:
                if ($this->exec(sprintf('stty -f %s %s', $this->device, self::VALID_PARITY[$parity])) !== 0) {
                    throw new InvalidParityException('Specified parity value is not valid.');
                }

                break;
            default:
                throw new UnsupportedOperatingSystemException('Host OS is neither osx, linux, unable to run.');
        }
    }

    /**
     * @throws InvalidCharacterLengthException
     * @throws UnsupportedOperatingSystemException
     */
    private function confCharacterLength(int $int): void
    {
        if ($int < 5) {
            $int = 5;
        }

        if ($int > 8) {
            $int = 8;
        }

        switch ($this->os) {
            case self::LINUX:
                if ($this->exec(sprintf('stty -F %s cs %s', $this->device, $int)) !== 0) {
                    throw new InvalidCharacterLengthException('Specified character length is not valid.');
                }

                break;
            case self::OSX:
                if ($this->exec(sprintf('stty -f %s cs %s', $this->device, $int)) !== 0) {
                    throw new InvalidCharacterLengthException('Specified character length is not valid.');
                }

                break;
            default:
                throw new UnsupportedOperatingSystemException('Host OS is neither osx, linux, unable to run.');
        }
    }

    /**
     * @throws InvalidCharacterLengthException
     * @throws UnsupportedOperatingSystemException
     */
    private function confStopBits(int $length): void
    {
        switch ($this->os) {
            case self::LINUX:
                if ($this->exec(sprintf('stty -F %s %scstopb', $this->device, $length === 1 ? '-' : '')) !== 0) {
                    throw new InvalidCharacterLengthException('Specified character length is not valid.');
                }

                break;
            case self::OSX:
                if ($this->exec(sprintf('stty -f %s cs %scstopb', $this->device, $length === 1 ? '-' : '')) !== 0) {
                    throw new InvalidCharacterLengthException('Specified character length is not valid.');
                }

                break;
            default:
                throw new UnsupportedOperatingSystemException('Host OS is neither osx, linux, unable to run.');
        }
    }

    /**
     * @throws InvalidFlowControlException
     * @throws UnsupportedOperatingSystemException
     */
    private function confFlowControl(string $mode): void
    {
        if (!array_key_exists($mode, self::VALID_FLOW_CONTROL)) {
            throw new InvalidFlowControlException('Specified mode is not valid.');
        }

        switch ($this->os) {
            case self::LINUX:
                if ($this->exec(sprintf('stty -F %s %s', $this->device, self::VALID_FLOW_CONTROL[$mode])) !== 0) {
                    throw new InvalidFlowControlException('Specified mode is not valid.');
                }

                break;
            case self::OSX:
                if ($this->exec(sprintf('stty -f %s %s', $this->device, self::VALID_FLOW_CONTROL[$mode])) !== 0) {
                    throw new InvalidFlowControlException('Specified mode is not valid.');
                }

                break;
            default:
                throw new UnsupportedOperatingSystemException('Host OS is neither osx, linux, unable to run.');
        }
    }

    private function exec(string $cmd, &$out = null): int
    {
        $desc = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $proc = proc_open($cmd, $desc, $pipes);

        $ret = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $procStatus = proc_close($proc);

        if (func_num_args() === 2) {
            $out = [$ret, $err];
        }

        return $procStatus;
    }
}