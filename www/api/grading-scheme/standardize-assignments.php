<?php

require_once('../.ignore.stmarksschool-test-authentication.inc.php');
require_once('config.inc.php');

require_once('../canvas-api.inc.php');
require_once('../page-generator.inc.php');

debugFlag('START');

/* get all courses in our Academics sub-account */
$courses = callCanvasApiPaginated(
	CANVAS_API_GET,
	'/accounts/132/courses'
);

do {

	foreach($courses as $course) {
		// FIXME: need to paginate assignments
		$assignments = callCanvasApi(
			CANVAS_API_GET,
			"/courses/{$course['id']}/assignments"
		);
		
		foreach ($assignments as $assignment) {
			if ($assignment['grading_type'] == 'letter_grade' && !isset($assignment['grading_standard_id'])) {
				$assignment = callCanvasApi(
					CANVAS_API_PUT,
					"/courses/{$course['id']}/assignments/{$assignment['id']}",
					array(
						'assignment[grading_standard_id]' => '1' // our St. Mark's grading scheme
					)
				);
			}
		}
	}
	
} while ($courses = callCanvasApiNextPage());

debugFlag('FINISH');

?>