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

  public static function resolveImageBySize(DataObject $obj, array $args, array $context, ResolveInfo $info)
  {
    $imageObj = Image::get()->byID($obj->ID);
    $relativeUrl = '';
    if ($imageObj) {
      $processed = $imageObj;
      $fieldName = $info->fieldName;
      switch ($fieldName) {
        case 'xlImage':
          $processed = $imageObj->FitMax(1920, 1920);
          break;
        case 'lgImage':
          $processed = $imageObj->FitMax(1080, 1080);
          break;
        case 'mdImage':
          $processed = $imageObj->FitMax(800, 800);
          break;
        case 'smImage':
          $processed = $imageObj->FitMax(640, 640);
          break;
        case 'xsImage':
          $processed = $imageObj->FitMax(480, 480);
          break;
      }

      $urls = explode('/assets/', $processed->AbsoluteURL);
      $relativeUrl = '/assets/' . $urls[1];
    }
    return $relativeUrl;
  }

  public static function resolveBaseUrl(DataObject $obj, array $args, array $context, ResolveInfo $info)
  {
    $baseUrl = Environment::getEnv('NEXTJS_BASE_URL');
    return $baseUrl ? $baseUrl : '';
  }
}
