<?php
namespace app\util;

use Slim\Slim;

class MyTwigCustomExtension extends \Twig_Extension
{
    public function getName()
    {
        return 'mySlim';
    }

    public function getFunctions()
    {
        return array(
            new \Twig_SimpleFunction('slugify', array($this, 'slugify'))
        );
    }

    public function slugify($text, $appName = 'default')
    {
        $text = preg_replace('~[^\\pL\d]+~u', '-', $text);
		$text = trim($text, '-');
		$text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
		$text = strtolower($text);
		$text = preg_replace('~[^-\w]+~', '', $text);
		if (empty($text)){
			return 'n-a';
		}
		return $text;
    }
}
