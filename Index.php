<?php require_once __DIR__ . '/Index.logic.php'; ?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Folder Reader Studio</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<style>
		body { background: #f5f7fb; }
		.hero { background: linear-gradient(135deg, #0b4f8a, #226cb0); color: #fff; }
		.path-cell { font-family: Consolas, monospace; word-break: break-all; font-size: 12px; }
		.table-wrap { max-height: 62vh; overflow: auto; }
	</style>
</head>
<body>
	<div class="container-xl py-4">
		<div class="card shadow-sm border-0 hero mb-3">
			<div class="card-body">
				<h1 class="h3 mb-1">Folder Reader Studio</h1>
				<div class="opacity-75">Visual test page for all class features: URI navigation, extension filtering, limit/no-limit, paging.</div>
			</div>
		</div>

		<div class="row g-3">
			<div class="col-lg-4">
				<div class="card shadow-sm">
					<div class="card-body">
						<h2 class="h6">Controls</h2>
						<form method="get" class="vstack gap-2">
							<input type="hidden" name="uri" value="<?php echo h($current_url_path); ?>">
							<div>
								<label class="form-label small text-uppercase fw-semibold">Path (`where`)</label>
								<input class="form-control form-control-sm" name="path" value="<?php echo h($input_path); ?>" placeholder="C:\folder\path">
							</div>
							<div>
								<label class="form-label small text-uppercase fw-semibold">Extensions (`extension`)</label>
								<input class="form-control form-control-sm" name="extensions" value="<?php echo h($input_extensions); ?>" placeholder="php,txt,jpg">
								<div class="form-text">Empty means all files. Folders are always included.</div>
							</div>
							<div>
								<label class="form-label small text-uppercase fw-semibold">Mode</label>
								<select class="form-select form-select-sm" name="mode">
									<option value="paging" <?php echo $input_mode === 'paging' ? 'selected' : ''; ?>>Paging (`read_page`)</option>
									<option value="limit" <?php echo $input_mode === 'limit' ? 'selected' : ''; ?>>Limit (`limit` + `read`)</option>
									<option value="no_limit" <?php echo $input_mode === 'no_limit' ? 'selected' : ''; ?>>No Limit (`no_limit` + `read`)</option>
								</select>
							</div>
							<div class="row g-2">
								<div class="col-4">
									<label class="form-label small">Page</label>
									<input class="form-control form-control-sm" type="number" min="1" name="page" value="<?php echo h($input_page); ?>">
								</div>
								<div class="col-4">
									<label class="form-label small">Page Size</label>
									<input class="form-control form-control-sm" type="number" min="1" name="page_size" value="<?php echo h($input_page_size); ?>">
								</div>
								<div class="col-4">
									<label class="form-label small">Limit</label>
									<input class="form-control form-control-sm" type="number" min="1" name="limit" value="<?php echo h($input_limit); ?>">
								</div>
							</div>
							<button class="btn btn-primary btn-sm mt-1" type="submit">Run</button>
						</form>
					</div>
				</div>
			</div>

			<div class="col-lg-8">
				<div class="card shadow-sm mb-3">
					<div class="card-body">
						<h2 class="h6">Status</h2>
						<div class="row g-2">
							<div class="col-md-4"><div class="border rounded p-2 small"><div class="text-muted text-uppercase">Status</div><div class="<?php echo $error === '' ? 'text-success' : 'text-danger'; ?> fw-semibold"><?php echo h($error === '' ? $status : $error); ?></div></div></div>
							<div class="col-md-4"><div class="border rounded p-2 small"><div class="text-muted text-uppercase">Current URL Path</div><div class="fw-semibold"><?php echo h($current_url_path); ?></div></div></div>
							<div class="col-md-4"><div class="border rounded p-2 small"><div class="text-muted text-uppercase">Resolved Path</div><div class="fw-semibold"><?php echo h($resolved_path !== FALSE ? $resolved_path : 'N/A'); ?></div></div></div>
							<div class="col-md-4"><div class="border rounded p-2 small"><div class="text-muted text-uppercase">Items</div><div class="fw-semibold"><?php echo h(count($results)); ?></div></div></div>
							<div class="col-md-4"><div class="border rounded p-2 small"><div class="text-muted text-uppercase">`not_empty`</div><div class="fw-semibold"><?php echo $not_empty ? 'TRUE' : 'FALSE'; ?></div></div></div>
							<div class="col-md-4"><div class="border rounded p-2 small"><div class="text-muted text-uppercase">`has_more`</div><div class="fw-semibold"><?php echo $has_more ? 'TRUE' : 'FALSE'; ?></div></div></div>
						</div>
					</div>
				</div>

				<div class="card shadow-sm">
					<div class="card-body">
						<h2 class="h6">Results</h2>

						<?php if(!empty($breadcrumbs)): ?>
							<nav class="mb-2">
								<?php foreach($breadcrumbs as $i => $crumb): ?>
									<?php $is_last = ($i === count($breadcrumbs) - 1); $crumb_query = $_GET; unset($crumb_query['path']); $crumb_query['page'] = 1; ?>
									<?php if($is_last): ?>
										<span class="badge text-bg-primary"><?php echo h($crumb['name']); ?></span>
									<?php else: ?>
										<a class="badge text-bg-light text-decoration-none border" href="<?php echo h(build_index_link($entry_uri, $crumb['url'], $crumb_query)); ?>"><?php echo h($crumb['name']); ?></a>
									<?php endif; ?>
								<?php endforeach; ?>
							</nav>
						<?php endif; ?>

						<div class="table-wrap border rounded">
							<table class="table table-sm table-striped align-middle mb-0">
								<thead class="table-light sticky-top">
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
												$entry_url_path = $is_folder ? absolute_to_relative_uri($entry_path, $browse_root_path) : FALSE;
												$entry_query = $_GET;
												unset($entry_query['path']);
												$entry_query['page'] = 1;
											?>
											<tr>
												<td><?php echo h($index); ?></td>
												<td><span class="badge <?php echo $is_folder ? 'text-bg-primary' : 'text-bg-secondary'; ?>"><?php echo $is_folder ? 'Folder' : 'File'; ?></span></td>
												<td>
													<?php if($is_folder && $entry_url_path !== FALSE): ?>
														<a href="<?php echo h(build_index_link($entry_uri, $entry_url_path, $entry_query)); ?>"><?php echo h($entry_name); ?></a>
													<?php else: ?>
														<?php echo h($entry_name); ?>
													<?php endif; ?>
												</td>
												<td class="path-cell"><?php echo h($entry_path); ?></td>
												<td><?php echo h($entry_size); ?></td>
											</tr>
										<?php endforeach; ?>
									<?php endif; ?>
								</tbody>
							</table>
						</div>

						<?php if($input_mode === 'paging' && $error === ''): ?>
							<?php
								$query = $_GET;
								unset($query['Uri']);
								$query['mode'] = 'paging';
								$prev_query = $query;
								$next_query = $query;
								$prev_query['page'] = max(1, $input_page - 1);
								$next_query['page'] = $input_page + 1;
							?>
							<div class="d-flex gap-2 align-items-center mt-2">
								<a class="btn btn-outline-secondary btn-sm" href="<?php echo h(build_index_link($entry_uri, $current_url_path, $prev_query)); ?>">Previous</a>
								<span class="badge text-bg-light border">Page <?php echo h($input_page); ?></span>
								<?php if($has_more): ?>
									<a class="btn btn-outline-primary btn-sm" href="<?php echo h(build_index_link($entry_uri, $current_url_path, $next_query)); ?>">Next</a>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
	</div>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
