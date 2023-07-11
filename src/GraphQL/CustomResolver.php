<?php

namespace SilverStripe\Headless\GraphQL;

use SilverStripe\ORM\DataObject;
use SilverStripe\CMS\Model\SiteTree;
use GraphQL\Type\Definition\ResolveInfo;
use SilverStripe\SiteConfig\SiteConfig;
use Ogilvy\Models\Elemental\TeamMember\ElementTeamMemberProfile;

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

  public static function resolveMetaObject(DataObject $obj, array $args, array $context, ResolveInfo $info)
  {
    $sitetreeObj = SiteTree::get()->byID($obj->ID);
    $array = [];

    if($sitetreeObj){
      $siteConfig = SiteConfig::current_site_config();

      $array['metaTitle'] = $sitetreeObj->metaTitle ? $sitetreeObj->metaTitle : $sitetreeObj->Title;
      $array['metaDescription'] = $sitetreeObj->MetaDescription ? $sitetreeObj->MetaDescription : $siteConfig->MetaSiteDescription;
      $array['canonical'] = $sitetreeObj->MetaCanonicalURL ? $sitetreeObj->MetaCanonicalURL : $sitetreeObj->AbsoluteLink();
      $array['siteName'] = $siteConfig->Title;

      $imageObj = $sitetreeObj->MetaImage();
      if ( !$imageObj->exists() ) {
        $imageObj = $siteConfig->MetaSiteImage();
      }
      $array['twitterImage'] = $imageObj->exists() ? $imageObj->FocusFill(1024,512)->AbsoluteURL : '';
      $array['ogImage'] = $imageObj->exists() ? $imageObj->FocusFill(1200,628)->AbsoluteURL : '';
      $array['mimeType'] = $imageObj->exists() ? $imageObj->MimeType : '';
      $array['imageTitle'] = $imageObj->exists() ? $imageObj->Title : '';

      $array['ogImageWidth'] = 1200;
      $array['ogImageHeight'] = 628;
    }

    return $sitetreeObj ? json_encode($array) : '';
  }

  public static function resolveTeamMemberSort(DataObject $obj, array $args, array $context, ResolveInfo $info)
  {
    $elementTeamMember = ElementTeamMemberProfile::get()->byID($obj->ID);
    $sortData = [];
    if ($elementTeamMember) {
      foreach($elementTeamMember->MemberProfiles() as $memberProfile) {
        $sortData[$memberProfile->SortOrder] = $memberProfile->ID;
      }
      ksort($sortData);
    }
    return json_encode(array_values($sortData));
  }
}
