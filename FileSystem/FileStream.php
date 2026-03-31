<?php
$SystemPath = __DIR__ . '/File.php';
include($SystemPath);

class FileStream {

	private string $BaseDirectory;
	private string $FilePath = '';
	private File $FileReader;
	private int $ChunkSize = 1048576;

	public function __construct(?string $BaseDirectory = null){
		$ResolvedBaseDirectory = realpath($BaseDirectory ?? __DIR__);

		if($ResolvedBaseDirectory === FALSE){
			throw new RuntimeException('Base directory could not be resolved.');
		}

		$this->BaseDirectory = $ResolvedBaseDirectory;
		$this->FileReader = new File();
	}

	public function SetPath(string $Path): self {
		$ResolvedPath = realpath($Path);

		if($ResolvedPath === FALSE || !is_dir($ResolvedPath)){
			throw new InvalidArgumentException('The stream path must be an existing directory.');
		}

		$NormalizedBase = rtrim($this->BaseDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
		$NormalizedPath = rtrim($ResolvedPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

		if(strncmp($NormalizedPath, $NormalizedBase, strlen($NormalizedBase)) !== 0 && rtrim($ResolvedPath, DIRECTORY_SEPARATOR) !== $this->BaseDirectory){
			throw new InvalidArgumentException('The stream path must stay inside the base directory.');
		}

		$this->BaseDirectory = rtrim($ResolvedPath, DIRECTORY_SEPARATOR);
		return $this;
	}

	public function SetFile(string $FileName): self {
		$ResolvedFilePath = $this->ResolveRequestedFile($FileName);

		if($ResolvedFilePath === null){
			throw new RuntimeException('File not found.');
		}

		$this->FilePath = $ResolvedFilePath;
		$this->FileReader->SetPath(dirname($this->FilePath));
		$this->FileReader->SetFile(basename($this->FilePath));
		return $this;
	}

	public function SetChunkSize(int $ChunkSize): self {
		if($ChunkSize < 1){
			throw new InvalidArgumentException('Chunk size must be greater than 0.');
		}

		$this->ChunkSize = $ChunkSize;
		return $this;
	}

	public function ReadFile(): void {
		$RequestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

		if(!in_array($RequestMethod, array('GET', 'HEAD'), TRUE)){
			header('Allow: GET, HEAD');
			$this->SendErrorResponse(405, 'Method not allowed.');
		}

		if($this->FilePath === ''){
			$this->SendErrorResponse(404, 'File not found.');
		}

		$FileSize = filesize($this->FilePath);
		$LastModifiedTime = filemtime($this->FilePath);
		$LastModifiedHeader = gmdate('D, d M Y H:i:s', $LastModifiedTime) . ' GMT';
		$MimeType = $this->DetectMimeType($this->FilePath);
		$ContentDisposition = $this->SupportsInlineDisplay($MimeType, $this->FilePath) ? 'inline' : 'attachment';
		$SupportsRanges = $this->SupportsByteRanges($MimeType, $this->FilePath);
		$PrefersRanges = $this->PrefersRangeDelivery($MimeType, $this->FilePath);
		$DownloadName = $this->SanitizeDownloadName(basename($this->FilePath));
		$ETag = '"' . sha1($this->FilePath . '|' . $FileSize . '|' . $LastModifiedTime) . '"';

		$RangeStart = 0;
		$RangeEnd = $FileSize - 1;
		$HasPartialRange = FALSE;

		if($SupportsRanges && isset($_SERVER['HTTP_RANGE']) && $this->RangeAllowedByIfRange($ETag, $LastModifiedTime)){
			$ParsedRange = $this->ParseRangeHeader($_SERVER['HTTP_RANGE'], $FileSize);

			if($ParsedRange === null){
				http_response_code(416);
				header('Content-Range: bytes */' . $FileSize);
				exit;
			}

			$RangeStart = $ParsedRange[0];
			$RangeEnd = $ParsedRange[1];
			$HasPartialRange = TRUE;
		}

		if($this->HasFreshClientCache($ETag, $LastModifiedTime) && !$HasPartialRange){
			http_response_code(304);
			header('ETag: ' . $ETag);
			header('Last-Modified: ' . $LastModifiedHeader);
			header('Cache-Control: public, max-age=3600, must-revalidate');
			exit;
		}

		while(ob_get_level() > 0){
			ob_end_clean();
		}

		header('Content-Type: ' . $MimeType);
		header('Content-Disposition: ' . $ContentDisposition . '; filename="' . $DownloadName . '"');
		header('Cache-Control: public, max-age=3600, must-revalidate');
		header('ETag: ' . $ETag);
		header('Last-Modified: ' . $LastModifiedHeader);
		header('X-Content-Type-Options: nosniff');
		header('Content-Transfer-Encoding: binary');
		header('Vary: Range, If-Range, If-None-Match, If-Modified-Since');

		if($SupportsRanges){
			header('Accept-Ranges: bytes');
		}else{
			header('Accept-Ranges: none');
		}

		if($HasPartialRange){
			http_response_code(206);
			header('Content-Range: bytes ' . $RangeStart . '-' . $RangeEnd . '/' . $FileSize);
			header('Content-Length: ' . (($RangeEnd - $RangeStart) + 1));

			if($RequestMethod !== 'HEAD'){
				$this->FileReader->FileReadRange($RangeStart, ($RangeEnd - $RangeStart) + 1, $this->ChunkSize, function($Chunk){
					echo $Chunk;
					flush();
				});
			}
		}else{
			header('Content-Length: ' . $FileSize);

			if($PrefersRanges){
				header('X-Range-Recommended: bytes');
			}

			if($RequestMethod !== 'HEAD'){
				$this->FileReader->FileRead($this->ChunkSize, function($Chunk){
					echo $Chunk;
					flush();
				});
			}
		}

		$this->FileReader->FileClose();
	}

	private function DetectMimeType(string $FilePath): string {
		$FileInfo = finfo_open(FILEINFO_MIME_TYPE);

		if($FileInfo !== FALSE){
			$MimeType = finfo_file($FileInfo, $FilePath);
			finfo_close($FileInfo);

			if(is_string($MimeType) && $MimeType !== ''){
				return $MimeType;
			}
		}

		$Extension = strtolower(pathinfo($FilePath, PATHINFO_EXTENSION));
		$MimeTypes = array(
			'mp4' => 'video/mp4',
			'webm' => 'video/webm',
			'ogg' => 'video/ogg',
			'mp3' => 'audio/mpeg',
			'wav' => 'audio/wav',
			'flac' => 'audio/flac',
			'jpg' => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png' => 'image/png',
			'gif' => 'image/gif',
			'webp' => 'image/webp',
			'svg' => 'image/svg+xml',
			'pdf' => 'application/pdf',
			'txt' => 'text/plain',
			'csv' => 'text/csv',
			'json' => 'application/json',
			'xml' => 'application/xml',
			'html' => 'text/html',
			'htm' => 'text/html'
		);

		return $MimeTypes[$Extension] ?? 'application/octet-stream';
	}

	private function SupportsInlineDisplay(string $MimeType, string $FilePath): bool {
		$Extension = strtolower(pathinfo($FilePath, PATHINFO_EXTENSION));
		$InlineExtensions = array('mp4', 'webm', 'ogg', 'mp3', 'wav', 'flac', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf', 'txt', 'csv', 'json', 'xml', 'html', 'htm');

		if(in_array($Extension, $InlineExtensions, TRUE)){
			return TRUE;
		}

		foreach(array('video/', 'audio/', 'image/', 'text/') as $Prefix){
			if(strpos($MimeType, $Prefix) === 0){
				return TRUE;
			}
		}

		return in_array($MimeType, array('application/pdf', 'application/json', 'application/xml'), TRUE);
	}

	private function SupportsByteRanges(string $MimeType, string $FilePath): bool {
		$Extension = strtolower(pathinfo($FilePath, PATHINFO_EXTENSION));
		$RangeExtensions = array('mp4', 'webm', 'ogg', 'mp3', 'wav', 'flac', 'pdf');

		if(in_array($Extension, $RangeExtensions, TRUE)){
			return TRUE;
		}

		foreach(array('video/', 'audio/') as $Prefix){
			if(strpos($MimeType, $Prefix) === 0){
				return TRUE;
			}
		}

		return ($MimeType === 'application/pdf');
	}

	private function PrefersRangeDelivery(string $MimeType, string $FilePath): bool {
		return $this->SupportsByteRanges($MimeType, $FilePath);
	}

	private function SanitizeDownloadName(string $FileName): string {
		$FileName = str_replace(array("\r", "\n", '"'), '', $FileName);
		return ($FileName === '') ? 'download' : $FileName;
	}

	private function ResolveRequestedFile(string $RequestedFile): ?string {
		$RequestedFile = trim(str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $RequestedFile));

		if($RequestedFile === '' || strpos($RequestedFile, '..') !== FALSE){
			return null;
		}

		$CandidatePath = $this->BaseDirectory . DIRECTORY_SEPARATOR . ltrim($RequestedFile, DIRECTORY_SEPARATOR);
		$ResolvedPath = realpath($CandidatePath);

		if($ResolvedPath === FALSE || !is_file($ResolvedPath)){
			return null;
		}

		$NormalizedBase = rtrim($this->BaseDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

		if(strncmp($ResolvedPath, $NormalizedBase, strlen($NormalizedBase)) !== 0){
			return null;
		}

		return $ResolvedPath;
	}

	private function ParseRangeHeader(string $RangeHeader, int $FileSize): ?array {
		if(stripos($RangeHeader, ',') !== FALSE){
			return null;
		}

		if(!preg_match('/^bytes=(\d*)-(\d*)$/', trim($RangeHeader), $Matches)){
			return null;
		}

		$StartText = $Matches[1];
		$EndText = $Matches[2];

		if($StartText === '' && $EndText === ''){
			return null;
		}

		if($StartText === ''){
			$SuffixLength = (int)$EndText;

			if($SuffixLength <= 0){
				return null;
			}

			return array(max(0, $FileSize - $SuffixLength), $FileSize - 1);
		}

		$Start = (int)$StartText;
		$End = ($EndText === '') ? ($FileSize - 1) : (int)$EndText;

		if($Start < 0 || $End < $Start || $Start >= $FileSize){
			return null;
		}

		return array($Start, min($End, $FileSize - 1));
	}

	private function ETagMatches(string $HeaderValue, string $ETag): bool {
		foreach(array_map('trim', explode(',', $HeaderValue)) as $Value){
			if($Value === '*' || $Value === $ETag){
				return TRUE;
			}
		}

		return FALSE;
	}

	private function HasFreshClientCache(string $ETag, int $LastModifiedTime): bool {
		if(isset($_SERVER['HTTP_IF_NONE_MATCH']) && $this->ETagMatches($_SERVER['HTTP_IF_NONE_MATCH'], $ETag)){
			return TRUE;
		}

		if(isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])){
			$IfModifiedSince = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);

			if($IfModifiedSince !== FALSE && $IfModifiedSince >= $LastModifiedTime){
				return TRUE;
			}
		}

		return FALSE;
	}

	private function RangeAllowedByIfRange(string $ETag, int $LastModifiedTime): bool {
		if(!isset($_SERVER['HTTP_IF_RANGE'])){
			return TRUE;
		}

		$IfRange = trim($_SERVER['HTTP_IF_RANGE']);

		if($IfRange === $ETag){
			return TRUE;
		}

		$IfRangeTime = strtotime($IfRange);
		return ($IfRangeTime !== FALSE && $IfRangeTime >= $LastModifiedTime);
	}

	private function SendErrorResponse(int $StatusCode, string $Message): void {
		http_response_code($StatusCode);
		header('Content-Type: text/plain; charset=UTF-8');
		header('X-Content-Type-Options: nosniff');
		exit($Message);
	}
}

?>