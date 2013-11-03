<?php
	
/*
	Question2Answer (c) Gideon Greenspan

	http://www.question2answer.org/

	
	File: qa-include/qa-page-admin-pages.php
	Version: See define()s at top of qa-include/qa-base.php
	Description: Controller for admin page for editing custom pages and external links


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
	require_once QA_INCLUDE_DIR.'qa-app-format.php';
	require_once QA_INCLUDE_DIR.'qa-db-selects.php';

	
//	Get current list of pages and determine the state of this admin page

	$pageid=qa_post_text('edit');
	if (!isset($pageid))
		$pageid=qa_get('edit');
		
	list($pages, $editpage)=qa_db_select_with_pending(
		qa_db_pages_selectspec(),
		isset($pageid) ? qa_db_page_full_selectspec($pageid, true) : null
	);
	
	if ((qa_clicked('doaddpage') || qa_clicked('doaddlink') || qa_get('doaddlink') || qa_clicked('dosavepage')) && !isset($editpage)) {
		$editpage=array('title' => qa_get('text'), 'tags' => qa_get('url'), 'nav' => qa_get('nav'), 'position' => 1);
		$isexternal=qa_clicked('doaddlink') || qa_get('doaddlink') || qa_post_text('external');
		
	} elseif (isset($editpage))
		$isexternal=$editpage['flags'] & QA_PAGE_FLAGS_EXTERNAL;
	

//	Check admin privileges (do late to allow one DB query)

	if (!qa_admin_check_privileges($qa_content))
		return $qa_content;
		
		
//	Define an array of navigation settings we can change, option name => language key
	
	$hascustomhome=qa_has_custom_home();
	
	$navoptions=array(
		'nav_home' => _('Home'),
		'nav_activity' => _('All Activity'),
		 $hascustomhome ? 'nav_qa_not_home' : 'nav_qa_is_home' => $hascustomhome ? _('Q&A') : _('Q&A (links to home page)'),
		'nav_questions' => _('Questions'),
		'nav_hot' => _('Hot!'),
		'nav_unanswered' => _('Unanswered'),
		'nav_tags' => _('Tags'),
		'nav_categories' => _('Categories'),
		'nav_users' => _('Users'),
		'nav_ask' => _('Ask a Question'),
	);
	
	$navpaths=array(
		'nav_home' => '',
		'nav_activity' => 'activity',
		'nav_qa_not_home' => 'qa',
		'nav_qa_is_home' => '',
		'nav_questions' => 'questions',
		'nav_hot' => 'hot',
		'nav_unanswered' => 'unanswered',
		'nav_tags' => 'tags',
		'nav_categories' => 'categories',
		'nav_users' => 'users',
		'nav_ask' => 'ask',
	);
	
	if (!qa_opt('show_custom_home'))
		unset($navoptions['nav_home']);
		
	if (!qa_using_categories())
		unset($navoptions['nav_categories']);

	if (!qa_using_tags())
		unset($navoptions['nav_tags']);


//	Process saving an old or new page

	$securityexpired=false;

	if (qa_clicked('docancel'))
		$editpage=null;

	elseif (qa_clicked('dosaveoptions') || qa_clicked('doaddpage') || qa_clicked('doaddlink')) {
		if (!qa_check_form_security_code('admin/pages', qa_post_text('code')))
			$securityexpired=true;
		else foreach ($navoptions as $optionname => $lang)
			qa_set_option($optionname, (int)qa_post_text('option_'.$optionname));

	} elseif (qa_clicked('dosavepage')) {
		require_once QA_INCLUDE_DIR.'qa-db-admin.php';
		require_once QA_INCLUDE_DIR.'qa-util-string.php';
		
		if (!qa_check_form_security_code('admin/pages', qa_post_text('code')))
			$securityexpired=true;
		else {
			$reloadpages=false;
			
			if (qa_post_text('dodelete')) {
				qa_db_page_delete($editpage['pageid']);
				
				$searchmodules=qa_load_modules_with('search', 'unindex_page');
				foreach ($searchmodules as $searchmodule)
					$searchmodule->unindex_page($editpage['pageid']);
				
				$editpage=null;
				$reloadpages=true;
			
			} else {
				$inname=qa_post_text('name');
				$inposition=qa_post_text('position');
				$inpermit=(int)qa_post_text('permit');
				$inurl=qa_post_text('url');
				$innewwindow=qa_post_text('newwindow');
				$inheading=qa_post_text('heading');
				$incontent=qa_post_text('content');
	
				$errors=array();
				
			//	Verify the name (navigation link) is legitimate
			
				if (empty($inname))
					$errors['name']=_('Please enter something in this field');
				elseif (qa_strlen($inname)>QA_DB_MAX_CAT_PAGE_TITLE_LENGTH)
					$errors['name']=sprintf(ngettext('Maximum length is %d character', 'Maximum length is %d characters', QA_DB_MAX_CAT_PAGE_TITLE_LENGTH), QA_DB_MAX_CAT_PAGE_TITLE_LENGTH);
							
				if ($isexternal) {
				
				//	Verify the url is legitimate (vaguely)
				
					if (empty($inurl))
						$errors['url']=_('Please enter something in this field');
					elseif (qa_strlen($inurl)>QA_DB_MAX_CAT_PAGE_TAGS_LENGTH)
						$errors['url']=sprintf(ngettext('Please provide more information - at least %d character', 'Please provide more information - at least %d characters', QA_DB_MAX_CAT_PAGE_TAGS_LENGTH), QA_DB_MAX_CAT_PAGE_TAGS_LENGTH);
	
				} else {
				
				//	Verify the heading is legitimate
				
					if (qa_strlen($inheading)>QA_DB_MAX_TITLE_LENGTH)
						$errors['heading']=sprintf(ngettext('Maximum length is %d character', 'Maximum length is %d characters', QA_DB_MAX_TITLE_LENGTH), QA_DB_MAX_TITLE_LENGTH);
				
				//	Verify the slug is legitimate (and try some defaults if we're creating a new page, and it's not)
						
					for ($attempt=0; $attempt<100; $attempt++) {
						switch ($attempt) {
							case 0:
								$inslug=qa_post_text('slug');
								if (!isset($inslug))
									$inslug=implode('-', qa_string_to_words($inname));
								break;
								
							case 1:
								$inslug=sprintf(_('page-%s'), $inslug);
								break;
								
							default:
								$inslug=sprintf(_('page-%s'), $attempt-1);
								break;
						}
						
						list($matchcategoryid, $matchpage)=qa_db_select_with_pending(
							qa_db_slugs_to_category_id_selectspec($inslug),
							qa_db_page_full_selectspec($inslug, false)
						);
						
						if (empty($inslug))
							$errors['slug']=_('Please enter something in this field');
						elseif (qa_strlen($inslug)>QA_DB_MAX_CAT_PAGE_TAGS_LENGTH)
							$errors['slug']=sprintf(ngettext('Maximum length is %d character', 'Maximum length is %d characters', QA_DB_MAX_CAT_PAGE_TAGS_LENGTH), QA_DB_MAX_CAT_PAGE_TAGS_LENGTH);
						elseif (preg_match('/[\\+\\/]/', $inslug))
							$errors['slug']=sprintf(_('The slug may not contain these characters: %s'), '+ /');
						elseif (qa_admin_is_slug_reserved($inslug))
							$errors['slug']=_('This slug is reserved for use by another page');
						elseif (isset($matchpage) && ($matchpage['pageid']!=@$editpage['pageid']))
							$errors['slug']=_('This is already being used by a page');
						elseif (isset($matchcategoryid))
							$errors['slug']=_('This is already being used by a category');
						else
							unset($errors['slug']);
						
						if (isset($editpage['pageid']) || !isset($errors['slug'])) // don't try other options if editing existing page
							break;
					}
				}
				
			//	Perform appropriate database action
		
				if (isset($editpage['pageid'])) { // changing existing page
					if ($isexternal)
						qa_db_page_set_fields($editpage['pageid'],
							isset($errors['name']) ? $editpage['title'] : $inname,
							QA_PAGE_FLAGS_EXTERNAL | ($innewwindow ? QA_PAGE_FLAGS_NEW_WINDOW : 0),
							isset($errors['url']) ? $editpage['tags'] : $inurl,
							null, null, $inpermit);
	
					else {
						$setheading=isset($errors['heading']) ? $editpage['heading'] : $inheading;
						$setslug=isset($errors['slug']) ? $editpage['tags'] : $inslug;
						$setcontent=isset($errors['content']) ? $editpage['content'] : $incontent;
						
						qa_db_page_set_fields($editpage['pageid'],
							isset($errors['name']) ? $editpage['title'] : $inname,
							0,
							$setslug, $setheading, $setcontent, $inpermit);
	
						$searchmodules=qa_load_modules_with('search', 'unindex_page');
						foreach ($searchmodules as $searchmodule)
							$searchmodule->unindex_page($editpage['pageid']);
						
						$indextext=qa_viewer_text($setcontent, 'html');
						
						$searchmodules=qa_load_modules_with('search', 'index_page');
						foreach ($searchmodules as $searchmodule)
							$searchmodule->index_page($editpage['pageid'], $setslug, $setheading, $setcontent, 'html', $indextext);
					}
					
					qa_db_page_move($editpage['pageid'], substr($inposition, 0, 1), substr($inposition, 1));
					
					$reloadpages=true;
		
					if (empty($errors))
						$editpage=null;
					else
						$editpage=@$pages[$editpage['pageid']];
		
				} else { // creating a new one
					if (empty($errors)) {
						if ($isexternal)
							$pageid=qa_db_page_create($inname, QA_PAGE_FLAGS_EXTERNAL | ($innewwindow ? QA_PAGE_FLAGS_NEW_WINDOW : 0), $inurl, null, null, $inpermit);
						else {
							$pageid=qa_db_page_create($inname, 0, $inslug, $inheading, $incontent, $inpermit);
						
							$indextext=qa_viewer_text($incontent, 'html');
							
							$searchmodules=qa_load_modules_with('search', 'index_page');
							foreach ($searchmodules as $searchmodule)
								$searchmodule->index_page($pageid, $inslug, $inheading, $incontent, 'html', $indextext);
						}
						
						qa_db_page_move($pageid, substr($inposition, 0, 1), substr($inposition, 1));
	
						$editpage=null;
						$reloadpages=true;
					}
				}
			}
			
			if ($reloadpages) {
				qa_db_flush_pending_result('navpages');
				$pages=qa_db_select_with_pending(qa_db_pages_selectspec());
			}
		}
	}
		
		
//	Prepare content for theme
	
	$qa_content=qa_content_prepare();

	$qa_content['title']=qa_html(_('Administration center').' - '._('Pages'));	
	$qa_content['error']=$securityexpired ? qa_html(_('Form security code expired - please try again')) : qa_admin_page_error();

	if (isset($editpage)) {
		$positionoptions=array();
		
		if (!$isexternal)
			$positionoptions['_'.max(1, @$editpage['position'])]=qa_html(_('No link'));
		
		$navlang=array(
			'B' => _('Before tabs at top'),
			'M' => _('After tabs at top'),
			'O' => _('Far end of tabs at top'),
			'F' => _('After links in footer'),
		);
		
		foreach ($navlang as $nav => $lang) {
			$previous=null;
			$passedself=false;
			$maxposition=0;
			
			foreach ($pages as $key => $page)
				if ($page['nav']==$nav) {
					if (isset($previous))
						$positionhtml=qa_html(sprintf(_('After "%s" tab'), $passedself ? $page['title'] : $previous['title']));
					else
						$positionhtml=qa_html($lang);
						
					if ($page['pageid']==@$editpage['pageid'])
						$passedself=true;
		
					$maxposition=max($maxposition, $page['position']);
					$positionoptions[$nav.$page['position']]=$positionhtml;
						
					$previous=$page;
				}
				
			if ((!isset($editpage['pageid'])) || $nav!=@$editpage['nav']) {
				$positionvalue=isset($previous) ? qa_html(sprintf(_('After "%s" tab'), $previous['title'])) : qa_html($lang);
				$positionoptions[$nav.(isset($previous) ? (1+$maxposition) : 1)]=$positionvalue;
			}
		}
		
		$positionvalue=@$positionoptions[$editpage['nav'].$editpage['position']];
		
		$permitoptions=qa_admin_permit_options(QA_PERMIT_ALL, QA_PERMIT_ADMINS, false, false);
		$permitvalue=@$permitoptions[isset($inpermit) ? $inpermit : $editpage['permit']];
		
		$qa_content['form']=array(
			'tags' => 'method="post" action="'.qa_path_html(qa_request()).'"',
			
			'style' => 'tall',
			
			'fields' => array(
				'name' => array(
					'tags' => 'name="name" id="name"',
					'label' => qa_html($isexternal ? _('Text of link:') : _('Name of page (also used for tab or link):')),
					'value' => qa_html(isset($inname) ? $inname : @$editpage['title']),
					'error' => qa_html(@$errors['name']),
				),
				
				'delete' => array(
					'tags' => 'name="dodelete" id="dodelete"',
					'label' => qa_html($isexternal ? _('Delete this link') : _('Delete this page')),
					'value' => 0,
					'type' => 'checkbox',
				),
				
				'position' => array(
					'id' => 'position_display',
					'tags' => 'name="position"',
					'label' => qa_html(_('Position:')),
					'type' => 'select',
					'options' => $positionoptions,
					'value' => $positionvalue,
				),
				
				'permit' => array(
					'id' => 'permit_display',
					'tags' => 'name="permit"',
					'label' => qa_html(_('Visible for:')),
					'type' => 'select',
					'options' => $permitoptions,
					'value' => $permitvalue,
				),
				
				'slug' => array(
					'id' => 'slug_display',
					'tags' => 'name="slug"',
					'label' => qa_html(_('Page slug (URL fragment):')),
					'value' => qa_html(isset($inslug) ? $inslug : @$editpage['tags']),
					'error' => qa_html(@$errors['slug']),
				),
				
				'url' => array(
					'id' => 'url_display',
					'tags' => 'name="url"',
					'label' => qa_html(_('URL of link - absolute or relative to Q2A root:')),
					'value' => qa_html(isset($inurl) ? $inurl : @$editpage['tags']),
					'error' => qa_html(@$errors['url']),
				),
				
				'newwindow' => array(
					'id' => 'newwindow_display',
					'tags' => 'name="newwindow"',
					'label' => qa_html(_('Open link in a new window')),
					'value' => (isset($innewwindow) ? $innewwindow : (@$editpage['flags'] & QA_PAGE_FLAGS_NEW_WINDOW)) ? 1 : 0,
					'type' => 'checkbox',
				),
				
				'heading' => array(
					'id' => 'heading_display',
					'tags' => 'name="heading"',
					'label' => qa_html(_('Heading to display at top of page:')),
					'value' => qa_html(isset($inheading) ? $inheading : @$editpage['heading']),
					'error' => qa_html(@$errors['heading']),
				),
				
				'content' => array(
					'id' => 'content_display',
					'tags' => 'name="content"',
					'label' => qa_html(_('Content to display in page - HTML allowed:')),
					'value' => qa_html(isset($incontent) ? $incontent : @$editpage['content']),
					'error' => qa_html(@$errors['content']),
					'rows' => 16,
				),
			),

			'buttons' => array(
				'save' => array(
					'label' => qa_html(isset($editpage['pageid']) ? _('Save Changes') : ($isexternal ? _('Add Link') : _('Add Page'))),
				),
				
				'cancel' => array(
					'tags' => 'name="docancel"',
					'label' => qa_html(_('Cancel')),
				),
			),
			
			'hidden' => array(
				'dosavepage' => '1', // for IE
				'edit' => @$editpage['pageid'],
				'external' => (int)$isexternal,
				'code' => qa_get_form_security_code('admin/pages'),
			),
		);
		
		if ($isexternal) {
			unset($qa_content['form']['fields']['slug']);
			unset($qa_content['form']['fields']['heading']);
			unset($qa_content['form']['fields']['content']);
		
		} else {
			unset($qa_content['form']['fields']['url']);
			unset($qa_content['form']['fields']['newwindow']);
		}
		
		if (isset($editpage['pageid']))
			qa_set_display_rules($qa_content, array(
				'position_display' => '!dodelete',
				'permit_display' => '!dodelete',
				($isexternal ? 'url_display' : 'slug_display') => '!dodelete',
				($isexternal ? 'newwindow_display' : 'heading_display') => '!dodelete',
				'content_display' => '!dodelete',
			));
		
		else {
			unset($qa_content['form']['fields']['slug']);
			unset($qa_content['form']['fields']['delete']);
		}
		
		$qa_content['focusid']='name';
	
	} else {

	//	List of standard navigation links

		$qa_content['form']=array(
			'tags' => 'method="post" action="'.qa_self_html().'"',
			
			'style' => 'tall',
			
			'fields' => array(),

			'buttons' => array(
				'save' => array(
					'tags' => 'name="dosaveoptions"',
					'label' => qa_html(_('Save Changes')),
				),

				'addpage' => array(
					'tags' => 'name="doaddpage"',
					'label' => qa_html(_('Add Page')),
				),

				'addlink' => array(
					'tags' => 'name="doaddlink"',
					'label' => qa_html(_('Add Link')),
				),
			),
			
			'hidden' => array(
				'code' => qa_get_form_security_code('admin/pages'),
			),
		);
		
		$qa_content['form']['fields']['navlinks']=array(
			'label' => qa_html(_('Show navigation links:')),
			'type' => 'static',
			'tight' => true,
		);

		foreach ($navoptions as $optionname => $lang) {
			$qa_content['form']['fields'][$optionname]=array(
				'label' => '<a href="'.qa_path_html($navpaths[$optionname]).'">'.qa_html($lang).'</a>',
				'tags' => 'name="option_'.$optionname.'"',
				'type' => 'checkbox',
				'value' => qa_opt($optionname),
			);
		}
		
		$qa_content['form']['fields'][]=array(
			'type' => 'blank'
		);

	//	List of suggested plugin pages

		$listhtml='';
		
		$pagemodules=qa_load_modules_with('page', 'suggest_requests');
		
		foreach ($pagemodules as $tryname => $trypage) {
			$suggestrequests=$trypage->suggest_requests();
		
			foreach ($suggestrequests as $suggestrequest) {
				$listhtml.='<li><b><a href="'.qa_path_html($suggestrequest['request']).'">'.qa_html($suggestrequest['title']).'</a></b>';
				
				$listhtml.=qa_html(sprintf(_(' (plugin module: %s)'), $tryname));

				$listhtml.=sprintf(qa_html(_(' - %sadd link%s')),
					'<a href="'.qa_path_html(qa_request(), array('doaddlink' => 1, 'text' => $suggestrequest['title'], 'url' => $suggestrequest['request'], 'nav' => @$suggestrequest['nav'])).'">',
					'</a>'
				);
				
				if (method_exists($trypage, 'admin_form'))
					$listhtml.=' - <a href="'.qa_admin_module_options_path('page', $tryname).'">'.qa_html(_('options')).'</a>';
					
				$listhtml.='</li>';
			}
		}

		if (strlen($listhtml))
			$qa_content['form']['fields']['plugins']=array(
				'label' => qa_html(_('Pages available via plugins:')),
				'type' => 'custom',
				'html' => '<ul style="margin-bottom:0;">'.$listhtml.'</ul>',
			);
		
	//	List of custom pages or links

		$listhtml='';
		
		foreach ($pages as $page) {
			$listhtml.='<li><b><a href="'.qa_custom_page_url($page).'">'.qa_html($page['title']).'</a></b>';
			
			$listhtml.=sprintf(qa_html(($page['flags'] & QA_PAGE_FLAGS_EXTERNAL) ? _(' - %sedit link%s') : _(' - %sedit page%s')),
				'<a href="'.qa_path_html('admin/pages', array('edit' => $page['pageid'])).'">',
				'</a>'
			);
								
			$listhtml.='</li>';
		}
		
		$qa_content['form']['fields']['pages']=array(
			'label' => strlen($listhtml) ? qa_html(_('Custom pages or links:')) : qa_html(_('Click the \'Add Page\' button to add custom content to your Q2A site, or \'Add Link\' to link to any other web page.')),
			'type' => 'custom',
			'html' => strlen($listhtml) ? '<ul style="margin-bottom:0;">'.$listhtml.'</ul>' : null,
		);
	}

	$qa_content['navigation']['sub']=qa_admin_sub_navigation();

	
	return $qa_content;


/*
	Omit PHP closing tag to help avoid accidental output
*/
