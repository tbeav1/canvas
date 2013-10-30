<?php

require_once('../.ignore.grading-scheme-authentication.inc.php');
require_once('config.inc.php');

require_once('../canvas-api.inc.php');
require_once('../page-generator.inc.php');

debugFlag('START');


$courseApi = new CanvasApiProcess(CANVAS_API_URL, CANVAS_API_TOKEN);
$assignmentApi = new CanvasApiProcess(CANVAS_API_URL, CANVAS_API_TOKEN);

/* get all courses in our Academics sub-account */
$courses = $courseApi->get('/accounts/' . AFFECTED_SUBACCOUNT_ID . '/courses'); // TODO make this a GET parameter?

do {

	foreach($courses as $course) {
		$assignments = $assignmentApi->get("/courses/{$course['id']}/assignments");
		
		do {
			foreach ($assignments as $assignment) {
				if ($assignment['grading_type'] == 'letter_grade' && !isset($assignment['grading_standard_id'])) {
					$assignment = callCanvasApi(
						CANVAS_API_PUT,
						"/courses/{$course['id']}/assignments/{$assignment['id']}",
						array(
							'assignment[grading_standard_id]' => STANDARD_GRADING_SCHEME_ID // the preferred standard grading scheme
						)
					);
				}
			}
		} while ($assignments = $assignmentApi->nextPage());
	}
	
} while ($courses = $courseApi->nextPage());

debugFlag('FINISH');

?>