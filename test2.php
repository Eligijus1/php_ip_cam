<?php
// Convert a pathname and a project identifier to a System V IPC key:
$key = ftok(realpath(dirname(__FILE__)) . '/index.php', 'a');

//echo "__FILE__: " . __FILE__ . "<br>";
//echo "__FILE__: " . realpath(dirname(__FILE__)) . "<br>";
echo "System V IPC key: $key<br>";

// Allocate or attach to 1 MByte of shared memory:
$shm = shm_attach($key, 1024 * 1024, 0600); // https://www.php.net/manual/en/function.shm-attach.php
if ($shm === false) {
    echo "Failed to attach shared memory.<br>";
    return;
} else {
    echo "Allocated or attached to 1 MByte of shared memory.<br>";
}

$shmData = shm_get_var($shm, 0);
if ($shmData === false) {
    echo "Failed to retrieve data from Shared memory.<br>";
} else {
    echo "Retrieved data from Shared memory: $shmData<br>";
}
