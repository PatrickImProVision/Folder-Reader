<?php
function h($value){
	return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function normalize_path($path){
	return str_replace('\\', '/', (string)$path);
}

function path_starts_with($path, $prefix){
	$path = normalize_path($path);
	$prefix = rtrim(normalize_path($prefix), '/');
	if($path === $prefix){
		return TRUE;
	}
	$prefix_with_sep = $prefix . '/';
	return strncasecmp($path, $prefix_with_sep, strlen($prefix_with_sep)) === 0;
}

function normalize_uri_path($path){
	$path = preg_replace('#/+#', '/', (string)$path);
	$path = '/' . ltrim($path, '/');
	return $path === '' ? '/' : $path;
}

function detect_script_path(){
	$candidates = array();
	if(isset($_SERVER['PHP_SELF'])){
		$candidates[] = (string)$_SERVER['PHP_SELF'];
	}
	if(isset($_SERVER['SCRIPT_NAME'])){
		$candidates[] = (string)$_SERVER['SCRIPT_NAME'];
	}
	foreach($candidates as $candidate){
		$normalized = normalize_uri_path($candidate);
		if(stripos($normalized, '.php') !== FALSE){
			return $normalized;
		}
	}
	return '/Index.php';
}

function get_script_dir_uri($script_name){
	$script_name = normalize_uri_path($script_name);
	$script_dir = str_replace('\\', '/', dirname($script_name));
	if($script_dir === '/' || $script_dir === '.'){
		return '';
	}
	return rtrim($script_dir, '/');
}

function uri_starts_with($uri, $prefix){
	$uri = normalize_uri_path($uri);
	$prefix = rtrim(normalize_uri_path($prefix), '/');
	if($prefix === ''){
		return TRUE;
	}
	if($uri === $prefix){
		return TRUE;
	}
	return strncasecmp($uri, $prefix . '/', strlen($prefix) + 1) === 0;
}

function request_uri_to_relative_uri($request_uri_path, $script_name){
	$request_uri_path = normalize_uri_path($request_uri_path);
	$script_name = normalize_uri_path($script_name);
	$script_dir_uri = get_script_dir_uri($script_name);
	if(strcasecmp($request_uri_path, $script_name) === 0){
		return '/';
	}
	if($script_dir_uri === ''){
		return $request_uri_path;
	}
	if($request_uri_path === $script_dir_uri || $request_uri_path === $script_dir_uri . '/'){
		return '/';
	}
	if(uri_starts_with($request_uri_path, $script_dir_uri)){
		$relative = substr($request_uri_path, strlen($script_dir_uri));
		return $relative === '' ? '/' : normalize_uri_path($relative);
	}
	return '/';
}

function normalize_input_uri($uri_path, $script_name){
	$uri_path = normalize_uri_path((string)$uri_path);
	$script_dir_uri = get_script_dir_uri($script_name);
	if($script_dir_uri !== '' && uri_starts_with($uri_path, $script_dir_uri)){
		$relative = substr($uri_path, strlen($script_dir_uri));
		return $relative === '' ? '/' : normalize_uri_path($relative);
	}
	return $uri_path;
}

function relative_uri_to_absolute($relative_uri, $browse_root_path){
	$relative_uri = normalize_uri_path($relative_uri);
	$relative = ltrim($relative_uri, '/');
	$absolute = realpath($browse_root_path . ($relative === '' ? '' : DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative)));
	if($absolute === FALSE || !is_dir($absolute)){
		return FALSE;
	}
	if(!path_starts_with($absolute, $browse_root_path)){
		return FALSE;
	}
	return $absolute;
}

function absolute_to_relative_uri($absolute_path, $browse_root_path){
	$absolute_real = realpath($absolute_path);
	if($absolute_real === FALSE || !path_starts_with($absolute_real, $browse_root_path)){
		return FALSE;
	}
	$root = rtrim(normalize_path($browse_root_path), '/');
	$full = normalize_path($absolute_real);
	$relative = ltrim(substr($full, strlen($root)), '/');
	if($relative === ''){
		return '/';
	}
	$segments = explode('/', $relative);
	$encoded = array_map('rawurlencode', $segments);
	return '/' . implode('/', $encoded);
}

function build_index_link($entry_uri, $uri_path, $query_params = array()){
	$params = $query_params;
	unset($params['Uri']);
	$params['uri'] = normalize_uri_path($uri_path);
	$separator = (strpos($entry_uri, '?') === FALSE) ? '?' : '&';
	return $entry_uri . $separator . http_build_query($params);
}
