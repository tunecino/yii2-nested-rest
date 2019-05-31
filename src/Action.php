<?php
/**
 * @link https://github.com/tunecino/yii2-nested-rest
 * @copyright Copyright (c) 2016 Salem Ouerdani
 * @license http://opensource.org/licenses/BSD-3-Clause
 */

namespace tunecino\nestedrest;

use Yii;
use yii\base\InvalidConfigException;
use yii\web\NotFoundHttpException;
use yii\helpers\StringHelper;

/**
 * Action is the base class for nested action classes that depends on the custom UrlRule class.
 *
 * @author Salem Ouerdani <tunecino@gmail.com>
 */
class Action extends \yii\rest\Action
{
    /**
     * @var string class name of the related model.
     * This should be provided by the UrlClass within queryParams.
     */
    protected $relativeClass;
    /**
     * @var string name of the resource. used to generating the related 'prefix'.
     * This should be provided by the UrlClass within queryParams.
     */
    protected $relationName;
    /**
     * @var string name of the attribute name used as a foreign key in the related model. also used to build the 'prefix'.
     * This should be provided by the UrlClass within queryParams.
     */
    protected $linkAttribute;
    /**
     * @var primary key value of the linkAttribute.
     * This should be provided by the UrlClass within queryParams.
     * @see linkAttribute
     */
    protected $relative_id;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        $params = Yii::$app->request->queryParams;

        if ($this->expectedParams($params) === false) {
            throw new InvalidConfigException("unexpected configurations.");
        }

        $this->relativeClass = $params['relativeClass'];
        $this->relationName  = $params['relationName'];
        $this->linkAttribute = $params['linkAttribute'];
        $this->relative_id   = $params[$this->linkAttribute];
    }

    /**
     * Checks if the expected params that should be provided by the custom UrlClass are not missing.
     * @return Bolean.
     */
    protected function expectedParams($params)
    {
        $expected = ['relativeClass', 'relationName', 'linkAttribute'];
        foreach ($expected as $attr) {
            if (isset($params[$attr]) === false || ($attr === 'linkAttribute' && isset($params[$params[$attr]]) === false)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Finds the related model.
     * @return \yii\db\ActiveRecordInterface.
     * @throws NotFoundHttpException if not found.
     */
    public function getRelativeModel()
    {
        $relativeClass = $this->relativeClass;
        $relModel = $relativeClass::findOne($this->relative_id);

        if ($relModel === null) {
            throw new NotFoundHttpException(StringHelper::basename($relativeClass) . " '$this->relative_id' not found.");
        }

        if ($this->checkAccess) {
            call_user_func($this->checkAccess, $this->id, $relModel);
        }

        return $relModel;
    }

    /**
     * Finds the model or the list of models corresponding
     * to the specified primary keys values within the relative model retreived by [[getRelativeModel()]].
     * @param string $IDs should hold the list of IDs related to the models to be loaded.
     * it must be a string of the primary keys values separated by commas.
     * @return \yii\db\ActiveRecordInterface
     * @throws NotFoundHttpException if not found or not related.
     */
    public function findCurrentModels($IDs)
    {
        $modelClass = $this->modelClass;
        $pk = $modelClass::primaryKey()[0];
        $ids = preg_split('/\s*,\s*/', $IDs, -1, PREG_SPLIT_NO_EMPTY);
        $getter = 'get' . $this->relationName;

        $relModel = $this->getRelativeModel();
        $q = $relModel->$getter()->andWhere([$pk => $ids]);

        $ci = count($ids);
        $model = $ci > 1 ? $q->all() : $q->one();

        if ($model === null || (is_array($model) && count($model) !== $ci)) {
            throw new NotFoundHttpException("Not found or unrelated objects.");
        }

        return $model;
    }
}
