# Folder Reader
Read directory entries (folders + files) with extension filters, limits, and paging.

## Current behavior
1. Returns both subfolders and files.
2. Extension filter applies to files only. Folders are always included.
3. Ordering is stable:
   - folders first
   - files second
   - name order inside each group
4. Paging and limit are mutually exclusive modes:
   - `page(...)` enables paging
   - `limit(...)` / `no_limit()` disables paging
5. Invalid input/path throws exceptions (`InvalidArgumentException` or `RuntimeException`).

## Methods
1. `where($folder)`
   - Sets the folder path to scan and clears previous results.
2. `extension(array $extension)`
   - Sets allowed file extensions.
   - Normalizes input (case-insensitive, strips leading dots).
3. `limit($limit)`
   - Sets max returned entries (`int > 0`).
4. `no_limit()`
   - Disables limit.
5. `page($page, $size)`
   - Enables paging (`page >= 1`, `size >= 1`).
6. `read_page($page, $size)`
   - Shortcut: set page + read + return files.
7. `read()`
   - Executes scan using current settings.
8. `get_files()`
   - Returns array of absolute paths.
9. `not_empty()`
   - `true` if current result set has entries.
10. `has_more()`
    - `true` when additional entries exist beyond current page/limit.
11. `disable_paging()`
    - Resets paging mode.

## Quick usage
```php
$folder = new Folder();
$folder->where($path);
$folder->extension(array('php', 'txt')); // optional
$folder->page(1, 100);                   // or: limit(100), or: no_limit()
$folder->read();

$files = $folder->get_files();
$hasMore = $folder->has_more();
```

## Examples
### Limit mode
```php
$folder->where($path);
$folder->limit(50);
$folder->read();
$items = $folder->get_files();
```

### Paging mode
```php
$folder->where($path);
$items = $folder->read_page(2, 200); // page 2, 200 items
$hasMore = $folder->has_more();
```

### No limit mode
```php
$folder->where($path);
$folder->no_limit();
$folder->read();
$items = $folder->get_files();
```

## Notes
1. Results are absolute filesystem paths.
2. Index/Test pages in this project are visual test runners for the class.
