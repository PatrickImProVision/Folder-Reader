<?php
require_once __DIR__ . '/Folder.php';
require_once __DIR__ . '/Common.helpers.php';

$folder_input = isset($_GET['folder']) ? trim((string)$_GET['folder']) : __DIR__;
$extensions_input = isset($_GET['extensions']) ? trim((string)$_GET['extensions']) : '';
$mode = isset($_GET['mode']) ? (string)$_GET['mode'] : 'paging';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page_size = isset($_GET['page_size']) ? (int)$_GET['page_size'] : 100;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;

if($page < 1){
	$page = 1;
}
if($page_size < 1){
	$page_size = 100;
}
if($limit < 1){
	$limit = 100;
}
if(!in_array($mode, array('paging', 'limit', 'no_limit'), TRUE)){
	$mode = 'paging';
}

$status_message = '';
$error_message = '';
$results = array();
$has_more = FALSE;
$resolved_path = realpath($folder_input);

if($folder_input !== ''){
	if($resolved_path === FALSE || !is_dir($resolved_path)){
		$error_message = 'Invalid folder path. Please provide an existing directory.';
	}else{
		try{
			$folder_reader = new Folder();
			$folder_reader->where($resolved_path);

			if($extensions_input !== ''){
				$extensions = array_filter(array_map('trim', explode(',', $extensions_input)), 'strlen');
				$folder_reader->extension($extensions);
			}

			if($mode === 'paging'){
				$folder_reader->page($page, $page_size);
			}elseif($mode === 'limit'){
				$folder_reader->limit($limit);
			}else{
				$folder_reader->no_limit();
			}

			$folder_reader->read();
			$results = $folder_reader->get_files();
			$has_more = $folder_reader->has_more();
			$status_message = 'Read completed.';
		}catch(Throwable $e){
			$error_message = $e->getMessage();
		}
	}
}
