<?php
namespace F2cAnywhere;

class F2cAnywhereArticleException extends \Exception
{
	const ARTICLE_KEY_MISSING = 1;
	const DB_QUERY_ERROR = 2;
	const CODE_UNSUPPORTED = 3;
	const TEMPLATE_NOT_FOUND = 4;
	const TEMPLATE_CACHING_ERROR = 5;
}