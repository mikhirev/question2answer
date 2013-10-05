<?php

/*
	Question2Answer (c) Gideon Greenspan

	http://www.question2answer.org/

	
	File: qa-include/qa-page-admin-recalc.php
	Version: See define()s at top of qa-include/qa-base.php
	Description: Handles admin-triggered recalculations if JavaScript disabled


	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.
	
	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	More about this license: http://www.question2answer.org/license.php
*/

	if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
		header('Location: ../');
		exit;
	}

	require_once QA_INCLUDE_DIR.'qa-app-admin.php';
	require_once QA_INCLUDE_DIR.'qa-app-recalc.php';

	
//	Check we have administrative privileges

	if (!qa_admin_check_privileges($qa_content))
		return $qa_content;

	
//	Find out the operation

	$allowstates=array(
		'dorecountposts',
		'doreindexcontent',
		'dorecalcpoints',
		'dorefillevents',
		'dorecalccategories',
		'dodeletehidden',
		'doblobstodisk',
		'doblobstodb',
	);
	
	$recalcnow=false;
	
	foreach ($allowstates as $allowstate)
		if (qa_post_text($allowstate) || qa_get($allowstate)) {
			$state=$allowstate;
			$code=qa_post_text('code');
			
			if (isset($code) && qa_check_form_security_code('admin/recalc', $code))
				$recalcnow=true;
		}
			
	if ($recalcnow) {
?>

<html>
	<head>
		<meta http-equiv="content-type" content="text/html; charset=utf-8">
	</head>
	<body>
		<tt>

<?php

		while ($state) {
			set_time_limit(60);
			
			$stoptime=time()+2; // run in lumps of two seconds...
			
			while ( qa_recalc_perform_step($state) && (time()<$stoptime) )
				;
			
			echo qa_html(qa_recalc_get_message($state)).str_repeat('    ', 1024)."<br>\n";

			flush();
			sleep(1); // ... then rest for one
		}

?>
		</tt>
		
		<a href="<?php echo qa_path_html('admin/stats')?>"><?php echo qa_html(_('Administration center')).' - '.qa_html(_('Stats'))?></a>
	</body>
</html>

<?php
		qa_exit();
	
	} elseif (isset($state)) {
		$qa_content=qa_content_prepare();

		$qa_content['title']=qa_html(_('Administration center'));
		$qa_content['error']=qa_html(_('Please click again to confirm'));
		
		$qa_content['form']=array(
			'tags' => 'method="post" action="'.qa_self_html().'"',
		
			'style' => 'wide',
			
			'buttons' => array(
				'recalc' => array(
					'tags' => 'name="'.qa_html($state).'"',
					'label' => qa_html(_('Please click again to confirm')),
				),
			),
			
			'hidden' => array(
				'code' => qa_get_form_security_code('admin/recalc'),
			),
		);
		
		return $qa_content;
	
	} else {
		require_once QA_INCLUDE_DIR.'qa-app-format.php';
		
		$qa_content=qa_content_prepare();

		$qa_content['title']=qa_html(_('Administration center'));
		$qa_content['error']=qa_html(_('Page not found'));
		
		return $qa_content;
	}
			

/*
	Omit PHP closing tag to help avoid accidental output
*/
