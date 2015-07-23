<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once PATH_THIRD . 'flickr/config.php';
require_once PATH_THIRD . 'flickr/helper.php';

$plugin_info = array(
	'pi_name' => FLICKR_NAME,
	'pi_version' => FLICKR_VER,
	'pi_author' => FLICKR_AUTHOR,
	'pi_author_url' => FLICKR_DOCS,
	'pi_description' => FLICKR_DESC,
	'pi_usage' => flickr::usage()
);

class Flickr {

	function __construct()
	{
		$this->EE = get_instance();

		$this->helper = new flickr_helper();
		
		$this->api_extras = 'description,url_sq,url_t,url_s,url_m,url_z,url_l,url_o';

	}
	
	
	/**
	 * Wrapper for phpFlickr::photos_search()
	 *
	 * Many thanks to ORD (http://ordhq.com) for funding the work for this functionality!
	 */
	function search()
	{
		/*
		 * Step 1: Setup
		 */
		// Load the flickr class
		$flickr = $this->_flickr();
		$f = $flickr[0];
		$flickr_settings = $flickr[1];

		// array of all supported parameters to pass to Flickr's search endpoint
		$supported = array(
			'api_key',
			'user_id',
			'tags',
			'tag_mode',
			'text',
			'min_upload_date',
			'max_upload_date',
			'min_taken_date',
			'max_taken_date',
			'license',
			'sort',
			'privacy_filter',
			'bbox',
			'accuracy',
			'safe_search',
			'content_type',
			'machine_tags',
			'machine_tag_mode',
			'group_id',
			'contacts',
			'woe_id',
			'place_id',
			'media',
			'has_geo',
			'geo_context',
			'lat',
			'lon',
			'radius',
			'radius_units',
			'is_commons',
			'in_gallery',
			'is_getty',
			'extras',
			'per_page',
			'page',
			
			// aliases just to cover our bases
			'limit',
			'tag',
			'group',
			'nsid'
		);
		
		// minimum default parameters to pass
		$default = array(
			'api_key',
			'extras',
			'per_page'
		);


		/*
		 * Step 2: build our $params array that will be passed to phpFlickr::photos_search()
		 */
		
		// 'IMF': magical Intersect, Merge & Flip
		$params = array_flip(array_merge($default, array_intersect($supported, array_keys($this->EE->TMPL->tagparams))));
		
		// fetch each param in turn
		foreach($params as $param => $value)
		{
			// our valid tags
			switch($param) :
			
				case('nsid') :
				case('user_id') :
					$params['user_id'] = $this->EE->TMPL->fetch_param($param, 'me');
				break;
				
				case('api_key') :
					$params['api_key'] = $this->EE->TMPL->fetch_param($param, $flickr_settings['option_api']);
				break;
			
				case('extras') :
					// need to be sure extras are only appended, not overwritten
					$params['extras'] = $this->api_extras . ',' . $this->EE->TMPL->fetch_param($param);
				break;
				
				case('tag_mode') :
					$params['tag_mode'] = $this->EE->TMPL->fetch_params($param, 'any');
					
					// aliases need to be matched
					if(stristr($params['tag_mode'], 'or'))
					{
						$params['tag_mode'] = 'any';
					}

					if(stristr($params['tag_mode'], 'and'))
					{
						$params['tag_mode'] = 'all';
					}
				break;
				
				case('limit') :
				case('per_page') :
					$params['per_page'] = $this->EE->TMPL->fetch_param($param, 10);
				break;
				
				case('page') :
					$params['page'] = $this->EE->TMPL->fetch_param($param, 1);
				break;

				case('tag') :
				case('tags') :
					$params['tags'] = $this->EE->TMPL->fetch_param($param);
				break;

				// for all else, just defaults
				default :
					$params[$param] = $this->EE->TMPL->fetch_param($param);
				break;
			endswitch;
		}


		/*
		 * Step 3: Fire off search & handle results
		 */
		$result = $f->photos_search($params);

		if($result && is_array($result) && $result['total'] > 0)
		{

			// Get the desired size, or default to square
			$sz = $this->_size($this->EE->TMPL->fetch_param('size'), 'square');
	
			// Establish our prefix (undocumented)
			$prefix = $this->EE->TMPL->fetch_param('prefix', 'flickr_');

			// our eventual array to parse against tagdata
			$variables = array();


			// loop through our photos
			foreach($result['photo'] as $flickr_data)
			{

				$variable_row = array(
					$prefix . 'img' 			=> 'http://farm' . $flickr_data['farm'] . '.static.flickr.com/' . $flickr_data['server'] . '/' . $flickr_data['id'] . '_' . $flickr_data['secret'] . $sz . '.jpg',
					$prefix . 'url' 			=> 'http://www.flickr.com/photos/' . $flickr_data['owner'] . '/' . $flickr_data['id'] . '/',
					$prefix . 'url_square'		=> $f->buildPhotoURL($flickr_data, "square"),
					$prefix . 'url_thumb'		=> isset($flickr_data['url_t']) ? $flickr_data['url_t'] : '',
					$prefix . 'url_small' 		=> isset($flickr_data['url_s']) ? $flickr_data['url_s'] : '',
					$prefix . 'url_medium' 		=> isset($flickr_data['url_m']) ? $flickr_data['url_m'] : '',
					$prefix . 'url_medium_640'	=> isset($flickr_data['url_z']) ? $flickr_data['url_z'] : '',
					$prefix . 'url_large'	 	=> isset($flickr_data['url_l']) ? $flickr_data['url_l'] : '',
					$prefix . 'url_orig' 		=> isset($flickr_data['url_o']) ? $flickr_data['url_o'] :  '',
					$prefix . 'title' 			=> $flickr_data['title']
				);
				
				// any extras to parse?
				$extras = explode(',', $params['extras']);
				foreach($extras as $extra)
				{
					// if original_format, then both originalsecret & originalformat are returned
					// may not be available for all images
					if($extra == 'original_format')
					{
						$variable_row[$prefix . 'originalsecret'] = isset($flickr_data['originalsecret']) ? $flickr_data['originalsecret'] :  '';
						$variable_row[$prefix . 'originalformat'] = isset($flickr_data['originalformat']) ? $flickr_data['originalformat'] :  '';
					}
					else
					{
						$variable_row[$prefix . $extra] = isset($flickr_data[$extra]) ? $flickr_data[$extra] :  '';
					}
				}
				
				$variables[] = $variable_row;
			}
			
			$this->return_data = $this->EE->TMPL->parse_variables($this->EE->TMPL->tagdata, $variables);

			// cleanup
			unset($variables, $variable_row, $prefix, $sz);
		}
		else
		{
			// Nothing - show No Results
			$this->return_data = $this->EE->TMPL->no_results();
		}

		// cleanup
		unset($result, $params, $default, $supported, $f, $flickr, $flickr_settings);

		// release the hounds!
		return $this->return_data;
	}
	// ------------------------------------------------------------
	

	function photostream() {
		
		$template = $this->EE->TMPL->tagdata;
		
		$numPhotos = $this->EE->TMPL->fetch_param('limit');
		$username = $this->EE->TMPL->fetch_param('username');
		$numPhotos = $numPhotos != '' ?  $numPhotos :  10;

                $pageNum = $this->EE->TMPL->fetch_param('page');
                $pageNum = $pageNum != '' ?  $pageNum :  1;
		
		// Load the flickr class
		$flickr = $this->_flickr();
		$f = $flickr[0];
		$flickr_settings = $flickr[1];
		
		// Get the desired size, or default to square
		$sz = $this->_size($this->EE->TMPL->fetch_param('size'));
		
		// Find the NSID of the username
		$person = $f->people_findByUsername($username);
		
		// Retrieve photostream from Flickr
		if($person)
		{
		  $recent = $f->people_getPublicPhotos($person['id'], 1, $this->api_extras, $numPhotos, $pageNum);
		}
		else
		{
		  $recent = $f->people_getPublicPhotos($flickr_settings['option_nsid'], 1, $this->api_extras, $numPhotos, $pageNum);
		}
		
		// If number of returned photo is less than num
		$numPhotos = min($numPhotos,$recent['photos']['total']);
		
		$flickr_photos = $recent['photos']['photo'];
		
		$variables = array();
		
		for ($i = 0; $i < $numPhotos; $i++) {
			
			// Retrieve the data for each photo
			$flickr_data = $flickr_photos[$i];

			$variable_row = array(
				'flickr_img' 			=> 'http://farm' . $flickr_data['farm'] . '.static.flickr.com/' . $flickr_data['server'] . '/' . $flickr_data['id'] . '_' . $flickr_data['secret'] . $sz . '.jpg',
				'flickr_url' 			=> $flickr_settings['option_photourl'] . $flickr_data['id'],
				'flickr_url_square'		=> $f->buildPhotoURL($flickr_data, "square") ,
				'flickr_url_thumb'		=> isset($flickr_data['url_t']) ? $flickr_data['url_t'] : '',
				'flickr_url_small' 		=> isset($flickr_data['url_s']) ? $flickr_data['url_s'] : '',
				'flickr_url_medium' 	=> isset($flickr_data['url_m']) ? $flickr_data['url_m'] : '',
				'flickr_url_medium_640'	=> isset($flickr_data['url_z']) ? $flickr_data['url_z'] : '',
				'flickr_url_large'	 	=> isset($flickr_data['url_l']) ? $flickr_data['url_l'] : '',
				'flickr_url_orig' 		=> isset($flickr_data['url_o']) ? $flickr_data['url_o'] :  '',
				'flickr_title' 			=> $flickr_data['title'],
				'flickr_description' 	=> $flickr_data['description']
			);
			
			$variables[] = $variable_row;

		}
		
		$r = $this->EE->TMPL->parse_variables($this->EE->TMPL->tagdata, $variables);
		
		return $r;
	
  	}
	
	
	
	function favorites() {
		
		$template = $this->EE->TMPL->tagdata;
		
		$numPhotos = $this->EE->TMPL->fetch_param('limit');
		$numPhotos = $numPhotos != '' ?  $numPhotos :  10;
		
		// Load the flickr class
		$flickr = $this->_flickr();
		$f = $flickr[0];
		$flickr_settings = $flickr[1];
		
		// Get the desired size, or default to square
		$sz = $this->_size($this->EE->TMPL->fetch_param('size'));
		
		// Retrieve favorites from Flickr
		$favorites = $f->favorites_getPublicList($flickr_settings['option_nsid'], NULL, NULL, NULL, $this->api_extras, $numPhotos, 1);
		
		// If number of returned photo is less than num
		$numPhotos = min($numPhotos,$favorites['photos']['total']);
		
		
		$flickr_photos = $favorites['photos']['photo'];
		
		$variables = array();
		
		for ($i = 0; $i < $numPhotos; $i++) {
			
			// Retrieve the data for each photo
			$flickr_data = $flickr_photos[$i];

			$variable_row = array(
				'flickr_img' 			=> 'http://farm' . $flickr_data['farm'] . '.static.flickr.com/' . $flickr_data['server'] . '/' . $flickr_data['id'] . '_' . $flickr_data['secret'] . $sz . '.jpg',
				'flickr_url' 			=> 'http://www.flickr.com/photos/' . $flickr_data['owner'] . '/' . $flickr_data['id'],
				'flickr_url_square'		=> $f->buildPhotoURL($flickr_data, "square") ,
				'flickr_url_thumb'		=> isset($flickr_data['url_t']) ? $flickr_data['url_t'] : '',
				'flickr_url_small' 		=> isset($flickr_data['url_s']) ? $flickr_data['url_s'] : '',
				'flickr_url_medium' 	=> isset($flickr_data['url_m']) ? $flickr_data['url_m'] : '',
				'flickr_url_medium_640'	=> isset($flickr_data['url_z']) ? $flickr_data['url_z'] : '',
				'flickr_url_large'	 	=> isset($flickr_data['url_l']) ? $flickr_data['url_l'] : '',
				'flickr_url_orig' 		=> isset($flickr_data['url_o']) ? $flickr_data['url_o'] :  '',
				'flickr_title' 			=> $flickr_data['title'],
				'flickr_description' 	=> $flickr_data['description']
			);
			
			$variables[] = $variable_row;
		}
		
		$r = $this->EE->TMPL->parse_variables($this->EE->TMPL->tagdata, $variables);
		
		return $r;
	}
	
	
	
	function photosets() {
		
		$template = $this->EE->TMPL->tagdata;
		
		// Load the flickr class
		$flickr = $this->_flickr();
		$f = $flickr[0];
		$flickr_settings = $flickr[1];
		
		// Get the desired size, or default to square
		$sz = $this->_size($this->EE->TMPL->fetch_param('size'));
		
		// Retrieve sets from Flickr
		$set = $f->photosets_getList($flickr_settings['option_nsid']);
		
		$variables = array();
			
		for($i = 0; $i < count($set['photoset']); $i++) {
			
			// Retrieve the data for each photo
			$photoset = $set['photoset'][$i];
			
			$variable_row = array(
				'set_img' 			=> "http://farm" . $photoset['farm']. ".static.flickr.com/" . $photoset['server'] . "/" . $photoset['primary'] . "_" . $photoset['secret'] . $sz . '.jpg',
				'set_url' 			=> $flickr_settings['option_photourl'] . 'sets/' . $photoset['id'],
				'set_title' 		=> $photoset['title'],
				'set_count' 		=> $photoset['photos'],
				'set_id'			=> $photoset['id'],
				'set_description'	=> $photoset['description'],
				'set_created'		=> $photoset['date_create']
			);
			
			$variables[] = $variable_row;
			
		}
		
		$r = $this->EE->TMPL->parse_variables($this->EE->TMPL->tagdata, $variables);
		
		return $r;
	}
	
	
	
	function photoset() {
		
		$template = $this->EE->TMPL->tagdata;
		
		$setId = $this->EE->TMPL->fetch_param('set_id');
		
		$numPhotos = $this->EE->TMPL->fetch_param('limit');
		$numPhotos = $numPhotos != '' ?  $numPhotos :  10;
		
		// Load the flickr class
		$flickr = $this->_flickr();
		$f = $flickr[0];
		$flickr_settings = $flickr[1];
		
		// Get the desired size, or default to square
		$sz = $this->_size($this->EE->TMPL->fetch_param('size'));
		
		// Retrieve sets from Flickr
		$set_photos = $f->photosets_getPhotos($setId, $this->api_extras);
		
		// If number of returned photo is less than num
		$numPhotos = min($numPhotos,count($set_photos['photoset']['photo']));
		
		$flickr_photos = $set_photos['photoset']['photo'];
		
		$variables = array();
		
		for ($i = 0; $i < $numPhotos; $i++) {
			
			// Retrieve the data for each photo
			$flickr_data = $flickr_photos[$i];
			
			$variable_row = array(
				'set_url'				=> $flickr_settings['option_photourl'] . 'sets/' . $setId,
				'flickr_img' 			=> 'http://farm' . $flickr_data['farm'] . '.static.flickr.com/' . $flickr_data['server'] . '/' . $flickr_data['id'] . '_' . $flickr_data['secret'] . $sz . '.jpg',
				'flickr_url' 			=> $flickr_settings['option_photourl'] . '/' . $flickr_data['id'],
				'flickr_url_square'		=> $f->buildPhotoURL($flickr_data, "square") ,
				'flickr_url_thumb'		=> isset($flickr_data['url_t']) ? $flickr_data['url_t'] : '',
				'flickr_url_small' 		=> isset($flickr_data['url_s']) ? $flickr_data['url_s'] : '',
				'flickr_url_medium' 	=> isset($flickr_data['url_m']) ? $flickr_data['url_m'] : '',
				'flickr_url_medium_640'	=> isset($flickr_data['url_z']) ? $flickr_data['url_z'] : '',
				'flickr_url_large'	 	=> isset($flickr_data['url_l']) ? $flickr_data['url_l'] : '',
				'flickr_url_orig' 		=> isset($flickr_data['url_o']) ? $flickr_data['url_o'] :  '',
				'flickr_title' 			=> $flickr_data['title'],
				'flickr_description' 	=> $flickr_data['description']
			);
			
			$variables[] = $variable_row;
		}
		
		$r = $this->EE->TMPL->parse_variables($this->EE->TMPL->tagdata, $variables);
		
		return $r;
	}
	
	
	function groups() {
		
		$template = $this->EE->TMPL->tagdata;
		
		// Load the flickr class
		$flickr = $this->_flickr();
		$f = $flickr[0];
		$flickr_settings = $flickr[1];
		
		// Retrieve sets from Flickr
		$groups = $f->people_getPublicGroups($flickr_settings['option_nsid']);
		
		$variables = array();
			
		foreach ($groups as $group) {
			
			// Retrieve the data for each photo
			$group_info = $f->groups_getInfo($group['nsid']);
			$group_info = $group_info['group'];
			
			$variable_row = array(
				'group_url' 		=> 'http://www.flickr.com/groups/' . $group['nsid'],
				'group_name' 		=> $group['name'],
				'group_id'			=> $group['nsid']
			);
			
			$variables[] = $variable_row;
			
		}
		
		$r = $this->EE->TMPL->parse_variables($this->EE->TMPL->tagdata, $variables);
		
		return $r;
	}
	
	
	
	function groupset() {
		
		$template = $this->EE->TMPL->tagdata;
		
		$groupId = $this->EE->TMPL->fetch_param('group_id');
		
		$numPhotos = $this->EE->TMPL->fetch_param('limit');
		$numPhotos = $numPhotos != '' ?  $numPhotos :  10;
		
		// Load the flickr class
		$flickr = $this->_flickr();
		$f = $flickr[0];
		$flickr_settings = $flickr[1];
		
		// Get the desired size, or default to square
		$sz = $this->_size($this->EE->TMPL->fetch_param('size'));
		
		// Retrieve sets from Flickr
		$group_photos = $f->groups_pools_getPhotos($groupId, NULL, NULL, NULL, $this->api_extras, $numPhotos, NULL);
		
		$variables = array();
		
		foreach($group_photos['photos']['photo'] as $flickr_data) {
			
			$variable_row = array(
				'flickr_img' 			=> 'http://farm' . $flickr_data['farm'] . '.static.flickr.com/' . $flickr_data['server'] . '/' . $flickr_data['id'] . '_' . $flickr_data['secret'] . $sz . '.jpg',
				'flickr_url' 			=> 'http://www.flickr.com/photos/' .  $flickr_data['owner'] . '/' . $flickr_data['id'],
				'flickr_url_square'		=> $f->buildPhotoURL($flickr_data, "square") ,
				'flickr_url_thumb'		=> isset($flickr_data['url_t']) ? $flickr_data['url_t'] : '',
				'flickr_url_small' 		=> isset($flickr_data['url_s']) ? $flickr_data['url_s'] : '',
				'flickr_url_medium' 	=> isset($flickr_data['url_m']) ? $flickr_data['url_m'] : '',
				'flickr_url_medium_640'	=> isset($flickr_data['url_z']) ? $flickr_data['url_z'] : '',
				'flickr_url_large'	 	=> isset($flickr_data['url_l']) ? $flickr_data['url_l'] : '',
				'flickr_url_orig' 		=> isset($flickr_data['url_o']) ? $flickr_data['url_o'] :  '',
				'flickr_title' 			=> $flickr_data['title'],
				'flickr_description' 	=> $flickr_data['description'],
				'flickr_owner' 			=> $flickr_data['owner']
			);
			
			$variables[] = $variable_row;
		}
		
		$r = $this->EE->TMPL->parse_variables($this->EE->TMPL->tagdata, $variables);
		
		return $r;
	}
	
	
	/* unfinished, use with caution - or perhaps don't at all, just yet */
	function photo() {
		
		$template = $this->EE->TMPL->tagdata;
		
		$photoId = $this->EE->TMPL->fetch_param('photo_id');
		
		// Load the flickr class
		$flickr = $this->_flickr();
		$f = $flickr[0];
		$flickr_settings = $flickr[1];
		
		// Get the desired size, or default to square
		$sz = $this->_size($this->EE->TMPL->fetch_param('size'));
		
		// Retrieve the photo details from Flickr
		$flickr_data = $f->photos_getInfo($photoId);
		$flickr_comments = $f->photos_comments_getList($photoId);
		$flickr_geo = $f->photos_geo_getLocation($photoId);
		
		//
		if($flickr_data['stat'] != 'ok') return;
		$flickr_data = $flickr_data['photo'];
		
		$variables = array();
		
		// Get the photo's tags
		$tags = array();
		foreach ($flickr_data['tags']['tag'] as $tag) {
			$tags[] = array(
					'tag_link' 	=> 'http://www.flickr.com/photos/' . $tag['author'] . '/tags/' . $tag['raw'],
					'tag_name'	=> $tag['_content']
			);
		}
	
		// Get the photo's notes
		$notes = array();
		foreach ($flickr_data['notes']['note'] as $note) {
			$notes[] = array(
					'note' 			=> $note['_content'],
					'note_x'		=> $note['x'],
					'note_y'		=> $note['y'],
					'note_width'	=> $note['w'],
					'note_height'	=> $note['h'],
					'note_author'	=> $note['authorname'],
			);
		}
		
		// Get the photo's comments
		$comments = array();
		if (isset($flickr_comments['comments']['comment'])) {
			foreach ($flickr_comments['comments']['comment'] as $comment) {
				$comments[] = array(
						'comment' 			=> $comment['_content'],
						'comment_author' 	=> $comment['authorname'],
						'comment_date' 		=> $comment['datecreate']
				);
			}
		}
		
		$variables[] = array(
				'flickr_img' 			=> 'http://farm' . $flickr_data['farm'] . '.static.flickr.com/' . $flickr_data['server'] . '/' . $flickr_data['id'] . '_' . $flickr_data['secret'] . $sz . '.jpg',
				'flickr_url' 			=> $flickr_settings['option_photourl'] . $flickr_data['id'],
				'flickr_url_square' 	=> 'http://farm' . $flickr_data['farm'] . '.static.flickr.com/' . $flickr_data['server'] . '/' . $flickr_data['id'] . '_' . $flickr_data['secret'] . '_s.jpg',
				'flickr_url_thumb' 		=> 'http://farm' . $flickr_data['farm'] . '.static.flickr.com/' . $flickr_data['server'] . '/' . $flickr_data['id'] . '_' . $flickr_data['secret'] . '_t.jpg',
				'flickr_url_small' 		=> 'http://farm' . $flickr_data['farm'] . '.static.flickr.com/' . $flickr_data['server'] . '/' . $flickr_data['id'] . '_' . $flickr_data['secret'] . '_m.jpg',
				'flickr_url_medium' 	=> 'http://farm' . $flickr_data['farm'] . '.static.flickr.com/' . $flickr_data['server'] . '/' . $flickr_data['id'] . '_' . $flickr_data['secret'] . '.jpg',
				'flickr_url_medium_640'	=> 'http://farm' . $flickr_data['farm'] . '.static.flickr.com/' . $flickr_data['server'] . '/' . $flickr_data['id'] . '_' . $flickr_data['secret'] . '_z.jpg',
				'flickr_url_large'		=> 'http://farm' . $flickr_data['farm'] . '.static.flickr.com/' . $flickr_data['server'] . '/' . $flickr_data['id'] . '_' . $flickr_data['secret'] . '_b.jpg',
				'flickr_title' 			=> $flickr_data['title'],
				'flickr_description' 	=> $flickr_data['description'],
				'flickr_comment_total' 	=> $flickr_data['comments'],
				'flickr_dateposted'		=> $flickr_data['dates']['posted'],
				'flickr_datetaken'		=> $flickr_data['dates']['taken'],
				'flickr_latitude'		=> $flickr_geo['location']['latitude'],
				'flickr_longitude'		=> $flickr_geo['location']['longitude'],
				'flickr_locality'		=> $flickr_geo['location']['locality']['_content'],
				'flickr_tags'			=> $tags,
				'flickr_notes'			=> $notes,
				'flickr_comments'		=> $comments
			);
		
		$r = $this->EE->TMPL->parse_variables($this->EE->TMPL->tagdata, $variables);
		
		return $r;
	}
	
	
	
	function tagcloud() {
		
		$template = $this->EE->TMPL->tagdata;
		
		// Load the flickr class
		$flickr = $this->_flickr();
		$f = $flickr[0];
		$flickr_settings = $flickr[1];
		
		// Retrieve sets from Flickr
		$tags = $f->tags_getListUserPopular($flickr_settings['option_nsid'],1000);
		
		$fontMin = $this->EE->TMPL->fetch_param('font_min');
		$fontMax = $this->EE->TMPL->fetch_param('font_max');
		$fontMin = $fontMin != '' ?  $fontMin :  11;
		$fontMax = $fontMax != '' ?  $fontMax :  28;
		$size = $fontMin;
					
		$numTags = count($tags);
		sort($tags);
					
		$increment = intval($numTags/($fontMax-$fontMin));
					
		for ($i=0; $i < $numTags; $i++) {
			$output[$tags[$i]['_content']] = $size ;
			if ($increment == 0 || $i % $increment == 0 )  { 
			$size++;
			}
		}
					
		ksort($output);
		
		$variables = array();
		
		foreach ($output as $tg => $sz) {
			
			$variable_row = array(
				'tag_name' 			=> $tg,
				'tag_link' 			=> $flickr_settings['option_photourl'] . 'tags/' . $tg,
				'tag_urlname' 		=> $tg,
				'tag_size' 			=> $sz
			);
			
			$variables[] = $variable_row;
			
		}
		
		$r = $this->EE->TMPL->parse_variables($this->EE->TMPL->tagdata, $variables);
		
		return $r;
	}
	
	
	
	function tagset() {
		
		$template = $this->EE->TMPL->tagdata;
		
		$tag = $this->EE->TMPL->fetch_param('tag');
		
		$numPhotos = $this->EE->TMPL->fetch_param('limit');
		$numPhotos = $numPhotos != '' ?  $numPhotos :  10;
		
		// Load the flickr class
		$flickr = $this->_flickr();
		$f = $flickr[0];
		$flickr_settings = $flickr[1];
		
		// Get the desired size, or default to square
		$sz = $this->_size($this->EE->TMPL->fetch_param('size'));
		
		// Retrieve sets from Flickr
		$search_array = array('user_id' => $flickr_settings['option_nsid'], 'tags' => $tag, 'extras' => $this->api_extras, 'per_page' => $numPhotos);
		$tag_photos = $f->photos_search($search_array);
		//print_r($tag_photos);
		
		$variables = array();
		
		foreach ($tag_photos['photo'] as $flickr_data) {
			
			$variable_row = array(
				'flickr_img' 			=> 'http://farm' . $flickr_data['farm'] . '.static.flickr.com/' . $flickr_data['server'] . '/' . $flickr_data['id'] . '_' . $flickr_data['secret'] . $sz . '.jpg',
				'flickr_url' 			=> $flickr_settings['option_photourl'] . $flickr_data['id'],
				'flickr_url_square'		=> $f->buildPhotoURL($flickr_data, "square") ,
				'flickr_url_thumb'		=> isset($flickr_data['url_t']) ? $flickr_data['url_t'] : '',
				'flickr_url_small' 		=> isset($flickr_data['url_s']) ? $flickr_data['url_s'] : '',
				'flickr_url_medium' 	=> isset($flickr_data['url_m']) ? $flickr_data['url_m'] : '',
				'flickr_url_medium_640'	=> isset($flickr_data['url_z']) ? $flickr_data['url_z'] : '',
				'flickr_url_large'	 	=> isset($flickr_data['url_l']) ? $flickr_data['url_l'] : '',
				'flickr_url_orig' 		=> isset($flickr_data['url_o']) ? $flickr_data['url_o'] :  '',
				'flickr_title' 			=> $flickr_data['title'],
				'flickr_description' 	=> $flickr_data['description']
			);
			
			$variables[] = $variable_row;
		}
		
		$r = $this->EE->TMPL->parse_variables($this->EE->TMPL->tagdata, $variables);
		
		return $r;
	}
	
	
	
	// HELPER FUNCTIONS

	function _flickr() {
		// need settings from DB to run
		$this->helper->get_settings();

		require_once PATH_THIRD . 'flickr/libraries/Phpflickr.php';
		
		$f = new phpFlickr($this->helper->cache['settings']['option_api'], $this->helper->cache['settings']['option_secret']);
		$f->setToken($this->helper->cache['settings']['option_auth']);
		
		return array($f, $this->helper->cache['settings']);
	}
	
	
	
	function _size($size = 'square') {
		switch ($size) {
			case 'thumb':
			case 'thumbnail': /* just in case someone tries it */
				$sz = "_t";
				break;
			case 'small':
				$sz = "_m";
				break;
			case 'medium':
			case 'medium_500': /* just in case someone tries it */
				$sz = "";
				break;
			case 'medium_640':
				$sz = "_z";
				break;
			case 'large':
				$sz = "_b";
				break;
			case 'square':
			default:
				$sz = "_s";
				break;
		}
		
		return $sz;
	}
	
	
	
	// ----------------------------------------
	//  Plugin Usage
	// ----------------------------------------
	
	// This function describes how the plugin is used.
	//  Make sure and use output buffering
	
	function usage() {
	  ob_start(); 
	  ?>
	The Flickr Plugin is provides several
	tags to incorporate Flickr into your
    website.
	
	{exp:flickr:photostream}
	
	Displays your Flickr photostream
	
	  <?php
	  $buffer = ob_get_contents();
		
	  ob_end_clean(); 
	
	  return $buffer;
	}
	// END


}

/* End of file pi.flickr.php */
/* Location: ./system/expressionengine/third_party/flickr/pi.flickr.php */