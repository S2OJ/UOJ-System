<?php
	requirePHPLib('form');

 
	if (!isUser($myUser)) {
		become403Page();
	}
	
	if (!validateUInt($_GET['id']) || !($contest = queryContest($_GET['id']))) {
		become404Page();
	}
 
	genMoreContestInfo($contest);

	if (!hasContestPermission(Auth::user(), $contest)) {
		if ($contest['cur_progress'] == CONTEST_NOT_STARTED) {
			header("Location: /contest/{$contest['id']}/register");
			die();
		} elseif ($contest['cur_progress'] == CONTEST_IN_PROGRESS) {
			if ($myUser == null || !hasRegistered(Auth::user(), $contest)) {
				becomeMsgPage("<h1>比赛正在进行中</h1><p>很遗憾，您尚未报名。比赛结束后再来看吧～</p>");
			}
		}
	}

    

  
if(isset($_GET["nek_psc_key"]) && isset($_GET['nek_usr_name']) && isset($_GET["nek_psc_val"]) && $contest['cur_progress'] == CONTEST_IN_PROGRESS) {
   $nek_psc_key = (int) ($_GET["nek_psc_key"]);
   $nek_psc_val = (int) ($_GET["nek_psc_val"]);
   // echo $nek_psc_key . " " . $nek_psc_val;
   $username = $myUser["username"];
   $contest_id = $contest['id'];
   
   // var_dump($contest_id);
   
   if($username != $_GET['nek_usr_name'] || $nek_psc_val > 100 || $nek_psc_val < 0 || $nek_psc_key > 5 || $nek_psc_key < 0) {
     echo "0";
     exit(0);
   }
   
   // echo "$username  $contest_id";
   // exit(0);
   $ret = nek_read_predict_score_self($contest_id, $username);
   
   // 此人还未有预估分数
   if($ret == []) {
     // acm最多也就6个题……
     // echo "!@#"; exit(0);
     nek_insert_predict_score($contest_id, $username, "0,0,0,0,0,0");
     $ret = nek_read_predict_score_self($contest_id, $username);
   }
   // echo "ASD"; exit(0);
   
   
   // 表中已有此人
   // echo ($ret[0]); exit(0);
   // var_dump(explode(',', $ret[0])); exit(0);
   $ret = explode(',', $ret[0]);
   $ret[$nek_psc_key] = $nek_psc_val;
   // echo join(",", $ret); exit(0);
   nek_update_predict_score($contest_id, $username, join(",", $ret));
   echo $nek_psc_val;
   exit(0);
}
	
 
 
	if (isset($_GET['tab'])) {
		$cur_tab = $_GET['tab'];
	} else {
		$cur_tab = 'dashboard';
	}
	
	$tabs_info = array(
		'dashboard' => array(
			'name' => UOJLocale::get('contests::contest dashboard'),
			'url' => "/contest/{$contest['id']}"
		),
		'submissions' => array(
			'name' => UOJLocale::get('contests::contest submissions'),
			'url' => "/contest/{$contest['id']}/submissions"
		),
		'standings' => array(
			'name' => UOJLocale::get('contests::contest standings'),
			'url' => "/contest/{$contest['id']}/standings"
		),
		'after_sub' => array(
			'name' => '赛后排行榜',
			'url' => "/contest/{$contest['id']}/standings?after_sub=1"
		),
        'standing_download' => array(
            'name' => '导出排行榜',
            'url' => 'javascript:tableToExcel()'
        )
	);
	
	if (hasContestPermission(Auth::user(), $contest)) {
		$tabs_info['backstage'] = array(
			'name' => UOJLocale::get('contests::contest backstage'),
			'url' => "/contest/{$contest['id']}/backstage"
		);
	}
	
	if (!isset($tabs_info[$cur_tab])) {
		become404Page();
	}
	
	if (isset($_POST['check_notice'])) {
		$result = DB::query("select * from contests_notice where contest_id = '${contest['id']}' order by time desc limit 10");
		$ch = array();
		$flag = false;
		try {
			while ($row = DB::fetch($result)) {
				if (new DateTime($row['time']) > new DateTime($_POST['last_time'])) {
					$ch[] = $row['title'].': '.$row['content'];
				}
			}
		} catch (Exception $e) {
		}
		global $myUser;
		$result=DB::query("select * from contests_asks where contest_id='${contest['id']}' and username='${myUser['username']}' order by reply_time desc limit 10");
		try {
			while ($row = DB::fetch($result)) {
				if (new DateTime($row['reply_time']) > new DateTime($_POST['last_time'])) {
					$ch[] = $row['question'].': '.$row['answer'];
				}
			}
		} catch (Exception $e) {
		}
		if ($ch) {
			die(json_encode(array('msg' => $ch, 'time' => UOJTime::$time_now_str)));
		} else {
			die(json_encode(array('time' => UOJTime::$time_now_str)));
		}
	}
	
	if (isSuperUser($myUser)) {
		if ($contest['cur_progress'] >= CONTEST_PENDING_FINAL_TEST) {
			$start_test_form = new UOJForm('start_test');
			$start_test_form->handle = function() {
				global $contest;
				$result = DB::query("select id, problem_id, content from submissions where contest_id = {$contest['id']}");
				while ($submission = DB::fetch($result, MYSQLI_ASSOC)) {
					if (!isset($contest['extra_config']["problem_{$submission['problem_id']}"])) {
	 					$content = json_decode($submission['content'], true);
						if (isset($content['final_test_config'])) {
							$content['config'] = $content['final_test_config'];
							unset($content['final_test_config']);
						}
						if (isset($content['first_test_config'])) {
							unset($content['first_test_config']);
						}
						$esc_content = DB::escape(json_encode($content));
						DB::update("update submissions set judge_time = NULL, result = '', score = NULL, status = 'Waiting Rejudge', content = '$esc_content' where id = {$submission['id']}");
					}
				}
				DB::query("update contests set status = 'testing' where id = {$contest['id']}");
			};
			$start_test_form->submit_button_config['class_str'] = 'btn btn-danger btn-block';
			$start_test_form->submit_button_config['smart_confirm'] = '';
			if ($contest['cur_progress'] < CONTEST_TESTING) {
				$start_test_form->submit_button_config['text'] = '开始最终测试';
			} else {
				$start_test_form->submit_button_config['text'] = '重新开始最终测试';
			}

			$start_test_form->runAtServer();
		}
		if ($contest['cur_progress'] >= CONTEST_TESTING) {
			$publish_result_form = new UOJForm('publish_result');
			$publish_result_form->handle = function() {
				// time config
				set_time_limit(0);
				ignore_user_abort(true);

				global $contest;
				$contest_data = queryContestData($contest);
				calcStandings($contest, $contest_data, $score, $standings, true);
				if (!isset($contest['extra_config']['unrated'])) {
					$rating_k = isset($contest['extra_config']['rating_k']) ? $contest['extra_config']['rating_k'] : 400;
					$ratings = calcRating($standings, $rating_k);
				} else {
					$ratings = array();
					for ($i = 0; $i < count($standings); $i++) {
						$ratings[$i] = $standings[$i][2][1];
					}
				}

				for ($i = 0; $i < count($standings); $i++) {
					$username = $standings[$i][2][0];
					$user = queryUser($username);
					$old_rating = $standings[$i][2][1];
					$change = $ratings[$i] - $old_rating;
					$registrant = fetchRegistrant($contest['id'], $username);
					$already_changed = (int)$registrant['rating_change'];
					$after_rating = $user['rating'] - $already_changed + $change;
					$user_link = getUserLink($user['username']);

					if ($change != 0) {
						$tail = '<strong style="color:red">' . ($change > 0 ? '+' : '') . $change . '</strong>';
						$content = <<<EOD
<p>${user_link} 您好：</p>
<p class="indent2">您在 <a href="/contest/{$contest['id']}">{$contest['name']}</a> 这场比赛后的 Rating 变化为 ${tail}，当前 Rating 为 <strong style="color:red">{$after_rating}</strong>。</p>
EOD;
					} else {
						$content = <<<EOD
<p>${user_link} 您好：</p>
<p class="indent2">您在 <a href="/contest/{$contest['id']}">{$contest['name']}</a> 这场比赛后 Rating 没有变化。当前 Rating 为 <strong style="color:red">{$after_rating}</strong>。</p>
EOD;
					}
					
					sendSystemMsg($user['username'], 'Rating变化通知', $content);
					DB::query("update user_info set rating = {$after_rating} where username = '{$standings[$i][2][0]}'");
					DB::query("update contests_registrants set rank = {$standings[$i][3]}, rating_change = {$change} where contest_id = {$contest['id']} and username = '{$standings[$i][2][0]}'");
				}
				DB::query("update contests set status = 'finished' where id = {$contest['id']}");
			};
			$publish_result_form->submit_button_config['class_str'] = 'btn btn-danger btn-block';
			$publish_result_form->submit_button_config['smart_confirm'] = '';
			$publish_result_form->submit_button_config['text'] = '公布成绩';
			
			$publish_result_form->runAtServer();
		}
	}
	
	if ($cur_tab == 'dashboard') {
		if ($contest['cur_progress'] <= CONTEST_IN_PROGRESS) {
			$post_question = new UOJForm('post_question');
			$post_question->addVTextArea('qcontent', '问题', '', 
				function($content) {
					if (!Auth::check()) {
						return '您尚未登录';
					}
					if (!$content || strlen($content) == 0) {
						return '问题不能为空';
					}
					if (strlen($content) > 140 * 4) {
						return '问题太长';
					}
					return '';
				},
				null
			);
			$post_question->handle = function() {
				global $contest;
				$content = DB::escape($_POST['qcontent']);
				$username = Auth::id();
				DB::query("insert into contests_asks (contest_id, question, username, post_time, is_hidden) values ('{$contest['id']}', '$content', '$username', now(), 1)");
			};
			$post_question->runAtServer();
		} else {
			$post_question = null;
		}
	} elseif ($cur_tab == 'backstage') {
		if (isSuperUser(Auth::user())) {
			$post_notice = new UOJForm('post_notice');
			$post_notice->addInput('title', 'text', '标题', '',
				function($title) {
					if (!$title) {
						return '标题不能为空';
					}
					return '';
				},
				null
			);
			$post_notice->addTextArea('content', '正文', '', 
				function($content) {
					if (!$content) {
						return '公告不能为空';
					}
					return '';
				},
				null
			);
			$post_notice->handle = function() {
				global $contest;
				$title = DB::escape($_POST['title']);
				$content = DB::escape($_POST['content']);
				DB::insert("insert into contests_notice (contest_id, title, content, time) values ('{$contest['id']}', '$title', '$content', now())");
			};
			$post_notice->runAtServer();
		} else {
			$post_notice = null;
		}
		
		if (hasContestPermission(Auth::user(), $contest)) {
			$reply_question = new UOJForm('reply_question');
			$reply_question->addHidden('rid', '0',
				function($id) {
				    global $contest;
				    
					if (!validateUInt($id)) {
						return '无效ID';
					}
					$q = DB::selectFirst("select * from contests_asks where id = $id");
					if ($q['contest_id'] != $contest['id']) {
					    return '无效ID';
					}
					return '';
				},
				null
			);
			$reply_question->addVSelect('rtype', [
				'public' => '公开',
				'private' => '非公开',
				'statement' => '请仔细阅读题面（非公开）',
				'no_comment' => '无可奉告（非公开）',
				'no_play' => '请认真比赛（非公开）',
			], '回复类型', 'private');
			$reply_question->addVTextArea('rcontent', '回复', '', 
				function($content) {
				    if (!Auth::check()) {
				        return '您尚未登录';
				    }
				    switch ($_POST['rtype']) {
				    	case 'public':
				    	case 'private':
				    		if (strlen($content) == 0) {
								return '回复不能为空';
							}
							break;
				    }
					return '';
				},
				null
			);
			$reply_question->handle = function() {
				global $contest;
				$content = DB::escape($_POST['rcontent']);
				$is_hidden = 1;
				switch ($_POST['rtype']) {
					case 'statement':
						$content = '请仔细阅读题面';
						break;
					case 'no_comment':
						$content = '无可奉告 ╮(╯▽╰)╭ ';
						break;
					case 'no_play':
						$content = '请认真比赛 (￣口￣)!!';
						break;
					case 'public':
						$is_hidden = 0;
						break;
					default:
						break;
				}
				DB::update("update contests_asks set answer = '$content', reply_time = now(), is_hidden = {$is_hidden} where id = {$_POST['rid']}");
			};
			$reply_question->runAtServer();
		} else {
			$reply_question = null;
		}
	}
	
	function echoDashboard() {
		global $contest, $post_notice, $post_question, $reply_question;
		
		$myname = Auth::id();
		$contest_problems = DB::selectAll("select contests_problems.problem_id, best_ac_submissions.submission_id from contests_problems left join best_ac_submissions on contests_problems.problem_id = best_ac_submissions.problem_id and submitter = '{$myname}' where contest_id = {$contest['id']} order by contests_problems.problem_id asc");
		
		for ($i = 0; $i < count($contest_problems); $i++) {
			$contest_problems[$i]['problem'] = queryProblemBrief($contest_problems[$i]['problem_id']);
		}
		
		$contest_notice = DB::selectAll("select * from contests_notice where contest_id = {$contest['id']} order by time desc");
		
		if (Auth::check()) {
			$my_questions = DB::selectAll("select * from contests_asks where contest_id = {$contest['id']} and username = '{$myname}' order by post_time desc");
			$my_questions_pag = new Paginator([
				'data' => $my_questions
			]);
		} else {
			$my_questions_pag = null;
		}
		
		$others_questions_pag = new Paginator([
			'col_names' => array('*'),
			'table_name' => 'contests_asks',
			'cond' => "contest_id = {$contest['id']} and username != '{$myname}' and is_hidden = 0",
			'tail' => 'order by reply_time desc',
			'page_len' => 10
		]);
		
		uojIncludeView('contest-dashboard', [
			'contest' => $contest,
			'contest_notice' => $contest_notice,
			'contest_problems' => $contest_problems,
			'post_question' => $post_question,
			'my_questions_pag' => $my_questions_pag,
			'others_questions_pag' => $others_questions_pag
		]);
	}
	
	function echoBackstage() {
		global $contest, $post_notice, $reply_question;
		
		$questions_pag = new Paginator([
			'col_names' => array('*'),
			'table_name' => 'contests_asks',
			'cond' => "contest_id = {$contest['id']}",
			'tail' => 'order by post_time desc',
			'page_len' => 50
		]);
		
		if ($contest['cur_progress'] < CONTEST_TESTING) {
			$contest_data = queryContestData($contest, ['pre_final' => true]);
			calcStandings($contest, $contest_data, $score, $standings);
			
			$standings_data = [
				'contest' => $contest,
				'standings' => $standings,
				'score' => $score,
				'contest_data' => $contest_data
			];
		} else {
			$standings_data = null;
		}
		
		uojIncludeView('contest-backstage', [
			'contest' => $contest,
			'post_notice' => $post_notice,
			'reply_question' => $reply_question,
			'questions_pag' => $questions_pag,
			'standings_data' => $standings_data
		]);
	}
	
	function echoMySubmissions() {
		global $contest, $myUser;

		$show_all_submissions_status = Cookie::get('show_all_submissions') !== null ? 'checked="checked" ' : '';
		$show_all_submissions = UOJLocale::get('contests::show all submissions');
		echo <<<EOD
			<div class="checkbox text-right">
				<label for="input-show_all_submissions"><input type="checkbox" id="input-show_all_submissions" $show_all_submissions_status/> $show_all_submissions</label>
			</div>
			<script type="text/javascript">
				$('#input-show_all_submissions').click(function() {
					if (this.checked) {
						$.cookie('show_all_submissions', '');
					} else {
						$.removeCookie('show_all_submissions');
					}
					location.reload();
				});
			</script>
EOD;
		if (Cookie::get('show_all_submissions') !== null) {
			echoSubmissionsList("contest_id = {$contest['id']}", 'order by id desc', array('judge_time_hidden' => ''), $myUser);
		} else {
			echoSubmissionsList("submitter = '{$myUser['username']}' and contest_id = {$contest['id']}", 'order by id desc', array('judge_time_hidden' => ''), $myUser);
		}
	}
	
	function echoStandings($is_after_contest_query = false) {
		global $contest;
		
		// NEW CODE BEGIN
		// $contest_data = queryContestData($contest);
		$contest_data = queryContestData($contest, array(), $is_after_contest_query);
		// NEW CODE END

		calcStandings($contest, $contest_data, $score, $standings);
		
		uojIncludeView('contest-standings', [
			'contest' => $contest,
			'standings' => $standings,
			'score' => $score,
			'contest_data' => $contest_data
		]);
    
?>
<script>
$(document).ready(function() {
	window.show_predict_score = function(username, scores) {
		// scores = [A-score, B-score, C-score, ...]
	    
        window.nek_tr = null;
        
        $(`td > .uoj-username:contains('${username}')`).map(function(){
	       if ($(this).text().replace(/ \(.*\)/,"") == `${username}`) {
             window.nek_tr = $(this).parent().parent();
           }
   		 });
         
        let tr = window.nek_tr;
        
        if(!tr) {
          return ;
        }
        
        let tot_score = 0;
        let pro_cnt = tr.children().length - 3;	
        
        if(scores.length == 0) {
          scores = [0, 0, 0, 0, 0, 0];
        }
        // console.log(scores);
        // console.log(scores.length);
    
        
        if(scores[0] == -1) {
          tot_score = 0;
          // console.log("totscore = " + tot_score);
          for(let i = 1 ; i <= pro_cnt ; ++ i) {
             // console.log("tot = " + tot_score);  
		 	 let tmp = parseInt(tr.find(`td:nth-child(${3 + i}) > div:nth-child(1) > span`).text().replace("(", "").replace(")", ""), 10);
             if(isNaN(tmp)) {
               tmp = 0;
             }
             tot_score += tmp;
             // console.log("tots——subcore = " + tot_score);
		  }
         // console.log("totscore = " + tot_score);
         
          tr.find("td:nth-child(3) > div:nth-child(1) > span > span").text(`(${tot_score})`);
          return ;
        }
		
        for(let i = 1 ; i <= pro_cnt ; ++ i) {
			let sco = scores[i - 1];
			tr.find(`td:nth-child(${3 + i}) > div:nth-child(1) > a`).after(`<span class='uoj-score' data-max='100' style='color:grey'>(${sco})</span>`);
		}
    
        tot_score = scores.reduce(function(a, b) {
						    return parseInt(a, 10) + parseInt(b, 10);
						});
		 
		tr.find("td:nth-child(3) > div:nth-child(1) > span").append(`<span class='uoj-score' data-max='100' style='color:grey'>(${tot_score})</span>`);
    
	};
	// show_predict_score("nekko_test_test", [1,2,3]);
});
</script>
<?php
    
        if($is_after_contest_query == false) {
            global $myUser;
            $username = $myUser["username"];
            // echo ($contest["id"]);
            // nek_write_predict_score($contest["id"], 'nekko_test', "12,13,14");
            // nek_write_predict_score($contest["id"], $username, "5,6,7");
            
            // DEBUG
            //  $username = "nekko_test_test";
            // END DEBUG
            
            // 如果比赛还未结束，则只显示自己的预估分数
            // 管理员可以查看所有人的预估成绩
            if($contest['cur_progress'] <= CONTEST_IN_PROGRESS && !isSuperUser($myUser)) {
              $ret = nek_read_predict_score_self($contest["id"], $username);
              if($ret == []) {
                nek_insert_predict_score($contest["id"], $username, "0,0,0,0,0,0");
                $ret = nek_read_predict_score_self($contest["id"], $username);
              }
              ?>
                  <script>
                    $(document).ready(function() {
                      show_predict_score('<?php echo $username; ?>', [<?php echo $ret[0]; ?>]);
                    });
                  </script>
              <?php
              // echo $ret[0];
              // exit(0);
            }
            // 如果比赛已经结束，则显示所有人的分数
            else {
              $ret = nek_read_predict_score($contest["id"]);
            ?>
                <script>
                  $(document).ready(function() {
                    window.nek_show_all = 1;
                    <?php foreach($ret as $ele) { ?>
                      show_predict_score('<?php echo $ele[0]; ?>', [<?php echo $ele[1]; ?>]);
                    <?php } ?>
                  });
                </script>
              <?php
            }
            
            
            ?>
            <script>
                $(document).ready(function() {
                    window.nek_utr = null;
                    let username = '<?php echo $username; ?>';
                    
                    // username = 'nekko_test';
                    
                    $(`td > .uoj-username:contains('${username}')`).map(function(){
    	               if($(this).text().replace(/ \(.*\)/,"") == `${username}`) {
                           window.nek_utr = $(this).parent().parent();
                       }
         		    });
                        
                   // nek_utr.children(":nth-child(2)").append("<hr/>").append($("<a class='nek_pre_sco_upd btn btn-primary btn-block' href='#'>提交</a>"));
                   
                   if(!nek_utr || window.nek_show_all) {
                     return ;
                   }
                   
                   var pro_cnt = nek_utr.children().length - 3;
                   // console.log(pro_cnt); 
              	   for(var i = 1 ; i <= pro_cnt ; ++ i) {
                       // console.log(i);
                       // console.log(nek_utr.find(`td:nth-child(${3 + i}) > div:nth-child(1) > span`));
         			   nek_utr.find(`td:nth-child(${3 + i}) > div:nth-child(1) > span`)
                              .attr('nek_i', i)
                              .attr('usr-na', username)
                              .attr('contenteditable', 'true')
                              .focus(function() {
                                  $(this).text($(this).text().replace("(", "").replace(")", ""));
                                  // $(this).select();
                               })
                              .blur(function() {
                                  $.ajax({
                                        url: `${window.location.href}?nek_usr_name=${$(this).attr('usr-na')}&nek_psc_key=${$(this).attr('nek_i')-1}&nek_psc_val=${$(this).text()}`,
                                        success: (data) => {
                                          // console.log(data);
                                          if(data.length > 10) data = '0';
                                          $(this).text("(" + data + ")");
                                          show_predict_score($(this).attr('usr-na'), [-1]);
                                        }
                                  });
                              
                               });
            		}
                });
            </script>
            <?php
        }
	}
	
	function echoContestCountdown() {
		global $contest;
	 	$rest_second = $contest['end_time']->getTimestamp() - UOJTime::$time_now->getTimestamp();
	 	$time_str = UOJTime::$time_now_str;
	 	$contest_ends_in = UOJLocale::get('contests::contest ends in');
	 	echo <<<EOD
 		<div class="card border-info">
 			<div class="card-header bg-info">
 				<h3 class="card-title">$contest_ends_in</h3>
 			</div>
 			<div class="card-body text-center countdown" data-rest="$rest_second"></div>
 		</div>
		<script type="text/javascript">
			checkContestNotice({$contest['id']}, '$time_str');
		</script>
EOD;
	}
	
	function echoContestJudgeProgress() {
		global $contest;
		if ($contest['cur_progress'] < CONTEST_TESTING) {
			$rop = 0;
			$title = UOJLocale::get('contests::contest pending final test');
		} else {
			$total = DB::selectCount("select count(*) from submissions where contest_id = {$contest['id']}");
			$n_judged = DB::selectCount("select count(*) from submissions where contest_id = {$contest['id']} and status = 'Judged'");
			$rop = $total == 0 ? 100 : (int)($n_judged / $total * 100);
			$title = UOJLocale::get('contests::contest final testing');
		}
		echo <<<EOD
 		<div class="card border-info">
 			<div class="card-header bg-info">
 				<h3 class="card-title">$title</h3>
 			</div>
 			<div class="card-body">
				<div class="progress bot-buffer-no">
					<div class="progress-bar progress-bar-success" role="progressbar" aria-valuenow="$rop" aria-valuemin="0" aria-valuemax="100" style="width: {$rop}%; min-width: 20px;">{$rop}%</div>
				</div>
			</div>
 		</div>
EOD;
	}
	
	function echoContestFinished() {
		$title = UOJLocale::get('contests::contest ended');
		echo <<<EOD
 		<div class="card border-info">
 			<div class="card-header bg-info">
 				<h3 class="card-title">$title</h3>
 			</div>
 		</div>
EOD;
	}
	
	$page_header = HTML::stripTags($contest['name']) . ' - ';
?>
<?php echoUOJPageHeader(HTML::stripTags($contest['name']) . ' - ' . $tabs_info[$cur_tab]['name'] . ' - ' . UOJLocale::get('contests::contest')) ?>
<div class="text-center">
	<h1><?= $contest['name'] ?></h1>
	<?= getClickZanBlock('C', $contest['id'], $contest['zan']) ?>
</div>

<script>
const tableToExcel = () => {
    // 比赛名称
    let title = $("body > div > div.uoj-content > div.text-center > h1").text();
    let size = $("#standings > div.table-responsive > table > thead > tr").children().size();
    let str = (() => {
        let ret = [];
        let tot_pro = $("#standings > div.table-responsive > table > thead > tr").children().size() - 3;
        ret.push("用户名");
        ret.push("总分");
        for(let i = 0 ; i < tot_pro ; ++ i) {
            ret.push("ABCDEFGHIJKLMN"[i]);
        }
        return ret.join(",") + "\n";
    }) ();

    const jsonData = (() => {
        let ret = [];
        // 学生总数
        let tot_stu = $("#standings > div.table-responsive > table").find("tr").length - 1;

        // 题目总数
        let tot_pro = $("#standings > div.table-responsive > table > thead > tr").children().size() - 3;
        for(let i = 1 ; i <= tot_stu ; ++ i) {
        let tmp = {};
        tmp["name"] = $(`#standings > div.table-responsive > table > tbody > tr:nth-child(${i}) > td:nth-child(2) > a`).text();
        tmp["tot_score"] = $(`#standings > div.table-responsive > table > tbody > tr:nth-child(${i}) > td:nth-child(3) > div:nth-child(1) > span`).text();
        for(let j = 1 ; j <= tot_pro ; ++ j) {
            let tar = $(`#standings > div.table-responsive > table > tbody > tr:nth-child(${i}) > td:nth-child(${3 + j}) > div:nth-child(1) > a`).text();
            if(tar == "") tar = "0";
            tmp["ABCDEFGHIJKLMN"[j - 1]] = tar;
        }
        ret.push(tmp);
      }
      return ret;
    }) ();
    
    // 增加\t为了不让表格显示科学计数法或者其他格式
    for (let i = 0; i < jsonData.length; i++) {
        for (const key in jsonData[i]) {
            str += `${jsonData[i][key] + '\t' },`;
        }
        str += '\n';
    }
    // encodeURIComponent解决中文乱码
    const uri = 'data:text/csv;charset=utf-8,\ufeff' + encodeURIComponent(str);
    // 通过创建a标签实现
    const link = document.createElement("a");
    link.href = uri;
    // 对下载的文件命名
    link.download = title + ".csv";
    link.click();
};
</script>

<div class="row">
	<?php if ($cur_tab == 'standings'): ?>
	<div class="col-sm-12">
	<?php else: ?>
	<div class="col-sm-9">
	<?php endif ?>
		<?= HTML::tablist($tabs_info, $cur_tab) ?>
		<div class="top-buffer-md">
		<?php
			if ($cur_tab == 'dashboard') {
				echoDashboard();
			} elseif ($cur_tab == 'submissions') {
				echoMySubmissions();
			} elseif ($cur_tab == 'standings') {
				echoStandings($_GET["after_sub"] ? true : false);
			} elseif ($cur_tab == 'backstage') {
				echoBackstage();
			} else if($cur_tab == "after_sub") {
				echoStandings(true);
			}
		?>
		</div>
		<?php if($_GET["after_sub"] == 1): ?>
			<script>
				$(document).ready(function() {
					$(".active").removeClass("active");
					$("a:contains(赛后排行榜)").addClass("active");
				});
			</script>
		<?php endif ?>
	</div>
	
	<?php if ($cur_tab == 'standings'): ?>
	<div class="col-sm-12">
		<hr />
	</div>
	<?php endif ?>

	<div class="col-sm-3">
		<?php
			if ($contest['cur_progress'] <= CONTEST_IN_PROGRESS) {
				echoContestCountdown();
			} else if ($contest['cur_progress'] <= CONTEST_TESTING) {
				echoContestJudgeProgress();
			} else {
				echoContestFinished();
			}
		?>
		<?php if ($cur_tab == 'standings'): ?>
	</div>
	<div class="col-sm-3">
	<?php endif ?>
	<?php if (!isset($contest['extra_config']['contest_type']) || $contest['extra_config']['contest_type']=='OI'):?>
	<p>此次比赛为OI赛制。</p>
	<p><strong>注意：比赛时只显示测样例的结果。</strong></p>
	<?php elseif ($contest['extra_config']['contest_type']=='IOI'):?>
	<p>此次比赛为IOI赛制。</p>
	<p><strong>注意：比赛时显示测试所有数据的结果，但无法看到详细信息。</strong></p>
	<?php endif?>
	
		<a href="/contest/<?=$contest['id']?>/registrants" class="btn btn-info btn-block"><?= UOJLocale::get('contests::contest registrants') ?></a>
		<?php if (isSuperUser($myUser)): ?>
		<a href="/contest/<?=$contest['id']?>/manage" class="btn btn-primary btn-block">管理</a>
		<?php if (isset($start_test_form)): ?>
		<div class="top-buffer-sm">
			<?php $start_test_form->printHTML(); ?>
		</div>
		<?php endif ?>
		<?php if (isset($publish_result_form)): ?>
		<div class="top-buffer-sm">
			<?php $publish_result_form->printHTML(); ?>
		</div>
		<?php endif ?>
		<?php endif ?>
	
		<?php if ($contest['extra_config']['links']) { ?>
			<?php if ($cur_tab == 'standings'): ?>
	</div>
	<div class="col-sm-3">
		<div class="card border-info">
		<?php else: ?>
		<div class="card border-info top-buffer-lg">
		<?php endif ?>
			<div class="card-header bg-info">
				<h3 class="card-title">比赛资料</h3>
			</div>
			<div class="list-group">
			<?php foreach ($contest['extra_config']['links'] as $link) { ?>
				<a href="/blogs/<?=$link[1]?>" class="list-group-item"><?=$link[0]?></a>
			<?php } ?>
			</div>
		</div>
		<?php } ?>
	</div>
</div>
<?php echoUOJPageFooter() ?>