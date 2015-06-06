<?php
namespace F2cAnywhere;

class ContentPluginTag
{
	protected $tagWord = null;
	protected $callback = null;

	public function __construct($word)
	{
		$this->tagWord = $word;
	}
	
	public function containsTag($text)
	{
		return (\JString::strpos($text, '{' . $this->tagWord) !== false);
	}
	
	public function parse(&$text)
	{
		// {tagWord params/} or {tagWord params}...{/tagWord}
		$regex = '#\{' . $this->tagWord . '(\s+[^\}]*)?(\}([^\{]*)\{/' . $this->tagWord . '|\/)\}#';

		$text = preg_replace_callback($regex, array($this, 'processTagMatches'), $text);
	}

	/**
	 * Callback for preg_replace_callback() called by TagProcessor::processTags().
	 * 
	 * A content tag has two possible embedding syntaxes:
	 *	{tagWord [params]/}
	 *	Matches will have the following fields:
	 *		$matches[0] = {tagWord params/}
	 *		$matches[1] = params
	 *		$matches[2] = "/"
	 * 
	 *	{tagWord [params]}body{/tagWord}
	 *	Matches will have the following fields:
	 *		$matches[0] = {tagWord params}body{/tagWord}
	 *		$matches[1] = params
	 *		$matches[2] = "}{/tagWord}"
	 *		$matches[3] = body
	 */
	protected function processTagMatches($matches)
	{
		$tag = array('params' => array(), 'body' => '');
		
		$tag['tag'] = $matches[0];
		$tag['params'] = $this->extractTagParams($matches[1]);
		$tag['body'] = (count($matches) == 3 ? '' : $matches[3]);

		return call_user_func_array($this->callback, array($tag));
	}

	/**
	 * Extract tag parameters from string.
	 * 
	 * The string containing the tag parameters is expected to be
	 * in the following format: name=value|name=value|name=value|.....
	 * 
	 * @param string $paramString The string containing the tag parameters
	 * 
	 * @return array
	 */
	private function extractTagParams($paramString)
	{
		$paramList = explode('|', $paramString);
		$params = array();
		
		$i = 0;
		foreach ($paramList as $paramElement)
		{
			$param = preg_split('/!?=/', $paramElement);
			switch (count($param))
			{
				case 1:
                    $params[trim($param[0])] = true;
					break;

				case 2:
					$params[trim($param[0])] = $param[1];
					break;
			}
			$i++;
		}

		return $params;
	}

	public function getTagWord() {
		return $this->tagWord;
	}

	public function setTagWord($tagWord) {
		$this->tagWord = $tagWord;
		return $this;
	}

	public function getCallback() {
		return $this->callback;
	}

	public function setCallback($callback) {
		$this->callback = $callback;
		return $this;
	}
}