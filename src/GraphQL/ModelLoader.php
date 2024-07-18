<?php

namespace SilverStripe\Headless\GraphQL;

use SilverStripe\Assets\File;
use SilverStripe\Assets\Image;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\GraphQL\Schema\DataObject\InterfaceBuilder;
use SilverStripe\GraphQL\Schema\Exception\SchemaBuilderException;
use SilverStripe\GraphQL\Schema\Field\ModelField;
use SilverStripe\GraphQL\Schema\Interfaces\SchemaUpdater;
use SilverStripe\GraphQL\Schema\Schema;
use SilverStripe\GraphQL\Schema\Type\ModelType;
use SilverStripe\ORM\DataObject;

use ReflectionException;

use SilverStripe\Headless\GraphQL\CustomResolver;
use Ogilvy\Models\Elemental\TeamMember\ElementTeamMemberProfile;
use Ogilvy\Models\Elemental\FeaturedArticles\ElementFeaturedArticles;

use App\PageTypes\ProductPage;
use App\Models\Elemental\FeaturedBrands\ElementFeaturedBrands;
use App\Models\Elemental\RecipeCards\ElementRecipeCards;

use SilverStripe\Core\Injector\Injector;

class ModelLoader implements SchemaUpdater
{
    use Configurable;

    /**
     * @var array
     * @config
     */
    private static $included_dataobjects = [];

    /**
     * @var array
     * @config
     */
    private static $excluded_dataobjects = [];

    /**
     * @var array|null
     */
    private static $_cachedIncludedClasses;

    /**
     * @param Schema $schema
     * @throws ReflectionException
     * @throws SchemaBuilderException
     */
    public static function updateSchema(Schema $schema, array $config = []): void
    {
        $classes = static::getIncludedClasses();

        foreach ($classes as $class) {
            $schema->addModelbyClassName($class, function (ModelType $model) use ($schema) {
                $model->addAllFields();
                $model->addOperation('read');
                $model->addOperation('readOne');
                $sng = Injector::inst()->get($model->getModel()->getSourceClass());

                if ($sng instanceof SiteTree) {
                    $modelName = $schema->getConfig()->getTypeNameForClass('Page');
                    $interfaceName = InterfaceBuilder::interfaceName($modelName, $schema->getConfig());
                    $model->addField('breadcrumbs', [
                        'type' => "[{$interfaceName}!]!",
                        'property' => 'NavigationPath',
                        'plugins' => [
                            'paginateList' => false,
                        ]
                    ]);

                    $model->addField('children', "[$interfaceName!]!");
                    // Keep the base navigation query in its own space so users can customise
                    // "children" and "parent." This could also be done with aliases, but
                    // this allows for a really straightforward generation of a types definition file
                    $model->addField('navChildren', [
                        'type' => "[$interfaceName!]!",
                        'property' => 'Children',
                    ]);
                    $model->addField('navParent', [
                        'type' => $interfaceName,
                        'property' => 'Parent',
                    ], function (ModelField  $field) {
                        // if ParentID = 0, this comes back as new SiteTree(). Ensure it's a Page
                        // for consistency
                        $field->addResolverAfterware([static::class, 'ensurePage']);
                    });

                    $model->addField('metaObject', [
                        'type' => 'String',
                        'resolver' => [Injector::inst()->get(CustomResolver::class)::class, 'resolveMetaObject']
                    ]); 

                    $model->addField('basePageData', [
                        'type' => 'String',
                        'resolver' => [Injector::inst()->get(CustomResolver::class)::class, 'resolveBasePageData']
                    ]); 
                    
                    $model->addField('navigationData', [
                        'type' => 'String',
                        'resolver' => [Injector::inst()->get(CustomResolver::class)::class, 'resolveNavigationData']
                    ]); 
                }
                if ($sng instanceof File) {
                    $model->addField('absoluteLink', 'String');
                }
                if ($sng instanceof Image) {
                    $model->addField('width', 'Int');
                    $model->addField('height', 'Int');

                    $model->addField('relativeLink', [
                        'type' => 'String',
                        'property' => 'Link'
                    ]);
                }

                if (
                    $sng instanceof ElementFeaturedArticles ||
                    $sng instanceof ElementTeamMemberProfile
                ) {
                    $model->addField('sortData', [
                        'type' => 'String',
                        'resolver' => [Injector::inst()->get(CustomResolver::class)::class, 'resolveSortingData']
                    ]);
                }

                if( $sng instanceof ProductPage) {
                    $model->addField('stockistsExtra', [
                        'type' => 'String',
                        'resolver' => [Injector::inst()->get(CustomResolver::class)::class, 'resolveStockistManyMany']
                    ]);
                    
                    $model->addField('nextProduct', [
                        'type' => 'String',
                        'resolver' => [Injector::inst()->get(CustomResolver::class)::class, 'resolveNextProduct']
                    ]);
                }

                if( $sng instanceof ElementFeaturedBrands) {
                    $model->addField('brandsExtra', [
                        'type' => 'String',
                        'resolver' => [Injector::inst()->get(CustomResolver::class)::class, 'resolveBrandsManyMany']
                    ]);
                }

                if( $sng instanceof ElementRecipeCards) {
                    $model->addField('recipesExtra', [
                        'type' => 'String',
                        'resolver' => [Injector::inst()->get(CustomResolver::class)::class, 'resolveRecipesManyMany']
                    ]);
                }

                // Special case for link
                if ($model->getModel()->hasField('link') && !$model->getFieldByName('link')) {
                    $model->addField('link', 'String!');
                }
            });
        }
    }

    /**
     * @todo Make configurable
     * @return array
     * @throws ReflectionException
     */
    public static function getIncludedClasses(): array
    {
        if (self::$_cachedIncludedClasses) {
            return self::$_cachedIncludedClasses;
        }
        $blockList = static::config()->get('excluded_dataobjects');
        $allowList = static::config()->get('included_dataobjects');

        $classes = array_values(ClassInfo::subclassesFor(DataObject::class, false));
        $classes = array_filter($classes, function ($class) use ($blockList, $allowList) {
            $included = empty($allowList);
            foreach ($allowList as $pattern) {
                if (fnmatch($pattern, $class, FNM_NOESCAPE)) {
                    $included = true;
                    break;
                }
            }
            foreach ($blockList as $pattern) {
                if (fnmatch($pattern, $class, FNM_NOESCAPE)) {
                    $included = false;
                }
            }

            return $included;
        });
        sort($classes);
        $classes = array_combine($classes, $classes);
        self::$_cachedIncludedClasses = $classes;

        return $classes;
    }

    /**
     * @param DataObject $obj
     * @return bool
     * @throws ReflectionException
     */
    public static function includes(DataObject $obj): bool
    {
        $included =  true;
        $obj->invokeWithExtensions('updateModelLoaderIncluded', $included);
        if (!$included) {
            return false;
        }
        return in_array($obj->ClassName, static::getIncludedClasses());
    }

    public static function ensurePage($obj)
    {
        if (!$obj instanceof SiteTree) {
            return $obj;
        }
        if ($obj->ClassName === SiteTree::class) {
            return $obj->newClassInstance('Page');
        }

        return $obj;
    }

}
