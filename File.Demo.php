<?php

$SystemPath = __DIR__ . '/FileSystem/FileStream.php';
include($SystemPath);

$RequestedFile = $_GET['file'] ?? 'TestFile.mp4';
$Stream = new FileStream(__DIR__);
$Stream->SetFile($RequestedFile);
$Stream->ReadFile();

?>
