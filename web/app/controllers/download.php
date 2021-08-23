<?php
	requirePHPLib('judger');

	if (!isUser($myUser)) {
		become403Page();
	}
 
 
    // 只有管理员可以下载数据
    // if (!isSuperUser($myUser)) {
	//	become403Page();
	// }
 
    $is_super_user = isSuperUser($myUser);

	switch ($_GET['type']) {
		case 'problem':
			if (!validateUInt($_GET['id']) || !($problem = queryProblemBrief($_GET['id']))) {
				become404Page();
			}
        
			
           // var_dump(isProblemVisibleToUser($problem, $myUser));
           // exit(0);
        
			$visible = isProblemVisibleToUser($problem, $myUser);
			if (!$visible && $myUser != null) {
				$result = DB::query("select contest_id from contests_problems where problem_id = {$_GET['id']}");
				while (list($contest_id) = DB::fetch($result, MYSQLI_NUM)) {
					$contest = queryContest($contest_id);
					genMoreContestInfo($contest);
					if ($contest['cur_progress'] != CONTEST_NOT_STARTED && hasRegistered($myUser, $contest) && queryContestProblemRank($contest, $problem)) {
						$visible = true;
					}
				}
			}
        
        
          // var_dump($_GET['normal']);exit(0);
          
          $normal_user_download = isProblemVisibleToUser($problem, $myUser) && $_GET['normal'] == 1;
          if($normal_user_download) {
            $visible = 1;
          }
        
        
        // echo "forehead"; exit(0);
        
			if (!$visible) {
        		become404Page();
			}
        
          // echo "TMP!"; exit(0);
        
			$id = $_GET['id'];
			
			$file_name = "/var/uoj_data/$id/download.zip";
			$download_name = "problem_$id.zip";
        
          
        
            if($is_super_user || $normal_user_download) {
                $file_name = "/var/uoj_data/$id.zip";
                // var_dump($file_name); exit(0);
                
                
                if(validateUInt($_GET['cnt']) && isset($_GET['inout'])) {
                    $cnt = (int) $_GET['cnt'];
                    $problem_conf = getUOJConf("/var/uoj_data/$id/problem.conf");
				    if ($problem_conf === -1) {
                        become404Page();
					    // throw new UOJFileNotFoundException("problem.conf");
				    } elseif ($problem_conf === -2) {
					    become404Page();
                        // throw new UOJProblemConfException("syntax error");
				    }
        
                    $n_tests = getUOJConfVal($problem_conf, 'n_tests', 10);
                    // var_dump($n_tests); exit(0);
                    if($cnt < 1 || $cnt > $n_tests) {
                        become404Page();
                    }
                    
                    if($_GET['inout'] == 'in') {
                      $file_name = "/var/uoj_data/$id/" . getUOJProblemInputFileName($problem_conf, $cnt);
                      $download_name = getUOJProblemInputFileName($problem_conf, $cnt);
                    } else if($_GET['inout'] == 'out') {
                      $file_name = "/var/uoj_data/$id/" . getUOJProblemOutputFileName($problem_conf, $cnt);
                      $download_name = getUOJProblemOutputFileName($problem_conf, $cnt);
                    } else {
                      become404Page();
                    }
                    
                    // echo $n_tests;
                     // echo $file_name ;
                     // exit(0);
                    
                }
                
            }
        
			break;
		case 'testlib.h':
			$file_name = "/opt/uoj/judger/uoj_judger/include/testlib.h";
			$download_name = "testlib.h";
			break;
		default:
			become404Page();
	}
 
	$finfo = finfo_open(FILEINFO_MIME);
	$mimetype = finfo_file($finfo, $file_name);
	if ($mimetype === false) {
		become404Page();
	}
	finfo_close($finfo);
	
	header("X-Sendfile: $file_name");
	header("Content-type: $mimetype");
	header("Content-Disposition: attachment; filename=$download_name");
?>