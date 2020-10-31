<div id="standings"></div>

<div class="table-responsive">
	<table id="standings-table" class="table table-bordered table-striped table-text-center table-vertical-middle"></table>
</div>

<script type="text/javascript">
contests=<?=json_encode($contests)?>;
overall_standings=<?=json_encode($overall_standings)?>;
$(document).ready(showOverallStandings());
</script>
