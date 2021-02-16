<?php
	requirePHPLib('form');

	if (!isUser($myUser)) {
		become403Page();
	}
	
	$upcoming_contest_name = null;
	$upcoming_contest_href = null;
	$rest_second = 1000000;
	function echoContest($contest) {
		global $myUser, $upcoming_contest_name, $upcoming_contest_href, $rest_second;
		
		$contest_name_link = <<<EOD
<a href="/contest/{$contest['id']}">{$contest['name']}</a>
EOD;
		genMoreContestInfo($contest);
		if ($contest['cur_progress'] == CONTEST_NOT_STARTED) {
			$cur_rest_second = $contest['start_time']->getTimestamp() - UOJTime::$time_now->getTimestamp();
			if ($cur_rest_second < $rest_second) {
				$upcoming_contest_name = $contest['name'];
				$upcoming_contest_href = "/contest/{$contest['id']}";
				$rest_second = $cur_rest_second;
			}
			if ($myUser != null && hasRegistered($myUser, $contest)) {
				$contest_name_link .= '<sup><a style="color:green">'.UOJLocale::get('contests::registered').'</a></sup>';
			} else {
				$contest_name_link .= '<sup><a style="color:red" href="/contest/'.$contest['id'].'/register">'.UOJLocale::get('contests::register').'</a></sup>';
			}
		} elseif ($contest['cur_progress'] == CONTEST_IN_PROGRESS) {
			$contest_name_link .= '<sup><a style="color:blue" href="/contest/'.$contest['id'].'">'.UOJLocale::get('contests::in progress').'</a></sup>';
		} elseif ($contest['cur_progress'] == CONTEST_PENDING_FINAL_TEST) {
			$contest_name_link .= '<sup><a style="color:blue" href="/contest/'.$contest['id'].'">'.UOJLocale::get('contests::pending final test').'</a></sup>';
		} elseif ($contest['cur_progress'] == CONTEST_TESTING) {
			$contest_name_link .= '<sup><a style="color:blue" href="/contest/'.$contest['id'].'">'.UOJLocale::get('contests::final testing').'</a></sup>';
		} elseif ($contest['cur_progress'] == CONTEST_FINISHED) {
			$contest_name_link .= '<sup><a style="color:grey" href="/contest/'.$contest['id'].'/standings">'.UOJLocale::get('contests::ended').'</a></sup>';
		}
		
		$last_hour = round($contest['last_min'] / 60, 2);
		
		$click_zan_block = getClickZanBlock('C', $contest['id'], $contest['zan']);
		echo '<tr>';
		echo '<td>', $contest_name_link, '</td>';
		echo '<td>', '<a href="'.HTML::timeanddate_url($contest['start_time'], array('duration' => $contest['last_min'])).'">'.$contest['start_time_str'].'</a>', '</td>';
		echo '<td>', UOJLocale::get('hours', $last_hour), '</td>';
		echo '<td>', '<a href="/contest/'.$contest['id'].'/registrants"><i class="fa fa-user"></i> &times;'.$contest['player_num'].'</a>', '</td>';
		echo '<td>', '<div class="text-left">'.$click_zan_block.'</div>', '</td>';
		echo '</tr>';
	}
?>
<?php echoUOJPageHeader(UOJLocale::get('contests')) ?>
<?php
	if (isSuperUser($myUser)) {
		echo '<div class="text-right">';
		echo '<a href="/contest/new" class="btn btn-primary" style="margin-top: .5rem">'.UOJLocale::get('contests::add new contest').'</a>';
		echo '</div>';
	}
?>
<h4><?= UOJLocale::get('contests::current or upcoming contests') ?></h4>
<?php
	$table_header = '';
	$table_header .= '<tr>';
	$table_header .= '<th>'.UOJLocale::get('contests::contest name').'</th>';
	$table_header .= '<th style="width:15em;">'.UOJLocale::get('contests::start time').'</th>';
	$table_header .= '<th style="width:100px;">'.UOJLocale::get('contests::duration').'</th>';
	$table_header .= '<th style="width:100px;">'.UOJLocale::get('contests::the number of registrants').'</th>';
	$table_header .= '<th style="width:180px;">'.UOJLocale::get('appraisal').'</th>';
	$table_header .= '</tr>';
	echoLongTable(array('*'), 'contests', "status != 'finished'", 'order by id desc', $table_header,
		"echoContest",
		array('page_len' => 40)
	);

	if ($rest_second <= 86400) {
		echo <<<EOD
<div class="text-center bot-buffer-lg">
<div class="text-warning">$upcoming_contest_name 倒计时</div>
<div id="contest-countdown"></div>
<script type="text/javascript">
$('#contest-countdown').countdown($rest_second, function() {
	if (confirm('$upcoming_contest_name 已经开始了。是否要跳转到比赛页面？')) {
		window.location.href = "$upcoming_contest_href";
	}
});
</script>
</div>
EOD;
	}
?>

<h4><?= UOJLocale::get('contests::ended contests') ?></h4>
<?php
	echoLongTable(array('*'), 'contests', "status = 'finished'", 'order by id desc', $table_header,
		"echoContest",
		array('page_len' => 100,
			'print_after_table' => function() {
				global $myUser;
			}
		)
	);
?>

<h4><?= UOJLocale::get('contests::contest statistics') ?></h4>

<?php
	$to_filter = isset($_GET['stat-end']) && isset($_GET['stat-begin']);

	if ($to_filter) {
		$form_begin = $_GET['stat-begin'];
		$form_end = $_GET['stat-end'];
	} else {
		$today = new DateTime();
		$default_begin = clone $today;
		$default_begin->sub(new DateInterval("P30D"));
		$form_begin = $default_begin->format('Y-m-d');
		$form_end = $today->format('Y-m-d');
	}
?>

<form class="form-horizontal uoj-form-compressed" id="contest-stat-date" target="_self" action="?">
	<div class="form-group">
		<label for="stat-end" class="col-sm-2 control-label" style="width: 7em">开始时间不晚于</label>
		<div class="col-sm-3">
			<input type="text" class="form-control" name="stat-end" value="<?= $form_end ?>"></input>
		</div>
	</div>
	<div class="form-group">
		<label for="stat-begin" class="col-sm-2 control-label">不早于</label>
		<div class="col-sm-3">
			<input type="text" class="form-control" name="stat-begin" value="<?= $form_begin ?>"></input>
		</div>
	</div>
	<div class="text-center">
		<button type="submit" class="mt-2 btn btn-secondary">筛选</button>
	</div>
</form>

<?php
	if ($to_filter) {
		$begin = DateTime::createFromFormat('Y-m-d', $_GET['stat-begin']);
		$end = DateTime::createFromFormat('Y-m-d', $_GET['stat-end']);
		
		if (!$begin) {
			echo "<p>起始时间 ({$_GET['stat-begin']}) 的格式不正确，请以类似 \"2020-10-1\" 的格式输入。</p>";
			$to_filter = false;
		}
		if (!$end) {
			echo "<p>结束时间 ({$_GET['stat-end']}) 的格式不正确，请以类似 \"2020-10-1\" 的格式输入。</p>";
			$to_filter = false;
		}
	}
?>

<?php if ($to_filter): ?>
<?php
	function echoContestForStat($contest) {
		$contest_name_link = <<<EOD
<a href="/contest/{$contest['id']}">{$contest['name']}</a>
EOD;
		genMoreContestInfo($contest);

		$last_hour = round($contest['last_min'] / 60, 2);

		echo '<tr>';
		echo '<td>', $contest_name_link, '</td>';
		echo '<td>', '<a href="'.HTML::timeanddate_url($contest['start_time'], array('duration' => $contest['last_min'])).'">'.$contest['start_time_str'].'</a>', '</td>';
		echo '<td>', UOJLocale::get('hours', $last_hour), '</td>';
		echo "<td><input type=\"checkbox\" value=\"{$contest['id']}\" class=\"contest-selector\"></td>";
		echo '</tr>';
	}

	$stat_table_header = '';
	$stat_table_header .= '<tr>';
	$stat_table_header .= '<th>'.UOJLocale::get('contests::contest name').'</th>';
	$stat_table_header .= '<th style="width:15em;">'.UOJLocale::get('contests::start time').'</th>';
	$stat_table_header .= '<th style="width:100px;">'.UOJLocale::get('contests::duration').'</th>';
	$stat_table_header .= '<th style="width:15em;">'.UOJLocale::get('contests::included in stat').'</th>';
	$stat_table_header .= '</tr>';

	$end->add(new DateInterval("P1D"));
	$begin_str = $begin->format('Y-m-d');
	$end_str = $end->format('Y-m-d');

	echoLongTable(array('*'), 'contests', "status = 'finished' and start_time >= '$begin_str' and start_time < '$end_str'", 'order by id desc', $stat_table_header,
		"echoContestForStat",
		array('page_len' => 100,
			'print_after_table' => function() {
				echo '<div class="text-right">';
				echo '<a href="#" class="btn btn-secondary" style="margin-right: 0.5em" id="button-select-all">'.UOJLocale::get('contests::select all').'</a>';
				echo '<a href="#" class="btn btn-secondary" style="margin-right: 0.5em" id="button-unselect-all">'.UOJLocale::get('contests::unselect all').'</a>';
				echo '<a href="#" class="btn btn-primary" id="button-stat">'.UOJLocale::get('contests::stat').'</a>';
				echo '</div>';
			}
		)
	);
?>

<script type="text/javascript">
$(function() {
	$('#button-select-all').click(function(e) {
		e.preventDefault();
		$('input.contest-selector').prop("checked", true);
	});
	$('#button-unselect-all').click(function(e) {
		e.preventDefault();
		$('input.contest-selector').prop("checked", false);
	});
	$('#button-stat').click(function(e) {
		e.preventDefault();
		let ids = [];
		$('input.contest-selector:checked').each(function(i, e) {
			ids.push($(e).val());
		});
		let ids_str = ids.join(",");
		window.location.href = "/contest_overall_rank?contest_ids=" + encodeURIComponent(ids_str);
	});
});
</script>

<?php endif ?>

<?php echoUOJPageFooter() ?>
