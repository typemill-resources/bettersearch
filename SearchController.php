<?php

namespace Plugins\bettersearch;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Typemill\Models\Navigation;
use Typemill\Models\StorageWrapper;
use Typemill\Controllers\Controller;

## gets and creates the index

class SearchController extends Controller
{
	protected $error = false;

	protected $project = false;

	protected $projectlist = false;

	protected $indexname = 'index';

	protected $urlinfo = false;

	public function index(Request $request, Response $response, $args)
	{
		$token 		= $request->getQueryParams()['token'] ?? false;
		$project 	= $request->getQueryParams()['project'] ?? false;

		if(!$this->validateToken($token))
		{
			$response->getBody()->write('Please reload the page and start again.');

			return $response->withStatus(403);
		}

		$pluginSettings 	= $this->settings['plugins']['bettersearch'] ?? false;
		$storage 			= new StorageWrapper($this->settings['storage']);
		$navigation 		= new Navigation();
		$this->urlinfo 		= $this->c->get('urlinfo');

		if($project)
		{
			$navigation->setProject($this->settings, '/' . $project . '/');
			$project = $navigation->getProject();

			if($project)
			{
				$this->project = $project;
			
				if(isset($pluginSettings['fullindex']) && $pluginSettings['fullindex'])
				{
					# search all indexes
					$this->projectlist = $navigation->getAllProjects($this->settings);
				}
			}
		}

		$index = $this->getIndex($storage, $navigation);

		if($this->error)
		{
			$response->getBody()->write($this->error);

			$response->withHeader('Content-Type', 'application/json')->withStatus(500);
		}

		$response->getBody()->write($index);

		return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
	}

	private function getIndex($storage, $navigation)
	{
		if($this->projectlist && is_array($this->projectlist))
		{
			$mergedIndex = [];

			# get the main index
			$baseNavigation = new Navigation();
			$baseindex = $this->getSingleIndex($storage, $baseNavigation);

			# get the project indexes
			foreach($this->projectlist as $singleproject)
			{
				if(isset($singleproject['base']) && $singleproject['base'])
				{
					# it is the base project, so use the baseindex
					$index = $baseindex;
				}
				else
				{
					$this->indexname = "index_" . strtolower($singleproject['id']);

					$navigation->setProject($this->settings, '/' . $singleproject['id'] . '/');

					$index = $this->getSingleIndex($storage, $navigation);
				}

			    if ($index)
			    {
			        $decoded = json_decode($index, true);
			        if (is_array($decoded))
			        {
			        	$decoded = $this->addProjectFolderToIndex($decoded, $singleproject);

			            $mergedIndex = array_merge($mergedIndex, $decoded);
			        }
			    }
    		}

			# merge index;
			return json_encode($mergedIndex, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
		}
		else
		{
			if($this->project)
			{
				$this->indexname = "index_" . strtolower($this->project);
			}

			$index = $this->getSingleIndex($storage, $navigation);
		
			return $index;
		}
	}

	private function getSingleIndex($storage, $navigation)
	{
		$index = $storage->getFile('dataFolder', 'bettersearch', $this->indexname . '.json');

		if(!$index or empty(json_decode($index)))
		{
			$index = $this->createIndex($storage, $navigation);

			if(!$index)
			{
				return false;
			}

			$index = json_encode($index, JSON_UNESCAPED_SLASHES);

			# store the index file here
			$store = $storage->writeFile('dataFolder', 'bettersearch', $this->indexname . '.json', $index);
			if(!$store)
			{
				$this->error = $storage->getError();
				return false; 
			}

		}

		return $index;
	}

	private function createIndex($storage, $navigation)
	{
		$urlinfo 	= $this->c->get('urlinfo');
		$langattr 	= $this->settings['langattr'];

		$liveNavigation = $navigation->getLiveNavigation($urlinfo, $langattr);

		if(!$liveNavigation)
		{
			$this->error = "No navigation/content found.";
			return false;
		}

        # get data for search-index
        $index = $this->getAllContent($liveNavigation, $storage, []);

        if(!$index OR !is_array($index) OR empty($index))
        {
        	$this->error = 'We could not create the search-index.';
        	return false;
        }

        return $index;
	}

    private function getAllContent($navigation, $storage, $index, $firstLevel = false)
	{
		# get the homepage
		if(empty($index))
		{
			$folder 		= '';
			$urlAbs 		= $this->urlinfo['baseurl'];

			# if it is a current project, load the index file of the project
			if($this->project)
			{
				$folder = '_' . $this->project;
				$urlAbs .= '/' . $this->project;
			}

			# try to add the startpage
			$page = $storage->getFile('contentFolder', $folder, 'index.md');
			if($page)
			{
                $pageArray = $this->getPageContentArray($page, $urlAbs, $firstLevel);
				$index[$pageArray['url']] = $pageArray;
			}
		}

		foreach($navigation as $item)
		{
            # Check if the item is a first-level item and set the firstLevelTitle
            if (
            	$item->elementType == "folder" && 
            	isset($item->keyPathArray) && 
            	count($item->keyPathArray) == 1
            )
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
        $content = preg_replace('/\[(.*?)\][\[(](.*?)[\])]/', '$1 $2', $content);
		if(!$content){return false;}

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

	private function addProjectFolderToIndex($decoded, $singleproject)
	{
		foreach($decoded as $key => &$item)
		{
			$item['filtername'] = $singleproject['label'];
			$item['filterpath'] = '/' . strtolower($singleproject['id']) . '/';
		}

		return $decoded;
	}

	private function validateToken($token)
	{
		if(!$token)
		{
			return false;
		}

		$secretKey = hash('sha256', __DIR__);
		$decoded = base64_decode($token, true);
		if (!$decoded || strpos($decoded, '.') === false)
		{
		    return false;
		}

		[$payload, $sig] = explode('.', $decoded, 2);
		$expected = hash_hmac('sha256', $payload, $secretKey);

		if (!hash_equals($expected, $sig))
		{
		    return false;
		}

		$data = json_decode($payload, true);
		if (!$data || time() - $data['t'] > 900) // 15 minutes
		{
		    return false;
		}

		return true;
	}
}
