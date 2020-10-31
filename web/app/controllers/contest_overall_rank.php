<?php
	requirePHPLib('form');

	if (!isUser($myUser)) {
		become403Page();
    }

    if (!isset($_GET['contest_ids'])) {
        die('contest_ids not passed');
    }
    $ids = explode(",", $_GET['contest_ids']);
    $contests = [];
    foreach ($ids as $id) {
        $contest = queryContest($id);
        if (!$contest) {
            become404Page();
        }
        genMoreContestInfo($contest);
        if ($contest['cur_progress'] != CONTEST_FINISHED) {
            become404Page();
        }
        $contests[] = $contest;
    }
?>
<?php echoUOJPageHeader(UOJLocale::get('contests')) ?>

<?php
    // print(json_encode($contests));
    // echo "<br><br>";

    $usernames = [];
    $contest_scores = [];
    $contest_problems = [];

    foreach ($contests as $k => $contest) {
        $contest_data = queryContestData($contest);
        calcStandings($contest, $contest_data, $score, $standings, false);
        
        $contest_score = [];
        foreach ($score as $username => $problems) {
            $total_score = 0;
            foreach ($problems as $problem_no => $arr) {
                $total_score += $arr[0];
            }
            $contest_score[$username] = $total_score;
            $usernames[$username] = true;
        }

        $contest_scores[$k] = $contest_score;
        $contest_problems[$k] = count($contest_data['problems']);
    }

    $overall_standings = [];
    foreach ($usernames as $username => $_) {
        $total_score = 0;
        $total_problems = 0;
        $arr = [];
        foreach ($contests as $k => $_) {
            if (isset($contest_scores[$k][$username])) {
                $s = $contest_scores[$k][$username];
                $c = $contest_problems[$k];
                $total_score += $s;
                $total_problems += $c;
                $arr[] = array('score' => $s, 'problems' => $c);
            } else {
                $arr[] = null;
            }
        }
        $arr['total'] = array('score' => $total_score, 'problems' => $total_problems);
        $arr['username'] = $username;

        $user = queryUser($username);
        $rating = 1500;
        if ($user) {
            $rating = $user['rating'];
        }
        $arr['rating'] = $rating;

        $overall_standings[] = $arr;
    }

    usort($overall_standings, function($lhs, $rhs) {
        if ($lhs['total']['score'] != $rhs['total']['score']) {
            return $rhs['total']['score'] - $lhs['total']['score'];
        } else {
            return strcmp($lhs['username'], $rhs['username']);
        }
    });

    if (count($overall_standings) > 0) {
        $standing = 1;
        $overall_standings[0]['standing'] = $standing;
        foreach ($overall_standings as $k => $_) {
            if ($k == 0) {
                continue;
            }
            if ($overall_standings[$k]['total']['score'] != $overall_standings[$k - 1]['total']['score']) {
                $standing += 1;
            }
            $overall_standings[$k]['standing'] = $standing;
        }
    }

    uojIncludeView('contest-overall-standings', [
        'contests' => $contests,
        'overall_standings' => $overall_standings
    ]);

    // print(json_encode($overall_standings));
    // echo "<br><br>";
?>

<?php echoUOJPageFooter() ?>
