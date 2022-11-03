<?php

namespace PhpIpCam;

use Exception;

class SharedMemoryManager
{
    private int $key;

    /**
     * @var resource|false
     */
    private $shm;

    public function __construct()
    {
        $this->key = ftok(realpath(dirname(__FILE__) . '/..') . '/index.php', 'a');

        // Allocate or attach to 1 MByte of shared memory:
        $this->shm = shm_attach($this->key, 1024 * 1024, 0600);
        if ($this->shm === false) {
            throw new Exception('Failed to attach shared memory.');
        }
    }

    /**
     * @param string $data
     *
     * @return void
     * @throws Exception
     */
    function putDataToSharedMemory(string $data): void
    {
        // Inserts or updates a variable in shared memory:
        if (!shm_put_var($this->shm, 0, $data)) {
            throw new Exception('shm_put_var failed.');
        }
    }
}