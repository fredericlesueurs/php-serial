<?php
declare(strict_types=1);

namespace FredericLesueurs\PhpSerial;

class SerialPortOptions
{
    public const NONE_PARITY = 'none';
    public const ODD_PARITY = 'odd';
    public const EVEN_PARITY = 'even';

    public const NONE_FLOW_MODE = 'none';
    public const RTS_CTS_FLOW_MODE = 'rts/cts';
    public const XON_XOFF_FLOW_MODE = 'xon/xoff';

    private ?int $timeout = null;
    private ?int $baudRate = null;
    private ?string $parity = null;
    private ?int $characterLength = null;
    private ?int $stopBits = null;
    private ?string $flowMode = null;

    /**
     * @param int|null $timeout
     * @param int|null $baudRate
     * @param string|null $parity
     * @param int|null $characterLength
     * @param int|null $stopBits
     * @param string|null $flowMode
     */
    public function __construct(?int $timeout = null, ?int $baudRate = null, ?string $parity = null, ?int $characterLength = null, ?int $stopBits = null, ?string $flowMode = null)
    {
        $this->timeout = $timeout;
        $this->baudRate = $baudRate;
        $this->parity = $parity;
        $this->characterLength = $characterLength;
        $this->stopBits = $stopBits;
        $this->flowMode = $flowMode;
    }

    /**
     * @return int|null
     */
    public function getTimeout(): ?int
    {
        return $this->timeout;
    }

    /**
     * @param int|null $timeout
     */
    public function setTimeout(?int $timeout): void
    {
        $this->timeout = $timeout;
    }

    /**
     * @return int|null
     */
    public function getBaudRate(): ?int
    {
        return $this->baudRate;
    }

    /**
     * @return string|null
     */
    public function getParity(): ?string
    {
        return $this->parity;
    }

    /**
     * @return int|null
     */
    public function getCharacterLength(): ?int
    {
        return $this->characterLength;
    }

    /**
     * @return int|null
     */
    public function getStopBits(): ?int
    {
        return $this->stopBits;
    }

    /**
     * @return string|null
     */
    public function getFlowMode(): ?string
    {
        return $this->flowMode;
    }
}