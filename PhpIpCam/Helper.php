<?php

namespace PhpIpCam;

use DateTime;

class Helper
{
    /**
     * @param string $string
     *
     * @return void
     */
    public function debugMessage(string $string): void
    {
        $this->message("DEBUG", $string);
    }

    /**
     * @param string $prefix
     * @param string $string
     *
     * @return void
     */
    public function message(string $prefix, string $string): void
    {
        file_put_contents("php://stdout", "\n" . date_format(new DateTime(), 'Y-m-d H:i:s') . " $prefix $string");
    }

    /**
     * @param float $startHighResolutionTime
     * @param float $endHighResolutionTime
     * @return float
     */
    public function getHighResolutionTimeEtaInMilliseconds(float $startHighResolutionTime, float $endHighResolutionTime): float
    {
        $etaProgramHighResolutionTime = $endHighResolutionTime - $startHighResolutionTime;
        return $etaProgramHighResolutionTime / 1e+6; //nanoseconds to milliseconds
    }
}