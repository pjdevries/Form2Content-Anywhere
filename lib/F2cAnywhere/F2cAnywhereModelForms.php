<?php
/**
 * Created by PhpStorm.
 * User: dev2
 * Date: 3/7/15
 * Time: 1:50 PM
 */

namespace F2cAnywhere;

require_once JPATH_ADMINISTRATOR . '/components/com_form2content/tables/form.php';
if (!class_exists('Form2ContentModelForms'))
{
    require_once JPATH_ADMINISTRATOR . '/components/com_form2content/models/forms.php';
}

class F2cAnywhereModelForms extends \Form2ContentModelForms {

    protected function getStoreId($id = '')
    {
        $id .= parent::getStoreId($id);

        $id	.= ':' . $this->getState('f2canywhere.filter.id');
        $id	.= ':' . $this->getState('f2canywhere.filter.category_id');
        $id	.= ':' . $this->getState('f2canywhere.filter.author_id');
        $id	.= ':' . $this->getState('f2canywhere.filter.contenttype_id');
        $id	.= ':' . $this->getState('f2canywhere.filter.id');

        return $id;
    }

    protected function getListQuery()
    {
        $this->setState('filter.published', 1);

        $ordering = $this->getState('list.ordering', 'ordering');
        $this->setState('list.ordering', $ordering);

        $orderDirection = $this->getState('list.direction', 'ASC');
        $this->setState('list.direction', $orderDirection);

        $query = parent::getListQuery();
        $query
            ->select('cc.title AS catTitle, cc.alias AS catAlias')
            ->select('u.name AS author, u.username AS authorUsername, u.email as authorEmail');

        $this->f2cAnywhereFilterToQuery($query, 'a.id', $this->getState('f2canywhere.filter.id'));
        $this->f2cAnywhereFilterToQuery($query, 'a.catid', $this->getState('f2canywhere.filter.category_id'));
        $this->f2cAnywhereFilterToQuery($query, 'a.created_by', $this->getState('f2canywhere.filter.author_id'));
        $this->f2cAnywhereFilterToQuery($query, 'a.projectid', $this->getState('f2canywhere.filter.contenttype_id'));
        $this->f2cAnywhereFilterToQuery($query, 'a.id', $this->getState('f2canywhere.filter.exclude'), true);

        return $query;
    }

    private function f2cAnywhereFilterToQuery($query, $column, $value, $negate = false)
    {
        if (!empty($value)) {
            if (substr($value, 0, 1) == '!') {
                $negate = true;
                $value = substr($value, 1);
            }

            if (strpos($value, ',') !== false) {
                $query->where(sprintf('%s %s (%s)',
                    $column,
                    ($negate ? 'NOT IN' : 'IN'),
                    $value
                ));
            } else if (is_numeric($value)) {
                $query->where(sprintf('%s %s %s',
                    $column,
                    ($negate ? '<>' : '='),
                    $value
                ));
            }
        }
    }

    public function setArticleId($id)
    {
        if ($id === 'article')
        {
            $parsedId = $this->retrieveF2cIdFromCurrentArticle();
        }
        else
        {
            $parsedId = $this->parseId($id);
        }

        if (!empty($parsedId))
        {
            $this->setState('f2canywhere.filter.id', $parsedId);
        }
        else
        {
            throw new F2cAnywhereArticleException(\JText::_('PLG_F2CANYWHERE_ERROR_INVALID_ARTICLE_ID'));
        }

        return $this;
    }

    public function setCategoryId($id)
    {
        if ($id === 'article')
        {
            $parsedId = $this->retrieveCategoryIdFromCurrentArticle();
        }
        else if (strpos($id, '/') !== false)
        {
            $parsedId = $this->getCategoryIdFromCategoryPath(trim($id, '/'));
        }
        else
        {
            $parsedId = $this->parseId($id);
        }

        if (!empty($parsedId))
        {
            $this->setModelIdState('category_id', $parsedId);
        }
        else
        {
            throw new F2cAnywhereArticleException(\JText::_('PLG_F2CANYWHERE_ERROR_INVALID_CATEGORY_ID'));
        }

        return $this;
    }


    public function setContentTypeId($id)
    {
        $parsedId = $this->parseId($id);

        if (!empty($parsedId))
        {
            $this->setModelIdState('contenttype_id', $parsedId);
        }
        else
        {
            throw new F2cAnywhereArticleException('Invalid data type for content type id!');
        }

        return $this;
    }

    public function setAuthorId($id)
    {
        if ($id === 'user')
        {
            if (!($parsedId = $this->getLoggedInUserId()))
            {
                $parsedId = -1;
            }
        }
        else if ($id === 'article')
        {
            $parsedId = $this->retrieveAuthorIdFromCurrentArticle();
        }
        else
        {
            $parsedId = $this->parseId($id);
        }

        if (!empty($parsedId))
        {
            $this->setModelIdState('author_id', $parsedId);
        }
        else
        {
            throw new F2cAnywhereArticleException(\JText::_('PLG_F2CANYWHERE_ERROR_INVALID_AUTHOR_ID'));
        }

        return $this;
    }

    public function setExcludeId($id)
    {
        if ($id === 'article')
        {
            $parsedId = $this->retrieveF2cIdFromCurrentArticle();
        }
        else
        {
            $parsedId = $this->parseId($id);
        }

        if (!empty($parsedId))
        {
            $this->setModelIdState('exclude', $parsedId, true);
        }
        else
        {
            throw new F2cAnywhereArticleException(\JText::_('PLG_F2CANYWHERE_ERROR_INVALID_EXCLUDE_ID'));
        }

        return $this;
    }

    public function setOrder($order)
    {
        if (!empty($order))
        {
            list($orderColumn, $orderDirection) = explode(' ', str_replace('  ', ' ', $order));

            if (!empty($orderColumn))
            {
                $this->setState('list.ordering', $orderColumn);
            }
            if (!empty($orderDirection))
            {
                $this->setState('list.direction', $orderDirection);
            }
        }

        return $this;
    }

    public function setLimit($limit)
    {
        $this->setState('list.start', 0);
        $this->setState('list.limit', $limit);

        return $this;
    }

    protected function parseId($id)
    {
        $parsedId = null;

        if (is_array($id))
        {
            $parsedId = implode(',', $id);
        }
        else if (is_string($id))
        {
            $parsedId = $id;
        }
        else if (is_int($id))
        {
            $parsedId = strval($id);
        }

        return $parsedId;
    }

    private function setModelIdState($filterParam, $value, $forceF2cAnywhere = false)
    {
        if ($value)
        {
            if (substr($value, 0, 1) !== '!' && strpos($value, ',') === false && !$forceF2cAnywhere)
            {
                $this->setState('filter.' . $filterParam, $value);
            }
            else
            {
                $this->setState('f2canywhere.filter.' . $filterParam, $value);
            }
        }

    }

    protected function retrieveF2cIdFromCurrentArticle()
    {
        $f2cId = 0;

        if (($articleId = $this->retrieveCurrentArticleId()))
        {
            $f2cId = $this->retrieveF2cIdFromCurrentArticleId($articleId);
        }

        return $f2cId;
    }

    protected function getLoggedInUserId()
    {
        $userId = 0;

        if (($user = \JFactory::getUser()))
        {
            $userId = $user->id;
        }

        return $userId;
    }

    protected function retrieveAuthorIdFromCurrentArticle()
    {
        $authorId = 0;

        if (($articleId = $this->retrieveCurrentArticleId()))
        {
            $tableContent = \JTable::getInstance('Content', 'JTable');
            if ($tableContent->load($articleId))
            {
                $authorId = $tableContent->created_by;
            }
        }

        return $authorId;
    }

    protected function retrieveF2cIdFromCurrentArticleId($articleId)
    {
        $f2cId = 0;

        $tableForm = \JTable::getInstance('Form', 'Form2ContentTable');
        if ($tableForm->load($articleId))
        {
            $f2cId = $tableForm->id;
        }

        return $f2cId;
    }

    protected function retrieveCategoryIdFromCurrentArticle()
    {
        $input = \JFactory::getApplication()->input;

        $option = $input->getCmd('option', '');
        if ($option !== 'com_content')
        {
            return 0;
        }

        $view = $input->getCmd('view', '');
        if ($view !== 'article')
        {
            return 0;
        }

        $catId = $input->getInt('catid', 0);
        if (!$catId)
        {
            $articleId = $input->getInt('id', 0);

            if (!$articleId)
            {
                return 0;
            }
            else
            {
                $catId = $this->getCategoryIdByArticleId($articleId);
            }
        }

        return $catId;
    }

    protected function getCategoryIdByArticleId($articleId)
    {
        $categoryId = 0;

        $tableContent = \JTable::getInstance('Content', 'JTable');
        if ($tableContent->load($articleId))
        {
            $categoryId = $tableContent->catid;
        }

        return $categoryId;
    }

    protected function getCategoryIdFromCategoryPath($categoryPath)
    {
        $categoryId = 0;

        $tableCategories = \JTable::getInstance('Category', 'JTable');
        if ($tableCategories->load(array('path' => $categoryPath)))
        {
            $categoryId = $tableCategories->id;
        }

        return $categoryId;
    }

    protected function retrieveCurrentArticleId()
    {
        $input = \JFactory::getApplication()->input;

        $option = $input->getCmd('option', '');
        if ($option !== 'com_content') {
            return 0;
        }

        $view = $input->getCmd('view', '');
        if ($view !== 'article') {
            return 0;
        }

        $articleId = $input->getInt('id', 0);

        return $articleId;
    }
}