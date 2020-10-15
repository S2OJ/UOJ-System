<?php
	$blogs = DB::selectAll("select blogs.id, title, poster, post_time from important_blogs, blogs where is_hidden = 0 and important_blogs.blog_id = blogs.id order by level desc, important_blogs.blog_id desc limit 5");
?>
<?php echoUOJPageHeader(UOJConfig::$data['profile']['oj-name-short']) ?>
<div class="card card-default">
	<div class="card-body">
		<div class="row">
			<div class="col-sm-12 col-md-9">
				<table class="table table-sm">
					<thead>
						<tr>
							<th style="width:60%"><?= UOJLocale::get('announcements') ?></th>
							<th style="width:20%"></th>
							<th style="width:20%"></th>
						</tr>
					</thead>
				  	<tbody>
					<?php $now_cnt = 0; ?>
					<?php foreach ($blogs as $blog): ?>
						<?php
							$now_cnt++;
							$new_tag = '';
							if ((time() - strtotime($blog['post_time'])) / 3600 / 24 <= 7) {
								$new_tag = '<sup style="color:red">&nbsp;new</sup>';
							}
						?>
						<tr>
							<td><a href="/blogs/<?= $blog['id'] ?>"><?= $blog['title'] ?></a><?= $new_tag ?></td>
							<td>by <?= getUserLink($blog['poster']) ?></td>
							<td><small><?= $blog['post_time'] ?></small></td>
						</tr>
					<?php endforeach ?>
					<?php for ($i = $now_cnt + 1; $i <= 5; $i++): ?>
						<tr><td colspan="233">&nbsp;</td></tr>
					<?php endfor ?>
						<tr><td class="text-right" colspan="233"><a href="/announcements"><?= UOJLocale::get('all the announcements') ?></a></td></tr>
					</tbody>
				</table>
			</div>
			<div class="col-xs-6 col-sm-4 col-md-3">
				<img class="media-object img-thumbnail" src="/images/blue.png" alt="Logo" style="width: 100%; height: 100%; padding: 10px; " />
				<img class="media-object" src="/images/logo.png" style="width: auto; height: 106%; position: absolute; bottom: 11px; left: 0;" />
			</div>
		</div>
	</div>
</div>

<?php
	if ($myUser) {
		if (isSuperUser($myUser)) {
			$from = "problems_solutions a inner join problems c on c.id = a.problem_id";
			$cond = "a.is_hidden = 1";
		} else {
			$from = "problems_solutions a inner join problems_permissions b on a.problem_id = b.problem_id inner join problems c on c.id = a.problem_id";
			$cond = "a.is_hidden = 1 and b.username = '{$myUser['username']}'";
		}
		
		$count = DB::selectCount("select count(*) from {$from} where {$cond}");

		if ($count > 0) {
			echo '<div class="row">';
			echo '<div class="col-sm-12 mt-4">';
			echo '<h3>待审核题解</h3>';

			$header = <<<EOD
	<tr>
		<th class="text-center" style="width:5em;">ID</th>
		<th>题目</th>
	</tr>
EOD;
			function echoProblem($problem) {
				echo '<tr>';
				echo '<td class="text-center">';
				echo '#', $problem['id'], '</td>';
				echo '<td class="text-left">';
				echo '<a href="/problem/', $problem['id'], '">', $problem['title'], '</a>';
				echo '</td>';
			}

			echoLongTable(array('distinct a.problem_id as id', 'c.title as title'), $from, $cond, 'order by id asc', $header, 'echoProblem', array(
				'table_classes' => array('table', 'table-hover', 'table-striped', 'table-bordered'),
				'page_len' => 100
			));

			echo '</div>';
			echo '</div>';
		}
	}
?>

<?php if (isUser($myUser)): ?>
<div class="row">
	<div class="col-sm-12 mt-4">
		<h3><?= UOJLocale::get('top rated') ?></h3>
		<?php echoRanklist(array('echo_full' => '', 'top10' => '')) ?>
		<div class="text-center">
			<a href="/ranklist"><?= UOJLocale::get('view all') ?></a>
		</div>
	</div>
</div>

<div class="row">
	<div class="col-sm-12 mt-4">
		<h3><?= UOJLocale::get('top solver') ?></h3>
		<?php echoRanklist(array('echo_full' => '', 'top10' => '', 'by_accepted' => '')) ?>
		<div class="text-center">
			<a href="/solverlist"><?= UOJLocale::get('view all') ?></a>
		</div>
	</div>
</div>
<?php endif ?>

<?php echoUOJPageFooter() ?>
