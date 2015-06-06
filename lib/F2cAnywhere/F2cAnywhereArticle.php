<?php
namespace F2cAnywhere;

class F2cAnywhereArticle
{
	protected $db = null;

	protected $form = null;

	protected $templatePath = null;
	protected $templateName = null;
	protected $template = null;
	
	protected $f2cArticleData = null;
	protected $content = '';
	
	const UUID_NAMESPACE = "508d8862-9271-43db-b6ec-f03a95894be1";

	public function __construct()
	{
        require_once(JPATH_SITE.'/components/com_form2content/utils.form2content.php');
        require_once(JPATH_SITE.'/components/com_form2content/factory.form2content.php');
        require_once(JPATH_SITE.'/components/com_form2content/models/form.php');

        \JLoader::registerPrefix('F2c', JPATH_SITE . '/components/com_form2content/libraries/form2content');

		$this->db = \JFactory::getDbo();
		$this->templatePath = \F2cFactory::getConfig()->get('template_path');
	}

	public function renderArticle()
	{
		$this->setArticleData();
		$this->parseArticleData();

		return $this;
	}

	protected function setArticleData()
	{
		$this->f2cArticleData = null;

		if ($this->form)
		{
			$query = $this->db->getQuery(true);

			$query->select('fc.id AS fieldcontentid, ' . $this->form->id . ' as formid, pf.id as fieldid, fc.attribute, fc.content, ft.name, pf.*');
			$query->from('#__f2c_projectfields pf');
			$query->join('LEFT', '#__f2c_fieldtype ft on pf.fieldtypeid = ft.id');
			$query->join('LEFT', '#__f2c_fieldcontent fc on pf.id = fc.fieldid and formid = ' . $this->form->id);
			$query->where('pf.projectid = ' . $this->form->projectid);
			$query->order('pf.ordering, pf.fieldtypeid');

			$this->db->setQuery($query);

			try
			{
				$fieldContentList = $this->db->loadObjectList();
			}
			catch (\Exception $e)
			{
				throw new F2cAnywhereArticleException('Database error while selecting article field data.', F2cAnywhereArticleException::DB_QUERY_ERROR, $e);
			}

			$modelForm = new \Form2contentModelForm();
			$fieldContentList = $modelForm->createFormDataObjects($fieldContentList);

			$this->form->fields = $fieldContentList[$this->form->id];

			$this->f2cArticleData = $this->form;
		}
	}
	
	protected function parseArticleData()
	{
		$this->content = '';

		if ($this->f2cArticleData)
		{

			$parser = new \F2cParser();
			if (!empty($this->template))
			{
				$parser->addTemplate('string:' . $this->template, F2C_TEMPLATE_INTRO);
			}
			else if (!empty($this->templateName))
			{
				$parser->addTemplate($this->templateName, F2C_TEMPLATE_INTRO);
			}
			$parser->addVars($this->f2cArticleData);

			$this->content = $parser->parseIntro();
		}
	}

	protected function saveToFile($template)
	{
		$name = '';
		
		if (!empty($template))
		{
			$uuid = new UUID();
			$fileName = $uuid->v5(self::UUID_NAMESPACE, $template) . '.tpl';
			$filePath = Path::Combine($this->templatePath, $fileName);

			if (!JFile::exists($filePath))
			{
				file_put_contents($filePath, $template);
			}
		}
		
		return $fileName;
	}

	public function getForm()
	{
		return $this->form;
	}

	public function setForm($form)
	{
		$this->form = $form;
		
		return $this;
	}

	public function getTemplateName()
	{
		return $this->templateName;
	}

	public function setTemplateName($templateName)
	{
		if (!\JFile::exists(\Path::Combine($this->templatePath, $templateName)))
		{
			throw new \F2cAnywhereArticleException('Form2Content template not found.', \F2cAnywhereArticleException::TEMPLATE_NOT_FOUND);
		}
		
		$this->templateName = $templateName;

		return $this;
	}

	public function getTemplate()
	{
		return $this->template;
	}

	public function setTemplate($template, $saveToFile = false)
	{
        if ($saveToFile)
        {
            try
            {
                $this->templateName = $this->saveToFile($template);
            }
            catch (Exception $e)
            {
                throw new \F2cAnywhereArticleException('Unable to save template to file.', \F2cAnywhereArticleException::TEMPLATE_CACHING_ERROR, $e);
            }
        }		
        else
        {
            $this->template = $template;
        }

		return $this;
	}

	public function getContent($forceRender = false)
	{
		if (empty($this->content) || $forceRender)
		{
			$this->renderArticle();
		}

		return $this->content;
	}
	
	public function clearContent()
	{
		$this->content = '';
		
		return $this;
	}
}