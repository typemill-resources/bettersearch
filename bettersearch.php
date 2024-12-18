<?php

namespace Plugins\Bettersearch;

use \Typemill\Plugin;
use \Typemill\Models\StorageWrapper;

class Bettersearch extends Plugin
{
	protected $item;

	protected $settings;

	protected $navigation;
	
    public static function getSubscribedEvents()
    {
		return array(
			'onSettingsLoaded' 		=> 'onsettingsLoaded',
			'onPagetreeLoaded' 		=> 'onPagetreeLoaded',
			'onPageReady'			=> 'onPageReady',
			'onPagePublished'		=> 'onPagePublished',
			'onPageUnpublished'		=> 'onPageUnpublished',
			'onPageSorted'			=> 'onPageSorted',
			'onPageDeleted'			=> 'onPageDeleted',	
		);
	}
	
	# get search.json with route
	# update search.json on publish

	public static function addNewRoutes()
	{
		return [

			# add a frontend route with a form
			[	
				'httpMethod' 	=> 'get', 
#				'route' 		=> '/indexrs51gfe2o2', 
				'route' 		=> '/indexrs62hgf3p3', 
				'name' 			=> 'bettersearch.frontend', 
				'class' 		=> 'Plugins\bettersearch\SearchController:index',
			],
		];
	}

	public function onSettingsLoaded($settings)
	{
		$this->settings = $settings->getData();
	}

	# at any of theses events, delete the old search index
	public function onPagePublished($item)
	{
		$this->deleteSearchIndex();
	}
	public function onPageUnpublished($item)
	{
		$this->deleteSearchIndex();
	}
	public function onPageSorted($inputParams)
	{
		$this->deleteSearchIndex();
	}
	public function onPageDeleted($item)
	{
		$this->deleteSearchIndex();
	}

	private function deleteSearchIndex()
	{
    	$storage = new StorageWrapper($this->settings['storage']);

    	# delete the index file here
    	$storage->deleteFile('cacheFolder', '', 'searchindex.json');		
	}

	public function onPagetreeLoaded($data)
	{
		$this->navigation = $data->getData();
	}
	
	# add the search form to frontend
	public function onPageReady($page)
	{
		$pageData 			= $page->getData($page);

		$pluginsettings 	= $this->getPluginSettings('bettersearch');
		$salt 				= "asPx9Derf2";
		$langsupport 		= [	'ar' => true,
								'da' => true,
								'de' => true,
								'du' => true,
								'es' => true,
								'fi' => true,
								'fr' => true,
								'hi' => true,
								'hu' => true,
								'it' => true,
								'ja' => true,
								'jp' => true,
								'nl' => true,
								'no' => true,
								'pt' => true,
								'ro' => true,
								'ru' => true,
								'sv' => true,
								'th' => true,
								'tr' => true,
								'vi' => true,
								'zh' => true ]; 


		# activate axios and vue in frontend
		$this->activateAxios();

		# add the css and lunr library
		$this->addCSS('/bettersearch/public/bettersearch.css');
		$this->addJS('/bettersearch/public/lunr.js');
		
		# add language support 
		$langattr = ( isset($this->settings['langattr']) && $this->settings['langattr'] != '' ) ? $this->settings['langattr'] : 'en';
		if($langattr != 'en')
		{			
			if(isset($langsupport[$langattr]))
			{
				$this->addJS('/bettersearch/public/lunr-languages/min/lunr.stemmer.support.min.js');
				$this->addJS('/bettersearch/public/lunr-languages/min/lunr.' . $langattr . '.min.js');
			}
			else
			{
				$langattr = false;
			}
		}

		# add the custom search script
		$this->addJS('/bettersearch/public/bettersearch.js');

		# simple security for first request
		$secret = time();
		$secret = substr($secret,0,-1);
		$secret = md5($secret . $salt);

		# simple csrf protection with a session for long following requests
		if (session_status() == PHP_SESSION_NONE)
		{
		    session_start();
		}

		$length 					= 32;
		$token 						= substr(base_convert(sha1(uniqid(mt_rand())), 16, 36), 0, $length);
		$_SESSION['search'] 		= $token; 
		$_SESSION['search-expire'] 	= time() + 1300; # 60 seconds * 30 minutes

		$searchfilter 	= [];
		if(isset($this->navigation) && $this->navigation)
		{
			foreach($this->navigation as $firstLevel)
			{
				if($firstLevel->elementType == 'folder' && isset($firstLevel->name) && isset($firstLevel->pathWithoutType) )
				{
					$searchfilter[] = [
						'name' 	=> $firstLevel->name,
						'path'	=> $firstLevel->pathWithoutType
					];
				}
			}
		}

		$searchfilterJson 	= htmlspecialchars(json_encode($searchfilter), ENT_QUOTES, 'UTF-8');
		$noresulttitle 		= (isset($pluginsettings['noresulttitle']) && $pluginsettings['noresulttitle'] != '' ) ? $pluginsettings['noresulttitle'] : 'No result.';
		$noresulttext 		= (isset($pluginsettings['noresulttext']) && $pluginsettings['noresulttext'] != '' ) ? $pluginsettings['noresulttext'] : 'We did not find anything for that search term.';
		$placeholder 		= (isset($pluginsettings['placeholder']) && $pluginsettings['placeholder'] != '') ? $pluginsettings['placeholder'] : 'search ...';

		$pageData['widgets']['search'] = '<div class="searchContainer"' 
											. 'data-access="' . $secret 
											. '" data-token="' . $token 
											. '" data-language="' . $langattr 
											. '" data-searchplaceholder="' . $placeholder 
											. '" data-noresulttitle="' . $noresulttitle 
											. '" data-noresulttext="' . $noresulttext 
											. '" data-filter="' . $searchfilterJson 
											. '" id="searchForm">' .
									        '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false" style="display:none">' .
												'<symbol id="icon-search" viewBox="0 0 20 20">' .
													'<path d="M12.9 14.32c-1.34 1.049-3.050 1.682-4.908 1.682-4.418 0-8-3.582-8-8s3.582-8 8-8c4.418 0 8 3.582 8 8 0 1.858-0.633 3.567-1.695 4.925l0.013-0.018 5.35 5.33-1.42 1.42-5.33-5.34zM8 14c3.314 0 6-2.686 6-6s-2.686-6-6-6v0c-3.314 0-6 2.686-6 6s2.686 6 6 6v0z"></path>' .
												'</symbol>' .
											'</svg>' .
											'<span class="searchicon"><svg class="icon icon-search"><use xlink:href="#icon-search"></use></svg></span>' .
	        								'<input type="text" placeholder="' . $placeholder . '" />'.
    									'</div>';

    	if(isset($pluginsettings['searchfield']) && $pluginsettings['searchfield'] == 'icon')
    	{
			$pageData['widgets']['search'] = '<div class="searchContainerIcon"' 
												. 'data-access="' . $secret 
												. '" data-token="' . $token 
												. '" data-language="' . $langattr 
												. '" data-searchplaceholder="' . $placeholder 
												. '" data-noresulttitle="' . $noresulttitle 
												. '" data-noresulttext="' . $noresulttext 
												. '" data-filter="' . $searchfilterJson 
												. '" id="searchForm">' .
										        '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false" style="display:none">' .
													'<symbol id="icon-search" viewBox="0 0 20 20">' .
														'<path d="M12.9 14.32c-1.34 1.049-3.050 1.682-4.908 1.682-4.418 0-8-3.582-8-8s3.582-8 8-8c4.418 0 8 3.582 8 8 0 1.858-0.633 3.567-1.695 4.925l0.013-0.018 5.35 5.33-1.42 1.42-5.33-5.34zM8 14c3.314 0 6-2.686 6-6s-2.686-6-6-6v0c-3.314 0-6 2.686-6 6s2.686 6 6 6v0z"></path>' .
													'</symbol>' .
												'</svg>' .
												'<span class="searchicon"><svg class="icon icon-search"><use xlink:href="#icon-search"></use></svg></span>' .
	    									'</div>';    		
    	}
 		$page->setData($pageData);
	}
}