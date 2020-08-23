<?php
/**
 * @link https://github.com/tunecino/yii2-nested-rest
 * @copyright Copyright (c) 2016 Salem Ouerdani
 * @license http://opensource.org/licenses/BSD-3-Clause
 */

namespace tunecino\nestedrest;

use Yii;
use yii\web\UrlRuleInterface;
use yii\base\BaseObject;
use yii\helpers\Inflector;
use yii\helpers\StringHelper;
use yii\base\InvalidConfigException;

/**
 * UrlRule is a custom implementation that creates multi instances of a UrlRuleInterface[] to generate nested rules based in model relations.
 * @author Salem Ouerdani <tunecino@gmail.com>
 */
class UrlRule extends BaseObject implements UrlRuleInterface
{
    /**
     * @var string class name of the model which will be used to generate related rules.
     * The model class must implement [[ActiveRecordInterface]].
     * This property must be set.
     */
    public $modelClass;
    /**
     * @var array list of relation names as defined in model class.
     * This property must be set.
     */
    public $relations = [];
    /**
     * @var string name of the resource. used to generating the related 'prefix'.
     */
    public $resourceName;
    /**
     * @var string name of the attribute name used as a foreign key in the related model. also used to build the 'prefix'.
     */
    public $linkAttribute;
    /**
     * @var string name of the module to use as a prefix when generating the list of the related controllers.
     */
    public $modulePrefix;
    /**
     * @var array list of tokens that should be replaced for each pattern. The keys are the token names,
     * and the values are the corresponding replacements.
     * @see patterns
     */
    public $tokens = [
        '{id}' => '<id:\\d[\\d,]*>',
        '{IDs}' => '<IDs:\\d[\\d,]*>',
    ];
    /**
     * @var array list of possible patterns and the corresponding actions for creating the URL rules.
     * The keys are the patterns and the values are the corresponding actions.
     * The format of patterns is `Verbs Pattern`, where `Verbs` stands for a list of HTTP verbs separated
     * by comma (without space). If `Verbs` is not specified, it means all verbs are allowed.
     * `Pattern` is optional. It will be prefixed with [[prefix]]/[[controller]]/,
     * and tokens in it will be replaced by [[tokens]].
     */
    public $patterns = [
        'GET,HEAD {IDs}' => 'nested-view',
        'GET,HEAD' => 'nested-index',
        'POST' => 'nested-create',
        'PUT {IDs}' => 'nested-link',
        'DELETE {IDs}' => 'nested-unlink',
        'DELETE' => 'nested-unlink-all',
        '{id}' => 'options',
        '' => 'options',
    ];
    /**
     * @var array list of acceptable actions. If not empty, only the actions within this array
     * will have the corresponding URL rules created.
     * @see patterns
     */
    public $only = [];
    /**
     * @var array list of actions that should be excluded. Any action found in this array
     * will NOT have its URL rules created.
     * @see patterns
     */
    public $except = [];
    /**
     * @var array patterns for supporting extra actions in addition to those listed in [[patterns]].
     * The keys are the patterns and the values are the corresponding action IDs.
     * These extra patterns will take precedence over [[patterns]].
     * @see patterns
     */
    public $extraPatterns = [];
    /**
     * @var array the default configuration for creating each collection of URL rules related to a model relation.
     */
    private $config = [
        'class' => 'yii\rest\UrlRule'
    ];

    private $_rulesFactory;
    /**
     * @var bool whether to automatically pluralize the URL names for controllers.
     * If true, a controller ID will appear in plural form in URLs. For example, `user` controller
     * will appear as `users` in URLs.
     * @see controller
     */
    public $pluralize = true;

    /**
     * Returns the UrlRule instance used to generate related rules to each model.
     * @return UrlRuleInterface[]
     * @see config
     */
    protected function getRulesFactory()
    {
        return $this->_rulesFactory;
    }

    /**
     * Sets the UrlRule instance used to generate related rules to each model.
     * @param $config
     * @see config
     */
    protected function setRulesFactory($config)
    {
        $this->_rulesFactory = Yii::createObject($config);
    }

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        if (empty($this->modelClass)) {
            throw new InvalidConfigException('"modelClass" must be set.');
        }

        if (empty($this->relations)) {
            throw new InvalidConfigException('"relations" must be set.');
        }

        $this->config['patterns'] = $this->patterns;
        $this->config['tokens'] = $this->tokens;
        if (!empty($this->only)) {
            $this->config['only'] = $this->only;
        }
        if (!empty($this->except)) {
            $this->config['except'] = $this->except;
        }
        if (!empty($this->extraPatterns)) {
            $this->config['extraPatterns'] = $this->extraPatterns;
        }
    }

    /**
     * @inheritdoc
     */
    public function createUrl($manager, $route, $params)
    {
        if ($this->rulesFactory) {
            unset($params['relativeClass'], $params['relationName'], $params['linkAttribute']);
            return $this->rulesFactory->createUrl($manager, $route, $params);
        }
        return false;
    }

    /**
     * @inheritdoc
     */
    public function parseRequest($manager, $request)
    {
        $modelName = Inflector::camel2id(StringHelper::basename($this->modelClass));

        if (isset($this->resourceName)) {
            $resourceName = $this->resourceName;
        } else {
            $resourceName = $this->pluralize ? Inflector::pluralize($modelName) : $modelName;
        }

        $link_attribute = isset($this->linkAttribute) ? $this->linkAttribute : $modelName . '_id';
        $this->config['prefix'] = $resourceName . '/<' . $link_attribute . ':\d+>';

        foreach ($this->relations as $key => $value) {
            if (is_int($key)) {
                $relation = $value;
                $urlName = $this->pluralize ? Inflector::camel2id(Inflector::pluralize($relation)) : Inflector::camel2id($relation);
                $controller = Inflector::camel2id(Inflector::singularize($relation));
            } else {
                $relation = $key;
                if (is_array($value)) {
                    list($urlName, $controller) = [key($value), current($value)];
                } else {
                    $urlName = $this->pluralize ? Inflector::camel2id(Inflector::pluralize($relation)) : Inflector::camel2id($relation);
                    $controller = $value;
                }
            }

            if (YII_DEBUG) {
                (new $this->modelClass)->getRelation($relation);
            }

            $modulePrefix = isset($this->modulePrefix) ? $this->modulePrefix . '/' : '';
            $this->config['controller'][$urlName] = $modulePrefix . $controller;

            $this->setRulesFactory($this->config);
            $routeObj = $this->rulesFactory->parseRequest($manager, $request);

            if ($routeObj) {
                $routeObj[1]['relativeClass'] = $this->modelClass;
                $routeObj[1]['relationName'] = $relation;
                $routeObj[1]['linkAttribute'] = $link_attribute;
                return $routeObj;
            }
        }

        return false;
    }
}
