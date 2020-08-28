<?php
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
<?php echoUOJPageFooter() ?>
