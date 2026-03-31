<?php
class Folder {
	
	public $file_limit = 1;
	public $array_extension = array();
	public $files = array();
	public $read_folder;
	public $page_number = 1;
	public $page_size = null;
	public $has_more = FALSE;
	
	public function __construct(){
	}
	
	public function reset_previous(){
		$this->files = array();
	}
	
	public function where($folder){
		$this->reset_previous();
		$this->read_folder = $folder;
	}
	
	public function get_files(){
		return $this->files;
	}
	
	public function not_empty(){
		return !empty($this->files);
	}
	
	public function limit($limit=false){
		if($limit !== false && is_numeric($limit) && (int)$limit > 0){
			$this->file_limit = (int)$limit;
			$this->disable_paging();
		}else{
			throw new InvalidArgumentException('You must set a numeric file limit greater than 0!');
		}
	}
	
	public function no_limit(){
		$this->file_limit = 0;
		$this->disable_paging();
	}
	
	public function page($page=1, $size=100){
		if(!is_numeric($page) || !is_numeric($size) || (int)$page < 1 || (int)$size < 1){
			throw new InvalidArgumentException('Page and page size must be numeric values greater than 0!');
		}
		$this->page_number = (int)$page;
		$this->page_size = (int)$size;
	}
	
	public function disable_paging(){
		$this->page_number = 1;
		$this->page_size = null;
	}
	
	public function has_more(){
		return $this->has_more;
	}
	
	public function read_page($page=1, $size=100){
		$this->page($page, $size);
		$this->read();
		return $this->files;
	}
	
	public function extension($extension){
		if(is_array($extension)){
			$normalized_extensions = array();
			foreach($extension as $item){
				$item = strtolower(trim((string)$item));
				$item = ltrim($item, '.');
				if($item !== ''){
					$normalized_extensions[] = $item;
				}
			}
			$this->array_extension = array_values(array_unique($normalized_extensions));
		}else{
			throw new InvalidArgumentException('The extension for files must be in array!');
		}
		
	}
	
	private function file_matches_extension($file_name){
		if(empty($this->array_extension)){
			return TRUE;
		}
		$extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
		return in_array($extension, $this->array_extension, TRUE);
	}
	
	private function should_include_entry($directory_path, $entry_name){
		if(in_array($entry_name, array('.', '..'), TRUE)){
			return FALSE;
		}
		
		$entry_path = $directory_path . '/' . $entry_name;
		
		// Subfolders are always visible.
		if(is_dir($entry_path)){
			return TRUE;
		}
		
		return $this->file_matches_extension($entry_name);
	}
	
	private function get_stable_filtered_entries($directory_path){
		$entries = array();
		
		if($handle = opendir($directory_path)){
			while(($entry_name = readdir($handle)) !== FALSE){
				if($this->should_include_entry($directory_path, $entry_name)){
					$entries[] = $entry_name;
				}
			}
			closedir($handle);
		}
		
		// Stable ordering: folders first, then files, each group sorted by name.
		usort($entries, function($left_entry, $right_entry) use ($directory_path){
			$left_is_dir = is_dir($directory_path . '/' . $left_entry);
			$right_is_dir = is_dir($directory_path . '/' . $right_entry);
			
			if($left_is_dir !== $right_is_dir){
				return $left_is_dir ? -1 : 1;
			}
			
			$case_insensitive = strcasecmp($left_entry, $right_entry);
			if($case_insensitive !== 0){
				return $case_insensitive;
			}
			return strcmp($left_entry, $right_entry);
		});
		return $entries;
	}
	
	public function read(){
		$this->reset_previous();
		$this->has_more = FALSE;
		
		$directory_path = realpath($this->read_folder);
		
		if($directory_path === FALSE || !is_dir($directory_path)){
			throw new RuntimeException('The path is not a folder,please set the correct path!');
		}
		
		$offset = 0;
		$max_items = 0;
		
		if($this->page_size !== null){
			$offset = ($this->page_number - 1) * $this->page_size;
			$max_items = $this->page_size;
		}else{
			$offset = 0;
			$max_items = ($this->file_limit > 0) ? $this->file_limit : 0;
		}
		
		$filtered_entries = $this->get_stable_filtered_entries($directory_path);
		$total_entries = count($filtered_entries);
		$selected_entries = array();
		
		if($max_items === 0){
			$selected_entries = array_slice($filtered_entries, $offset);
			$this->has_more = FALSE;
		}else{
			$selected_entries = array_slice($filtered_entries, $offset, $max_items);
			$this->has_more = (($offset + count($selected_entries)) < $total_entries);
		}
		
		$file_index = 1;
		
		foreach($selected_entries as $entry_name){
			$this->files[$file_index++] = $directory_path . '/' . $entry_name;
		}
	}
}
?>
