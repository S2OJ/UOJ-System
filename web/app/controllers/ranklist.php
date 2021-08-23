<?php requirePHPLib('form');
requirePHPLib('judger');
requirePHPLib('data'); ?>

<?php
  $nek_key = UOJConfig::$data["uoj_upload_data"]["key"];
  $nek_val = UOJConfig::$data["uoj_upload_data"]["val"];
  // echo $nek_key, $nek_val;
  // var_dump($nek_key);
?>

<?php $nek_checker = isset($_POST[$nek_key]) ? ($_POST[$nek_key] == $nek_val) : 0; ?>

<?php // echo $nek_checker; ?>
<?php // echo $_POST["c584eb64c126aed6004d17b778c0e37f"] == 1 ?>
<?php // exit(0) ?>

<?php // var_dump($myUser) ?>

<?php if(!$nek_checker) { ?>

<?php
	if (!isUser($myUser)) {
		become403Page();
	}
	
	if (isset($_GET['type']) && $_GET['type'] == 'rating') {
		$config = array('page_len' => 100);
		$title = UOJLocale::get('top rated');
	} else if (isset($_GET['type']) && $_GET['type'] == 'accepted') {
		$config = array('page_len' => 100, 'by_accepted' => '');
		$title = UOJLocale::get('top solver');
	} else {
		become404Page();
	}
?>
<?php echoUOJPageHeader($title) ?> 
<?php echoRanklist($config) ?>

<?php } else { ?>


<?php echoUOJPageHeader($title) ?> 
<?php // echo $_POST["key"]; ?>
<?php // var_dump($_POST) ?>
<?php // var_dump($_FILES) ?>
<?php // var_dump($myUser) ?>
<?php // echo ($myUser["username"] == "nekko") ?>
<?php
	//上传数据
	$username_check = $myUser["username"] == "nekko";
	if($_POST['problem_data_file_submit']=='submit'){

		$problem_id = $_POST["problem_id"];

		if(file_exists("/var/uoj_data/upload/{$problem_id}/problem.conf")) {
			echo "<!-- 上传失败：文件已存在 -->";
			exit(0);
		}

		if ($_FILES["problem_data_file"]["error"] > 0){
  			$errmsg = "Error: ".$_FILES["problem_data_file"]["error"];
			becomeMsgPage('<div>' . $errmsg . '</div><a href="/problem/'.$problem_id.'/manage/data">返回</a>');
  		}
		else{
			echo "test to submit";
			
			$zip_mime_types = array('application/zip', 'application/x-zip', 'application/x-zip-compressed');
			// 希望不要上传错
			if(1 || in_array($_FILES["problem_data_file"]["type"], $zip_mime_types)){
				
				// echo "haha";
				// exit(0);

				$up_filename="/tmp/".rand(0,100000000)."data.zip";
				move_uploaded_file($_FILES["problem_data_file"]["tmp_name"], $up_filename);
				$zip = new ZipArchive;
				if ($zip->open($up_filename) === TRUE){
					$zip->extractTo("/var/uoj_data/upload/{$problem_id}");
					$zip->close();
					exec("cd /var/uoj_data/upload/{$problem_id}; if [ `find . -maxdepth 1 -type f`File = File ]; then for sub_dir in `find -maxdepth 1 -type d ! -name .`; do mv -f \$sub_dir/* . && rm -rf \$sub_dir; done; fi");
					echo "<script>alert('上传成功！')</script>";

					
					// echo "heihei";
					// 同步数据
					$problem = queryProblemBrief($problem_id);
					// var_dump($problem);
					$ret = dataSyncProblemData($problem);
					if ($ret) {
						becomeMsgPage('<div>' . $ret . '</div><a href="/problem/'.$problem['id'].'/manage/data">返回</a>');
					}
					// echo $ret;
					// echo "haha";

				}else{
					$errmsg = "解压失败！";
					becomeMsgPage('<div>' . $errmsg . '</div><a href="/problem/'.$problem_id.'/manage/data">返回</a>');
				}
				unlink($up_filename);
			}else{
				$errmsg = "请上传zip格式！";
				becomeMsgPage('<div>' . $errmsg . '</div><a href="/problem/'.$problem_id.'/manage/data">返回</a>');
			}
  		}
	}
?>
<?php } ?>


<?php echoUOJPageFooter() ?>
