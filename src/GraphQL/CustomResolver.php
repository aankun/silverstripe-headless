<?php

namespace SilverStripe\Headless\GraphQL;

use SilverStripe\Assets\Image;
use SilverStripe\Core\Environment;
use SilverStripe\ORM\DataObject;
use SilverStripe\CMS\Model\SiteTree;
use GraphQL\Type\Definition\ResolveInfo;

class CustomResolver
{
  private static $priority = 1;

  /**
   * @param DataObject $obj
   * @param array $args
   * @param array $context
   * @param ResolveInfo $info
   * @return mixed|null
   * @see VersionedDataObject
   */
  
  public static function resolveBaseUrl(DataObject $obj, array $args, array $context, ResolveInfo $info)
  {
    $baseUrl = Environment::getEnv('NEXTJS_BASE_URL');
    return $baseUrl ? $baseUrl : '';
  }
}
