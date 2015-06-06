<?php
// no direct access
defined('_JEXEC') or die;

jimport('joomla.plugin.plugin');

/*
	$this->params: the parameters set for this plugin by the administrator
	$this->_name: the name of the plugin
	$this->_type: the group (type) of the plugin
	$this->db: the db object (since Joomla 3.1)
	$this->app: the application object (since Joomla 3.1)
*/
class plgContentF2cAnywhere extends JPlugin
{
	protected $db = null;
	protected $app = null;

	protected $tagWord = 'F2cAnywhere';
	protected $recursive = true;
	protected $errorHandling = 'joomla';
    protected $notFoundMessage = false;
	protected $messageClass = '';

	protected $tagParams = null;

	public function __construct(&$subject, $config = array()) {
		parent::__construct($subject, $config);

        \JLoader::registerNamespace('F2cAnywhere', JPATH_PLUGINS . '/content/f2canywhere/lib');

        \JLoader::discover('Form2ContentTable', JPATH_ADMINISTRATOR . '/components/com_form2content/tables');
        \JLoader::discover('Form2ContentModel', JPATH_ADMINISTRATOR . '/components/com_form2content/models');

        JFactory::getLanguage()->load('plg_content_f2canywhere',dirname(__FILE__));

		$this->tagWord = $this->params->get('tag_word', $this->tagWord);
		$this->recursive = (bool)$this->params->get('tag_word', $this->recursive);
		$this->errorHandling = $this->params->get('error_handling', $this->errorHandling);
        $this->notFoundMessage = $this->params->get('not_found_message', $this->notFoundMessage);
		$this->messageClass = $this->params->get('message_css_class', $this->messageClass);
	}

	public function onContentPrepare($context, &$article, &$params, $page = 0)
	{
		if ($this->app->isAdmin())
		{
			return;
		}

		$tagProcessor = new \F2cAnywhere\ContentPluginTag($this->tagWord);

		if ($tagProcessor->containsTag($article->text)) {
			$tagProcessor
				->setCallback(array($this, 'processTags'))
				->parse($article->text);
		}

		return true;
	}

	/**
	 * Callback for preg_replace_callback() called by TagProcessor::processTags().
	 * 
	 * A content tag has two possible embedding syntaxes:
	 *	{F2cAnywhere params/}
	 *	Matches will have the following fields:
	 *		$matches[0] = {F2cAnywhere params}fields{/F2cAnywhere}
	 *		$matches[1] = params
	 *		$matches[2] = "/"
	 * 
	 *	{F2cAnywhere params}content{/F2cAnywhere}
	 *	Matches will have the following fields:
	 *		$matches[0] = {F2cAnywhere params}fields{/F2cAnywhere}
	 *		$matches[1] = params
	 *		$matches[2] = "}{/F2cAnywhere}"
	 *		$matches[3] = content
	 */
	public function processTags($tag) {
		$f2cArticleContent = '';

		try
		{
            // If a F2C id is passed in the url, it will supersede the id in the plugin tag.
            if ($id = JFactory::getApplication()->input->getInt('f2cid'))
            {
                $tag['params']['id'] = $id;
            }

			$hasBody = !empty($tag['body']);
			$hasTemplate = array_key_exists('template', $tag['params']);

			// If we don't have a body, we need a template parameter
			if (!$hasBody && !$hasTemplate)
			{
				return $this->handleMessage(JText::_('PLG_F2CANYWHERE_ERROR_NO_PARAM_TEMPLATE'), $tag);
			}

			// Only one template allowed.
			if ($hasBody && $hasTemplate)
			{
				return $this->handleMessage(JText::_('PLG_F2CANYWHERE_ERROR_AMBIGUOUS_TEMPLATE'), $tag);
			}

            $f2cArticle = new \F2cAnywhere\F2cAnywhereArticle();

            if ($hasBody)
			{
				$f2cArticle->setTemplate(str_replace(array('[:', ':]'), array('{', '}'), $tag['body']));
			}
			else
			{
				$f2cArticle->setTemplateName($tag['params']['template']);
			}

            $model = new \F2cAnywhere\F2cAnywhereModelForms(array('ignore_request' => true));

            if (!empty($tag['params']['id']))
            {
                $model->setArticleId($tag['params']['id']);
            }
            if (!empty($tag['params']['catid']))
            {
                $model->setCategoryId($tag['params']['catid']);
            }
            if (!empty($tag['params']['authorid']))
            {
                $model->setAuthorId($tag['params']['authorid']);
            }
            if (!empty($tag['params']['contenttypeid']))
            {
                $model->setContentTypeId($tag['params']['contenttypeid']);
            }
            if (!empty($tag['params']['exclude']))
            {
                $model->setExcludeId($tag['params']['exclude']);
            }
            if (!empty($tag['params']['order']))
            {
                $model->setOrder($tag['params']['order']);
            }
            if (!empty($tag['params']['limit']))
            {
                $model->setLimit($tag['params']['limit']);
            }

			$forms = $model->getItems();

            if (!(is_array($forms) && count($forms)))
            {
                if ($this->notFoundMessage) {
                    return $this->handleMessage(JText::_('PLG_F2CANYWHERE_WARNING_NOTHING_FOUND'), $tag);
                }
                else
                {
                    return '';
                }
            }

			$f2cArticleContent = '';
			foreach ($forms as $form)
			{
                $form->tags = array();

                // Load item tags
                $form->extended = new \JRegistry($form->extended);
                $tagList = $form->extended->get('tags');

                if(!empty($tagList))
                {
                    $form->tags = explode(',', $tagList);
                }

				$f2cArticle->setForm($form);
				$f2cArticleContent .= $f2cArticle->getContent(true);
			}

            if (!empty($f2cArticleContent) && $this->recursive)
            {
                $tagProcessor = new \F2cAnywhere\ContentPluginTag($this->tagWord);

                if ($tagProcessor->containsTag($f2cArticleContent)) {
                    $tagProcessor
                        ->setCallback(array($this, 'processTags'))
                        ->parse($f2cArticleContent);
                }
            }

            return $f2cArticleContent;
		}
		catch (F2cAnywhereArticleException $e)
		{
			switch ($e->getCode())
			{
				case F2cAnywhereArticleException::TEMPLATE_NOT_FOUND:
					return $this->handleMessage(JText::sprintf('PLG_F2CANYWHERE_ERROR_TEMPLATE_NOT_FOUND', $tag['params']['template']), $tag);
					break;

				case F2cAnywhereArticleException::TEMPLATE_CACHING_ERROR:
					return $this->handleMessage(JText::_('PLG_F2CANYWHERE_ERROR_TEMPLATE_CACHING'), $tag);
					break;

				default:
                    return $this->handleMessage($e->getMessage(), $tag);
					break;
			}
		}
		catch (Exception $e)
		{
            return $this->handleMessage($e->getMessage(), $tag);
		}
	}

	protected function handleMessage($message, $tag, $extraText = '')
	{
        $returnValue = '';

        switch ($this->errorHandling)
        {
            case 'joomla':
                JFactory::getApplication()->enqueueMessage('<p>' . $message . '</p><p>' . $tag['tag'] . '</p>', 'warning');
                $returnValue = '';
                break;

            case 'content':
                $attributes = (empty($this->messageClass) ? '' : ' class="' . $this->messageClass . '"');
                $returnValue = '<div' . $attributes . '><p>' . $message . '</p><p>' . $tag['tag'] . '</p></div>';
                break;

            case 'ignore':
                break;
        }

        return $returnValue;
	}
}
