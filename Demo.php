<?php
require_once __DIR__ . '/Folder.php';

function h($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$default_path = __DIR__;
$input_path = isset($_GET['path']) ? trim((string)$_GET['path']) : $default_path;
$input_extensions = isset($_GET['extensions']) ? trim((string)$_GET['extensions']) : '';
$input_mode = isset($_GET['mode']) ? (string)$_GET['mode'] : 'paging';
$input_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$input_page_size = isset($_GET['page_size']) ? max(1, (int)$_GET['page_size']) : 50;
$input_limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 50;

if(!in_array($input_mode, array('paging', 'limit', 'no_limit'), TRUE)){
    $input_mode = 'paging';
}

$error = '';
$status = 'Ready';
$results = array();
$has_more = FALSE;
$not_empty = FALSE;

if($input_path !== ''){
    try{
        $folder = new Folder();
        $folder->where($input_path);

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
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Folder Demo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f6fb; }
        .path-cell { font-family: Consolas, monospace; word-break: break-all; font-size: 12px; }
    </style>
</head>
<body>
<div class="container py-4">
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <h1 class="h4 mb-3">Folder.php Demo</h1>
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="form-label">Path</label>
                    <input class="form-control" name="path" value="<?php echo h($input_path); ?>" placeholder="C:\\Xampp\\htdocs">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Extensions (comma separated)</label>
                    <input class="form-control" name="extensions" value="<?php echo h($input_extensions); ?>" placeholder="php,txt,jpg">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Mode</label>
                    <select class="form-select" name="mode">
                        <option value="paging" <?php echo $input_mode === 'paging' ? 'selected' : ''; ?>>Paging</option>
                        <option value="limit" <?php echo $input_mode === 'limit' ? 'selected' : ''; ?>>Limit</option>
                        <option value="no_limit" <?php echo $input_mode === 'no_limit' ? 'selected' : ''; ?>>No Limit</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Page</label>
                    <input class="form-control" type="number" min="1" name="page" value="<?php echo h($input_page); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Page Size</label>
                    <input class="form-control" type="number" min="1" name="page_size" value="<?php echo h($input_page_size); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Limit</label>
                    <input class="form-control" type="number" min="1" name="limit" value="<?php echo h($input_limit); ?>">
                </div>
                <div class="col-12">
                    <button class="btn btn-primary" type="submit">Run</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div><strong>Status:</strong> <?php echo h($error === '' ? $status : $error); ?></div>
            <div><strong>Items:</strong> <?php echo h(count($results)); ?></div>
            <div><strong>not_empty:</strong> <?php echo $not_empty ? 'TRUE' : 'FALSE'; ?></div>
            <div><strong>has_more:</strong> <?php echo $has_more ? 'TRUE' : 'FALSE'; ?></div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <h2 class="h6">Results</h2>
            <div class="table-responsive">
                <table class="table table-sm table-striped">
                    <thead>
                    <tr><th>#</th><th>Type</th><th>Name</th><th>Path</th><th>Size</th></tr>
                    </thead>
                    <tbody>
                    <?php if(empty($results)): ?>
                        <tr><td colspan="5" class="text-muted">No items returned.</td></tr>
                    <?php else: ?>
                        <?php foreach($results as $index => $entry_path): ?>
                            <?php
                                $is_folder = is_dir($entry_path);
                                $entry_name = basename($entry_path);
                                $entry_size = $is_folder ? '-' : (is_file($entry_path) ? (string)filesize($entry_path) : '-');
                            ?>
                            <tr>
                                <td><?php echo h($index); ?></td>
                                <td><?php echo $is_folder ? 'Folder' : 'File'; ?></td>
                                <td><?php echo h($entry_name); ?></td>
                                <td class="path-cell"><?php echo h($entry_path); ?></td>
                                <td><?php echo h($entry_size); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if($input_mode === 'paging' && $error === ''): ?>
                <?php $query = $_GET; $query['mode'] = 'paging'; ?>
                <?php $prev_query = $query; $next_query = $query; ?>
                <?php $prev_query['page'] = max(1, $input_page - 1); ?>
                <?php $next_query['page'] = $input_page + 1; ?>
                <div class="d-flex gap-2">
                    <a class="btn btn-outline-secondary btn-sm" href="?<?php echo h(http_build_query($prev_query)); ?>">Previous</a>
                    <span class="badge text-bg-light border align-self-center">Page <?php echo h($input_page); ?></span>
                    <?php if($has_more): ?>
                        <a class="btn btn-outline-primary btn-sm" href="?<?php echo h(http_build_query($next_query)); ?>">Next</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
