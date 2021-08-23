<?php
	requirePHPLib('form');
	requirePHPLib('judger');

	if (!isUser($myUser)) {
		become403Page();
	}
 
	if (!validateUInt($_GET['id']) || !($problem = queryProblemBrief($_GET['id']))) {
		become404Page();
	}

    // var_dump($problem);
?>









<?php
function comment_form_printHTML() {
$pid = $_GET['id'];
// echo "!@#"; exit(0);
 echo <<<EOD


<!--<form action="/problem/$pid" method="post" class="form-horizontal" id="form-comment" enctype="multipart/form-data">-->
<div id="div-comment" class="form-group">
	<label for="input-comment" class="control-label">内容</label>
	<textarea class="form-control" name="comment" id="input-comment" style="overflow: hidden; overflow-wrap: break-word; resize: none; height: 54.1px;"></textarea>
	<span class="help-block" id="help-comment"></span>
</div>
<div class="text-center"><button type="submit" id="button-submit-comment" name="submit-comment" value="comment" class="mt-2 btn btn-secondary">提交</button></div>
<script>
$(document).ready(function() {
    $("#button-submit-comment").click(function() {
        $.ajax({
            url: "/problem/$pid",
            type: "POST",
            data: {
                "comment": $("#input-comment").val()
            },
            success: function(data) {
                // console.log(data);
                location.replace(location.href.replace(/#/,"").replace(/\?.*/,"") + "?tj=0");
            }
        });
    });
});
</script>
<!--</form>-->
EOD;
};
function comment_form_handle() {
		global $myUser, $comment_form, $problem;
    if(1) :
    
        $comment = '';
        $r_id = 0;
        if(isset($_POST['comment'])) {
            $comment = HTML::escape($_POST['comment']);
        } else if(isset($_POST['reply_comment'])) {
            $comment = HTML::escape($_POST['reply_comment']);
            $r_id = (int) ($_POST['reply_id']);
        } else {
            exit(0);
        }
		
		
		list($comment, $referrers) = uojHandleAtSign($comment, "/problem/{$problem['id']}");
		
		$esc_comment = DB::escape($comment);
		
        // echo "insert into nek_cmt (poster, cid, content, reply_id, post_time, zan) values ('{$myUser['username']}', '{$problem['id']}', '$esc_comment', 0, now(), 0)";
        
        DB::insert("insert into nek_cmt (poster, cid, content, reply_id, post_time, zan) values ('{$myUser['username']}', '{$problem['id']}', '$esc_comment', $r_id, now(), 0)");
		
        $comment_id = DB::insert_id();
		
		$rank = DB::selectCount("select count(*) from nek_cmt where cid = {$problem['id']} and reply_id = 0 and id < {$comment_id}");
		$page = floor($rank / 20) + 1;
		
		$uri = getLongTablePageUri($page) . '#' . "comment-{$comment_id}";
		
		foreach ($referrers as $referrer) {
			$content = '有人在题解 ' . $problem['title'] . ' 的评论里提到你：<a href="' . $uri . '">点击此处查看</a>';
			sendSystemMsg($referrer, '有人提到你', $content);
		}
		
    /*
		if ($blog['poster'] !== $myUser['username']) {
			$content = '有人回复了您的博客 ' . $blog['title'] . ' ：<a href="' . $uri . '">点击此处查看</a>';
			sendSystemMsg($blog['poster'], '博客新回复通知', $content);
		}
    */
		
		// $comment_form->succ_href = getLongTablePageRawUri($page);
    
        // unset($_POST['comment']);
        // unset($_POST['reply_comment']);
    
    endif;
	};
?>










<?php

	
	$problem_content = queryProblemContent($problem['id']);
	
	$contest = validateUInt($_GET['contest_id']) ? queryContest($_GET['contest_id']) : null;
	if ($contest != null) {
		genMoreContestInfo($contest);
		$problem_rank = queryContestProblemRank($contest, $problem);
		if ($problem_rank == null) {
			become404Page();
		} else {
			$problem_letter = chr(ord('A') + $problem_rank - 1);
		}
	}
	
	$is_in_contest = false;
	$ban_in_contest = false;
	if ($contest != null) {
		if (!hasContestPermission($myUser, $contest)) {
			if ($contest['cur_progress'] == CONTEST_NOT_STARTED) {
				become404Page();
			} elseif ($contest['cur_progress'] == CONTEST_IN_PROGRESS) {
				if ($myUser == null || !hasRegistered($myUser, $contest)) {
					becomeMsgPage("<h1>比赛正在进行中</h1><p>很遗憾，您尚未报名。比赛结束后再来看吧～</p>");
				} else {
					$is_in_contest = true;
					DB::update("update contests_registrants set has_participated = 1 where username = '{$myUser['username']}' and contest_id = {$contest['id']}");
				}
			} else {
				$ban_in_contest = !isProblemVisibleToUser($problem, $myUser);
			}
		}
	} else {
		if (!isProblemVisibleToUser($problem, $myUser)) {
			become404Page();
		}
	}
 
 
   // 终于判断完了，下面应该是都有访问权限的
   
      if(isset($_POST["comment"]) || isset($_POST["reply_comment"])) {
        comment_form_handle();
        // exit(0);
      }

	$submission_requirement = json_decode($problem['submission_requirement'], true);
	$problem_extra_config = getProblemExtraConfig($problem);

	$solution_viewable = hasViewSolutionPermission($problem_extra_config['view_solution_type'], $myUser, $problem);
	$solution_submittable = hasViewSolutionPermission($problem_extra_config['submit_solution_type'], $myUser, $problem);
	if (!$myUser) {
		$solution_submittable = false;
	}

	function removeSolutionForm($blog_id) {
		$res_form = new UOJForm("remove_solution_{$blog_id}");
		$input_name = "blog_id_delete_{$blog_id}";
		$res_form->addHidden($input_name, $blog_id, function($blog_id) {
			global $myUser, $problem;
			if (!hasProblemPermission($myUser, $problem)) {
				$blog = queryBlog($blog_id);
				if (!$blog) {
					return '';
				}
				if ($blog['poster'] != $myUser['username']) {
					return '您只能删除自己的题解';
				}
			}
		}, null);
		$res_form->handle = function() use ($input_name) {
			global $myUser, $problem;

			$blog_id = $_POST[$input_name];
			DB::query("delete from problems_solutions where problem_id={$problem['id']} and blog_id={$blog_id}");
			$blog = queryBlog($blog_id);

			if ($blog['poster'] != $myUser['username']) {
				$blog_link = getBlogLink($blog['id']);
				$poster_user_link = getUserLink($blog['poster']);
				$admin_user_link = getUserLink($myUser['username']);
				$content = <<<EOD
<p>{$poster_user_link} 您好：</p>
<p class="indent2">您为问题 <a href="/problem/{$problem['id']}">#{$problem['id']} ({$problem['title']})</a> 提交的题解 {$blog_link} 已经被管理员 {$admin_user_link} 删除。 </p>
EOD;
				sendSystemMsg($blog['poster'], '题解删除通知', $content);
			}
		};
		$res_form->submit_button_config['class_str'] = 'btn btn-danger';
		$res_form->submit_button_config['text'] = '删除';
		$res_form->submit_button_config['align'] = 'inline';
		return $res_form;
	}

	function confirmSolutionForm($blog_id) {
		$res_form = new UOJForm("confirm_solution_{$blog_id}");
		$input_name = "blog_id_confirm_{$blog_id}";
		$res_form->addHidden($input_name, $blog_id, function($blog_id) {
			global $myUser, $problem;
			if (!hasProblemPermission($myUser, $problem)) {
				return '只有管理员可以审核题解';
			}
		}, null);
		$res_form->handle = function() use ($input_name) {
			global $myUser, $problem;

			$blog_id = $_POST[$input_name];
			DB::query("update problems_solutions set is_hidden = 0 where problem_id={$problem['id']} and blog_id={$blog_id}");
			$blog = queryBlog($blog_id);

			if ($blog['poster'] != $myUser['username']) {
				$blog_link = getBlogLink($blog['id']);
				$poster_user_link = getUserLink($blog['poster']);
				$admin_user_link = getUserLink($myUser['username']);
				$content = <<<EOD
<p>{$poster_user_link} 您好：</p>
<p class="indent2">您为问题 <a href="/problem/{$problem['id']}">#{$problem['id']} ({$problem['title']})</a> 提交的题解 {$blog_link} 已经由管理员 {$admin_user_link} 审核通过。 </p>
EOD;
				sendSystemMsg($blog['poster'], '题解审核通过通知', $content);
			}
		};
		$res_form->submit_button_config['class_str'] = 'btn btn-info';
		$res_form->submit_button_config['text'] = '通过';
		$res_form->submit_button_config['align'] = 'inline';
		return $res_form;
	}

	$removeSolutionForms = array();
	$confirmSolutionForms = array();

	$solutions = DB::query("select b.id as id from problems_solutions a inner join blogs b on a.blog_id = b.id where a.problem_id = {$problem['id']}");
	while ($row = DB::fetch($solutions)) {
		$blog_id = $row['id'];
		$removeForm = removeSolutionForm($blog_id);
		$removeForm->runAtServer();
		$removeSolutionForms[$blog_id] = $removeForm;
		$confirmForm = confirmSolutionForm($blog_id);
		$confirmForm->runAtServer();
		$confirmSolutionForms[$blog_id] = $confirmForm;
	}

	$add_new_solution_form = new UOJForm('add_new_solution');
	$add_new_solution_form->addInput('blog_id_2', 'text', '博客 ID', '', 
		function ($x) {
			global $myUser, $problem, $solution_submittable;

			if (!validateUInt($x)) return 'ID 不合法';
			$blog = queryBlog($x);
			if (!$blog) return '博客不存在';

			if (!isSuperUser($myUser)) {
				if ($blog['poster'] != $myUser['username']) {
					if ($blog['is_hidden']) {
						return '博客不存在';
					}
					return '只能提交本人撰写的博客';
				}
			}

			if ($blog['is_hidden']) {
				return '只能提交公开的博客';
			}
			if (querySolution($problem['id'], $x)) {
				return '该题解已提交';
			}
			if (!$solution_submittable) {
				return '您无权提交题解';
			}
			return '';
		},
		null
	);
	$add_new_solution_form->submit_button_config['text'] = '发布';
	$add_new_solution_form->handle = function() {
		global $problem, $myUser;

		$blog_id_2 = $_POST['blog_id_2'];
		$problem_id = $problem['id'];
		$is_hidden = 1;
		if (hasProblemPermission($myUser, $problem)) {
			$is_hidden = 0;
		}

		DB::insert("insert into problems_solutions (problem_id, blog_id, is_hidden) values ({$problem_id}, {$blog_id_2}, {$is_hidden})");
	};
	$add_new_solution_form->runAtServer();

	// $custom_test_requirement = getProblemCustomTestRequirement($problem);

	// if ($custom_test_requirement && Auth::check()) {
	// 	$custom_test_submission = DB::selectFirst("select * from custom_test_submissions where submitter = '".Auth::id()."' and problem_id = {$problem['id']} order by id desc limit 1");
	// 	$custom_test_submission_result = json_decode($custom_test_submission['result'], true);
	// }
	// if ($custom_test_requirement && $_GET['get'] == 'custom-test-status-details' && Auth::check()) {
	// 	if ($custom_test_submission == null) {
	// 		echo json_encode(null);
	// 	} else if ($custom_test_submission['status'] != 'Judged') {
	// 		echo json_encode(array(
	// 			'judged' => false,
	// 			'html' => getSubmissionStatusDetails($custom_test_submission)
	// 		));
	// 	} else {
	// 		ob_start();
	// 		$styler = new CustomTestSubmissionDetailsStyler();
	// 		if (!hasViewPermission($problem_extra_config['view_details_type'], $myUser, $problem, $submission)) {
	// 			$styler->fade_all_details = true;
	// 		}
	// 		echoJudgementDetails($custom_test_submission_result['details'], $styler, 'custom_test_details');
	// 		$result = ob_get_contents();
	// 		ob_end_clean();
	// 		echo json_encode(array(
	// 			'judged' => true,
	// 			'html' => getSubmissionStatusDetails($custom_test_submission),
	// 			'result' => $result
	// 		));
	// 	}
	// 	die();
	// }
	
	$can_use_zip_upload = true;
	foreach ($submission_requirement as $req) {
		if ($req['type'] == 'source code') {
			$can_use_zip_upload = false;
		}
	}
	
	function handleUpload($zip_file_name, $content, $tot_size) {
		global $problem, $contest, $myUser, $is_in_contest;
		
		$content['config'][] = array('problem_id', $problem['id']);
		if ($is_in_contest && $contest['extra_config']["contest_type"]!='IOI' && !isset($contest['extra_config']["problem_{$problem['id']}"])) {
			$content['final_test_config'] = $content['config'];
			$content['config'][] = array('test_sample_only', 'on');
		}
		$esc_content = DB::escape(json_encode($content));

		$language = '/';
		foreach ($content['config'] as $row) {
			if (strEndWith($row[0], '_language')) {
				$language = $row[1];
				break;
			}
		}
		if ($language != '/') {
			Cookie::set('uoj_preferred_language', $language, time() + 60 * 60 * 24 * 365, '/');
		}
		$esc_language = DB::escape($language);
 		
		$result = array();
		$result['status'] = "Waiting";
		$result_json = json_encode($result);
		
		if ($is_in_contest) {
			DB::query("insert into submissions (problem_id, contest_id, submit_time, submitter, content, language, tot_size, status, result, is_hidden) values (${problem['id']}, ${contest['id']}, now(), '${myUser['username']}', '$esc_content', '$esc_language', $tot_size, '${result['status']}', '$result_json', 0)");
		} else {
			DB::query("insert into submissions (problem_id, submit_time, submitter, content, language, tot_size, status, result, is_hidden) values (${problem['id']}, now(), '${myUser['username']}', '$esc_content', '$esc_language', $tot_size, '${result['status']}', '$result_json', {$problem['is_hidden']})");
		}
 	}
	// function handleCustomTestUpload($zip_file_name, $content, $tot_size) {
	// 	global $problem, $contest, $myUser;
		
	// 	$content['config'][] = array('problem_id', $problem['id']);
	// 	$content['config'][] = array('custom_test', 'on');
	// 	$esc_content = DB::escape(json_encode($content));

	// 	$language = '/';
	// 	foreach ($content['config'] as $row) {
	// 		if (strEndWith($row[0], '_language')) {
	// 			$language = $row[1];
	// 			break;
	// 		}
	// 	}
	// 	if ($language != '/') {
	// 		Cookie::set('uoj_preferred_language', $language, time() + 60 * 60 * 24 * 365, '/');
	// 	}
	// 	$esc_language = DB::escape($language);
 		
	// 	$result = array();
	// 	$result['status'] = "Waiting";
	// 	$result_json = json_encode($result);
		
	// 	DB::insert("insert into custom_test_submissions (problem_id, submit_time, submitter, content, status, result) values ({$problem['id']}, now(), '{$myUser['username']}', '$esc_content', '{$result['status']}', '$result_json')");
 	// }
	
	if ($can_use_zip_upload) {
		$zip_answer_form = newZipSubmissionForm('zip_answer',
			$submission_requirement,
			'uojRandAvaiableSubmissionFileName',
			'handleUpload');
		$zip_answer_form->extra_validator = function() {
			global $ban_in_contest;
			if ($ban_in_contest) {
				return '请耐心等待比赛结束后题目对所有人可见了再提交';
			}
			return '';
		};
		$zip_answer_form->succ_href = $is_in_contest ? "/contest/{$contest['id']}/submissions" : '/submissions';
		$zip_answer_form->runAtServer();
	}
	
	$answer_form = newSubmissionForm('answer',
		$submission_requirement,
		'uojRandAvaiableSubmissionFileName',
		'handleUpload');
	$answer_form->extra_validator = function() {
		global $ban_in_contest;
		if ($ban_in_contest) {
			return '请耐心等待比赛结束后题目对所有人可见了再提交';
		}
		return '';
	};
	$answer_form->succ_href = $is_in_contest ? "/contest/{$contest['id']}/submissions" : '/submissions';
	$answer_form->runAtServer();

// 	if ($custom_test_requirement) {
// 		$custom_test_form = newSubmissionForm('custom_test',
// 			$custom_test_requirement,
// 			function() {
// 				return uojRandAvaiableFileName('/tmp/');
// 			},
// 			'handleCustomTestUpload');
// 		$custom_test_form->appendHTML(<<<EOD
// <div id="div-custom_test_result"></div>
// EOD
// 		);
// 		$custom_test_form->succ_href = 'none';
// 		$custom_test_form->extra_validator = function() {
// 			global $ban_in_contest, $custom_test_submission;
// 			if ($ban_in_contest) {
// 				return '请耐心等待比赛结束后题目对所有人可见了再提交';
// 			}
// 			if ($custom_test_submission && $custom_test_submission['status'] != 'Judged') {
// 				return '上一个测评尚未结束';
// 			}
// 			return '';
// 		};
// 		$custom_test_form->ctrl_enter_submit = true;
// 		$custom_test_form->setAjaxSubmit(<<<EOD
// function(response_text) {custom_test_onsubmit(response_text, $('#div-custom_test_result')[0], '{$_SERVER['REQUEST_URI']}?get=custom-test-status-details')}
// EOD
// 		);
// 		$custom_test_form->submit_button_config['text'] = UOJLocale::get('problems::run');
// 		$custom_test_form->runAtServer();
// 	}
?>
<?php
	$REQUIRE_LIB['mathjax'] = '';
	$REQUIRE_LIB['shjs'] = '';
?>
<?php echoUOJPageHeader(HTML::stripTags($problem['title']) . ' - ' . UOJLocale::get('problems::problem')) ?>
<?php
	$limit = getUOJConf("/var/uoj_data/{$problem['id']}/problem.conf");
	$time_limit = $limit['time_limit'];
	$memory_limit = $limit['memory_limit'];
   $poster_limit = $limit['poster'];
?>
<div class="row d-flex justify-content-center">
	<span class="badge badge-secondary mr-1">时间限制:<?=$time_limit!=null?"$time_limit s":"N/A"?></span>
	<span class="badge badge-secondary mr-1">空间限制:<?=$memory_limit!=null?"$memory_limit MB":"N/A"?></span>
    <span class="badge badge-secondary mr-1">上传者:<?=$poster_limit!=null?"$poster_limit":"root"?></span> 
</div>
<div class="float-right">
	<?= getClickZanBlock('P', $problem['id'], $problem['zan']) ?>
</div>

<?php if ($contest): ?>
<div class="page-header row">
	<h1 class="col-md-3 text-left"><small><?= $contest['name'] ?></small></h1>
	<h1 class="col-md-7 text-center"><?= $problem_letter ?>. <?= $problem['title'] ?></h1>
	<div class="col-md-2 text-right" id="contest-countdown"></div>
</div>
<a role="button" class="btn btn-info float-right" href="/contest/<?= $contest['id'] ?>/problem/<?= $problem['id'] ?>/statistics"><i class="fa fa-bar-chart"></i> <?= UOJLocale::get('problems::statistics') ?></a>
<?php if ($contest['cur_progress'] <= CONTEST_IN_PROGRESS): ?>
<script type="text/javascript">
checkContestNotice(<?= $contest['id'] ?>, '<?= UOJTime::$time_now_str ?>');
$('#contest-countdown').countdown(<?= $contest['end_time']->getTimestamp() - UOJTime::$time_now->getTimestamp() ?>);
</script>
<?php endif ?>
<?php else: ?>
<h1 class="page-header text-center">#<?= $problem['id']?>. <?= $problem['title'] ?></h1>
<a role="button" class="btn btn-info float-right" href="/problem/<?= $problem['id'] ?>/statistics"><i class="fa fa-bar-chart"></i> <?= UOJLocale::get('problems::statistics') ?></a>
<?php endif ?>

<ul class="nav nav-tabs" role="tablist">
	<li class="nav-item"><a class="nav-link active" href="#tab-statement" role="tab" data-toggle="tab"><i class="fa fa-book"></i> <?= UOJLocale::get('problems::statement') ?></a></li>
	<li class="nav-item"><a class="nav-link" href="#tab-submit-answer" role="tab" data-toggle="tab"><i class="fa fa-upload"></i> <?= UOJLocale::get('problems::submit') ?></a></li>
	<!-- <?php if ($custom_test_requirement): ?>
	<li class="nav-item"><a class="nav-link" href="#tab-custom-test" role="tab" data-toggle="tab"><i class="fa fa-code"></i> <?= UOJLocale::get('problems::custom test') ?></a></li>
	<?php endif ?> -->
	<?php if ($solution_viewable): ?>
	<li class="nav-item"><a class="nav-link" href="#tab-solutions" role="tab" data-toggle="tab"><i class="fa fa-pencil-square-o"></i> <?= UOJLocale::get('problems::solutions') ?></a></li>
	<?php endif ?>
	<?php if (hasProblemPermission($myUser, $problem)): ?>
	<li class="nav-item"><a class="nav-link" href="/problem/<?= $problem['id'] ?>/manage/statement" role="tab"><i class="fa fa-wrench"></i> <?= UOJLocale::get('problems::manage') ?></a></li>
	<?php endif ?>
	<?php if ($contest): ?>
	<li class="nav-item"><a class="nav-link" href="/contest/<?= $contest['id'] ?>" role="tab"><?= UOJLocale::get('contests::back to the contest') ?></a></li>
	<?php endif ?>
</ul>
<div class="tab-content">
	<div class="tab-pane active" id="tab-statement">
		<article class="top-buffer-md"><?= $problem_content['statement'] ?></article>
	</div>
	<div class="tab-pane" id="tab-submit-answer">
		<div class="top-buffer-sm"></div>
		<?php if ($can_use_zip_upload): ?>
		<?php $zip_answer_form->printHTML(); ?>
		<hr />
		<strong><?= UOJLocale::get('problems::or upload files one by one') ?><br /></strong>
		<?php endif ?>
		<?php $answer_form->printHTML(); ?>
	</div>
	<!-- <?php if ($custom_test_requirement): ?>
	<div class="tab-pane" id="tab-custom-test">
		<div class="top-buffer-sm"></div>
		<?php $custom_test_form->printHTML(); ?>
	</div>
	<?php endif ?> -->
	<?php if ($solution_viewable): ?>
	<div class="tab-pane" id="tab-solutions">

<?php if(1): ?>
	<!-- new codes BEGIN-->
	<?php 
	
	global $contest;
			// echo $contest['cur_progress'], ' ';
			// echo CONTEST_TESTING;
		// echo $contest;
	?>
	<?php if ($contest === NULL || isSuperUser($myUser) || $contest['cur_progress'] >= CONTEST_TESTING): ?>
	<div>
		<div id="vcomments"></div>
		<script>
			new Valine({
				el: '#vcomments',
				appId: 'LIrbXXrWQfHs8EVwh5K4mnOL-gzGzoHsz',
				appKey: 'hlrkLoaj1uqgSDUme69qorOF'
			});
			jQuery("#tab-solutions").click(function() {
				jQuery("[name=nick]").val("<?php echo $myUser['username']; ?>");
			});
			jQuery("[name=nick]").attr("disabled", "disabled");
		</script>
	</div>
	<?php endif ?>
	<!-- new codes END -->
<?php endif; ?>


<?php if(0): /* 啊这，就别要之前的题解系统了吧…… */ ?>		

<?php
	$cond = "(a.is_hidden = 0 or b.poster = '{$myUser['username']}') and a.problem_id = {$problem['id']}";
	if (hasProblemPermission($myUser, $problem)) {
		$cond = "a.problem_id = {$problem['id']}";
	}
	$header = <<<EOD
	<tr>
		<th width="60%">标题</th>
		<th width="20%">发表者</th>
		<th width="20%">发表日期</th>
	</tr>
EOD;

	$config = array();
	$config['table_classes'] = array('table', 'table-hover');

	function echoBlogCell($blog) {
		global $myUser, $problem, $confirmSolutionForms, $removeSolutionForms;

		echo '<tr>';
		echo '<td>';

		if (hasProblemPermission($myUser, $problem) || $blog['poster'] == $myUser['username']) {
			$form = $removeSolutionForms[$blog['id']];
			$form->printHTML();
		}

		if ($blog['is_hidden']) {
			if (hasProblemPermission($myUser, $problem)) {
				$form = $confirmSolutionForms[$blog['id']];
				$form->printHTML();
			}
			echo '<span class="text-danger">[待审核]</span>';
		}

		echo getBlogLink($blog['id']);
		echo '</td>';

		echo '<td>' . getUserLink($blog['poster']) . '</td>';
		echo '<td>' . $blog['post_time'] . '</td>';
		echo '</tr>';
	}

	echoLongTable(array('b.id as id', 'b.poster as poster', 'b.title as title', 'b.post_time as post_time', 'b.zan as zan', 'a.is_hidden as is_hidden', 'b.poster as poster'), 'problems_solutions a inner join blogs b on a.blog_id = b.id', $cond, 'order by post_time desc', $header, 'echoBlogCell', $config);
	
?>


	<?php if ($solution_submittable): ?>
	<h3 class="mt-4">发布题解</h3>
	<p>要发布题解，您需要将您的题解发布为博客，然后将博客 ID 填在下面并发布。博客 ID 可以在标题下方找到。</p>
	<p>注意，您只能发布自己的博客，并且这篇博客不能是隐藏的。</p>
	<?php $add_new_solution_form->printHTML(); ?>
	<?php endif ?>
<?php endif ?>





















<?php if ($contest === NULL || isSuperUser($myUser) || $contest['cur_progress'] >= CONTEST_TESTING): ?>


<?php /*------------------------------------------------------------------------------------------------------------------------------------------------------*/ ?>

<?php
$pro_id = $problem['id'];
// echo "select * from nek_cmt where cid=$pro_id"; 

// $ret = DB::query("select * from nek_cmt where cid=$pro_id order by id");

// $result=DB::query("select * from contests_asks where contest_id='${contest['id']}' and username='${myUser['username']}' order by reply_time desc limit 10");

$result = DB::query("select * from nek_cmt where cid=$pro_id and reply_id=0 order by id");
$cmt = [];
try {
	while ($row = DB::fetch($result)) {
		$cmt[] = $row;
	}
} catch (Exception $e) {
}

// var_dump($cmt);
// $cmt = [];
// exit(0);

// $arrt = array ('a'=>1,'b'=>2,'c'=>3,'d'=>4,'e'=>5);
//echo json_encode($arrt);
//exit(0);

?>

<h5>评论 <i class="fa fa-comment"></i></h5>
<div class="list-group">
<?php if ($cmt == []): ?>
	<div class="list-group-item text-muted">暂无评论</div>
<?php else: ?>
	<?php foreach ($cmt as $comment):
		$poster = queryUser($comment['poster']);
		$esc_email = HTML::escape($poster['email']);
		$asrc = HTML::avatar_addr($poster, 80);
		
		$replies = DB::selectAll("select id, poster, content, post_time from nek_cmt where reply_id = {$comment['id']} order by id");
        // var_dump($replies);
		foreach ($replies as $idx => $reply) {
            // echo $idx . " ". $reply[0];
			$replies[$idx]['poster_rating'] = queryUser($reply['poster'])['rating'];
			$replies[$idx]['poster_realname'] = queryUser($reply['poster'])['realname'];
		}
      // var_dump($replies);
      // var_dump(json_encode($replies));
      // exit(0);
      // var_dump(json_encode($replies));
		$replies_json = json_encode($replies);
      //var_dump($replies_json);
      //exit(0);
	?>
	<div id="comment-<?= $comment['id'] ?>" class="list-group-item">
		<div class="media">
			<div class="media-left comtposterbox mr-3">
				<a href="<?= HTML::url('/user/profile/'.$poster['username']) ?>" class="d-none d-sm-block">
					<img class="media-object img-rounded" src="<?= $asrc ?>" alt="avatar" />
				</a>
			</div>
			<div id="comment-body-<?= $comment['id'] ?>" class="media-body comtbox">
				<div class="row">
					<div class="col-sm-6"><?= getUserLink($poster['username']) ?></div>
					<div class="col-sm-6 text-right"><?= getClickZanBlock('BC', $comment['id'], $comment['zan']) ?></div>
				</div>
				<div class="comtbox1"><?= $comment['content'] ?></div>
				<ul class="text-right list-inline bot-buffer-no"><li><small class="text-muted"><?= $comment['post_time'] ?></small></li><li><a id="reply-to-<?= $comment['id'] ?>" href="#">回复</a></li></ul>
				<?php if ($replies): ?>
				<div id="replies-<?= $comment['id'] ?>" class="comtbox5"></div>
				<?php endif ?>
				<script type="text/javascript">showCommentReplies('<?= $comment['id'] ?>', <?= $replies_json ?>);</script>
			</div>
		</div>
	</div>
	<?php endforeach ?>
<?php endif ?>
</div>


<?php
/*
  $comments_pag = new Paginator(array(
		'col_names' => array('*'),
		'table_name' => 'nek_cmt',
		'cond' => 'blog_id = ' . $problem['id'] . ' and reply_id = 0',
		'tail' => 'order by id asc',
		'page_len' => 20
	));*/

?>

<?php /*$comments_pag->pagination();*/ ?>
<h5 class="mt-4">发表评论</h5>
<p>可以用 @mike 来提到 mike 这个用户，mike 会被高亮显示。如果你真的想打 “@” 这个字符，请用 “@@”。</p>

<?php comment_form_printHTML();  ?>

<div id="div-form-reply" style="display:none">
    	<!--<form action="/problem/<?php echo $problem['id']; ?>" method="post" class="form-horizontal" id="form-reply" enctype="multipart/form-data">-->
          <input type="hidden" name="reply_id" id="input-reply_id" value="">
          <div id="div-reply_comment" class="form-group">
    	    <label for="input-reply_comment" class="control-label">内容</label>
  	      <textarea class="form-control" name="reply_comment" id="input-reply_comment" style="overflow: hidden; overflow-wrap: break-word; resize: none; height: 54.1px;"></textarea>
    	  <span class="help-block" id="help-reply_comment"></span>
    </div>
    <div class="text-center"><button type="submit" id="button-submit-reply" name="submit-reply" value="reply" class="mt-2 btn btn-secondary">提交</button></div>
    
<script>
$(document).ready(function() {
    $("#button-submit-reply").click(function() {
        window.nek_f_rid = $("#input-reply_id").val();
        $.ajax({
            url: "/problem/<?php echo $problem['id']; ?>",
            type: "POST",
            data: {
                "reply_comment": $("#input-reply_comment").val(),
                "reply_id": window.nek_f_rid
            },
            success: function(data) {
                // console.log(data);
                location.replace(location.href.replace(/#/,"").replace(/\?.*/,"") + "?tj=" + window.nek_f_rid);
            }
        });
    });
});
</script>
    
    <!--</form>-->
</div>


<?php /*------------------------------------------------------------------------------------------------------------------------------------------------------*/ ?>


<?php if(isset($_GET['tj'])): ?>
<script>
$(document).ready(function() {

  try{

    $("body > div.container.theme-showcase > div.uoj-content > ul > li:nth-child(3) > a").click();

    <?php if($_GET['tj'] == 0): /* 打开题解 */ ?>
          // $(document).scrollTop(999999);
          console.log($("#div-comment"));
          $(document).scrollTop($("#div-comment").offset().top);
    <?php endif; ?>
    <?php if($_GET['tj'] > 0): /* 打开题解 */ ?>
          $(document).scrollTop($("#comment-body-<?php echo $_GET['tj']; ?>").offset().top);
          $("#comment-body-<?php echo $_GET['tj']; ?>").parent().css('display', 'none').fadeIn(1000);
    <?php endif; ?>
    }catch(e) {};
});
</script>
<?php endif; ?>


<?php endif; ?>



<script>
$(document).ready(function() {


 //console.log($("[class*=comtbox] [class^=comtbox]"));
 
	var md;
	var defaults = {
	  html: true, // Enable HTML tags in source
	  xhtmlOut: false, // Use '/' to close single tags (<br />)
	  breaks: false, // Convert '\n' in paragraphs into <br>
	  langPrefix: 'language-', // CSS language prefix for fenced blocks
	  linkify: true, // autoconvert URL-like texts to links
	  typographer: true, // Enable smartypants and other sweet transforms
	  // options below are for demo only
	  _highlight: true,
	  _strict: false,
	  _view: 'html' // html / src / debug
	};

	defaults.highlight = function (str, lang) {
	  var esc = md.utils.escapeHtml;
	  // console.log(str)
	  // console.log(lang)
	  if (lang && hljs.getLanguage(lang)) {
		try {
          // console.log(str);
          str = $("<div>").html(str).text();
          // console.log(str);
          // console.log(hljs.highlight(lang, str, true).value);
		  return '<pre class="hljs"><code>' +
				 hljs.highlight(lang, str, true).value +
				 '</code></pre>';
		} catch (__) {}
	  }else {
		return '<pre class="hljs"><code>' + esc(str) + '</code></pre>';
	  }
	  
	};
 
	
 
 
	md = window.markdownit(defaults);
 //console.log($("[class*=comtbox] [class^=comtbox]"));
 
	$('div').filter(function() { return this.className.match(/comtbox.*/) && !this.id.match(/co.*|re.*/); }).each(function () {
      // console.log(md.render($(this).html()));
      $(this).html(md.render($(this).html()));
    });
 
   window.md = md;
 
});
</script>







	</div>
	<?php endif ?>
</div>
<?php echoUOJPageFooter() ?>
