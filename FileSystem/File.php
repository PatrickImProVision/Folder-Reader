<?php

class File {

	private string $Path;
	private string $FileName;
	private string $FilePath;
	private $Stream = null;

	public function __construct(){
	}

	public function SetPath(string $DirPath) {
		$this->Path = rtrim($DirPath, DIRECTORY_SEPARATOR);
		$this->SetFilePath();
	}

	public function SetFile(string $FileName) {
		$this->FileName = $FileName;
		$this->SetFilePath();
	}

	public function SetFilePath(){
		if(isset($this->Path, $this->FileName)){
			$this->FilePath = $this->Path . DIRECTORY_SEPARATOR . $this->FileName;
		}
	}

	public function FileOpen(string $FileMode){

		if(empty($this->FilePath)){
			throw new RuntimeException('File path is not set. Please set both path and file name first.');
		}

		switch($FileMode){
			case 'r':
			case 'w':
			case 'a':
				$this->Stream = fopen($this->FilePath, $FileMode);
				break;
			default:
				throw new InvalidArgumentException('Unsupported file mode. Use r, w, or a.');
		}

		if($this->Stream === FALSE){
			$this->Stream = null;
			throw new RuntimeException('Unable to open file: ' . $this->FilePath);
		}

		return $this->Stream;
	}

	public function FileRead(int $ChunkSize = 8192, ?callable $ChunkHandler = null){
		if($ChunkSize < 1){
			throw new InvalidArgumentException('Chunk size must be greater than 0.');
		}

		if(!is_resource($this->Stream)){
			$this->FileOpen('r');
		}

		if($ChunkHandler !== null){
			while(!feof($this->Stream)){
				$Chunk = fread($this->Stream, $ChunkSize);
				if($Chunk === FALSE){
					throw new RuntimeException('Unable to read file: ' . $this->FilePath);
				}

				if($Chunk === ''){
					continue;
				}

				$ChunkHandler($Chunk);
			}

			return TRUE;
		}

		$Content = '';

		while(!feof($this->Stream)){
			$Chunk = fread($this->Stream, $ChunkSize);
			if($Chunk === FALSE){
				throw new RuntimeException('Unable to read file: ' . $this->FilePath);
			}
			$Content .= $Chunk;
		}

		return $Content;
	}

	public function FileSeek(int $Offset, int $Whence = SEEK_SET){
		if(!is_resource($this->Stream)){
			$this->FileOpen('r');
		}

		if(fseek($this->Stream, $Offset, $Whence) !== 0){
			throw new RuntimeException('Unable to seek file: ' . $this->FilePath);
		}

		return TRUE;
	}

	public function FileReadRange(int $Start, int $Length, int $ChunkSize = 8192, ?callable $ChunkHandler = null){
		if($Start < 0){
			throw new InvalidArgumentException('Start position must be 0 or greater.');
		}

		if($Length < 0){
			throw new InvalidArgumentException('Length must be 0 or greater.');
		}

		if($ChunkSize < 1){
			throw new InvalidArgumentException('Chunk size must be greater than 0.');
		}

		if(!is_resource($this->Stream)){
			$this->FileOpen('r');
		}

		$this->FileSeek($Start);
		$Remaining = $Length;

		if($ChunkHandler !== null){
			while($Remaining > 0 && !feof($this->Stream)){
				$ReadLength = min($ChunkSize, $Remaining);
				$Chunk = fread($this->Stream, $ReadLength);

				if($Chunk === FALSE){
					throw new RuntimeException('Unable to read file: ' . $this->FilePath);
				}

				if($Chunk === ''){
					break;
				}

				$Remaining -= strlen($Chunk);
				$ChunkHandler($Chunk);
			}

			return TRUE;
		}

		$Content = '';

		while($Remaining > 0 && !feof($this->Stream)){
			$ReadLength = min($ChunkSize, $Remaining);
			$Chunk = fread($this->Stream, $ReadLength);

			if($Chunk === FALSE){
				throw new RuntimeException('Unable to read file: ' . $this->FilePath);
			}

			if($Chunk === ''){
				break;
			}

			$Remaining -= strlen($Chunk);
			$Content .= $Chunk;
		}

		return $Content;
	}

	public function FileWrite(string $Content){
		$this->FileOpen('w');
		$Bytes = fwrite($this->Stream, $Content);

		if($Bytes === FALSE){
			throw new RuntimeException('Unable to write file: ' . $this->FilePath);
		}

		return $Bytes;
	}

	public function FileAppend(string $Content){
		$this->FileOpen('a');
		$Bytes = fwrite($this->Stream, $Content);

		if($Bytes === FALSE){
			throw new RuntimeException('Unable to append file: ' . $this->FilePath);
		}

		return $Bytes;
	}

	public function FileClose(){
		if(is_resource($this->Stream)){
			fclose($this->Stream);
		}

		$this->Stream = null;
	}

}

?>
