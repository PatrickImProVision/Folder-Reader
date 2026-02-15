<?php
require_once __DIR__ . '/Folder.php';
require_once __DIR__ . '/Common.helpers.php';

$browse_root_path = realpath(__DIR__);
$script_path = detect_script_path();
$script_dir_uri = get_script_dir_uri($script_path);
$entry_uri = ($script_dir_uri === '') ? '/' : $script_dir_uri . '/';

$request_uri_path = '/';
if(isset($_SERVER['REQUEST_URI'])){
	$request_uri_path = (string)parse_url((string)$_SERVER['REQUEST_URI'], PHP_URL_PATH);
}
$request_uri_path = $request_uri_path !== '' ? normalize_uri_path($request_uri_path) : '/';
$request_relative_uri = request_uri_to_relative_uri($request_uri_path, $script_path);

$uri_input = null;
if(isset($_GET['uri']) && trim((string)$_GET['uri']) !== ''){
	$uri_input = (string)$_GET['uri'];
}elseif(isset($_GET['Uri']) && trim((string)$_GET['Uri']) !== ''){
	$uri_input = (string)$_GET['Uri'];
}

if($uri_input === null){
	$auto_query = $_GET;
	unset($auto_query['Uri']);
	$auto_query['uri'] = $request_relative_uri;
	$target = build_index_link($entry_uri, $request_relative_uri, $auto_query);
	$current_full = $request_uri_path;
	$current_query = isset($_SERVER['QUERY_STRING']) ? (string)$_SERVER['QUERY_STRING'] : '';
	if($current_query !== ''){
		$current_full .= '?' . $current_query;
	}
	if($target !== $current_full){
		header('Location: ' . $target, true, 302);
		exit;
	}
}

if($uri_input !== null){
	$requested_relative_uri = normalize_input_uri($uri_input, $script_path);
}else{
	$requested_relative_uri = $request_relative_uri;
}

$requested_absolute = relative_uri_to_absolute($requested_relative_uri, $browse_root_path);
$default_path = ($requested_absolute !== FALSE) ? $requested_absolute : $browse_root_path;

$path_input_raw = isset($_GET['path']) ? trim((string)$_GET['path']) : '';
$input_path = $path_input_raw !== '' ? $path_input_raw : $default_path;
$input_extensions = isset($_GET['extensions']) ? trim((string)$_GET['extensions']) : '';
$input_mode = isset($_GET['mode']) ? (string)$_GET['mode'] : 'paging';
$input_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$input_page_size = isset($_GET['page_size']) ? max(1, (int)$_GET['page_size']) : 60;
$input_limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 60;

if(!in_array($input_mode, array('paging', 'limit', 'no_limit'), TRUE)){
	$input_mode = 'paging';
}

$resolved_path = FALSE;
$error = '';
$status = 'Ready';
$results = array();
$has_more = FALSE;
$not_empty = FALSE;
$current_url_path = '/';
$breadcrumbs = array();

if($input_path !== ''){
	if($path_input_raw !== ''){
		$manual_path = realpath($path_input_raw);
		if($manual_path === FALSE || !is_dir($manual_path) || !path_starts_with($manual_path, $browse_root_path)){
			$error = 'Invalid path. Please provide a valid directory inside the URI root.';
		}else{
			$resolved_path = $manual_path;
		}
	}

	if($error === ''){
		if($resolved_path === FALSE){
			if($requested_absolute !== FALSE){
				$resolved_path = $requested_absolute;
			}else{
				$error = 'Invalid URI path. Please provide a valid folder URI inside the URI root.';
			}
		}
	}

	if($error === ''){
		$current_url_path = absolute_to_relative_uri($resolved_path, $browse_root_path);
		if($current_url_path === FALSE){
			$current_url_path = '/';
		}

		$segments = $current_url_path === '/' ? array() : explode('/', trim($current_url_path, '/'));
		$breadcrumbs[] = array('name' => 'Index Root', 'url' => '/');
		if(!empty($segments)){
			$progress = '';
			foreach($segments as $segment){
				$progress .= '/' . $segment;
				$breadcrumbs[] = array(
					'name' => rawurldecode($segment),
					'url' => $progress
				);
			}
		}

		try{
			$folder = new Folder();
			$folder->where($resolved_path);

			if($input_extensions !== ''){
				$extensions = array_filter(array_map('trim', explode(',', $input_extensions)), 'strlen');
				$folder->extension($extensions);
			}

			if($input_mode === 'paging'){
				$results = $folder->read_page($input_page, $input_page_size);
			}elseif($input_mode === 'limit'){
				$folder->limit($input_limit);
				$folder->read();
				$results = $folder->get_files();
			}else{
				$folder->no_limit();
				$folder->read();
				$results = $folder->get_files();
			}

			$has_more = $folder->has_more();
			$not_empty = $folder->not_empty();
			$status = 'Scan complete';
		}catch(Throwable $e){
			$error = $e->getMessage();
		}
	}
}
