<?php

namespace giudicelli\DistributedArchitectureQueue\Slave\Queue;

class Helper
{
    const ERROR_EINTR = 4;
    const ERROR_EAGAIN = 11;

    public static $ignorableSocketErrors = [
        self::ERROR_EINTR,
        self::ERROR_EAGAIN,
    ];

    public static function isIgnorableSocketErrors($socket = null): bool
    {
        if ($socket) {
            $errno = socket_last_error($socket);
        } else {
            $errno = socket_last_error();
        }
        if (!$errno) {
            return true;
        }

        return in_array($errno, self::$ignorableSocketErrors);
    }

    public static function socketSend($socket, string $data): bool
    {
        // Try a maximum of 5 times
        for ($i = 0; $i < 5; ++$i) {
            $result = @socket_write($socket, $data."\0", strlen($data) + 1);
            // An error
            if (false === $result && !Helper::isIgnorableSocketErrors($socket)) {
                return false;
            }
            if ($result) {
                // Success
                return true;
            }

            // Wait a bit before retrying (100ms)
            usleep(100000);
        }

        return false;
    }
}
