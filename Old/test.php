<?php
// Using this script for testing
// Convert a pathname and a project identifier to a System V IPC key:
//$key = ftok(__FILE__, 'a');
$key = ftok(realpath(dirname(__FILE__)) . '/index.php', 'a');

$testData = "Some test data 2.";

echo "System V IPC key: $key<br>";

// Allocate or attach to 1 MByte of shared memory:
$shm = shm_attach($key, 1024 * 1024, 0600); // https://www.php.net/manual/en/function.shm-attach.php
if ($shm === false) {
    echo "Failed to attach shared memory.<br>";
    return;
}
echo "Allocated or attached to 1 MByte of shared memory.<br>";

if (shm_put_var($shm, 0, $testData)) {
    echo "shm_put_var added test data.<br>";
} else {
    echo "shm_put_var failed.<br>";
}

$shmData = shm_get_var($shm, 0);
if ($shmData === false) {
    echo "Failed to retrieve data from Shared memory.<br>";
} else {
    echo "Retrieved data from Shared memory: <b>$shmData</b><br>";
}

//// Removes shared memory from Unix systems:
//if ($shm) {
//    shm_remove($shm);
//}
//echo "Removed shared memory.<br>";
//
//$shmData2 = shm_get_var($shm, 0);
//if ($shmData === false) {
//    echo "Failed to retrieve shmData2 from Shared memory.<br>";
//} else {
//    echo "Retrieved shmData2 from Shared memory: $shmData2<br>";
//}

