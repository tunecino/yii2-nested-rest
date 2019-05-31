<?php
/**
 * @link https://github.com/tunecino/yii2-nested-rest
 * @copyright Copyright (c) 2016 Salem Ouerdani
 * @license http://opensource.org/licenses/BSD-3-Clause
 */

namespace tunecino\nestedrest;

use Yii;
use yii\base\Model;
use yii\helpers\Url;
use yii\helpers\ArrayHelper;
use yii\web\ServerErrorHttpException;

/**
 * CreateAction implements the API endpoint for creating a new model from the given data and linking it with its related model.
 *
 * @author Salem Ouerdani <tunecino@gmail.com>
 */
class CreateAction extends Action
{
    /**
     * @var string the scenario to be assigned to the new model before it is validated and saved.
     */
    public $scenario = Model::SCENARIO_DEFAULT;
    /**
     * @var string the scenario to be assigned to the model representing 
     * the junction table related data before it is validated and saved.
     */
    public $viaScenario = Model::SCENARIO_DEFAULT;
    /**
     * @var string the wrapper name of the junction table related data
     * that should be expected in BodyParams.
     */
    public $viaWrapper = null;

    /**
     * Creates a new model and link it to the relative model.
     * @return \yii\db\ActiveRecordInterface the model newly created
     * @throws ServerErrorHttpException if there is any error when creating the model
     */
    public function run()
    {
        if ($this->checkAccess) {
            call_user_func($this->checkAccess, $this->id);
        }

        $relModel = $this->getRelativeModel();
        $relType = $relModel->getRelation($this->relationName);

        $isManyToMany = ($relType->multiple === true && $relType->via !== null);
        $isManyToMany_viaClass = ($relType->multiple === true && is_array($relType->via));

        $model = new $this->modelClass(['scenario' => $this->scenario]);

        $bodyParams = Yii::$app->getRequest()->getBodyParams();
        $viaData = $this->viaWrapper ? ArrayHelper::remove($bodyParams, $this->viaWrapper) : $bodyParams;

        // special case: when poor many-to-many configs -> try to manually guess junction's extraColumns
        if (!empty($viaData) && $isManyToMany && $isManyToMany_viaClass === false && $this->viaWrapper === null) {
            $model_attributes = $model->safeAttributes();
            $junction = [];
            foreach ($viaData as $key => $value) {
                if (!in_array($key, $model_attributes)) {
                    $junction[$key] = $value;
                }
            }
            $viaData = $junction;
        }

        $model->load($bodyParams, '');

        if ($model->save() === false && !$model->hasErrors()) {
            throw new ServerErrorHttpException('Failed to update the object for unknown reason.');
        } else if ($model->hasErrors()) {
            return $model;
        }

        $id = implode(',', array_values($model->getPrimaryKey(true)));

        if ($isManyToMany) {
            $extraColumns = $viaData === null ? [] : $viaData;

            if ($isManyToMany_viaClass) {
                $viaRelation = $relType->via[1];
                $viaClass = $viaRelation->modelClass;

                $viaModel = new $viaClass;
                $viaModel->scenario = $this->viaScenario;

                if ($this->checkAccess) {
                    call_user_func($this->checkAccess, $this->id, $viaModel);
                }

                $modelClass = $this->modelClass;
                $pk = $modelClass::primaryKey()[0];

                $attributes = array_merge([
                    $this->linkAttribute => $this->relative_id,
                    $relType->link[$pk] => $id
                ], $extraColumns);

                $viaModel->load($attributes, '');

                if ($viaModel->save() === false && !$viaModel->hasErrors()) {
                    throw new ServerErrorHttpException('Failed to update the object for unknown reason.');
                } else if ($viaModel->hasErrors()) {
                    return $viaModel;
                }
            } else {
                $relModel->link($this->relationName, $model, $extraColumns);
            }
        } else {
            $relModel->link($this->relationName, $model);
        }

        $response = Yii::$app->getResponse();
        $response->setStatusCode(201);
        $response->getHeaders()->set('Location', Url::to('', true) . '/' . $id);

        return $model;
    }
}
