<div class="navbar navbar-light navbar-expand-md bg-light mb-4" role="navigation">
	<a class="navbar-brand" href="<?= HTML::blog_url(UOJContext::userid(), '/')?>"><?= UOJContext::userid() ?></a>
	<button type="button" class="navbar-toggler collapsed" data-toggle="collapse" data-target=".navbar-collapse">
		<span class="sr-only">导航</span>
		<span class="navbar-toggler-icon"></span>
	</button>
	<div class="navbar-collapse collapse">
		<ul class="nav navbar-nav">
			<li class="nav-item"><a class="nav-link" href="<?= HTML::blog_url(UOJContext::userid(), '/archive')?>"><i class="fa fa-quote-left"></i> 日志</a></li>
			<!-- <li class="nav-item"><a class="nav-link" href="<?= HTML::blog_url(UOJContext::userid(), '/aboutme')?>"><i class="fa fa-id-card"></i> 关于我</a></li> -->
			<li class="nav-item"><a class="nav-link" href="<?= HTML::url('/') ?>"><i class="fa fa-link"></i> <?= UOJConfig::$data['profile']['oj-name-short'] ?></a></li>
		</ul>
	</div><!--/.nav-collapse -->
</div>
<script type="text/javascript">
	var uojBlogUrl = '<?= HTML::blog_url(UOJContext::userid(), '')?>';
	var zan_link = uojBlogUrl;
</script>