<?php require_once __DIR__ . '/Test.logic.php'; ?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Folder Reader Test</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<style>
		body { background: #f6f8fc; }
		.path-cell { font-family: Consolas, monospace; font-size: 12px; word-break: break-all; }
		.table-wrap { max-height: 60vh; overflow: auto; }
	</style>
</head>
<body>
	<div class="container-lg py-4">
		<div class="card shadow-sm border-0 mb-3">
			<div class="card-body">
				<h1 class="h4 mb-1">Folder Reader Test</h1>
				<div class="text-muted">Simple visual test for: extensions, paging, limit, and no-limit modes.</div>
			</div>
		</div>

		<div class="card shadow-sm mb-3">
			<div class="card-body">
				<form method="get" class="row g-2 align-items-end">
					<div class="col-md-4">
						<label class="form-label small fw-semibold">Folder</label>
						<input class="form-control form-control-sm" name="folder" value="<?php echo h($folder_input); ?>">
					</div>
					<div class="col-md-3">
						<label class="form-label small fw-semibold">Extensions</label>
						<input class="form-control form-control-sm" name="extensions" value="<?php echo h($extensions_input); ?>" placeholder="php,txt">
					</div>
					<div class="col-md-2">
						<label class="form-label small fw-semibold">Mode</label>
						<select class="form-select form-select-sm" name="mode">
							<option value="paging" <?php echo $mode === 'paging' ? 'selected' : ''; ?>>Paging</option>
							<option value="limit" <?php echo $mode === 'limit' ? 'selected' : ''; ?>>Limit</option>
							<option value="no_limit" <?php echo $mode === 'no_limit' ? 'selected' : ''; ?>>No Limit</option>
						</select>
					</div>
					<div class="col-md-1">
						<label class="form-label small fw-semibold">Page</label>
						<input class="form-control form-control-sm" type="number" min="1" name="page" value="<?php echo h($page); ?>">
					</div>
					<div class="col-md-1">
						<label class="form-label small fw-semibold">Size</label>
						<input class="form-control form-control-sm" type="number" min="1" name="page_size" value="<?php echo h($page_size); ?>">
					</div>
					<div class="col-md-1">
						<label class="form-label small fw-semibold">Limit</label>
						<input class="form-control form-control-sm" type="number" min="1" name="limit" value="<?php echo h($limit); ?>">
					</div>
					<div class="col-12">
						<button class="btn btn-primary btn-sm" type="submit">Run Test</button>
					</div>
				</form>
			</div>
		</div>

		<div class="card shadow-sm mb-3">
			<div class="card-body">
				<?php if($error_message !== ''): ?>
					<div class="text-danger fw-semibold"><?php echo h($error_message); ?></div>
				<?php else: ?>
					<div class="text-success fw-semibold"><?php echo h($status_message); ?></div>
				<?php endif; ?>
				<div class="small text-muted mt-1">
					Resolved Path: <?php echo h($resolved_path !== FALSE ? $resolved_path : 'N/A'); ?> |
					Items: <?php echo h(count($results)); ?> |
					Has More: <?php echo $has_more ? 'Yes' : 'No'; ?>
				</div>
			</div>
		</div>

		<div class="card shadow-sm">
			<div class="card-body">
				<div class="table-wrap border rounded">
					<table class="table table-sm table-striped mb-0">
						<thead class="table-light sticky-top">
							<tr><th>#</th><th>Type</th><th>Name</th><th>Path</th><th>Size</th></tr>
						</thead>
						<tbody>
							<?php if(empty($results)): ?>
								<tr><td colspan="5" class="text-muted">No data to display.</td></tr>
							<?php else: ?>
								<?php foreach($results as $index => $path): ?>
									<?php $is_dir = is_dir($path); $name = basename($path); $size = $is_dir ? '-' : (is_file($path) ? (string)filesize($path) : '-'); ?>
									<tr>
										<td><?php echo h($index); ?></td>
										<td><span class="badge <?php echo $is_dir ? 'text-bg-primary' : 'text-bg-secondary'; ?>"><?php echo $is_dir ? 'Folder' : 'File'; ?></span></td>
										<td><?php echo h($name); ?></td>
										<td class="path-cell"><?php echo h($path); ?></td>
										<td><?php echo h($size); ?></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>

				<?php if($mode === 'paging' && $folder_input !== '' && $error_message === ''): ?>
					<?php
						$query_base = $_GET;
						$query_base['mode'] = 'paging';
						$prev_page = max(1, $page - 1);
						$next_page = $page + 1;
						$query_prev = $query_base;
						$query_next = $query_base;
						$query_prev['page'] = $prev_page;
						$query_next['page'] = $next_page;
					?>
					<div class="d-flex gap-2 align-items-center mt-2">
						<a class="btn btn-outline-secondary btn-sm" href="?<?php echo h(http_build_query($query_prev)); ?>">Previous</a>
						<span class="badge text-bg-light border">Page <?php echo h($page); ?></span>
						<?php if($has_more): ?>
							<a class="btn btn-outline-primary btn-sm" href="?<?php echo h(http_build_query($query_next)); ?>">Next</a>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
