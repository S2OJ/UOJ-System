<?php
    requirePHPLib('form');
	requirePHPLib('judger');
    requirePHPLib('data');
    
    $group_id = $_GET['id'];
    $group = queryGroup($group_id);

    if (isSuperUser($myUser)) {
        $group_editor = new UOJBlogEditor();
        $group_editor->name = 'group';
		$group_editor->blog_url = null;
		$group_editor->cur_data = array(
			'title' => $group['title'],
			'tags' => array(),
			'is_hidden' => $group['is_hidden']
        );
        $group_editor->label_text = array_merge($group_editor->label_text, array(
			'view blog' => '保存小组信息',
			'blog visibility' => '小组可见性'
        ));
        $group_editor->show_editor = false;
        $group_editor->show_tags = false;

        $group_editor->save = function($data) {
			global $group_id, $group;
			DB::update("update groups set title = '".DB::escape($data['title'])."' where id = {$group_id}");

			if ($data['is_hidden'] != $group['is_hidden'] ) {
				DB::update("update groups set is_hidden = {$data['is_hidden']} where id = {$group_id}");
			}
		};

        $group_editor->runAtServer();
    }
    
    if (isSuperUser($myUser)) {
		$add_new_user_form = new UOJForm('add_new_user');
		$add_new_user_form->addInput('new_username', 'text', '用户名', '', 
			function ($x) {
				global $myUser, $group_id;

				if (!isSuperUser($myUser)) {
					return '只有超级用户可以编辑小组';
				}

				if (!validateUsername($x)) return '用户名不合法';
				$user = queryUser($x);
				if (!$user) return '用户不存在';

				if (queryUserInGroup($group_id, $x)) {
					return '该用户已经在小组中';
				}
				
				return '';
			},
			null
        );
        $add_new_user_form->submit_button_config['align'] = 'compressed';
		$add_new_user_form->submit_button_config['text'] = '添加到小组';
		$add_new_user_form->handle = function() {
			global $group_id, $myUser;
			$username = $_POST['new_username'];

			DB::insert("insert into groups_users (group_id, username) values ({$group_id}, '{$username}')");
		};
		$add_new_user_form->runAtServer();
    }

    if (isSuperUser($myUser)) {
		$delete_user_form = new UOJForm('delete_user');
		$delete_user_form->addInput('del_username', 'text', '用户名', '', 
			function ($x) {
				global $myUser, $group_id;

				if (!isSuperUser($myUser)) {
					return '只有超级用户可以编辑小组';
				}

				if (!validateUsername($x)) return '用户名不合法';
				if (!queryUserInGroup($group_id, $x)) {
					return '该用户不在小组中';
				}
				
				return '';
			},
			null
        );
        $delete_user_form->submit_button_config['align'] = 'compressed';
		$delete_user_form->submit_button_config['text'] = '从小组中删除';
		$delete_user_form->handle = function() {
			global $group_id, $myUser;
			$username = $_POST['del_username'];

			DB::query("delete from groups_users where username='{$username}' and group_id={$group_id}");
		};
		$delete_user_form->runAtServer();
    }
    
?>
<?php echoUOJPageHeader(UOJLocale::get('groups')) ?>
<?php
	if (isSuperUser($myUser)) {
		echo '<h5>编辑小组信息</h5>';
		echo '<div class="mb-4">';
		$group_editor->printHTML();
		echo '</div>';
	}
?>

<?php 
	if (isSuperUser($myUser)) {
		echo '<h5>添加用户到小组</h5>';
        $add_new_user_form->printHTML(); 
        echo '<h5>从小组中删除用户</h5>';
		$delete_user_form->printHTML(); 
	}
?>

<h2 style="margin-top: 24px"><?= $group['title'] ?></h2>
<p>(<b>小组 ID</b>: <?= $group['id'] ?>)</p>

<div class="row">
	<div class="col-sm-12 mt-4">
		<h5><?= UOJLocale::get('news') ?></h5>
		<ul>
		<?php
			$current_ac = queryGroupCurrentAC($group['id']);
			foreach ($current_ac as $ac) {
				echo '<li><a href="/user/profile/', $ac['submitter'], '">', $ac['submitter'], '</a> 解决了问题 <a href="/problem/', $ac['problem_id'], '">', $ac['problem_title'], '</a>', ' <span class="time">(', $ac['submit_time'] ,')</span></li>';
			}
		?>
		</ul>
	</div>
</div>

<div class="row">
	<div class="col-sm-12 mt-4">
		<h5><?= UOJLocale::get('top rated') ?></h5>
		<?php echoRanklist(array('echo_full' => '', 'group_id' => $group_id)) ?>
	</div>
</div>

<div class="row">
	<div class="col-sm-12 mt-4">
		<h5><?= UOJLocale::get('top solver') ?></h5>
		<?php echoRanklist(array('echo_full' => '', 'group_id' => $group_id, 'by_accepted' => '')) ?>
	</div>
</div>

<?php echoUOJPageFooter() ?>
