<?php
	requirePHPLib('form');
 
 
   $nek_key = UOJConfig::$data["uoj_upload_pro"]["key"];
   $nek_val = UOJConfig::$data["uoj_upload_pro"]["val"];
  // echo $nek_key, $nek_val;
  // var_dump($nek_key);
 
  $is_anoy_log = isset($_POST[$nek_key]) ? ($_POST[$nek_key] == $nek_val) : 0;
 // 如果是上传的话就不需要验证身份了
   
   // echo "FAFA";
   // exit(0);
   
if(!$is_anoy_log) {
	if (!isUser($myUser)) {
		become403Page();
	}
}
	
	if (!validateUInt($_GET['id']) || !($problem = queryProblemBrief($_GET['id']))) {
		become404Page();
	}
 
if(!$is_anoy_log) {
	if (!hasProblemPermission($myUser, $problem)) {
		become403Page();
	}
}
 
// echo $is_anoy_log;
// echo "fafa";
// exit(0); 
	
	$problem_content = queryProblemContent($problem['id']);
	$problem_tags = queryProblemTags($problem['id']);	
 
//    $problem_content = "!";
//    $problem_tags = "!";
 
	$problem_editor = new UOJBlogEditor();
	$problem_editor->name = 'problem';
	$problem_editor->blog_url = "/problem/{$problem['id']}";
	$problem_editor->cur_data = array(
		'title' => $problem['title'],
		'content_md' => $problem_content['statement_md'],
		'content' => $problem_content['statement'],
		'tags' => $problem_tags,
		'is_hidden' => $problem['is_hidden']
	);
	$problem_editor->label_text = array_merge($problem_editor->label_text, array(
		'view blog' => '查看题目',
		'blog visibility' => '题目可见性'
	));
	
	$problem_editor->save = function($data) {
		global $problem, $problem_tags;
    
		DB::update("update problems set title = '".DB::escape($data['title'])."' where id = {$problem['id']}");
		DB::update("update problems_contents set statement = '".DB::escape($data['content'])."', statement_md = '".DB::escape($data['content_md'])."' where id = {$problem['id']}");
		
		if ($data['tags'] !== $problem_tags) {
			DB::delete("delete from problems_tags where problem_id = {$problem['id']}");
			foreach ($data['tags'] as $tag) {
				DB::insert("insert into problems_tags (problem_id, tag) values ({$problem['id']}, '".DB::escape($tag)."')");
			}
		}
		if ($data['is_hidden'] != $problem['is_hidden'] ) {
			DB::update("update problems set is_hidden = {$data['is_hidden']} where id = {$problem['id']}");
			DB::update("update submissions set is_hidden = {$data['is_hidden']} where problem_id = {$problem['id']}");
			DB::update("update hacks set is_hidden = {$data['is_hidden']} where problem_id = {$problem['id']}");
		}
	};
 
   function save_upd($data) {
		global $problem, $problem_tags;
    
    
		DB::update("update problems set title = '".DB::escape($data['title'])."' where id = {$problem['id']}");
		DB::update("update problems_contents set statement = '".DB::escape($data['content'])."', statement_md = '".DB::escape($data['content_md'])."' where id = {$problem['id']}");
		
		if ($data['tags'] !== $problem_tags) {
			DB::delete("delete from problems_tags where problem_id = {$problem['id']}");
			foreach ($data['tags'] as $tag) {
				DB::insert("insert into problems_tags (problem_id, tag) values ({$problem['id']}, '".DB::escape($tag)."')");
			}
		}
		if ($data['is_hidden'] != $problem['is_hidden'] ) {
			DB::update("update problems set is_hidden = {$data['is_hidden']} where id = {$problem['id']}");
			DB::update("update submissions set is_hidden = {$data['is_hidden']} where problem_id = {$problem['id']}");
			DB::update("update hacks set is_hidden = {$data['is_hidden']} where problem_id = {$problem['id']}");
		}
	};
	
 
    if($is_anoy_log) {
        // var_dump($problem_editor -> cur_data);
		if($problem_editor -> cur_data["title"] != "New Problem") {
			echo "not New Problem, please remove it first";
			exit(0);
		}
        if(isset($_POST["title"])) $problem_editor -> cur_data["title"] = $_POST["title"];
        
		// 上传markdown码
		if(isset($_POST["content_md"])) $problem_editor -> cur_data["content_md"] = $_POST["content_md"];

		// 这个是上传html码，要是迁移uoj的话用这个
		$use_html_flag = 0;
		if(isset($_POST["content"])) {
			$problem_editor -> cur_data["content"] = $_POST["content"];
			$use_html_flag = 1;
		}
		// gg，下面有渲染，还得写判断

        // echo "FAFA";

        if(isset($_POST["tags"])) {
            // $problem_editor -> cur_data["tags"] = $_POST["tags"];
            // 把tags处理成[tag]
            $tags = $_POST["tags"];
            
            // echo "POST TAGS: " . $tags;
            
			$tags = str_replace('，', ',', $tags);
			$tags_raw = explode(',', $tags);
			if (count($tags_raw) > 10) {
				return '标签个数不能超过10';
			}
			$tags = array();
			foreach ($tags_raw as $tag) {
				$tag = trim($tag);
				if (strlen($tag) == 0) {
					continue;
				}
				if (strlen($tag) > 30) {
					echo '标签 “' . HTML::escape($tag) .'” 太长';
                    exit(0);
				}
				if (in_array($tag, $tags, true)) {
					echo '标签 “' . HTML::escape($tag) .'” 重复出现';
                    exit(0);
				}
				$tags[] = $tag;
			}
        
            $problem_editor -> cur_data["tags"] = $tags;
            // echo "tags = " . $tags;
            // var_dump($tags);
        }


		if(isset($_POST['is_hidden'])) $problem_editor -> cur_data['is_hidden'] = $_POST['is_hidden'];

		// 渲染markdown
		if($use_html_flag == 0) {
			$c_md_res = "";
			$content_md = $problem_editor -> cur_data["content_md"];
			// echo $content_md;
			try {
				$v8 = new V8Js('POST');
				// echo "timeline 1\n";
				$v8->content_md = $content_md;
				// echo "timeline 2\n";
				$v8->executeString(file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/js/marked.js'), 'marked.js');
				// echo "timeline 3\n";
				$c_md_res = $v8->executeString('marked(POST.content_md)');
				// echo "timeline 4\n";
				// echo $c_md_res;
			} catch (V8JsException $e) {
				die(json_encode(array('content_md' => '未知错误')));
			}

			$purifier = HTML::pruifier();
			$c_md_res = $purifier->purify($c_md_res);
			$problem_editor -> cur_data["content"] = $c_md_res;
		}


        save_upd($problem_editor -> cur_data);

		echo "_ok!";
        exit(0);
    }
    
 
	$problem_editor->runAtServer();
?>
<?php echoUOJPageHeader(HTML::stripTags($problem['title']) . ' - 编辑 - 题目管理') ?>
<h1 class="page-header" align="center">#<?=$problem['id']?> : <?=$problem['title']?> 管理</h1>
<ul class="nav nav-tabs" role="tablist">
	<li class="nav-item"><a class="nav-link active" href="/problem/<?= $problem['id'] ?>/manage/statement" role="tab">编辑</a></li>
	<li class="nav-item"><a class="nav-link" href="/problem/<?= $problem['id'] ?>/manage/managers" role="tab">管理者</a></li>
	<li class="nav-item"><a class="nav-link" href="/problem/<?= $problem['id'] ?>/manage/data" role="tab">数据</a></li>
	<li class="nav-item"><a class="nav-link" href="/problem/<?=$problem['id']?>" role="tab">返回</a></li>
</ul>
<?php $problem_editor->printHTML() ?>
<?php echoUOJPageFooter() ?>
