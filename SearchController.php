<?php

namespace Plugins\bettersearch;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Typemill\Models\Navigation;
use Typemill\Models\StorageWrapper;
use Typemill\Controllers\Controller;

class SearchController extends Controller
{
	protected $error = false;

	public function index(Request $request, Response $response, $args)
	{
		$storage = new StorageWrapper($this->settings['storage']);

		$index = $storage->getFile('cacheFolder', '', 'searchindex.json');

		if(!$index or empty(json_decode($index)))
		{
			$createIndex = $this->createIndex($storage);

			if(!$createIndex)
			{
				$response->getBody()->write($this->error);

				$response->withHeader('Content-Type', 'application/json')->withStatus(500);
			}

			$index = $storage->getFile('cacheFolder', '', 'searchindex.json');
		}
	
		$response->getBody()->write($index);

		return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
	}

	private function createIndex($storage)
	{
		$navigation = new Navigation();
		$urlinfo 	= $this->c->get('urlinfo');
		$langattr 	= $this->settings['langattr'];

		$liveNavigation = $navigation->getLiveNavigation($urlinfo, $langattr);

        # get data for search-index
        $index = $this->getAllContent($liveNavigation, $storage, [], null);

        if(!$index OR !is_array($index) OR empty($index))
        {
        	$this->error = 'We could not create the search-index.';
        	return false;
        }

		# store the index file here
		$store = $storage->writeFile('cacheFolder', '', 'searchindex.json',  json_encode($index, JSON_UNESCAPED_SLASHES));
		if(!$store)
		{
			$this->error = $storage->getError();
			return false; 
		}

		return true;
	}

    private function getAllContent($navigation, $storage, $index, $firstLevel)
	{
		foreach($navigation as $item)
		{
            # Check if the item is a first-level item and set the firstLevelTitle
            if ($item->elementType == "folder" && isset($item->keyPathArray) && count($item->keyPathArray) == 1)
            {
            	$firstLevel = [
	                'name' => $item->name,
	                'path' => $item->pathWithoutType
            	];
            }

			if($item->fileType == 'md')
			{
				$page = $storage->getFile('contentFolder', '',  $item->pathWithoutType . '.md');
                $pageArray = $this->getPageContentArray($page, $item->urlAbs, $firstLevel);
				$index[$pageArray['url']] = $pageArray;

				if($item->elementType == "folder")
				{
                    $index = $this->getAllContent($item->folderContent, $storage, $index, $firstLevel);
				}
			}
		}

		return $index;
	}

    private function getPageContentArray($page, $url, $firstLevel)
	{
		$parts = explode("\n", $page, 2);

		# get the title / headline
		$title = trim($parts[0], '# ');
		$title = str_replace(["\r\n", "\n", "\r"],' ', $title);


	    # Get and clean up the content
	    $content = $parts[1] ?? '';
	    
	    # Remove empty lines
	    $content = preg_replace('/^\s*$(?:\r\n?|\n)/m', '', $content);

		# get and cleanup the content
		$content = str_replace(["\r\n", "\n", "\r"],' ', $content);
		$content = $this->strip_markdown($content);

		$pageContent = [
			'title' 		=> $title,
			'content' 		=> $content,
			'url'			=> $url,
            'filtername' 	=> false,
            'filterpath' 	=> false						
		];

		if(isset($firstLevel) && is_array($firstLevel))
		{
            $pageContent['filtername'] 	= $firstLevel['name'] ?? false;
            $pageContent['filterpath'] 	= $firstLevel['path'] ?? false;
		}

		return $pageContent;
	}

	# see https://github.com/stiang/remove-markdown/blob/master/index.js
	private function strip_markdown($content)
	{
		# Remove TOC
		$content = str_replace('[TOC]', '', $content);
		if(!$content){return false;} 

		# Remove Shortcodes
		$content = preg_replace('/\[:.*:\]/m', '', $content);
		if(!$content){return false;} 
		
		# Remove horizontal rules
		$content = preg_replace('/^(-\s*?|\*\s*?|_\s*?){3,}\s*$/m', '', $content);
		if(!$content){return false;} 

		# Lists
		$content = preg_replace('/^([\s\t]*)([\*\-\+]|\d+\.)\s+/', '', $content);
		if(!$content){return false;} 

		# fenced codeblock
		$content = preg_replace('/~{3}.*\n/', '', $content);
		if(!$content){return false;} 

		# strikethrough
		$content = preg_replace('/~~/', '', $content);
		if(!$content){return false;} 

		# attributes
		$content = preg_replace('/\{.*?\}/', '', $content);
		if(!$content){return false;} 

		# fenced codeblocks
		$content = preg_replace('/`{3}.*\n/', '', $content);
		if(!$content){return false;} 

		# html tags
		$content = preg_replace('/<[^>]*>/', '', $content);
		if(!$content){return false;} 

		# setext headers
		$content = preg_replace('/^[=\-]{2,}\s*$/', '', $content);
		if(!$content){return false;} 

		# atx headers
		$content = preg_replace('/#{1,6}/', '', $content);
		$content = preg_replace('/^(\n)?\s{0,}#{1,6}\s+| {0,}(\n)?\s{0,}#{0,} {0,}(\n)?\s{0,}$/', '$1$2$3', $content);
		if(!$content){return false;} 

		# footnotes
		$content = preg_replace('/\[\^.+?\](\: .*?$)?/', '', $content);
		$content = preg_replace('/\s{0,2}\[.*?\]: .*?$/', '', $content);
		if(!$content){return false;} 

		# images
		$content = preg_replace('/\!\[(.*?)\][\[\(].*?[\]\)]/', '', $content);
		if(!$content){return false;} 

		# inline links
#		$content = preg_replace('/\[(.*?)\][\[\(].*?[\]\)]/', '$1', $content);
#		if(!$content){return false;} 

		# referenced style links 
		#  $content = preg_replace('/^\s{1,2}\[(.*?)\]: (\S+)( ".*?")?\s*$/', '$1', $content);

		# blockquotes
		$content = preg_replace('/\s{0,3}>\s?/', '', $content);
		if(!$content){return false;} 

		# emphasis
		$content = preg_replace('/([\*_]{1,3})(\S.*?\S{0,1})\1/', '$2', $content);
		if(!$content){return false;} 

		# codeblocks
		$content = preg_replace('/(`{3,})(.*?)\1/', '$2', $content);
		if(!$content){return false;} 

		# inlinecode
		$content = preg_replace('/`(.+?)`/', '$1', $content);
		if(!$content){return false;} 

		return $content;
	}
}
